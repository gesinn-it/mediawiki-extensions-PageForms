<?php

use MediaWiki\Extension\PageForms\FormCounters;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormCounters
 * @group PF
 */
class FormCountersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		FormCounters::resetStandalone();
		unset( $GLOBALS['wgPageFormsTabIndex'], $GLOBALS['wgPageFormsFieldNum'] );
	}

	protected function tearDown(): void {
		FormCounters::resetStandalone();
		unset( $GLOBALS['wgPageFormsTabIndex'], $GLOBALS['wgPageFormsFieldNum'] );
		parent::tearDown();
	}

	public function testCurrentOutsideOfARenderIsTheSameStandaloneInstance(): void {
		$first = FormCounters::current();
		$first->fieldNum = 3;

		$this->assertSame( $first, FormCounters::current() );
		$this->assertSame( 3, FormCounters::current()->fieldNum );
	}

	public function testResetStandaloneStartsOverAtTheGivenValues(): void {
		FormCounters::current()->tabIndex = 9;

		FormCounters::resetStandalone( 2, 5 );

		$this->assertSame( 2, FormCounters::current()->fieldNum );
		$this->assertSame( 5, FormCounters::current()->tabIndex );
	}

	public function testBeginMakesCountersCurrentAndEndRestoresTheStandaloneOnes(): void {
		$standalone = FormCounters::current();
		$render = new FormCounters();

		FormCounters::begin( $render );
		$this->assertSame( $render, FormCounters::current() );
		FormCounters::end();

		$this->assertSame( $standalone, FormCounters::current() );
	}

	public function testNestedRendersEachWorkOnTheirOwnCounters(): void {
		$outer = new FormCounters( 1, 1 );
		$inner = new FormCounters();

		FormCounters::begin( $outer );
		FormCounters::begin( $inner );
		FormCounters::current()->fieldNum++;
		FormCounters::end();
		$this->assertSame( $outer, FormCounters::current() );
		FormCounters::end();

		$this->assertSame( 1, $inner->fieldNum );
		$this->assertSame( 1, $outer->fieldNum );
	}

	public function testGlobalsHoldTheValuesOfTheRenderThatFinishedLast(): void {
		$outer = new FormCounters( 7, 8 );
		$inner = new FormCounters( 1, 2 );

		// The inner render finishes first, then the outer one, as in FormPrinter::render().
		$inner->mirrorToGlobals();
		$this->assertSame( 1, $GLOBALS['wgPageFormsFieldNum'] );
		$this->assertSame( 2, $GLOBALS['wgPageFormsTabIndex'] );

		$outer->mirrorToGlobals();
		$this->assertSame( 7, $GLOBALS['wgPageFormsFieldNum'] );
		$this->assertSame( 8, $GLOBALS['wgPageFormsTabIndex'] );
	}
}
