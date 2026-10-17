# Security SCA Documentation

Software composition analysis (SCA) of an application: the known vulnerabilities of its Composer packages,
checked with `composer audit`, as an A4 PDF report by CVE and risk, in the design of the reports of
[pieceofcake2/report](https://pieceofcake2.github.io/report/). The exit code fails CI when a vulnerability
reaches a risk.

It is one of the security checks of pieceofcake2, with pieceofcake2/security-sast (the source code) and
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

## GitHub Actions

[docs/examples/security-sca.yml](examples/security-sca.yml) audits the packages on each push and pull
request, and every week for the advisories published since, and uploads the report:

```yaml
- run: composer install --no-interaction --no-progress
- run: sudo apt-get install -y fonts-noto-cjk
- run: vendor/bin/security-sca --pdf=tmp/security-sca.pdf --fail-on=high --language=ja
- uses: actions/upload-artifact@v7
  if: ${{ !cancelled() }}
  with:
    path: tmp/security-sca.pdf
    archive: false
```
