# CHANGELOG

## Unreleased

- The vulnerabilities by risk show Critical, High, Medium and Low also without vulnerabilities (`None`,
  pieceofcake2/report 1.1), so that a reader tells a risk without vulnerabilities from one that was left out.
  Unknown is shown when there are some, as on the cover
- The samples of the docs are built with `docs/examples/report-sample.php`, from the recorded audit of the fixture
  project, and show the report of this version

## 1.0.4

- The audited packages of the report are on two pages, the production packages and the development packages,
  each with the numbers of packages, of direct ones and of vulnerable ones

## 1.0.3

- A large error output of Composer, e.g. many deprecation notices, no longer blocks the audit: it waited for the
  output while Composer waited to write its errors
- `--composer` takes words, e.g. `php composer.phar`, and its files are relative to the current directory, as the
  other paths of the options
- The packages of a filter list that `config.policy` of `composer.json` sets to report only (`"audit": "report"`)
  are of an unknown risk instead of critical, as Composer does not fail on them
- The example workflow of the docs is in the Composer package, which `docs/ci.md` links to
- A package whose name in `composer.lock` has capitals, e.g. of a private repository, gets its installed version on
  the page of its advisories and its vulnerabilities in the list of the packages: the advisories name it in lower
  case

## 1.0.2

- The advisories that `audit.ignore` of `composer.json` ignores are in the report, with their reasons: their
  number on the cover, a group "Ignored advisories" with a page for each, and their number in the list of the
  audited packages and in the summary of the command. They do not fail the run

## 1.0.1

- Needs Composer 2.8+: the command stops with a message when Composer is older, as `composer audit` of older
  versions cannot report the abandoned packages and the risks
- The packages that match a filter list of Composer 2.10+ (e.g. a list of malware) are reported as critical
  vulnerabilities
- The repositories that Composer could not reach, whose advisories are missing, are on the cover and printed
  by the command
- `--no-dev=0` keeps the development packages that the settings file leaves out
- A `null` in the settings file is not set, e.g. `getenv('...') ?: null`
- The direct packages of `composer.json` are found whatever the case of their names

## 1.0.0

- First release: `vendor/bin/security-sca` audits the Composer packages of a project with `composer audit` and
  writes a PDF report of the vulnerabilities by CVE and risk, and of the abandoned packages, with
  pieceofcake2/report. `--fail-on` sets the lowest risk that fails (default `high`)
- The settings file `config/security.php` (`app/Config/security.php`) keeps the settings of the security
  checks; its `sca` and `report` sections set the options
- The report lists the audited packages of `composer.lock`, with their versions, kinds (production or
  development, direct or indirect), licenses and vulnerabilities
