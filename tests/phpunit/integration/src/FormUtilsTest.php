<?php

use MediaWiki\Extension\PageForms\FormUtils;
use MediaWiki\MediaWikiServices;
use PHPUnit\Framework\TestCase;

/**
 * The deprecated forwards of FormUtils.
 *
 * @group PF
 */
class FormUtilsTest extends TestCase {

	/**
	 * Setup method for each test.
	 *
	 * Initializes the environment for each test, including setting the OOUI theme.
	 */
	protected function setUp(): void {
		parent::setUp();
		OOUI\Theme::setSingleton( new OOUI\WikimediaUITheme() );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getPreloadedText
	 */
	public function testGetPreloadedTextForwardsToFormCache() {
		$this->assertSame( '', FormUtils::getPreloadedText( '' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::purgeCache
	 */
	public function testPurgeCacheForwardsToFormCache() {
		$title = Title::newFromText( 'PFTestFormUtilsPurgeCachePage01', NS_MAIN );
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );

		$result = FormUtils::purgeCache( $wikiPage );

		$this->assertTrue( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::purgeCacheOnSave
	 */
	public function testPurgeCacheOnSaveForwardsToFormCache() {
		$revisionRecord = $this->createMock( MediaWiki\Revision\RevisionRecord::class );
		$revisionRecord->method( 'getPageId' )->willReturn( 0 );

		$renderedRevision = $this->createMock( MediaWiki\Revision\RenderedRevision::class );
		$renderedRevision->method( 'getRevision' )->willReturn( $revisionRecord );

		$result = FormUtils::purgeCacheOnSave( $renderedRevision );

		$this->assertTrue( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getFormCache
	 */
	public function testGetFormCacheForwardsToFormCache() {
		$cache = FormUtils::getFormCache();
		$this->assertInstanceOf( BagOStuff::class, $cache );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getCacheKey
	 */
	public function testGetCacheKeyForwardsToFormCache() {
		$key = FormUtils::getCacheKey( 42 );
		$this->assertIsString( $key );
		$this->assertStringContainsString( '42', $key );
	}
}
