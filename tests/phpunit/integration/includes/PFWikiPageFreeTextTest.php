<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers PFWikiPageFreeText
 */
class PFWikiPageFreeTextTest extends TestCase {

	public function testSetAndGetText() {
		$obj = new PFWikiPageFreeText();
		$obj->setText( 'Hello world' );
		$this->assertSame( 'Hello world', $obj->getText() );
	}

	public function testGetTextReturnsNullByDefault() {
		$obj = new PFWikiPageFreeText();
		$this->assertNull( $obj->getText() );
	}

	public function testSetTextOverwritesPreviousValue() {
		$obj = new PFWikiPageFreeText();
		$obj->setText( 'first' );
		$obj->setText( 'second' );
		$this->assertSame( 'second', $obj->getText() );
	}

	public function testSetTextAcceptsEmptyString() {
		$obj = new PFWikiPageFreeText();
		$obj->setText( '' );
		$this->assertSame( '', $obj->getText() );
	}
}
