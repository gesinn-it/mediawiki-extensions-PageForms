<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\PossibleValueList;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\PossibleValueList
 * @covers MediaWiki\Extension\PageForms\PossibleValue
 */
class PossibleValueListTest extends TestCase {

	public function testPlainListValueAndLabelAreTheSame(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ] );

		$match = $list->find( 'Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Foo', $match->getValue() );
		$this->assertSame( 'Foo', $match->getLabel() );
	}

	public function testCanonicalValueLabelMapFindsByValue(): void {
		$list = new PossibleValueList( [ 'Category:Foo' => 'Foo (DisplayTitle)' ] );

		$match = $list->find( 'Category:Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Category:Foo', $match->getValue() );
		$this->assertSame( 'Foo (DisplayTitle)', $match->getLabel() );
	}

	public function testCanonicalValueLabelMapFindsByLabel(): void {
		$list = new PossibleValueList( [ 'Category:Foo' => 'Foo (DisplayTitle)' ] );

		$match = $list->find( 'Foo (DisplayTitle)' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Category:Foo', $match->getValue() );
	}

	public function testAmbiguousLabelDoesNotResolve(): void {
		$list = new PossibleValueList( [
			'Category:Foo' => 'Duplicate Label',
			'Category:Bar' => 'Duplicate Label',
		] );

		$this->assertNull( $list->find( 'Duplicate Label' ) );
	}

	public function testUnknownValueReturnsNull(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ] );

		$this->assertNull( $list->find( 'Unknown' ) );
		$this->assertFalse( $list->contains( 'Unknown' ) );
	}

	public function testContainsTrueForKnownValue(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ] );

		$this->assertTrue( $list->contains( 'Foo' ) );
	}

	public function testEmptyListIsEmpty(): void {
		$list = new PossibleValueList( [] );

		$this->assertTrue( $list->isEmpty() );
		$this->assertSame( 0, $list->count() );
		$this->assertSame( [], $list->all() );
	}

	public function testCountAndAllReflectConstructorInput(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar', 'Baz' ] );

		$this->assertSame( 3, $list->count() );
		$this->assertFalse( $list->isEmpty() );
	}

	public function testValueEqualsComparesCanonicalValueOnly(): void {
		$list = new PossibleValueList( [ 'Category:Foo' => 'Foo (DisplayTitle)' ] );
		$other = new PossibleValueList( [ 'Category:Foo' => 'Different Label' ] );

		$a = $list->find( 'Category:Foo' );
		$b = $other->find( 'Category:Foo' );

		$this->assertTrue( $a->equals( $b ) );
	}
}
