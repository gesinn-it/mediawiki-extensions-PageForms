# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- Replace `strpos() !== false` with `str_contains()`, `strpos() === 0` with `str_starts_with()`, and `strstr()` bool checks with `str_contains()` across 16 files to use PHP 8 string functions

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.4.0...HEAD

Older releases: [1.x](CHANGELOG-1.x.md)
