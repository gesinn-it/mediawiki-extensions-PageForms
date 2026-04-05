<?php

use MediaWiki\MediaWikiServices;

/**
 * Integration tests for PFFormCache — the form-definition caching subsystem
 * extracted from PFFormUtils.
 *
 * @group PF
 * @group Database
 * @covers PFFormCache::getFormCache
 * @covers PFFormCache::getCacheKey
 * @covers PFFormCache::purgeCache
 * @covers PFFormCache::purgeCacheOnSave
 * @covers PFFormCache::getPreloadedText
 * @covers PFFormCache::getFormDefinition
 */
class PFFormCacheTest extends MediaWikiIntegrationTestCase {

	// -----------------------------------------------------------------------
	// getFormCache
	// -----------------------------------------------------------------------

	public function testGetFormCacheReturnsBagOStuff() {
		$cache = PFFormCache::getFormCache();
		$this->assertInstanceOf( BagOStuff::class, $cache );
	}

	// -----------------------------------------------------------------------
	// getCacheKey
	// -----------------------------------------------------------------------

	public function testGetCacheKeyWithoutParserReturnsString() {
		$key = PFFormCache::getCacheKey( '42' );
		$this->assertIsString( $key );
		$this->assertStringContainsString( 'PageForms', $key );
		$this->assertStringContainsString( '42', $key );
	}

	public function testGetCacheKeyWithParserDiffersFromWithout() {
		// getServiceContainer() was added in MW 1.36; use MediaWikiServices::getInstance() for MW 1.35 compat.
		$parser = MediaWikiServices::getInstance()->getParser();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$keyWithout = PFFormCache::getCacheKey( '7' );
		$keyWith = PFFormCache::getCacheKey( '7', $parser );
		$this->assertNotSame( $keyWithout, $keyWith );
	}

	public function testGetCacheKeysDifferForDifferentIds() {
		$key1 = PFFormCache::getCacheKey( '1' );
		$key2 = PFFormCache::getCacheKey( '2' );
		$this->assertNotSame( $key1, $key2 );
	}

	// -----------------------------------------------------------------------
	// purgeCache — non-form namespace → no-op, returns true
	// -----------------------------------------------------------------------

	public function testPurgeCacheIgnoresNonFormPages() {
		$title = Title::newFromText( 'SomePage', NS_MAIN );
		// getServiceContainer() / getWikiPageFactory() require MW 1.36+; use WikiPage::factory() on MW 1.35.
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			// @codeCoverageIgnoreStart
			$wikiPage = WikiPage::factory( $title );
			// @codeCoverageIgnoreEnd
		}
		$result = PFFormCache::purgeCache( $wikiPage );
		$this->assertTrue( $result );
	}

	// -----------------------------------------------------------------------
	// getPreloadedText
	// -----------------------------------------------------------------------

	public function testGetPreloadedTextEmptyStringReturnsEmpty() {
		$this->assertSame( '', PFFormCache::getPreloadedText( '' ) );
	}

	public function testGetPreloadedTextNonExistentTitleReturnsEmpty() {
		// Non-existent page → getPageText returns '' and no noinclude stripping needed
		$result = PFFormCache::getPreloadedText( 'PFFormCacheTestNonExistentPage12345' );
		$this->assertSame( '', $result );
	}

	public function testGetPreloadedTextExistingPageReturnsContent() {
		$this->editPage(
			'PFFormCacheTestPreloadPage01',
			"Hello preload\n<noinclude>hidden</noinclude>",
			'',
			NS_MAIN
		);
		$result = PFFormCache::getPreloadedText( 'PFFormCacheTestPreloadPage01' );
		$this->assertStringContainsString( 'Hello preload', $result );
		$this->assertStringNotContainsString( 'hidden', $result );
		$this->assertStringNotContainsString( '<noinclude>', $result );
	}

	public function testGetPreloadedTextStripsIncludeonlyTags() {
		$this->editPage(
			'PFFormCacheTestPreloadPage02',
			"<includeonly>wrapped</includeonly> visible",
			'',
			NS_MAIN
		);
		$result = PFFormCache::getPreloadedText( 'PFFormCacheTestPreloadPage02' );
		$this->assertStringNotContainsString( '<includeonly>', $result );
		$this->assertStringContainsString( 'wrapped', $result );
	}
}
