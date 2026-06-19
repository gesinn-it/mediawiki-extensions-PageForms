**Coding Conventions — JavaScript · MediaWiki**

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

**Deprecation handling**

Treat deprecation warnings as errors — they indicate APIs that must be
migrated before the next version drop.

Enable the
[`no-restricted-syntax`](https://eslint.org/docs/latest/rules/no-restricted-syntax)
or a dedicated deprecated-API rule in `.eslintrc.json` to catch known
deprecated mw.\* calls at lint time.

For MediaWiki version-conditional JS (e.g. a module available only from
MW 1.41+), use `mw.config.get( 'wgVersion' )` as a guard:

``` javascript
var mwVersion = mw.config.get( 'wgVersion' ).split( '.' ).map( Number );
var hasFoo = mwVersion[ 0 ] > 1 || ( mwVersion[ 0 ] === 1 && mwVersion[ 1 ] >= 41 );
if ( hasFoo ) {
    // MW ≥ 1.41: use new API — remove guard when MW < 1.41 support is dropped
    mw.foo.bar();
} else {
    // MW < 1.41 fallback
    mw.oldFoo.bar();
}
```

Rules:

- Add a comment on the `else`-branch with the minimum MW version that
  makes the guard removable

- When that version is dropped, delete the entire `if/else` and keep
  only the `if`-branch body

- Search for guards to remove: `grep -rn "wgVersion" resources/`
