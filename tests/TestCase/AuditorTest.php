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

        $this->assertSame(['composer', '--version', '--no-ansi'], $commands[0]);
        $this->assertSame(['composer', 'audit', '--locked', '--format=json', '--no-interaction', '--no-plugins', '--abandoned=report'], $commands[1]);
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

    public function testPackages(): void
    {
        $result = self::auditor()->audit(self::PROJECT);

        $packages = array_map(static fn($package): string => sprintf('%s %s %s %s %s', $package->name, $package->version, $package->dev ? 'dev' : 'prod', $package->direct ? 'direct' : 'indirect', implode(',', $package->licenses)), $result->packageList);
        $this->assertSame([
            'phpmailer/phpmailer v6.0.0 prod direct LGPL-2.1-only',
            'phpunit/phpunit 9.6.0 dev indirect BSD-3-Clause',
            'pieceofcake2/cakephp v2.12.2 prod indirect MIT',
            'symfony/debug v4.4.44 prod indirect MIT',
            'symfony/http-kernel v4.4.0 prod indirect MIT',
        ], $packages);
        $this->assertCount(4, self::auditor()->audit(self::PROJECT, true)->packageList);
    }

    public function testNoDev(): void
    {
        $commands = [];
        $result = self::auditor($commands)->audit(self::PROJECT, true);

        $this->assertContains('--no-dev', $commands[1]);
        $this->assertSame(4, $result->packages);
    }

    public function testOldComposer(): void
    {
        $auditor = new Auditor('composer', static fn(array $command, string $dir): array => [0, 'Composer version 2.7.9 2024-09-04 14:43:28', '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Composer 2.8.0 or later is needed');

        $auditor->audit(self::PROJECT);
    }

    public function testFilterAndUnreachableRepositories(): void
    {
        $output = (string)json_encode([
            'advisories' => [],
            'abandoned' => [],
            'filter' => [
                'Symfony/Debug' => [[
                    'packageName' => 'Symfony/Debug',
                    'listName' => 'malware',
                    'constraint' => '>=4.4',
                    'url' => 'https://example.com/malware/1',
                    'reason' => 'Steals credentials',
                    'id' => 'MAL-1',
                    'source' => 'example',
                ]],
            ],
            'unreachable-repositories' => ['https://repo.example.com'],
        ]);
        $commands = [];
        $result = self::auditor($commands, $output)->audit(self::PROJECT);

        $this->assertCount(1, $result->advisories);
        $advisory = $result->advisories[0];
        $this->assertSame('critical', $advisory->severity);
        $this->assertSame('MAL-1', $advisory->id);
        $this->assertSame('Listed in malware: Steals credentials', $advisory->title);
        $this->assertSame('v4.4.44', $advisory->installedVersion);
        $this->assertSame('https://example.com/malware/1', $advisory->link);
        $this->assertSame(['https://repo.example.com'], $result->unreachable);
        $this->assertTrue($result->fails('critical'));
    }

    public function testIgnoredAdvisories(): void
    {
        // As Composer 2.8+ prints the advisories that audit.ignore of composer.json ignores
        $output = (string)json_encode([
            'advisories' => [],
            'ignored-advisories' => ['phpmailer/phpmailer' => [[
                'advisoryId' => 'PKSA-35kn-2ddp-d3p4',
                'packageName' => 'phpmailer/phpmailer',
                'affectedVersions' => '<6.5.0',
                'title' => 'RCE affecting Windows hosts via UNC paths to translation files',
                'cve' => 'CVE-2021-34551',
                'severity' => 'high',
                'ignoreReason' => 'Windows only: the application runs on Linux',
            ]]],
            'abandoned' => [],
        ]);
        $commands = [];
        $result = self::auditor($commands, $output)->audit(self::PROJECT);

        $this->assertSame([], $result->advisories);
        $this->assertCount(1, $result->ignored);
        $this->assertSame('CVE-2021-34551', $result->ignored[0]->cve);
        $this->assertSame('v6.0.0', $result->ignored[0]->installedVersion);
        $this->assertSame('Windows only: the application runs on Linux', $result->ignored[0]->ignoreReason);
        // They do not fail the audit
        $this->assertFalse($result->fails('low'));
    }

    public function testDirectIsCaseInsensitive(): void
    {
        $dir = TMP . 'case' . DS;
        is_dir($dir) || mkdir($dir);
        copy(self::PROJECT . DS . 'composer.lock', $dir . 'composer.lock');
        file_put_contents($dir . 'composer.json', '{"require": {"PHPMailer/PHPMailer": "^6.0"}}');

        $packages = self::auditor()->audit($dir)->packageList;

        $this->assertTrue($packages[0]->direct);
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
