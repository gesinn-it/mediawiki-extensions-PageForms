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
	public function testGetRemoteDataTypeAndPossiblySetAutocompleteValuesReturnsRemoteForCategoryWhenSmwAbsent() {
		// When SMW is not installed, getSourceCount() returns null → always remote.
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW is installed; this test requires SMW to be absent.' );
		}

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
	 * Regression test: getSMWPropertyValues() must preserve the order the SMW store returns.
	 * Previously, shiftShortestMatch() was applied, moving the shortest value to position 0
	 * regardless of the actual sort order. This broke enum value ordering for input types
	 * such as radiobutton, dropdown, and checkboxes when values came from a property.
	 *
	 * @covers \PFValuesUtils::getSMWPropertyValues
	 */
	public function testGetSMWPropertyValuesPreservesStoreOrderWithoutShortestFirstShift(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		// Arrange: three string data items where the SHORTEST is NOT first.
		// shiftShortestMatch() would move '(7) Bug' (7 chars) before '(0) Emergency'.
		$item1 = $this->createMock( \SMWDataItem::class );
		$item1->method( 'getSortKey' )->willReturn( '(0) Emergency' );
		$item2 = $this->createMock( \SMWDataItem::class );
		$item2->method( 'getSortKey' )->willReturn( '(7) Bug' );
		$item3 = $this->createMock( \SMWDataItem::class );
		$item3->method( 'getSortKey' )->willReturn( '(1) Security' );

		$store = $this->createMock( \SMW\Store::class );
		$store->method( 'getPropertyValues' )->willReturn( [ $item1, $item2, $item3 ] );

		// Act
		$result = PFValuesUtils::getSMWPropertyValues( $store, null, 'IssueType' );

		// Assert: store insertion order must be preserved — no shortest-first reordering.
		$this->assertSame( [ '(0) Emergency', '(7) Bug', '(1) Security' ], $result );
	}

	/**
	 * Regression test for issue #175: on a wiki with a non-English content
	 * language, getSMWPropertyValues() must prefix page-type values with the
	 * canonical (English) namespace name, not the localized one. Wikitext
	 * always stores internal links using the canonical prefix (e.g.
	 * "Category:"), regardless of content language, so using the localized
	 * name (e.g. "Kategorie:" on a German wiki) here would make the returned
	 * value unable to match the value actually stored on the page, breaking
	 * the DisplayTitle mapping done by addDisplayTitlesForPageValues().
	 *
	 * @covers \PFValuesUtils::getSMWPropertyValues
	 */
	public function testGetSMWPropertyValuesUsesCanonicalNamespaceNameOnNonEnglishWiki(): void {
		if ( !class_exists( '\SMW\DIWikiPage' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		global $wgLanguageCode;
		$oldLanguageCode = $wgLanguageCode;
		$wgLanguageCode = 'de';
		MediaWiki\MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );

		try {
			$item = $this->createMock( \SMW\DIWikiPage::class );
			$item->method( 'getDBKey' )->willReturn( 'Product_Aspect_Dimensions' );
			$item->method( 'getNamespace' )->willReturn( NS_CATEGORY );

			$store = $this->createMock( \SMW\Store::class );
			$store->method( 'getPropertyValues' )->willReturn( [ $item ] );

			$result = PFValuesUtils::getSMWPropertyValues( $store, null, 'AvailableProductAspectCategory' );

			$this->assertSame( [ 'Category:Product Aspect Dimensions' ], $result );
		} finally {
			$wgLanguageCode = $oldLanguageCode;
			MediaWiki\MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );
		}
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
	 * MW stores displaytitle as parsed HTML (e.g. <i>MyPage</i> for ''MyPage'').
	 * resolveDisplayTitle must return plain text, not raw HTML, so that Select2
	 * renders the label correctly in the tokens dropdown.
	 *
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleStripsHtmlTagsFromParsedTitle(): void {
		$this->assertSame( 'MyPage', PFValuesUtils::resolveDisplayTitle( '<i>MyPage</i>', 'Page' ) );
	}

	/**
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleStripsNestedHtmlTags(): void {
		$this->assertSame( 'My Page', PFValuesUtils::resolveDisplayTitle( '<b><i>My Page</i></b>', 'Page' ) );
	}

	/**
	 * HTML entities inside tagged content must be decoded to plain text.
	 *
	 * @covers \PFValuesUtils::resolveDisplayTitle
	 */
	public function testResolveDisplayTitleDecodesEntitiesInsideHtmlTags(): void {
		$raw = '<i>Hello &quot;World&quot;</i>';
		$this->assertSame( 'Hello "World"', PFValuesUtils::resolveDisplayTitle( $raw, 'Page' ) );
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

	// -------------------------------------------------------------------------
	// getAllValuesForProperty (mock-store injection)
	// -------------------------------------------------------------------------

	/**
	 * @covers \PFValuesUtils::getAllValuesForProperty
	 */
	public function testGetAllValuesForPropertyReturnsSortedValues(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [ 'Zebra', 'Apple', 'Mango' ] );

		$result = PFValuesUtils::getAllValuesForProperty( 'SomeProp', $store, 100, false );

		$this->assertSame( [ 'Apple', 'Mango', 'Zebra' ], $result );
	}

	/**
	 * @covers \PFValuesUtils::getAllValuesForProperty
	 */
	public function testGetAllValuesForPropertyRespectsMaxValues(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->createMock( \SMW\Store::class );
		$store->expects( $this->once() )
			->method( 'getPropertyValues' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->callback( static fn ( $opts ) => $opts instanceof \SMW\RequestOptions && $opts->limit === 2 )
			)
			->willReturn( [] );

		PFValuesUtils::getAllValuesForProperty( 'SomeProp', $store, 2, false );
	}

	/**
	 * @covers \PFValuesUtils::getAllValuesForProperty
	 */
	public function testGetAllValuesForPropertySkipsDisplayTitleWhenDisabled(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [ 'Charlie', 'Alpha' ] );

		$result = PFValuesUtils::getAllValuesForProperty( 'SomeProp', $store, 100, false );

		// Plain sorted array — no display-title keys
		$this->assertSame( [ 'Alpha', 'Charlie' ], $result );
		$this->assertArrayHasKey( 0, $result );
	}

	// -------------------------------------------------------------------------
	// getAllValuesFromWikidata (SPARQL injection regression — live query.wikidata.org)
	// -------------------------------------------------------------------------

	/**
	 * Regression test for a SPARQL injection in getAllValuesFromWikidata(): a
	 * "values from wikidata" filter value was spliced unescaped into a SPARQL
	 * string literal, letting a Form editor break out of the literal and inject
	 * arbitrary SPARQL sent to the public https://query.wikidata.org/sparql
	 * endpoint. This test fires the adversarial payload at the real endpoint and
	 * asserts the call completes without triggering a malformed-query error and
	 * without leaking the injected marker into the result set.
	 *
	 * @covers \PFValuesUtils::getAllValuesFromWikidata
	 */
	public function testGetAllValuesFromWikidataEscapesInjectionInFilterValue(): void {
		$this->skipIfWikidataUnreachable();

		// Breaks out of the rdfs:label string literal, closes the surrounding
		// triple pattern and group, and unions in a query that would surface a
		// distinctive marker value if the injected SPARQL were executed.
		$payload = 'nomatch"@en . } UNION { BIND("INJECTED-MARKER" AS ?valueLabel) } #';
		$query = urlencode( 'P31=' . $payload );

		$result = PFValuesUtils::getAllValuesFromWikidata( $query );

		$this->assertIsArray( $result );
		$this->assertNotContains(
			'INJECTED-MARKER',
			$result,
			'Injected SPARQL must not be executed against the live endpoint'
		);
	}

	/**
	 * Same injection class, but via the $substring parameter (the autocomplete
	 * search term), which is spliced into a REGEX() string literal.
	 *
	 * @covers \PFValuesUtils::getAllValuesFromWikidata
	 */
	public function testGetAllValuesFromWikidataEscapesInjectionInSubstring(): void {
		$this->skipIfWikidataUnreachable();

		$GLOBALS['wgPageFormsMaxAutocompleteValues'] = 100;

		$query = urlencode( 'P31=Q6256' );
		$substringPayload = 'x")) } UNION { BIND("INJECTED-MARKER" AS ?valueLabel) } #';

		$result = PFValuesUtils::getAllValuesFromWikidata( $query, $substringPayload );

		$this->assertIsArray( $result );
		$this->assertNotContains( 'INJECTED-MARKER', $result );
	}

	/**
	 * A legitimate value containing a double quote must still work as literal
	 * text (i.e. escaping does not just strip quotes, it round-trips them), and
	 * a well-known real filter must still return the expected label.
	 *
	 * @covers \PFValuesUtils::getAllValuesFromWikidata
	 */
	public function testGetAllValuesFromWikidataReturnsRealValueForLegitimateFilter(): void {
		$this->skipIfWikidataUnreachable();

		// wdt:P31 wd:Q6256 = "instance of: country" - Germany (Q183) is one match.
		$query = urlencode( 'P31=Q6256' );

		$result = PFValuesUtils::getAllValuesFromWikidata( $query );

		$this->assertIsArray( $result );
		$this->assertContains( 'Germany', $result );
	}

	private function skipIfWikidataUnreachable(): void {
		set_error_handler( static function () {
			return true;
		} );
		$reachable = fsockopen( 'query.wikidata.org', 443, $errno, $errstr, 5 );
		restore_error_handler();
		if ( $reachable === false ) {
			$this->markTestSkipped( 'query.wikidata.org is not reachable from this environment' );
		}
		fclose( $reachable );
	}

	/**
	 * Build a mock SMW store that returns the given string values from getPropertyValues().
	 *
	 * @param string[] $values
	 * @return \SMW\Store
	 */
	private function mockStoreReturning( array $values ): \SMW\Store {
		$items = array_map( function ( $v ) {
			$item = $this->createMock( \SMWDataItem::class );
			$item->method( 'getSortKey' )->willReturn( $v );
			return $item;
		}, $values );

		$store = $this->createMock( \SMW\Store::class );
		$store->method( 'getPropertyValues' )->willReturn( $items );
		return $store;
	}

	private function restoreGlobal( $globalName, $value ): void {
		if ( $value === self::GLOBAL_UNSET ) {
			unset( $GLOBALS[$globalName] );
			return;
		}

		$GLOBALS[$globalName] = $value;
	}
}
