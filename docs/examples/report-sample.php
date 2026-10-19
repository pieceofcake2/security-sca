<?php
/**
 * Writes the sample reports of the documentation with the report of this version, from the recorded audit of the
 * fixture project of the tests (`tests/fixtures/audit.json` of `tests/fixtures/project`):
 *
 *     php docs/examples/report-sample.php
 *
 * It writes `docs/public/report-sample.pdf` and `report-sample-ja.pdf`, and, with `pdftoppm` (Poppler) and
 * `magick` (ImageMagick), the images of some of their pages, `docs/img/report-sample.png` and
 * `report-sample-ja.png`.
 */
declare(strict_types=1);

use PieceOfCake2\Report\Environment;
use PieceOfCake2\Report\ReportWriter;
use PieceOfCake2\Report\Row;
use PieceOfCake2\SecuritySca\Auditor;
use PieceOfCake2\SecuritySca\Config;
use PieceOfCake2\SecuritySca\ReportBuilder;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$project = $root . '/tests/fixtures/project';
// The recorded audit, as Composer 2.8.12 printed it
$auditor = new Auditor('composer', static function (array $command) use ($root): array {
    return $command[1] === '--version'
        ? [0, 'Composer version 2.8.12 2025-09-19 13:41:59', '']
        : [1, (string)file_get_contents($root . '/tests/fixtures/audit.json'), ''];
});
$startedAt = new DateTimeImmutable('2026-10-17 14:28:21', new DateTimeZone('UTC'));
$software = 'PHP 8.5.11　Composer 2.8.12　pieceofcake2/security-sca 1.x-dev';
// The pages of the image: the cover, the vulnerabilities by risk, the page of a vulnerability and of the packages
$pages = [1, 2, 3, -2];

$magick = trim((string)shell_exec('command -v magick')) !== '' ? 'magick' : 'convert';
foreach (['en' => 'report-sample', 'ja' => 'report-sample-ja'] as $language => $name) {
    $doc = (new ReportBuilder(ReportBuilder::translator($language)))->build(
        $auditor->audit($project),
        new Config(project: $project, language: $language),
        new Environment(),
        $startedAt,
        $startedAt,
    );
    // The software of the recorded audit, not of this machine
    $last = count($doc->run) - 1;
    $doc->run[$last] = new Row($doc->run[$last]->label, $software);

    $pdf = $root . '/docs/public/' . $name . '.pdf';
    (new ReportWriter())->write($doc, $pdf);
    echo $pdf . "\n";

    $count = preg_match('/Pages:\s+(\d+)/', (string)shell_exec('pdfinfo ' . escapeshellarg($pdf)), $matches) ? (int)$matches[1] : 1;
    $png = $root . '/docs/img/' . $name . '.png';
    $tmp = sys_get_temp_dir() . '/report-sample-' . bin2hex(random_bytes(4));
    mkdir($tmp);
    $images = [];
    foreach ($pages as $page) {
        // A negative page counts from the end
        $page = $page < 0 ? $count + $page + 1 : $page;
        exec(sprintf('pdftoppm -png -scale-to-x 595 -scale-to-y 842 -f %d -l %d %s %s', $page, $page, escapeshellarg($pdf), escapeshellarg($tmp . '/p' . $page)));
        $images = array_merge($images, glob($tmp . '/p' . $page . '*.png') ?: []);
    }
    // The pages side by side on a grey background: 595 x 842 (A4 at 72 dpi), 12px apart from the edges and 24px
    // from each other
    exec(sprintf('%s %s -bordercolor "#e9e9e9" -border 12 +append %s', $magick, implode(' ', array_map('escapeshellarg', $images)), escapeshellarg($png)));
    array_map('unlink', glob($tmp . '/*') ?: []);
    rmdir($tmp);
    echo $png . "\n";
}
