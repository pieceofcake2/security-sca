# Security SCA Documentation

Software composition analysis (SCA) of an application: the known vulnerabilities of its Composer packages,
checked with `composer audit`, as an A4 PDF report by CVE and risk, in the design of the reports of
[pieceofcake2/report](https://pieceofcake2.github.io/report/). The exit code fails CI when a vulnerability
reaches a risk.

- [CI with GitHub Actions](ci.md)

It is one of the security checks of pieceofcake2, with
[pieceofcake2/security-sast](https://pieceofcake2.github.io/security-sast/) (the source code) and
pieceofcake2/security-dast (the running application); pieceofcake2/security-check runs them all.

## Requirements

- PHP 8.0+ and Composer 2.4+ (`composer audit`)
- `composer.lock` in the project: the audit reads it, so the packages need not be installed
- Google Chrome or Chromium 131+ to print the PDF, and on Linux `fonts-noto-cjk` for a Japanese report

## Installation

```bash
composer require --dev pieceofcake2/security-sca
```

## Usage

```bash
vendor/bin/security-sca --pdf=tmp/security-sca.pdf --language=ja
```

| Option | Environment variable | Default | |
| --- | --- | --- | --- |
| `--pdf=PATH` | `SECURITY_SCA_PDF` | `tmp/security-sca.pdf` | The PDF report |
| `--fail-on=RISK` | `SECURITY_SCA_FAIL_ON` | `high` | The lowest risk that fails: `critical`, `high`, `medium`, `low` or `none` |
| `--no-dev` | `SECURITY_SCA_NO_DEV` | | Leaves out the development packages (`require-dev`) |
| `--project=DIR` | | the current directory | The directory of `composer.json` and `composer.lock` |
| `--composer=COMMAND` | `SECURITY_SCA_COMPOSER` | `composer` | The Composer command |
| `--language=LANG` | `SECURITY_REPORT_LANGUAGE` | `en` | `en` or `ja`, or a language of `--locale-dir` |
| `--locale-dir=DIR` | `SECURITY_REPORT_LOCALE_DIR` | | `<language>.json` files that add or replace messages |
| `--title=TITLE` | `SECURITY_SCA_TITLE` | the title of the language | The title of the report |
| `--project-name=NAME` | `SECURITY_REPORT_PROJECT` | the name in `composer.json` | The project under the title |
| `--note=TEXT` | `SECURITY_SCA_NOTE` | | A note on the cover |

The exit code is `0` when no vulnerability reaches `--fail-on`, `1` when one does, and `2` when the audit or
the report fails. A vulnerability whose risk is not known fails only with `--fail-on=low`.

## The Report

1. **Cover**: the result, the numbers of vulnerabilities by risk, the repository, branch and commit, the
   number of audited packages, the threshold, and the date, environment and software of the run
2. **Vulnerabilities by risk**: Critical, High, Medium, Low and Unknown, then the abandoned packages; each
   links to its page
3. **A page for each vulnerability**: its CVE (or GHSA ID when it has no CVE) and title, risk, package,
   installed and affected versions, the IDs of the advisory, the date of the report, and the links to the
   advisory, the CVE record and the GitHub advisory

The vulnerabilities are those of the security advisories of Packagist at the time of the run: the GitHub
Advisory Database (reviewed advisories) and the FriendsOfPHP security advisories.

## Where the Vulnerabilities Come From

`composer audit` looks the packages of `composer.lock` up in the security advisories of Packagist, which come
from:

- the [GitHub Advisory Database](https://github.com/advisories?query=ecosystem%3Acomposer): the advisories that
  GitHub has reviewed. An advisory that a project has published on its repository appears after GitHub reviews
  it, which can take days
- the [FriendsOfPHP security advisories](https://github.com/FriendsOfPHP/security-advisories)

The risk is the one of the advisory (`critical`, `high`, `medium`, `low`); an advisory without one is
`unknown`. The report is the state of the advisories at the time of the run: run it again, e.g. every week in
CI, to see the advisories published since.

## Abandoned Packages

A package that its author has marked as abandoned on Packagist is listed after the vulnerabilities, with the
package to use instead when the author gave one. It gets no fixes, also for vulnerabilities found later. The
abandoned packages do not fail the run.

## Ignoring an Advisory

When an advisory does not apply to the application, e.g. a vulnerability of Windows only, or of a feature that
the application does not use, ignore it in `composer.json` with the reason (Composer 2.6+). It is then left out
of `composer audit` and of the report:

```json
{
    "config": {
        "audit": {
            "ignore": {
                "CVE-2021-34551": "Windows only: the application runs on Linux"
            }
        }
    }
}
```

The key is the CVE ID, the advisory ID (`PKSA-...`) or the ID of a source of the advisory, e.g. its GHSA ID. Update the package instead when you can:
an ignored advisory is easily forgotten.

## Troubleshooting

**`composer.lock is not found`**: run `composer install` (or `composer update`) in the project first, or give the
project with `--project`.

**`composer audit failed`**: the message of Composer follows, e.g. the network to `packagist.org` is not allowed,
or `composer.lock` is not valid. `composer audit` needs to download the advisories.

**The vulnerabilities of an advisory that was just published are missing**: it is not in the GitHub Advisory
Database yet (see [Where the Vulnerabilities Come From](#where-the-vulnerabilities-come-from)).

**`Chrome is needed to print the report`**: install Chrome or Chromium, or set `REPORT_CHROME_BINARY` (see
[pieceofcake2/report](https://pieceofcake2.github.io/report/)).
