<?php

declare( strict_types=1 );

/**
 * @covers \PFListBoxInput
 * @group Database
 */
class PFListBoxInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
	}

	private function getHtml(
		string $curValue = '',
		array $possibleValues = [],
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		if ( $isMandatory ) {
			$extraArgs['mandatory'] = true;
		}
		$input = new PFListBoxInput(
			1,
			$curValue,
			'PFLBField01',
			$isDisabled,
			array_merge( [ 'possible_values' => $possibleValues ], $extraArgs )
		);
		return $input->getHtmlText();
	}

	public function testGetHtmlRendersSelectElementWithMultipleAttribute(): void {
		$html = $this->getHtml( '', [ 'PFLBOptA', 'PFLBOptB' ] );

		$this->assertStringContainsString( '<select ', $html );
		$this->assertStringContainsString( 'multiple=""', $html );
	}

	public function testGetHtmlNameEndsWithBrackets(): void {
		$html = $this->getHtml( '', [ 'PFLBOptA' ] );

		$this->assertStringContainsString( 'name="PFLBField01[]"', $html );
	}

	public function testGetHtmlRendersIsListHiddenField(): void {
		$html = $this->getHtml( '', [ 'PFLBOptA' ] );

		$this->assertStringContainsString( 'name="PFLBField01[is_list]"', $html );
	}

	public function testGetHtmlRendersOptionsFromPossibleValues(): void {
		$html = $this->getHtml( '', [ 'PFLBOptA', 'PFLBOptB' ] );

		$this->assertStringContainsString( '<option value="PFLBOptA">PFLBOptA</option>', $html );
		$this->assertStringContainsString( '<option value="PFLBOptB">PFLBOptB</option>', $html );
	}

	public function testGetHtmlSetsSelectedOnCurrentValue(): void {
		$html = $this->getHtml( 'PFLBOptB', [ 'PFLBOptA', 'PFLBOptB', 'PFLBOptC' ] );

		$this->assertStringContainsString( '<option value="PFLBOptB" selected="">PFLBOptB</option>', $html );
		$this->assertStringNotContainsString( '<option value="PFLBOptA" selected', $html );
		$this->assertStringNotContainsString( '<option value="PFLBOptC" selected', $html );
	}

	public function testGetHtmlMultipleValuesSelectedWithCommaDelimiter(): void {
		$html = $this->getHtml( 'PFLBOptA,PFLBOptC', [ 'PFLBOptA', 'PFLBOptB', 'PFLBOptC' ] );

		$this->assertStringContainsString( '<option value="PFLBOptA" selected="">PFLBOptA</option>', $html );
		$this->assertStringContainsString( '<option value="PFLBOptC" selected="">PFLBOptC</option>', $html );
		$this->assertStringNotContainsString( '<option value="PFLBOptB" selected', $html );
	}

	public function testGetHtmlCustomDelimiterSplitsValues(): void {
		$html = $this->getHtml(
			'PFLBOptA;PFLBOptC',
			[ 'PFLBOptA', 'PFLBOptB', 'PFLBOptC' ],
			false,
			false,
			[ 'delimiter' => ';' ]
		);

		$this->assertStringContainsString( '<option value="PFLBOptA" selected="">PFLBOptA</option>', $html );
		$this->assertStringContainsString( '<option value="PFLBOptC" selected="">PFLBOptC</option>', $html );
		$this->assertStringNotContainsString( '<option value="PFLBOptB" selected', $html );
	}

	public function testGetHtmlUsesValueLabelsForOptionDisplay(): void {
		$html = $this->getHtml(
			'',
			[ 'PFLBOptA', 'PFLBOptB' ],
			false,
			false,
			[ 'value_labels' => [ 'PFLBOptA' => 'PFLBLabelA', 'PFLBOptB' => 'PFLBLabelB' ] ]
		);

		$this->assertStringContainsString( '<option value="PFLBOptA">PFLBLabelA</option>', $html );
		$this->assertStringContainsString( '<option value="PFLBOptB">PFLBLabelB</option>', $html );
		$this->assertStringNotContainsString( '>PFLBOptA<', $html );
	}

	public function testGetHtmlSizeParameterAddsHtmlAttribute(): void {
		$html = $this->getHtml( '', [ 'PFLBOptA' ], false, false, [ 'size' => 25 ] );

		$this->assertStringContainsString( 'size="25"', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryClasses(): void {
		$html = $this->getHtml( '', [ 'PFLBOptA' ], true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
		$this->assertStringContainsString( 'mandatoryField', $html );
	}

	public function testGetHtmlDisabledAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', [ 'PFLBOptA' ], false, true );

		$this->assertStringContainsString( 'disabled=""', $html );
	}
}
