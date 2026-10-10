<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use MediaWiki\Extension\PageForms\FormRenderRequest;
use MediaWiki\Extension\PageForms\RenderServices;
use RequestContext;
use Title;

/**
 * Finds out which page a form is rendered for and whether the user may edit it.
 *
 * @ingroup PF
 */
class PageEditabilityResolver {

	public function __construct(
		private RenderServices $services
	) {
	}

	/**
	 * Resolve the title of the page (needed for permission testing even when the real page
	 * name isn't known yet) and compute the edit-permission errors.
	 *
	 * @param FormRenderRequest $request
	 * @return PageEditability
	 */
	public function resolve( FormRenderRequest $request ): PageEditability {
		$pageTitle = $this->resolvePageTitle( $request );
		if ( $request->isQuery ) {
			return new PageEditability( $pageTitle, [], true );
		}

		// Unfortunately, we can't just call userCan() or its
		// equivalent here because it seems to ignore the setting
		// "$wgEmailConfirmToEdit = true;". Instead, we'll just get the
		// permission errors from the start, and use those to determine
		// whether the page is editable.
		$permissionErrors = $this->services->permissionManager()
			->getPermissionErrors( 'edit', $request->user, $pageTitle );
		$readOnlyMode = $this->services->readOnlyMode();
		if ( $readOnlyMode->isReadOnly() ) {
			$permissionErrors = [ [ 'readonlytext', [ $readOnlyMode->getReason() ] ] ];
		}
		$userCanEditPage = count( $permissionErrors ) == 0;
		$this->services->hookContainer()->run(
			'PageForms::UserCanEditPage', [ $pageTitle, &$userCanEditPage ]
		);

		return new PageEditability( $pageTitle, $permissionErrors, $userCanEditPage );
	}

	private function resolvePageTitle( FormRenderRequest $request ): Title {
		// HACK - sometimes we don't know the page name in advance, but
		// we still need to set a title here for testing permissions.
		$placeholderTitle = static fn (): Title => Title::newFromText(
			$request->webRequest->getVal( 'namespace' ) . ":Page Forms permissions test"
		) ?? Title::makeTitle( NS_MAIN, 'Page Forms permissions test' );
		if ( $request->isEmbedded || $request->isQuery ) {
			// If this is an embedded form (probably a 'RunQuery') or we're in Special:RunQuery,
			// just use the name of the actual page we're on.
			return RequestContext::getMain()->getTitle() ?? $placeholderTitle();
		}
		if ( $request->pageName === '' || $request->pageName === null ) {
			return $placeholderTitle();
		}
		// $request->pageName may not be a syntactically valid title (e.g. it was
		// generated from a page name formula, or came from an untrusted
		// request value); fall back to the placeholder title used above for
		// permission-testing purposes, which would otherwise fatal in
		// getPermissionErrors() and other unguarded uses below.
		return Title::newFromText( $request->pageName ) ?? $placeholderTitle();
	}
}
