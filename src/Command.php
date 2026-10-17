<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PieceOfCake2\Report\Environment;
use PieceOfCake2\Report\ReportWriter;
use Throwable;

/**
 * The command `security-sca`: audits the Composer packages of a project and writes the PDF report.
 *
 * The exit code is 0 when no vulnerability reaches `--fail-on`, 1 when one does, and 2 when the audit or the
 * report fails.
 */
final class Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const ERROR = 2;

    /**
     * Constructor
     *
     * @param resource $stdout Output
     * @param resource $stderr Error output
     * @param \PieceOfCake2\SecuritySca\Auditor|null $auditor Auditor, default: with the Composer command of the settings
     * @param \PieceOfCake2\Report\ReportWriter|null $writer Writer of the PDF
     * @param \PieceOfCake2\Report\Environment|null $environment Environment, default: detected
     */
    public function __construct(
        private $stdout = STDOUT,
        private $stderr = STDERR,
        private ?Auditor $auditor = null,
        private ?ReportWriter $writer = null,
        private ?Environment $environment = null,
    ) {
    }

    /**
     * Runs the command
     *
     * @param array<string> $argv Arguments, without the name of the command
     * @param array<string, mixed> $env Environment variables, e.g. `$_SERVER`
     * @return int Exit code
     */
    public function run(array $argv, array $env = []): int
    {
        try {
            $config = self::config($argv, $env);
        } catch (Throwable $e) {
            fwrite($this->stderr, $e->getMessage() . "\n\n" . self::usage());

            return self::ERROR;
        }
        if ($config === null) {
            fwrite($this->stdout, self::usage());

            return self::SUCCESS;
        }

        try {
            $timezone = new DateTimeZone(date_default_timezone_get());
            $startedAt = new DateTimeImmutable('now', $timezone);
            $result = ($this->auditor ?? new Auditor($config->composer))->audit($config->project, $config->noDev);
            $finishedAt = new DateTimeImmutable('now', $timezone);

            $translator = ReportBuilder::translator($config->language, $config->localeDir);
            $doc = (new ReportBuilder($translator))->build($result, $config, $this->environment ?? Environment::detect($config->project), $startedAt, $finishedAt);
            ($this->writer ?? new ReportWriter())->write($doc, $config->pdf);
        } catch (Throwable $e) {
            fwrite($this->stderr, 'security-sca: ' . $e->getMessage() . "\n");

            return self::ERROR;
        }

        $counts = $result->counts();
        $summary = [];
        foreach ($counts as $severity => $count) {
            if ($count > 0) {
                $summary[] = $count . ' ' . $severity;
            }
        }
        fwrite($this->stdout, sprintf(
            "%d vulnerabilities%s in %d packages, %d abandoned packages. Report: %s\n",
            count($result->advisories),
            $summary === [] ? '' : ' (' . implode(', ', $summary) . ')',
            $result->packages,
            count($result->abandoned),
            $config->pdf,
        ));

        return $result->fails($config->failOn) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The settings of the arguments and the environment variables, or null for `--help`
     *
     * @param array<string> $argv Arguments
     * @param array<string, mixed> $env Environment variables
     * @return \PieceOfCake2\SecuritySca\Config|null
     * @throws \InvalidArgumentException When an argument is invalid
     */
    public static function config(array $argv, array $env = []): ?Config
    {
        $value = static function (string $name) use ($env): ?string {
            $value = $env[$name] ?? null;

            return is_scalar($value) && trim((string)$value) !== '' ? (string)$value : null;
        };
        $options = [
            'pdf' => $value('SECURITY_SCA_PDF'),
            'fail-on' => $value('SECURITY_SCA_FAIL_ON'),
            'no-dev' => filter_var($value('SECURITY_SCA_NO_DEV') ?? false, FILTER_VALIDATE_BOOLEAN),
            'project' => null,
            'composer' => $value('SECURITY_SCA_COMPOSER'),
            'language' => $value('SECURITY_REPORT_LANGUAGE'),
            'locale-dir' => $value('SECURITY_REPORT_LOCALE_DIR'),
            'title' => $value('SECURITY_SCA_TITLE'),
            'project-name' => $value('SECURITY_REPORT_PROJECT'),
            'note' => $value('SECURITY_SCA_NOTE'),
        ];
        foreach ($argv as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                return null;
            }
            if ($arg === '--no-dev') {
                $options['no-dev'] = true;
                continue;
            }
            if (!preg_match('/\A--([a-z-]+)=(.*)\z/s', $arg, $matches) || !array_key_exists($matches[1], $options) || $matches[1] === 'no-dev') {
                throw new InvalidArgumentException(sprintf('Unknown option: %s', $arg));
            }
            $options[$matches[1]] = $matches[2];
        }

        $failOn = strtolower((string)($options['fail-on'] ?? Severity::HIGH));
        if (!in_array($failOn, Config::THRESHOLDS, true)) {
            throw new InvalidArgumentException(sprintf('--fail-on must be one of %s.', implode(', ', Config::THRESHOLDS)));
        }

        return new Config(
            (string)($options['pdf'] ?? 'tmp/security-sca.pdf'),
            $failOn,
            (bool)$options['no-dev'],
            (string)($options['project'] ?? '.'),
            (string)($options['composer'] ?? 'composer'),
            (string)($options['language'] ?? 'en'),
            $options['locale-dir'],
            $options['title'],
            $options['project-name'],
            $options['note'],
        );
    }

    /**
     * The help of the command
     *
     * @return string
     */
    public static function usage(): string
    {
        return <<<'TEXT'
Usage: vendor/bin/security-sca [options]

Audits the Composer packages of the project (composer audit) and writes a PDF report of the
vulnerabilities by CVE and risk.

Options:
  --pdf=PATH           PDF report (SECURITY_SCA_PDF, default: tmp/security-sca.pdf)
  --fail-on=RISK       Lowest risk that fails: critical, high, medium, low or none
                       (SECURITY_SCA_FAIL_ON, default: high)
  --no-dev             Leave out the development packages (SECURITY_SCA_NO_DEV)
  --project=DIR        Directory of composer.json and composer.lock (default: the current directory)
  --composer=COMMAND   Composer command (SECURITY_SCA_COMPOSER, default: composer)
  --language=LANG      en or ja (SECURITY_REPORT_LANGUAGE, default: en)
  --locale-dir=DIR     Directory of <language>.json files (SECURITY_REPORT_LOCALE_DIR)
  --title=TITLE        Title of the report (SECURITY_SCA_TITLE)
  --project-name=NAME  Name of the project (SECURITY_REPORT_PROJECT, default: the root package)
  --note=TEXT          Note on the cover (SECURITY_SCA_NOTE)
  --help               Show this help

Exit code: 0 passed, 1 a vulnerability reaches --fail-on, 2 error.

TEXT;
    }
}
