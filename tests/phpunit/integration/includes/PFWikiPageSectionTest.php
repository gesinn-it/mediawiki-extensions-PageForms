<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers PFWikiPageSection
 */
class PFWikiPageSectionTest extends TestCase {

	private function make( array $opts = [] ): PFWikiPageSection {
		return new PFWikiPageSection(
			$opts['name'] ?? 'Background',
			$opts['level'] ?? 2,
			$opts['text'] ?? 'Some content',
			$opts['options'] ?? [ 'hideIfEmpty' => false ]
		);
	}

	public function testGetHeader() {
		$this->assertSame( 'Background', $this->make()->getHeader() );
	}

	public function testGetHeaderLevel() {
		$this->assertSame( 2, $this->make()->getHeaderLevel() );
	}

	public function testGetText() {
		$this->assertSame( 'Some content', $this->make()->getText() );
	}

	public function testIsHideIfEmptyFalse() {
		$this->assertFalse( $this->make()->isHideIfEmpty() );
	}

	public function testIsHideIfEmptyTrue() {
		$s = $this->make( [ 'options' => [ 'hideIfEmpty' => true ] ] );
		$this->assertTrue( $s->isHideIfEmpty() );
	}

	public function testCustomHeaderLevel() {
		$s = $this->make( [ 'level' => 3 ] );
		$this->assertSame( 3, $s->getHeaderLevel() );
	}

	public function testEmptyText() {
		$s = $this->make( [ 'text' => '' ] );
		$this->assertSame( '', $s->getText() );
	}
}
