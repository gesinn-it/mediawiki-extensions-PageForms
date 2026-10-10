<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The "plain" format: each field as a paragraph that starts with its label in bold.
 *
 * It is also the base of the formats that put no table around the fields, and so it writes no
 * label for a format it does not know (see UnformattedTemplateFormat).
 */
class PlainTemplateFormat implements TemplateFormat {

	public function open(): string {
		return '';
	}

	public function header( string $label, bool $notFirst ): string {
		return "\n'''" . $label . ":''' ";
	}

	public function nonemptyLead(): string {
		return "\n";
	}

	public function nonemptyHeader( string $label, bool $notFirst ): string {
		return "'''" . $label . ":''' ";
	}

	public function nonemptySeparator(): string {
		return '';
	}

	public function valueCell(): string {
		return '';
	}

	public function nonemptyValueCell(): string {
		return '';
	}

	public function aggregationHeader( string $label, bool $hasFields ): string {
		return "\n'''" . $label . ":''' ";
	}

	public function close(): string {
		return '';
	}

	public function trailingNewline(): string {
		return '';
	}
}
