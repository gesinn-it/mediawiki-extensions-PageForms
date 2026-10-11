<?php

use MediaWiki\Extension\PageForms\NamespaceFinder;
use MediaWiki\MediaWikiServices;

/**
 * @covers \MediaWiki\Extension\PageForms\NamespaceFinder
 * @group PF
 */
class NamespaceFinderTest extends MediaWikiIntegrationTestCase {

	public function testStandardizeReturnsTheCanonicalNameOfANamespace(): void {
		$this->assertSame( 'Template', NamespaceFinder::standardize( 'template' ) );
		$this->assertSame( 'Help', NamespaceFinder::standardize( 'HELP' ) );
	}

	public function testNormalizeTurnsMainIntoTheBlankName(): void {
		$this->assertSame( '', NamespaceFinder::normalize( 'Main' ) );
		$this->assertSame( '', NamespaceFinder::normalize( 'main' ) );
		$this->assertSame( 'Template', NamespaceFinder::normalize( 'template' ) );
	}

	public function testFindCodeFindsTheNamespacesOfTheWiki(): void {
		$this->assertSame( NS_TEMPLATE, NamespaceFinder::findCode( 'Template' ) );
		$this->assertSame( NS_MAIN, NamespaceFinder::findCode( '' ) );
		$this->assertSame( NS_TALK, NamespaceFinder::findCode( 'Talk' ) );
	}

	public function testFindCodeReturnsNullForAMissingNamespace(): void {
		$this->assertNull( NamespaceFinder::findCode( 'PFNamespaceFinderNoSuchNamespace' ) );
	}

	public function testFindCodeFindsTheEnglishNameOnAWikiInAnotherLanguage(): void {
		if ( !class_exists( '\SMW\NamespaceManager' ) ) {
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
		MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );

		try {
			$this->assertSame( NS_TEMPLATE, NamespaceFinder::findCode( 'Vorlage' ), 'the name in the wiki language' );
			$this->assertSame( NS_TEMPLATE, NamespaceFinder::findCode( 'Template' ), 'the name in English' );
		} finally {
			$wgLanguageCode = $oldLanguageCode;
			$initLanguageCode->setValue( null, $oldInitLanguageCode );
			MediaWikiServices::getInstance()->resetServiceForTesting( 'ContentLanguage' );
		}
	}
}
