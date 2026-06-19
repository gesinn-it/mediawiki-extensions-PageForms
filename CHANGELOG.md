# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed
- Fix `preg_replace()` deprecation on PHP 8 when `PFUtils::getPageText()` returns null in `PF_Templates`
- Add missing qqq.json documentation for `pf-target-input-*` i18n messages added in the formlink feature

## [1.3.5] - 2026-06-10

SMW 7.0.0 compatibility, preview fix, and UploadWindow hardening.

### Changed
- Upgrade `nyc` from 15 to 18, fix `brace-expansion` security vulnerability via `npm audit fix` [`6544b33`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/6544b337)
- Remove dead SMWSQLStore2 branches from `PF_AutocompleteAPI` and `PF_Template`; always use SMWSQLStore3 table names [`0d25af6`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/0d25af61)
- Remove unused `PFUtils::getSMWContLang()` shim (references `smwfContLang()`/`$smwgContLang` which no longer exist in SMW 7) [`ff09ad3`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/ff09ad3c)

### Fixed
- Replace removed `DIProperty::findPropertyTypeID()` with `findPropertyValueType()` for SMW 7 compatibility [`1e9e759`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/1e9e759d)
- Fix "EditPage does not have a context title set" error when clicking the preview standard input on MW 1.43 [`b41bc21`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/b41bc219)
- Forward parser tag ResourceLoader modules to OutputPage after form render [`d9de6a6`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d9de6a60)
- Skip empty patterns in `ignoreFormName()` [`4d55220`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/4d552200)
- Fix null pointer and remove removed MW config vars in UploadWindow [`bc7e9ee`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/bc7e9ee6)

## [1.3.4] - 2026-06-02

Fixes a JavaScript error in the select2 tokens input caused by a missing ResourceLoader dependency.

### Fixed
- Fix `Sortable is not defined` JS error when `ext.pageforms.select2` loads before `ext.pageforms.main` — add missing `ext.pageforms.sortable` dependency [`(commit)`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/releases/tag/1.3.4)

## [1.3.3] - 2026-04-16

### Fixed
- Add missing `mediawiki.api` and `mediawiki.util` ResourceLoader dependencies [`c9cff7b`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/c9cff7b)

### Changed
- Replace inline-style div with semantic span in submit options [`743f925`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/743f9257)

## [1.3.2] - 2026-04-15

Broad JS cleanup: IIFE wrapping, XSS fix, jQuery modernisation, and expanded QUnit coverage.

### Fixed
- Remove `shiftShortestMatch` — restores alphabetical enum ordering [`72233c5`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/72233c5)
- `PFYearInput::getParameters()` use associative keys [`1e54f0f`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/1e54f0f)
- Activate IIFE in `PF_upload`, fix XSS in `PF_simpleupload` [`a2209fe`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/a2209fe)
- Cache `mw.Api` instance, add preview error handler, fix brace-style [`eaac191`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/eaac191)

### Changed
- Remove Cargo support from all JS files [`fe16578`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/fe16578)
- Rename `$j`-prefixed jQuery vars to `$` in `PF_autoedit` and `PF_AutoEditRating` [`649dc69`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/649dc69)
- Unify jQuery→`$` usage in AutoEditRating, collapsible, rating [`60e7014`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/60e7014)
- Wrap global functions in IIFEs, fix quotes and `bind()` [`bbc10ff`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/bbc10ff)
- Apply remaining low-priority JS convention fixes [`d4badc2`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d4badc2)
- Migrate `$.ajax` → `mw.Api()` in autoedit, submit, preview [`c55cea9`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/c55cea9)

## [1.3.1] - 2026-04-13

Broad bug-fix release covering PHP 8.2 compatibility, DOM injection hardening, and form input correctness.

### Fixed
- Hide empty `oo-ui-fieldLayout-header` in simpleUpload widget [`b88932a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/b88932a)
- Resolve PHP 8.2 dynamic-property and PHPUnit schema warnings [`892e263`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/892e2639)
- Replace deprecated `Parser::$mOptions` with local variable in RunQuery [`319ea83`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/319ea833)
- Reset upload widget after upload to re-trigger duplicate detection [`6bf10a3`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/6bf10a3d)
- Fix scrollbar reset and TAB-clears-value bugs in combobox [`fd4ba3a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/fd4ba3a5)
- Harden save-and-continue and simpleupload against DOM injection [`788a72a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/788a72a3)
- Replace `assertMatchesRegularExpression` with PHPUnit 8 compatible assertion [`a27d66c`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/a27d66c7)
- Normalize `value_labels` JSON string in dropdown, tokens, listbox [`29d2507`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/29d25072)
- Remove Cargo code from checkboxes, fix `value_labels` JSON decoding [`5bfe7e0`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/5bfe7e06)
- Prevent numeric index replacing value on re-edit in ComboBoxInput [`bf7e9fb`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/bf7e9fb8)
- Guard `preventClickjacking()` for MW 1.35–1.42 compatibility [`d35f94c`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d35f94cd)
- Remove deprecated `SpecialVersion::getGitHeadSha1()` from autoedit [`891cada`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/891cada7)
- Replace `assertEquals` with `assertSame` for int comparison in SF_Select [`c021242`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/c021242e)

### Changed
- Extract `InputTypeRegistry` and `getStringForCurrentTime` from FormPrinter [`20e896a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/20e896a4)
- Remove all remaining Cargo integration methods from forminputs [`37852d3`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/37852d36)

