<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca;

use Closure;
use JsonException;
use RuntimeException;

/**
 * Runs `composer audit` on a project and reads its result.
 *
 * The audit reads `composer.lock` (`--locked`), so the packages need not be installed. Composer looks the
 * packages up in the security advisories of Packagist, which come from the GitHub Advisory Database and the
 * FriendsOfPHP security advisories.
 */
final class Auditor
{
    /**
     * @var \Closure(array<string>, string): array{0: int, 1: string, 2: string}
     */
    private Closure $run;

    /**
     * Constructor
     *
     * @param string $composer Composer command, e.g. `composer` or the path of `composer.phar`
     * @param (\Closure(array<string>, string): array{0: int, 1: string, 2: string})|null $run Runs a command in a
     *  directory and returns its exit code, output and error output, default: `proc_open()`
     */
    public function __construct(private string $composer = 'composer', ?Closure $run = null)
    {
        $this->run = $run ?? Closure::fromCallable([self::class, 'exec']);
    }

    /**
     * Audits a project
     *
     * @param string $dir Directory of the project, with `composer.json` and `composer.lock`
     * @param bool $noDev Whether to leave out the development packages (`require-dev`)
     * @return \PieceOfCake2\SecuritySca\AuditResult
     * @throws \RuntimeException When the project has no `composer.lock` or Composer fails
     */
    public function audit(string $dir, bool $noDev = false): AuditResult
    {
        $lock = $dir . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_file($lock)) {
            throw new RuntimeException(sprintf('%s is not found: run composer install or composer update first.', $lock));
        }
        $installed = self::installed($lock, $noDev);

        $command = [$this->composer, 'audit', '--locked', '--format=json', '--no-interaction', '--no-plugins', '--abandoned=report'];
        if ($noDev) {
            $command[] = '--no-dev';
        }
        [$code, $output, $error] = ($this->run)($command, $dir);

        try {
            $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException(sprintf('composer audit failed (exit code %d): %s', $code, trim($error !== '' ? $error : $output)));
        }
        if (!is_array($data)) {
            throw new RuntimeException('composer audit did not return a JSON object.');
        }

        [$versionCode, $version] = ($this->run)([$this->composer, '--version', '--no-ansi'], $dir);

        return new AuditResult(
            self::advisories($data['advisories'] ?? [], $installed),
            self::abandoned($data['abandoned'] ?? []),
            count($installed),
            $versionCode === 0 && preg_match('/(\d+\.\d+\.\d+\S*)/', $version, $matches) ? $matches[1] : null,
            self::packages($lock, $noDev, $dir . DIRECTORY_SEPARATOR . 'composer.json'),
        );
    }

    /**
     * The advisories of the JSON of `composer audit`, from the highest risk, then by package
     *
     * @param mixed $data `advisories` of the JSON
     * @param array<string, string> $installed Installed versions by package
     * @return array<\PieceOfCake2\SecuritySca\Advisory>
     */
    public static function advisories(mixed $data, array $installed = []): array
    {
        $advisories = [];
        foreach (is_array($data) ? $data : [] as $package => $items) {
            foreach (is_array($items) ? $items : [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sources = [];
                foreach (is_array($item['sources'] ?? null) ? $item['sources'] : [] as $source) {
                    if (is_array($source) && isset($source['name'], $source['remoteId'])) {
                        $sources[] = ['name' => (string)$source['name'], 'id' => (string)$source['remoteId']];
                    }
                }
                $name = (string)($item['packageName'] ?? $package);
                $reportedAt = self::string($item['reportedAt'] ?? null);
                $advisories[] = new Advisory(
                    (string)($item['advisoryId'] ?? ''),
                    $name,
                    (string)($item['title'] ?? ''),
                    Severity::normalize($item['severity'] ?? null),
                    self::string($item['cve'] ?? null),
                    (string)($item['affectedVersions'] ?? ''),
                    $installed[$name] ?? null,
                    $reportedAt !== null ? substr($reportedAt, 0, 10) : null,
                    self::string($item['link'] ?? null),
                    $sources,
                );
            }
        }
        usort($advisories, static fn(Advisory $a, Advisory $b): int => [Severity::rank($a->severity), $a->package, $a->name()] <=> [Severity::rank($b->severity), $b->package, $b->name()]);

        return $advisories;
    }

    /**
     * The packages of `composer.lock`, by name
     *
     * @param string $lock Path of `composer.lock`
     * @param bool $noDev Whether to leave out the development packages
     * @param string|null $json Path of `composer.json`, for the packages that the project requires
     * @return array<\PieceOfCake2\SecuritySca\Package>
     */
    public static function packages(string $lock, bool $noDev = false, ?string $json = null): array
    {
        $data = json_decode((string)file_get_contents($lock), true);
        $project = $json !== null && is_file($json) ? json_decode((string)file_get_contents($json), true) : null;
        $required = [];
        foreach (['require', 'require-dev'] as $key) {
            $required += is_array($project) && is_array($project[$key] ?? null) ? $project[$key] : [];
        }
        $packages = [];
        foreach ($noDev ? ['packages'] : ['packages', 'packages-dev'] as $key) {
            foreach (is_array($data) && is_array($data[$key] ?? null) ? $data[$key] : [] as $package) {
                if (!is_array($package) || !isset($package['name'], $package['version'])) {
                    continue;
                }
                $name = (string)$package['name'];
                $packages[$name] = new Package(
                    $name,
                    (string)$package['version'],
                    $key === 'packages-dev',
                    isset($required[$name]),
                    array_values(array_map('strval', array_filter((array)($package['license'] ?? []), 'is_scalar'))),
                );
            }
        }
        ksort($packages);

        return array_values($packages);
    }

    /**
     * The abandoned packages of the JSON of `composer audit`
     *
     * @param mixed $data `abandoned` of the JSON
     * @return array<string, string|null>
     */
    public static function abandoned(mixed $data): array
    {
        $abandoned = [];
        foreach (is_array($data) ? $data : [] as $package => $replacement) {
            $abandoned[(string)$package] = is_string($replacement) && $replacement !== '' ? $replacement : null;
        }
        ksort($abandoned);

        return $abandoned;
    }

    /**
     * The installed versions of `composer.lock`
     *
     * @param string $lock Path of `composer.lock`
     * @param bool $noDev Whether to leave out the development packages
     * @return array<string, string>
     */
    public static function installed(string $lock, bool $noDev = false): array
    {
        $data = json_decode((string)file_get_contents($lock), true);
        $installed = [];
        foreach ($noDev ? ['packages'] : ['packages', 'packages-dev'] as $key) {
            foreach (is_array($data) && is_array($data[$key] ?? null) ? $data[$key] : [] as $package) {
                if (is_array($package) && isset($package['name'], $package['version'])) {
                    $installed[(string)$package['name']] = (string)$package['version'];
                }
            }
        }

        return $installed;
    }

    /**
     * A non-empty string, or null
     *
     * @param mixed $value Value
     * @return string|null
     */
    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Runs a command
     *
     * @param array<string> $command Command and arguments
     * @param string $dir Working directory
     * @return array{0: int, 1: string, 2: string} Exit code, output and error output
     * @throws \RuntimeException When the command cannot run
     */
    private static function exec(array $command, string $dir): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Cannot run %s.', $command[0]));
        }
        $output = (string)stream_get_contents($pipes[1]);
        $error = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
