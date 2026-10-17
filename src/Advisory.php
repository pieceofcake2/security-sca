<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca;

/**
 * A known vulnerability of an installed package, as `composer audit` reports it.
 */
final class Advisory
{
    /**
     * Constructor
     *
     * @param string $id ID of the advisory, e.g. `PKSA-35kn-2ddp-d3p4`
     * @param string $package Package, e.g. `phpmailer/phpmailer`
     * @param string $title Title
     * @param string $severity Risk (`Severity`)
     * @param string|null $cve CVE ID, e.g. `CVE-2021-34551`
     * @param string $affectedVersions Affected versions, e.g. `<6.5.0`
     * @param string|null $installedVersion Installed version, from `composer.lock`
     * @param string|null $reportedAt Date of the report, e.g. `2021-06-16`
     * @param string|null $link Link to the advisory or the fix
     * @param array<array{name: string, id: string}> $sources Sources and their IDs, e.g. `GitHub` and its GHSA ID
     */
    public function __construct(
        public string $id,
        public string $package,
        public string $title,
        public string $severity = Severity::UNKNOWN,
        public ?string $cve = null,
        public string $affectedVersions = '',
        public ?string $installedVersion = null,
        public ?string $reportedAt = null,
        public ?string $link = null,
        public array $sources = [],
    ) {
    }

    /**
     * The name of the vulnerability: the CVE ID, else the GHSA ID of GitHub, else the ID of the advisory
     *
     * @return string
     */
    public function name(): string
    {
        if ($this->cve !== null) {
            return $this->cve;
        }
        foreach ($this->sources as $source) {
            if (str_starts_with($source['id'], 'GHSA-')) {
                return $source['id'];
            }
        }

        return $this->id;
    }
}
