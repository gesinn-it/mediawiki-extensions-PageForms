<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use BagOStuff;
use MediaWiki\Extension\PageForms\FormCache;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOptions;
use Title;
use WikiPage;

/**
 * Integration tests for FormCache — the form-definition caching subsystem
 * extracted from FormUtils.
 *
 * @group PF
 * @group Database
 * @covers \MediaWiki\Extension\PageForms\FormCache::getFormCache
 * @covers \MediaWiki\Extension\PageForms\FormCache::getCacheKey
 * @covers \MediaWiki\Extension\PageForms\FormCache::purgeCache
 * @covers \MediaWiki\Extension\PageForms\FormCache::purgeCacheOnSave
 * @covers \MediaWiki\Extension\PageForms\FormCache::getPreloadedText
 * @covers \MediaWiki\Extension\PageForms\FormCache::getFormDefinition
 * @covers \MediaWiki\Extension\PageForms\FormCache::getFormDefinitionFromCache
 * @covers \MediaWiki\Extension\PageForms\FormCache::cacheFormDefinition
 */
class FormCacheTest extends MediaWikiIntegrationTestCase {

	// -----------------------------------------------------------------------
	// getFormCache
	// -----------------------------------------------------------------------

	public function testGetFormCacheReturnsBagOStuff() {
		$cache = FormCache::getFormCache();
		$this->assertInstanceOf( BagOStuff::class, $cache );
	}

	// -----------------------------------------------------------------------
	// getCacheKey
	// -----------------------------------------------------------------------

	public function testGetCacheKeyWithoutParserReturnsString() {
		$key = FormCache::getCacheKey( 42 );
		$this->assertIsString( $key );
		$this->assertStringContainsString( 'PageForms', $key );
		$this->assertStringContainsString( '42', $key );
	}

	public function testGetCacheKeyWithParserDiffersFromWithout() {
		// getServiceContainer() was added in MW 1.36; use MediaWikiServices::getInstance() for MW 1.35 compat.
		$parser = MediaWikiServices::getInstance()->getParser();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$keyWithout = FormCache::getCacheKey( 7 );
		$keyWith = FormCache::getCacheKey( 7, $parser );
		$this->assertNotSame( $keyWithout, $keyWith );
	}

	public function testGetCacheKeysDifferForDifferentIds() {
		$key1 = FormCache::getCacheKey( 1 );
		$key2 = FormCache::getCacheKey( 2 );
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
		$result = FormCache::purgeCache( $wikiPage );
		$this->assertTrue( $result );
	}

	// -----------------------------------------------------------------------
	// getPreloadedText
	// -----------------------------------------------------------------------

	public function testGetPreloadedTextEmptyStringReturnsEmpty() {
		$this->assertSame( '', FormCache::getPreloadedText( '' ) );
	}

	public function testGetPreloadedTextNonExistentTitleReturnsEmpty() {
		// Non-existent page → getPageText returns '' and no noinclude stripping needed
		$result = FormCache::getPreloadedText( 'PFFormCacheTestNonExistentPage12345' );
		$this->assertSame( '', $result );
	}

	public function testGetPreloadedTextExistingPageReturnsContent() {
		$this->editPage(
			'PFFormCacheTestPreloadPage01',
			"Hello preload\n<noinclude>hidden</noinclude>",
			'',
			NS_MAIN
		);
		$result = FormCache::getPreloadedText( 'PFFormCacheTestPreloadPage01' );
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
		$result = FormCache::getPreloadedText( 'PFFormCacheTestPreloadPage02' );
		$this->assertStringNotContainsString( '<includeonly>', $result );
		$this->assertStringContainsString( 'wrapped', $result );
	}

	public function testGetPreloadedTextInvalidTitleReturnsEmpty() {
		// "|" is not a valid title character; Title::newFromText() returns null.
		$result = FormCache::getPreloadedText( 'PFFormCacheTestInvalid|Title01' );
		$this->assertSame( '', $result );
	}

	public function testGetPreloadedTextPermissionDeniedReturnsEmpty() {
		$this->editPage(
			'PFFormCacheTestPreloadPage03',
			'Secret preload content',
			'',
			NS_MAIN
		);
		$this->setGroupPermissions( '*', 'read', false );
		$this->setGroupPermissions( 'user', 'read', false );
		$result = FormCache::getPreloadedText( 'PFFormCacheTestPreloadPage03' );
		$this->assertSame( '', $result );
	}

	// -----------------------------------------------------------------------
	// getFormDefinition — uncacheable parse with no form_id (preview/preload)
	// -----------------------------------------------------------------------

	public function testGetFormDefinitionWithNullFormIdAndUncacheableOutputDoesNotThrow() {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$title = Title::newFromText( 'PFFormCacheTestNullFormId01', PF_NS_FORM );
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->setTitle( $title );
		// getFormDefinition() calls Parser::parse() with $clearState = false,
		// which assumes an outer parse already initialized $parser->mOutput.
		$parser->clearState();
		// A tag hook that forces the ParserOutput to report itself uncacheable,
		// matching the $output->getCacheTime() == -1 branch in getFormDefinition().
		$parser->setHook( 'pftestnocache', static function ( $input, $args, Parser $parser ) {
			$parser->getOutput()->setCacheTime( '-1' );
			return '';
		} );

		$formDef = '<pftestnocache/>{{{standard input|save}}}';

		// form_id = null (as used for preview/preload/red-link contexts via
		// FormLinker::createPageWithForm()) must not reach
		// purgeCache( Article::newFromID( null ) ), which throws a TypeError
		// since purgeCache() requires a non-nullable WikiPage.
		$result = FormCache::getFormDefinition( $parser, $formDef, null );

		$this->assertIsString( $result );
	}

