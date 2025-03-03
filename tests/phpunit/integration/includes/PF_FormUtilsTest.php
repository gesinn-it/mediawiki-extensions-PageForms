<?php

use MediaWiki\MediaWikiServices;
use PHPUnit\Framework\TestCase;

/**
 * Test class for PFFormUtils functionality.
 *
 * This class contains unit tests for various utility methods used in the PageForms
 * extension. It tests methods that generate HTML for form elements, manage preload
 * text, handle headers, and calculate changed indices.
 *
 * @group PF
 */
class PFFormUtilsTest extends TestCase {

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
	 * @covers PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTML() {
		$mockTemplate = $this->createMock( PFTemplateInForm::class );

		// Mock methods for PFTemplateInForm
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'ExampleTemplate' );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [
			'field1' => 'value1',
			'field 2' => 'value2',
			3 => 'numeric_key_value',
			null => 'null_key_value'
		] );

		$output = PFFormUtils::unhandledFieldsHTML( $mockTemplate );

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
	 * Test for unhandledFieldsHTML method when the template is null.
	 *
	 * This test ensures that when the template is null, the method returns an empty string.
	 *
	 * @covers PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLWithNullTemplate() {
		$output = PFFormUtils::unhandledFieldsHTML( null );
		$this->assertSame( '', $output );
	}

	/**
	 * Test for minorEditInputHTML method.
	 *
	 * Verifies that the minor edit input HTML is generated correctly with the given parameters.
	 *
	 * @covers PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTML() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = PFFormUtils::minorEditInputHTML( false, false, false, "Test" );

		// Check if the checkbox input is present with expected attributes
		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'id=\'wpMinoredit\'', $output );
		$this->assertStringContainsString( 'Test', $output );
	}

	/**
	 * Test for watchInputHTML method.
	 *
	 * Verifies that the watch input HTML is generated correctly, including the 'checked' attribute.
	 *
	 * @covers PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTML() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = PFFormUtils::watchInputHTML( false, false, false, "Watch this" );

		// Check if the checkbox input is present with expected attributes
		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'id=\'wpWatchthis\'', $output );
		$this->assertStringContainsString( 'tabindex=\'2\'', $output );
		$this->assertStringContainsString( ' checked=\'checked\'', $output );
	}

	/**
	 * Test for saveAndContinueButtonHTML method.
	 *
	 * Verifies that the "Save and continue" button HTML is generated correctly with expected attributes.
	 *
	 * @covers PFFormUtils::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTML() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = PFFormUtils::saveAndContinueButtonHTML( false, "Save and continue editing" );

		// Check if the button contains the expected string and attributes
		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'pf-save_and_continue', $output );
		$this->assertStringContainsString( 'id=\'wpSaveAndContinue\'', $output );
		$this->assertStringContainsString( 'accesskey=\'', $output );
		$this->assertStringContainsString( 'title=\'', $output );
		$this->assertStringContainsString( 'Save and continue editing', $output );
	}

	/**
	 * Test for saveAndContinueButtonHTML method when the button is disabled.
	 *
	 * Verifies that the "Save and continue" button is rendered correctly with the disabled class and attributes.
	 *
	 * @covers PFFormUtils::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTMLWithDisabled() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = PFFormUtils::saveAndContinueButtonHTML( true, "Save and continue editing" );

		// Check if the button contains the disabled class and other expected attributes
		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'id=\'wpSaveAndContinue\'', $output );
		$this->assertStringContainsString( 'pf-save_and_continue disabled', $output );
		$this->assertStringContainsString( 'accesskey=\'', $output );
		$this->assertStringContainsString( 'title=\'', $output );
		$this->assertStringContainsString( 'Save and continue editing', $output );
	}

	/**
	 * Test for getPreloadedText method.
	 *
	 * Verifies the behavior of the getPreloadedText method when different titles are passed as arguments.
	 *
	 * @covers PFFormUtils::getPreloadedText
	 */
	public function testGetPreloadedText() {
		$preloadTitleMock = $this->createMock( Title::class );
		$userMock = $this->createMock( User::class );
		$servicesMock = $this->createMock( MediaWikiServices::class );
		$contextMock = $this->createMock( RequestContext::class );

		// Test empty input
		$output = PFFormUtils::getPreloadedText( '' );
		$this->assertSame( '', $output );

		// Test non-existent title
		$preloadTitleMock->method( 'newFromText' )->willReturn( null );
		$output = PFFormUtils::getPreloadedText( 'NonExistentTitle' );
		$this->assertSame( '', $output );

		// Test valid title
		$preloadTitleMock->method( 'newFromText' )->willReturn( $preloadTitleMock );
		$contextMock->method( 'getUser' )->willReturn( $userMock );
		$output = PFFormUtils::getPreloadedText( 'SomeTitle' );
		$this->assertSame( '', $output );
	}

	/**
	 * Test for headerHTML method with valid header levels.
	 *
	 * Verifies that the headerHTML method returns the correct HTML for valid header levels.
	 *
	 * @covers PFFormUtils::headerHTML
	 */
	public function testHeaderHTMLWithValidLevel() {
		$result = PFFormUtils::headerHTML( 'Sample Header', 2 );
		$this->assertSame( '<h2>Sample Header</h2>', $result );

		$result = PFFormUtils::headerHTML( 'Sample Header', 3 );
		$this->assertSame( '<h3>Sample Header</h3>', $result );

		$result = PFFormUtils::headerHTML( 'Sample Header', 6 );
		$this->assertSame( '<h6>Sample Header</h6>', $result );
	}

	/**
	 * Test for headerHTML method with invalid header levels.
	 *
	 * Verifies that the headerHTML method returns a fallback header when an invalid level is provided.
	 *
	 * @covers PFFormUtils::headerHTML
	 */
	public function testHeaderHTMLWithInvalidLevel() {
		$result = PFFormUtils::headerHTML( 'Sample Header', "test" );
		$this->assertSame( '<h2>Sample Header</h2>', $result );

		$result = PFFormUtils::headerHTML( 'Sample Header', 10 );
		$this->assertSame( '<h6>Sample Header</h6>', $result );
	}

	/**
	 * Test for getChangedIndex method when the item is deleted.
	 *
	 * Verifies that the index is calculated correctly when the item is deleted.
	 *
	 * @covers PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithDeletedItemLoc() {
		$result = PFFormUtils::getChangedIndex( 5, null, 3 );
		$this->assertSame( 6, $result );

		$result = PFFormUtils::getChangedIndex( 2, null, 3 );
		$this->assertSame( 2, $result );
	}

	/**
	 * Test for getChangedIndex method when a new item is added.
	 *
	 * Verifies that the index is calculated correctly when a new item is added.
	 *
	 * @covers PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithNewItemLoc() {
		$result = PFFormUtils::getChangedIndex( 5, 3, null );
		$this->assertSame( 4, $result );

		$result = PFFormUtils::getChangedIndex( 3, 3, null );
		$this->assertSame( -1, $result );

		$result = PFFormUtils::getChangedIndex( 2, 3, null );
		$this->assertSame( 2, $result );
	}
}