## [1.3.0] - 2026-04-09

Extracts autocomplete helpers into reusable shared modules.

### Changed
- Extract `pf.ComboBoxDataSource` from ComboBoxInput [`ddf287a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/ddf287a8)
- Extract `pf.highlightText`, `pf.nameAttr`, `pf.partOfMultiple` to `ext.pf.js` [`88e6c14`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/88e6c144)

## [1.2.0] - 2026-04-09

Improves autocomplete performance by fetching concept/category/namespace/property values remotely and bootstrapping display titles from the DOM.

### Added
- Always fetch concept/category/namespace/property autocomplete values remotely [`f0ac008`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/f0ac0088)
- Bootstrap display title maps from DOM, skip initial AJAX request [`78f6146`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/78f6146c)

### Fixed
- Concept display-title pre-load must respect `MaxAutocompleteValues` [`75900d4`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/75900d41)
- Replace `empty()` with `!== []` check on `$possible_values` [`f36cc97`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/f36cc975)

### Changed
- Make `SpreadsheetAutocompleteWidget` a subclass of `AutocompleteWidget` [`36a708e`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/36a708e6)
- Extract shared `pf.buildAutocompleteParams()` helper [`01fcbfe`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/01fcbfe6)

## [1.1.7] - 2026-04-09

### Fixed
- Concept substring search misses pages beyond `MaxAutocompleteValues` [`be052c8`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/be052c8a)
- Remove legacy `User::isWatched()` fallback dropped in MW 1.43 [`6105198`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/61051982)
- Restore MW 1.35/1.36 compatibility for watchlist check [`23e3ffc`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/23e3ffc5)

## [1.1.5] - 2026-04-05

MW 1.43 compatibility fixes, autoedit preload path hardening, and a new `dev-test` build target.

### Added
- Add focused dev-test targets and volume mount setup [`7b69e35`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/7b69e351)

### Fixed
- Fix MW 1.43 `Parser` typed-property errors in form rendering [`d801f36`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d801f360)
- Replace removed `StubObject` with direct `PFFormPrinter` instantiation in hooks [`cadd084`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/cadd0845)
- Preserve free text in `preparePreloadData` fast path [`ab5c397`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/ab5c397c)
- Guard `preparePreloadData` fast path against `+`/`-` modifier keys [`d9a7111`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d9a7111d)
- Set fallback `Title` on parser in `preparePreloadData` for MW 1.35 [`1b10778`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/1b107787)
- Remove literal `\t` from single-quoted HTML strings in date-input [`8cc86673`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/8cc86673)

### Changed
- Bypass HTML round-trip in preload path for save/preview/diff [`54b96954`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/54b96954)
- Extract `PFFormCache` — form definition caching subsystem [`6944e41`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/6944e41d)
- Extract `getStringFromPassedInArray`, `displayLoadingImage`, `generateUUID` to `PFFormUtils` [`2863cf4`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/2863cf43)
- Fix 4 npm security vulnerabilities via `npm audit fix` [`3d401930`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/3d401930)

## [1.1.4] - 2026-03-23

### Fixed
- Fix `finalizeResults` ok-text key, context restore, `getVersion` precedence, `rand`→`random_int` [`547d113`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/547d1135)
- Use `RequestContext` in request-reading call sites in autoedit [`ecdd0ff`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/ecdd0ff1)
- Replace `JQuery` typo with `$` in IIFE click handler [`314ef4c`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/314ef4cb)

### Changed
- Extract `HtmlFormDataExtractor` from `PFAutoeditAPI` [`5920e66`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/5920e662)
- Add `PFUtils::getReplicaDB()` and `newWikiPageFromTitle()` helpers [`d34ee51`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d34ee511)

## [1.1.3] - 2026-03-21

### Breaking Changes
- Remove Cargo and PageSchemas integration code [`1b73df7`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/1b73df7)
- Remove `CreateCategory`, `CreateClass`, `CreateForm`, `CreateProperty`, and `CreateTemplate` special pages [`4c40b4d`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/4c40b4d)

### Fixed
- Skip change handler for nameless elements (e.g. select2 search input) in SF_Select [`5b7bbe5`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/5b7bbe5)

## [1.1.2] - 2026-03-21

### Fixed
- Refactor while-loop assignments, remove `AssignmentInCondition` PHPCS exclusion [`3e2af97`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/3e2af973)
- Migrate globals to `getConfig()`/`getRequest()`, remove `ExtendClassUsage` PHPCS exclusions [`b6be229`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/b6be229b)
- Wrap long lines, remove `LineLength.TooLong` PHPCS exclusion [`a13ae1f`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/a13ae1f5)

### Changed
- Fix npm security vulnerabilities, upgrade jsdom to v29 [`0a4200d`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/0a4200d0)

## [1.1.1] - 2026-03-21

### Fixed
- Use `version_compare` guard for `getServiceContainer()` on MW < 1.36 [`f484232`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/f484232)

## [1.1.0] - 2026-03-21

Adds the SF_Select input type with dynamic SMW query support, previously provided by the separate SemanticFormsSelect extension.

### Added
- Add `SF_Select` input type with dynamic SMW query support [`4a25ca2`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/4a25ca2)

## [1.0.3] - 2026-03-20

### Fixed
- Use `currentFiles[0]` in change handler on MW >= 1.43 in simpleupload [`490015a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/490015ae)
- Eliminate layout shift on form load [`303f15a`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/303f15a7)
- Strip wiki link syntax from duplicate-upload error message [`d4fa89c`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/d4fa89ce)
- Update preview on combobox dropdown selection in simpleupload [`2fe216e`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/2fe216ed)

