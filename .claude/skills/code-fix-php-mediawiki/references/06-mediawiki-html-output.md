'''

*Coding Conventions — HTML Output · MediaWiki*

MediaWiki extensions must never concatenate raw HTML strings with unescaped variables.
Use the two sanctioned approaches below depending on complexity.

*`Html` class — simple elements and inputs*

Use https://www.mediawiki.org/wiki/Manual:Html.php[`Html`] for individual elements, form inputs, and attribute construction.
`Html::element()` escapes both attribute values and text content automatically.
`Html::rawElement()` escapes attribute values but passes the inner HTML through unchanged — use only when the content is already safe.

This is explicitly required by https://www.mediawiki.org/wiki/Best_practices_for_extensions[Best practices for extensions]:
"MUST: Use MediaWiki's validation/sanitization methods, e.g. those in the `Html` and `Sanitizer` classes."

[source,php]
----
// Safe: both attributes and text content are escaped
$html = Html::element( 'span', [ 'class' => 'pf-date-input__error' ], $userText );

// Safe: attributes escaped; $innerHtml must already be safe
$html = Html::rawElement( 'div', [ 'class' => 'pf-date-input' ], $innerHtml );

// Form inputs
$html = Html::hidden( 'wpEditToken', $token );
$html = Html::input( 'q', $value, 'search', [ 'class' => 'pf-search__input' ] );
----

Anti-patterns to avoid:

* `'<span class="' . $class . '">' . $text . '</span>'` — no escaping, XSS risk
* Direct use of `$_GET` / `$_POST` in HTML — use `WebRequest` first

*`TemplateParser` + Mustache — complex HTML structures*

Use https://www.mediawiki.org/wiki/Manual:TemplateParser[`TemplateParser`] for larger HTML blocks, conditional structures, and repeated elements.
Mustache templates (`.mustache` files) separate logic from markup and are testable in isolation.
This approach has been the recommended standard since MediaWiki 1.25 (RFC: HTML templating library, approved 2014).

[source,php]
----
// In your class constructor or method:
$templateParser = new TemplateParser( __DIR__ . '/../templates' );
$html = $templateParser->processTemplate( 'MyWidget', [
    'label'    => $label,       // auto-escaped by Mustache {{label}}
    'items'    => $items,       // array of associative arrays for {{#items}} sections
    'isActive' => $isActive,
] );
----

[source,html]
----
{{! templates/MyWidget.mustache }}
<div class="pf-widget{{#isActive}} pf-widget--active{{/isActive}}">
    <label class="pf-widget__label">{{label}}</label>
    <ul class="pf-widget__list">
        {{#items}}<li class="pf-widget__item">{{name}}</li>{{/items}}
    </ul>
</div>
----

Rules:

* Store +*.mustache+ files under a `templates/` directory alongside your PHP source
* Use `{{variable}}` (HTML-escaped) by default; `{{{variable}}}` (unescaped) only for already-safe HTML
* Do not build HTML strings inside PHP and pass them into a Mustache variable unless the value is wrapped in `new HtmlArmor( $html )`
* Prefer Mustache for any structure with more than two or three nested elements

*Choosing between the two*

[cols="1,1"]
|===
| Situation | Use

| Single element, input, or short attribute construction
| `Html::element()` / `Html::rawElement()`

| Form controls (hidden fields, inputs)
| `Html::hidden()` / `Html::input()`

| Multi-element blocks, conditionals, loops
| `TemplateParser` + Mustache

| Skin or full-page templating
| `SkinMustache`
|===

*Semantic CSS classes — customizability*

Every rendered component must carry CSS classes that allow site administrators and users to target elements via `MediaWiki:Common.css` or a site skin without reading extension source code.
Use BEM (Block__Element--Modifier) with the extension-specific prefix defined in `css.adoc`.

Levels:

* *Block* — the outermost container of a component: `{prefix}-{component}` (e.g. `pf-date-input`)
* *Element* — a named functional part of the block: `{prefix}-{component}__{part}` (e.g. `pf-date-input__label`, `pf-date-input__input`, `pf-date-input__help`)
* *Modifier* — a state or variant: `{prefix}-{component}--{state}` (e.g. `pf-date-input--disabled`, `pf-date-input--error`)

Rules:

* Every component wrapper receives a block class — no anonymous `<div>` containers
* Functional sub-elements (label, input, error message, help text, icon) each receive an element class
* States (disabled, required, error, loading) are expressed as modifier classes on the block, not via inline styles or ad-hoc class names
* Modifiers are added alongside the block class, not instead of it: `class="pf-date-input pf-date-input--error"`
* IDs are allowed for ARIA relationships (`aria-describedby`, `aria-labelledby`) but must not be the primary styling hook

Example:

[source,html]
----
<div class="pf-date-input pf-date-input--required">
    <label class="pf-date-input__label" for="pf-date-input-1">Start date</label>
    <input class="pf-date-input__input" id="pf-date-input-1" type="text" />
    <span class="pf-date-input__help">Format: YYYY-MM-DD</span>
    <span class="pf-date-input__error">Invalid date</span>
</div>
----

*Migration strategy for existing code*

Changing CSS class names on rendered HTML is a breaking change for any site that targets those classes in `MediaWiki:Common.css` or a skin.
Apply the following strategy — do not rename classes silently.

[cols="1,2,2"]
|===
| Code status | Rule | Action

| New code
| BEM required
| Apply BEM from the start — no legacy class needed

| Existing code being modified
| Migrate on touch
| Add BEM class, keep old class alongside it, document as deprecated in `CHANGELOG.md`

| Existing code not being touched
| Leave as-is
| Do not migrate speculatively — only when the file is opened for another reason

|===

Deprecation procedure when migrating existing classes:

. Add the BEM class alongside the old class: +
  `class="pf-date-input pf-dateinput"` — old class last
. Add a `CHANGELOG.md` entry under `Deprecated`: +
  "CSS class `pf-dateinput` deprecated; use `pf-date-input` instead. Old class will be removed in the next major release."
. Remove the old class in the next major version bump and add a `Breaking Changes` entry to `CHANGELOG.md`
