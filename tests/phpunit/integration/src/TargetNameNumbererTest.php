<?php

use MediaWiki\Extension\PageForms\TargetNameNumberer;

/**
 * @covers \MediaWiki\Extension\PageForms\TargetNameNumberer
 * @group PF
 * @group Database
 */
class TargetNameNumbererTest extends MediaWikiIntegrationTestCase {

	private function number( string $targetName, string $formula = '' ): string {
		return ( new TargetNameNumberer( RequestContext::getMain() ) )->numberTargetName( $targetName, $formula );
	}

	public function testAnotherNameWithoutATagIsLeftAsItIs(): void {
		$this->assertSame( 'TNNoTag_page', $this->number( 'TNNoTag_page' ) );
	}

	public function testATagWithoutAStartValueStartsAtBlank(): void {
		$this->assertSame( 'TNBlank x', $this->number( 'TNBlank_x{num}' ) );
	}

	public function testATagThatIsAloneInTheNameStartsAtOne(): void {
		$this->assertSame( '1', $this->number( '{num}' ) );
		$this->assertSame( '1', $this->number( '_{num}_{num}' ) );
	}

	public function testTheStartValueOfATagIsTheFirstNumber(): void {
		$this->assertSame( 'TNStart 5', $this->number( 'TNStart_{num;start=5}' ) );
	}

	/**
	 * @dataProvider provideStartValuesThatAreNoNumber
	 */
	public function testAStartValueThatIsNoPositiveNumberStartsAtBlank( string $start ): void {
		$this->assertSame( 'TNBadStart', $this->number( "TNBadStart_{num;start=$start}" ) );
	}

	public static function provideStartValuesThatAreNoNumber(): array {
		return [
			'text' => [ 'abc' ],
			'negative' => [ '-3' ],
			'empty' => [ '' ],
		];
	}

	public function testAnExistingPageMakesTheNextNumber(): void {
		$this->insertPage( 'TNTaken_1', 'text' );
		$this->insertPage( 'TNTaken_2', 'text' );

		$this->assertSame( 'TNTaken 3', $this->number( 'TNTaken_{num;start=1}' ) );
	}

	public function testAnExistingPageWithABlankStartMakesNumberTwo(): void {
		$this->insertPage( 'TNBlankTaken_x', 'text' );

		$this->assertSame( 'TNBlankTaken x2', $this->number( 'TNBlankTaken_x{num}' ) );
	}

	public function testTheNextNumberKeepsTheLeadingZerosOfTheStartValue(): void {
		$this->insertPage( 'TNPadded_007', 'text' );

		$this->assertSame( 'TNPadded 008', $this->number( 'TNPadded_{num;start=007}' ) );
	}

	public function testARandomNumberHasUpToSixDigitsByDefault(): void {
		$this->assertSame( 1, preg_match( '/^TNRandom \d{1,6}$/', $this->number( 'TNRandom_{num;random}' ) ) );
	}

	/**
	 * @dataProvider provideRandomNumberTags
	 */
	public function testARandomNumberHasTheDigitsTheTagAsksFor( string $tag, string $pattern ): void {
		$this->assertSame( 1, preg_match( $pattern, $this->number( "TNDigits_$tag" ) ) );
	}

	public static function provideRandomNumberTags(): array {
		return [
			'up to 4 digits' => [ '{num;random;4}', '/^TNDigits \d{1,4}$/' ],
			'padded to 4 digits' => [ '{num;random;04}', '/^TNDigits \d{4}$/' ],
		];
	}

	public function testARandomNumberIsAnotherOneIfThePageExists(): void {
		// With one digit there are ten names, and all but one are taken. After 20 tries that
		// found no free name the numbers get one digit longer, so the free name is the one with
		// the digit 9, or one with two digits.
		foreach ( range( 0, 8 ) as $digit ) {
			$this->insertPage( "TNRandomTaken_$digit", 'text' );
		}

		$this->assertSame( 1, preg_match(
			'/^TNRandomTaken (9|\d\d)$/',
			$this->number( 'TNRandomTaken_{num;random;1}' )
		) );
	}

	public function testANameThatIsNoTitleIsRefusedWithTheFormulaWithoutItsTag(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( wfMessage( 'pf_autoedit_invalidtargetspecified', 'TN[Bad]_' )->parse() );

		$this->number( 'TN[Bad]_{num}', ' TN[Bad]_<unique number> ' );
	}
}
