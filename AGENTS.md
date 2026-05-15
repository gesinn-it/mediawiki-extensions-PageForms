<!-- THIS FILE IS AUTO-GENERATED. Edit AGENTS-source.adoc instead. -->

# Project Notes

## Cargo and PageSchemas integrations

Support for Cargo and PageSchemas is dropped. Delete any
Cargo/PageSchemas code immediately upon discovery — no tests, no docs,
no annotations.

# Coding Conventions

**Coding Conventions — General**

All source files regardless of language must follow these baseline
rules. They are enforced by `make ci` (lint + phpcs + eslint).

- Encoding: UTF-8 without BOM

- Line endings: Unix-style LF (not CR+LF)

- Indentation: tabs, not spaces

- Maximum line length: 120 characters

- No trailing whitespace

- Newline at end of file

**Coding Conventions — PHP**

Tooling:
[mediawiki-codesniffer](https://github.com/wikimedia/mediawiki-tools-codesniffer)
via PHPCS. Run locally: `make composer-phpcs` (or `make ci`).

**File structure**

- Every file starts with `declare( strict_types=1 );`

- No closing `?>` tag

- One class per file; filename matches class name (UpperCamelCase, e.g.
  `MyClass.php`)

- New code belongs in `src/` following PSR-4; `includes/` is legacy and
  should be migrated incrementally

**Namespaces and autoloading**

- PSR-4 via Composer (`autoload.psr-4` in `composer.json`)

- Top-level namespace = extension name (e.g.
  `MediaWiki\Extension\FooBar...`)

- Acronyms treated as single words: `HtmlId`, not `HTMLId`

**Naming**

| Element                     | Convention     | Example                |
|-----------------------------|----------------|------------------------|
| Classes, interfaces, traits | UpperCamelCase | `PageFormParser`       |
| Methods, variables          | lowerCamelCase | `getFormContent()`     |
| Constants                   | UPPER_CASE     | `MAX_FORM_SIZE`        |
| Global variables            | `$wg` prefix   | `$wgPageFormsSettings` |

**Type system**

- Use native type declarations on all parameters, properties, and return
  types

- PHPDoc only when native types are insufficient (e.g. `string[]`,
  `array<string, Foo>`)

- Nullable parameters: `?Type`, not `Type $x = null`

- Prefer `??` (null coalescing) and `??=` over ternary isset checks

- Use arrow functions `fn( $x ) ⇒ $x * 2` for single-expression closures

**Modern PHP features (target: PHP 8.1+)**

- Constructor property promotion

- `readonly` properties for immutable value objects

- `enum` instead of class constant groups

- `match()` instead of `switch` when returning a value

**Code style**

- 1TBS brace style — opening brace on same line, `else`/`elseif` on
  closing brace line

- Always use braces, even for single-line blocks

- Spaces inside parentheses: `getFoo( $bar )`, empty: `getBar()`

- Spaces around binary operators: `$a = $b + $c`

- Single quotes preferred; double quotes for string interpolation

- `===` strict equality; `==` only when type coercion is intentional

- No Yoda conditions: `$a === 'foo'`, not `'foo' === $a`

- `elseif` not `else if`

- `true`, `false`, `null` always lowercase

**Architecture**

- `private` by default; `protected` only when subclass access is needed

- Dependency injection over direct instantiation — delegate `new Foo()`
  to factories

- Single Responsibility: one class, one concern

- No superglobals (`$_GET`, `$_POST`) — use `WebRequest` via
  `RequestContext`

- No new global functions — use static utility classes (`Html`, `IP`) if
  needed

- Order class members: `public` → `protected` → `private`

**Coding Conventions — JavaScript**

Tooling: [ESLint](https://eslint.org/) with
[eslint-config-wikimedia](https://github.com/wikimedia/eslint-config-wikimedia).
Run locally: `npm run lint:js` (or `make ci`).

**ESLint configuration**

Every repository must have a `.eslintrc.json` at root with
`"root": true`:

``` json
{
  "root": true,
  "extends": [
    "wikimedia/client/es2016",
    "wikimedia/jquery",
    "wikimedia/mediawiki"
  ],
  "env": { "commonjs": true }
}
```

**Module system**

- CommonJS modules: `require()` for imports, `module.exports` for
  exports

- Register modules with ResourceLoader; bundle name pattern:
  `ext.myExtension`

- JS class files match the class name exactly (`TitleWidget.js` for
  `TitleWidget`)

**Naming**

- Variables and methods: lowerCamelCase

- Constructors / classes: UpperCamelCase

- jQuery objects: `$`-prefix (`$button`, not `button`)

- Constants: `ALL_CAPS`

- Acronyms as single words: `getHtmlApiSource`, not `getHTMLAPISource`

**Code style**

- Tabs for indentation; single quotes for string literals

- `===` and `!==`; no Yoda conditions

- Spaces inside parentheses: `if ( foo )`, `getFoo( bar )`

- `const` and `let` — never `var` in new code

- Arrow functions for callbacks

**jQuery**

- Prefer ES6/DOM equivalents over deprecated jQuery methods (`.each` →
  `forEach`, etc.)

- Never search the full DOM with `$( '#id' )` or `$( '.selector' )`; use
  hook-provided `$content` and call `.find()` on it *(full-DOM queries
  match stale or foreign nodes, break hook-lifecycle isolation, and
  waste performance by traversing the entire document)*

- Prefer `$( '<div>' ).text( value )` over `$( '<div>text</div>' )` to
  avoid XSS

**MediaWiki APIs**

- Access configuration via `mw.config.get( 'wgFoo' )`, never direct
  globals

- Expose public API via `module.exports` or within the `mw` namespace
  (e.g. `mw.echo.Foo`)

- Use `mw.storage` / `mw.storage.session` for
  localStorage/sessionStorage

- Storage keys: `mw`-prefix + camelCase/hyphens (e.g.
  `mwedit-state-foo`)

**Coding Conventions — CSS / LESS**

Tooling: [stylelint](https://stylelint.io/) via `npm run lint:styles`
(or `make ci`). ResourceLoader natively compiles `.less` files; prefer
LESS over plain CSS.

**Naming**

- Classes and IDs: all-lowercase, hyphen-separated

- Use an extension-specific prefix to avoid conflicts (e.g. `pf-`,
  `smw-`, `mw-`)

- LESS mixin names: `mixin-` prefix + hyphen-case (e.g.
  `mixin-screen-reader-text`)

**Whitespace and formatting**

- One selector per line, one property per line

- Opening brace on the same line as the last selector

- Tab indentation for properties and nested rules

- Semicolon after every declaration, including the last

- Empty line between rule sets

**Colors**

- Lowercase hex shorthand preferred: `#fff`, `#252525`

- `rgba()` when alpha transparency is needed; `transparent` keyword
  otherwise

- No named color keywords (except `transparent`), no `rgb()`, `hsl()`,
  `hsla()`

- Ensure color contrast meets [WCAG 2.0
  AA](https://www.w3.org/TR/WCAG20/)

**LESS specifics**

- CSS custom properties (design tokens) preferred over LESS variables
  for new code

- `@import` only for mixins and variables (`variables.less`,
  `mixins.less`); do not use `@import` for bundling conceptually related
  files

- Omit `.less` extension in `@import` statements

- Bundle related files via the `styles` array in `skin.json` /
  `extension.json`

**Anti-patterns to avoid**

- `!important` — avoid except when overriding upstream code that also
  uses it

- `z-index` — use natural DOM stacking order where possible; document
  exceptions

- Inline `style` attributes — always use stylesheet classes instead

- `float` / `text-align: left` hardcoded — use `/* @noflip */`
  annotation when needed, otherwise ResourceLoader’s CSSJanus handles
  RTL automatically

# Test Workflow

**Test-first approach**

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**Test environment setup**

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

**PageForms local development workflow (volume mount)**

This repository uses a `build/docker-compose.override.yml`
(developer-local, git-ignored) that bind-mounts the extension source
directory into the running container. This means `make install` is *not*
required before every test run — source file changes on the host are
immediately visible in the container.

The full setup and rationale is documented in
`build/docs/use-case-local-development.adoc`.

`make install` is only needed:

- At the start of a new session (first run)

- After `composer.json` or `package.json` changes

- After changing a bundled extension version variable

- After changing `setup_extension`

Use these targets for iterative work — do NOT prefix them with
`make install`:

``` console
# PHP iteration — lint (full) + phpunit filtered by class or method:
make php-test FILTER=PFFormCacheTest
make php-test FILTER=PFFormCacheTest::testGetPreloadedText

# JS iteration — eslint + banana-checker + qunit, test file derived from source file:
# libs/PF_foo.js → tests/node-qunit/PF_foo.test.js (automatically)
make js-test FILE=libs/PF_formInput.js

# Pre-commit gate — all checks without reinstalling (~2–3 min):
make dev-test 2>&1 | tee /tmp/dev-test.log; echo "EXIT:$?"

# Phan — only before releases or after major refactoring (~1 min):
make composer-phan 2>&1 | tee /tmp/phan.log; echo "EXIT:$?"
```

<div class="important">

Always pipe every `make` invocation through `tee` to capture the full
output in a log file — never pipe directly into `grep`. Log first,
analyse after:

</div>

``` console
make ci 2>&1 | tee /tmp/ci.log; echo "EXIT:$?"
make dev-test 2>&1 | tee /tmp/dev-test.log; echo "EXIT:$?"
```

Then analyse the log:

``` console
grep -E "OK$|FAILURES|Tests:|pass|fail" /tmp/ci.log | tail -10
```

**Coverage analysis**

Look up the matrix entry with `coverage: true` in
`.github/workflows/ci.yml` and use exactly those version parameters for
all local coverage commands.

Run the full coverage suite (generates HTML + Clover XML under
`build/coverage/php/`):

``` console
make install composer-test-coverage MW_VERSION=<mw> PHP_VERSION=<php> SMW_VERSION=<smw> DT_VERSION=<dt>
```

The coverage output is mounted from the container to the host and is
available at `build/coverage/php/` after the run:

- `build/coverage/php/index.html` — browsable HTML report

- `build/coverage/php/coverage.xml` — Clover XML (used by Codecov)

To analyse coverage for a **specific file**, bash into the running
container after the `make install` step and run PHPUnit directly with
`--coverage-clover` and a `--filter` matching the test class name.

<div class="important">

`XDEBUG_MODE=coverage` must be set explicitly — without it, no coverage
data is collected and the output will show 0% for everything.

</div>

``` console
make install MW_VERSION=<mw> PHP_VERSION=<php> SMW_VERSION=<smw> DT_VERSION=<dt>
make bash
> XDEBUG_MODE=coverage composer phpunit -- --coverage-clover /tmp/cov.xml --filter PFAutocompleteAPI
```

<div class="note">

`--filter` matches against test **class and method names**, not source
file names. To cover `PF_AutocompleteAPI.php` filter for the test class
`PFAutocompleteAPI`.

</div>

Extract coverage numbers from the Clover XML inside the container:

``` console
> python3 - <<'EOF'
import xml.etree.ElementTree as ET
tree = ET.parse('/tmp/cov.xml')
for f in tree.getroot().iter('file'):
    if 'PF_AutocompleteAPI' in f.get('name', ''):
        covered = [int(l.get('num')) for l in f.iter('line') if int(l.get('count', 0)) > 0]
        missed  = [int(l.get('num')) for l in f.iter('line') if int(l.get('count', 0)) == 0]
        total   = len(covered) + len(missed)
        pct     = round(len(covered)/total*100, 1) if total else 0
        methods = {}
        for l in f.iter('line'):
            n = l.get('name')
            if n:
                methods.setdefault(n, 0)
                if int(l.get('count', 0)) > 0:
                    methods[n] += 1
        cov_m = sum(1 for v in methods.values() if v > 0)
        print(f'Lines:   {len(covered)}/{total} ({pct}%)')
        print(f'Methods: {cov_m}/{len(methods)}')
EOF
```

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

**Before/after coverage comparison**

When adding tests to improve coverage, always measure before **and**
after and present the comparison to the user. Both measurements reuse
the same running container — no second `make install` is needed.

*AFTER* measurement: run the coverage command above with the new tests
in place and save the output.

*BEFORE* measurement: temporarily swap the test file inside the running
container with the old version from git, measure, then restore:

``` console
# Save old test file from git to host
git show <old-commit>:tests/phpunit/integration/includes/PFMyClassTest.php > /tmp/PFMyClassTest_before.php

# Copy it into the container, measure, restore
make bash
> cp tests/phpunit/integration/includes/PFMyClassTest.php /tmp/PFMyClassTest_new.php
> cp /tmp/PFMyClassTest_before.php tests/phpunit/integration/includes/PFMyClassTest.php
> XDEBUG_MODE=coverage composer phpunit -- --coverage-clover /tmp/cov_before.xml --filter PFMyClass
> cp /tmp/PFMyClassTest_new.php tests/phpunit/integration/includes/PFMyClassTest.php
```

Then extract numbers from `/tmp/cov_before.xml` using the Python snippet
above, and compare with the AFTER numbers.

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

## SMW property-based JSONScript tests — deferred update isolation on MW 1.43

On MW 1.43 + SMW 6.0.1, the SMW store is empty when the first
property-dependent test file runs in isolation (e.g. via `--filter` on a
fresh PHPUnit process).

**Root cause**: `SMWIntegrationTestCase::resetGlobalInstance()` destroys
MW’s test-scoped LBFactory and replaces it with SMW’s own LBFactory.
This creates two separate DB connections. Page writes go through SMW’s
connection; MW’s `tearDown` truncates only its own tables. On the very
first test file in a fresh process the SMW store table therefore
contains no data when assertions run.

<div class="note">

An earlier diagnosis blamed `LinksUpdate` being a
`TransactionRoundAwareUpdate` that requires
`LBFactory::commitPrimaryChanges()`. That was incorrect — the real cause
is the dual-LBFactory split described above.

</div>

**Fixed in SMW 7.0.0**: Commit `7dcedd8b8` ("Align test DB lifecycle
with MW", 2026-03-16) removes the separate `TestDatabaseTableBuilder` /
`TestDatabaseConnectionProvider` infrastructure entirely and uses MW’s
native test lifecycle. Once the CI matrix is upgraded to SMW 7.0.0 the
issue is gone.

**Effect until then**: The first property-dependent JSONScript test file
run in isolation (e.g. via `--filter`) on a fresh MW 1.43 container
returns empty API results. Subsequent test files in the same PHPUnit
process pass because the SMW store is warmed up by earlier tests.

**Full suite unaffected**: When the full test suite runs (as in GH CI),
earlier test files warm up the SMW store, so property tests always pass.
This is the authoritative validation gate.

**Mitigation applied**: Add
`"beforeTest": { "job-run": ["smw.update"] }` to every JSONScript test
file that creates pages with SMW property annotations. This flushes any
jobs that may be enqueued and triggers
`executePendingDeferredUpdates()`, which is the closest we can get to
forcing a store update from outside SMW’s internal transaction
management.

``` json
"beforeTest": {
    "job-run": [
        "smw.update"
    ]
},
```

**For local development**: Use MW 1.39 (the default `make install`
target) for filtered/isolated test runs — the deferred update issue does
not occur there. Only validate against MW 1.43 via the full suite:

``` console
make install composer-phpunit MW_VERSION=1.43 SMW_VERSION=6.0.1 PHP_VERSION=8.3 DT_VERSION=4.0.3
```

**Test naming**: Property-based test files must be named with a prefix
that sorts alphabetically **after** all other property-based test files
(e.g. `api-pfautocomplete_property-value-*`). This prevents accidental
ordering dependencies in the full suite.

**Test data naming**: Always use long, test-case-specific names for
pages, properties, and categories (e.g. `PFTestACPropAllCharsOffText01`
rather than `FooBar`). This prevents cross-test data pollution within
the shared SMW store.

# Conventional Commits

# Conventional Commits Policy

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
