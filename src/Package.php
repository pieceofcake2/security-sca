<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca;

/**
 * A package of `composer.lock`.
 */
final class Package
{
    /**
     * Constructor
     *
     * @param string $name Name, e.g. `phpmailer/phpmailer`
     * @param string $version Version, e.g. `v6.0.0`
     * @param bool $dev Whether it is a development package (`packages-dev`)
     * @param bool $direct Whether `composer.json` of the project requires it, rather than another package
     * @param array<string> $licenses Licenses, e.g. `MIT`
     */
    public function __construct(
        public string $name,
        public string $version,
        public bool $dev = false,
        public bool $direct = false,
        public array $licenses = [],
    ) {
    }
}
