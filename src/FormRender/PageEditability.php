<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use Title;

/**
 * The page a form is rendered for, and whether the user may edit it.
 */
class PageEditability {

	public function __construct(
		private Title $pageTitle,
		private array $permissionErrors,
		private bool $userCanEdit
	) {
	}

	/**
	 * The page, which is a placeholder title if the real page name is not known yet.
	 */
	public function getPageTitle(): Title {
		return $this->pageTitle;
	}

	/**
	 * The errors that keep the user from editing the page, in the format of PermissionManager.
	 */
	public function getPermissionErrors(): array {
		return $this->permissionErrors;
	}

	public function userCanEdit(): bool {
		return $this->userCanEdit;
	}
}
