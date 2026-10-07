<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormValues;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormValues
 */
class FormValuesTest extends TestCase {

	public function testNoValuesGiveNoOptions(): void {
		$this->assertSame( [], ( new FormValues() )->toOptions() );
	}

	public function testSingleInstanceFieldsAreKeyedByTemplateAndField(): void {
		$values = new FormValues();
		$values->setFieldValue( 'Tpl', null, 'a', '1' );
		$values->setFieldValue( 'Tpl', null, 'b', '2' );

		$this->assertSame( [ 'Tpl' => [ 'a' => '1', 'b' => '2' ] ], $values->toOptions() );
	}

	public function testMultipleInstancesAreKeyedByInstance(): void {
		$values = new FormValues();
		$values->addInstance( 'Tpl', '0a' );
		$values->addInstance( 'Tpl', '1a' );
		$values->setFieldValue( 'Tpl', '1a', 'a', 'x' );

		$this->assertSame( [ 'Tpl' => [ '0a' => [], '1a' => [ 'a' => 'x' ] ] ], $values->toOptions() );
	}

	public function testUnhandledParametersAndFreeText(): void {
		$values = new FormValues();
		$values->setUnhandled( 'Tpl', 'my param', 'v' );
		$values->setFreeText( 'text' );

		$this->assertSame( [ '_unhandled_Tpl_my+param' => 'v', 'pf_free_text' => 'text' ], $values->toOptions() );
		$this->assertTrue( $values->hasUnhandled( 'Tpl', 'my param' ) );
		$this->assertFalse( $values->hasUnhandled( 'Tpl', 'other' ) );
	}

	public function testRequestWinsOverPageValues(): void {
		$values = new FormValues();
		$values->setFieldValue( 'Tpl', null, 'a', 'page' );
		$values->setFieldValue( 'Tpl', null, 'b', 'page' );
		$values->setFreeText( 'page text' );

		$merged = $values->mergeRequest( [ 'Tpl' => [ 'a' => 'request' ], 'pf_free_text' => 'new' ] );

		$this->assertSame( [ 'Tpl' => [ 'a' => 'request', 'b' => 'page' ], 'pf_free_text' => 'new' ], $merged );
	}

	public function testRequestIsKeptWhenThePageHasNoValues(): void {
		$this->assertSame(
			[ 'Other' => [ 'x' => '1' ] ],
			( new FormValues() )->mergeRequest( [ 'Other' => [ 'x' => '1' ] ] )
		);
	}

	public function testRequestValueOfAMappedFieldIsMarkedForMappingBack(): void {
		$values = new FormValues();
		$values->setFieldValue( 'Tpl', null, 'm', 'b' );
		$values->setMappedFields( 'Tpl', [ 'm', 'n' ] );

		$merged = $values->mergeRequest( [ 'Tpl' => [ 'm' => 'Label c' ] ] );

		$this->assertSame( [ 'Tpl' => [ 'm' => 'Label c', 'map_field' => [ 'm' => 'true' ] ] ], $merged );
	}

	public function testMappedFieldTheRequestDoesNotCarryIsNotMarked(): void {
		$values = new FormValues();
		$values->setFieldValue( 'Tpl', null, 'm', 'b' );
		$values->setMappedFields( 'Tpl', [ 'm' ] );

		$merged = $values->mergeRequest( [ 'Tpl' => [ 'o' => '2' ] ] );

		$this->assertSame( [ 'Tpl' => [ 'm' => 'b', 'o' => '2' ] ], $merged );
	}

	public function testMarkerFromTheRequestIsLeftAlone(): void {
		$values = new FormValues();
		$values->setMappedFields( 'Tpl', [ 'm' ] );

		$merged = $values->mergeRequest( [ 'Tpl' => [ 'm' => 'x', 'map_field' => [ 'm' => 'yes' ] ] ] );

		$this->assertSame( 'yes', $merged['Tpl']['map_field']['m'] );
	}

	public function testMappedFieldsOfEveryInstanceInTheRequestAreMarked(): void {
		$values = new FormValues();
		$values->setMappedFields( 'Tpl', [ 'm' ] );

		$merged = $values->mergeRequest( [ 'Tpl' => [ '0a' => [ 'm' => 'L1' ], 'new' => [ 'o' => '1' ] ] ] );

		$this->assertSame( [ 'm' => 'true' ], $merged['Tpl']['0a']['map_field'] );
		$this->assertArrayNotHasKey( 'map_field', $merged['Tpl']['new'] );
	}

	public function testRequestWithoutTheMappedTemplateIsLeftAlone(): void {
		$values = new FormValues();
		$values->setMappedFields( 'Tpl', [ 'm' ] );

		$this->assertSame( [ 'x' => '1' ], $values->mergeRequest( [ 'x' => '1' ] ) );
	}

	public function testUnhandledParametersOfMultipleInstancesStayWithTheirInstance(): void {
		$values = new FormValues();
		$values->addInstance( 'Tpl', '0a' );
		$values->addInstance( 'Tpl', '1a' );
		$values->setFieldValue( 'Tpl', '1a', 'a', 'x' );
		$values->setUnhandled( 'Tpl', 'my param', 'v', '1a' );

		$this->assertSame(
			[ 'Tpl' => [ '0a' => [], '1a' => [ 'a' => 'x', '_unhandled' => [ 'my+param' => 'v' ] ] ] ],
			$values->toOptions()
		);
	}
}
