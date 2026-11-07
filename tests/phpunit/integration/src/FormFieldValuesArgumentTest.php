<?php

use MediaWiki\Extension\PageForms\FormPrinterFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * What the 'values' argument of a field gives as the options of the input.
 *
 * The cases without a link or markup pin what 'values' did before they were handled (#279), so that
 * a change in how the argument is read cannot change them unnoticed. Templates, parser functions
 * and magic words are expanded; the result is read as plain text, so a link gives the name of the
 * page and wiki markup or a bare URL stays as it was written.
 *
 * @covers \MediaWiki\Extension\PageForms\FormFieldTagReader
 * @group Database
 */
class FormFieldValuesArgumentTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			}
		);
		global $wgPageFormsFormPrinter;
		$wgPageFormsFormPrinter = FormPrinterFactory::create();
		parent::setUp();
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'PFValuesWithParam' ), '{{{1}}}-t,u' );
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'PFValuesLinks' ), '[[A]],b,[[C d]]' );
	}

	/**
	 * @return string[] The values of the options of the input, in the order of the input
	 */
	private function optionValues( string $inputType, string $arguments ): array {
		global $wgPageFormsFormPrinter, $wgOut;
		$title = Title::makeTitle( NS_MAIN, 'PFValuesPage' );
		$wgOut->getContext()->setTitle( $title );
		RequestContext::getMain()->setTitle( $title );
		$formDef = "{{{for template|T}}}\n{{{field|f|input type=$inputType|$arguments}}}\n{{{end template}}}\n";
		[ $formText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null, 'PFValuesPage', null, false, false, false, [],
			$this->getTestUser()->getUser(), null
		);
		$patterns = [
			'checkboxes' => '/<input type=\'checkbox\'[^>]*value=\'([^\']*)\'/',
			'radiobutton' => '/<input [^>]*type="radio" value="([^"]*)"/',
		];
		$pattern = $patterns[$inputType] ?? '/<option value="([^"]*)"/';
		preg_match_all( $pattern, $formText, $matches );
		return array_values( array_filter(
			array_map( static fn ( $v ) => htmlspecialchars_decode( $v, ENT_QUOTES ), $matches[1] ),
			static fn ( $v ) => $v !== ''
		) );
	}

	/**
	 * @dataProvider provideValues
	 * @param string $values The 'values' argument
	 * @param string[] $expected The values of the options
	 */
	public function testValues( string $values, array $expected ): void {
		foreach ( [ 'dropdown', 'checkboxes' ] as $inputType ) {
			$this->assertSame( $expected, $this->optionValues( $inputType, "values=$values" ), $inputType );
		}
	}

	public static function provideValues(): array {
		return [
			'plain' => [ 'a,b,c', [ 'a', 'b', 'c' ] ],
			'spaces are trimmed' => [ ' a , b b ,c', [ 'a', 'b b', 'c' ] ],
			'ampersand' => [ 'a&b,c', [ 'a&b', 'c' ] ],
			'escaped ampersand' => [ 'a&amp;b,c', [ 'a&b', 'c' ] ],
			'less-than sign' => [ 'a<b,c', [ 'a<b', 'c' ] ],
			'quotes' => [ 'a"b,it\'s', [ 'a"b', "it's" ] ],
			'numbers' => [ '1,2,3', [ '1', '2', '3' ] ],
			'empty item is dropped' => [ 'a,,b', [ 'a', 'b' ] ],
			'list and heading characters' => [ '*a,#b,:c', [ '*a', '#b', ':c' ] ],
			'line break after the delimiter' => [ "a,\nb", [ 'a', 'b' ] ],
			'non-ASCII' => [ 'ä,ö', [ 'ä', 'ö' ] ],
			'percent sign' => [ '50%,b', [ '50%', 'b' ] ],
			'single braces' => [ '{a},b', [ '{a}', 'b' ] ],
			'same value twice' => [ 'a,a', [ 'a', 'a' ] ],
			'template call' => [ '{{lc:X}},{{uc:y}}', [ 'x', 'Y' ] ],
			'magic word' => [ '{{PAGENAME}},b', [ 'PFValuesPage', 'b' ] ],
			'magic word with a parameter' => [ '{{ns:Help}},b', [ 'Help', 'b' ] ],
			'template with a parameter' => [ '{{PFValuesWithParam|x}},z', [ 'x-t', 'u', 'z' ] ],
			'template parameter with a default' => [ '{{{x|dflt}}},b', [ 'dflt', 'b' ] ],
			'array map' => [ '{{#arraymap:p;q;r|;|x|x-1|,}}', [ 'p-1', 'q-1', 'r-1' ] ],
			'at sign and semicolon' => [ 'a@b,c;d', [ 'a@b', 'c;d' ] ],
			'other delimiter' => [ 'a,b;c d,e|delimiter=;', [ 'a,b', 'c d,e' ] ],
		];
	}

	/**
	 * A link in 'values' gives the name of the page, as the value must be plain text (#279).
	 *
	 * @dataProvider provideLinks
	 * @param string $values The 'values' argument
	 * @param string[] $expected The values of the options
	 */
	public function testLinksGiveThePageName( string $values, array $expected ): void {
		foreach ( [ 'dropdown', 'radiobutton', 'listbox', 'checkboxes' ] as $inputType ) {
			$options = $this->optionValues( $inputType, "values=$values" );
			$this->assertSame( $expected, $options, $inputType );
		}
	}

	public static function provideLinks(): array {
		return [
			'links from a template' => [ '{{PFValuesLinks}}', [ 'A', 'b', 'C d' ] ],
			'link made by a template call' => [ '[[{{lc:X}}]],b', [ 'x', 'b' ] ],
			'link in a parser function' => [ '{{#arraymap:p;q|;|x|[[x]]|,}}', [ 'p', 'q' ] ],
			'link' => [ '[[A]],b', [ 'A', 'b' ] ],
			'only links' => [ '[[A]],[[B]]', [ 'A', 'B' ] ],
			'link with spaces' => [ '[[ A b ]],c', [ 'A b', 'c' ] ],
			'link to a category' => [ '[[:Category:A]],b', [ 'Category:A', 'b' ] ],
			'link with text, the pipe by a template' => [ '[[A{{!}}t]],b', [ 'A', 'b' ] ],
			'link in the middle of a value' => [ 'x [[A]] y,b', [ 'x A y', 'b' ] ],
			'link and other delimiter' => [ '[[A]];b,c|delimiter=;', [ 'A', 'b,c' ] ],
		];
	}

	/**
	 * The text is plain: wiki markup and bare URLs are not turned into HTML, which the input would
	 * show as text and which a saved value would hold.
	 *
	 * @dataProvider provideMarkup
	 * @param string $values The 'values' argument
	 * @param string[] $expected The values of the options
	 */
	public function testMarkupStaysText( string $values, array $expected ): void {
		foreach ( [ 'dropdown', 'checkboxes' ] as $inputType ) {
			$this->assertSame( $expected, $this->optionValues( $inputType, "values=$values" ), $inputType );
		}
	}

	public static function provideMarkup(): array {
		return [
			'italic' => [ "''it'',b", [ "''it''", 'b' ] ],
			'bold' => [ "'''bo''',b", [ "'''bo'''", 'b' ] ],
			'bare URL' => [ 'http://x.org/a,b', [ 'http://x.org/a', 'b' ] ],
			'external link' => [ '[http://x.org text],b', [ '[http://x.org text]', 'b' ] ],
			'horizontal rule' => [ '----,b', [ '----', 'b' ] ],
			'markup made by a template' => [ '{{PFValuesWithParam|\'\'\'x\'\'\'}},z', [ "'''x'''-t", 'u', 'z' ] ],
		];
	}

	/**
	 * The query of "#ask" has links in it, which must reach the parser function as they are written.
	 * With the links in the result, the page names are the values. Both ways are in one test, as the
	 * tables of Semantic MediaWiki are not reset between tests.
	 */
	public function testQueryOfParserFunctionIsNotChanged(): void {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'Semantic MediaWiki is not installed.' );
		}
		$this->insertPage( 'PFValuesMember', '[[Category:PFValuesCategory]]' );
		// The category membership is stored by a deferred update.
		\DeferredUpdates::doUpdates();
		foreach ( [ 'links in the result' => '', 'no links in the result' => '|link=none' ] as $name => $linkMode ) {
			$values = '{{#ask:[[Category:PFValuesCategory]]|format=plainlist|sep=,' . $linkMode . '}}';
			foreach ( [ 'dropdown', 'checkboxes' ] as $inputType ) {
				$this->assertSame(
					[ 'PFValuesMember' ], $this->optionValues( $inputType, "values=$values" ), "$name, $inputType"
				);
			}
		}
	}
}
