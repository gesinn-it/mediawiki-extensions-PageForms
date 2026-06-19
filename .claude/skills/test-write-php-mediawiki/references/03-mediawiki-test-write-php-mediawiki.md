**Procedure — test:write · MediaWiki**

Before making any code changes to fix a bug or implement a feature:

1.  Check whether an existing test already covers the described
    behavior.

2.  If not, write or adapt a test that reproduces the issue — it must
    fail first.

3.  Only after a failing test exists, make the code changes.

4.  Re-run the test to confirm it passes (green).

**MediaWiki test base classes**

Use the appropriate base class:

- `MediaWikiUnitTestCase` — pure unit tests (no database, no service
  container); fastest

- `MediaWikiIntegrationTestCase` — integration tests that need the
  service container or database

- `MediaWikiLangTestCase` — when language handling is under test

- `SpecialPageTestBase` — extends `MediaWikiIntegrationTestCase`; use
  when testing Special Pages

- `HookRunnerTestBase` — unit test base for `HookRunner` classes;
  validates hook delegation automatically

Do **not** extend `MediaWikiIntegrationTestCase` by default — use
`MediaWikiUnitTestCase` unless integration with MW services is required.

**Testing Special Pages with SpecialPageTestBase**

Extend `SpecialPageTestBase` and implement `newSpecialPage()` to return
the page instance:

``` php
class SpecialFooTest extends SpecialPageTestBase {
    protected function newSpecialPage() {
        return MediaWikiServices::getInstance()
            ->getSpecialPageFactory()->getPage( 'Foo' );
    }
}
```

Key methods available in tests:

- `$this→executeSpecialPage( $subpage, new FauxRequest( $query, $isPosted ) )`
  — renders the special page and returns `[ $html, $context ]`

- `$this→setUserLang( 'qqx' )` — use `qqx` locale so message keys appear
  literally in output, making assertions locale-independent

- `$this→setGroupPermissions( '*', 'edit', false )` — adjust
  permissions; combine with
  `$this→expectException( PermissionsError::class )` to test access
  control

- `$this→insertPage( $title, $content )` — create a page as fixture

- `$this→getServiceContainer()` — access MW services

Annotate the class with `@group Database` when the test writes to the
database.

If the special page requires a permission (e.g. `upload`), pass a
matching performer as the fourth argument — otherwise
`executeSpecialPage()` runs as an anonymous user and throws
`PermissionsError`:

``` php
$performer = $this->getTestUser( [ 'sysop' ] )->getAuthority();
[ $html, ] = $this->executeSpecialPage( '', new FauxRequest( [] ), null, $performer );
```

If the page checks a feature flag via config (e.g.
`UploadBase::isEnabled()` reads `wgEnableUploads`), set it before
calling `executeSpecialPage()`:

``` php
$this->setMwGlobals( 'wgEnableUploads', true );
```

**Asserting HTML output**

Parse the returned `$html` with `DomDocument` and `DomXPath` to assert
on form fields, links, or rendered content:

``` php
[ $html, ] = $this->executeSpecialPage( '', new FauxRequest( [] ) );
$dom = new DomDocument;
$dom->loadHTML( $html );
$xpath = new DomXpath( $dom );
$input = $xpath->query( '//input[@name="wpFoo"]' )->item( 0 );
$this->assertNotNull( $input );
```

**Testing HookRunner classes with HookRunnerTestBase**

If the extension has a `HookRunner` class (a class that implements hook
interfaces and delegates to `HookContainer::run()`), add a unit test
that extends `HookRunnerTestBase`. This test automatically verifies that
every hook method delegates correctly — right hook name, right argument
signature.

First check whether a `HookRunner` exists:

``` console
find includes -name "*HookRunner.php" | head -1
```

If one exists, the test is a one-liner:

``` php
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \FooHookRunner
 */
class FooHookRunnerTest extends HookRunnerTestBase {
    public static function provideHookRunners() {
        yield FooHookRunner::class => [ FooHookRunner::class ];
    }
}
```

Place the test under `tests/phpunit/unit/` — no database needed.

Do **not** introduce a `HookRunner` class just to have this test. Only
add the test when the extension already uses the Hook Runner pattern.

**Testing parser functions**

Extend `MediaWikiIntegrationTestCase` and use the shared parser
singleton for simple parse-and-assert tests:

``` php
$parserOutput = $this->getServiceContainer()->getParser()->parse(
    $wikitext,
    Title::makeTitle( NS_MAIN, 'Test' ),
    ParserOptions::newFromAnon()
);
```

Use `getParserFactory()→create()` only when you need to mutate the
parser instance (e.g. registering a custom tag hook via `setHook()`).

Extract the raw HTML with `getRawText()` — do **not** use `getText()`,
which is deprecated since MW 1.42 (T353257) and has side-effects on
`ParserOutput`:

``` php
$html = $parserOutput->getRawText();
```

`Parser::parse()` wraps inline content in `<p>…</p>\n`. Strip it with
`Parser::stripOuterParagraph()` before asserting on plain text output:

``` php
$text = Parser::stripOuterParagraph( $parserOutput->getRawText() );
$this->assertSame( 'expected', $text );
```

Combine with `@dataProvider` to express wikitext → output cases as a
table:

``` php
public static function provideParserFunction(): array {
    return [
        'basic case'  => [ '{{#myfunc:a|b}}', 'expected output' ],
        'empty input' => [ '{{#myfunc:}}',    '' ],
    ];
}

#[DataProvider( 'provideParserFunction' )]
public function testMyFunc( string $wikitext, string $expected ): void {
    $out = $this->getServiceContainer()->getParser()->parse(
        $wikitext,
        Title::makeTitle( NS_MAIN, 'Test' ),
        ParserOptions::newFromAnon()
    );
    $this->assertSame(
        $expected,
        Parser::stripOuterParagraph( $out->getRawText() )
    );
}
```

Annotate the class with `@group Database` — the parser service requires
the database to be initialised even when no pages are written.

**Test fixtures**

- Use `setUp()` and `tearDown()` for test-scoped fixtures

- For database fixtures, use `addDBDataOnce()` (run once per class) or
  `addDBData()` (run per test)

- Use `getMockBuilder()` / `createMock()` for dependencies; prefer
  constructor injection so mocks can be passed in

**Running tests**

See the **Execution — Install Dependencies · MediaWiki** and **Execution
— Run Tests (PHPUnit) · MediaWiki** reference files loaded by this
skill.
