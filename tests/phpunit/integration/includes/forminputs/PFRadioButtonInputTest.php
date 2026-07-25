<?php

declare( strict_types=1 );

/**
 * @covers \PFRadioButtonInput
 * @group Database
 */
class PFRadioButtonInputTest extends MediaWikiIntegrationTestCase {

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
		return PFRadioButtonInput::getHTML(
			$curValue,
			'TestField',
			$isMandatory,
			$isDisabled,
			array_merge( [ 'possible_values' => $possibleValues ], $extraArgs )
		);
	}

	public function testGetHtmlRendersOneRadioPerPossibleValue(): void {
		$html = $this->getHtml( '', [ 'PFRbOptA', 'PFRbOptB' ] );

		$this->assertStringContainsString( 'value="PFRbOptA"', $html );
		$this->assertStringContainsString( 'value="PFRbOptB"', $html );
	}

	public function testGetHtmlNonMandatoryPrependsNoneOption(): void {
		$html = $this->getHtml( '', [ 'PFRbOptA' ] );

		$this->assertStringContainsString( wfMessage( 'pf_formedit_none' )->text(), $html );
	}

	public function testGetHtmlMandatoryWithValueOmitsNoneOption(): void {
		$html = $this->getHtml( 'PFRbOptA', [ 'PFRbOptA', 'PFRbOptB' ], true );

		$this->assertStringNotContainsString( wfMessage( 'pf_formedit_none' )->text(), $html );
	}

	public function testGetHtmlChecksCurrentValueAndLeavesOtherUnchecked(): void {
		$html = $this->getHtml( 'PFRbOptB', [ 'PFRbOptA', 'PFRbOptB' ] );

		$this->assertRegex(
			'/checked="" type="radio" value="PFRbOptB"/',
			$html
		);
		$this->assertNotRegex(
			'/checked="" type="radio" value="PFRbOptA"/',
			$html
		);
	}

	public function testGetHtmlInvalidCurrentValueFallsBackToNone(): void {
		$html = $this->getHtml( 'PFRbUnknown', [ 'PFRbOptA', 'PFRbOptB' ] );

		$this->assertRegex(
			'/checked="" type="radio" value=""/',
			$html
		);
	}

	public function testGetHtmlWithValueLabelsRendersCustomLabelText(): void {
		$html = $this->getHtml(
			'',
			[ 'PFRbOptA', 'PFRbOptB' ],
			false,
			false,
			[ 'value_labels' => [ 'PFRbOptA' => 'Custom Label A', 'PFRbOptB' => 'Custom Label B' ] ]
		);

		$this->assertStringContainsString( '&nbsp;Custom Label A', $html );
		$this->assertStringContainsString( '&nbsp;Custom Label B', $html );
	}

	public function testGetHtmlWithValueLabelsAsJsonStringIsDecoded(): void {
		$html = $this->getHtml(
			'',
			[ 'PFRbOptA' ],
			false,
			false,
			[ 'value_labels' => json_encode( [ 'PFRbOptA' => 'Custom Label A' ] ) ]
		);

		$this->assertStringContainsString( '&nbsp;Custom Label A', $html );
	}

	public function testGetHtmlNoneOptionIgnoresValueLabelsOverride(): void {
		$html = $this->getHtml(
			'',
			[ 'PFRbOptA' ],
			false,
			false,
			[ 'value_labels' => [ '' => 'Should Not Be Used For None' ] ]
		);

		$this->assertStringContainsString( wfMessage( 'pf_formedit_none' )->text(), $html );
		$this->assertStringNotContainsString( 'Should Not Be Used For None', $html );
	}

	public function testGetHtmlDisabledAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', [ 'PFRbOptA' ], false, true );

		$this->assertStringContainsString( 'disabled=""', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', [ 'PFRbOptA' ], true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testGetHtmlMandatoryWithClassOverridePreservesSpanClassOrder(): void {
		$html = $this->getHtml( '', [ 'PFRbOptA' ], true, false, [ 'class' => 'customRbClass' ] );

		$this->assertStringContainsString(
			'class="radioButtonSpan customRbClass mandatoryFieldSpan"',
			$html
		);
	}

	public function testGetHtmlMandatoryWithClassOverrideAndShowOnSelectPreservesSpanClassOrder(): void {
		$html = $this->getHtml(
			'',
			[ 'PFRbOptA' ],
			true,
			false,
			[ 'class' => 'customRbClass', 'show on select' => [ 'PFRbOptA' => [ 'div#dummy' ] ] ]
		);

		$this->assertStringContainsString(
			'class="radioButtonSpan customRbClass mandatoryFieldSpan pfShowIfChecked"',
			$html
		);
	}

	public function testGetHtmlBooleanPropertyWithoutPossibleValuesRendersYesNo(): void {
		$html = PFRadioButtonInput::getHTML( '', 'TestField', false, false, [ 'property_type' => '_boo' ] );

		$this->assertStringContainsString( PFUtils::getWordForYesOrNo( true ), $html );
		$this->assertStringContainsString( PFUtils::getWordForYesOrNo( false ), $html );
	}

	/**
	 * Cross-version assertRegExp: uses assertMatchesRegularExpression (PHPUnit ≥ 9)
	 * when available, otherwise falls back to assertRegExp (PHPUnit 8, MW 1.39).
	 */
	private function assertRegex( string $pattern, string $string, string $message = '' ): void {
		if ( method_exists( $this, 'assertMatchesRegularExpression' ) ) {
			$this->assertMatchesRegularExpression( $pattern, $string, $message );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->assertRegExp( $pattern, $string, $message );
		}
	}

	/**
	 * Cross-version assertNotRegExp: uses assertDoesNotMatchRegularExpression (PHPUnit ≥ 9)
	 * when available, otherwise falls back to assertNotRegExp (PHPUnit 8, MW 1.39).
	 */
	private function assertNotRegex( string $pattern, string $string, string $message = '' ): void {
		if ( method_exists( $this, 'assertDoesNotMatchRegularExpression' ) ) {
			$this->assertDoesNotMatchRegularExpression( $pattern, $string, $message );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->assertNotRegExp( $pattern, $string, $message );
		}
	}
}