	public function testGetFormDefinitionNullFormIdAndNullFormDefReturnsEmpty() {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$result = FormCache::getFormDefinition( $parser, null, null );
		$this->assertSame( '', $result );
	}

	public function testGetFormDefinitionCacheHitReturnsCachedValue() {
		$this->setMwGlobals( 'wgPageFormsCacheFormDefinitions', true );
		// Pin the cache backend explicitly: some third-party test suites (e.g. SMW's
		// SMWIntegrationTestCase::disableGlobalCaches()) write $GLOBALS['wgParserCacheType']
		// directly, bypassing setMwGlobals()'s stash/restore, and permanently leak
		// CACHE_NONE into the rest of the PHPUnit process. getFormCache() falls back to
		// wgParserCacheType when wgPageFormsFormCacheType is unset, so without this the
		// cache would silently become a no-op EmptyBagOStuff whenever such a test ran earlier.
		$this->setMwGlobals( 'wgPageFormsFormCacheType', CACHE_HASH );

		$title = Title::newFromText( 'PFFormCacheTestCacheHitForm01', PF_NS_FORM );
		$this->editPage( $title, '{{{standard input|save}}}' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$formId = $wikiPage->getId();

		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->setTitle( $title );
		$parser->clearState();

		// First call populates the cache (cache miss branch).
		$firstResult = FormCache::getFormDefinition( $parser, null, $formId );

		// Second call must hit the cache and skip re-parsing.
		$parser2 = $this->getServiceContainer()->getParserFactory()->create();
		$parser2->setOptions( ParserOptions::newFromAnon() );
		$parser2->setTitle( $title );
		$parser2->clearState();
		$secondResult = FormCache::getFormDefinition( $parser2, null, $formId );

		$this->assertSame( $firstResult, $secondResult );
	}

	public function testGetFormDefinitionCacheDisabledPurgesWikiPage() {
		$this->setMwGlobals( 'wgPageFormsCacheFormDefinitions', false );

		// getFormDefinition() re-reads the form page's own text via
		// Title::newFromID( $form_id ) whenever $form_id is not null, ignoring
		// any $form_def passed in — so the uncacheable-output tag must live in
		// the page content itself, not just in the $form_def argument.
		$title = Title::newFromText( 'PFFormCacheTestUncacheableForm01', PF_NS_FORM );
		$this->editPage( $title, '<pftestnocacheformid/>{{{standard input|save}}}' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$formId = $wikiPage->getId();

		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->setTitle( $title );
		$parser->clearState();
		$parser->setHook( 'pftestnocacheformid', static function ( $input, $args, Parser $parser ) {
			$parser->getOutput()->setCacheTime( '-1' );
			return '';
		} );

		$result = FormCache::getFormDefinition( $parser, null, $formId );

		$this->assertIsString( $result );
	}

	// -----------------------------------------------------------------------
	// purgeCache — form namespace page with cached keys → deletes them
	// -----------------------------------------------------------------------

	public function testPurgeCacheDeletesCachedFormDefinitions() {
		$this->setMwGlobals( 'wgPageFormsCacheFormDefinitions', true );
		// See testGetFormDefinitionCacheHitReturnsCachedValue() for why this is needed:
		// pins the cache backend so a leaked wgParserCacheType=CACHE_NONE from an earlier
		// test class (e.g. SMW's SMWIntegrationTestCase) can't turn this into a no-op cache.
		$this->setMwGlobals( 'wgPageFormsFormCacheType', CACHE_HASH );

		$title = Title::newFromText( 'PFFormCacheTestPurgeCacheForm01', PF_NS_FORM );
		$this->editPage( $title, '{{{standard input|save}}}' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$formId = $wikiPage->getId();

		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->setTitle( $title );
		$parser->clearState();
		// Populate the cache for this form ID.
		FormCache::getFormDefinition( $parser, null, $formId );

		$cache = FormCache::getFormCache();
		$cacheKeyForForm = FormCache::getCacheKey( $formId, $parser );
		$this->assertIsString( $cache->get( $cacheKeyForForm ) );

		$result = FormCache::purgeCache( $wikiPage );
		$this->assertTrue( $result );
		$this->assertFalse( $cache->get( $cacheKeyForForm ) );
	}

	// -----------------------------------------------------------------------
	// purgeCacheOnSave — hook handler
	// -----------------------------------------------------------------------

	public function testPurgeCacheOnSaveWithExistingPageReturnsTrue() {
		$title = Title::newFromText( 'PFFormCacheTestPurgeOnSaveForm01', PF_NS_FORM );
		$this->editPage( $title, '{{{standard input|save}}}' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$pageId = $wikiPage->getId();

		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getPageId' )->willReturn( $pageId );

		$renderedRevision = $this->createMock( RenderedRevision::class );
		$renderedRevision->method( 'getRevision' )->willReturn( $revisionRecord );

		$result = FormCache::purgeCacheOnSave( $renderedRevision );
		$this->assertTrue( $result );
	}
}
