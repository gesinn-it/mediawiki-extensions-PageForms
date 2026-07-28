<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFCheckboxesInput
 * @group Database
 */
class PFCheckboxesInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsCheckboxesSelectAllMinimum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgPageFormsCheckboxesSelectAllMinimum = 10;
	}

	private function getHtml(
		string $curValue,
		array $possibleValues,
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		return PFCheckboxesInput::getHTML(
			$curValue,
			'TestField',
			$isMandatory,
			$isDisabled,
			array_merge( [ 'possible_values' => $possibleValues ], $extraArgs )
		);
	}

	public function testGetHtmlRendersOuterSpanAndIsListHidden(): void {
		$html = $this->getHtml( '', [ 'PFCbOptA', 'PFCbOptB' ] );

		$this->assertStringContainsString( 'class="checkboxesSpan"', $html );
		$this->assertStringContainsString( 'name="TestField[is_list]"', $html );
	}

	public function testGetHtmlRendersCheckboxValuesAndNamesWithNumericIndex(): void {
		$html = $this->getHtml( '', [ 'PFCbOptA', 'PFCbOptB' ] );

		$this->assertStringContainsString( "value='PFCbOptA'", $html );
		$this->assertStringContainsString( "value='PFCbOptB'", $html );
		$this->assertStringContainsString( "name='TestField[0]'", $html );
		$this->assertStringContainsString( "name='TestField[1]'", $html );
	}

	public function testGetHtmlRendersLabelTextForEachOption(): void {
		$html = $this->getHtml( '', [ 'PFCbOptA', 'PFCbOptB' ] );

		$this->assertStringContainsString( 'class="checkboxLabel"', $html );
		$this->assertStringContainsString( '&nbsp;PFCbOptA', $html );
		$this->assertStringContainsString( '&nbsp;PFCbOptB', $html );
	}

	public function testGetHtmlChecksCurrentValueAndLeavesOtherUnchecked(): void {
		$html = $this->getHtml( 'PFCbOptA', [ 'PFCbOptA', 'PFCbOptB' ] );

		$this->assertStringContainsString( "value='PFCbOptA' checked='checked'", $html );
		$this->assertStringNotContainsString( "value='PFCbOptB' checked='checked'", $html );
	}

	public function testGetHtmlWithCustomDelimiterChecksMultipleValues(): void {
		$html = $this->getHtml(
			'PFCbOptA;PFCbOptC',
			[ 'PFCbOptA', 'PFCbOptB', 'PFCbOptC' ],
			false,
			false,
			[ 'delimiter' => ';' ]
		);

		$this->assertStringContainsString( "value='PFCbOptA' checked='checked'", $html );
		$this->assertStringContainsString( "value='PFCbOptC' checked='checked'", $html );
		$this->assertStringNotContainsString( "value='PFCbOptB' checked='checked'", $html );
	}

	public function testGetHtmlWithValueLabelsRendersCustomLabelText(): void {
		$html = $this->getHtml(
			'',
			[ 'PFCbOptA', 'PFCbOptB' ],
			false,
			false,
			[ 'value_labels' => [ 'PFCbOptA' => 'Custom Label A', 'PFCbOptB' => 'Custom Label B' ] ]
		);

		$this->assertStringContainsString( '&nbsp;Custom Label A', $html );
		$this->assertStringContainsString( '&nbsp;Custom Label B', $html );
		$this->assertStringContainsString( "value='PFCbOptA'", $html );
		$this->assertStringNotContainsString( '&nbsp;PFCbOptA', $html );
	}

	public function testGetHtmlWithValueLabelsAsJsonStringIsDecoded(): void {
		$html = $this->getHtml(
			'',
			[ 'PFCbOptA' ],
			false,
			false,
			[ 'value_labels' => json_encode( [ 'PFCbOptA' => 'Custom Label A' ] ) ]
		);

		$this->assertStringContainsString( '&nbsp;Custom Label A', $html );
	}

	/**
	 * Regression coverage for issue #186: when a checked value falls outside a
	 * truncated 'values from ...' fetch window (so PossibleValueList doesn't
	 * contain it), it must not be silently dropped from the rendered markup -
	 * an extra checked checkbox is rendered for it (mirroring PFTokensInput's
	 * #185 fallback), using resolveMissingLabel() for its label.
	 */
	public function testGetHtmlUnmatchedCurrentValueRendersExtraCheckedCheckbox(): void {
		$html = $this->getHtml( 'PFCbOptA,PFCbUnknown', [ 'PFCbOptA', 'PFCbOptB' ] );

		$this->assertStringContainsString( "value='PFCbUnknown' checked='checked'", $html );
		$this->assertStringContainsString( '&nbsp;PFCbUnknown', $html );
	}

	public function testGetHtmlUnmatchedPageTypeCurrentValueUsesDisplayTitle(): void {
		$this->overrideConfigValue( 'RestrictDisplayTitle', false );

		$title = Title::makeTitle( NS_CATEGORY, 'PFCheckboxesDisplayTitleFallback' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->editPage( $page, '{{DISPLAYTITLE:Fallback Display Title}}' );
		DeferredUpdates::doUpdates();

		$curValue = 'Category:PFCheckboxesDisplayTitleFallback';
		$html = $this->getHtml( $curValue, [ 'PFCbOptA' ] );

		$this->assertStringContainsString( "value='" . $curValue . "' checked='checked'", $html );
		$this->assertStringContainsString( '&nbsp;Fallback Display Title', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', [ 'PFCbOptA' ], true );

		$this->assertStringContainsString( 'class="checkboxesSpan mandatoryFieldSpan"', $html );
	}

	public function testGetHtmlShowSelectAllAddsSelectAllClass(): void {
		$html = $this->getHtml( '', [ 'PFCbOptA', 'PFCbOptB' ], false, false, [ 'show select all' => true ] );

		$this->assertStringContainsString( 'select-all', $html );
	}

	public function testGetHtmlAutoSelectAllWhenCountReachesMinimum(): void {
		global $wgPageFormsCheckboxesSelectAllMinimum;
		$wgPageFormsCheckboxesSelectAllMinimum = 3;

		$html = $this->getHtml( '', [ 'PFCbOptA', 'PFCbOptB', 'PFCbOptC' ] );

		$this->assertStringContainsString( 'select-all', $html );
	}

	public function testGetHtmlHideSelectAllSuppressesSelectAllEvenAtMinimum(): void {
		global $wgPageFormsCheckboxesSelectAllMinimum;
		$wgPageFormsCheckboxesSelectAllMinimum = 2;

		$html = $this->getHtml(
			'',
			[ 'PFCbOptA', 'PFCbOptB', 'PFCbOptC' ],
			false,
			false,
			[ 'hide select all' => true ]
		);

		$this->assertStringNotContainsString( 'select-all', $html );
	}
}
