---
applyTo: "**/*.php"
---
<!-- AUTO-GENERATED from docs/gesinn-it-docs-master-pub/documents/mediawiki/instructions/php.adoc -->

**Procedure — code:write**

1.  Write a failing test that specifies the expected behavior. The test
    must fail before you write any implementation.

2.  Write the minimum implementation to make the test pass.

3.  Refactor if needed — tests must stay green.

4.  Never write implementation before a failing test exists.

**Procedure — code:fix**

1.  Reproduce the bug with a failing test first. This test is the proof
    the bug exists.

2.  Fix the code until the test passes.

3.  Never fix code without a reproducing test — you cannot verify the
    fix is correct.

4.  If the fix addresses a reported issue: after pushing, close the
    issue in the issue tracker with a comment referencing the commit.

**Procedure — code:refactor**

1.  Run the full test suite first. All tests must be green before you
    start.

2.  Check test coverage for the files you intend to change. If coverage
    is below ~80% on the affected code paths, warn explicitly before
    proceeding: low coverage means the refactoring cannot be verified
    safely. Do not block, but make the risk visible.

3.  Make structural changes (extract method, rename, move class, etc.).

4.  Run the full test suite again. All tests must still be green.

5.  If a test breaks, you changed behavior — revert the change or
    explicitly justify updating the test.

6.  Never change test logic during a refactor unless the test itself was
    wrong.

**Procedure — test:write**

The goal is correct code, not just passing tests. Use the
**specification** (issue, docs, method name, contract) as the source of
truth — never the current output of the production code.

1.  Check whether the described behavior is already covered by existing
    tests.

2.  Understand the **intent** of the code under test: what should it do,
    for whom, under which conditions? Read the specification, not just
    the implementation.

    - If no specification exists (no issue description, no docs, no
      method contract) and the intent cannot be confidently derived from
      the code alone: **stop and ask**. State what is unclear and what
      information is needed before proceeding. Do not infer tests from
      implementation details alone.

3.  Write the new test(s) that assert the intended behavior —
    independently of how the code currently works.

4.  Run the targeted test class.

    - If all new tests are green: the code matches its specification.
      Done.

    - If a new test fails: the code deviates from its specification —
      this is a bug discovery. Do **not** adjust the test to match the
      actual output. Fix the production code so it fulfills the
      specification (follow the `fix` procedure for the code change).
      The test stays as written.

5.  Never adjust a test to match incorrect production code behavior.

**Coding Conventions — MediaWiki**

All source files regardless of language must follow these baseline
rules. They are enforced by `make ci` (lint + phpcs + eslint).

- Encoding: UTF-8 without BOM

- Line endings: Unix-style LF (not CR+LF)

- Indentation: tabs, not spaces

- Maximum line length: 120 characters

- No trailing whitespace

- Newline at end of file

**Coding Conventions — PHP**

**File structure**

- Every file starts with `declare( strict_types=1 );`

- No closing `?>` tag

- One class per file; filename matches class name (UpperCamelCase, e.g.
  `MyClass.php`)

**Namespaces and autoloading**

- PSR-4 via Composer (`autoload.psr-4` in `composer.json`)

- Acronyms treated as single words: `HtmlId`, not `HTMLId`

**Naming**

| Element                     | Convention     | Example            |
|-----------------------------|----------------|--------------------|
| Classes, interfaces, traits | UpperCamelCase | `PageFormParser`   |
| Methods, variables          | lowerCamelCase | `getFormContent()` |
| Constants                   | UPPER_CASE     | `MAX_FORM_SIZE`    |

**Type system**

- Use native type declarations on all parameters, properties, and return
  types

- PHPDoc only when native types are insufficient (e.g. `string[]`,
  `array<string, Foo>`)

- Nullable parameters: `?Type`, not `Type $x = null`

- Prefer `??` (null coalescing) and `??=` over ternary isset checks

- Use arrow functions `fn( $x ) => $x * 2` for single-expression
  closures

**Modern PHP features (target: PHP 8.1+)**

- Constructor property promotion

- `readonly` properties for immutable value objects

- `enum` instead of class constant groups

- `match()` instead of `switch` when returning a value

**Code style**

- Indentation: tabs, not spaces

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

- Order class members: `public` → `protected` → `private`

**Deprecation handling**

Treat deprecation warnings as errors — they are signals of technical
debt that must be resolved, not suppressed.

Configure `phpunit.xml` to convert `E_USER_DEPRECATED` to a test
failure:

``` xml
<phpunit convertDeprecationsToExceptions="true">
    ...
</phpunit>
```

When a test triggers a deprecation from code under your control, fix the
code to use the non-deprecated API. When the deprecation originates from
a third-party dependency outside your control, suppress it at the call
site with a comment:

