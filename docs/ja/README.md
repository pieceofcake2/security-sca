# Security SCA ドキュメント

アプリケーションのソフトウェア構成分析 (SCA) です。Composer のパッケージの既知の脆弱性を `composer audit` で調べ、
CVE とリスクごとの A4 の PDF の報告書にします。報告書は [pieceofcake2/report](https://pieceofcake2.github.io/report/ja/)
のデザインです。あるリスク以上の脆弱性があれば、終了コードで CI を失敗にします。

- [GitHub Actions での CI](ci.md)

pieceofcake2 のセキュリティ診断の 1 つで、ほかに [pieceofcake2/security-sast](https://pieceofcake2.github.io/security-sast/ja/)
(ソースコード) と [pieceofcake2/security-dast](https://pieceofcake2.github.io/security-dast/ja/) (動いているアプリケーション)
があります。pieceofcake2/security-check で、すべてを実行できます。

## チェックすること

`composer.lock` のすべてのパッケージ (アプリケーションが必要とするパッケージと、それらが必要とするパッケージ) を、
インストールされているバージョンで調べます。

- **既知の脆弱性**: そのバージョンのパッケージに公開されたセキュリティアドバイザリ。あるバージョンのライブラリの
  SQL インジェクションなどで、CVE とリスクも示します ([脆弱性の情報源](#脆弱性の情報源))
- **開発が終了したパッケージ**: 作者がもうメンテナンスしていないパッケージ ([開発が終了したパッケージ](#開発が終了したパッケージ))

アプリケーションのコードはここでは調べません。コードは [pieceofcake2/security-sast](https://pieceofcake2.github.io/security-sast/ja/)
で、動いているアプリケーションは [pieceofcake2/security-dast](https://pieceofcake2.github.io/security-dast/ja/) で調べます。

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
| `--settings=FILE` | `SECURITY_SETTINGS` | `config/security.php` か `app/Config/security.php` | 設定ファイル |

終了コードは、`--fail-on` に達する脆弱性がなければ `0`、あれば `1`、調査や報告書の作成に失敗したときは `2` です。リスクが
不明な脆弱性は、`--fail-on=low` のときだけ不合格になります。

### 設定ファイル

pieceofcake2 のセキュリティ診断の設定は、プロジェクトの `config/security.php` (従来の構成では `app/Config/security.php`)
にまとめられます。診断ごとのセクションと、共通の `report` セクションを持つ配列を返します。

```php
<?php
return [
    'report' => [
        'language' => 'ja',
        'dir' => 'tmp/security',
    ],
    'sca' => [
        'failOn' => 'medium',
        'noDev' => true,
    ],
];
```

`sca` セクションのキーは、オプションをキャメルケースにしたものです (`--fail-on` は `failOn`)。`report` セクションには
`language`、`localeDir`、`project` (プロジェクト名)、`note` と、報告書のディレクトリ `dir` があります (この例では
`tmp/security/security-sca.pdf` に書き出します)。`sca` セクションに知らないキーがあるとエラーになります。オプションが
環境変数より、環境変数が設定ファイルより優先されます。

## 報告書

- [見本の報告書 (日本語、PDF)](/report-sample-ja.pdf)
- [見本の報告書 (英語、PDF)](/report-sample.pdf)

![表紙、リスク別の脆弱性、脆弱性のページ](../img/report-sample-ja.png)

1. **表紙**: 結果、リスクごとの脆弱性の数、リポジトリ、ブランチ、コミット、検査したパッケージの数、不合格の基準、実行の
   日時、環境、ソフトウェア
2. **リスク別の脆弱性**: 緊急、高、中、低、不明の順に並べ、最後に開発が終了したパッケージ。それぞれのページへのリンク付き
3. **脆弱性ごとのページ**: CVE (CVE がないものは GHSA ID) とタイトル、リスク、パッケージ、導入バージョンと影響する
   バージョン、アドバイザリの ID、公開日、アドバイザリ、CVE のレコード、GitHub のアドバイザリへのリンク
4. **検査したパッケージ**: `composer.lock` のすべてのパッケージ (`--no-dev` では開発用を除く) と、そのバージョン、本番用か
   開発用か、プロジェクトが直接要求しているかほかのパッケージが要求しているか、ライセンス、脆弱性の件数と開発終了か
   どうか。何も見つからなかったときにも、何を検査したかの一覧になります

脆弱性は、実行した時点の Packagist のセキュリティアドバイザリによるものです。GitHub Advisory Database (審査済みの
アドバイザリ) と FriendsOfPHP のセキュリティアドバイザリが元になっています。

## 脆弱性の情報源

`composer audit` は、`composer.lock` のパッケージを Packagist のセキュリティアドバイザリで調べます。その元は次のとおりです。

- [GitHub Advisory Database](https://github.com/advisories?query=ecosystem%3Acomposer): GitHub が審査したアドバイザリ。
  プロジェクトが自分のリポジトリで公開したアドバイザリは、GitHub の審査の後に出てきます。審査には数日かかることがあります
- [FriendsOfPHP のセキュリティアドバイザリ](https://github.com/FriendsOfPHP/security-advisories)

リスクはアドバイザリのもの (`critical`、`high`、`medium`、`low`) で、ないものは `unknown` です。報告書は実行した時点の
アドバイザリによるものです。その後に公開されたアドバイザリを見るには、CI で毎週など、繰り返し実行してください。

## 開発が終了したパッケージ

作者が Packagist で開発の終了 (abandoned) を示したパッケージを、脆弱性の後に並べます。作者が示していれば、代わりの
パッケージも載せます。後で見つかる脆弱性も含めて、修正は出ません。開発が終了したパッケージで不合格にはなりません。

## 脆弱性を直す

ほとんどの脆弱性は、パッケージを影響のないバージョンに更新すれば直ります。報告書の脆弱性のページに、導入バージョン、
影響するバージョン、アドバイザリがあります。修正されたバージョンはアドバイザリに書いてあります。

1. **パッケージを更新します**。`composer.json` の制約の範囲で更新します。

   ```bash
   composer update vendor/package --with-dependencies
   ```

2. **制約が修正されたバージョンを許さないとき**は、パッケージの変更 (CHANGELOG やリリースノート) を読んでから、制約を
   上げます。

   ```bash
   composer require vendor/package:^2.4
   ```

3. **アプリケーションではなく、ほかのパッケージがそのパッケージを必要としているとき**は、どのパッケージか、何が修正
   されたバージョンを妨げているかを調べます。

   ```bash
   composer why vendor/package             # それを必要とするパッケージ
   composer why-not vendor/package 2.4.1   # このバージョンを妨げているもの
   ```

   それを必要とするパッケージを更新するか、そのパッケージが許すなら、修正されたバージョンを `composer.json` で
   直接要求します。

4. **修正されたバージョンがないとき**は、アドバイザリを読んでください。設定や使わない機能など、脆弱性を避ける方法が
   書いてあることがあります。開発が終了したパッケージなら置き換えます。アプリケーションに当てはまらない脆弱性なら、
   理由を書いて除外します ([アドバイザリを除外する](#アドバイザリを除外する))。

そのあと `composer.lock` をコミットしてデプロイし、もう一度チェックします。`pieceofcake2/cakephp` の脆弱性は、`2.12`
の最新のリリースに更新してください。アプリケーションの API は変わりません。

開発用のパッケージ (`require-dev`) はデプロイされませんが、開発者のマシンや CI で動きます。これも直してください。
`--no-dev` は、リリースの報告書などで開発用のパッケージを除きます。

## アドバイザリを除外する

Windows だけの脆弱性や、アプリケーションが使わない機能の脆弱性など、アプリケーションに当てはまらないアドバイザリは、
`composer.json` で理由と一緒に除外します (Composer 2.6 以上)。`composer audit` と報告書から除かれます。

```json
{
    "config": {
        "audit": {
            "ignore": {
                "CVE-2021-34551": "Windows だけの脆弱性。アプリケーションは Linux で動く"
            }
        }
    }
}
```

キーには、CVE ID、アドバイザリの ID (`PKSA-...`)、またはアドバイザリの情報源の ID (GHSA ID など) を書きます。除外したアドバイザリは忘れられがちなので、
できるだけパッケージを更新してください。

## トラブルシューティング

**`composer.lock is not found`**: 先にプロジェクトで `composer install` (または `composer update`) を実行するか、
`--project` でプロジェクトを指定してください。

**`composer audit failed`**: 続けて Composer のメッセージが出ます。`packagist.org` へのネットワークが許可されていない、
`composer.lock` が正しくない、などです。`composer audit` はアドバイザリをダウンロードする必要があります。

**公開されたばかりのアドバイザリの脆弱性が出ない**: GitHub Advisory Database にまだ載っていません
([脆弱性の情報源](#脆弱性の情報源))。

**`Chrome is needed to print the report`**: Chrome か Chromium をインストールするか、`REPORT_CHROME_BINARY` を設定して
ください ([pieceofcake2/report](https://pieceofcake2.github.io/report/ja/))。
