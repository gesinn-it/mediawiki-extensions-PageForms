# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- Remove Cargo integration: drop `PFTemplate::setCargoTable()`, `PFTemplateField::$mCargoField`/`$mCargoTable`, `PFFormField::setValuesWithMappingCargoField()`, `mapping cargo table`/`mapping cargo field` support, `CargoDelimiter` CSS class (renamed to `pf-list-delimiter`), Cargo image path in rating display, and `PageFormsCargoFields` global; removes `PhanUndeclaredStaticMethod` Phan warning for the Cargo mapping method (#56, #57, #58)
- Replace `empty()` with explicit `=== ''` / `=== []` comparisons across 12 files; removes all `MediaWikiNoEmptyIfDefined` Phan warnings (2 intentional `empty()` calls on `string|false` and `mixed` return values remain)
- Move `strReplaceFirst()` to `PFUtils` as a public static method; `PFFormPrinter::strReplaceFirst()` is now a deprecated shim and the duplicate in `FormDefParser` is removed
- Add `@var string|null` annotations to `$sectionanchor` and `$extraQuery` in `PF_AutoeditAPI` so Phan knows the hook may set them; removes `PhanImpossibleCondition` suppression

- Add variable initializers before conditional branches in 12 methods (`getAllPagesForNamespace`, `getStringForCurrentTime`, `createText`, `parseDate`, `PF_DateTimeInput::getHTML`, `PF_LeafletInput::getHTML`, `mapLookupHTML`, `PF_AutoEdit::run`, `PF_FormInputParserFunction::run`, `createFormLink`, `printForm`, `PF_FormPrinter::formHTML`); reduces `PhanPossiblyUndeclaredVariable` suppressions in baseline from 20+ to 3

- Remove redundant `is_object()` guard on `Parser::getTitle()` in `PF_FormCache`; `getTitle()` always returns a `Title` object since MW 1.39
- Replace `strpos() !== false` with `str_contains()`, `strpos() === 0` with `str_starts_with()`, and `strstr()` bool checks with `str_contains()` across 16 files to use PHP 8 string functions
- Bump `mediawiki/mediawiki-codesniffer` from 43.0.0 to 48.0.0; fix all new violations (remove `@file` annotations, fix comment-before-class spacing, nullable type syntax, data provider naming, static closures, `.phpcs.xml` array syntax)
- Bump `mediawiki/mediawiki-phan-config` from 0.14.0 to 0.20.0; regenerate Phan baseline with method-level suppressions; fix surfaced issues (isset → null-checks, unused parameters, stale inline suppressions)

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.4.0...HEAD

Older releases: [1.x](CHANGELOG-1.x.md)
