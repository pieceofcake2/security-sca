# Fixing a Vulnerability

Most vulnerabilities are fixed by updating the package to a version that is not affected. This page goes from
the page of a vulnerability in the report to a fixed and deployed application, and to an application that stays
up to date.

## 1. Read the Advisory

The page of the vulnerability gives the package, the installed version, the affected versions and the links to
the advisory. The affected versions are constraints of Composer: `,` is "and", `|` is "or".

| Affected versions | Means |
| --- | --- |
| `<6.5.0` | every version before 6.5.0: 6.5.0 fixes it |
| `>=5.0.0,<5.2.27\|>=6.0.0,<6.0.6` | 5.0.0 to 5.2.26 and 6.0.0 to 6.0.5: 5.2.27 and 6.0.6 fix it |
| `>=2.0.0` | every version since 2.0.0: no fixed version yet |

Open the advisory (the first link of the page) and read:

- **The fixed version**, and the CHANGELOG of the package between the installed version and it.
- **What is vulnerable**: often a feature, a function or a setting. Find whether the application uses it, to know
  how urgent the fix is. Fix it anyway: the code can start to use it later.
- **A workaround**, when there is no fixed version yet.

The risk of the report is the one of the advisory (`critical`, `high`, `medium`, `low`). Fix the critical and
high ones first, and before the next release.

## 2. Update the Package

1. **Update it within the constraints of `composer.json`**: a patch or minor release usually fixes it.

   ```bash
   composer update vendor/package --with-dependencies
   ```

2. **When the constraint does not allow the fixed version**, raise it, after reading the changes of the package
   (its CHANGELOG or release notes, for the changes that break the application):

   ```bash
   composer require vendor/package:^2.4
   ```

3. **When the package is not required by the application**, but by another package, find which one, and what
   keeps the fixed version out:

   ```bash
   composer why vendor/package             # the packages that require it
   composer why-not vendor/package 2.4.1   # what prevents this version
   ```

   Update the package that requires it. When that package allows the fixed version but the lock file does not
   have it, `composer update vendor/package` is enough. When it does not allow it yet, require the fixed version
   in `composer.json` only if the other package works with it (its tests, or its issue tracker), or wait for its
   release and [ignore](README.md#ignoring-an-advisory) the advisory with the reason until then.

4. **When no version is fixed**, apply the workaround of the advisory (a setting, a feature not to use), replace
   the package if it is abandoned, or, when the vulnerability does not apply to the application, ignore it with
   the reason.

Then run the tests, commit `composer.lock`, deploy, and run the check again: the vulnerability is no longer in
the report.

## CakePHP

For a vulnerability of `pieceofcake2/cakephp`, update to the latest release of 2.12: its releases keep the API of
the applications, and its security fixes are published as
[advisories of the repository](https://github.com/pieceofcake2/cakephp/security/advisories).

```bash
composer update pieceofcake2/cakephp
```

Read its CHANGELOG for the notes of the release, e.g. a setting to add for a fix. The plugins of pieceofcake2
are updated the same way.

## Abandoned Packages

A package that its author has marked as abandoned gets no fixes, also for the vulnerabilities found later. The
report lists it with the package to use instead, when the author gave one, e.g. `symfony/debug` is replaced by
`symfony/error-handler`:

1. Find what uses it: `composer why symfony/debug`.
2. If the application requires it, replace it in `composer.json` and in the code (`composer remove`,
   `composer require` of the new package, then the namespaces and the calls that changed).
3. If another package requires it, update that package: its newer versions often moved to the replacement.

The abandoned packages do not fail the run, but plan their replacement: the next vulnerability of an abandoned
package has no fix.

## The Development Packages

The development packages (`require-dev`: PHPUnit, the tools of the build and of the checks) are not deployed,
but they run on the machines of the developers and in CI, with access to the code and to the secrets of CI. Fix
them too. `--no-dev` leaves them out of a report, e.g. for the report of a release that lists what is deployed.

## Stay Up to Date

The advisories are published for the versions already installed: an application that changes nothing gets new
vulnerabilities. Keep its packages up to date:

- **Check every week in CI**, also when nothing changed ([CI with GitHub Actions](ci.md)): the `schedule` of
  the workflow finds the advisories published since the last push.
- **Update with pull requests**: Dependabot of GitHub opens pull requests for the new versions of the packages,
  which CI tests:

  ```yaml
  # .github/dependabot.yml
  version: 2
  updates:
    - package-ecosystem: composer
      directory: /
      schedule:
        interval: weekly
  ```

- **See what is behind**: `composer outdated --direct` lists the packages of `composer.json` that have newer
  versions.
- **Commit `composer.lock`** of the application: the check reads it, and the deployment installs the same
  versions that were checked.
