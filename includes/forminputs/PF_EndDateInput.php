<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFEndDateInput extends PFDateInput {

	public static function getName(): string {
		return 'end date';
	}

	// @codeCoverageIgnoreStart

	public static function getDefaultCargoTypes() {
		return [
			'End date' => [],
		];
	}

	// @codeCoverageIgnoreEnd

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_dat' ];
	}

	// @codeCoverageIgnoreStart

	public static function getOtherCargoTypesHandled() {
		return [ 'Date' ];
	}

	// @codeCoverageIgnoreEnd

	public function getInputClass() {
		return 'dateInput endDateInput';
	}
}
