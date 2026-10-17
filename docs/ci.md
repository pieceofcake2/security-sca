# CI with GitHub Actions

[docs/examples/security-sca.yml](examples/security-sca.yml) audits the packages on each push and pull request,
and every week for the advisories published since, and uploads the report as a PDF (not a zip). The Ubuntu
runners have Google Chrome for the report.

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

- The weekly run (`schedule`) matters: new advisories are published for packages that did not change.
- `if: ${{ !cancelled() }}` uploads the report also when the audit fails the job, which is when it is needed.
- `--no-dev` audits the packages of production only, e.g. for the report of a release.
- Start with `--fail-on=none` to see the vulnerabilities of an existing application without failing the job,
  then raise it once the packages are updated.

The summary of the command is in the log of the job:

```text
6 vulnerabilities (5 high, 1 medium) in 18 packages, 1 abandoned packages. Report: tmp/security-sca.pdf
```
