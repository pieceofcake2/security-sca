# GitHub Actions での CI

[docs/examples/security-sca.yml](../examples/security-sca.yml) は、push と pull request のたびと、その後に公開された
アドバイザリのために毎週、パッケージを調べ、報告書を (zip ではなく) PDF のままアップロードします。Ubuntu のランナーには、
報告書のための Google Chrome が入っています。

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

- 毎週の実行 (`schedule`) が大切です。変えていないパッケージにも、新しいアドバイザリが公開されます。
- `if: ${{ !cancelled() }}` で、調査がジョブを失敗にしたときも報告書をアップロードします。報告書が必要なのはそのときです。
- `--no-dev` で、本番のパッケージだけを調べます。リリースの報告書などに使います。
- 既存のアプリケーションでは、まず `--fail-on=none` で、ジョブを失敗にせずに脆弱性を見てください。パッケージを更新してから、
  基準を上げます。

コマンドの要約は、ジョブのログに出ます。

```text
6 vulnerabilities (5 high, 1 medium) in 18 packages, 1 abandoned packages. Report: tmp/security-sca.pdf
```
