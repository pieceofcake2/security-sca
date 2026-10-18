<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca;

use DateTimeImmutable;
use PieceOfCake2\Report\Count;
use PieceOfCake2\Report\Cover;
use PieceOfCake2\Report\Document;
use PieceOfCake2\Report\Environment;
use PieceOfCake2\Report\Group;
use PieceOfCake2\Report\Item;
use PieceOfCake2\Report\Row;
use PieceOfCake2\Report\Translator;
use PieceOfCake2\Report\Verdict;

/**
 * Builds the report of an audit: the vulnerabilities by risk, one page for each, then the abandoned packages.
 */
final class ReportBuilder
{
    /**
     * The directory of the messages and the CSS of this package
     */
    public const RESOURCE_DIR = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources';

    /**
     * Constructor
     *
     * @param \PieceOfCake2\Report\Translator $translator Messages
     */
    public function __construct(private Translator $translator)
    {
    }

    /**
     * The messages of the report in a language: those of pieceofcake2/report, of this package and of a directory
     *
     * @param string $language Language
     * @param string|null $localeDir Directory of more messages
     * @return \PieceOfCake2\Report\Translator
     */
    public static function translator(string $language, ?string $localeDir = null): Translator
    {
        return new Translator($language, [self::RESOURCE_DIR . DIRECTORY_SEPARATOR . 'locales', $localeDir]);
    }

    /**
     * The report of an audit
     *
     * @param \PieceOfCake2\SecuritySca\AuditResult $result Audit
     * @param \PieceOfCake2\SecuritySca\Config $config Settings
     * @param \PieceOfCake2\Report\Environment $environment Where it ran
     * @param \DateTimeImmutable $startedAt Start
     * @param \DateTimeImmutable $finishedAt End
     * @return \PieceOfCake2\Report\Document
     */
    public function build(AuditResult $result, Config $config, Environment $environment, DateTimeImmutable $startedAt, DateTimeImmutable $finishedAt): Document
    {
        $cover = new Cover($this->translator);
        $title = $config->title ?? $this->t('title');
        $project = $config->projectName ?? self::projectName($config->project) ?? Cover::rootPackage();
        $passed = !$result->fails($config->failOn);

        $counts = [new Count(count($result->advisories), $this->t('count.advisories'))];
        foreach ($result->counts() as $severity => $count) {
            if ($severity !== Severity::UNKNOWN || $count > 0) {
                $counts[] = new Count($count, $this->t('severity.' . $severity), $count > 0 ? 't-' . $severity : '');
            }
        }

        $target = $cover->target($environment);
        $target[] = new Row($this->t('field.packages'), (string)$result->packages);
        if ($result->ignored !== []) {
            $target[] = new Row($this->t('field.ignored'), $this->t('ignored.count', ['count' => count($result->ignored)]));
        }
        if ($result->unreachable !== []) {
            $target[] = new Row($this->t('field.unreachable'), implode(', ', $result->unreachable), warning: $this->t('unreachable.warning'), monoValue: true);
        }
        $target[] = new Row($this->t('field.threshold'), $config->failOn === 'none'
            ? $this->t('threshold.none')
            : $this->t('threshold.value', ['severity' => $this->t('severity.' . $config->failOn)]));

        $software = ['PHP ' . PHP_VERSION];
        if ($result->composerVersion !== null) {
            $software[] = 'Composer ' . $result->composerVersion;
        }
        $software[] = Cover::package('pieceofcake2/security-sca');

        $note = $config->note ?? ($result->advisories === [] ? $this->t('note.none') . ' ' : '') . $this->t('note.source');

        return new Document(
            title: $title,
            verdict: new Verdict($passed, $this->t($passed ? 'verdict.passed' : 'verdict.failed'), $counts),
            columns: [$this->t('field.no'), $this->t('field.vulnerability'), $this->t('field.risk'), $this->t('field.package')],
            groups: $this->groups($result),
            project: $project,
            target: $target,
            run: array_merge(
                $cover->run($startedAt, $finishedAt, (float)($finishedAt->format('U.u') - $startedAt->format('U.u')), $environment),
                [new Row($this->t('field.software'), implode('　', array_filter($software)))],
            ),
            note: $note,
            listTitle: $this->t('section.advisories'),
            targetTitle: $this->t('section.target'),
            runTitle: $this->t('section.run'),
            headerLeft: Cover::headerLeft($title, $project),
            headerRight: $cover->headerRight($startedAt, $environment),
            css: (string)file_get_contents(self::RESOURCE_DIR . DIRECTORY_SEPARATOR . 'sca.css'),
            language: $this->translator->language(),
        );
    }

