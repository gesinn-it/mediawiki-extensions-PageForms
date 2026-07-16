# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [2.1.2] - 2026-07-16

### Fixed
- `libs/PageForms.js` (`checkForPipes()`): replace legacy octal string escapes, forbidden by the ECMAScript strict-mode grammar, with the equivalent unicode escapes; Babel's parser (used by `istanbul-lib-instrument` for JS coverage) threw a `SyntaxError` on this file, which `nyc` silently caught and fell back to instrumenting the raw source with no coverage counters and no error output, dropping `PageForms.js` entirely from the coverage report instead of showing it at its actual (near-0%) coverage ([#104](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/104))
- `PFUploadWindow::processUpload()`: escape the attacker-controlled filename, input ID, and delimiter with `Xml::encodeJsVar()` before interpolating them into the inline `<script>` block; previously spliced in raw inside single-quoted JS string literals, allowing a crafted upload filename to break out and inject script (reflected XSS) ([#59](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/59))
- `PFFormLink::createFormLink()`: HTML-escape the `target=` page name when it is used as the fallback link text (no explicit `link text=` given); previously inserted raw via `Html::rawElement()`, allowing `{{#formlink:target=<script>...}}` to inject script (stored XSS) ([#59](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/59))
- `PFFormEditAction::displayFormChooser()`, `PFUtils::linkForSpecialPage()`, `PFForms::formatResult()`, `PFTemplates::formatResult()`: wrap already-escaped strings in `HtmlArmor` (or drop the redundant pre-escaping) before passing them to `LinkRenderer::makeKnownLink()`, which treats bare PHP strings as plain text and escapes them again; previously rendered mangled double-escaped entities (e.g. `&amp;amp;`) ([#59](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/59))
- `PFAutoEdit::run()`: wrap the button link text in `OOUI\HtmlSnippet` instead of passing an already-escaped bare string, which OOUI was escaping a second time ([#59](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/59))
- `PFMultiPageEdit::displaySpreadsheet()`: render the `pf-spreadsheet-addrowinstructions` message with `->text()` instead of `->parse()`, since `Html::element()` already escapes the result and the message contains no wiki markup to parse; previously double-escaped `&`/`<` in the message ([#59](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/59))
- `CalendarHtmlBuilder::calendarHTML()`: fix `$text .= Html::rawElement(..., $text)` reading the pre-assignment value of `$text` on both sides of the compound assignment, which duplicated the loading-indicator `<div>` in the rendered output ([#59](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/59))
- `composer.json`: drop the unconditional `phpunit/phpcov` dev dependency, which forced `phpunit/phpunit` to `^9.3` for every CI matrix leg; on MW 1.39 (PHP 8.1), this collided with MW core's own bundled `phpunit/phpunit ^8.5`, where `PHPUnit\TextUI\ResultPrinter` is a class rather than an interface, causing `composer test` to abort with `PHPUnit\TextUI\DefaultResultPrinter cannot implement PHPUnit\TextUI\ResultPrinter - it is not an interface` before any test ran. `phpcov` is now installed on demand, into an isolated project directory (`.phpcov-tool/`), only by the `phpunit-coverage` script used on the MW 1.43 coverage leg
- `PFFormLinker::getDefaultFormsForPage()`: restore the `NS_CATEGORY` guard removed in `0f690bdf` (#24); `{{#default_form:Foo}}` on a category page sets the default form for that category's *member pages*, not for the category page itself, so the self-check must keep skipping category pages ([#96](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/96))
- `PFValuesUtils::getAllPagesForQuery()`: use `SMWQueryProcessor::getQueryAndParamsFromFunctionParams()` instead of the removed `processFunctionParams()`/`addThisPrintout()`/`getProcessedParams()`/`createQuery()` sequence; `values from query` autocomplete fields crashed with `Call to undefined method SMWQueryProcessor::processFunctionParams()` on current SMW versions ([#96](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/96))
- `PFRunQuery::printPage()`: read the `{{{info|query form at top}}}` result from `PFFormPrinter::formHTML()`'s return value instead of reading `$wgPageFormsRunQueryFormAtTop` before `formHTML()` had parsed the form definition and set it; the query form was always rendered below the results, regardless of the tag ([#97](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/97))
- `PFRunQuery::printPage()`: forward the `ParserOutput` from parsing the query results (`$data_text`) to `OutputPage`, not just the form's own `ParserOutput`; on SMW 6, the tooltip stylesheet required by table headers (e.g. for preferred property labels) is registered only on the query result's `ParserOutput` and was silently dropped on the non-embedded `Special:RunQuery` path, leaving the tooltip text visible inline next to the property name in the header ([#95](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/95))
- `PF_editWarning.js`: snapshot select-multiple field values consistently instead of as an array compared against a later stringified value; the "Leave site?" warning fired even when nothing had changed [`0466ee38`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/0466ee38)

### Changed
- `PFFormPrinter::formHTML()`, `PFTemplateInForm::setFieldValuesFromSubmit()`, `PFWikiPage::createPageText()`/`createTemplateCallsForTemplateName()`/`createTemplateCall()`, `PFWikiPageTemplate::addUnhandledParams()`: take the current `WebRequest` as an explicit parameter instead of reading `RequestContext::getMain()->getRequest()`; removes the dual request-context access that made `PFRunQuery::printPage()`'s non-embedded path and other callers rely on manually syncing the global request context before calling `formHTML()` ([#99](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/99))

## [2.1.1] - 2026-07-06

### Fixed
- `PFFormField::newFromFormFieldTag()`: resolve a form-level `property=` override (and the SMW-derived possible values it populates) before the `mapping template`/`mapping property` block decides whether there is anything to map; previously this ran afterwards, so `mapping template` had no effect when a field's allowed values came from an SMW property instead of an explicit `values=` list ([#39](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/39))
- `PFFormEditAction::classifyForms()`: main forms configured via `$wgPageFormsMainForms` are now shown in the order given, instead of alphabetically; `array_intersect()` was called with the alphabetically sorted list of all forms as the first argument, whose order it preserves, instead of the configured list ([#41](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/41))
- `PFRunQuery::printPage()`: forward the `ParserOutput` returned by `PFFormPrinter::formHTML()` to `OutputPage` instead of the global parser's, which `PFFormField::clearState()` resets during field rendering, discarding any ResourceLoader modules registered by parser tag hooks (e.g. `ext.headertabs` from `<headertabs />`); tabs rendered but were not clickable on Special:RunQuery (query forms) ([#15](https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/15))

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

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.1.2...HEAD
[2.1.2]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.0.1...2.1.0
[2.0.1]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.4.0...2.0.0

Older releases: [1.x](CHANGELOG-1.x.md)
