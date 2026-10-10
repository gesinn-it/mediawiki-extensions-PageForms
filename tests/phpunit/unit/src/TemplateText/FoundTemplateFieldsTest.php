<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\TemplateField;
use MediaWiki\Extension\PageForms\TemplateText\FoundTemplateFields;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\TemplateText\FoundTemplateFields
 */
class FoundTemplateFieldsTest extends TestCase {

	public function testNothingIsFoundAtFirst() {
		$found = new FoundTemplateFields();

		$this->assertFalse( $found->has( 'Title' ) );
		$this->assertSame( [], $found->fields() );
	}

	public function testAFieldIsKnownByItsNameAndKeptUnderItsKey() {
		$found = new FoundTemplateFields();
		$field = $this->createMock( TemplateField::class );

		$found->add( 'Title', 12, $field );

		$this->assertTrue( $found->has( 'Title' ) );
		$this->assertFalse( $found->has( 'Author' ) );
		$this->assertSame( [ 12 => $field ], $found->fields() );
	}

	public function testTheFieldsKeepTheOrderInWhichTheyWereFound() {
		$found = new FoundTemplateFields();
		$first = $this->createMock( TemplateField::class );
		$second = $this->createMock( TemplateField::class );

		$found->add( 'B', 40, $first );
		$found->add( 'A', 3, $second );

		$this->assertSame( [ 40 => $first, 3 => $second ], $found->fields() );
	}

	public function testNamesThatAreNumbersAreComparedLoosely() {
		$found = new FoundTemplateFields();
		$found->add( '1', 0, $this->createMock( TemplateField::class ) );

		$this->assertTrue( $found->has( '01' ) );
		$this->assertTrue( $found->has( '1.0' ) );
		$this->assertFalse( $found->has( 'one' ) );
	}

	public function testAFieldWithAKeyInUseTakesTheNextFreeOneAndReplacesNone() {
		$found = new FoundTemplateFields();
		$first = $this->createMock( TemplateField::class );
		$second = $this->createMock( TemplateField::class );
		$third = $this->createMock( TemplateField::class );

		$found->add( 'A', 3, $first );
		$found->add( 'B', 3, $second );
		$found->add( 'C', 3, $third );

		$this->assertSame( [ 3 => $first, 4 => $second, 5 => $third ], $found->fields() );
	}

	public function testAFieldWithANameAsItsKeyKeepsIt() {
		$found = new FoundTemplateFields();
		$field = $this->createMock( TemplateField::class );

		$found->add( 'Title', 'Title', $field );

		$this->assertSame( [ 'Title' => $field ], $found->fields() );
	}
}