    /**
     * The name of the package of a project, from its `composer.json`
     *
     * @param string $dir Directory of the project
     * @return string|null
     */
    public static function projectName(string $dir): ?string
    {
        $file = $dir . DIRECTORY_SEPARATOR . 'composer.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        $name = is_array($data) ? ($data['name'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The vulnerabilities by risk, then the abandoned packages
     *
     * @param \PieceOfCake2\SecuritySca\AuditResult $result Audit
     * @return array<\PieceOfCake2\Report\Group>
     */
    private function groups(AuditResult $result): array
    {
        $groups = [];
        foreach (Severity::ALL as $severity) {
            $advisories = array_values(array_filter($result->advisories, static fn(Advisory $advisory): bool => $advisory->severity === $severity));
            if ($advisories === []) {
                continue;
            }
            $group = new Group(count($groups) + 1, $this->t('severity.' . $severity));
            foreach ($advisories as $i => $advisory) {
                $group->items[] = $this->advisory($group->no . '-' . ($i + 1), $advisory);
            }
            $groups[] = $group;
        }

        if ($result->abandoned !== []) {
            $group = new Group(count($groups) + 1, $this->t('group.abandoned'));
            $i = 0;
            foreach ($result->abandoned as $package => $replacement) {
                $group->items[] = new Item(
                    $group->no . '-' . ++$i,
                    $package,
                    'abandoned',
                    $this->t('badge.abandoned'),
                    $package,
                    [
                        new Row($this->t('field.package'), $package, monoValue: true),
                        new Row($this->t('field.replacement'), $replacement ?? $this->t('abandoned.none'), monoValue: $replacement !== null),
                    ],
                    $this->t('abandoned.description'),
                );
            }
            $groups[] = $group;
        }

        if ($result->ignored !== []) {
            $group = new Group(count($groups) + 1, $this->t('group.ignored'));
            foreach ($result->ignored as $i => $advisory) {
                $item = $this->advisory($group->no . '-' . ($i + 1), $advisory);
                // Ignored, whatever its risk: the risk is in the table of the page
                $item->badge = 'ignored';
                $item->badgeLabel = $this->t('badge.ignored');
                array_splice($item->meta, 1, 0, [new Row($this->t('field.ignoreReason'), $advisory->ignoreReason ?? $this->t('ignored.noReason'))]);
                $group->items[] = $item;
            }
            $groups[] = $group;
        }

        if ($result->packageList !== []) {
            $group = new Group(count($groups) + 1, $this->t('group.packages'));
            $group->items[] = new Item(
                $group->no . '-1',
                $this->t('packages.title'),
                // Not "list", the class of the list page, which breaks the page after it
                'inventory',
                $this->t('badge.list'),
                (string)count($result->packageList),
                [
                    new Row($this->t('packages.count'), (string)count($result->packageList)),
                    new Row($this->t('packages.direct'), (string)count(array_filter($result->packageList, static fn(Package $package): bool => $package->direct))),
                    new Row($this->t('packages.dev'), (string)count(array_filter($result->packageList, static fn(Package $package): bool => $package->dev))),
                ],
                $this->t('packages.description'),
                body: $this->packages($result),
            );
            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * The table of the audited packages, with their vulnerabilities
     *
     * @param \PieceOfCake2\SecuritySca\AuditResult $result Audit
     * @return string HTML
     */
    private function packages(AuditResult $result): string
    {
        $h = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $vulnerabilities = [];
        foreach ($result->advisories as $advisory) {
            $vulnerabilities[strtolower($advisory->package)] = ($vulnerabilities[strtolower($advisory->package)] ?? 0) + 1;
        }
        $ignored = [];
        foreach ($result->ignored as $advisory) {
            $ignored[strtolower($advisory->package)] = ($ignored[strtolower($advisory->package)] ?? 0) + 1;
        }

        $html = '<table class="packages"><colgroup><col class="name"><col class="version"><col class="kind"><col class="license"><col class="state"></colgroup>'
            . '<thead><tr><th>' . $h($this->t('field.package')) . '</th><th>' . $h($this->t('field.installed')) . '</th><th>'
            . $h($this->t('field.kind')) . '</th><th>' . $h($this->t('field.license')) . '</th><th>' . $h($this->t('field.state')) . '</th></tr></thead><tbody>';
        foreach ($result->packageList as $package) {
            $kind = $this->t($package->dev ? 'kind.dev' : 'kind.prod') . '・' . $this->t($package->direct ? 'kind.direct' : 'kind.indirect');
            $state = [];
            // The advisories give the names in lower case, composer.lock as the package writes them
            $key = strtolower($package->name);
            if (isset($vulnerabilities[$key])) {
                $state[] = '<span class="t-high">' . $h($this->t('state.vulnerabilities', ['count' => $vulnerabilities[$key]])) . '</span>';
            }
            if (isset($ignored[$key])) {
                $state[] = $h($this->t('state.ignored', ['count' => $ignored[$key]]));
            }
            if (array_key_exists($key, array_change_key_case($result->abandoned))) {
                $state[] = $h($this->t('badge.abandoned'));
            }
            $html .= '<tr><td class="mono">' . $h($package->name) . '</td><td class="mono">' . $h($package->version) . '</td><td>' . $h($kind) . '</td><td>'
                . $h($package->licenses !== [] ? implode(', ', $package->licenses) : '-') . '</td><td>' . ($state !== [] ? implode('、', $state) : '-') . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * The item of a vulnerability
     *
     * @param string $no Number
     * @param \PieceOfCake2\SecuritySca\Advisory $advisory Advisory
     * @return \PieceOfCake2\Report\Item
     */
    private function advisory(string $no, Advisory $advisory): Item
    {
        $meta = [
            new Row($this->t('field.risk'), $this->t('severity.' . $advisory->severity)),
            new Row($this->t('field.package'), $advisory->package, monoValue: true),
        ];
        if ($advisory->installedVersion !== null) {
            $meta[] = new Row($this->t('field.installed'), $advisory->installedVersion, monoValue: true);
        }
        $meta[] = new Row($this->t('field.affected'), $advisory->affectedVersions, monoValue: true);
        if ($advisory->cve !== null) {
            $meta[] = new Row($this->t('field.cve'), $advisory->cve, monoValue: true);
        }
        $ids = [$advisory->id];
        foreach ($advisory->sources as $source) {
            $ids[] = $source['name'] . ': ' . $source['id'];
        }
        $meta[] = new Row($this->t('field.advisory'), implode(', ', array_filter($ids)), monoValue: true);
        if ($advisory->reportedAt !== null) {
            $meta[] = new Row($this->t('field.reported'), $advisory->reportedAt);
        }

        return new Item(
            $no,
            self::title($advisory),
            $advisory->severity,
            $this->t('severity.' . $advisory->severity),
            $advisory->package,
            $meta,
            body: $this->links($advisory),
        );
    }

    /**
     * The title of a vulnerability: its name and title, without the name at the start of the title
     *
     * @param \PieceOfCake2\SecuritySca\Advisory $advisory Advisory
     * @return string
     */
    public static function title(Advisory $advisory): string
    {
        $name = $advisory->name();
        $title = (string)preg_replace('/\A' . preg_quote($name, '/') . '\s*[:\-–]?\s*/i', '', $advisory->title);

        return trim($name . ' ' . $title);
    }

    /**
     * The links of a vulnerability: its link, its CVE record and its GitHub advisory
     *
     * @param \PieceOfCake2\SecuritySca\Advisory $advisory Advisory
     * @return string HTML
     */
    private function links(Advisory $advisory): string
    {
        $links = [];
        if ($advisory->link !== null && preg_match('#\Ahttps?://#i', $advisory->link)) {
            $links[] = $advisory->link;
        }
        if ($advisory->cve !== null) {
            $links[] = 'https://www.cve.org/CVERecord?id=' . rawurlencode($advisory->cve);
        }
        foreach ($advisory->sources as $source) {
            if (str_starts_with($source['id'], 'GHSA-')) {
                $links[] = 'https://github.com/advisories/' . rawurlencode($source['id']);
            }
        }
        $links = array_values(array_unique($links));
        if ($links === []) {
            return '';
        }

        $html = '<ul class="links">';
        foreach ($links as $link) {
            $url = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<li><a href="' . $url . '">' . $url . '</a></li>';
        }

        return $html . '</ul>';
    }

    /**
     * A message
     *
     * @param string $key Key
     * @param array<string, string|int|float> $params Placeholders
     * @return string
     */
    private function t(string $key, array $params = []): string
    {
        return $this->translator->translate($key, $params);
    }
}
