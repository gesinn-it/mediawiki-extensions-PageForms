<!-- THIS FILE IS AUTO-GENERATED. Edit .github/copilot-instructions-source.adoc instead. -->

# Project Notes

## Deprecated integrations: Cargo and PageSchemas

Support for the Cargo and PageSchemas extensions will be removed in a
future version. The following rules apply immediately:

- Do **not** write new tests for Cargo- or PageSchemas-related code
  paths.

- Do **not** document Cargo or PageSchemas behaviour in any new or
  updated documentation.

- When coverage gaps in existing code are caused solely by Cargo or
  PageSchemas code paths, treat those gaps as irrelevant — do not create
  tests to fill them.

- Annotate Cargo- and PageSchemas-related code blocks with a PHPUnit
  coverage ignore comment so that they are excluded from coverage
  metrics:

``` php
// @codeCoverageIgnoreStart
... Cargo / PageSchemas code ...
// @codeCoverageIgnoreEnd
```

# Test Workflow

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**Test-first approach**

All tests run inside a containerized MediaWiki environment managed via
[docker-compose-ci](https://github.com/gesinn-it-pub/docker-compose-ci)
(the `build/` submodule). Never run tests directly against a local PHP
or Node.js installation.

Always run `make install` before executing tests to ensure that the
latest file changes are copied into the container. Changes to source or
test files on the host are **not** automatically reflected in a running
container.

``` console
make install
```

**PHPUnit tests**

Run all PHPUnit tests:

``` console
make install composer-phpunit
```

Run a single test class or method (filtered):

``` console
make install composer-phpunit COMPOSER_PARAMS="-- --filter YourTestName"
```

Run a specific test suite:

``` console
make install composer-phpunit COMPOSER_PARAMS="-- --testsuite your-suite-name"
```

For interactive use, bash into the running container:

``` console
make bash
> composer phpunit -- --filter YourTestName
```

**Node QUnit tests**

Run all JavaScript tests:

``` console
make install npm-test
```

There is no direct `make` target for filtering individual tests. Bash
into the running container to run a specific test file or test case:

``` console
make bash
> npm run node-qunit -- tests/node-qunit/yourtest.test.js
```

Filter by test description:

``` console
make bash
> npx qunit --require ./tests/node-qunit/setup.js 'tests/node-qunit/**/*.test.js' --filter "your test description"
```

**Pre-commit validation gate**

Before every commit, run the full CI suite to confirm nothing is broken:

``` console
make ci
```

**Coverage analysis**

Coverage reports require PHP 7.4 / MW 1.35, because PHPUnit 8.5 does not
support code coverage on PHP 8. Always pass the matching versions
explicitly.

Run the full coverage suite (generates HTML + Clover XML under
`build/coverage/php/`):

``` console
make install composer-test-coverage MW_VERSION=1.35 PHP_VERSION=7.4 SMW_VERSION=4.2.0 DT_VERSION=3.1 PS_VERSION=0.6.1
```

The coverage output is mounted from the container to the host and is
available at `build/coverage/php/` after the run:

- `build/coverage/php/index.html` — browsable HTML report

- `build/coverage/php/coverage.xml` — Clover XML (used by Codecov)

To analyse coverage for a **specific file**, bash into the running
container after the `make install` step and run PHPUnit directly with
`--coverage-text` and a `--filter` matching the test class name:

``` console
make install MW_VERSION=1.35 PHP_VERSION=7.4 SMW_VERSION=4.2.0 DT_VERSION=3.1 PS_VERSION=0.6.1
make bash
> XDEBUG_MODE=coverage composer phpunit -- --coverage-text --filter PFAutocompleteAPI
```

<div class="note">

`--filter` matches against test **class and method names**, not source
file names. To cover `PF_AutocompleteAPI.php` filter for the test class
`PFAutocompleteAPI`.

</div>

To extract exact covered/missed line numbers from the generated Clover
XML on the host:

``` console
python3 - <<'EOF'
import xml.etree.ElementTree as ET
tree = ET.parse('build/coverage/php/coverage.xml')
covered, missed = [], []
for f in tree.getroot().iter('file'):
    if 'PF_AutocompleteAPI' in f.get('name', ''):
        for line in f.iter('line'):
            (covered if int(line.get('count', 0)) > 0 else missed).append(int(line.get('num')))
print(f'Covered ({len(covered)}): {covered}')
print(f'Missed  ({len(missed)}):  {missed}')
EOF
```

# MediaWiki Version Compatibility

This section documents recurring patterns that must be applied whenever
a new MediaWiki version is added to the CI matrix.

## PHP 8 — null safety and deprecation warnings

`phpunit.xml.dist` sets `convertWarningsToExceptions="true"`. This means
any `E_WARNING` or `E_DEPRECATED` thrown at PHP 8+ becomes a test
exception that kills the test silently (empty output, no assertion
failures).

Common culprits to check when a test produces an empty form body or
crashes without an obvious assertion failure:

- Passing `null` to functions that require a string:
  `Language::ucfirst(null)`, `str_replace('…​', '…​', null)`,
  `Sanitizer::safeEncodeAttribute(null)`. Fix: add a `!== null` guard or
  `?? ''` null-coalescing before the call.

- Pattern to search for:
  `grep -rn 'ucfirst\|str_replace\|safeEncodeAttribute' includes/` and
  check every call site for missing null guards.

## MW DB API — deprecated `wfGetDB()` and raw JOIN strings

`wfGetDB()` is deprecated since MW 1.39. For MW ≥ 1.42, use:

``` php
MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase()
```

Keep a `version_compare( MW_VERSION, '1.42', '>=' )` guard to maintain
backward compatibility with MW 1.35–1.41.

Raw SQL JOIN strings passed as the table name to `$db→select()` fail on
MW 1.43’s `SQLPlatform::tableName()` with a `DBLanguageError`. Use the
proper array form with table aliases and a `$joinConds` parameter
instead:

``` php
$tables = [ 'a' => $tableA, 'b' => $tableB ];
$joinConds = [ 'b' => [ 'JOIN', 'a.col = b.col' ] ];
$db->select( $tables, $fields, $conds, __METHOD__, $opts, $joinConds );
```

## Test context URL paths — `wgArticlePath`

MW 1.43’s `tests/common/TestSetup::applyInitialConfig()` sets
`$wgArticlePath = '/wiki/$1'`. Older versions (≤ 1.39) do not set this
variable in the test bootstrap, so the runtime value (`/index.php/$1`)
is used.

Any JSONScript test case that asserts URL paths (e.g. `Special:FormEdit`
links) must pin `wgArticlePath` explicitly in its `settings` block:

``` json
"settings": {
    "wgArticlePath": "/index.php/$1"
}
```

For this key to be honoured, it must be listed in
`JsonTestCaseScriptRunnerTest::getPermittedSettings()`.

## Node QUnit — ooui Web Components and `HTMLElement`

MW 1.43’s ooui uses the Web Components API
(`class extends HTMLElement`). The jsdom window exposes `HTMLElement`,
but it is not automatically a Node.js global. Add it once in
`tests/node-qunit/setup.js` after `global.window` is assigned:

``` javascript
global.HTMLElement = window.HTMLElement;
```

`SelectFileWidget` was renamed to `SelectFileInputWidget` in MW 1.43
ooui and no longer provides a built-in fallback label. Any test that
checks for a button label rendered by this widget must mock
`mw.message()` in `beforeEach` to return the expected string.

# Conventional Commits

Commit messages follow the [Conventional Commits
specification](https://www.conventionalcommits.org/).

Commit format:

`type(scope): short description`

The scope is optional and should describe the affected subsystem,
module, or dependency when useful.

Examples:

- feat(api): add autocomplete endpoint

- fix(parser): handle empty token lists

- docs(readme): explain input architecture

- refactor(parser): simplify token parsing

- deps(smw): bump from 5.1.0 to 5.2.0

- ci(github): update workflow configuration

- test(api): add autocomplete tests

Recommended commit types:

- `feat` — new functionality

- `fix` — bug fixes

- `deps` — dependency updates

- `docs` — documentation changes

- `refactor` — internal code changes without behavioral change

- `test` — tests added or updated

- `ci` — changes to continuous integration configuration

- `chore` — repository maintenance tasks without impact on runtime
  behavior

Dependency updates:

- Use the `deps` type for dependency upgrades

- The scope should identify the dependency being updated

- Include the version change when applicable

Example:

- deps(smw): bump from 5.1.0 to 5.2.0

Guidelines:

- Use the imperative mood (e.g. "add feature", not "added feature")

- Keep the subject line concise

- Use the commit body to explain **why**, not only **what**

- Scopes should be short, lowercase identifiers (e.g. `api`, `parser`,
  `smw`, `mediawiki`, `docker`)

- Use `chore` only for repository maintenance tasks that do not affect
  runtime behavior, dependencies, CI configuration, or tests

# Versioning and Releases

This project follows [Semantic Versioning](https://semver.org/).

Version numbers follow the format:

`MAJOR.MINOR.PATCH`

Version increment rules:

- MAJOR — incompatible or breaking changes

- MINOR — backwards-compatible feature additions

- PATCH — backwards-compatible bug fixes

Breaking changes include (but are not limited to):

- incompatible API changes

- removal or renaming of public interfaces

- behavior changes that may break existing integrations

- increased minimum runtime or dependency requirements

- incompatible configuration or data format changes

- dependency upgrades that introduce breaking changes for users

Breaking changes must always increment the MAJOR version.
