<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca;

/**
 * The result of an audit: the advisories of the installed packages, and the abandoned packages.
 */
final class AuditResult
{
    /**
     * Constructor
     *
     * @param array<\PieceOfCake2\SecuritySca\Advisory> $advisories Advisories
     * @param array<string, string|null> $abandoned Abandoned packages and the package to use instead, if any
     * @param int $packages Number of the audited packages
     * @param string|null $composerVersion Version of Composer, e.g. `2.8.12`
     */
    public function __construct(
        public array $advisories = [],
        public array $abandoned = [],
        public int $packages = 0,
        public ?string $composerVersion = null,
    ) {
    }

    /**
     * The number of the advisories of each risk, from the highest
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = array_fill_keys(Severity::ALL, 0);
        foreach ($this->advisories as $advisory) {
            $counts[$advisory->severity]++;
        }

        return $counts;
    }

    /**
     * Whether an advisory has the risk of the threshold or higher
     *
     * @param string $threshold Lowest risk that counts, or `none`
     * @return bool
     */
    public function fails(string $threshold): bool
    {
        foreach ($this->advisories as $advisory) {
            if (Severity::reaches($advisory->severity, $threshold)) {
                return true;
            }
        }

        return false;
    }
}
