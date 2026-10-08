<?php

use MediaWiki\Extension\PageForms\MappingLabels;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\MappingLabels::valueStringToLabels
 * @covers \MediaWiki\Extension\PageForms\MappingLabels::labelToValue
 * @group PF
 */
class MappingLabelsValuesTest extends TestCase {

	private const LABELS = [ 'DE' => 'Germany', 'FR' => 'France', '1' => 'One' ];

	public function testAValueIsTurnedIntoItsLabel(): void {
		$this->assertSame( 'Germany', MappingLabels::valueStringToLabels( self::LABELS, 'DE', null ) );
	}

	public function testAValueWithoutALabelStaysAsItIs(): void {
		$this->assertSame( 'XX', MappingLabels::valueStringToLabels( self::LABELS, 'XX', null ) );
	}

	public function testTheValuesOfAListAreTurnedIntoLabelsAndJoinedWithTheDelimiter(): void {
		$this->assertSame(
			'Germany;France;XX', MappingLabels::valueStringToLabels( self::LABELS, 'DE; FR;XX', ';' )
		);
	}

	public function testEmptyPartsOfAListAreLeftOut(): void {
		$this->assertSame( 'Germany,France', MappingLabels::valueStringToLabels( self::LABELS, 'DE,,FR', ',' ) );
	}

	public function testABlankOrMissingValueIsReturnedAsItIs(): void {
		$this->assertSame( '  ', MappingLabels::valueStringToLabels( self::LABELS, '  ', ',' ) );
		$this->assertNull( MappingLabels::valueStringToLabels( self::LABELS, null, ',' ) );
	}

	public function testWithoutPossibleValuesTheStringIsReturnedAsItIs(): void {
		$this->assertSame( 'DE', MappingLabels::valueStringToLabels( null, 'DE', null ) );
	}

	public function testALabelIsTurnedBackIntoItsValue(): void {
		$this->assertSame( 'FR', MappingLabels::labelToValue( self::LABELS, 'France' ) );
	}

	public function testALabelThatNoValueHasStaysAsItIs(): void {
		$this->assertSame( 'Spain', MappingLabels::labelToValue( self::LABELS, 'Spain' ) );
	}

	public function testANumericValueIsReturnedAsAString(): void {
		$this->assertSame( '1', MappingLabels::labelToValue( self::LABELS, 'One' ) );
	}
}
