<?php

declare( strict_types=1 );

/**
 * PFMultiEnumInput is abstract; exercised here through PFCheckboxesInput,
 * one of its concrete subclasses, which does not override
 * getOtherPropTypesHandled() or getParameters().
 *
 * @covers PFMultiEnumInput
 * @group Database
 */
class PFMultiEnumInputTest extends MediaWikiIntegrationTestCase {

	public function testGetOtherPropTypesHandledIsEmpty(): void {
		// Overridden to an empty array, unlike the parent PFEnumInput, since
		// multi-value enumerations do not handle any "other" property types.
		$this->assertSame( [], PFMultiEnumInput::getOtherPropTypesHandled() );
	}

	public function testGetOtherPropTypeListsHandledReturnsEnumeration(): void {
		// PFCheckboxesInput overrides this to []; call the base class directly to
		// exercise PFMultiEnumInput's own implementation.
		$this->assertSame( [ 'enumeration' ], PFMultiEnumInput::getOtherPropTypeListsHandled() );
	}

	public function testGetParametersAppendsDelimiterAfterEnumParameters(): void {
		$params = PFCheckboxesInput::getParameters();
		$names = array_column( $params, 'name' );

		$this->assertContains( 'delimiter', $names );
		$this->assertSame( 'delimiter', end( $names ), 'delimiter must be the last parameter' );
	}

	public function testGetParametersIncludesInheritedEnumValuesParameters(): void {
		$params = PFCheckboxesInput::getParameters();
		$names = array_column( $params, 'name' );

		// Inherited from PFEnumInput::getParameters() via PFMultiEnumInput::getParameters().
		$this->assertContains( 'values', $names );
		$this->assertContains( 'show on select', $names );
	}
}
