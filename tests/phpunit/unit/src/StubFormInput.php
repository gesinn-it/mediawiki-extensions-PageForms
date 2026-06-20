<?php

declare( strict_types=1 );

/**
 * Minimal stub for PFFormInput used in unit tests to avoid bootstrapping
 * the full MediaWiki environment.
 */
class StubFormInput extends PFFormInput {

	public const STUB_HTML = '<input class="stub"/>';

	public static function getName(): string {
		return 'text';
	}

	public static function getDefaultPropTypes(): array {
		return [];
	}

	public static function getDefaultPropTypeLists(): array {
		return [];
	}

	public static function getOtherPropTypesHandled(): array {
		return [];
	}

	public static function getOtherPropTypeListsHandled(): array {
		return [];
	}

	public function getHtmlText(): string {
		return self::STUB_HTML;
	}

	public static function getParameters(): array {
		return [];
	}
}
