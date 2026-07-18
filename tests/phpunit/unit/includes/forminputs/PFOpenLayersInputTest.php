<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PFOpenLayersInput::coordinatePartToNumber().
 *
 * @group PF
 * @covers PFOpenLayersInput::coordinatePartToNumber
 */
class PFOpenLayersInputTest extends TestCase {

	/**
	 * Decimal seconds must be parsed in full, not truncated to their
	 * integer part.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/25
	 */
	public function testCoordinatePartToNumberWithDecimalSeconds() {
		$result = PFOpenLayersInput::coordinatePartToNumber( '48°8\'21.45"N' );

		$expected = 48 + ( 8 / 60 ) + ( 21.45 / 3600 );
		$this->assertEqualsWithDelta( $expected, $result, 0.0000001 );
	}

	public function testCoordinatePartToNumberWithIntegerSeconds() {
		$result = PFOpenLayersInput::coordinatePartToNumber( '48°8\'21"N' );

		$expected = 48 + ( 8 / 60 ) + ( 21 / 3600 );
		$this->assertEqualsWithDelta( $expected, $result, 0.0000001 );
	}

	public function testCoordinatePartToNumberWithoutSeconds() {
		$result = PFOpenLayersInput::coordinatePartToNumber( '48°8\'N' );

		$expected = 48 + ( 8 / 60 );
		$this->assertEqualsWithDelta( $expected, $result, 0.0000001 );
	}
}
