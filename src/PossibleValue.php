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

	private bool $labelIsOverride;

	public function __construct( string $value, string $label, bool $labelIsOverride = false ) {
		$this->value = $value;
		$this->label = $label;
		$this->labelIsOverride = $labelIsOverride;
	}

	public function getValue(): string {
		return $this->value;
	}

	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Whether getLabel() returns a 'value_labels' override rather than the
	 * label derived from the raw 'possible_values' argument.
	 */
	public function labelIsOverride(): bool {
		return $this->labelIsOverride;
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
