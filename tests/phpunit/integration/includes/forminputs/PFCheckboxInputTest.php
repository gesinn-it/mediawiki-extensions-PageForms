<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFCheckboxInput
 * @group Database
 */
class PFCheckboxInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
	}

	private function getHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		return PFCheckboxInput::getHTML(
			$curValue,
			'PFCbxField01',
			$isMandatory,
			$isDisabled,
			$extraArgs
		);
	}

	public function testGetHtmlRendersCheckboxInputWidget(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( "<input type='checkbox'", $html );
	}

	public function testGetHtmlRendersIsCheckboxHiddenField(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'name="PFCbxField01[is_checkbox]"', $html );
	}

	public function testGetHtmlNameHasValueSuffix(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( "name='PFCbxField01[value]'", $html );
	}

	public function testGetHtmlUncheckedByDefaultWithEmptyValue(): void {
		$html = $this->getHtml( '' );

		$this->assertStringNotContainsString( "checked=", $html );
	}

	public function testGetHtmlCheckedForTrueString(): void {
		$html = $this->getHtml( 'true' );

		$this->assertStringContainsString( "checked=", $html );
	}

	public function testGetHtmlCheckedForOneString(): void {
		$html = $this->getHtml( '1' );

		$this->assertStringContainsString( "checked=", $html );
	}

	public function testGetHtmlCheckedForYesString(): void {
		$html = $this->getHtml( 'yes' );

		$this->assertStringContainsString( "checked=", $html );
	}

	public function testGetHtmlCheckedForArrayWithValueOn(): void {
		$html = PFCheckboxInput::getHTML(
			[ 'value' => 'on' ],
			'PFCbxField01',
			false,
			false,
			[]
		);

		$this->assertStringContainsString( "checked=", $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testGetHtmlLabelWrapsInLabelElement(): void {
		$html = $this->getHtml( '', false, false, [ 'label' => 'PFCbxMyLabel01' ] );

		$this->assertStringContainsString( '<label for="input_1"', $html );
		$this->assertStringContainsString( 'PFCbxMyLabel01</label>', $html );
	}

	public function testGetHtmlWithoutLabelOmitsLabelElement(): void {
		$html = $this->getHtml();

		$this->assertStringNotContainsString( '<label', $html );
	}

	public function testGetHtmlDisabledAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'disabled', $html );
	}
}
