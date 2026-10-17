# CHANGELOG

## Unreleased

- First release: `vendor/bin/security-sca` audits the Composer packages of a project with `composer audit` and
  writes a PDF report of the vulnerabilities by CVE and risk, and of the abandoned packages, with
  pieceofcake2/report. `--fail-on` sets the lowest risk that fails (default `high`)
- The settings file `config/security.php` (`app/Config/security.php`) keeps the settings of the security
  checks; its `sca` and `report` sections set the options
