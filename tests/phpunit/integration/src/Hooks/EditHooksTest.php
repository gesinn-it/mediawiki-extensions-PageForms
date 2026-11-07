<?php

use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\PageForms\Hooks\EditHooks;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\PageForms\Hooks\EditHooks
 * @group Database
 *
 * @author gesinn-it-wam
 */
class EditHooksTest extends MediaWikiIntegrationTestCase {

	use \HidesLinkHolderDeprecation;

	/**
	 * Set up environment
	 */
	private EditHooks $hooks;

	public function setUp(): void {
		parent::setUp();
		$this->hooks = new EditHooks();
	}

	// -------------------------------------------------------------------------
	// disableTinyMCE()
	// -------------------------------------------------------------------------

	public function testDisableTinyMCEReturnsFalseForFormNamespaceTitle(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFTestHooksFormNamespaceTitle01' );

		$this->assertFalse( $this->hooks->onTinyMCEDisable( $title ) );
	}

	public function testDisableTinyMCEReturnsFalseWhenPageHasDefaultForm(): void {
		$title = Title::newFromText( 'PFTestHooksPageWithDefaultForm01' );
		$this->insertPage( $title, '{{#default_form:PFTestHooksForm01}}' );

		$this->assertFalse( $this->hooks->onTinyMCEDisable( $title ) );
	}

	public function testDisableTinyMCEReturnsTrueForNormalPageWithNoDefaultForm(): void {
		$title = Title::newFromText( 'PFTestHooksPageWithoutDefaultForm01' );
		$this->insertPage( $title, 'Plain content without a default form.' );

		$this->assertTrue( $this->hooks->onTinyMCEDisable( $title ) );
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

		$result = $this->hooks->onEditPage__importFormData( $editPage, new FauxRequest() );

		$this->assertTrue( $result );
		$this->assertSame( '', $editPage->previewTextAfterContent );
	}

	public function testShowFormPreviewReturnsTrueWithoutSideEffectsOutsideFormNamespace(): void {
		$title = Title::newFromText( 'PFTestHooksNotFormNamespacePage01' );
		$this->insertPage( $title, 'Some content.' );
		$editPage = $this->newEditPage( $title );
		$editPage->preview = true;

		$result = $this->hooks->onEditPage__importFormData( $editPage, new FauxRequest() );

		$this->assertTrue( $result );
		$this->assertSame( '', $editPage->previewTextAfterContent );
	}

	public function testShowFormPreviewRendersFormUsingPassedInRequest(): void {
		global $wgPageFormsFormPrinter, $wgOut;
		$this->assertNotNull(
			$wgPageFormsFormPrinter,
			'Setup::initialize() must have run to set up the global form printer'
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

		$result = $this->hooks->onEditPage__importFormData( $editPage, $request );

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'pfForm', $editPage->previewTextAfterContent );
		$this->assertStringContainsString( 'PFTestHooksPreviewTpl01', $editPage->previewTextAfterContent );
	}

	// -------------------------------------------------------------------------
	// setPostEditCookie()
	// -------------------------------------------------------------------------

	public function testSetPostEditCookieReturnsTrueWhenGlobalFormPrinterIsNull(): void {
		global $wgPageFormsFormPrinter;
		$savedFormPrinter = $wgPageFormsFormPrinter;
		$wgPageFormsFormPrinter = null;

		try {
			$result = $this->hooks->onPageSaveComplete( ...$this->newSetPostEditCookieArgs() );
			$this->assertTrue( $result );
		} finally {
			$wgPageFormsFormPrinter = $savedFormPrinter;
		}
	}

	public function testSetPostEditCookieReturnsTrueWhenGlobalIsNotAFormPrinter(): void {
		global $wgPageFormsFormPrinter;
		$savedFormPrinter = $wgPageFormsFormPrinter;
		// A plain stdClass is not a FormPrinter, matching the guard clause.
		$wgPageFormsFormPrinter = new stdClass();

		try {
			$result = $this->hooks->onPageSaveComplete( ...$this->newSetPostEditCookieArgs() );
			$this->assertTrue( $result );
		} finally {
			$wgPageFormsFormPrinter = $savedFormPrinter;
		}
	}

	public function testSetPostEditCookieSkipsCookieForBotEditSignalledViaRequestContext(): void {
		global $wgPageFormsFormPrinter;
		$this->assertNotNull(
			$wgPageFormsFormPrinter,
			'Setup::initialize() must have run to set up the global form printer'
		);
		// Build fixtures under the default request first, so the real
		// PageSaveComplete hook fired by insertPage() doesn't interfere with
		// the bot-flagged request swapped in below.
		[ $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ] = $this->newSetPostEditCookieArgs();

		$context = RequestContext::getMain();
		$savedRequest = $context->getRequest();
		// The bot flag must be honoured via RequestContext/WebRequest, not $_REQUEST,
		// so it is controllable through a FauxRequest in tests. With the form
		// printer present (guard clause not triggered), the only remaining
		// early-return path is the bot-flag check itself.
		$botRequest = new FauxRequest( [ 'bot' => 'true' ] );
		$context->setRequest( $botRequest );

		try {
			$result = $this->hooks->onPageSaveComplete(
				$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
			);

			$this->assertTrue( $result );
			$this->assertFalse(
				$botRequest->response()->hasCookies(),
				'A bot edit must not set the post-edit cookie'
			);
		} finally {
			$context->setRequest( $savedRequest );
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