``` php
// @deprecated-call: Foo::bar() deprecated in lib 2.3, remove when lib ≥ 3.0 is required
@trigger_error( '', E_USER_DEPRECATED );  // suppress in test output
$result = Foo::bar();
```

Never use `@` suppression without an explanatory comment and a removal
condition.

**Coding Conventions — PHP · MediaWiki**

Tooling:
[mediawiki-codesniffer](https://github.com/wikimedia/mediawiki-tools-codesniffer)
via PHPCS. Run locally: `make composer-phpcs` (or `make ci`).

**Source directories**

- New code belongs in `src/` following PSR-4; `includes/` is legacy and
  should be migrated incrementally

**Namespaces**

- Top-level namespace = extension name (e.g.
  `MediaWiki\Extension\FooBar...`)

**Global variable prefix**

- Global variables: `$wg` prefix (e.g. `$wgPageFormsSettings`)

**Request handling**

- No superglobals (`$_GET`, `$_POST`) — use `WebRequest` via
  `RequestContext`

- No new global functions — use static utility classes (`Html`, `IP`) if
  needed

**Version guards**

Use version guards to call the correct API across supported MediaWiki
versions while preventing deprecation warnings.

``` php
if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
    $html = $parserOutput->getRawText();
} else {
    // MW < 1.42: getRawText() did not exist; getText() was the only option
    $html = $parserOutput->getText();
}
```

Rules:

- Use `MW_VERSION` — never read `$wgVersion` directly

- Use `version_compare()` — never compare version strings with `===` or
  `>=` operators

- Write the guard condition so the **new** (non-deprecated) path is the
  `if`-branch

- Add a comment on the `else`-branch naming the deprecated call and the
  minimum version that removes the guard

- Name version boundaries with the **first** version that ships the new
  API, not the last that ships the old one

**Removing version guards**

When support for a MediaWiki version is dropped:

1.  Search for all guards referencing that version:  
    `grep -rn "version_compare.MW_VERSION.'1.XX'" src/ includes/`

2.  Delete the entire `if/else` block and keep only the `if`-branch body
    (the new path)

3.  Delete any `@deprecated-call` comments that referenced the dropped
    version

4.  Run the full test suite and linters to confirm nothing regressed

**Static Analysis — Phan · PHP**

Tooling: [Phan](https://github.com/phan/phan) with
[mediawiki-phan-config](https://github.com/wikimedia/mediawiki-phan-config).
Run locally: `make composer-phan` (or `make dev-test`).

**Setup**

Add the Phan script to `composer.json`:

``` json
"scripts": {
    "phan": "phan --allow-polyfill-parser"
}
```

<div class="note">

`--allow-polyfill-parser` activates a pure-PHP AST fallback. Required
when the native `php-ast` extension is not available (e.g. Debian trixie
/ PHP 8.3 where `php-ast` has no apt package). Without this flag Phan
exits immediately if `php-ast` is absent.

</div>

Add the following targets to the extension `Makefile`:

``` makefile
composer-phan: .init ## Run Phan static analysis
    $(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan $(COMPOSER_PARAMS)"

composer-phan-update-baseline: .init ## Re-generate baseline and fix indentation for PHPCS
    $(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan -- --save-baseline=.phan/baseline.php"
    unexpand --first-only -t 4 .phan/baseline.php > /tmp/baseline.php && mv /tmp/baseline.php .phan/baseline.php
```

<div class="note">

The `unexpand` post-processing step is required because Phan hardcodes
4-space indentation in `BaselineSavingPlugin.php` — this cannot be
configured via CLI or `config.php`. MediaWiki PHPCS enforces tabs, so
committing the unmodified baseline will cause PHPCS failures. On macOS
where `unexpand --first-only` is unavailable, use `sed` instead:  
`sed -i 's/ /\t/g' .phan/baseline.php`

</div>

**Configuration**

`.phan/config.php` inherits from `mediawiki-phan-config`:

``` php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['baseline_path'] = __DIR__ . '/baseline.php';

$cfg['directory_list'] = array_merge(
    $cfg['directory_list'],
    ['src', 'includes', 'specials']
);

$cfg['exclude_analysis_directory_list'] = array_merge(
    $cfg['exclude_analysis_directory_list'],
    ['vendor/']
);

return $cfg;
```

**Baseline**

- `.phan/baseline.php` is auto-generated — do not edit it manually

- New code must not introduce Phan issues beyond the current baseline

- When deliberately deferring a pre-existing issue, update the baseline
  via the dedicated target:  
  `make composer-phan-update-baseline`  
  This re-generates `.phan/baseline.php` and converts Phan’s hardcoded
  4-space indentation to tabs (required by MediaWiki PHPCS). Never run
  `--save-baseline` directly without this post-processing step.

- When suppressing with `@suppress`, always add an explanatory comment
