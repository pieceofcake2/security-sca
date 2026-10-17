# Security SCA ドキュメント

アプリケーションのソフトウェア構成分析 (SCA) です。Composer のパッケージの既知の脆弱性を `composer audit` で調べ、
CVE とリスクごとの A4 の PDF の報告書にします。報告書は [pieceofcake2/report](https://pieceofcake2.github.io/report/ja/)
のデザインです。あるリスク以上の脆弱性があれば、終了コードで CI を失敗にします。

pieceofcake2 のセキュリティ診断の 1 つで、ほかに pieceofcake2/security-sast (ソースコード) と pieceofcake2/security-dast
(動いているアプリケーション) があります。pieceofcake2/security-check で、すべてを実行できます。

## 必要なもの

- PHP 8.0 以上と Composer 2.4 以上 (`composer audit`)
- プロジェクトの `composer.lock`。これを読んで調べるので、パッケージをインストールしておく必要はありません
- PDF を印刷するための Google Chrome または Chromium 131 以上。日本語の報告書には、Linux では `fonts-noto-cjk`

## インストール

```bash
composer require --dev pieceofcake2/security-sca
```

## 使い方

```bash
vendor/bin/security-sca --pdf=tmp/security-sca.pdf --language=ja
```

| オプション | 環境変数 | デフォルト | |
| --- | --- | --- | --- |
| `--pdf=PATH` | `SECURITY_SCA_PDF` | `tmp/security-sca.pdf` | PDF の報告書 |
| `--fail-on=RISK` | `SECURITY_SCA_FAIL_ON` | `high` | 不合格にする最も低いリスク。`critical`、`high`、`medium`、`low`、`none` |
| `--no-dev` | `SECURITY_SCA_NO_DEV` | | 開発用のパッケージ (`require-dev`) を除きます |
| `--project=DIR` | | カレントディレクトリ | `composer.json` と `composer.lock` のディレクトリ |
| `--composer=COMMAND` | `SECURITY_SCA_COMPOSER` | `composer` | Composer のコマンド |
| `--language=LANG` | `SECURITY_REPORT_LANGUAGE` | `en` | `en` か `ja`、または `--locale-dir` の言語 |
| `--locale-dir=DIR` | `SECURITY_REPORT_LOCALE_DIR` | | メッセージを足したり置き換えたりする `<言語>.json` のディレクトリ |
| `--title=TITLE` | `SECURITY_SCA_TITLE` | 言語のタイトル | 報告書のタイトル |
| `--project-name=NAME` | `SECURITY_REPORT_PROJECT` | `composer.json` の名前 | タイトルの下のプロジェクト名 |
| `--note=TEXT` | `SECURITY_SCA_NOTE` | | 表紙の注記 |

終了コードは、`--fail-on` に達する脆弱性がなければ `0`、あれば `1`、調査や報告書の作成に失敗したときは `2` です。リスクが
不明な脆弱性は、`--fail-on=low` のときだけ不合格になります。

## 報告書

1. **表紙**: 結果、リスクごとの脆弱性の数、リポジトリ、ブランチ、コミット、検査したパッケージの数、不合格の基準、実行の
   日時、環境、ソフトウェア
2. **リスク別の脆弱性**: 緊急、高、中、低、不明の順に並べ、最後に開発が終了したパッケージ。それぞれのページへのリンク付き
3. **脆弱性ごとのページ**: CVE (CVE がないものは GHSA ID) とタイトル、リスク、パッケージ、導入バージョンと影響する
   バージョン、アドバイザリの ID、公開日、アドバイザリ、CVE のレコード、GitHub のアドバイザリへのリンク

脆弱性は、実行した時点の Packagist のセキュリティアドバイザリによるものです。GitHub Advisory Database (審査済みの
アドバイザリ) と FriendsOfPHP のセキュリティアドバイザリが元になっています。

## GitHub Actions

[docs/examples/security-sca.yml](../examples/security-sca.yml) は、push と pull request のたびと、その後に公開された
アドバイザリのために毎週、パッケージを調べ、報告書をアップロードします。

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
