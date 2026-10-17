# pieceofcake2/security-sca

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/security-sca?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/security-sca?label=Packagist)](https://packagist.org/packages/pieceofcake2/security-sca)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/security-sca/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/security-sca/actions/workflows/CI.yml)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/security-sca/CI.yml?label=CI)](https://github.com/pieceofcake2/security-sca/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/security-sca?label=Coverage)](https://codecov.io/gh/pieceofcake2/security-sca)

Software composition analysis of an application: the known vulnerabilities of its Composer packages
(`composer audit`), as an A4 PDF report by CVE and risk in the design of
[pieceofcake2/report](https://github.com/pieceofcake2/report), and an exit code that fails CI.

```bash
composer require --dev pieceofcake2/security-sca
vendor/bin/security-sca --pdf=tmp/security-sca.pdf --fail-on=high
```

## Documentation

- Website: https://pieceofcake2.github.io/security-sca/ (日本語: https://pieceofcake2.github.io/security-sca/ja/)
- Markdown: [docs/README.md](docs/README.md) (日本語: [docs/ja/README.md](docs/ja/README.md)) (for AI tools and offline reading)

## Requirements

* PHP 8.0+ and Composer 2.8+
* Google Chrome or Chromium 131+

## Installation

```bash
composer require --dev pieceofcake2/security-sca
```

## Reporting Issues

Please open an issue on [GitHub](https://github.com/pieceofcake2/security-sca/issues).
To report a security vulnerability, use [private vulnerability reporting](https://github.com/pieceofcake2/security-sca/security/advisories/new).

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## Origin

This package was created by [pieceofcake2](https://github.com/pieceofcake2), which also maintains it. It is
not a fork of another project and is not based on one.

## License

The MIT License. See [LICENSE](LICENSE).
