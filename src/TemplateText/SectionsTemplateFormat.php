<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The "sections" format: each field below a heading of its label.
 */
class SectionsTemplateFormat extends PlainTemplateFormat {

	public function header( string $label, bool $notFirst ): string {
		return "\n==" . $label . "==\n";
	}

	public function nonemptyHeader( string $label, bool $notFirst ): string {
		return '==' . $label . "==\n";
	}

	public function aggregationHeader( string $label, bool $hasFields ): string {
		return "\n==" . $label . "==\n";
	}
}
