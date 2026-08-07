<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFValuesUtils;

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
 *
 * It also centralizes resolution of the 'value_labels' field argument, an
 * optional override mapping that takes precedence over the label derived
 * from 'possible_values' - e.g. a per-field custom label, or (via
 * "mapping using translate") an interface-message-translated label. A
 * possible value's label is overridden when 'value_labels' contains an
 * entry keyed by either its canonical value or its original label.
 */
class PossibleValueList {

	/** @var PossibleValue[] */
	private array $values = [];

	/**
	 * @param array $possibleValues Raw 'possible_values' field argument -
	 *  either a plain list of values, or a canonicalValue => displayLabel map.
	 * @param array|string|null $valueLabels Raw 'value_labels' field
	 *  argument - either a canonicalValue|label => overrideLabel map, a JSON
	 *  string encoding one (as it arrives from a form field definition), or
	 *  null/absent if there is no override.
	 */
	public function __construct( array $possibleValues, $valueLabels = null ) {
		if ( is_string( $valueLabels ) ) {
			$valueLabels = json_decode( $valueLabels, true ) ?? [];
		} elseif ( !is_array( $valueLabels ) ) {
			$valueLabels = [];
		}

		foreach ( $possibleValues as $key => $value ) {
			$canonicalValue = is_string( $key ) ? $key : (string)$value;
			$originalLabel = (string)$value;

			if ( array_key_exists( $canonicalValue, $valueLabels ) ) {
				$this->values[] = new PossibleValue( $canonicalValue, (string)$valueLabels[$canonicalValue], true );
			} elseif ( array_key_exists( $originalLabel, $valueLabels ) ) {
				$this->values[] = new PossibleValue( $canonicalValue, (string)$valueLabels[$originalLabel], true );
			} else {
				$this->values[] = new PossibleValue( $canonicalValue, $originalLabel );
			}
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

	/**
	 * Resolves a clean display label for a raw value that find() did not
	 * match - typically the field's current/preselected value when it falls
	 * outside a truncated 'values from ...' fetch (see #185). For a
	 * page-type value this is the page's DisplayTitle if set, otherwise the
	 * bare canonical title; for anything else (e.g. a plain string value)
	 * $rawValue is already the label.
	 */
	public function resolveMissingLabel( string $rawValue ): string {
		$pageValue = PageValue::newFromText( $rawValue );
		if ( $pageValue === null ) {
			return $rawValue;
		}

		$displayTitles = PFValuesUtils::addDisplayTitlesForPageValues( [ $rawValue ] );
		return $displayTitles[$rawValue] ?? $pageValue->getCanonicalString();
	}

	/**
	 * Resolves $cur_value to its display label(s) for a disabled form input
	 * that has no JS-driven widget of its own to do this substitution
	 * client-side (unlike combobox/tokens) - e.g. a plain text/textarea
	 * input. Used directly by such input classes' getHTML()/getHtmlText()
	 * instead of duplicating the possible_values/property_type handling.
	 *
	 * Resolves via the field's 'possible_values' map when one is configured
	 * (a 'values from ...' list); otherwise, for a Page-type (_wpg)
	 * property, falls back to a direct DisplayTitle lookup on $cur_value -
	 * covering fields with no 'values from ...' clause at all. Anything
	 * else (a plain string field, or a Page-type field with no match and no
	 * resolvable page) is returned unchanged.
	 *
	 * @param string|null $cur_value
	 * @param string|null $delimiter Non-null for a list field.
	 * @param array $other_args Must contain 'possible_values' and/or
	 *  'property_type' to have any effect.
	 * @return string
	 */
	public static function resolveDisabledDisplayValue( $cur_value, $delimiter, array $other_args ) {
		if ( $cur_value === null || $cur_value === '' ) {
			return (string)$cur_value;
		}

		$possibleValues = $other_args['possible_values'] ?? null;
		if ( !is_array( $possibleValues ) || $possibleValues === [] ||
			!is_string( array_key_first( $possibleValues ) )
		) {
			if ( ( $other_args['property_type'] ?? null ) !== '_wpg' ) {
				return $cur_value;
			}
			$possibleValues = [];
		}

		$possibleValueList = new self( $possibleValues, $other_args['value_labels'] ?? null );
		if ( $delimiter === null ) {
			return self::resolveLabelForValue( $cur_value, $possibleValueList );
		}

		$labels = [];
		foreach ( explode( $delimiter, $cur_value ) as $rawValue ) {
			$labels[] = self::resolveLabelForValue( trim( $rawValue ), $possibleValueList );
		}
		return implode( "$delimiter ", $labels );
	}

	private static function resolveLabelForValue( string $rawValue, self $possibleValueList ): string {
		$match = $possibleValueList->find( $rawValue );
		if ( $match !== null ) {
			return $match->getLabel();
		}
		// $rawValue sorted outside the truncated 'values from ...' fetch
		// window (or there was no 'values from ...' list at all) - fall back
		// to a resolved clean label (DisplayTitle or bare title) instead of
		// the raw stored value.
		return $possibleValueList->resolveMissingLabel( $rawValue );
	}
}
