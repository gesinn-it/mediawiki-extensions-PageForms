<?php

/**
 * @covers \PFFormEditAction::displayTab
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
