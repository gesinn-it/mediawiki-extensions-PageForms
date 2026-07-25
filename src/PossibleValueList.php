<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * The set of possible values for a selection-based form input (dropdown,
 * radiobutton, checkboxes, tokens, combobox), built once from the raw
 * 'possible_values' field argument.
 *
 * That raw argument has historically had two shapes:
 * - a plain list of values, e.g. [ 'Foo', 'Bar' ]
 * - a canonicalValue => displayLabel map, e.g. [ 'Foo' => 'Foo (DisplayTitle)' ]
 * (numeric keys in the first case, string keys in the second.) This class
 * normalizes both into a list of PossibleValue objects and centralizes the
 * value/label lookups that were previously duplicated across the five input
 * widget classes.
 */
class PossibleValueList {

	/** @var PossibleValue[] */
	private array $values = [];

	/**
	 * @param array $possibleValues Raw 'possible_values' field argument -
	 *  either a plain list of values, or a canonicalValue => displayLabel map.
	 */
	public function __construct( array $possibleValues ) {
		foreach ( $possibleValues as $key => $value ) {
			$canonicalValue = is_string( $key ) ? $key : (string)$value;
			$this->values[] = new PossibleValue( $canonicalValue, (string)$value );
		}
	}

	/**
	 * @return PossibleValue[]
	 */
	public function all(): array {
		return $this->values;
	}

	public function isEmpty(): bool {
		return $this->values === [];
	}

	public function count(): int {
		return count( $this->values );
	}

	/**
	 * Finds the possible value that $rawValue (as stored in the page, or
	 * typed by the user) refers to - either directly, via its label, or (for
	 * page-type values) via a legacy localized namespace prefix that a form
	 * save could have persisted before the fix for #175, e.g. "Kategorie:Foo"
	 * on a German wiki referring to the same page as the canonically-prefixed
	 * "Category:Foo" possible value (see #178).
	 * Returns null if $rawValue is null, or does not match any known
	 * possible value - mirroring array_search()'s tolerance of a null
	 * needle, since callers pass field values that are not always
	 * guaranteed to be strings.
	 */
	public function find( ?string $rawValue ): ?PossibleValue {
		if ( $rawValue === null ) {
			return null;
		}

		$labelMatch = null;
		$labelMatchCount = 0;

		foreach ( $this->values as $possibleValue ) {
			if ( $rawValue === $possibleValue->getValue() ) {
				return $possibleValue;
			}
			if ( $rawValue === $possibleValue->getLabel() ) {
				$labelMatchCount++;
				$labelMatch = $possibleValue;
			}
		}

		$rawPageValue = PageValue::newFromText( $rawValue );
		if ( $rawPageValue !== null ) {
			foreach ( $this->values as $possibleValue ) {
				$possibleValuePage = PageValue::newFromText( $possibleValue->getValue() );
				if ( $possibleValuePage !== null && $rawPageValue->equals( $possibleValuePage ) ) {
					return $possibleValue;
				}
			}
		}

		// Only resolve a bare label to its canonical value when that label is
		// unambiguous - if several possible values share the same label,
		// there is no single canonical value to prefer.
		return $labelMatchCount === 1 ? $labelMatch : null;
	}

	public function contains( ?string $rawValue ): bool {
		return $this->find( $rawValue ) !== null;
	}
}
