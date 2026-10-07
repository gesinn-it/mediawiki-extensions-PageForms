<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\RestrictedInputs;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\RestrictedInputs
 */
class RestrictedInputsTest extends TestCase {

	public function testNothingRestrictedLeavesTheRequestAlone(): void {
		$restricted = new RestrictedInputs();
		$options = [ 'Tpl' => [ 'a' => '1' ], 'pf_free_text' => 't' ];

		$this->assertTrue( $restricted->isEmpty() );
		$this->assertSame( $options, $restricted->removeFrom( $options ) );
	}

	public function testRestrictedFieldIsRemovedWithItsModifiersAndMarker(): void {
		$restricted = new RestrictedInputs();
		$restricted->addField( 'Tpl', 'a' );

		$options = [ 'Tpl' => [
			'a' => '1', 'a+' => '2', 'a-' => '3', 'b' => '4', 'map_field' => [ 'a' => 'true', 'b' => 'true' ],
		] ];

		$this->assertFalse( $restricted->isEmpty() );
		$this->assertSame(
			[ 'Tpl' => [ 'b' => '4', 'map_field' => [ 'b' => 'true' ] ] ],
			$restricted->removeFrom( $options )
		);
	}

	public function testRestrictedFieldIsRemovedFromEveryInstance(): void {
		$restricted = new RestrictedInputs();
		$restricted->addField( 'Tpl', 'a' );

		$options = [ 'Tpl' => [
			'0a' => [ 'a' => '1', 'b' => '2' ],
			'new' => [ 'a' => '3' ],
		] ];

		$this->assertSame(
			[ 'Tpl' => [ '0a' => [ 'b' => '2' ], 'new' => [] ] ],
			$restricted->removeFrom( $options )
		);
	}

	public function testOtherTemplatesAndMissingTemplatesAreUntouched(): void {
		$restricted = new RestrictedInputs();
		$restricted->addField( 'Tpl', 'a' );
		$restricted->addField( 'Absent', 'a' );

		$options = [ 'Other' => [ 'a' => '1' ] ];

		$this->assertSame( $options, $restricted->removeFrom( $options ) );
	}

	public function testRestrictedSectionAndFreeText(): void {
		$restricted = new RestrictedInputs();
		$restricted->addSection( 'Locked' );
		$restricted->restrictFreeText();

		$options = [
			'_section' => [ 'Locked' => 'x', 'Open' => 'y' ],
			'pf_free_text' => 'text',
			'Tpl' => [ 'a' => '1' ],
		];

		$this->assertSame(
			[ '_section' => [ 'Open' => 'y' ], 'Tpl' => [ 'a' => '1' ] ],
			$restricted->removeFrom( $options )
		);
	}
}
