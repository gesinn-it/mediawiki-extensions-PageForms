# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- Extract standard-input tag dispatch from `PFFormPrinter::formHTML()` into `StandardInputHtmlBuilder` in `src/`; the call site becomes a single delegation call, reducing `formHTML()` by ~51 lines and making the dispatch independently testable
- Extract calendar display-mode rendering from `PFFormPrinter::formHTML()` into `CalendarHtmlBuilder` in `src/`; mirrors the existing `SpreadsheetHtmlBuilder` pattern and reduces `formHTML()` by ~67 lines
- Replace `isset($x) ? $x : $default` with `$x ?? $default` across 7 files; reduces `PhanPluginDuplicateConditionalNullCoalescing` from 10+ to 1 (one complex control-flow pattern in `PFAutoeditAPI::doStore` remains in the baseline)
- Remove Cargo integration: drop `PFTemplate::setCargoTable()`, `PFTemplateField::$mCargoField`/`$mCargoTable`, `PFFormField::setValuesWithMappingCargoField()`, `mapping cargo table`/`mapping cargo field` support, `CargoDelimiter` CSS class (renamed to `pf-list-delimiter`), Cargo image path in rating display, and `PageFormsCargoFields` global; removes `PhanUndeclaredStaticMethod` Phan warning for the Cargo mapping method (#56, #57, #58)
- Replace `empty()` with explicit `=== ''` / `=== []` comparisons across 12 files; removes all `MediaWikiNoEmptyIfDefined` Phan warnings (2 intentional `empty()` calls on `string|false` and `mixed` return values remain)
- Move `strReplaceFirst()` to `PFUtils` as a public static method; `PFFormPrinter::strReplaceFirst()` is now a deprecated shim and the duplicate in `FormDefParser` is removed
- Add `@var string|null` annotations to `$sectionanchor` and `$extraQuery` in `PF_AutoeditAPI` so Phan knows the hook may set them; removes `PhanImpossibleCondition` suppression

- Add variable initializers before conditional branches in 12 methods (`getAllPagesForNamespace`, `getStringForCurrentTime`, `createText`, `parseDate`, `PF_DateTimeInput::getHTML`, `PF_LeafletInput::getHTML`, `mapLookupHTML`, `PF_AutoEdit::run`, `PF_FormInputParserFunction::run`, `createFormLink`, `printForm`, `PF_FormPrinter::formHTML`); reduces `PhanPossiblyUndeclaredVariable` suppressions in baseline from 20+ to 3

- Remove redundant `is_object()` guard on `Parser::getTitle()` in `PF_FormCache`; `getTitle()` always returns a `Title` object since MW 1.39
- Remove MW 1.35 compat shims in `FormDefParser`: drop `getFreshParser()` branch and `is_object(Parser::getTitle())` guard; always use `ParserFactory::create()`; removes `PhanRedundantCondition` and `PhanUndeclaredMethod` suppressions
- Add `__METHOD__` to `PFFormLinker::getDefaultForm()` DB select call and `@param`/`@return` PHPDoc; removes `PhanParamTooFewInPHPDoc` suppression
- Extract `$inputType` variable in `PFFormField::createMarkup()` to satisfy Phan null-check flow; removes `PhanTypeSuspiciousStringExpression` suppression
- Remove redundant null-check guards in `PFCreatePageJob::run()` and `PFTreeInput::makeTitle()` where return types are non-nullable; removes `PhanRedundantCondition` suppressions
- Add `@var string` annotations to `PFTemplateField::$mFieldName` and `$mLabel`
- Replace `strpos() !== false` with `str_contains()`, `strpos() === 0` with `str_starts_with()`, and `strstr()` bool checks with `str_contains()` across 16 files to use PHP 8 string functions
- Bump `mediawiki/mediawiki-codesniffer` from 43.0.0 to 48.0.0; fix all new violations (remove `@file` annotations, fix comment-before-class spacing, nullable type syntax, data provider naming, static closures, `.phpcs.xml` array syntax)
- Bump `mediawiki/mediawiki-phan-config` from 0.14.0 to 0.20.0; regenerate Phan baseline with method-level suppressions; fix surfaced issues (isset → null-checks, unused parameters, stale inline suppressions)

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.4.0...HEAD

Older releases: [1.x](CHANGELOG-1.x.md)
