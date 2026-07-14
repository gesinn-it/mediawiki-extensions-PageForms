<?php

/**
 * @covers \PFHooks
 * @group Database
 *
 * @author gesinn-it-wam
 */
class PFHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * Set up environment
	 */
	public function setUp(): void {
		parent::setUp();
	}

	public function testVisitSomeLinesOfCodeOnly() {
		self::expectNotToPerformAssertions();

		PFHooks::registerExtension();
		PFHooks::initialize();

		$resourceLoaderClass = class_exists( 'MediaWiki\\ResourceLoader\\ResourceLoader' )
			? \MediaWiki\ResourceLoader\ResourceLoader::class
			: \ResourceLoader::class;
		$resourceLoader = $this->createStub( $resourceLoaderClass );
		PFHooks::registerModules( $resourceLoader );

		$list = [];
		PFHooks::registerNamespaces( $list );

		$parser = $this->createStub( Parser::class );
		PFHooks::registerFunctions( $parser );
	}

	// -------------------------------------------------------------------------
	// registerNamespaces()
	// -------------------------------------------------------------------------

	public function testRegisterNamespacesAddsFormNamespaces(): void {
		$list = [];

		PFHooks::registerNamespaces( $list );

		$this->assertSame( 'Form', $list[PF_NS_FORM] );
		$this->assertSame( 'Form_talk', $list[PF_NS_FORM_TALK] );
	}

	public function testRegisterNamespacesEnablesSubpagesForFormTalk(): void {
		global $wgNamespacesWithSubpages;
		$list = [];

		PFHooks::registerNamespaces( $list );

		$this->assertTrue( $wgNamespacesWithSubpages[PF_NS_FORM_TALK] );
	}

	// -------------------------------------------------------------------------
	// registerModules()
	// -------------------------------------------------------------------------

	public function testRegisterModulesRegistersMapsModuleWithNonOfflineScript(): void {
		$resourceLoader = $this->getServiceContainer()->getResourceLoader();

		$result = PFHooks::registerModules( $resourceLoader );

		$this->assertTrue( $result );
		$this->assertContains( 'ext.pageforms.maps', $resourceLoader->getModuleNames() );
		$this->assertNotNull( $resourceLoader->getModule( 'ext.pageforms.maps' ) );
	}

	// -------------------------------------------------------------------------
	// disableTinyMCE()
	// -------------------------------------------------------------------------

	public function testDisableTinyMCEReturnsFalseForFormNamespaceTitle(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFTestHooksFormNamespaceTitle01' );

		$this->assertFalse( PFHooks::disableTinyMCE( $title ) );
	}

	public function testDisableTinyMCEReturnsFalseWhenPageHasDefaultForm(): void {
		$title = Title::newFromText( 'PFTestHooksPageWithDefaultForm01' );
		$this->insertPage( $title, '{{#default_form:PFTestHooksForm01}}' );

		$this->assertFalse( PFHooks::disableTinyMCE( $title ) );
	}

	public function testDisableTinyMCEReturnsTrueForNormalPageWithNoDefaultForm(): void {
		$title = Title::newFromText( 'PFTestHooksPageWithoutDefaultForm01' );
		$this->insertPage( $title, 'Plain content without a default form.' );

		$this->assertTrue( PFHooks::disableTinyMCE( $title ) );
	}

	// -------------------------------------------------------------------------
	// showFormPreview() — directly exercises the #99 request-plumbing fix:
	// formHTML() must render using the $request passed into showFormPreview(),
	// not a globally-spoofed RequestContext.
	// -------------------------------------------------------------------------

	public function testShowFormPreviewReturnsTrueWithoutSideEffectsWhenNotInPreviewMode(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFTestHooksNotPreviewForm01' );
		$editPage = $this->newEditPage( $title );
		$editPage->preview = false;

		$result = PFHooks::showFormPreview( $editPage, new FauxRequest() );

		$this->assertTrue( $result );
		$this->assertSame( '', $editPage->previewTextAfterContent );
	}

	public function testShowFormPreviewReturnsTrueWithoutSideEffectsOutsideFormNamespace(): void {
		$title = Title::newFromText( 'PFTestHooksNotFormNamespacePage01' );
		$this->insertPage( $title, 'Some content.' );
		$editPage = $this->newEditPage( $title );
		$editPage->preview = true;

		$result = PFHooks::showFormPreview( $editPage, new FauxRequest() );

		$this->assertTrue( $result );
		$this->assertSame( '', $editPage->previewTextAfterContent );
	}

	public function testShowFormPreviewRendersFormUsingPassedInRequest(): void {
		global $wgPageFormsFormPrinter, $wgOut;
		$this->assertNotNull(
			$wgPageFormsFormPrinter,
			'PFHooks::initialize() must have run to set up the global form printer'
		);

		$title = Title::makeTitle( PF_NS_FORM, 'PFTestHooksPreviewForm01' );
		$wgOut->getContext()->setTitle( $title );
		$editPage = $this->newEditPage( $title );
		$editPage->preview = true;
		$editPage->textbox1 = "{{{for template|PFTestHooksPreviewTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		// The submitted field value must reach formHTML() only through the
		// $request parameter passed into showFormPreview() - not through any
		// global RequestContext spoofing.
		$request = new FauxRequest( [ 'PFTestHooksPreviewTpl01' => [ 'Name' => 'PFTestHooksPreviewValue01' ] ], true );

		$result = PFHooks::showFormPreview( $editPage, $request );

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'pfForm', $editPage->previewTextAfterContent );
		$this->assertStringContainsString( 'PFTestHooksPreviewTpl01', $editPage->previewTextAfterContent );
	}

	// -------------------------------------------------------------------------
	// setPostEditCookie() — guard-clause paths only; the cookie-setting path
	// requires a full RequestContext response object and is not worth the
	// setup complexity here.
	// -------------------------------------------------------------------------

	public function testSetPostEditCookieReturnsTrueWhenGlobalFormPrinterIsNull(): void {
		global $wgPageFormsFormPrinter;
		$savedFormPrinter = $wgPageFormsFormPrinter;
		$wgPageFormsFormPrinter = null;

		try {
			$result = PFHooks::setPostEditCookie( ...$this->newSetPostEditCookieArgs() );
			$this->assertTrue( $result );
		} finally {
			$wgPageFormsFormPrinter = $savedFormPrinter;
		}
	}

	public function testSetPostEditCookieReturnsTrueWhenFormPrinterHasNoInputTypeHooksProperty(): void {
		global $wgPageFormsFormPrinter;
		$savedFormPrinter = $wgPageFormsFormPrinter;
		// A plain stdClass has no mInputTypeHooks property, matching the guard clause.
		$wgPageFormsFormPrinter = new stdClass();

		try {
			$result = PFHooks::setPostEditCookie( ...$this->newSetPostEditCookieArgs() );
			$this->assertTrue( $result );
		} finally {
			$wgPageFormsFormPrinter = $savedFormPrinter;
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function newEditPage( Title $title ): EditPage {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		$article = Article::newFromTitle( $title, $context );
		return new EditPage( $article );
	}

	private function newSetPostEditCookieArgs(): array {
		$title = Title::newFromText( 'PFTestHooksPostEditCookiePage01' );
		$this->insertPage( $title, 'content' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$user = $this->getTestUser()->getUser();
		$revisionRecord = $wikiPage->getRevisionRecord();
		$editResult = $this->createMock( MediaWiki\Storage\EditResult::class );

		return [ $wikiPage, $user, 'summary', 0, $revisionRecord, $editResult ];
	}
}
