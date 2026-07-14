<?php

declare( strict_types=1 );

/**
 * PFEnumInput is abstract; exercised here through PFDropdownInput, one of
 * its concrete subclasses, which does not override getValuesParameters()
 * or getParameters().
 *
 * @covers PFEnumInput
 * @group Database
 */
class PFEnumInputTest extends MediaWikiIntegrationTestCase {

	public function testGetOtherPropTypesHandledReturnsEnumerationAndBoolean(): void {
		$this->assertSame( [ 'enumeration', '_boo' ], PFEnumInput::getOtherPropTypesHandled() );
	}

	public function testGetValuesParametersAlwaysIncludesValues(): void {
		$names = array_column( PFDropdownInput::getValuesParameters(), 'name' );

		$this->assertContains( 'values', $names );
		$this->assertContains( 'values from category', $names );
		$this->assertContains( 'values from namespace', $names );
		$this->assertContains( 'values from wikidata', $names );
	}

	public function testGetValuesParametersIncludesSmwParametersOnlyWhenSmwIsInstalled(): void {
		$names = array_column( PFDropdownInput::getValuesParameters(), 'name' );

		if ( defined( 'SMW_VERSION' ) ) {
			$this->assertContains( 'values from property', $names );
			$this->assertContains( 'values from concept', $names );
		} else {
			$this->assertNotContains( 'values from property', $names );
			$this->assertNotContains( 'values from concept', $names );
		}
	}

	public function testGetParametersAppendsShowOnSelectAfterValuesParameters(): void {
		$params = PFDropdownInput::getParameters();
		$names = array_column( $params, 'name' );

		$this->assertContains( 'show on select', $names );
		$this->assertSame( 'show on select', end( $names ), 'show on select must be the last parameter' );
	}

	public function testGetParametersIncludesBothBaseAndValuesParameters(): void {
		$params = PFDropdownInput::getParameters();
		$names = array_column( $params, 'name' );

		// Base parameters, inherited via PFFormInput::getParameters().
		$this->assertContains( 'mandatory', $names );
		$this->assertContains( 'class', $names );
		// Values parameters, added by PFEnumInput::getParameters().
		$this->assertContains( 'values', $names );
	}
}
