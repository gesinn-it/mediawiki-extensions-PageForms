<?php

use MediaWiki\Extension\PageForms\FormCounters;
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
		FormCounters::resetStandalone();

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
	 * When SMW is not installed (or the count cannot be determined),
	 * getSourceCount() returns null, and exceedsLocalAutocompleteThreshold()
	 * must conservatively report the source as exceeding the threshold, so
	 * that 'remote autocompletion' callers stay remote rather than risking
	 * an unbounded local fetch (see #187).
	 *
	 * @covers \PFValuesUtils::exceedsLocalAutocompleteThreshold
	 */
	public function testExceedsLocalAutocompleteThresholdReturnsTrueWhenSmwAbsent(): void {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW is installed; this test requires SMW to be absent.' );
		}

		$this->assertTrue(
			PFValuesUtils::exceedsLocalAutocompleteThreshold( 'category', 'PFTestValuesUtilsLargeCat01' )
		);
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
		FormCounters::current()->fieldNum = 7;
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
	// getSourceCount / exceedsLocalAutocompleteThreshold — 'property' type
	// (issue #190: a property count must reflect distinct *values*, not the
	// number of pages/subjects annotated with it)
	// -------------------------------------------------------------------------

	/**
	 * A property annotated on many pages but with only a handful of distinct
	 * values (the exact scenario reported in #190: 517 annotations, 5
	 * distinct values) must be counted by its distinct-value count, not its
	 * annotation count — otherwise it's wrongly pushed into remote
	 * autocompletion even though the full value set would easily fit locally.
	 *
	 * @covers \PFValuesUtils::getSourceCount
	 */
	public function testGetSourceCountForPropertyCountsDistinctValuesNotAnnotations(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		// Store already returns the deduplicated set (mirrors real SMW
		// behavior for getPropertyValues( null, $property, ... )) — the
		// point under test is that getSourceCount() doesn't separately
		// re-count annotations via a SomeProperty MODE_COUNT query.
		$store = $this->mockStoreReturning( [ 'Apple', 'Banana', 'Cherry', 'Date', 'Elderberry' ] );

		$count = PFValuesUtils::getSourceCount( 'property', 'Foo', $store );

		$this->assertSame( 5, $count );
	}

	/**
	 * @covers \PFValuesUtils::getSourceCount
	 */
	public function testGetSourceCountForPropertyCapsAtLimitPlusOne(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] = 3;

		// The RequestOptions passed to getPropertyValues() caps the fetch at
		// limit+1; simulate the store honoring that cap.
		$store = $this->mockStoreReturning( [ 'A', 'B', 'C', 'D' ] );
		$store->expects( $this->once() )
			->method( 'getPropertyValues' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->callback( static fn ( $opts ) => $opts instanceof \SMW\RequestOptions && $opts->limit === 4 )
			)
			->willReturn( array_map( function ( $v ) {
				$item = $this->createMock( \SMWDataItem::class );
				$item->method( 'getSortKey' )->willReturn( $v );
				return $item;
			}, [ 'A', 'B', 'C', 'D' ] ) );

		$count = PFValuesUtils::getSourceCount( 'property', 'Foo', $store );

		$this->assertSame( 4, $count );
	}

	/**
	 * A property with zero values (e.g. newly created, never annotated)
	 * must count as 0, not be mistaken for "count unavailable → remote".
	 *
	 * @covers \PFValuesUtils::getSourceCount
	 */
	public function testGetSourceCountForPropertyReturnsZeroForUnusedProperty(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [] );

		$count = PFValuesUtils::getSourceCount( 'property', 'Foo', $store );

		$this->assertSame( 0, $count );
	}

	/**
	 * category/namespace/concept types must keep using the cheap MODE_COUNT
	 * path (annotation-style counting is correct for those types — a
	 * category's "member count" and "distinct value count" are the same
	 * thing), i.e. the 'property' special-case must not affect other types.
	 *
	 * @covers \PFValuesUtils::getSourceCount
	 */
	public function testGetSourceCountForCategoryDoesNotUseDistinctValuePath(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->createMock( \SMW\Store::class );
		$store->expects( $this->never() )->method( 'getPropertyValues' );
		$store->method( 'getQueryResult' )->willReturn( null );

		PFValuesUtils::getSourceCount( 'category', 'SomeCategory', $store );
	}

	// -------------------------------------------------------------------------
	// buildWikidataSparqlQuery / parseWikidataResponse (SPARQL injection regression)
	// -------------------------------------------------------------------------

	/**
	 * Runs $callback with the given globals set and restores them afterwards.
	 *
	 * @param array $globals
	 * @param callable $callback
	 * @return mixed
	 */
	private function withGlobals( array $globals, callable $callback ) {
		$previous = [];
		foreach ( $globals as $name => $value ) {
			$previous[$name] = $GLOBALS[$name] ?? null;
			$GLOBALS[$name] = $value;
		}
		try {
			return $callback();
		} finally {
			foreach ( $previous as $name => $value ) {
				$GLOBALS[$name] = $value;
			}
		}
	}

	/**
	 * Regression test for a SPARQL injection in getAllValuesFromWikidata(): a
	 * "values from wikidata" filter value was spliced unescaped into a SPARQL
	 * string literal, letting a Form editor break out of the literal and inject
	 * arbitrary SPARQL. The payload must end up inside the label literal, with
	 * its quotes escaped.
	 *
	 * @covers \PFValuesUtils::buildWikidataSparqlQuery
	 */
	public function testWikidataQueryEscapesInjectionInFilterValue(): void {
		$payload = 'nomatch"@en . } UNION { BIND("INJECTED-MARKER" AS ?valueLabel) } #';

		$sparql = $this->withGlobals( [ 'wgLanguageCode' => 'en' ], static function () use ( $payload ) {
			return PFValuesUtils::buildWikidataSparqlQuery( urlencode( 'P31=' . $payload ) );
		} );

		$this->assertStringContainsString(
			'rdfs:label "nomatch\\"@en . } UNION { BIND(\\"INJECTED-MARKER\\" AS ?valueLabel) } #"@en',
			$sparql
		);
		$this->assertStringNotContainsString( 'BIND("INJECTED-MARKER"', $sparql );
	}

	/**
	 * Same injection class, but via the $substring parameter (the autocomplete
	 * search term), which is spliced into a REGEX() string literal.
	 *
	 * @covers \PFValuesUtils::buildWikidataSparqlQuery
	 */
	public function testWikidataQueryEscapesInjectionInSubstring(): void {
		$substringPayload = 'x")) } UNION { BIND("INJECTED-MARKER" AS ?valueLabel) } #';

		$sparql = $this->withGlobals(
			[ 'wgLanguageCode' => 'en', 'wgPageFormsMaxAutocompleteValues' => 100 ],
			static function () use ( $substringPayload ) {
				return PFValuesUtils::buildWikidataSparqlQuery( urlencode( 'P31=Q6256' ), $substringPayload );
			}
		);

		// The substring is lowercased, then escaped, and stays inside the REGEX() literal.
		$this->assertStringContainsString(
			'FILTER(REGEX(LCASE(?valueLabel), "\\\\bx\\")) } union { bind(\\"injected-marker\\" as ?valuelabel) } #"))',
			$sparql
		);
		$this->assertStringNotContainsString( 'BIND("INJECTED-MARKER"', $sparql );
		$this->assertStringNotContainsString( 'bind("injected-marker"', $sparql );
	}

	/**
	 * A numeric (item) filter is used as a plain wd: reference, a text filter
	 * becomes a label match, and the autocomplete limits only apply with a substring.
	 *
	 * @covers \PFValuesUtils::buildWikidataSparqlQuery
	 */
	public function testWikidataQueryBuildsItemAndLabelFiltersAndLimits(): void {
		[ $itemOnly, $labelOnly, $withSubstring ] = $this->withGlobals(
			[ 'wgLanguageCode' => 'en', 'wgPageFormsMaxAutocompleteValues' => 100 ],
			static function () {
				return [
					PFValuesUtils::buildWikidataSparqlQuery( urlencode( 'P31=Q6256' ) ),
					PFValuesUtils::buildWikidataSparqlQuery( urlencode( 'P31=Some "quoted" label' ) ),
					PFValuesUtils::buildWikidataSparqlQuery( urlencode( 'P31=Q6256' ), 'Ger' ),
				];
			}
		);

		$this->assertStringContainsString( '?value wdt:P31 wd:Q6256 .', $itemOnly );
		$this->assertStringContainsString( 'rdfs:label "Some \\"quoted\\" label"@en', $labelOnly );
		$this->assertStringNotContainsString( 'LIMIT', $itemOnly );
		$this->assertStringContainsString( 'LIMIT 110', $withSubstring );
		$this->assertStringEndsWith( 'LIMIT 100', $withSubstring );
	}

	/**
	 * @covers \PFValuesUtils::parseWikidataResponse
	 */
	public function testParseWikidataResponseReturnsTheLabelsOfAllBindings(): void {
		$response = json_encode( [ 'results' => [ 'bindings' => [
			[ 'valueLabel' => [ 'type' => 'literal', 'value' => 'Germany' ] ],
			[ 'valueLabel' => [ 'type' => 'literal', 'value' => 'France' ] ],
		] ] ] );

		$this->assertSame( [ 'Germany', 'France' ], PFValuesUtils::parseWikidataResponse( $response ) );
	}

	/**
	 * @covers \PFValuesUtils::parseWikidataResponse
	 */
	public function testParseWikidataResponseReturnsNothingForAnUnusableResponse(): void {
		$this->assertSame( [], PFValuesUtils::parseWikidataResponse( false ) );
		$this->assertSame( [], PFValuesUtils::parseWikidataResponse( 'not json' ) );
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

	/**
	 * @covers \PFValuesUtils::getSourceCount
	 * @covers \PFValuesUtils::resetSourceCounts
	 */
	public function testGetSourceCountOfTheLiveStoreIsRememberedUntilReset(): void {
		if ( !class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$remembered = new ReflectionProperty( PFValuesUtils::class, 'sourceCounts' );
		PFValuesUtils::resetSourceCounts();

		$count = PFValuesUtils::getSourceCount( 'category', 'PFSourceCountTestCategory' );

		$this->assertSame( 0, $count );
		$this->assertCount( 1, $remembered->getValue() );
		PFValuesUtils::resetSourceCounts();
		$this->assertSame( [], $remembered->getValue() );
	}
}
