<?php

use MediaWiki\Extension\PageForms\FormPrinterFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * Saving a form, opening the page in the form again and saving it again must give the same page
 * text, whatever the 'values' of the field contain. The test submits the values it expects the input
 * to offer (what the input does offer is checked in FormFieldValuesArgumentTest) and checks that the
 * form shows the stored value for the page. The inputs that fill their options from the
 * autocompletion settings (combobox, tokens) are also checked through the list they give to the
 * script, which is where a link placeholder in the values showed up (#279).
 *
 * @covers \MediaWiki\Extension\PageForms\FormFieldTagReader
 * @group Database
 */
class FormFieldValuesRoundTripTest extends MediaWikiIntegrationTestCase {

	private const LIST_TYPES = [ 'tokens', 'listbox', 'checkboxes' ];

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
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'PFRoundTripLinks' ), '[[A]],b,[[C d]]' );
	}

	/**
	 * @return array{0: string, 1: string} The form HTML and the page text the form makes
	 */
	private function formHtml( string $inputType, string $values, bool $submitted, ?string $pageText, ?array $post ) {
		global $wgPageFormsFormPrinter, $wgOut, $wgPageFormsAutocompleteValues;
		$title = Title::makeTitle( NS_MAIN, 'PFRoundTrip' );
		$wgOut->getContext()->setTitle( $title );
		RequestContext::getMain()->setTitle( $title );
		$wgPageFormsAutocompleteValues = [];
		$list = in_array( $inputType, self::LIST_TYPES ) ? '|list' : '';
		$formDef = "{{{for template|RtTpl}}}\n{{{field|f$list|input type=$inputType|values=$values}}}\n"
			. "{{{end template}}}\n{{{standard input|save}}}\n";
		[ $html, $newText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, $submitted, !$submitted, null, $pageText, 'PFRoundTrip', null, false, false, false, [],
			$this->getTestUser()->getUser(), $post === null ? null : new FauxRequest( $post, true )
		);
		return [ $html, $newText ];
	}

	/** What the input shows as the current value, as the list of the values that a browser submits. */
	private static function currentValues( string $inputType, string $html ): array {
		$patterns = [
			'checkboxes' => "/<input type='checkbox'[^>]*value='([^']*)' checked='checked'/",
			'radiobutton' => '/checked="" type="radio" value="([^"]*)"/',
			'combobox' => '/<option selected="">([^<]*)<\/option>/',
		];
		preg_match_all( $patterns[$inputType] ?? '/<option value="([^"]*)" selected=""/', $html, $m );
		return array_map( static fn ( $v ) => htmlspecialchars_decode( $v, ENT_QUOTES ), $m[1] );
	}

	/** The request a browser sends when the form is saved with these values chosen. */
	private static function post( string $inputType, array $chosen ): array {
		if ( !in_array( $inputType, self::LIST_TYPES ) ) {
			return [ 'RtTpl' => [ 'f' => $chosen[0] ] ];
		}
		return [ 'RtTpl' => [ 'f' => array_merge( array_values( $chosen ), [ 'is_list' => '1' ] ) ] ];
	}

	/**
	 * @dataProvider provideCases
	 * @param string $inputType
	 * @param string $values The 'values' argument
	 * @param string[] $options What the field offers
	 */
	public function testSavingAgainChangesNothing( string $inputType, string $values, array $options ): void {
		$isList = in_array( $inputType, self::LIST_TYPES );
		$chosen = $isList ? [ $options[0], $options[2] ] : [ $options[0] ];
		$expectedText = "{{RtTpl\n|f=" . implode( ', ', $chosen ) . "\n}}\n";

		// A new page: the form offers the options, and its autocompletion list is the plain names.
		[ , $text ] = $this->formHtml( $inputType, $values, true, null, self::post( $inputType, $chosen ) );
		$this->assertSame( $expectedText, $text, 'first save' );

		for ( $cycle = 1; $cycle <= 3; $cycle++ ) {
			[ $html, ] = $this->formHtml( $inputType, $values, false, $text, null );
			$this->assertSame( $chosen, self::currentValues( $inputType, $html ), "shown in cycle $cycle" );
			if ( in_array( $inputType, [ 'combobox', 'tokens' ] ) ) {
				global $wgPageFormsAutocompleteValues;
				$this->assertSame( [ $options ], array_values( $wgPageFormsAutocompleteValues ), 'autocompletion' );
			}
			[ , $text ] = $this->formHtml( $inputType, $values, true, null, self::post( $inputType, $chosen ) );
			$this->assertSame( $expectedText, $text, "saved in cycle $cycle" );
		}

		// The user picks another option: it is shown after saving and stays the same when saved again.
		$other = [ $options[1] ];
		$text = $this->formHtml( $inputType, $values, true, null, self::post( $inputType, $other ) )[1];
		[ $html, ] = $this->formHtml( $inputType, $values, false, $text, null );
		$this->assertSame( $other, self::currentValues( $inputType, $html ), 'another option' );
		$again = $this->formHtml( $inputType, $values, true, null, self::post( $inputType, $other ) )[1];
		$this->assertSame( $text, $again, 'another option, saved again' );
	}

	public static function provideCases(): array {
		$cases = [];
		$types = [ 'dropdown', 'radiobutton', 'combobox', 'tokens', 'listbox', 'checkboxes' ];
		$values = [
			'plain' => [ 'a,b,c d', [ 'a', 'b', 'c d' ] ],
			'links' => [ '[[A]],b,[[C d]]', [ 'A', 'b', 'C d' ] ],
			'link to a category' => [ '[[:Category:A]],b,c', [ 'Category:A', 'b', 'c' ] ],
			'links from a template' => [ '{{PFRoundTripLinks}}', [ 'A', 'b', 'C d' ] ],
		];
		foreach ( $types as $type ) {
			foreach ( $values as $name => [ $arg, $options ] ) {
				$cases["$type, $name"] = [ $type, $arg, $options ];
			}
		}
		return $cases;
	}
}
