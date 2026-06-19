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
