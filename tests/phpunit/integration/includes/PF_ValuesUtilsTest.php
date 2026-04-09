<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers \PFValuesUtils
 * @group PF
 */
class PFValuesUtilsTest extends TestCase {
	private const GLOBAL_UNSET = '__PF_GLOBAL_UNSET__';

	private $oldUseDisplayTitle;
	private $oldMaxLocalAutocompleteValues;
	private $oldAutocompleteValues;
	private $oldCapitalLinks;
	private $oldPageFormsFieldNum;

	protected function setUp(): void {
		parent::setUp();

		$this->oldUseDisplayTitle = array_key_exists( 'wgPageFormsUseDisplayTitle', $GLOBALS )
			? $GLOBALS['wgPageFormsUseDisplayTitle']
			: self::GLOBAL_UNSET;
		$this->oldMaxLocalAutocompleteValues = array_key_exists( 'wgPageFormsMaxLocalAutocompleteValues', $GLOBALS )
			? $GLOBALS['wgPageFormsMaxLocalAutocompleteValues']
			: self::GLOBAL_UNSET;
		$this->oldAutocompleteValues = array_key_exists( 'wgPageFormsAutocompleteValues', $GLOBALS )
			? $GLOBALS['wgPageFormsAutocompleteValues']
			: self::GLOBAL_UNSET;
		$this->oldCapitalLinks = array_key_exists( 'wgCapitalLinks', $GLOBALS )
			? $GLOBALS['wgCapitalLinks']
			: self::GLOBAL_UNSET;
		$this->oldPageFormsFieldNum = array_key_exists( 'wgPageFormsFieldNum', $GLOBALS )
			? $GLOBALS['wgPageFormsFieldNum']
			: self::GLOBAL_UNSET;

		$GLOBALS['wgPageFormsUseDisplayTitle'] = true;
		$GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] = 100;
		$GLOBALS['wgPageFormsAutocompleteValues'] = [];
		$GLOBALS['wgCapitalLinks'] = false;
	}

	protected function tearDown(): void {
		$this->restoreGlobal( 'wgPageFormsUseDisplayTitle', $this->oldUseDisplayTitle );
		$this->restoreGlobal( 'wgPageFormsMaxLocalAutocompleteValues', $this->oldMaxLocalAutocompleteValues );
		$this->restoreGlobal( 'wgPageFormsAutocompleteValues', $this->oldAutocompleteValues );
		$this->restoreGlobal( 'wgCapitalLinks', $this->oldCapitalLinks );
		$this->restoreGlobal( 'wgPageFormsFieldNum', $this->oldPageFormsFieldNum );

		parent::tearDown();
	}

	/**
	 * @covers \PFValuesUtils::maybeDisambiguateAutocompleteLabels
	 */
	public function testMaybeDisambiguateAutocompleteLabelsOnlyForMappedSources() {
		$labels = [
			'FooAlpha' => 'Paris',
			'FooBeta' => 'Paris'
		];
		$expected = [
			'FooAlpha' => 'Paris (FooAlpha)',
			'FooBeta' => 'Paris (FooBeta)'
		];

		foreach ( [ 'category', 'concept', 'namespace', 'property' ] as $sourceType ) {
			$this->assertSame( $expected, PFValuesUtils::maybeDisambiguateAutocompleteLabels( $labels, $sourceType ) );
		}

		$GLOBALS['wgPageFormsUseDisplayTitle'] = false;
		$this->assertSame( $labels, PFValuesUtils::maybeDisambiguateAutocompleteLabels( $labels, 'category' ) );
	}

	/**
	 * @covers \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
	 */
	public function testGetRemoteDataTypeAndPossiblySetAutocompleteValuesReturnsRemoteForCategory() {
		// category is always fetched remotely via the pfautocomplete API regardless
		// of possible_values or $wgPageFormsMaxLocalAutocompleteValues.
		$fieldArgs = [
			'possible_values' => [
				'FooAlpha' => 'Paris',
				'FooBeta' => 'Paris'
			]
		];

		$remoteDataType = PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			'category',
			'DisambiguationCategory',
			$fieldArgs,
			'disambiguation-settings'
		);

		$this->assertSame( 'category', $remoteDataType );
		$this->assertArrayNotHasKey( 'disambiguation-settings', $GLOBALS['wgPageFormsAutocompleteValues'] );
	}

	/**
	 * @covers \PFValuesUtils::shiftShortestMatch
	 */
	public function testShiftShortestMatchWithEmptyArray(): void {
		$this->assertSame( [], PFValuesUtils::shiftShortestMatch( [] ) );
	}

	/**
	 * @covers \PFValuesUtils::shiftShortestMatch
	 */
	public function testShiftShortestMatchMovesShortestToFront(): void {
		$this->assertSame(
			[ 'ab', 'abc', 'abcd' ],
			PFValuesUtils::shiftShortestMatch( [ 'abc', 'ab', 'abcd' ] )
		);
	}

	/**
	 * @covers \PFValuesUtils::shiftShortestMatch
	 */
	public function testShiftShortestMatchLeavesArrayUnchangedWhenShortestAlreadyFirst(): void {
		$this->assertSame(
			[ 'a', 'bc', 'dee' ],
			PFValuesUtils::shiftShortestMatch( [ 'a', 'bc', 'dee' ] )
		);
	}

	/**
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleReturnsFallbackForNull(): void {
		$this->assertSame( 'Page', PFValuesUtils::resolveDisplayTitle( null, 'Page' ) );
	}

	/**
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleReturnsFallbackForBlankString(): void {
		$this->assertSame( 'Page', PFValuesUtils::resolveDisplayTitle( '   ', 'Page' ) );
	}

	/**
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleReturnsFallbackForTagsOnly(): void {
		$this->assertSame( 'Page', PFValuesUtils::resolveDisplayTitle( '<b></b>', 'Page' ) );
	}

	/**
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleReturnsFallbackForNbspOnly(): void {
		$this->assertSame( 'Page', PFValuesUtils::resolveDisplayTitle( '&#160;', 'Page' ) );
	}

	/**
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleReturnsRawWhenSet(): void {
		$this->assertSame( 'My Display Title', PFValuesUtils::resolveDisplayTitle( 'My Display Title', 'Page' ) );
	}

	/**
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleDecodesHtmlEntities(): void {
		$this->assertSame( 'Hello "World"', PFValuesUtils::resolveDisplayTitle( 'Hello &quot;World&quot;', 'Page' ) );
	}

	/**
	 * @covers \PFValuesUtils::disambiguateLabels
	 */
	public function testDisambiguateLabelsReturnsUnchangedWhenNoDuplicates(): void {
		$input = [ 'paris_fr' => 'Alpha', 'paris_tx' => 'Beta' ];
		$result = PFValuesUtils::disambiguateLabels( $input );
		$this->assertSame( 'Alpha', $result['paris_fr'] );
		$this->assertSame( 'Beta', $result['paris_tx'] );
	}

	/**
	 * @covers \PFValuesUtils::disambiguateLabels
	 */
	public function testDisambiguateLabelsSuffixesDuplicateValues(): void {
		$input = [ 'Paris_FR' => 'Paris', 'Paris_TX' => 'Paris' ];
		$result = PFValuesUtils::disambiguateLabels( $input );
		$this->assertSame( 'Paris (Paris_FR)', $result['Paris_FR'] );
		$this->assertSame( 'Paris (Paris_TX)', $result['Paris_TX'] );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesReturnsEmptyArrayWhenSourceIsNull(): void {
		$this->assertSame( [], PFValuesUtils::getAutocompleteValues( null, 'property' ) );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 * @dataProvider provideGetAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSource( array $fieldArgs, array $expected ): void {
		$this->assertSame( $expected, PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs ) );
	}

	public static function provideGetAutocompletionTypeAndSource(): array {
		return [
			'values from property' => [
				[ 'values from property' => 'MyProp' ],
				[ 'property', 'MyProp' ],
			],
			'values from category' => [
				[ 'values from category' => 'MyCat' ],
				[ 'category', 'MyCat' ],
			],
			'values from concept' => [
				[ 'values from concept' => 'MyConcept' ],
				[ 'concept', 'MyConcept' ],
			],
			'values from namespace' => [
				[ 'values from namespace' => 'Template' ],
				[ 'namespace', 'Template' ],
			],
			'values from url' => [
				[ 'values from url' => 'myalias' ],
				[ 'external_url', 'myalias' ],
			],
			'values from wikidata' => [
				[ 'values from wikidata' => 'Q123' ],
				[ 'wikidata', 'Q123' ],
			],
			'autocomplete field type passthrough' => [
				[ 'autocomplete field type' => 'custom', 'autocompletion source' => 'src' ],
				[ 'custom', 'src' ],
			],
			'semantic_property' => [
				[ 'semantic_property' => 'HasName' ],
				[ 'property', 'HasName' ],
			],
			'no match returns nulls' => [
				[],
				[ null, null ],
			],
		];
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceForValues(): void {
		$GLOBALS['wgPageFormsFieldNum'] = 7;
		$fieldArgs = [ 'values' => 'a,b,c' ];
		$this->assertSame( [ 'values', 'values-7' ], PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs ) );
	}

	private function restoreGlobal( $globalName, $value ): void {
		if ( $value === self::GLOBAL_UNSET ) {
			unset( $GLOBALS[$globalName] );
			return;
		}

		$GLOBALS[$globalName] = $value;
	}
}
