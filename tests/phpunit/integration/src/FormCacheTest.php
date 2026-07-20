<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use BagOStuff;
use MediaWiki\Extension\PageForms\FormCache;
use MediaWiki\MediaWikiServices;
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
}
