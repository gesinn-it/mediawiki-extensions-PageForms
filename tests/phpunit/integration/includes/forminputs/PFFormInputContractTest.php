<?php

declare( strict_types=1 );

/**
 * Contract tests for all concrete PFFormInput subclasses.
 *
 * Verifies the static API contract declared by PFFormInput:
 *   - getName()              returns a non-empty string
 *   - getDefaultPropTypes()  returns an array whose values are arrays
 *   - getOtherPropTypesHandled() returns an array of strings
 *   - getParameters()        returns an array containing the four base keys
 *   - canHandleLists()       returns a bool
 *
 * Additionally asserts uniqueness invariants across all registered types:
 *   - no two classes return the same getName() value
 *   - no SMW property type is claimed as default by more than one class
 *
 * @group PF
 * @group Database
 * @covers PFFormInput
 */
class PFFormInputContractTest extends MediaWikiIntegrationTestCase {

	// -----------------------------------------------------------------------
	// Data provider — all concrete (non-abstract) PFFormInput subclasses
	// -----------------------------------------------------------------------

	public static function allInputTypes(): array {
		return [
			[ 'PFCheckboxInput' ],
			[ 'PFCheckboxesInput' ],
			[ 'PFComboBoxInput' ],
			[ 'PFDateInput' ],
			[ 'PFDatePickerInput' ],
			[ 'PFDateTimeInput' ],
			[ 'PFDateTimePicker' ],
			[ 'PFDropdownInput' ],
			[ 'PFEndDateInput' ],
			[ 'PFEndDateTimeInput' ],
			[ 'PFGoogleMapsInput' ],
			[ 'PFLeafletInput' ],
			[ 'PFListBoxInput' ],
			[ 'PFOpenLayersInput' ],
			[ 'PFRadioButtonInput' ],
			[ 'PFRatingInput' ],
			[ 'PFRegExpInput' ],
			[ 'PFSFSelectInput' ],
			[ 'PFStartDateInput' ],
			[ 'PFStartDateTimeInput' ],
			[ 'PFTextAreaInput' ],
			[ 'PFTextAreaWithAutocompleteInput' ],
			[ 'PFTextInput' ],
			[ 'PFTextWithAutocompleteInput' ],
			[ 'PFTimePickerInput' ],
			[ 'PFTokensInput' ],
			[ 'PFTreeInput' ],
			[ 'PFYearInput' ],
		];
	}

	// -----------------------------------------------------------------------
	// getName()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider allInputTypes
	 */
	public function testGetNameReturnsNonEmptyString( string $class ): void {
		$name = $class::getName();
		$this->assertIsString( $name, "$class::getName() must return a string" );
		$this->assertNotSame( '', $name, "$class::getName() must not return an empty string" );
	}

	// -----------------------------------------------------------------------
	// getDefaultPropTypes()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider allInputTypes
	 */
	public function testGetDefaultPropTypesReturnsArray( string $class ): void {
		$this->assertIsArray(
			$class::getDefaultPropTypes(),
			"$class::getDefaultPropTypes() must return an array"
		);
	}

	/**
	 * @dataProvider allInputTypes
	 */
	public function testGetDefaultPropTypesValuesAreArrays( string $class ): void {
		$propTypes = $class::getDefaultPropTypes();
		// Guarantee at least one assertion even when no default prop types are declared.
		$this->assertIsArray( $propTypes, "$class::getDefaultPropTypes() must return an array" );
		foreach ( $propTypes as $propType => $args ) {
			$this->assertIsArray(
				$args,
				"$class::getDefaultPropTypes(): value for '$propType' must be an array"
			);
		}
	}

	// -----------------------------------------------------------------------
	// getOtherPropTypesHandled()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider allInputTypes
	 */
	public function testGetOtherPropTypesHandledReturnsArray( string $class ): void {
		$this->assertIsArray(
			$class::getOtherPropTypesHandled(),
			"$class::getOtherPropTypesHandled() must return an array"
		);
	}

	/**
	 * @dataProvider allInputTypes
	 */
	public function testGetOtherPropTypesHandledValuesAreStrings( string $class ): void {
		$propTypes = $class::getOtherPropTypesHandled();
		// Guarantee at least one assertion even when no other prop types are declared.
		$this->assertIsArray( $propTypes, "$class::getOtherPropTypesHandled() must return an array" );
		foreach ( $propTypes as $idx => $propType ) {
			$this->assertIsString(
				$propType,
				"$class::getOtherPropTypesHandled(): entry at index $idx must be a string"
			);
		}
	}

	/**
	 * A property type must not appear in both getDefaultPropTypes() and
	 * getOtherPropTypesHandled() of the same class — that would be contradictory.
	 *
	 * @dataProvider allInputTypes
	 */
	public function testNoOverlapBetweenDefaultAndOtherPropTypes( string $class ): void {
		$defaultKeys = array_keys( $class::getDefaultPropTypes() );
		$otherValues = $class::getOtherPropTypesHandled();
		$overlap = array_intersect( $defaultKeys, $otherValues );
		$this->assertSame(
			[],
			array_values( $overlap ),
			"$class: property types appear in both getDefaultPropTypes() and " .
			"getOtherPropTypesHandled(): " . implode( ', ', $overlap )
		);
	}

	// -----------------------------------------------------------------------
	// getParameters()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider allInputTypes
	 */
	public function testGetParametersReturnsArray( string $class ): void {
		$this->assertIsArray(
			$class::getParameters(),
			"$class::getParameters() must return an array"
		);
	}

	/**
	 * Every input must expose the four base parameters inherited from PFFormInput.
	 *
	 * @dataProvider allInputTypes
	 */
	public function testGetParametersContainsBaseKeys( string $class ): void {
		$params = $class::getParameters();
		foreach ( [ 'mandatory', 'restricted', 'class', 'default' ] as $key ) {
			$this->assertArrayHasKey(
				$key,
				$params,
				"$class::getParameters() must contain base key '$key'"
			);
		}
	}

	// -----------------------------------------------------------------------
	// canHandleLists()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider allInputTypes
	 */
	public function testCanHandleListsReturnsBool( string $class ): void {
		$this->assertIsBool(
			$class::canHandleLists(),
			"$class::canHandleLists() must return a bool"
		);
	}

	// -----------------------------------------------------------------------
	// Global uniqueness invariants (no @dataProvider — run once across all)
	// -----------------------------------------------------------------------

	public function testGetNameIsUniqueAcrossAllTypes(): void {
		$names = [];
		foreach ( self::allInputTypes() as [ $class ] ) {
			$name = $class::getName();
			$existing = $names[$name] ?? null;
			$this->assertArrayNotHasKey(
				$name,
				$names,
				"Duplicate getName() value '$name' returned by both $existing and $class"
			);
			$names[$name] = $class;
		}
	}

	public function testNoTwoClassesClaimSameDefaultPropType(): void {
		$claimedBy = [];
		foreach ( self::allInputTypes() as [ $class ] ) {
			foreach ( array_keys( $class::getDefaultPropTypes() ) as $propType ) {
				$existing = $claimedBy[$propType] ?? null;
				$this->assertArrayNotHasKey(
					$propType,
					$claimedBy,
					"Property type '$propType' is claimed as default by both $existing and $class"
				);
				$claimedBy[$propType] = $class;
			}
		}
	}
}
