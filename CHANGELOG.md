# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed
- `PFFormPrinter`: list fields with `mapping template`, `mapping property`, or `use display title` no longer throw a `TypeError` when the existing page contains more than one delimited value; `valueStringToLabels()` returned an array for multi-value lists which was passed to `FormFieldHtmlBuilder::formFieldHTML(?string)` unchanged

## [2.0.0] - 2026-06-23

First stable release of the gesinn-it distribution. Drops Cargo integration,
MW < 1.39 and PHP < 8.0 support, and ships a major internal refactoring of
`PFFormPrinter` alongside a comprehensive new documentation set.

### Breaking Changes
- Remove Cargo integration: `PFTemplate::setCargoTable()`, `PFTemplateField::$mCargoField`/`$mCargoTable`, `PFFormField::setValuesWithMappingCargoField()`, `mapping cargo table`/`mapping cargo field`, `CargoDelimiter` CSS class (renamed to `pf-list-delimiter`), Cargo image path in rating display, and `PageFormsCargoFields` global are all removed [`d8befcb8`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d8befcb8) ([#56](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/56), [#57](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/57), [#58](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/58))
- Drop MW < 1.39 and PHP < 8.0 support [`93c105b9`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/93c105b9)

### Fixed
- `PF_preview.js`: replace removed jQuery 3 `.load()` event shorthand with `.on('load', ...)` on the preview iframe; the old call was silently reinterpreted as the AJAX `.load(url)` shorthand, so the `loadFrameHandler` was never registered and the preview pane stayed hidden after clicking "Show preview" [`b0d4c779`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/b0d4c779) ([#38](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/38))
- `FieldValueResolver`: `default=current user` (and `default=now`) fields can now be saved empty; previously submitting with a cleared field would re-apply the default [`dd668512`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/dd668512) ([#29](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/29))
- `PF_submit.js`: minor-edit flag is now only sent when the "Minor edit" checkbox is actually checked [`44f8e2c4`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/44f8e2c4) ([#26](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/26))
- `PFValuesUtils::resolveDisplayTitle()`: strip HTML tags from parsed display titles before returning them; the autocomplete dropdown was showing raw HTML markup instead of plain text [`87f2b29c`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/87f2b29c) ([#34](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/34))
- `PFValuesUtils::getAllPagesForNamespace()`: exclude redirect pages from `values from namespace` autocomplete [`910922e9`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/910922e9) ([#27](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/27))
- `PFFormLinker`: `{{#default_form:Foo}}` on a category page now causes an "Edit with form" tab to appear on that category page itself [`0f690bdf`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/0f690bdf) ([#24](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/24))
- `PFFormPrinter`: `{{{for template}}}`, `{{{field}}}`, and `{{{standard input}}}` tags without a name now throw an `MWException` with an actionable error message instead of a silent PHP warning [`fa640a88`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/fa640a88) ([#23](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/23))
- Sort dropdown options from SMW `Allows value` / `Allows value list` annotations alphabetically [`5d14154a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/5d14154a) ([#40](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/40))
- `FieldValueResolver`: correct five bugs in `applyValModifier` and `resolveDefaultValue` [`51d0956f`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/51d0956f)

### Changed
- Extract `FieldValueResolver`, `FormSectionHtmlBuilder`, `StandardInputHtmlBuilder`, `CalendarHtmlBuilder` from `PFFormPrinter::formHTML()`, reducing the method by ~300 lines and making each component independently testable [`61e9a86d`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/61e9a86d) [`85c90f6d`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/85c90f6d) [`368f0ca6`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/368f0ca6) [`15ba6a6b`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/15ba6a6b)
- Inject optional `$store` parameter into `PFTemplateField`, `PFFormField`, and `PFValuesUtils` methods to enable unit testing without a live SMW container [`cf124dc5`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/cf124dc5) [`74fbb8bf`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/74fbb8bf)
- Modernize string functions to PHP 8.x (`str_contains`, `str_starts_with`) across 16 files [`9f375bee`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/9f375bee)
- Migrate Extension:Page Forms wiki pages from mediawiki.org to local AsciiDoc manuals under `docs/` [`574543fc`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/574543fc)
- Bump `mediawiki/mediawiki-codesniffer` from 43.0.0 to 48.0.0 [`82eb2a6d`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/82eb2a6d)
- Bump `mediawiki/mediawiki-phan-config` from 0.14.0 to 0.20.0 [`69edc6d9`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/69edc6d9)
- Bump `undici` to 7.28.0 [`e8aafc73`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/e8aafc73)

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.0.0...HEAD
[2.0.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.4.0...2.0.0

Older releases: [1.x](CHANGELOG-1.x.md)
