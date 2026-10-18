<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PieceOfCake2\SecuritySca\Settings;

class SettingsTest extends TestCase
{
    public function testOptions(): void
    {
        $file = TMP . 'settings-options.php';
        file_put_contents($file, "<?php\nreturn ['report' => ['language' => 'ja', 'localeDir' => 'locales', 'note' => 'Release', 'dir' => 'tmp/security/'], 'sca' => ['failOn' => 'low', 'noDev' => true]];\n");

        $this->assertSame([
            'language' => 'ja',
            'locale-dir' => 'locales',
            'note' => 'Release',
            'pdf' => 'tmp/security/security-sca.pdf',
            'fail-on' => 'low',
            'no-dev' => true,
        ], Settings::options('.', 'sca', $file, 'security-sca.pdf'));
    }

    public function testNullIsNotSet(): void
    {
        $file = TMP . 'settings-null.php';
        file_put_contents($file, "<?php\nreturn ['report' => ['note' => null], 'sca' => ['failOn' => null, 'noDev' => true]];\n");

        $this->assertSame(['no-dev' => true], Settings::options('.', 'sca', $file));
    }

    public function testNoFile(): void
    {
        $this->assertSame([], Settings::options(TMP, 'sca'));
    }

    public function testMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not found');
        Settings::options('.', 'sca', TMP . 'missing.php');
    }

    public function testNotAnArray(): void
    {
        $file = TMP . 'settings-string.php';
        file_put_contents($file, "<?php\nreturn 'sca';\n");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must return an array');
        Settings::options('.', 'sca', $file);
    }
}
