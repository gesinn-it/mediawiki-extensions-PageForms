<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Pure string helpers for the placeholder tokens used when embedding
 * multiple-instance templates inside a parent template's form HTML.
 */
class FormPlaceholder {

	public static function format( string $templateName, string $fieldName ): string {
		$templateName = str_replace( '_', ' ', $templateName );
		$fieldName = str_replace( '_', ' ', $fieldName );
		return $templateName . '___' . $fieldName;
	}

	public static function toHtmlMarker( string $placeholder ): string {
		return '@insert"HTML_' . $placeholder . '@';
	}
}
