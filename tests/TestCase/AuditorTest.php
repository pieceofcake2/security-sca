<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca\Test;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\SecuritySca\Auditor;
use RuntimeException;

class AuditorTest extends TestCase
{
    public const PROJECT = __DIR__ . DS . '..' . DS . 'fixtures' . DS . 'project';

    /**
     * An auditor whose Composer answers with the fixture
     *
     * @param array<array<string>> $commands Receives the commands
     * @param string|null $output Output of composer audit, default: the fixture
     * @return \PieceOfCake2\SecuritySca\Auditor
     */
    public static function auditor(array &$commands = [], ?string $output = null): Auditor
    {
        return new Auditor('composer', static function (array $command, string $dir) use (&$commands, $output): array {
            $commands[] = $command;
            if ($command[1] === '--version') {
                return [0, 'Composer version 2.8.12 2025-09-19 13:41:59', ''];
            }

            return [1, $output ?? (string)file_get_contents(__DIR__ . DS . '..' . DS . 'fixtures' . DS . 'audit.json'), ''];
        });
    }

    public function testAudit(): void
    {
        $commands = [];
        $result = self::auditor($commands)->audit(self::PROJECT);

        $this->assertSame(['composer', 'audit', '--locked', '--format=json', '--no-interaction', '--no-plugins', '--abandoned=report'], $commands[0]);
        $this->assertCount(6, $result->advisories);
        $this->assertSame(5, $result->packages);
        $this->assertSame('2.8.12', $result->composerVersion);
        $this->assertSame(['symfony/debug' => 'symfony/error-handler'], $result->abandoned);
        $this->assertSame(['critical' => 0, 'high' => 5, 'medium' => 1, 'low' => 0, 'unknown' => 0], $result->counts());

        // From the highest risk, then by package and name
        $first = $result->advisories[0];
        $this->assertSame('high', $first->severity);
        $this->assertSame('phpmailer/phpmailer', $first->package);
        $this->assertSame('v6.0.0', $first->installedVersion);
        $this->assertSame('medium', $result->advisories[5]->severity);
        $this->assertSame('symfony/http-kernel', $result->advisories[5]->package);
        $this->assertMatchesRegularExpression('/\A\d{4}-\d\d-\d\d\z/', (string)$first->reportedAt);
    }

    public function testNoDev(): void
    {
        $commands = [];
        $result = self::auditor($commands)->audit(self::PROJECT, true);

        $this->assertContains('--no-dev', $commands[0]);
        $this->assertSame(4, $result->packages);
    }

    public function testFails(): void
    {
        $result = self::auditor()->audit(self::PROJECT);

        $this->assertTrue($result->fails('high'));
        $this->assertFalse($result->fails('critical'));
        $this->assertFalse($result->fails('none'));
    }

    public function testNoVulnerability(): void
    {
        $commands = [];
        $result = self::auditor($commands, '{"advisories": [], "abandoned": []}')->audit(self::PROJECT);

        $this->assertSame([], $result->advisories);
        $this->assertSame([], $result->abandoned);
    }

    public function testWithoutLockFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer.lock is not found');

        self::auditor()->audit(TMP);
    }

    public function testComposerFails(): void
    {
        $auditor = new Auditor('composer', static fn(array $command, string $dir): array => [1, '', 'The lock file is not up to date']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer audit failed (exit code 1): The lock file is not up to date');

        $auditor->audit(self::PROJECT);
    }
}
