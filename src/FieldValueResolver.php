<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFFormField;
use PFFormUtils;
use User;

/**
 * Pure value-transformation helpers for field values within a PageForms form render.
 *
 * Both methods are stateless — they take values in and return resolved values out,
 * with no dependency on form or template state beyond their parameters.
 */
class FieldValueResolver {

	/**
	 * Applies a val-modifier ('+' or '-') to produce the resolved field value.
	 *
	 * The '+' modifier appends $curValue to $pageValue (with $delimiter) unless
	 * $curValue is already present in $pageValue. The '-' modifier removes the
	 * comma-separated entries in $curValue from the delimited list in $pageValue.
	 *
	 * @param string $curValue The new value from the form or query
	 * @param string $modifier '+' or '-'
	 * @param string $pageValue The current value stored on the page
	 * @param string $delimiter List delimiter (e.g. ',')
	 * @return string Resolved value after applying the modifier
	 */
	public function applyValModifier(
		string $curValue,
		string $modifier,
		string $pageValue,
		string $delimiter
	): string {
		if ( $modifier === '+' ) {
			if ( preg_match( "#(,|\^)\s*$curValue\s*(,|\$)#", $pageValue ) === 0 ) {
				if ( trim( $pageValue ) !== '' ) {
					// if page_value is empty, simply don't do anything, because then cur_value
					// is already the value it has to be (no delimiter needed).
					return $pageValue . $delimiter . $curValue;
				}
			} else {
				return $pageValue;
			}
		} elseif ( $modifier === '-' ) {
			// get an array of elements to remove:
			$remove = array_map( 'trim', explode( ',', $curValue ) );
			// process the current value:
			$valArray = array_map( 'trim', explode( $delimiter, $pageValue ) );
			// remove element(s) from list
			foreach ( $remove as $rmv ) {
				$key = array_search( $rmv, $valArray );
				if ( $key !== false ) {
					unset( $valArray[$key] );
				}
			}
			$curValue = implode( $delimiter, $valArray );
			if ( $curValue === '' ) {
				// HACK: setting an empty string prevents anything from happening at all.
				// set a dummy string that evaluates to an empty string
				$curValue = '{{subst:lc: }}';
			}
		}
		return $curValue;
	}

	/**
	 * Resolves special default-value tokens ('now', 'current user', 'uuid') into
	 * concrete values and returns the updated [$curValue, $curValueInTemplate] pair.
	 *
	 * When the default is 'uuid' and the template allows multiple instances, no
	 * concrete value is computed here — instead a 'new-uuid' CSS class is added to
	 * $formField so that the JavaScript can generate a UUID per instance.
	 *
	 * @param PFFormField $formField The form field being rendered (mutated for uuid/multiple)
	 * @param string $curValue Current field value (empty or equal to the token when unresolved)
	 * @param string $curValueInTemplate Current template value (may already carry a concrete value)
	 * @param bool $allowsMultiple Whether the enclosing template allows multiple instances
	 * @param User $user Current user (used to resolve 'current user')
	 * @return array{0: string, 1: string} [$curValue, $curValueInTemplate] after resolution
	 */
	public function resolveDefaultValue(
		PFFormField $formField,
		string $curValue,
		string $curValueInTemplate,
		bool $allowsMultiple,
		User $user
	): array {
		$defaultValue = $formField->getDefaultValue();

		if ( $defaultValue == 'now' && ( $curValue == '' || $curValue == 'now' ) ) {
			$inputType = $formField->getInputType();
			// 'datepicker' and 'datetimepicker' handle 'now' themselves; skip them here.
			if ( $inputType == 'date' || $inputType == 'datetime' || $inputType == 'year' ||
				( $inputType == '' &&
					$formField->getTemplateField()->getPropertyType() == '_dat' )
			) {
				$curValueInTemplate = PFFormUtils::getStringForCurrentTime(
					$inputType == 'datetime', $formField->hasFieldArg( 'include timezone' )
				);
			}
		} elseif ( $defaultValue == 'current user' &&
			( $curValue === '' || $curValue == 'current user' )
		) {
			$curValueInTemplate = $user->isRegistered() ? $user->getName() : '';
			$curValue = $curValueInTemplate;
		} elseif ( $defaultValue == 'uuid' && ( $curValue == '' || $curValue == 'uuid' ) ) {
			if ( $allowsMultiple ) {
				// UUID will be generated per-instance by the JavaScript.
				$formField->setFieldArg( 'class', 'new-uuid' );
			} else {
				$curValue = $curValueInTemplate = PFFormUtils::generateUUID();
			}
		}

		return [ $curValue, $curValueInTemplate ];
	}
}
