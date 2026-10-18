<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca\Test;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PieceOfCake2\Report\Environment;
use PieceOfCake2\Report\HtmlRenderer;
use PieceOfCake2\SecuritySca\Advisory;
use PieceOfCake2\SecuritySca\AuditResult;
use PieceOfCake2\SecuritySca\Config;
use PieceOfCake2\SecuritySca\Package;
use PieceOfCake2\SecuritySca\ReportBuilder;

class ReportBuilderTest extends TestCase
{
    private static function html(Config $config, ?AuditResult $result = null): string
    {
        $result ??= AuditorTest::auditor()->audit(AuditorTest::PROJECT);
        $environment = new Environment();
        $environment->repository = 'acme/app';
        $environment->commit = 'a1b2c3d';
        $doc = (new ReportBuilder(ReportBuilder::translator($config->language)))->build(
            $result,
            $config,
            $environment,
            new DateTimeImmutable('2026-10-17 10:00:00'),
            new DateTimeImmutable('2026-10-17 10:00:08'),
        );

        return (new HtmlRenderer())->render($doc);
    }

    public function testPackages(): void
    {
        $html = self::html(new Config(project: AuditorTest::PROJECT));

        $this->assertStringContainsString('Packages of composer.lock', $html);
        $this->assertMatchesRegularExpression('#<tr><td class="mono">phpmailer/phpmailer</td><td class="mono">v6.0.0</td><td>Production・direct</td><td>LGPL-2.1-only</td><td><span class="t-high">\d+ vulnerabilities</span></td></tr>#', $html);
        $this->assertStringContainsString('<tr><td class="mono">symfony/debug</td><td class="mono">v4.4.44</td><td>Production・indirect</td><td>MIT</td><td>Abandoned</td></tr>', $html);
        $this->assertStringContainsString('<tr><td class="mono">phpunit/phpunit</td><td class="mono">9.6.0</td><td>Development・indirect</td><td>BSD-3-Clause</td><td>-</td></tr>', $html);
    }

    public function testReport(): void
    {
        $html = self::html(new Config(project: AuditorTest::PROJECT));

        $this->assertStringContainsString('<h1>Software Composition Analysis Report</h1>', $html);
        $this->assertStringContainsString('<div class="project">acme/app</div>', $html);
        $this->assertStringContainsString('<div class="verdict failed">', $html);
        $this->assertStringContainsString('<b class="t-high">5</b><span>High</span>', $html);
        $this->assertStringContainsString('<b class="">0</b><span>Critical</span>', $html);
        $this->assertStringNotContainsString('<span>Unknown</span>', $html);
        $this->assertMatchesRegularExpression('#<td>\s*High or higher\s*</td>#', $html);
        $this->assertMatchesRegularExpression('#<td>\s*5\s*</td>#', $html);
        $this->assertStringContainsString('Composer 2.8.12', $html);

        // The vulnerabilities by risk, then the abandoned packages
        $this->assertMatchesRegularExpression('#<h3>1\. High</h3>.*<h3>2\. Medium</h3>.*<h3>3\. Abandoned packages</h3>#s', $html);
        $this->assertStringContainsString('<span class="badge high">High</span>', $html);
        $this->assertStringContainsString('<td class="num">phpmailer/phpmailer</td>', $html);
        $this->assertStringContainsString('<td class="mono">v6.0.0</td>', $html);
        $this->assertStringContainsString('<a href="https://www.cve.org/CVERecord?id=CVE-2021-34551">', $html);
        $this->assertStringContainsString('<a href="https://github.com/advisories/GHSA-7q44-r25x-wm4q">', $html);
        $this->assertStringContainsString('<td class="mono">symfony/error-handler</td>', $html);
        $this->assertStringContainsString('.badge.critical', $html);
    }

    public function testPassedInJapanese(): void
    {
        $html = self::html(new Config(failOn: 'critical', project: AuditorTest::PROJECT, language: 'ja'));

        $this->assertStringContainsString('<h1>ソフトウェア構成分析報告書</h1>', $html);
        $this->assertStringContainsString('<div class="verdict passed">', $html);
        $this->assertMatchesRegularExpression('#<td>\s*緊急以上\s*</td>#', $html);
        $this->assertStringContainsString('<h3>1. 高</h3>', $html);
    }

