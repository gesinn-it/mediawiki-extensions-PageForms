# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed
- `PFFormEditAction::classifyForms()`: main forms configured via `$wgPageFormsMainForms` are now shown in the order given, instead of alphabetically; `array_intersect()` was called with the alphabetically sorted list of all forms as the first argument, whose order it preserves, instead of the configured list ([#41](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/41))

## [2.1.0] - 2026-06-30

Introduces smart local/remote autocompletion for wiki-sourced fields and a
configurable main-form classification system for the form chooser.

### Added
- `PFValuesUtils`: wiki-sourced autocomplete fields (category, namespace, property, concept) now switch between local and remote mode based on source count vs. `$wgPageFormsMaxLocalAutocompleteValues`; fields with `mapping template`/`mapping property` args or that exceed the threshold remain remote [`103f9a02`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/103f9a02e5efd06266b0a3c932448091c114b1b8)
- `$wgPageFormsMainForms` — explicitly designate which forms appear as "main forms" in the form chooser, bypassing the automatic heuristic [`3d80f0d9`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/3d80f0d9d06de51b7996f726160b9f90ba0c42db) ([#41](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/41))
- `$wgPageFormsMainFormsLimit` — configures the Top-N fallback: the N forms with the most associated pages are promoted to "main forms" (default: 5); replaces the old 1%-threshold that left small wikis with no main forms [`3d80f0d9`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/3d80f0d9d06de51b7996f726160b9f90ba0c42db) ([#41](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/41))

### Fixed
- `PFComboBoxInput`: canonical page title is now written into the selected `<option value>` when `possible_values` is a display-title map, regardless of autocomplete mode; previously the override was skipped in local mode, causing the display title to be saved instead of the canonical title on re-edit [`f05e0d24`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/f05e0d24ee1805bbc490549a79fdcd5b3df90eb4)
- `PF_preview.js`: use `new window.Event('resize')` instead of bare `new Event('resize')` to avoid a `TypeError` during QUnit test runs under jsdom [`0f948e42`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/0f948e42e7b7904e19d4f277fd1dee4c7870d9b0)
- `PFFormPrinter`: cast `$form_submitted` to `bool` before passing to `StandardInputHtmlBuilder::buildHtml()`; `null` caused a `TypeError` under `strict_types=1` when saving a form page via the API [`dedab736`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/dedab736bf7b9ec1a822b0207bfc3e7b0a80079d)
- Form chooser Top-N fallback now counts namespace-based `#default_form` assignments in addition to category-based ones; wikis using namespace-level `#default_form` no longer saw alphabetical instead of usage-based form ordering [`d065dc3b`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d065dc3b25c5a5217455f00c7e8a469600cff106) ([#41](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/41))
- Form chooser: alias `page_props` table in namespace query for MW 1.39 compatibility [`bf17aa64`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/bf17aa6482646f4395b01542f76cc2957fc67d20)

### Changed
- Form chooser HTML output now uses a Mustache template (`templates/FormChooser.mustache`) with BEM classes (`pf-form-chooser__*`); old classes `infoMessage`, `mainForms`, `otherForms`, `pageforms-separator` are no longer emitted [`775223bc`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/775223bc50f7bb91eac0a23fdcfd11b1118b4ed0)
- `PFFormEditAction`: extract `classifyForms()` from `displayFormChooser()`; rename `$popularForms` → `$mainForms`; narrow `getNumPagesPerForm()` and `printLinksToFormArray()` to `private` [`de008ef4`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/de008ef4e861548f51b836eb2fac58775217ec42)
- Bump `js-yaml` from 3.14.2 to 3.15.0 [`83925758`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/839257584e333059632ab296b634d2d106b7be44)

### Deprecated
- CSS classes `infoMessage`, `mainForms`, `otherForms` on form chooser section divs are no longer emitted; use `pf-form-chooser__section`, `pf-form-chooser__section--main`, `pf-form-chooser__section--other` instead
- CSS class `pageforms-separator` on form link separators is no longer emitted; separators are now rendered via `pf-form-chooser__separator`

## [2.0.1] - 2026-06-25

### Fixed
- `PFFormPrinter`: list fields with `mapping template`, `mapping property`, or `use display title` no longer throw a `TypeError` when the existing page contains more than one delimited value; `valueStringToLabels()` returned an array for multi-value lists which was passed to `FormFieldHtmlBuilder::formFieldHTML(?string)` unchanged [`7248bdd5`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/7248bdd5)

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

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.1.0...HEAD
[2.1.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.0.1...2.1.0
[2.0.1]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.4.0...2.0.0

Older releases: [1.x](CHANGELOG-1.x.md)
