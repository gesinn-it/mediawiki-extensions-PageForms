<?php

declare( strict_types=1 );

/**
 * @covers \PFDropdownInput
 * @group Database
 */
class PFDropdownInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
	}

	private function getHtml(
		string $curValue,
		array $possibleValues,
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		return PFDropdownInput::getHTML(
			$curValue,
			'TestField',
			$isMandatory,
			$isDisabled,
			array_merge( [ 'possible_values' => $possibleValues ], $extraArgs )
		);
	}

	public function testGetHtmlRendersSelectElement(): void {
		$html = $this->getHtml( '', [ 'PFDDOptA', 'PFDDOptB' ] );

		$this->assertStringContainsString( '<select ', $html );
		$this->assertStringContainsString( 'name="TestField"', $html );
	}

	public function testGetHtmlRendersBlankOptionForNonMandatory(): void {
		$html = $this->getHtml( '', [ 'PFDDOptA' ] );

		$this->assertStringContainsString( '<option value=""></option>', $html );
	}

	public function testGetHtmlOmitsBlankOptionForMandatoryWithValue(): void {
		$html = $this->getHtml( 'PFDDOptA', [ 'PFDDOptA', 'PFDDOptB' ], true );

		$this->assertStringNotContainsString( '<option value=""></option>', $html );
	}

	public function testGetHtmlKeepsBlankOptionForMandatoryWithoutValue(): void {
		$html = $this->getHtml( '', [ 'PFDDOptA', 'PFDDOptB' ], true );

		$this->assertStringContainsString( '<option value=""></option>', $html );
	}

	public function testGetHtmlMarksCurrentValueAsSelected(): void {
		$html = $this->getHtml( 'PFDDOptB', [ 'PFDDOptA', 'PFDDOptB', 'PFDDOptC' ] );

		$this->assertStringContainsString( '<option value="PFDDOptB" selected="">PFDDOptB</option>', $html );
		$this->assertStringNotContainsString( '<option value="PFDDOptA" selected', $html );
		$this->assertStringNotContainsString( '<option value="PFDDOptC" selected', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', [ 'PFDDOptA' ], true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
		$this->assertStringContainsString( 'mandatoryField', $html );
	}

	public function testGetHtmlNonMandatoryUsesCreateboxInputClass(): void {
		$html = $this->getHtml( '', [ 'PFDDOptA' ] );

		$this->assertStringContainsString( 'createboxInput', $html );
		$this->assertStringNotContainsString( 'mandatoryFieldSpan', $html );
	}

	/**
	 * Regression coverage for issue #186: when the current value falls outside
	 * a truncated 'values from ...' fetch window (so PossibleValueList doesn't
	 * contain it), the dropdown must not silently show the blank/first option
	 * instead - an extra selected option is appended for it (mirroring
	 * PFComboBoxInput/PFTokensInput's #185 fallback).
	 */
	public function testGetHtmlUnmatchedCurrentValueRendersExtraSelectedOption(): void {
		$html = $this->getHtml( 'PFDDUnknown', [ 'PFDDOptA', 'PFDDOptB' ] );

		$this->assertStringContainsString(
			'<option value="PFDDUnknown" selected="">PFDDUnknown</option>',
			$html
		);
	}

	public function testGetHtmlUnmatchedPageTypeCurrentValueUsesDisplayTitle(): void {
		$this->overrideConfigValue( 'RestrictDisplayTitle', false );

		$title = Title::makeTitle( NS_CATEGORY, 'PFDropdownDisplayTitleFallback' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->editPage( $page, '{{DISPLAYTITLE:Fallback Display Title}}' );
		DeferredUpdates::doUpdates();

		$curValue = 'Category:PFDropdownDisplayTitleFallback';
		$html = $this->getHtml( $curValue, [ 'PFDDOptA' ] );

		$this->assertStringContainsString(
			'<option value="' . $curValue . '" selected="">Fallback Display Title</option>',
			$html
		);
	}

	public function testGetHtmlWithValueLabelsRendersCustomLabelText(): void {
		$html = $this->getHtml(
			'',
			[ 'PFDDOptA', 'PFDDOptB' ],
			false,
			false,
			[ 'value_labels' => [ 'PFDDOptA' => 'Custom Label A', 'PFDDOptB' => 'Custom Label B' ] ]
		);

		$this->assertStringContainsString( '<option value="PFDDOptA">Custom Label A</option>', $html );
		$this->assertStringContainsString( '<option value="PFDDOptB">Custom Label B</option>', $html );
	}

	public function testGetHtmlWithValueLabelsAsJsonStringIsDecoded(): void {
		$html = $this->getHtml(
			'',
			[ 'PFDDOptA' ],
			false,
			false,
			[ 'value_labels' => json_encode( [ 'PFDDOptA' => 'Custom Label A' ] ) ]
		);

		$this->assertStringContainsString( '<option value="PFDDOptA">Custom Label A</option>', $html );
	}
}
