<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\MappingLabels;
use MediaWikiIntegrationTestCase;
use ReflectionProperty;

/**
 * @covers \MediaWiki\Extension\PageForms\MappingLabels
 * @group PF
 * @group Database
 * @group medium
 */
class MappingLabelsTest extends MediaWikiIntegrationTestCase {

	private function remembered( MappingLabels $labels ): array {
		$property = new ReflectionProperty( MappingLabels::class, 'templateLabels' );
		$property->setAccessible( true );
		return $property->getValue( $labels );
	}

	public function testTemplateLabelsComeFromTheTemplate(): void {
		$this->editPage( 'Template:PFTestMappingLabels01', 'L-{{{1}}}' );

		$labels = ( new MappingLabels() )->forTemplate( 'PFTestMappingLabels01', [ 'a', 'b' ] );

		$this->assertSame( [ 'a' => 'L-a', 'b' => 'L-b' ], $labels );
	}

	public function testAValueWhoseLabelIsEmptyIsItsOwnLabel(): void {
		$this->editPage( 'Template:PFTestMappingLabels02', '' );

		$labels = ( new MappingLabels() )->forTemplate( 'PFTestMappingLabels02', [ 'a' ] );

		$this->assertSame( [ 'a' => 'a' ], $labels );
	}

	public function testATemplateThatDoesNotExistLeavesTheValues(): void {
		$labels = ( new MappingLabels() )->forTemplate( 'PFTestMappingLabelsMissing', [ 'a', 'b' ] );

		$this->assertSame( [ 'a' => 'a', 'b' => 'b' ], $labels );
	}

	public function testNumericValuesAreKeptAsKeys(): void {
		$this->editPage( 'Template:PFTestMappingLabels03', '#{{{1}}}' );

		$labels = ( new MappingLabels() )->forTemplate( 'PFTestMappingLabels03', [ 1, 2 ] );

		$this->assertSame( [ 1 => '#1', 2 => '#2' ], $labels );
	}

	public function testWhatIsRememberedBelongsToTheObject(): void {
		$this->editPage( 'Template:PFTestMappingLabels04', 'L-{{{1}}}' );
		$first = new MappingLabels();
		$second = new MappingLabels();

		$first->forTemplate( 'PFTestMappingLabels04', [ 'a', 'b' ] );
		// Asking again adds nothing: every label is already there.
		$first->forTemplate( 'PFTestMappingLabels04', [ 'a', 'b' ] );

		$this->assertCount( 2, $this->remembered( $first ) );
		$this->assertSame( [], $this->remembered( $second ) );
	}

	public function testAnEditedTemplateIsReadAgain(): void {
		$this->editPage( 'Template:PFTestMappingLabels05', 'First' );
		$labels = new MappingLabels();
		$this->assertSame( [ 'a' => 'First' ], $labels->forTemplate( 'PFTestMappingLabels05', [ 'a' ] ) );

		$this->editPage( 'Template:PFTestMappingLabels05', 'Second' );

		$this->assertSame( [ 'a' => 'Second' ], $labels->forTemplate( 'PFTestMappingLabels05', [ 'a' ] ) );
	}

	public function testValuesWithMarkupAndPlainValuesEachGetTheirOwnLabel(): void {
		$this->editPage( 'Template:PFTestMappingLabels06', 'L-{{{1}}}' );
		$values = [ '{{{x}}}', '[[A]]' ];
		for ( $i = 1; $i <= 250; $i++ ) {
			$values[] = "v$i";
		}

		$labels = ( new MappingLabels() )->forTemplate( 'PFTestMappingLabels06', $values );

		$this->assertCount( 252, $labels );
		$this->assertSame( 'L-v1', $labels['v1'] );
		$this->assertSame( 'L-v137', $labels['v137'] );
		$this->assertSame( 'L-v250', $labels['v250'] );
	}

	public function testPropertyLabelsComeFromTheFirstValueOfTheProperty(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$item = $this->createMock( \SMWDataItem::class );
		$item->method( 'getSortKey' )->willReturn( 'MyLabel' );
		$store = $this->createMock( \SMW\Store::class );
		$store->method( 'getPropertyValues' )->willReturn( [ $item ] );

		$labels = ( new MappingLabels() )->forProperty( $store, 'MappingProp', [ 'PageA' ] );

		$this->assertSame( [ 'PageA' => 'MyLabel' ], $labels );
	}

	public function testAValueWithoutAPropertyValueIsItsOwnLabel(): void {
		if ( !class_exists( '\SMW\Store' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$store = $this->createMock( \SMW\Store::class );
		$store->method( 'getPropertyValues' )->willReturn( [] );

		$labels = ( new MappingLabels() )->forProperty( $store, 'MappingProp', [ 'PageA' ] );

		$this->assertSame( [ 'PageA' => 'PageA' ], $labels );
	}
}
