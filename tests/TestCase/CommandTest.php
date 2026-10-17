<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PieceOfCake2\Report\Environment;
use PieceOfCake2\Report\PdfPrinter;
use PieceOfCake2\Report\ReportWriter;
use PieceOfCake2\SecuritySca\Command;

class CommandTest extends TestCase
{
    public function testConfig(): void
    {
        $config = Command::config(['--pdf=out/sca.pdf', '--fail-on=MEDIUM', '--no-dev', '--language=ja'], ['SECURITY_SCA_TITLE' => 'Audit', 'SECURITY_SCA_PDF' => 'env.pdf']);

        $this->assertNotNull($config);
        $this->assertSame('out/sca.pdf', $config->pdf);
        $this->assertSame('medium', $config->failOn);
        $this->assertTrue($config->noDev);
        $this->assertSame('ja', $config->language);
        $this->assertSame('Audit', $config->title);
        $this->assertSame('.', $config->project);
    }

    public function testDefaults(): void
    {
        $config = Command::config([]);

        $this->assertNotNull($config);
        $this->assertSame('tmp/security-sca.pdf', $config->pdf);
        $this->assertSame('high', $config->failOn);
        $this->assertFalse($config->noDev);
        $this->assertSame('composer', $config->composer);
    }

    public function testInvalidOptions(): void
    {
        $this->assertNull(Command::config(['--help']));

        $this->expectException(InvalidArgumentException::class);
        Command::config(['--fail-on=severe']);
    }

    public function testUnknownOption(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option: --verbose');
        Command::config(['--verbose']);
    }

    /**
     * Runs the command with the fixture
     *
     * @param array<string> $argv Arguments
     * @param string $output Receives the output
     * @return int
     */
    private static function runCommand(array $argv, string &$output = ''): int
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        $command = new Command($stdout, $stderr, AuditorTest::auditor(), new ReportWriter(PdfPrinter::fromEnv($_SERVER)), new Environment());
        $code = $command->run(array_merge(['--project=' . AuditorTest::PROJECT], $argv));
        rewind($stdout);
        rewind($stderr);
        $output = stream_get_contents($stdout) . stream_get_contents($stderr);

        return $code;
    }

    public function testRunWritesThePdf(): void
    {
        if (PdfPrinter::findChrome() === null && empty($_SERVER['REPORT_CHROME_BINARY'])) {
            $this->markTestSkipped('Chrome is not installed.');
        }
        $pdf = TMP . 'sca' . DS . 'report.pdf';
        $output = '';
        try {
            $this->assertSame(Command::FAILURE, self::runCommand(['--pdf=' . $pdf], $output));
            $this->assertStringContainsString('6 vulnerabilities (5 high, 1 medium) in 5 packages, 1 abandoned packages.', $output);
            $this->assertStringStartsWith('%PDF', (string)file_get_contents($pdf));

            $this->assertSame(Command::SUCCESS, self::runCommand(['--pdf=' . $pdf, '--fail-on=critical']));
        } finally {
            if (is_file($pdf)) {
                unlink($pdf);
                rmdir(dirname($pdf));
            }
        }
    }

    public function testRunWithAnInvalidOption(): void
    {
        $output = '';
        $this->assertSame(Command::ERROR, self::runCommand(['--fail-on=severe'], $output));
        $this->assertStringContainsString('--fail-on must be one of', $output);
        $this->assertStringContainsString('Usage: vendor/bin/security-sca', $output);
    }

    public function testRunWithoutLockFile(): void
    {
        $output = '';
        $this->assertSame(Command::ERROR, self::runCommand(['--project=' . TMP], $output));
        $this->assertStringContainsString('composer.lock is not found', $output);
    }
}
