# CHANGELOG

## Unreleased

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
