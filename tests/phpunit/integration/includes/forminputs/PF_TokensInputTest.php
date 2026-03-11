<?php

class PFTokensInputTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsEDSettings;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgPageFormsEDSettings = [];
	}

	public function testMappedPossibleValuesUseCanonicalOptionValues() {
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
		$matchingOptions = array_values( array_filter( $options, static function( $option ) {
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
		$canonicalOptions = array_values( array_filter( $options, static function( $option ) {
			return $option['value'] === 'Values From Category 3';
		} ) );

		$this->assertCount( 1, $canonicalOptions );
		$this->assertSame( 'Values From Category 3 (DisplayTitle)', $canonicalOptions[0]['label'] );
		$this->assertTrue( $canonicalOptions[0]['selected'] );
		$this->assertNotContains( 'Values From Category 3 (DisplayTitle)', array_column( $options, 'value' ) );
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
