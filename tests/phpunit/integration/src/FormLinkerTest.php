<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\FormLinker;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use PFUtils;
use ReflectionProperty;
use Title;

/**
 * @covers \MediaWiki\Extension\PageForms\FormLinker::setBrokenLink
 * @covers \MediaWiki\Extension\PageForms\FormLinker::getDefaultFormsForPage
 * @covers \MediaWiki\Extension\PageForms\FormLinker::getDefaultForm
 * @covers \MediaWiki\Extension\PageForms\FormLinker::getDefaultFormForNamespace
 * @group Database
 */
class FormLinkerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset static namespace form cache before each test
		$reflProp = new ReflectionProperty( FormLinker::class, 'formPerNamespace' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( null, [] );
	}

	// -------------------------------------------------------------------------
	// getDefaultForm()
	// -------------------------------------------------------------------------

	public function testGetDefaultFormReturnsNullForNullTitle(): void {
		$this->assertNull( FormLinker::getDefaultForm( null ) );
	}

	public function testGetDefaultFormReturnsNullWhenNoPagePropSet(): void {
		$title = Title::newFromText( 'PFTestFormLinkerNoDefaultFormPage01' );
		$this->insertPage( $title, 'Plain content without a default form.' );

		$this->assertNull( FormLinker::getDefaultForm( $title ) );
	}

	public function testGetDefaultFormReturnsPagePropValueWhenSetViaDefaultFormTag(): void {
		$title = Title::newFromText( 'PFTestFormLinkerDefaultFormPage01' );
		$this->insertPage( $title, '{{#default_form:PFTestFormLinkerForm01}}' );

		$this->assertSame( 'PFTestFormLinkerForm01', FormLinker::getDefaultForm( $title ) );
	}

	// -------------------------------------------------------------------------
	// getDefaultFormForNamespace()
	// -------------------------------------------------------------------------

	public function testGetDefaultFormForNamespaceReturnsNullWhenNoProjectPageDefaultFormSet(): void {
		// NS_TALK has no project-namespace default-form page created in this test.
		$this->assertNull( FormLinker::getDefaultFormForNamespace( NS_TALK ) );
	}

	public function testGetDefaultFormForNamespaceReturnsConfiguredForm(): void {
		$namespaceLabel = PFUtils::getContLang()->getNamespaces()[NS_HELP];
		$nsPageTitle = Title::makeTitleSafe( NS_PROJECT, $namespaceLabel );
		$this->insertPage( $nsPageTitle, '{{#default_form:PFTestFormLinkerNSForm01}}' );

		$this->assertSame( 'PFTestFormLinkerNSForm01', FormLinker::getDefaultFormForNamespace( NS_HELP ) );
	}

	public function testGetDefaultFormForNamespaceReturnsSameValueOnRepeatedCalls(): void {
		$namespaceLabel = PFUtils::getContLang()->getNamespaces()[NS_CATEGORY];
		$nsPageTitle = Title::makeTitleSafe( NS_PROJECT, $namespaceLabel );
		$this->insertPage( $nsPageTitle, '{{#default_form:PFTestFormLinkerNSForm02}}' );

		$first = FormLinker::getDefaultFormForNamespace( NS_CATEGORY );
		$second = FormLinker::getDefaultFormForNamespace( NS_CATEGORY );

		$this->assertSame( 'PFTestFormLinkerNSForm02', $first );
		$this->assertSame( $first, $second );

		// insertPage() commits its write outside of this test's DB
		// transaction, so the NS_CATEGORY namespace default form would
		// otherwise leak into later tests. Delete it explicitly.
		$this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $nsPageTitle )
			->doDeleteArticleReal( 'test cleanup', $this->getTestUser()->getUser() );
	}

	// -------------------------------------------------------------------------
	// getDefaultFormsForPage() — additional branches not covered above
	// -------------------------------------------------------------------------

	public function testGetDefaultFormsForPageReturnsOwnDefaultFormBeforeCheckingCategory(): void {
		$catName = 'PFTestFormLinkerOwnFormPageCat01';
		$catTitle = Title::makeTitleSafe( NS_CATEGORY, $catName );
		$this->insertPage( $catTitle, '{{#default_form:PFTestFormLinkerCatForm01}}' );

		$title = Title::newFromText( 'PFTestFormLinkerOwnDefaultFormPage01' );
		$this->insertPage(
			$title,
			"{{#default_form:PFTestFormLinkerOwnForm01}}\n[[Category:$catName]]"
		);

		$this->assertSame( [ 'PFTestFormLinkerOwnForm01' ], FormLinker::getDefaultFormsForPage( $title ) );
	}

	public function testGetDefaultFormsForPageDedupesSharedCategoryDefaultForm(): void {
		$sharedForm = 'PFTestFormLinkerSharedForm01';
		$catA = 'PFTestFormLinkerCatA01';
		$catB = 'PFTestFormLinkerCatB01';
		$this->insertPage( Title::makeTitleSafe( NS_CATEGORY, $catA ), '{{#default_form:' . $sharedForm . '}}' );
		$this->insertPage( Title::makeTitleSafe( NS_CATEGORY, $catB ), '{{#default_form:' . $sharedForm . '}}' );

		$title = Title::newFromText( 'PFTestFormLinkerPageInTwoCats01' );
		$this->insertPage( $title, "[[Category:$catA]]\n[[Category:$catB]]" );

		$this->assertSame( [ $sharedForm ], FormLinker::getDefaultFormsForPage( $title ) );
	}

	public function testGetDefaultFormsForPageReturnsEmptyForSubpageWithOnlyNamespaceDefaultForm(): void {
		$namespaceLabel = PFUtils::getContLang()->getNamespaces()[NS_PROJECT];
		$nsPageTitle = Title::makeTitleSafe( NS_PROJECT, $namespaceLabel );
		$this->insertPage( $nsPageTitle, '{{#default_form:PFTestFormLinkerNSSubpageForm01}}' );

		$parentTitle = Title::newFromText( 'PFTestFormLinkerSubpageParent01', NS_PROJECT );
		$this->insertPage( $parentTitle, 'Parent content.' );
		$subpageTitle = Title::newFromText( 'PFTestFormLinkerSubpageParent01/Sub01', NS_PROJECT );
		$this->insertPage( $subpageTitle, 'Subpage content.' );

		$this->assertTrue( $subpageTitle->isSubpage() );
		$this->assertSame( [], FormLinker::getDefaultFormsForPage( $subpageTitle ) );
	}

	// -------------------------------------------------------------------------
	// Note: createPageWithForm() is deliberately not covered here. It calls
	// formHTML() with is_query=false, which runs formHTML()'s permission-check
	// branch (src/FormPrinter.php's getPermissionErrors()/getUserEffectiveGroups()
	// path). That branch was observed to leave stale state that makes unrelated
	// FormPrinterTest cases (data sets #5/#6, which depend on the *current*
	// test user's effective groups) fail when run in the same PHPUnit process
	// afterwards. Detailed formHTML()/page-text rendering behaviour for this
	// call path is already covered by FormPrinterTest and PFAutoeditAPITest,
	// so the risk of a brittle, order-dependent test here outweighs the
	// coverage gained.
	// -------------------------------------------------------------------------

	public function testSetBrokenLinkSkipsKnownLinks(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', true );
		$target = Title::newFromText( 'SomePFLinkerKnownPageXYZ01' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$attribs = [ 'href' => '/index.php/SomePFLinkerKnownPageXYZ01' ];
		$text = 'text';
		$ret = null;

		FormLinker::setBrokenLink( $linkRenderer, $target, true, $text, $attribs, $ret );

		$this->assertStringNotContainsString( 'formedit', $attribs['href'] );
	}

	public function testSetBrokenLinkSkipsSpecialNamespace(): void {
		$this->setMwGlobals( 'wgPageFormsLinkAllRedLinksToForms', true );
		$target = Title::newFromText( 'Special:RecentChanges' );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$attribs = [ 'href' => '/index.php/Special:RecentChanges' ];
		$text = 'text';
		$ret = null;

		FormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

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

		FormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

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

		FormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

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

		FormLinker::setBrokenLink( $linkRenderer, $target, false, $text, $attribs, $ret );

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

	public function testGetDefaultFormsForPageIgnoresCategoryOwnDefaultForm(): void {
		// "#default_form" on a category page sets the default form for
		// that category's member pages, not for the category page itself.
		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, 'PFFormLinkerTestCategoryWithDefaultForm01' );
		$this->insertPage( $categoryTitle, '{{#default_form:PFFormLinkerTestForm01}}' );

		$forms = FormLinker::getDefaultFormsForPage( $categoryTitle );

		$this->assertSame( [], $forms );
	}

	public function testGetDefaultFormsForPageIgnoresEmptyDefaultFormOnCategoryPage(): void {
		$categoryTitle = Title::makeTitleSafe( NS_CATEGORY, 'PFFormLinkerTestCategoryWithEmptyDefaultForm01' );
		$this->insertPage( $categoryTitle, '{{#default_form:}}' );

		$forms = FormLinker::getDefaultFormsForPage( $categoryTitle );

		$this->assertSame( [], $forms );
	}
}
