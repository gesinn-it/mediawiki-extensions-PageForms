<?php

use MediaWiki\Extension\PageForms\PropertyValues;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\PropertyValues
 * @group PF
 */
class PropertyValuesTest extends TestCase {

	/**
	 * Regression test: forSubject() must preserve the order the SMW store returns.
	 * Previously, shiftShortestMatch() was applied, moving the shortest value to position 0
	 * regardless of the actual sort order. This broke enum value ordering for input types
	 * such as radiobutton, dropdown, and checkboxes when values came from a property.
	 *
	 * @covers \MediaWiki\Extension\PageForms\PropertyValues::forSubject
	 */
	public function testForSubjectPreservesStoreOrderWithoutShortestFirstShift(): void {
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
		$result = PropertyValues::forSubject( $store, null, 'IssueType' );

		// Assert: store insertion order must be preserved — no shortest-first reordering.
		$this->assertSame( [ '(0) Emergency', '(7) Bug', '(1) Security' ], $result );
	}

	/**
	 * Regression test for issue #175: on a wiki with a non-English content
	 * language, forSubject() must prefix page-type values with the
	 * canonical (English) namespace name, not the localized one. Wikitext
	 * always stores internal links using the canonical prefix (e.g.
	 * "Category:"), regardless of content language, so using the localized
	 * name (e.g. "Kategorie:" on a German wiki) here would make the returned
	 * value unable to match the value actually stored on the page, breaking
	 * the DisplayTitle mapping done by addDisplayTitlesForPageValues().
	 *
	 * @covers \MediaWiki\Extension\PageForms\PropertyValues::forSubject
	 */
	public function testForSubjectUsesCanonicalNamespaceNameOnNonEnglishWiki(): void {
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

			$result = PropertyValues::forSubject( $store, null, 'AvailableProductAspectCategory' );

			$this->assertSame( [ 'Category:Product Aspect Dimensions' ], $result );
		} finally {
			$wgLanguageCode = $oldLanguageCode;
			$initLanguageCode->setValue( null, $oldInitLanguageCode );
			MediaWiki\MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\PropertyValues::all
	 */
	public function testAllReturnsSortedValues(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [ 'Zebra', 'Apple', 'Mango' ] );

		$result = PropertyValues::all( 'SomeProp', $store, 100, false );

		$this->assertSame( [ 'Apple', 'Mango', 'Zebra' ], $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\PropertyValues::all
	 */
	public function testAllRespectsMaxValues(): void {
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

		PropertyValues::all( 'SomeProp', $store, 2, false );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\PropertyValues::all
	 */
	public function testAllSkipsDisplayTitleWhenDisabled(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}

		$store = $this->mockStoreReturning( [ 'Charlie', 'Alpha' ] );

		$result = PropertyValues::all( 'SomeProp', $store, 100, false );

		// Plain sorted array — no display-title keys
		$this->assertSame( [ 'Alpha', 'Charlie' ], $result );
		$this->assertArrayHasKey( 0, $result );
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
}
