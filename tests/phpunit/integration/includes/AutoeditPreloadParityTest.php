<?php

use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\Extension\PageForms\HtmlFormDataExtractor;
use MediaWiki\MediaWikiServices;
use OOUI\BlankTheme;

/**
 * The two readers of "the values a form's fields have on an existing page" must agree.
 *
 * - formHTML() for an existing page (not submitted), followed by HtmlFormDataExtractor::extract():
 *   what the FORMEDIT branch of PFAutoeditAPI still does.
 * - FormDefParser::readPageValues(): what SAVE / PREVIEW / DIFF use.
 *
 * Where they differ today the difference is listed in knownDifferences() with its reason,
 * so that the list is the honest state of the two readers and shrinks when they are unified.
 * A case that is listed there but no longer differs fails, so the list cannot go stale.
 *
 * @covers \MediaWiki\Extension\PageForms\FormDefParser::readPageValues
 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
 * @group PF
 * @group Database
 * @group medium
 */
class AutoeditPreloadParityTest extends MediaWikiIntegrationTestCase {

	private const SAVE_INPUTS = "{{{standard input|free text}}}\n{{{standard input|save}}}";

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );
		// The permission check of formHTML() is not what is tested here.
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			} );
		parent::setUp();
	}

	/**
	 * What the FORMEDIT branch of PFAutoeditAPI reads: formHTML() for the existing page,
	 * then the values out of the rendered inputs.
	 */
	private function viaFormHtml( string $formDef, string $page ): array {
		global $wgOut;
		$wgOut->getContext()->setTitle( Title::newFromText( 'AEParityTarget' ) );
		// Not the global FormPrinter: it keeps the services of the test that built it first.
		[ $html ] = ( new FormPrinter() )->formHTML(
			$formDef, false, true, null, $page, 'AEParityTarget', null,
			false, false, false, [], $this->getTestUser()->getUser(),
			new FauxRequest( [], false )
		);
		$options = [];
		$data = HtmlFormDataExtractor::extract( $html, $options );
		// The form's own hidden inputs (edit token, timestamps) are not values of the page.
		return self::withoutEmpty( array_diff_key( $data, array_flip( [
			'wpStarttime', 'wpEdittime', 'editRevId', 'wpEditToken', 'wpUnicodeCheck', 'wpUltimateParam',
		] ) ) );
	}

	/**
	 * What SAVE / PREVIEW / DIFF read: the values straight from the page text.
	 */
	private function viaPageValues( string $formDef, string $page ): array {
		return self::withoutEmpty( ( new FormPrinter() )->readPageValues( $formDef, $page )->toOptions() );
	}

	/**
	 * An empty value and no value save the same page text, and the HTML route reports an empty
	 * entry for every field of the form, so empty entries are left out of the comparison.
	 */
	private static function withoutEmpty( array $values ): array {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::withoutEmpty( $value );
			}
			if ( $value === '' || $value === [] ) {
				unset( $values[$key] );
			} else {
				$values[$key] = $value;
			}
		}
		return $values;
	}

	/**
	 * @return array<string, array{0: string, 1: string}> form definition, page
	 */
	private static function cases(): array {
		$s = self::SAVE_INPUTS;
		return [
			'single template, one field changed' => [
				"{{{for template|AEParityA}}}\n{{{field|a}}}\n{{{field|b}}}\n{{{end template}}}\n$s",
				"{{AEParityA\n|a=1\n|b=2\n}}\n",
			],
			'mapping template, raw key submitted' => [
				"{{{for template|AEParityMap}}}\n"
					. "{{{field|m|input type=dropdown|values=a,b,c|mapping template=AEParityMapTpl}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityMap\n|m=b\n}}\n",
			],
			'mapping template, field untouched' => [
				"{{{for template|AEParityMap2}}}\n"
					. "{{{field|m|input type=dropdown|values=a,b,c|mapping template=AEParityMapTpl}}}\n"
					. "{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParityMap2\n|m=b\n|o=1\n}}\n",
			],
			'list field untouched' => [
				"{{{for template|AEParityList}}}\n"
					. "{{{field|l|list|delimiter=;|input type=tokens|values=a,b,c}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityList\n|l=a;b\n|o=1\n}}\n",
			],
			'date field untouched' => [
				"{{{for template|AEParityDate}}}\n{{{field|d|input type=date}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDate\n|d=2026-10-06\n|o=1\n}}\n",
			],
			'datetime field untouched' => [
				"{{{for template|AEParityDateTime}}}\n{{{field|d|input type=datetime}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDateTime\n|d=2026-10-06 12:30:00\n|o=1\n}}\n",
			],
			'hidden field untouched' => [
				"{{{for template|AEParityHidden}}}\n{{{field|a|hidden}}}\n{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParityHidden\n|a=h\n|o=1\n}}\n",
			],
			'default value does not overwrite the page value' => [
				"{{{for template|AEParityDefault}}}\n{{{field|a|default=DEF}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDefault\n|a=real\n|o=1\n}}\n",
			],
			'template parameter the form does not define is kept' => [
				"{{{for template|AEParityUnhandled}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityUnhandled\n|a=1\n|legacy=keep\n}}\n",
			],
			'template parameter the form does not define, positional and named' => [
				"{{{for template|AEParityUnhandled2}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityUnhandled2|a=1|legacy=keep|positional}}\n",
			],
			'embedded template in a holds-template field' => [
				"{{{for template|AEParityHolds}}}\n{{{field|Items|holds template}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n"
					. "{{{for template|AEParityEmb|multiple|embed in field=AEParityHolds[Items]}}}\n"
					. "{{{field|Foo}}}\n{{{end template}}}\n$s",
				"{{AEParityHolds\n|Items={{AEParityEmb|Foo=Bar}}{{AEParityEmb|Foo=Baz}}\n|o=1\n}}\n",
			],
			'template in the form but not on the page' => [
				"{{{for template|AEParityX}}}\n{{{field|a}}}\n{{{end template}}}\n"
					. "{{{for template|AEParityY}}}\n{{{field|b}}}\n{{{end template}}}\n$s",
				"{{AEParityX\n|a=1\n}}\n",
			],
			'template on the page but not in the form is kept' => [
				"{{{for template|AEParityZ}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityZ\n|a=1\n}}\n{{AEParityNotInForm|q=1}}\n",
			],
			'template name with a space' => [
				"{{{for template|AEParity Space}}}\n{{{field|a}}}\n{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParity Space\n|a=1\n|o=1\n}}\n",
			],
		];
	}

	private function insertMappingTemplate(): void {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'AEParityMapTpl' ), 'Label {{{1}}}' );
	}

	public static function provideParityCases(): iterable {
		foreach ( self::cases() as $label => $case ) {
			yield $label => $case;
		}
	}

	/**
	 * Cases in which the two readers differ today. Each entry names the reason and pins both
	 * results exactly, so that a change in either reader shows up here, too.
	 *
	 * In all of them the difference is the shape of the value, not the value: the HTML route
	 * yields what the rendered input carries, the page route yields the text on the page. They
	 * are the work list for unifying the two readers.
	 *
	 * @return array<string, array{reason: string, html: array, page: array}>
	 */
	private static function knownDifferences(): array {
		$mapping = 'The HTML route reports the label the dropdown shows plus a map_field marker; '
			. 'the page route reports the value on the page (the marker is added on merging the request).';
		return [
			'mapping template, raw key submitted' => [
				'reason' => $mapping,
				'html' => [ 'AEParityMap' => [ 'map_field' => [ 'm' => 'true' ], 'm' => 'Label b' ] ],
				'page' => [ 'AEParityMap' => [ 'm' => 'b' ] ],
			],
			'mapping template, field untouched' => [
				'reason' => $mapping,
				'html' => [ 'AEParityMap2' => [ 'map_field' => [ 'm' => 'true' ], 'o' => '1', 'm' => 'Label b' ] ],
				'page' => [ 'AEParityMap2' => [ 'm' => 'b', 'o' => '1' ] ],
			],
			'list field untouched' => [
				'reason' => 'The HTML route splits a list into its items plus an is_list marker; '
					. 'the page route keeps the delimited string.',
				'html' => [ 'AEParityList' => [ 'l' => [ 'is_list' => '1', 'a', 'b' ], 'o' => '1' ] ],
				'page' => [ 'AEParityList' => [ 'l' => 'a;b', 'o' => '1' ] ],
			],
			'date field untouched' => [
				'reason' => 'The HTML route splits a date into year, month and day; '
					. 'the page route keeps the date string.',
				'html' => [ 'AEParityDate' => [
					'd' => [ 'day' => '6', 'year' => '2026', 'month' => '10' ],
					'o' => '1',
				] ],
				'page' => [ 'AEParityDate' => [ 'd' => '2026-10-06', 'o' => '1' ] ],
			],
			'datetime field untouched' => [
				'reason' => 'The HTML route splits a datetime into its parts (and an am/pm marker); '
					. 'the page route keeps the datetime string.',
				'html' => [ 'AEParityDateTime' => [ 'd' => [
					'day' => '6', 'year' => '2026', 'hour' => '12', 'minute' => '30', 'second' => '00',
					'month' => '10', 'ampm24h' => 'PM',
				], 'o' => '1' ] ],
				'page' => [ 'AEParityDateTime' => [ 'd' => '2026-10-06 12:30:00', 'o' => '1' ] ],
			],
		];
	}

	/**
	 * @dataProvider provideParityCases
	 */
	public function testBothReadersSeeTheSameValues( string $formDef, string $page ): void {
		$this->insertMappingTemplate();

		$fromHtml = $this->viaFormHtml( $formDef, $page );
		$fromPage = $this->viaPageValues( $formDef, $page );

		$known = self::knownDifferences()[$this->dataName()] ?? null;
		if ( $known === null ) {
			$this->assertSame( $fromHtml, $fromPage );
			return;
		}
		$this->assertEquals( $known['html'], $fromHtml, $known['reason'] );
		$this->assertEquals( $known['page'], $fromPage, $known['reason'] );
	}

	/**
	 * A case listed as a known difference must exist, so that the list cannot go stale.
	 */
	public function testKnownDifferencesNameExistingCases(): void {
		$this->assertSame( [], array_diff_key( self::knownDifferences(), self::cases() ) );
	}
}
