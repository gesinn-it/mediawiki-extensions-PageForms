<?php

use MediaWiki\MediaWikiServices;

/**
 * @covers \PFFormLinker::setBrokenLink
 * @covers \PFFormLinker::getDefaultFormsForPage
 * @group Database
 */
class PFFormLinkerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset static namespace form cache before each test
		$reflProp = new ReflectionProperty( PFFormLinker::class, 'formPerNamespace' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( null, [] );
	}

	public function testSetBrokenLinkSkipsKnownLinks(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', true );
		$target = Title::newFromText( 'SomePFLinkerKnownPageXYZ01' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$attribs = [ 'href' => '/index.php/SomePFLinkerKnownPageXYZ01' ];
		$text = 'text';
		$ret = null;

		PFFormLinker::setBrokenLink( $linkRenderer, $target, true, $text, $attribs, $ret );

		$this->assertStringNotContainsString( 'formedit', $attribs['href'] );
	}

	public function testSetBrokenLinkSkipsSpecialNamespace(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', true );
		$target = Title::newFromText( 'Special:RecentChanges' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$attribs = [ 'href' => '/index.php/Special:RecentChanges' ];
		$text = 'text';
		$ret = null;

		PFFormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

		$this->assertStringNotContainsString( 'formedit', $attribs['href'] );
	}

	public function testSetBrokenLinkModifiesRedLinkWhenLinkAllRedLinksToFormsEnabled(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', true );
		$this->setMwGlobals( 'wgContentNamespaces', [ NS_MAIN ] );

		$target = Title::newFromText( 'PFFormLinkerNonExistentTestPage01' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$attribs = [ 'href' => $target->getLinkURL( [ 'action' => 'edit', 'redlink' => '1' ] ) ];
		$text = 'text';
		$ret = null;

		PFFormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

		$this->assertStringContainsString( 'action=formedit', $attribs['href'] );
		$this->assertStringContainsString( 'redlink=1', $attribs['href'] );
	}

	public function testSetBrokenLinkDoesNotModifyRedLinkWhenFeatureDisabled(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', false );
		$this->setMwGlobals( 'wgContentNamespaces', [ NS_MAIN ] );

		$target = Title::newFromText( 'PFFormLinkerNonExistentTestPage02' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$originalHref = $target->getLinkURL( [ 'action' => 'edit', 'redlink' => '1' ] );
		$attribs = [ 'href' => $originalHref ];
		$text = 'text';
		$ret = null;

		PFFormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

		$this->assertStringNotContainsString( 'formedit', $attribs['href'] );
	}

	public function testSetBrokenLinkDoesNotModifyRedLinkOutsideContentNamespace(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', true );
		$this->setMwGlobals( 'wgContentNamespaces', [ NS_MAIN ] );

		$target = Title::newFromText( 'Talk:PFFormLinkerNonExistentTestPage03' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$originalHref = $target->getLinkURL( [ 'action' => 'edit', 'redlink' => '1' ] );
		$attribs = [ 'href' => $originalHref ];
		$text = 'text';
		$ret = null;

		PFFormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

		$this->assertStringNotContainsString( 'formedit', $attribs['href'] );
	}

	public function testLinkRendererRedLinkHrefContainsFormeditWhenFeatureEnabled(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', true );
		$this->setMwGlobals( 'wgContentNamespaces', [ NS_MAIN ] );

		$target = Title::newFromText( 'PFFormLinkerNonExistentTestPage04' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$html = $linkRenderer->makeLink( $target );

		$this->assertStringContainsString( 'action=formedit', $html );
		$this->assertStringContainsString( 'redlink=1', $html );
	}

	public function testGetDefaultFormsForPageReturnsCategoryOwnDefaultForm(): void {
		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, 'PFFormLinkerTestCategoryWithDefaultForm01' );
		$this->insertPage( $categoryTitle, '{{#default_form:PFFormLinkerTestForm01}}' );

		$forms = PFFormLinker::getDefaultFormsForPage( $categoryTitle );

		$this->assertSame( [ 'PFFormLinkerTestForm01' ], $forms );
	}

	public function testGetDefaultFormsForPageEmptyDefaultFormOnCategoryPageCancelsInheritance(): void {
		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, 'PFFormLinkerTestCategoryWithEmptyDefaultForm01' );
		$this->insertPage( $categoryTitle, '{{#default_form:}}' );

		$forms = PFFormLinker::getDefaultFormsForPage( $categoryTitle );

		$this->assertSame( [], $forms );
	}
}
