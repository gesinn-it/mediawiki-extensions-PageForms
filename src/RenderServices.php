<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use ParserFactory;
use ReadOnlyMode;

/**
 * The MediaWiki services that rendering a form needs.
 *
 * The form printer lives as long as the request, and in tests longer than the service container:
 * the container is replaced between tests, and a service handed to the printer once would keep
 * running the hooks and checking the permissions of the old one. So each service is looked up when
 * it is used. This is the one place of the form rendering that asks MediaWikiServices; everything
 * else gets this object, and a test replaces it to fake a service.
 *
 * @ingroup PF
 */
class RenderServices {

	public function hookContainer(): HookContainer {
		return MediaWikiServices::getInstance()->getHookContainer();
	}

	public function permissionManager(): PermissionManager {
		return MediaWikiServices::getInstance()->getPermissionManager();
	}

	public function readOnlyMode(): ReadOnlyMode {
		return MediaWikiServices::getInstance()->getReadOnlyMode();
	}

	public function parserFactory(): ParserFactory {
		return MediaWikiServices::getInstance()->getParserFactory();
	}
}
