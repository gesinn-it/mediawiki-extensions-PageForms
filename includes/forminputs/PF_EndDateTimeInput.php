<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFEndDateTimeInput extends PFDateTimeInput {

	public static function getName(): string {
		return 'end datetime';
	}

	public function getInputClass() {
		return 'dateTimeInput endDateTimeInput';
	}

	// @codeCoverageIgnoreStart

	public static function getDefaultCargoTypes() {
		return [
			'End datetime' => [],
		];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Datetime' ];
	}

	// @codeCoverageIgnoreEnd
}
