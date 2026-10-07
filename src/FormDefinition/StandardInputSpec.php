<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * A {{{standard input|...}}} tag: free text, save, preview, changes, cancel, summary,
 * minor edit, watch or run query.
 */
class StandardInputSpec extends TagSpec {

	public const TYPE = 'standard input';

	public const FREE_TEXT = 'free text';

	public function getInputName(): string {
		return trim( $this->getRawName() );
	}

	public function isFreeText(): bool {
		return $this->getInputName() === self::FREE_TEXT;
	}
}
