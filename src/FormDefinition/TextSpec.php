<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * The text between two tags of a form definition, kept verbatim.
 */
class TextSpec implements FormElement {

	public const TYPE = 'text';

	public function __construct(
		private string $text
	) {
	}

	public function getText(): string {
		return $this->text;
	}

	public function toWikitext(): string {
		return $this->text;
	}

	public function toArray(): array {
		return [ 'type' => self::TYPE, 'text' => $this->text ];
	}
}
