<?php

use MediaWiki\Extension\PageForms\AutocompleteSourceCount;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount
 * @group PF
 */
class AutocompleteSourceCountTest extends TestCase {

	private $oldMaxLocalAutocompleteValues;

	protected function setUp(): void {
		parent::setUp();
		$this->oldMaxLocalAutocompleteValues = $GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] ?? null;
		$GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] = 100;
	}

	protected function tearDown(): void {
		if ( $this->oldMaxLocalAutocompleteValues === null ) {
			unset( $GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] );
		} else {
			$GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] = $this->oldMaxLocalAutocompleteValues;
		}
		parent::tearDown();
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

	/**
	 * When SMW is not installed (or the count cannot be determined),
	 * get() returns null, and exceedsLocalThreshold()
	 * must conservatively report the source as exceeding the threshold, so
	 * that 'remote autocompletion' callers stay remote rather than risking
	 * an unbounded local fetch (see #187).
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount::exceedsLocalThreshold
	 */
	public function testExceedsLocalThresholdReturnsTrueWhenSmwAbsent(): void {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW is installed; this test requires SMW to be absent.' );
		}

		$this->assertTrue(
			AutocompleteSourceCount::exceedsLocalThreshold( 'category', 'PFTestValuesUtilsLargeCat01' )
		);
	}

	/**
	 * A property annotated on many pages but with only a handful of distinct
	 * values (the exact scenario reported in #190: 517 annotations, 5
	 * distinct values) must be counted by its distinct-value count, not its
	 * annotation count — otherwise it's wrongly pushed into remote
	 * autocompletion even though the full value set would easily fit locally.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount::get
	 */
	public function testGetForPropertyCountsDistinctValuesNotAnnotations(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		// Store already returns the deduplicated set (mirrors real SMW
		// behavior for getPropertyValues( null, $property, ... )) — the
		// point under test is that get() doesn't separately
		// re-count annotations via a SomeProperty MODE_COUNT query.
		$store = $this->mockStoreReturning( [ 'Apple', 'Banana', 'Cherry', 'Date', 'Elderberry' ] );

		$count = AutocompleteSourceCount::get( 'property', 'Foo', $store );

		$this->assertSame( 5, $count );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount::get
	 */
	public function testGetForPropertyCapsAtLimitPlusOne(): void {
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

		$count = AutocompleteSourceCount::get( 'property', 'Foo', $store );

		$this->assertSame( 4, $count );
	}

	/**
	 * A property with zero values (e.g. newly created, never annotated)
	 * must count as 0, not be mistaken for "count unavailable → remote".
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount::get
	 */
	public function testGetForPropertyReturnsZeroForUnusedProperty(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [] );

		$count = AutocompleteSourceCount::get( 'property', 'Foo', $store );

		$this->assertSame( 0, $count );
	}

	/**
	 * category/namespace/concept types must keep using the cheap MODE_COUNT
	 * path (annotation-style counting is correct for those types — a
	 * category's "member count" and "distinct value count" are the same
	 * thing), i.e. the 'property' special-case must not affect other types.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount::get
	 */
	public function testGetForCategoryDoesNotUseDistinctValuePath(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->createMock( \SMW\Store::class );
		$store->expects( $this->never() )->method( 'getPropertyValues' );
		$store->method( 'getQueryResult' )->willReturn( null );

		AutocompleteSourceCount::get( 'category', 'SomeCategory', $store );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount::get
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSourceCount::reset
	 */
	public function testGetOfTheLiveStoreIsRememberedUntilReset(): void {
		if ( !class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$remembered = new ReflectionProperty( AutocompleteSourceCount::class, 'sourceCounts' );
		AutocompleteSourceCount::reset();

		$count = AutocompleteSourceCount::get( 'category', 'PFSourceCountTestCategory' );

		$this->assertSame( 0, $count );
		$this->assertCount( 1, $remembered->getValue() );
		AutocompleteSourceCount::reset();
		$this->assertSame( [], $remembered->getValue() );
	}
}