    public function testNoVulnerability(): void
    {
        $html = self::html(new Config(failOn: 'none', project: AuditorTest::PROJECT, title: 'Audit'), new AuditResult([], [], 10));

        $this->assertStringContainsString('<h1>Audit</h1>', $html);
        $this->assertStringContainsString('<div class="verdict passed">', $html);
        $this->assertMatchesRegularExpression('#<td>\s*None \(the report only\)\s*</td>#', $html);
        $this->assertStringContainsString('No known vulnerability was found', $html);
    }

    public function testIgnoredAdvisories(): void
    {
        $ignored = new Advisory('PKSA-1', 'phpmailer/phpmailer', 'RCE on Windows', 'high', 'CVE-2021-34551', '<6.5.0', 'v6.0.0', ignoreReason: 'Windows only: the application runs on Linux');
        $html = self::html(new Config(project: AuditorTest::PROJECT), new AuditResult([], [], 10, ignored: [$ignored]));

        // Passed, with the ignored advisory on the cover and on a page of its own
        $this->assertStringContainsString('<div class="verdict passed">', $html);
        $this->assertMatchesRegularExpression('#<td>\s*1 \(audit\.ignore of composer\.json; they do not fail the audit\)\s*</td>#', $html);
        $this->assertMatchesRegularExpression('#<h3>1\. Ignored advisories</h3>#', $html);
        $this->assertStringContainsString('<span class="badge ignored">Ignored</span>', $html);
        $this->assertStringContainsString('Windows only: the application runs on Linux', $html);
    }

    public function testIgnoredInThePackages(): void
    {
        $packages = AuditorTest::auditor()->audit(AuditorTest::PROJECT)->packageList;
        $ignored = new Advisory('PKSA-1', 'phpmailer/phpmailer', 'RCE on Windows', 'high', ignoreReason: 'Windows only');
        $html = self::html(new Config(project: AuditorTest::PROJECT), new AuditResult([], [], 5, null, $packages, ignored: [$ignored]));

        $this->assertStringContainsString('<tr><td class="mono">phpmailer/phpmailer</td><td class="mono">v6.0.0</td><td>Production・direct</td><td>LGPL-2.1-only</td><td>1 ignored</td></tr>', $html);
    }

    public function testPackagesOfOtherCase(): void
    {
        // composer.lock writes the name as the package does, the advisories in lower case
        $packages = [new Package('Acme/Lib', '1.0.0', false, true, ['MIT'])];
        $html = self::html(new Config(project: AuditorTest::PROJECT), new AuditResult([new Advisory('PKSA-1', 'acme/lib', 'XSS', 'high')], ['acme/lib' => null], 1, null, $packages));

        $this->assertStringContainsString('<td class="mono">Acme/Lib</td><td class="mono">1.0.0</td><td>Production・direct</td><td>MIT</td><td><span class="t-high">1 vulnerabilities</span>、Abandoned</td>', $html);
    }

    public function testUnreachableRepositories(): void
    {
        $result = new AuditResult([], [], 10, unreachable: ['https://repo.example.com']);
        $html = self::html(new Config(project: AuditorTest::PROJECT), $result);

        $this->assertStringContainsString('Unreachable repositories', $html);
        $this->assertStringContainsString('https://repo.example.com', $html);
        $this->assertStringContainsString('Their advisories could not be checked', $html);
    }

    public function testTitle(): void
    {
        $advisory = new Advisory('PKSA-1', 'acme/lib', 'CVE-2020-15094: Prevent RCE', cve: 'CVE-2020-15094');
        $this->assertSame('CVE-2020-15094 Prevent RCE', ReportBuilder::title($advisory));

        $advisory = new Advisory('PKSA-2', 'acme/lib', 'Object injection', sources: [['name' => 'GitHub', 'id' => 'GHSA-7w4p-72j7-v7c2']]);
        $this->assertSame('GHSA-7w4p-72j7-v7c2 Object injection', ReportBuilder::title($advisory));

        $this->assertSame('PKSA-3 XSS', ReportBuilder::title(new Advisory('PKSA-3', 'acme/lib', 'XSS')));
    }

    public function testOnlyWebLinks(): void
    {
        $result = new AuditResult([new Advisory('PKSA-1', 'acme/lib', 'XSS', 'low', link: 'javascript:alert(1)')]);

        $this->assertStringNotContainsString('javascript:', self::html(new Config(), $result));
    }
}
