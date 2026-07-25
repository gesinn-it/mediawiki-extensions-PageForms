<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * A single entry of a form field's "possible values" list: the value stored
 * in the wikitext when this option is selected, plus the label shown to the
 * user (which may differ, e.g. an SMW DisplayTitle).
 */
class PossibleValue {

	private string $value;

	private string $label;

	public function __construct( string $value, string $label ) {
		$this->value = $value;
		$this->label = $label;
	}

	public function getValue(): string {
		return $this->value;
	}

	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Whether $rawValue (a value as stored in the page, or typed by the
	 * user) refers to this possible value, either directly or via its label.
	 */
	public function matches( string $rawValue ): bool {
		return $rawValue === $this->value || $rawValue === $this->label;
	}

	public function equals( PossibleValue $other ): bool {
		return $this->value === $other->value;
	}
}
