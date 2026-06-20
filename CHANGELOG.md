# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- Add `@var string|null` annotations to `$sectionanchor` and `$extraQuery` in `PF_AutoeditAPI` so Phan knows the hook may set them; removes `PhanImpossibleCondition` suppression

- Remove redundant `is_object()` guard on `Parser::getTitle()` in `PF_FormCache`; `getTitle()` always returns a `Title` object since MW 1.39
- Replace `strpos() !== false` with `str_contains()`, `strpos() === 0` with `str_starts_with()`, and `strstr()` bool checks with `str_contains()` across 16 files to use PHP 8 string functions
- Bump `mediawiki/mediawiki-codesniffer` from 43.0.0 to 48.0.0; fix all new violations (remove `@file` annotations, fix comment-before-class spacing, nullable type syntax, data provider naming, static closures, `.phpcs.xml` array syntax)
- Bump `mediawiki/mediawiki-phan-config` from 0.14.0 to 0.20.0; regenerate Phan baseline with method-level suppressions; fix surfaced issues (isset → null-checks, unused parameters, stale inline suppressions)

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.4.0...HEAD

Older releases: [1.x](CHANGELOG-1.x.md)
