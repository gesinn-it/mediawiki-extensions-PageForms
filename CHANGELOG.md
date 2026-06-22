# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed
- PHPUnit `@phpunit` and `@phpunit-coverage` split into two separate PHP processes (unit suite, then integration suite); running both suites in a single process caused MW-internal caches (held in static variables across SMW store resets) to accumulate ~11 GB RSS, triggering the Linux OOM-killer (exit 137) before any test output appeared
- `PFValuesUtils::getAllPagesForNamespace()`: exclude redirect pages from `values from namespace` autocomplete; previously renaming a file/page left both the old redirect and the new page in the suggestion list (#27)
- `PFFormLinker`: `{{#default_form:Foo}}` on a category page now causes an "Edit with form" tab to appear on that category page itself; previously the self-check was skipped for `NS_CATEGORY` so the tab never appeared (#24)
- `PFHooks::setPostEditCookie()`: guard against `$wgPageFormsFormPrinter` being `null` before calling `property_exists()`; PHP 8 throws a `TypeError` when the first argument is `null`, causing all JSONScript tests to fail when run after a test that calls `insertPage()`
- `PFFormPrinter`: `{{{for template}}}`, `{{{field}}}`, and `{{{standard input}}}` tags without a name parameter now throw an `MWException` with an actionable error message instead of a silent PHP warning; fixes issue #23
- Sort dropdown options from SMW `Allows value` / `Allows value list` annotations alphabetically; previously they appeared in SMW store insertion order (#40)

### Refactored
- `PFTemplateField::setSemanticProperty()` and `setTypeAndPossibleValues()` accept an optional `$store` parameter; callers without the parameter are unaffected, but tests can now inject a mock store without a live SMW container
- `PFFormField::setValuesWithMappingProperty()` accepts an optional `$store` parameter (same pattern); enables unit-testing the mapping-property label resolution without a live SMW container
- `PFValuesUtils::getAllValuesForProperty()` accepts optional `$store`, `$maxValues`, and `$useDisplayTitle` parameters; production callers are unchanged, but tests can now exercise the sort, limit, and display-title branches in isolation

### Documentation
- Migrate all Extension:Page Forms wiki pages from mediawiki.org to local AsciiDoc manuals under `docs/` structured by audience: `user/`, `admin/`, `developer/`; 1:1 content migration, wikitext converted to AsciiDoc syntax

### Changed
- Extract val-modifier (`+`/`-`) and default-value token (`now`, `current user`, `uuid`) resolution from `PFFormPrinter::formHTML()` into `FieldValueResolver` in `src/`; both methods are pure value transformations with no form state, enabling isolated unit testing; reduces `formHTML()` by ~50 lines (#86)

### Tests
- Add `StandardInputHtmlBuilderTest` with 14 tests covering all 9 standard-input branches (`save`, `cancel`, `preview`, `minor edit`, `watch`, `run query`, `summary`, `save and continue`, `changes`) plus disabled state and custom class attribute (#87)
- Add `FormDefParserTest` with 6 tests porting and extending the `preparePreloadData` cases from `PFFormPrinterTest`; covers field extraction, free-text preservation, multi-template, and empty-content edge cases (#88)
- Extend `PFFormPrinterTest` with 12 new tests covering delegation methods (`getInputType`, `getAllInputTypes`, `getDefaultInputTypeSMW`, `getPossibleInputTypesSMW`, `strReplaceFirst`, `placeholderFormat`, `makePlaceholderInFormHTML`) and the `{{{for template/field}}}` processing loop (text input, default value, submitted form, hidden field, mandatory field) (#89, #90)
- Extend `FieldValueResolverTest` with 4 new tests covering the `'now'` default-value branch for `date`, `datetime`, `_dat` property type, and `datepicker` skip; extend `FormFieldHtmlBuilderTest` with 2 new tests for the semantic-type-hook and no-hook-fallback paths (#91)
- Extract `{{{section|...}}}` processing from `PFFormPrinter::formHTML()` into `FormSectionHtmlBuilder` in `src/`; section-text extraction, look-ahead boundary detection, and textarea rendering are now independently readable; reduces `formHTML()` by ~133 lines (#52)
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
