<?php

use MediaWiki\Extension\PageForms\FormPrinterFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * What the 'values' argument of a field gives as the options of the input.
 *
 * The cases without a link pin what 'values' did before links in it were handled (#279), so that a
 * change in how the argument is read cannot change them unnoticed. They only list what is
 * correct; inputs whose result is known to be wrong (wiki markup such as ''italic'', a bare URL
 * or "----", which show as escaped HTML) are left out on purpose.
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
			'link' => [ '[[A]],b', [ 'A', 'b' ] ],
			'only links' => [ '[[A]],[[B]]', [ 'A', 'B' ] ],
			'link with spaces' => [ '[[ A b ]],c', [ 'A b', 'c' ] ],
			'link to a category' => [ '[[:Category:A]],b', [ 'Category:A', 'b' ] ],
			'link with text, the pipe by a template' => [ '[[A{{!}}t]],b', [ 'A', 'b' ] ],
			'link in the middle of a value' => [ 'x [[A]] y,b', [ 'x A y', 'b' ] ],
			'link and other delimiter' => [ '[[A]];b,c|delimiter=;', [ 'A', 'b,c' ] ],
		];
	}
}
