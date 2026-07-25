<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\PageValue;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Title;

/**
 * @group PF
 * @covers \MediaWiki\Extension\PageForms\PageValue
 */
class PageValueTest extends MediaWikiIntegrationTestCase {

	public function testNewFromTextParsesNamespaceAndDbKey(): void {
		$pageValue = PageValue::newFromText( 'Category:Foo Bar' );

		$this->assertNotNull( $pageValue );
		$this->assertSame( NS_CATEGORY, $pageValue->getNamespace() );
	}

	public function testNewFromTextReturnsNullForInvalidTitle(): void {
		$this->assertNull( PageValue::newFromText( '<>' ) );
	}

	public function testNewFromTitleMatchesNewFromText(): void {
		$fromTitle = PageValue::newFromTitle( Title::newFromText( 'Category:Foo' ) );
		$fromText = PageValue::newFromText( 'Category:Foo' );

		$this->assertTrue( $fromTitle->equals( $fromText ) );
	}

	public function testGetCanonicalStringForMainNamespace(): void {
		$pageValue = PageValue::newFromText( 'Foo Bar' );

		$this->assertSame( 'Foo Bar', $pageValue->getCanonicalString() );
	}

	public function testGetCanonicalStringPrefixesCanonicalNamespaceName(): void {
		$pageValue = PageValue::newFromText( 'Category:Foo_Bar' );

		$this->assertSame( 'Category:Foo Bar', $pageValue->getCanonicalString() );
	}

	/**
	 * Regression scenario for issue #175: on a wiki with a non-English
	 * content language, the canonical string must use the canonical
	 * (English) namespace name, not the localized one, matching how
	 * MediaWiki serializes internal links in wikitext.
	 */
	public function testGetCanonicalStringUsesCanonicalNamespaceNameOnNonEnglishWiki(): void {
		// Warm up NamespaceInfo's canonical-namespaces cache before switching
		// the language, matching how SMW expects wgLanguageCode to be settled
		// before namespace setup - otherwise SMW's own language-change guard
		// (NamespaceManager) throws upon the first getCanonicalName() call.
		MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalNamespaces();

		global $wgLanguageCode;
		$oldLanguageCode = $wgLanguageCode;
		$wgLanguageCode = 'de';
		MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );

		try {
			$pageValue = PageValue::newFromTitle( Title::makeTitle( NS_CATEGORY, 'Product_Aspect_Dimensions' ) );

			$this->assertSame( 'Category:Product Aspect Dimensions', $pageValue->getCanonicalString() );
		} finally {
			$wgLanguageCode = $oldLanguageCode;
			MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );
		}
	}

	public function testEqualsTrueForSameCanonicalString(): void {
		$a = PageValue::newFromText( 'Category:Foo' );
		$b = PageValue::newFromText( 'Category:Foo' );

		$this->assertTrue( $a->equals( $b ) );
	}

	public function testEqualsTrueForUnderscoreVsSpaceVariant(): void {
		$a = PageValue::newFromText( 'Category:Foo_Bar' );
		$b = PageValue::newFromText( 'Category:Foo Bar' );

		$this->assertTrue( $a->equals( $b ) );
	}

	public function testEqualsTrueForCapitalizationVariant(): void {
		$a = PageValue::newFromTitle( Title::makeTitle( NS_MAIN, 'foo' ) );
		$b = PageValue::newFromTitle( Title::makeTitle( NS_MAIN, 'Foo' ) );

		$this->assertTrue( $a->equals( $b ) );
	}

	public function testEqualsFalseForDifferentNamespace(): void {
		$a = PageValue::newFromText( 'Category:Foo' );
		$b = PageValue::newFromText( 'Foo' );

		$this->assertFalse( $a->equals( $b ) );
	}

	public function testEqualsFalseForDifferentTitle(): void {
		$a = PageValue::newFromText( 'Category:Foo' );
		$b = PageValue::newFromText( 'Category:Bar' );

		$this->assertFalse( $a->equals( $b ) );
	}
}
