<?php

declare( strict_types=1 );

/**
 * @covers \PFTokensInput
 * @group Database
 */
class PFTokensInputTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsEDSettings;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgPageFormsEDSettings = [];
	}

	private function getHtml(
		string $curValue,
		array $possibleValues = [],
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		return PFTokensInput::getHTML(
			$curValue,
			'TestField',
			$isMandatory,
			$isDisabled,
			array_merge( [ 'possible_values' => $possibleValues ], $extraArgs )
		);
	}

	public function testGetHtmlRendersMultiSelectWithArrayInputName(): void {
		$html = $this->getHtml( '' );

		$this->assertStringContainsString( 'name="TestField[]"', $html );
		$this->assertStringContainsString( 'multiple=""', $html );
		$this->assertStringContainsString( 'class="pfTokens createboxInput"', $html );
	}

	public function testGetHtmlRendersIsListHiddenInput(): void {
		$html = $this->getHtml( '' );

		$this->assertStringContainsString( 'name="TestField[is_list]"', $html );
	}

	public function testGetHtmlMarksStoredValuesAsSelected(): void {
		$html = $this->getHtml(
			'PFTokAlpha,PFTokGamma',
			[ 'PFTokAlpha', 'PFTokBeta', 'PFTokGamma' ]
		);

		$this->assertStringContainsString( '<option value="PFTokAlpha" selected="">PFTokAlpha</option>', $html );
		$this->assertStringContainsString( '<option value="PFTokGamma" selected="">PFTokGamma</option>', $html );
		$this->assertStringNotContainsString( '<option value="PFTokBeta" selected', $html );
	}

	public function testGetHtmlAppendsUnknownCurrentValueAsSelectedOption(): void {
		// A value stored in the page that is not in possible_values must
		// still appear as a selected option so re-editing does not lose it.
		$html = $this->getHtml(
			'PFTokKnown,PFTokUnknown',
			[ 'PFTokKnown' ]
		);

		$this->assertStringContainsString( '<option value="PFTokUnknown" selected="">PFTokUnknown</option>', $html );
	}

	/**
	 * Regression coverage for issue #185: when the current value is a
	 * page-type value that falls outside a truncated 'values from ...' fetch
	 * (so it is not in possible_values), the fallback option's label must be
	 * the page's DisplayTitle rather than the raw namespace-prefixed value.
	 */
	public function testGetHtmlUsesDisplayTitleForUnknownPageTypeCurrentValue(): void {
		$this->overrideConfigValue( 'RestrictDisplayTitle', false );

		$title = Title::makeTitle( NS_CATEGORY, 'PFTokDisplayTitleFallback' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->editPage( $page, '{{DISPLAYTITLE:Fallback Display Title}}' );
		DeferredUpdates::doUpdates();

		$html = $this->getHtml(
			'PFTokKnown,Category:PFTokDisplayTitleFallback',
			[ 'PFTokKnown' ]
		);

		$options = $this->extractOptions( $html );
		$option = $this->findOptionByValue( $options, 'Category:PFTokDisplayTitleFallback' );

		$this->assertNotNull( $option );
		$this->assertSame( 'Fallback Display Title', $option['label'] );
		$this->assertTrue( $option['selected'] );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpan(): void {
		$html = $this->getHtml( '', [], true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
		$this->assertStringContainsString( 'mandatoryField', $html );
	}

	public function testGetHtmlCustomDelimiterSplitsCurrentValue(): void {
		$html = $this->getHtml(
			'PFTokAlpha;PFTokBeta',
			[ 'PFTokAlpha', 'PFTokBeta' ],
			false,
			false,
			[ 'delimiter' => ';' ]
		);

		$this->assertStringContainsString( '<option value="PFTokAlpha" selected="">PFTokAlpha</option>', $html );
		$this->assertStringContainsString( '<option value="PFTokBeta" selected="">PFTokBeta</option>', $html );
	}

	public function testMappedPossibleValuesUseCanonicalOptionValues(): void {
		$html = PFTokensInput::getHTML(
			'',
			'TestTemplate[Tokens]',
			false,
			false,
			[
				'values from external data' => null,
				'possible_values' => [
					'Values From Category 1' => 'Values From Category 1',
					'Values From Category 2' => 'Values From Category 2',
					'Values From Category 3' => 'Values From Category 3 (DisplayTitle)'
				]
			]
		);

		$options = $this->extractOptions( $html );
		$optionValues = array_column( $options, 'value' );

		$this->assertContains( 'Values From Category 3', $optionValues );
		$this->assertNotContains( 'Values From Category 3 (DisplayTitle)', $optionValues );
	}

	public function testCurrentCanonicalValueDoesNotCreateDuplicateOption() {
		$html = PFTokensInput::getHTML(
			'Values From Category 3',
			'TestTemplate[Tokens]',
			false,
			false,
			[
				'values from external data' => null,
				'possible_values' => [
					'Values From Category 1' => 'Values From Category 1',
					'Values From Category 2' => 'Values From Category 2',
					'Values From Category 3' => 'Values From Category 3 (DisplayTitle)'
				]
			]
		);

		$options = $this->extractOptions( $html );
		$matchingOptions = array_values( array_filter( $options, static function ( $option ) {
			return $option['value'] === 'Values From Category 3';
		} ) );

		$this->assertCount( 1, $matchingOptions );
		$this->assertSame( 'Values From Category 3 (DisplayTitle)', $matchingOptions[0]['label'] );
		$this->assertTrue( $matchingOptions[0]['selected'] );
	}

	public function testCurrentDisplayLabelValueMapsBackToCanonicalOption() {
		$html = PFTokensInput::getHTML(
			'Values From Category 3 (DisplayTitle)',
			'TestTemplate[Tokens]',
			false,
			false,
			[
				'values from external data' => null,
				'possible_values' => [
					'Values From Category 1' => 'Values From Category 1',
					'Values From Category 2' => 'Values From Category 2',
					'Values From Category 3' => 'Values From Category 3 (DisplayTitle)'
				]
			]
		);

		$options = $this->extractOptions( $html );
		$canonicalOptions = array_values( array_filter( $options, static function ( $option ) {
			return $option['value'] === 'Values From Category 3';
		} ) );

		$this->assertCount( 1, $canonicalOptions );
		$this->assertSame( 'Values From Category 3 (DisplayTitle)', $canonicalOptions[0]['label'] );
		$this->assertTrue( $canonicalOptions[0]['selected'] );
		$this->assertNotContains( 'Values From Category 3 (DisplayTitle)', array_column( $options, 'value' ) );
	}

	public function testValueLabelsOverrideKeyedByCanonicalValue(): void {
		$html = $this->getHtml(
			'',
			[ 'PFTokAlpha', 'PFTokBeta' ],
			false,
			false,
			[ 'value_labels' => [ 'PFTokAlpha' => 'Custom Label Alpha' ] ]
		);

		$options = $this->extractOptions( $html );
		$alphaOption = $this->findOptionByValue( $options, 'PFTokAlpha' );

		$this->assertNotNull( $alphaOption );
		$this->assertSame( 'Custom Label Alpha', $alphaOption['label'] );
	}

	/**
	 * Regression coverage for the value_labels fallback lookup keyed by the
	 * possible value's *original* (pre-override) label, exercised only when
	 * possible_values is a canonicalValue => displayLabel map. This is
	 * distinct from the by-canonical-value lookup covered above.
	 */
	public function testValueLabelsOverrideKeyedByOriginalLabelFallsBackWhenNotKeyedByValue(): void {
		$html = PFTokensInput::getHTML(
			'',
			'TestTemplate[Tokens]',
			false,
			false,
			[
				'values from external data' => null,
				'possible_values' => [
					'Values From Category 3' => 'Values From Category 3 (DisplayTitle)'
				],
				'value_labels' => [ 'Values From Category 3 (DisplayTitle)' => 'Custom Override Label' ]
			]
		);

		$options = $this->extractOptions( $html );
		$option = $this->findOptionByValue( $options, 'Values From Category 3' );

		$this->assertNotNull( $option );
		$this->assertSame( 'Custom Override Label', $option['label'] );
	}

	public function testValueLabelsAsJsonStringIsDecoded(): void {
		$html = $this->getHtml(
			'',
			[ 'PFTokAlpha' ],
			false,
			false,
			[ 'value_labels' => json_encode( [ 'PFTokAlpha' => 'Custom Label Alpha' ] ) ]
		);

		$options = $this->extractOptions( $html );
		$alphaOption = $this->findOptionByValue( $options, 'PFTokAlpha' );

		$this->assertNotNull( $alphaOption );
		$this->assertSame( 'Custom Label Alpha', $alphaOption['label'] );
	}

	private function findOptionByValue( array $options, string $value ): ?array {
		foreach ( $options as $option ) {
			if ( $option['value'] === $value ) {
				return $option;
			}
		}
		return null;
	}

	private function extractOptions( string $html ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<!DOCTYPE html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();

		$options = [];
		foreach ( $dom->getElementsByTagName( 'option' ) as $option ) {
			$options[] = [
				'value' => $option->getAttribute( 'value' ),
				'label' => $option->textContent,
				'selected' => $option->hasAttribute( 'selected' )
			];
		}

		return $options;
	}
}
