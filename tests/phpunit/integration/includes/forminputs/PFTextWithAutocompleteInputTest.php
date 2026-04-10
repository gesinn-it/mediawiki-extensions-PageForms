<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFTextWithAutocompleteInput
 * @group Database
 */
class PFTextWithAutocompleteInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
	}

	private static function baseArgs( array $extra = [] ): array {
		return array_merge( [ 'possible_values' => [] ], $extra );
	}

	public function testScalarFieldRendersComboBoxWidget(): void {
		$input = new PFTextWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs() );
		$html = $input->getHtmlText();

		$this->assertStringContainsString( 'pfComboBox', $html );
		$this->assertStringNotContainsString( 'pfTokens', $html );
	}

	public function testListFieldRendersTokensWidget(): void {
		$input = new PFTextWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs( [ 'is_list' => true ] ) );
		$html = $input->getHtmlText();

		$this->assertStringContainsString( 'pfTokens', $html );
		$this->assertStringNotContainsString( 'pfComboBox', $html );
	}

	public function testScalarFieldUsesScalarInputName(): void {
		$input = new PFTextWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs() );
		$html = $input->getHtmlText();

		// combobox: name="TestField" (no brackets)
		$this->assertStringContainsString( 'name="TestField"', $html );
		$this->assertStringNotContainsString( 'name="TestField[]"', $html );
	}

	public function testListFieldUsesArrayInputName(): void {
		$input = new PFTextWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs( [ 'is_list' => true ] ) );
		$html = $input->getHtmlText();

		// tokens: name="TestField[]"
		$this->assertStringContainsString( 'name="TestField[]"', $html );
	}

	public function testGetResourceModuleNamesReturnsComboboxModuleForScalar(): void {
		$input = new PFTextWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs() );

		$this->assertContains( 'ext.pageforms.ooui.combobox', $input->getResourceModuleNames() );
	}

	public function testGetResourceModuleNamesReturnsEmptyArrayForList(): void {
		$input = new PFTextWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs( [ 'is_list' => true ] ) );

		$this->assertSame( [], $input->getResourceModuleNames() );
	}
}
