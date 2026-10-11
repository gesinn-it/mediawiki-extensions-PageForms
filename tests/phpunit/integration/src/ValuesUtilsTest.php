<?php

use MediaWiki\Extension\PageForms\FormCounters;
use MediaWiki\Extension\PageForms\ValuesUtils;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\ValuesUtils
 * @group PF
 */
class ValuesUtilsTest extends TestCase {
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
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::maybeDisambiguateAutocompleteLabels
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
			$this->assertSame( $expected, ValuesUtils::maybeDisambiguateAutocompleteLabels( $labels, $sourceType ) );
		}

		$GLOBALS['wgPageFormsUseDisplayTitle'] = false;
		$this->assertSame( $labels, ValuesUtils::maybeDisambiguateAutocompleteLabels( $labels, 'category' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
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

		$remoteDataType = ValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
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
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getSMWPropertyValues
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
		$result = ValuesUtils::getSMWPropertyValues( $store, null, 'IssueType' );

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
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getSMWPropertyValues
	 */
	public function testGetSMWPropertyValuesUsesCanonicalNamespaceNameOnNonEnglishWiki(): void {
		if ( !class_exists( '\SMW\DIWikiPage' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		// SMW remembers the first wgLanguageCode it sees and refuses a later change (#4680). Whether
		// that was already 'en' depends on the tests that ran before, so start from a clean value
		// and put the old one back before the language is switched back.
		$initLanguageCode = new ReflectionProperty( \SMW\NamespaceManager::class, 'initLanguageCode' );
		$initLanguageCode->setAccessible( true );
		$oldInitLanguageCode = $initLanguageCode->getValue();
		$initLanguageCode->setValue( null, '' );

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

			$result = ValuesUtils::getSMWPropertyValues( $store, null, 'AvailableProductAspectCategory' );

			$this->assertSame( [ 'Category:Product Aspect Dimensions' ], $result );
		} finally {
			$wgLanguageCode = $oldLanguageCode;
			$initLanguageCode->setValue( null, $oldInitLanguageCode );
			MediaWiki\MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::disambiguateLabels
	 */
	public function testDisambiguateLabelsReturnsUnchangedWhenNoDuplicates(): void {
		$input = [ 'paris_fr' => 'Alpha', 'paris_tx' => 'Beta' ];
		$result = ValuesUtils::disambiguateLabels( $input );
		$this->assertSame( 'Alpha', $result['paris_fr'] );
		$this->assertSame( 'Beta', $result['paris_tx'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::disambiguateLabels
	 */
	public function testDisambiguateLabelsSuffixesDuplicateValues(): void {
		$input = [ 'Paris_FR' => 'Paris', 'Paris_TX' => 'Paris' ];
		$result = ValuesUtils::disambiguateLabels( $input );
		$this->assertSame( 'Paris (Paris_FR)', $result['Paris_FR'] );
		$this->assertSame( 'Paris (Paris_TX)', $result['Paris_TX'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesReturnsEmptyArrayWhenSourceIsNull(): void {
		$this->assertSame( [], ValuesUtils::getAutocompleteValues( null, 'property' ) );
	}

	/**
	 * When SMW is not installed (or the count cannot be determined),
	 * getSourceCount() returns null, and exceedsLocalAutocompleteThreshold()
	 * must conservatively report the source as exceeding the threshold, so
	 * that 'remote autocompletion' callers stay remote rather than risking
	 * an unbounded local fetch (see #187).
	 *
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::exceedsLocalAutocompleteThreshold
	 */
	public function testExceedsLocalAutocompleteThresholdReturnsTrueWhenSmwAbsent(): void {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW is installed; this test requires SMW to be absent.' );
		}

		$this->assertTrue(
			ValuesUtils::exceedsLocalAutocompleteThreshold( 'category', 'PFTestValuesUtilsLargeCat01' )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAutocompletionTypeAndSource
	 * @dataProvider provideGetAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSource( array $fieldArgs, array $expected ): void {
		$this->assertSame( $expected, ValuesUtils::getAutocompletionTypeAndSource( $fieldArgs ) );
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
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceForValues(): void {
		FormCounters::current()->fieldNum = 7;
		$fieldArgs = [ 'values' => 'a,b,c' ];
		$this->assertSame( [ 'values', 'values-7' ], ValuesUtils::getAutocompletionTypeAndSource( $fieldArgs ) );
	}

	// -------------------------------------------------------------------------
	// getAllValuesForProperty (mock-store injection)
	// -------------------------------------------------------------------------

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAllValuesForProperty
	 */
	public function testGetAllValuesForPropertyReturnsSortedValues(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [ 'Zebra', 'Apple', 'Mango' ] );

		$result = ValuesUtils::getAllValuesForProperty( 'SomeProp', $store, 100, false );

		$this->assertSame( [ 'Apple', 'Mango', 'Zebra' ], $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAllValuesForProperty
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

		ValuesUtils::getAllValuesForProperty( 'SomeProp', $store, 2, false );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAllValuesForProperty
	 */
	public function testGetAllValuesForPropertySkipsDisplayTitleWhenDisabled(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [ 'Charlie', 'Alpha' ] );

		$result = ValuesUtils::getAllValuesForProperty( 'SomeProp', $store, 100, false );

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
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getSourceCount
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

		$count = ValuesUtils::getSourceCount( 'property', 'Foo', $store );

		$this->assertSame( 5, $count );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getSourceCount
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

		$count = ValuesUtils::getSourceCount( 'property', 'Foo', $store );

		$this->assertSame( 4, $count );
	}

	/**
	 * A property with zero values (e.g. newly created, never annotated)
	 * must count as 0, not be mistaken for "count unavailable → remote".
	 *
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getSourceCount
	 */
	public function testGetSourceCountForPropertyReturnsZeroForUnusedProperty(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [] );

		$count = ValuesUtils::getSourceCount( 'property', 'Foo', $store );

		$this->assertSame( 0, $count );
	}

	/**
	 * category/namespace/concept types must keep using the cheap MODE_COUNT
	 * path (annotation-style counting is correct for those types — a
	 * category's "member count" and "distinct value count" are the same
	 * thing), i.e. the 'property' special-case must not affect other types.
	 *
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getSourceCount
	 */
	public function testGetSourceCountForCategoryDoesNotUseDistinctValuePath(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->createMock( \SMW\Store::class );
		$store->expects( $this->never() )->method( 'getPropertyValues' );
		$store->method( 'getQueryResult' )->willReturn( null );

		ValuesUtils::getSourceCount( 'category', 'SomeCategory', $store );
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
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getSourceCount
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::resetSourceCounts
	 */
	public function testGetSourceCountOfTheLiveStoreIsRememberedUntilReset(): void {
		if ( !class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$remembered = new ReflectionProperty( ValuesUtils::class, 'sourceCounts' );
		ValuesUtils::resetSourceCounts();

		$count = ValuesUtils::getSourceCount( 'category', 'PFSourceCountTestCategory' );

		$this->assertSame( 0, $count );
		$this->assertCount( 1, $remembered->getValue() );
		ValuesUtils::resetSourceCounts();
		$this->assertSame( [], $remembered->getValue() );
	}
}
