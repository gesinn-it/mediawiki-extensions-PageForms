<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFTextAreaWithAutocompleteInput
 * @group Database
 */
class PFTextAreaWithAutocompleteInputTest extends MediaWikiIntegrationTestCase {

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

	public function testGetNameReturnsTextareaWithAutocomplete(): void {
		$this->assertSame( 'textarea with autocomplete', PFTextAreaWithAutocompleteInput::getName() );
	}

	public function testGetHTMLDelegatesToComboBoxAliasForScalarField(): void {
		// Constructing an instance first selects the alias (PFComboBoxInput), mirroring
		// how PFTextWithAutocompleteInput::__construct() sets it via is_list.
		new PFTextAreaWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs() );

		$html = PFTextAreaWithAutocompleteInput::getHTML( '', 'TestField', false, false, self::baseArgs() );

		$this->assertStringContainsString( 'pfComboBox', $html );
	}

	public function testGetHTMLDelegatesToTokensAliasForListField(): void {
		new PFTextAreaWithAutocompleteInput( 1, '', 'TestField', false, self::baseArgs( [ 'is_list' => true ] ) );

		$html = PFTextAreaWithAutocompleteInput::getHTML(
			'', 'TestField', false, false, self::baseArgs( [ 'is_list' => true ] )
		);

		$this->assertStringContainsString( 'pfTokens', $html );
	}

	public function testGetHtmlTextUsesInstanceState(): void {
		$input = new PFTextAreaWithAutocompleteInput( 1, 'MyValue', 'TestField', false, self::baseArgs() );

		$html = $input->getHtmlText();

		$this->assertStringContainsString( 'name="TestField"', $html );
	}
}
