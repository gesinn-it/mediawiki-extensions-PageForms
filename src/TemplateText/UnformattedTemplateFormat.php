<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The format of a name that is none of the known ones: the values of the fields without a label
 * or any other markup around them.
 */
class UnformattedTemplateFormat extends PlainTemplateFormat {

	public function header( string $label, bool $notFirst ): string {
		return '';
	}

	public function nonemptyLead(): string {
		return '';
	}

	public function nonemptyHeader( string $label, bool $notFirst ): string {
		return '';
	}

	public function aggregationHeader( string $label, bool $hasFields ): string {
		return '';
	}
}
