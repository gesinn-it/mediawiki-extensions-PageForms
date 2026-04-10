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
}
