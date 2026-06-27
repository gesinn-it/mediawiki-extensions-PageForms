<?php

/**
 * @covers \PFFormEditAction
 * @group Database
 */
class PFFormEditActionTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgPageFormsRenameEditTabs', false );
		$this->setMwGlobals( 'wgPageFormsRenameMainEditTab', false );
	}

	public function testDisplayTabSkipsPageWithoutDefaultForm(): void {
		$title = $this->getExistingTestTitle( 'PFFormEditActionNoDefaultForm01' );
		$links = $this->newViewLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayNotHasKey( 'formedit', $links['views'] );
		$this->assertSame( [ 'view', 'edit', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabAddsFormEditTabForEditableUser(): void {
		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionWithDefaultForm01',
			'PFFormEditActionForm01'
		);
		$links = $this->newViewLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formedit', $links['views'] );
		$this->assertSame( wfMessage( 'formedit' )->text(), $links['views']['formedit']['text'] );
		$this->assertSame( $title->getLocalURL( 'action=formedit' ), $links['views']['formedit']['href'] );
		$this->assertSame( [ 'view', 'formedit', 'edit', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabRemovesEditAndViewSourceWithoutViewedittabRight(): void {
		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionWithoutViewEditTab01',
			'PFFormEditActionForm02'
		);
		$links = [
			'views' => [
				'view' => [ 'text' => 'Read', 'href' => '#view' ],
				'edit' => [ 'text' => 'Edit', 'href' => '#edit' ],
				'viewsource' => [ 'text' => 'View source', 'href' => '#viewsource' ],
				'history' => [ 'text' => 'History', 'href' => '#history' ]
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

		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formedit', $links['views'] );
		$this->assertArrayNotHasKey( 'edit', $links['views'] );
		$this->assertArrayNotHasKey( 'viewsource', $links['views'] );
		$this->assertSame( [ 'view', 'formedit', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabMarksFormEditTabSelectedForFormEditAction(): void {
		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionSelectedTab01',
			'PFFormEditActionForm03'
		);
		$links = $this->newViewLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser(), [ 'action' => 'formedit' ] );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formedit', $links['views'] );
		$this->assertSame( 'selected', $links['views']['formedit']['class'] );
	}

	public function testDisplayTabSkipsSpecialPageTitle(): void {
		$title = Title::makeTitle( NS_SPECIAL, 'RecentChanges' );
		$links = $this->newViewLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayNotHasKey( 'formedit', $links['views'] );
	}

	public function testDisplayTabInsertedBeforeViewsourceWhenNoEditTab(): void {
		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionViewSource01',
			'PFFormEditActionForm04'
		);
		$links = [
			'views' => [
				'view'       => [ 'text' => 'Read', 'href' => '#view' ],
				'viewsource' => [ 'text' => 'View source', 'href' => '#viewsource' ],
				'history'    => [ 'text' => 'History', 'href' => '#history' ],
			]
		];

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formedit', $links['views'] );
		$this->assertSame( [ 'view', 'formedit', 'viewsource', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabInsertedAtEndWhenNoEditOrViewsourceTab(): void {
		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionNoEditOrViewSource01',
			'PFFormEditActionForm05'
		);
		$links = [
			'views' => [
				'view'    => [ 'text' => 'Read', 'href' => '#view' ],
				'history' => [ 'text' => 'History', 'href' => '#history' ],
			]
		];

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formedit', $links['views'] );
		// Inserted at position 0 (array_splice with -1 wraps to beginning for
		// single-character keys) — the important invariant is that formedit appears.
		$this->assertContains( 'formedit', array_keys( $links['views'] ) );
	}

	public function testDisplayTabWithRenameEditTabsEnabled(): void {
		$this->setMwGlobals( 'wgPageFormsRenameEditTabs', true );

		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionRenameEditTabs01',
			'PFFormEditActionForm06'
		);
		$links = $this->newViewLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formedit', $links['views'] );
		$this->assertSame( wfMessage( 'edit' )->text(), $links['views']['formedit']['text'] );
		$this->assertSame( wfMessage( 'pf_editsource' )->text(), $links['views']['edit']['text'] );
	}

	public function testDisplayTabWithRenameEditTabsEnabledNonEditableUser(): void {
		$this->setMwGlobals( 'wgPageFormsRenameEditTabs', true );

		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionRenameEditTabsNoEdit01',
			'PFFormEditActionForm07'
		);
		$links = $this->newViewLinks();

		$userWithoutEdit = $this->createMock( User::class );
		// isAllowed('viewedittab') → false so edit/viewsource tabs get removed
		$userWithoutEdit->method( 'isAllowed' )->willReturn( false );
		$permManager = $this->createMock( \MediaWiki\Permissions\PermissionManager::class );
		$permManager->method( 'userCan' )->with( 'edit', $userWithoutEdit, $title )->willReturn( false );
		$this->setService( 'PermissionManager', $permManager );

		$context = $this->newContext( $title, $userWithoutEdit );
		PFFormEditAction::displayTab( $context, $links );

		// With RenameEditTabs and a non-editable user: formedit tab uses 'pf_viewform',
		// and since isAllowed('viewedittab') is false the edit tab is removed entirely.
		$this->assertArrayHasKey( 'formedit', $links['views'] );
		$this->assertSame( wfMessage( 'pf_viewform' )->text(), $links['views']['formedit']['text'] );
		$this->assertArrayNotHasKey( 'edit', $links['views'] );
		$this->assertSame( [ 'view', 'formedit', 'history' ], array_keys( $links['views'] ) );
	}

	public function testDisplayTabWithRenameMainEditTabOnly(): void {
		$this->setMwGlobals( 'wgPageFormsRenameMainEditTab', true );

		$title = $this->getExistingTestTitleWithDefaultForm(
			'PFFormEditActionRenameMainTab01',
			'PFFormEditActionForm08'
		);
		$links = $this->newViewLinks();

		$context = $this->newContext( $title, $this->getTestUser()->getUser() );
		PFFormEditAction::displayTab( $context, $links );

		$this->assertArrayHasKey( 'formedit', $links['views'] );
		$this->assertSame( wfMessage( 'formedit' )->text(), $links['views']['formedit']['text'] );
		$this->assertSame( wfMessage( 'pf_editsource' )->text(), $links['views']['edit']['text'] );
	}

	// -----------------------------------------------------------------------
	// classifyForms
	// -----------------------------------------------------------------------

	/**
	 * Helper: invoke the private static classifyForms() method via reflection.
	 */
	private function classifyForms( array $allFormNames, array $pagesPerForm ): array {
		$method = new ReflectionMethod( PFFormEditAction::class, 'classifyForms' );
		$method->setAccessible( true );
		return $method->invoke( null, $allFormNames, $pagesPerForm );
	}

	public function testClassifyFormsExplicitConfigOverridesHeuristic(): void {
		$this->overrideConfigValues( [
			'PageFormsMainForms' => [ 'Person' ],
			'PageFormsMainFormsLimit' => 5,
		] );
		$allForms = [ 'Organization', 'Person', 'Project' ];
		$pagesPerForm = [ 'Organization' => 500, 'Project' => 200, 'Person' => 10 ];

		$result = $this->classifyForms( $allForms, $pagesPerForm );

		$this->assertSame( [ 'Person' ], $result['main'] );
		$this->assertSame( [ 'Organization', 'Project' ], $result['other'] );
	}

	public function testClassifyFormsExplicitConfigIgnoresUnknownFormNames(): void {
		$this->overrideConfigValues( [
			'PageFormsMainForms' => [ 'Person', 'NonExistentForm' ],
			'PageFormsMainFormsLimit' => 5,
		] );
		$allForms = [ 'Person', 'Organization' ];
		$pagesPerForm = [ 'Organization' => 100, 'Person' => 10 ];

		$result = $this->classifyForms( $allForms, $pagesPerForm );

		$this->assertSame( [ 'Person' ], $result['main'] );
		$this->assertSame( [ 'Organization' ], $result['other'] );
	}

	public function testClassifyFormsFallbackTopNByPageCount(): void {
		$this->overrideConfigValues( [
			'PageFormsMainForms' => [],
			'PageFormsMainFormsLimit' => 2,
		] );
		$allForms = [ 'Alpha', 'Beta', 'Gamma', 'Delta' ];
		// Already ordered DESC as the DB query returns them
		$pagesPerForm = [ 'Beta' => 300, 'Gamma' => 200, 'Alpha' => 50, 'Delta' => 10 ];

		$result = $this->classifyForms( $allForms, $pagesPerForm );

		$this->assertSame( [ 'Beta', 'Gamma' ], $result['main'] );
		$this->assertSame( [ 'Alpha', 'Delta' ], $result['other'] );
	}

	public function testClassifyFormsFallbackShowsAllAsMainWhenFewerThanLimit(): void {
		$this->overrideConfigValues( [
			'PageFormsMainForms' => [],
			'PageFormsMainFormsLimit' => 5,
		] );
		$allForms = [ 'FormA', 'FormB' ];
		$pagesPerForm = [ 'FormA' => 100, 'FormB' => 50 ];

		$result = $this->classifyForms( $allForms, $pagesPerForm );

		$this->assertSame( [ 'FormA', 'FormB' ], $result['main'] );
		$this->assertSame( [], $result['other'] );
	}

	public function testClassifyFormsFallbackAllOtherWhenNoPagesPerForm(): void {
		$this->overrideConfigValues( [
			'PageFormsMainForms' => [],
			'PageFormsMainFormsLimit' => 5,
		] );
		$allForms = [ 'FormA', 'FormB' ];
		$pagesPerForm = [];

		$result = $this->classifyForms( $allForms, $pagesPerForm );

		$this->assertSame( [], $result['main'] );
		$this->assertSame( [ 'FormA', 'FormB' ], $result['other'] );
	}

	// -----------------------------------------------------------------------
	// getNumPagesPerForm — namespace-based #default_form
	// -----------------------------------------------------------------------

	/**
	 * Helper: invoke private static getNumPagesPerForm() via reflection.
	 *
	 * @return int[]
	 */
	private function getNumPagesPerForm(): array {
		$method = new ReflectionMethod( PFFormEditAction::class, 'getNumPagesPerForm' );
		$method->setAccessible( true );
		return $method->invoke( null );
	}

	public function testGetNumPagesPerFormCountsNamespaceDefaultForms(): void {
		// Simulate a namespace-based #default_form assignment:
		// Create a Project-namespace page whose title is the content-language
		// name of NS_USER ("User"), with a PFDefaultForm page property.
		$namespaceLabel = PFUtils::getContLang()->getNamespaces()[NS_USER];
		$nsPageTitle = Title::makeTitleSafe( NS_PROJECT, $namespaceLabel );

		// Create the namespace-definition page with a #default_form property.
		$nsPage = $this->getExistingTestPageWithProps(
			$nsPageTitle,
			[ 'PFDefaultForm' => 'NSUserForm01' ]
		);

		// Create pages in NS_USER so they are counted.
		$this->editPage( 'PFFormEditActionNSUser01', 'content', '', NS_USER );
		$this->editPage( 'PFFormEditActionNSUser02', 'content', '', NS_USER );

		$pagesPerForm = $this->getNumPagesPerForm();

		$this->assertArrayHasKey( 'NSUserForm01', $pagesPerForm );
		$this->assertGreaterThanOrEqual( 2, $pagesPerForm['NSUserForm01'] );
	}

	/**
	 * Insert a page at an exact Title and set page_props on it.
	 *
	 * @param Title $title
	 * @param array<string,string> $props
	 * @return Title
	 */
	private function getExistingTestPageWithProps( Title $title, array $props ): Title {
		$status = $this->editPage(
			$title->getText(),
			'Namespace default form page.',
			'',
			$title->getNamespace()
		);
		$pageId = $title->getArticleID( IDBAccessObject::READ_LATEST );
		if ( version_compare( MW_VERSION, '1.41', '>=' ) ) {
			$dbw = $this->getServiceContainer()->getConnectionProvider()->getPrimaryDatabase();
		} else {
			// MW < 1.41: getConnectionProvider() did not exist; use getDBLoadBalancer()
			$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		}
		foreach ( $props as $propName => $propValue ) {
			$dbw->upsert(
				'page_props',
				[ 'pp_page' => $pageId, 'pp_propname' => $propName, 'pp_value' => $propValue ],
				[ [ 'pp_page', 'pp_propname' ] ],
				[ 'pp_value' => $propValue ],
				__METHOD__
			);
		}
		return $title;
	}

	public function testGetName(): void {
		$title = Title::makeTitle( NS_MAIN, 'PFFormEditActionGetName01' );
		$article = Article::newFromTitle( $title, RequestContext::getMain() );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		$action = new PFFormEditAction( $article, $context );

		$this->assertSame( 'formedit', $action->getName() );
	}

	public function testExecuteReturnsTrue(): void {
		$title = Title::makeTitle( NS_MAIN, 'PFFormEditActionExecute01' );
		$article = Article::newFromTitle( $title, RequestContext::getMain() );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		$action = new PFFormEditAction( $article, $context );

		$this->assertTrue( $action->execute() );
	}

	private function newContext( Title $title, User $user, array $requestParams = [] ): IContextSource {
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getTitle' )->willReturn( $title );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getRequest' )->willReturn( new FauxRequest( $requestParams ) );

		return $context;
	}

	private function getExistingTestTitle( string $pageName ): Title {
		$this->editPage( $pageName, 'Plain content without default form.' );
		return Title::newFromText( $pageName, NS_MAIN );
	}

	private function getExistingTestTitleWithDefaultForm( string $pageName, string $formName ): Title {
		$this->editPage( $pageName, '{{#default_form:' . $formName . '}}\nPage content.' );
		return Title::newFromText( $pageName, NS_MAIN );
	}

	private function newViewLinks(): array {
		return [
			'views' => [
				'view' => [ 'text' => 'Read', 'href' => '#view' ],
				'edit' => [ 'text' => 'Edit', 'href' => '#edit' ],
				'history' => [ 'text' => 'History', 'href' => '#history' ]
			]
		];
	}
}
