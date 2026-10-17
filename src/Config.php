<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca;

/**
 * The settings of a run: the options of `security-sca`, the environment variables of the same names, or the
 * settings file (`Settings`).
 *
 * | Option | Variable | Default | |
 * |---|---|---|---|
 * | `--pdf` | `SECURITY_SCA_PDF` | `tmp/security-sca.pdf` | Path of the PDF report |
 * | `--fail-on` | `SECURITY_SCA_FAIL_ON` | `high` | Lowest risk that fails: `critical`, `high`, `medium`, `low` or `none` |
 * | `--no-dev` | `SECURITY_SCA_NO_DEV` | | Leaves out the development packages |
 * | `--project` | | the current directory | Directory of `composer.json` and `composer.lock` |
 * | `--composer` | `SECURITY_SCA_COMPOSER` | `composer` | Composer command |
 * | `--language` | `SECURITY_REPORT_LANGUAGE` | `en` | Language of the report (`en`, `ja` or one of the locale directory) |
 * | `--locale-dir` | `SECURITY_REPORT_LOCALE_DIR` | | Directory of `<language>.json` files that add or replace messages |
 * | `--title` | `SECURITY_SCA_TITLE` | the title of the language | Title of the report |
 * | `--project-name` | `SECURITY_REPORT_PROJECT` | the name of the Composer root package | Name of the project under the title |
 * | `--note` | `SECURITY_SCA_NOTE` | | A note on the cover |
 * | `--settings` | `SECURITY_SETTINGS` | `config/security.php` or `app/Config/security.php` | Settings file |
 */
final class Config
{
    public const THRESHOLDS = [Severity::CRITICAL, Severity::HIGH, Severity::MEDIUM, Severity::LOW, 'none'];

    /**
     * Constructor
     *
     * @param string $pdf Path of the PDF report
     * @param string $failOn Lowest risk that fails, or `none`
     * @param bool $noDev Whether to leave out the development packages
     * @param string $project Directory of the project
     * @param string $composer Composer command
     * @param string $language Language of the report
     * @param string|null $localeDir Directory of more messages
     * @param string|null $title Title
     * @param string|null $projectName Name of the project
     * @param string|null $note Note on the cover
     */
    public function __construct(
        public string $pdf = 'tmp/security-sca.pdf',
        public string $failOn = Severity::HIGH,
        public bool $noDev = false,
        public string $project = '.',
        public string $composer = 'composer',
        public string $language = 'en',
        public ?string $localeDir = null,
        public ?string $title = null,
        public ?string $projectName = null,
        public ?string $note = null,
    ) {
    }
}
