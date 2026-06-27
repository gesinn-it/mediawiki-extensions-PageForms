**Coding Conventions — HTML Output · MediaWiki**

MediaWiki extensions must never concatenate raw HTML strings with
unescaped variables. Use the two sanctioned approaches below depending
on complexity.

**`Html` class — simple elements and inputs**

Use [`Html`](https://www.mediawiki.org/wiki/Manual:Html.php) for
individual elements, form inputs, and attribute construction.
`Html::element()` escapes both attribute values and text content
automatically. `Html::rawElement()` escapes attribute values but passes
the inner HTML through unchanged — use only when the content is already
safe.

This is explicitly required by [Best practices for
extensions](https://www.mediawiki.org/wiki/Best_practices_for_extensions):
"MUST: Use MediaWiki’s validation/sanitization methods, e.g. those in
the `Html` and `Sanitizer` classes."

``` php
// Safe: both attributes and text content are escaped
$html = Html::element( 'span', [ 'class' => 'pf-error' ], $userText );

// Safe: attributes escaped; $innerHtml must already be safe
$html = Html::rawElement( 'div', [ 'id' => 'pf-container' ], $innerHtml );

// Form inputs
$html = Html::hidden( 'wpEditToken', $token );
$html = Html::input( 'q', $value, 'search', [ 'class' => 'pf-search' ] );
```

Anti-patterns to avoid:

- `'<span class="' . $class . '">' . $text . '</span>'` — no escaping,
  XSS risk

- Direct use of `$_GET` / `$_POST` in HTML — use `WebRequest` first

**`TemplateParser` + Mustache — complex HTML structures**

Use
[`TemplateParser`](https://www.mediawiki.org/wiki/Manual:TemplateParser)
for larger HTML blocks, conditional structures, and repeated elements.
Mustache templates (`.mustache` files) separate logic from markup and
are testable in isolation. This approach has been the recommended
standard since MediaWiki 1.25 (RFC: HTML templating library, approved
2014).

``` php
// In your class constructor or method:
$templateParser = new TemplateParser( __DIR__ . '/../templates' );
$html = $templateParser->processTemplate( 'MyWidget', [
    'label'    => $label,       // auto-escaped by Mustache {{label}}
    'items'    => $items,       // array of associative arrays for {{#items}} sections
    'isActive' => $isActive,
] );
```

``` html
{{! templates/MyWidget.mustache }}
<div class="pf-widget{{#isActive}} pf-widget--active{{/isActive}}">
    <label>{{label}}</label>
    <ul>
        {{#items}}<li>{{name}}</li>{{/items}}
    </ul>
</div>
```

Rules:

- Store \*.mustache files under a `templates/` directory alongside your
  PHP source

- Use `{{variable}}` (HTML-escaped) by default; `{{{variable}}}`
  (unescaped) only for already-safe HTML

- Do not build HTML strings inside PHP and pass them into a Mustache
  variable unless the value is wrapped in `new HtmlArmor( $html )`

- Prefer Mustache for any structure with more than two or three nested
  elements

**Choosing between the two**

| Situation                                              | Use                                      |
|--------------------------------------------------------|------------------------------------------|
| Single element, input, or short attribute construction | `Html::element()` / `Html::rawElement()` |
| Form controls (hidden fields, inputs)                  | `Html::hidden()` / `Html::input()`       |
| Multi-element blocks, conditionals, loops              | `TemplateParser` + Mustache              |
| Skin or full-page templating                           | `SkinMustache`                           |
