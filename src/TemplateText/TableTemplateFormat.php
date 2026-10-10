<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The "standard" format: the fields as the rows of a wikitable.
 */
class TableTemplateFormat implements TemplateFormat {

	public function open(): string {
		return '{| class="wikitable"' . "\n";
	}

	public function header( string $label, bool $notFirst ): string {
		return ( $notFirst ? "|-\n" : '' ) . '! ' . $label . "\n";
	}

	public function nonemptyLead(): string {
		return '';
	}

	public function nonemptyHeader( string $label, bool $notFirst ): string {
		return ( $notFirst ? "\n{{!}}-\n" : '' ) . '! ' . $label . "\n";
	}

	public function nonemptySeparator(): string {
		return '{{!}}';
	}

	public function valueCell(): string {
		return '| ';
	}

	public function nonemptyValueCell(): string {
		return '{{!}} ';
	}

	public function aggregationHeader( string $label, bool $hasFields ): string {
		return ( $hasFields ? "|-\n" : '' ) . "! $label\n|";
	}

	public function close(): string {
		return '|}';
	}

	public function trailingNewline(): string {
		return "\n";
	}
}