## [1.0.2] - 2026-03-19

### Fixed
- Resolve ESLint warnings (jsdoc types, no-shadow, unlabeled-button, security) [`77cd483`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/77cd483)
- Render namespace dropdown in OOUI overlay to fix z-index on `Special:FormStart` [`dc9c138`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/dc9c138)
- Render form chooser dropdown in OOUI overlay to fix z-index on `Special:FormStart` [`e24268d`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/e24268d)
- Remove unused `pf` parameter from `PF_rating` IIFE [`0ca613c`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/0ca613c)
- Add null-coalescing guards for `str_replace`/`explode`/`trim` on nullable inputs [`2b0adce`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/2b0adce)

### Changed
- Upgrade `eslint-config-wikimedia` to 0.32.3 and modernize JS syntax [`6e141c9`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/6e141c9)
- Fix deprecated `wfGetDB`, `WikiPage::factory` and `$wgHooks` usage [`1ef47cb`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/1ef47cb)

## [1.0.1] - 2026-03-19

### Changed
- Remove PageSchemas integration [`bf0a178`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/bf0a178f)
- Remove Cargo support from `PFAutocompleteAPI` [`ceaaf6c`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/ceaaf6c8)

## [1.0.0] - 2026-03-19

Initial release as an independent fork from upstream PageForms 5.5.1, with version numbering reset to follow Semantic Versioning.

### Added
- Add `DisplayTitle` support for "values from property" [`b68ea74`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/b68ea74d)
- Add `DisplayTitle` support in multiple autocomplete input types
- Add support for `restricted` param in TreeInput and ComboBox (Page datatype) [`64dca4e`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/64dca4e7)
- Add support for `class` parameter in TreeInput and date-related inputs [`a4055f4`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/a4055f4a)
- Add support for `mandatory` param in DateTimeInput [`20d33da`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/20d33da9)
- Add support for `mandatory` param in CheckboxInput [`cf68f3f`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/cf68f3fa)
- Add missing param support in OpenLayersInput [`c19d719`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/c19d7195)

### Fixed
- Disambiguate display titles for namespace results in autocomplete [`8bf22cc`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/8bf22ccf)
- Disambiguate duplicate local display title labels in autocomplete [`431f519`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/431f5194)
- Store canonical title while showing display title in combobox [`85291ce`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/85291ce3)
- Keep display title visible after selection in combobox [`fadf25b`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/fadf25b0)
- Show display title for existing canonical values in combobox [`71c4626`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/71c4626d)
- Keep canonical values and prevent display title duplicates in tokens [`ed3ab49`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/ed3ab497)
- Clear inline search text after selecting suggestion in tokens [`9af86d7`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/9af86d75)
- Remove deprecated and dead SMW API calls [`5777aaa`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/5777aaac)
- Fix `strlen(null)` crash in `PFAutocompleteAPI` [`4a2c4a8`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/4a2c4a81)
- Prevent default context menu and safely handle undefined event in autoedit [`b353538`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/b353538c)
- Fix autoedit triggers by normalizing event handling [`4d607e7`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/4d607e7d)

### Changed
- Simplify `PFAutocompleteAPI` and fix uninitialized `$data` [`debd8c2`](https://github.com/gesinn-it/mediawiki-extensions-PageForms/commit/debd8c28)

[Unreleased]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.3.5...HEAD
[1.3.5]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.3.4...1.3.5
[1.3.4]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.3.3...1.3.4
[1.3.3]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.3.2...1.3.3
[1.3.2]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.3.1...1.3.2
[1.3.1]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.1.7...1.2.0
[1.1.7]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.1.5...1.1.7
[1.1.5]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.1.4...1.1.5
[1.1.4]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.1.3...1.1.4
[1.1.3]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.0.3...1.1.0
[1.0.3]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/gesinn-it/mediawiki-extensions-PageForms/releases/tag/1.0.0
