<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\FormRender\PageEditabilityResolver;
use MediaWiki\Extension\PageForms\FormRenderRequest;
use MediaWiki\Extension\PageForms\RenderServices;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWikiIntegrationTestCase;
use OutputPage;
use ReadOnlyMode;
use WebRequest;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * Which page a form is rendered for and whether the user may edit it, with the services faked.
 *
 * @group PF
 * @group Database
 * @covers \MediaWiki\Extension\PageForms\FormRender\PageEditabilityResolver
 * @covers \MediaWiki\Extension\PageForms\FormRender\PageEditability
 */
class PageEditabilityResolverTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param array $permissionErrors What MediaWiki reports for the edit
	 * @param bool $readOnly
	 * @param bool|null $hookSets What a handler of PageForms::UserCanEditPage sets, or null for no handler
	 * @return RenderServices
	 */
	private function services(
		array $permissionErrors, bool $readOnly = false, ?bool $hookSets = null
	): RenderServices {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'getPermissionErrors' )->willReturn( $permissionErrors );
		$readOnlyMode = $this->createMock( ReadOnlyMode::class );
		$readOnlyMode->method( 'isReadOnly' )->willReturn( $readOnly );
		$readOnlyMode->method( 'getReason' )->willReturn( 'maintenance' );
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->method( 'run' )->willReturnCallback(
			static function ( string $hook, array $args ) use ( $hookSets ): bool {
				if ( $hookSets !== null ) {
					$args[1] = $hookSets;
				}
				return true;
			}
		);

		$services = $this->createMock( RenderServices::class );
		$services->method( 'permissionManager' )->willReturn( $permissionManager );
		$services->method( 'readOnlyMode' )->willReturn( $readOnlyMode );
		$services->method( 'hookContainer' )->willReturn( $hookContainer );
		return $services;
	}

	private function request(
		?string $pageName, bool $isQuery = false, bool $isEmbedded = false
	): FormRenderRequest {
		return new FormRenderRequest(
			false, false, $isQuery, $isEmbedded, false, [], $pageName, null, null, null,
			$this->createMock( WebRequest::class ), self::getTestUser()->getUser(),
			$this->createMock( OutputPage::class ), ''
		);
	}

	public function testAUserWithoutErrorsMayEdit() {
		$editability = ( new PageEditabilityResolver( $this->services( [] ) ) )
			->resolve( $this->request( 'PFTestResolverPage01' ) );

		$this->assertTrue( $editability->userCanEdit() );
		$this->assertSame( [], $editability->getPermissionErrors() );
		$this->assertSame( 'PFTestResolverPage01', $editability->getPageTitle()->getPrefixedText() );
	}

	public function testThePermissionErrorsOfMediaWikiAreKept() {
		$errors = [ [ 'protectedpagetext', 'edit', 'edit' ] ];

		$editability = ( new PageEditabilityResolver( $this->services( $errors ) ) )
			->resolve( $this->request( 'PFTestResolverPage02' ) );

		$this->assertFalse( $editability->userCanEdit() );
		$this->assertSame( $errors, $editability->getPermissionErrors() );
	}

	public function testReadOnlyModeReplacesTheErrorsWithItsReason() {
		$editability = ( new PageEditabilityResolver( $this->services( [], true ) ) )
			->resolve( $this->request( 'PFTestResolverPage03' ) );

		$this->assertFalse( $editability->userCanEdit() );
		$this->assertSame( [ [ 'readonlytext', [ 'maintenance' ] ] ], $editability->getPermissionErrors() );
	}

	public function testAHookCanDenyTheEditAndTheFormGetsAnErrorToShow() {
		$editability = ( new PageEditabilityResolver( $this->services( [], false, false ) ) )
			->resolve( $this->request( 'PFTestResolverPage04' ) );

		$this->assertFalse( $editability->userCanEdit() );
		$this->assertSame( [ [ 'badaccess-group0' ] ], $editability->getPermissionErrors() );
	}

	public function testAHookDenyingTheEditKeepsTheErrorsOfMediaWiki() {
		$errors = [ [ 'protectedpagetext', 'edit', 'edit' ] ];

		$editability = ( new PageEditabilityResolver( $this->services( $errors, false, false ) ) )
			->resolve( $this->request( 'PFTestResolverPage05' ) );

		$this->assertSame( $errors, $editability->getPermissionErrors() );
	}

	public function testAHookCanGrantTheEditDespiteTheErrors() {
		$editability = ( new PageEditabilityResolver(
			$this->services( [ [ 'protectedpagetext', 'edit', 'edit' ] ], false, true )
		) )->resolve( $this->request( 'PFTestResolverPage06' ) );

		$this->assertTrue( $editability->userCanEdit() );
	}

	public function testAQueryIsNotCheckedForPermissions() {
		$services = $this->services( [ [ 'protectedpagetext', 'edit', 'edit' ] ], true, false );

		$editability = ( new PageEditabilityResolver( $services ) )
			->resolve( $this->request( 'PFTestResolverPage07', true ) );

		$this->assertTrue( $editability->userCanEdit() );
		$this->assertSame( [], $editability->getPermissionErrors() );
	}

	public function testAPageNameThatIsNoTitleFallsBackToAPlaceholder() {
		$editability = ( new PageEditabilityResolver( $this->services( [] ) ) )
			->resolve( $this->request( '<invalid>[page]' ) );

		$this->assertSame( 'Page Forms permissions test', $editability->getPageTitle()->getText() );
	}

	public function testWithoutAPageNameThePlaceholderIsUsed() {
		$editability = ( new PageEditabilityResolver( $this->services( [] ) ) )
			->resolve( $this->request( null ) );

		$this->assertSame( 'Page Forms permissions test', $editability->getPageTitle()->getText() );
	}
}
