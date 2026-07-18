<?php

/**
 * @covers \PFHelperFormAction
 * @group Database
 */
class PFHelperFormActionTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgPageFormsShowTabsForAllHelperForms', true );
	}

	// -----------------------------------------------------------------------
	// getName / execute
	// -----------------------------------------------------------------------

	public function testGetName(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionGetName01' );
		$article = Article::newFromTitle( $title, RequestContext::getMain() );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		$action = new PFHelperFormAction( $article, $context );

		$this->assertSame( 'formcreate', $action->getName() );
	}

	public function testExecuteReturnsTrue(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionExecute01' );
		$article = Article::newFromTitle( $title, RequestContext::getMain() );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		$action = new PFHelperFormAction( $article, $context );

		$this->assertTrue( $action->execute() );
	}

	// -----------------------------------------------------------------------
	// displayTab — early-return guards (no tab added)
	// -----------------------------------------------------------------------

	public function testDisplayTabSkipsMainNamespace(): void {
		$title = Title::makeTitle( NS_MAIN, 'PFHelperFormActionMainNS01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayNotHasKey( 'formcreate', $links['views'] );
		$this->assertSame( [ 'view', 'edit', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabSkipsExistingFormPage(): void {
		// editPage creates the page so title->exists() === true
		$this->editPage( 'PFHelperFormActionExistingForm01', '', '', PF_NS_FORM );
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionExistingForm01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayNotHasKey( 'formcreate', $links['views'] );
	}

	public function testDisplayTabSkipsTemplateNamespaceWhenFlagDisabled(): void {
		$this->setMwGlobals( 'wgPageFormsShowTabsForAllHelperForms', false );
		// Non-existing template title
		$title = Title::makeTitle( NS_TEMPLATE, 'PFHelperFormActionTplNoFlag01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayNotHasKey( 'formcreate', $links['views'] );
	}

	public function testDisplayTabSkipsCategoryNamespaceWhenFlagDisabled(): void {
		$this->setMwGlobals( 'wgPageFormsShowTabsForAllHelperForms', false );
		$title = Title::makeTitle( NS_CATEGORY, 'PFHelperFormActionCatNoFlag01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayNotHasKey( 'formcreate', $links['views'] );
	}

	// -----------------------------------------------------------------------
	// displayTab — tab added paths
	// -----------------------------------------------------------------------

	public function testDisplayTabAddsTabForNonExistingFormPage(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionNewForm01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertSame( wfMessage( 'pf_formcreate' )->text(), $links['views']['formcreate']['text'] );
		$this->assertSame( $title->getLocalURL( 'action=formcreate' ), $links['views']['formcreate']['href'] );
		$this->assertSame( [ 'view', 'formcreate', 'edit', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabAddsTabForNonExistingTemplatePageWhenFlagEnabled(): void {
		$title = Title::makeTitle( NS_TEMPLATE, 'PFHelperFormActionNewTpl01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertSame( wfMessage( 'pf_formcreate' )->text(), $links['views']['formcreate']['text'] );
	}

	public function testDisplayTabAddsTabForNonExistingCategoryPageWhenFlagEnabled(): void {
		$title = Title::makeTitle( NS_CATEGORY, 'PFHelperFormActionNewCat01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertSame( wfMessage( 'pf_formcreate' )->text(), $links['views']['formcreate']['text'] );
	}

	public function testDisplayTabShowsViewFormTextForNonEditableUser(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionNoEditUser01' );

		$userWithoutEdit = $this->createMock( User::class );
		$userWithoutEdit->method( 'isAllowed' )->willReturn( true );
		$permManager = $this->createMock( \MediaWiki\Permissions\PermissionManager::class );
		$permManager->method( 'userCan' )->with( 'edit', $userWithoutEdit, $title )->willReturn( false );
		$this->setService( 'PermissionManager', $permManager );

		$links = $this->newCreateLinks();
		$context = $this->newContext( $title, $userWithoutEdit );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertSame( wfMessage( 'pf_viewform' )->text(), $links['views']['formcreate']['text'] );
	}

	public function testDisplayTabMarksTabSelectedForFormCreateAction(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionSelected01' );
		$links = $this->newCreateLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser(), [ 'action' => 'formcreate' ] );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertSame( 'selected', $links['views']['formcreate']['class'] );
	}

	public function testDisplayTabInsertedBeforeViewsourceWhenNoEditTab(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionViewSource01' );
		$links = [
			'views' => [
				'view'       => [ 'text' => 'Read', 'href' => '#view' ],
				'viewsource' => [ 'text' => 'View source', 'href' => '#viewsource' ],
				'history'    => [ 'text' => 'History', 'href' => '#history' ],
			]
		];

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertSame( [ 'view', 'formcreate', 'viewsource', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabInsertedBeforeEditWhenEditIsFirstTab(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionEditFirst01' );
		$links = [
			'views' => [
				'edit'    => [ 'text' => 'Edit', 'href' => '#edit' ],
				'history' => [ 'text' => 'History', 'href' => '#history' ],
			]
		];

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertSame( [ 'formcreate', 'edit', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabInsertedAtEndWhenNoEditOrViewsourceTab(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionNoEditOrVS01' );
		$links = [
			'views' => [
				'view'    => [ 'text' => 'Read', 'href' => '#view' ],
				'history' => [ 'text' => 'History', 'href' => '#history' ],
			]
		];

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertContains( 'formcreate', array_keys( $links['views'] ) );
	}

	public function testDisplayTabRemovesEditAndViewSourceWithoutViewedittabRight(): void {
		$title = Title::makeTitle( PF_NS_FORM, 'PFHelperFormActionNoViewEditTab01' );
		$links = [
			'views' => [
				'view'       => [ 'text' => 'Read', 'href' => '#view' ],
				'edit'       => [ 'text' => 'Edit', 'href' => '#edit' ],
				'viewsource' => [ 'text' => 'View source', 'href' => '#viewsource' ],
				'history'    => [ 'text' => 'History', 'href' => '#history' ],
			]
		];

		$editableUser = $this->getTestUser()->getUser();
		$userWithoutViewEditTab = $this->createMock( User::class );
		$userWithoutViewEditTab->method( 'isAllowed' )
			->with( 'viewedittab' )
			->willReturn( false );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'getTitle' )->willReturn( $title );
		$context->method( 'getRequest' )->willReturn( new FauxRequest() );
		$context->method( 'getUser' )
			->willReturnOnConsecutiveCalls( $editableUser, $userWithoutViewEditTab );

		PFHelperFormAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formcreate', $links['views'] );
		$this->assertArrayNotHasKey( 'edit', $links['views'] );
		$this->assertArrayNotHasKey( 'viewsource', $links['views'] );
		$this->assertSame( [ 'view', 'formcreate', 'history' ], array_keys( $links['views'] ) );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function newContext( Title $title, User $user, array $requestParams = [] ): IContextSource {
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getTitle' )->willReturn( $title );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getRequest' )->willReturn( new FauxRequest( $requestParams ) );

		return $context;
	}

	private function newCreateLinks(): array {
		return [
			'views' => [
				'view'    => [ 'text' => 'Read', 'href' => '#view' ],
				'edit'    => [ 'text' => 'Edit', 'href' => '#edit' ],
				'history' => [ 'text' => 'History', 'href' => '#history' ],
			]
		];
	}
}
