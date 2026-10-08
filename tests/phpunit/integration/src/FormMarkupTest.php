<?php

use MediaWiki\Extension\PageForms\FormMarkup;
use MediaWiki\Extension\PageForms\TemplateInForm;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FormMarkup.
 *
 * @group PF
 */
class FormMarkupTest extends TestCase {

	/**
	 * Setup method for each test.
	 *
	 * Initializes the environment for each test, including setting the OOUI theme.
	 */
	protected function setUp(): void {
		parent::setUp();
		OOUI\Theme::setSingleton( new OOUI\WikimediaUITheme() );
	}

	/**
	 * Test for unhandledFieldsHTML method.
	 *
	 * This test verifies that unhandled form fields are correctly processed into
	 * HTML input elements with the expected attributes.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormMarkup::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTML() {
		$mockTemplate = $this->createMock( TemplateInForm::class );

		// Mock methods for TemplateInForm
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'ExampleTemplate' );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [
			'field1' => 'value1',
			'field 2' => 'value2',
			3 => 'numeric_key_value',
			null => 'null_key_value'
		] );

		$output = FormMarkup::unhandledFieldsHTML( $mockTemplate );

		// Load the HTML output and find input elements
		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $output );
		$inputs = $doc->getElementsByTagName( 'input' );

		// Expected values for input name and value
		$expectedValues = [
			'_unhandled_ExampleTemplate_field1' => 'value1',
			'_unhandled_ExampleTemplate_field_2' => 'value2'
		];

		// Verify input elements
		foreach ( $inputs as $input ) {
			$name = $input->getAttribute( 'name' );
			$value = $input->getAttribute( 'value' );
			if ( isset( $expectedValues[ $name ] ) ) {
				$this->assertSame( $expectedValues[ $name ], $value );
				unset( $expectedValues[ $name ] );
			}
		}
	}

	/**
	 * The unhandled parameters of a multiple-instance template are named after the instance
	 * ("[num]" is replaced by the instance number), so that each instance keeps its own.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormMarkup::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLForMultipleInstanceTemplateBelongToTheInstance() {
		$mockTemplate = $this->createMock( TemplateInForm::class );
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'Example Template' );
		$mockTemplate->method( 'allowsMultiple' )->willReturn( true );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [ 'field 1' => 'value1', 2 => 'positional' ] );

		$output = FormMarkup::unhandledFieldsHTML( $mockTemplate );

		$this->assertStringContainsString( 'name="Example_Template[num][_unhandled][field+1]"', $output );
		$this->assertStringContainsString( 'value="value1"', $output );
		$this->assertStringNotContainsString( 'positional', $output );
		$this->assertStringNotContainsString( '_unhandled_Example', $output );
	}

	/**
	 * Test for unhandledFieldsHTML method when the template is null.
	 *
	 * This test ensures that when the template is null, the method returns an empty string.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormMarkup::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLWithNullTemplate() {
		$output = FormMarkup::unhandledFieldsHTML( null );
		$this->assertSame( '', $output );
	}

	/**
	 * Test for headerHTML method with valid header levels.
	 *
	 * Verifies that the headerHTML method returns the correct HTML for valid header levels.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormMarkup::headerHTML
	 */
	public function testHeaderHTMLWithValidLevel() {
		$result = FormMarkup::headerHTML( 'Sample Header', 2 );
		$this->assertSame( '<h2>Sample Header</h2>', $result );

		$result = FormMarkup::headerHTML( 'Sample Header', 3 );
		$this->assertSame( '<h3>Sample Header</h3>', $result );

		$result = FormMarkup::headerHTML( 'Sample Header', 6 );
		$this->assertSame( '<h6>Sample Header</h6>', $result );
	}

	/**
	 * Test for headerHTML method with invalid header levels.
	 *
	 * Verifies that the headerHTML method returns a fallback header when an invalid level is provided.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormMarkup::headerHTML
	 */
	public function testHeaderHTMLWithInvalidLevel() {
		$result = FormMarkup::headerHTML( 'Sample Header', "test" );
		$this->assertSame( '<h2>Sample Header</h2>', $result );

		$result = FormMarkup::headerHTML( 'Sample Header', 10 );
		$this->assertSame( '<h6>Sample Header</h6>', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormMarkup::setShowOnSelect
	 */
	public function testSetShowOnSelectAppendsToExistingInputID() {
		global $wgPageFormsShowOnSelect;
		$wgPageFormsShowOnSelect = [];

		FormMarkup::setShowOnSelect( [ 'div1' => [ 'val1' ] ], 'pf_test_show_on_select_input01' );
		FormMarkup::setShowOnSelect( [ 'div2' => [ 'val2' ] ], 'pf_test_show_on_select_input01' );

		$this->assertCount( 2, $wgPageFormsShowOnSelect['pf_test_show_on_select_input01'] );
	}
}
