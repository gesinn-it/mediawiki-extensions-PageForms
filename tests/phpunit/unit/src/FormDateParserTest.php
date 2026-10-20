<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormDateParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDateParser
 * @group PF
 */
class FormDateParserTest extends TestCase {

	private const GERMAN = [
		'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
		'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
	];

	private function parse( string $raw, array $monthNames = [] ): ?string {
		$date = ( new FormDateParser( $monthNames ) )->parse( $raw );
		return $date === null ? null : $date->toIso();
	}

	public static function provideEnglishAndNumeric(): array {
		return [
			'year' => [ '2026', '2026' ],
			'ISO year and month' => [ '2026-09', '2026-09' ],
			'single-digit month' => [ '2026-9', '2026-09' ],
			'slashed year and month' => [ '2026/09', '2026-09' ],
			'ISO date' => [ '2026-09-30', '2026-09-30' ],
			'slashed date, as PF wrote it' => [ '2026/09/30', '2026-09-30' ],
			'single digits' => [ '2026-6-5', '2026-06-05' ],
			'surrounding whitespace' => [ "  2026-09-30 \n", '2026-09-30' ],
			'month name and year' => [ 'September 2026', '2026-09' ],
			'month name is case-insensitive' => [ 'september 2026', '2026-09' ],
			'English month in the middle of the year' => [ 'May 2026', '2026-05' ],
			'day, month name, year' => [ '30 September 2026', '2026-09-30' ],
			'day with a dot, month name, year' => [ '30. September 2026', '2026-09-30' ],
			'American' => [ 'September 30, 2026', '2026-09-30' ],
			'American with ordinal' => [ 'September 30th, 2026', '2026-09-30' ],
			'abbreviated month' => [ 'Sep 30, 2026', '2026-09-30' ],
			'abbreviated month after the day' => [ '30 Sep 2026', '2026-09-30' ],
			'dotted' => [ '30.09.2026', '2026-09-30' ],
			'dotted month and year' => [ '09.2026', '2026-09' ],
			'slash with year last, month first' => [ '09/30/2026', '2026-09-30' ],
			'slash with year last, day first because it cannot be a month' => [ '30/09/2026', '2026-09-30' ],
			'ambiguous slash date is read month first' => [ '01/02/2026', '2026-01-02' ],
			'dashes with year last are day first' => [ '30-09-2026', '2026-09-30' ],
		];
	}

	/**
	 * @dataProvider provideEnglishAndNumeric
	 */
	public function testReadsEnglishAndNumericFormats( string $raw, string $expected ): void {
		$this->assertSame( $expected, $this->parse( $raw ) );
	}

	public static function provideTimes(): array {
		return [
			'24 hour without seconds' => [ '2026-09-30 14:05', '2026-09-30 14:05:00' ],
			'24 hour with seconds' => [ '2026-09-30 14:05:09', '2026-09-30 14:05:09' ],
			'with T separator' => [ '2026-09-30T14:05:09', '2026-09-30 14:05:09' ],
			'PM, as PF wrote it' => [ '2026/09/30 02:05:00 PM', '2026-09-30 14:05:00' ],
			'AM' => [ '2026/09/30 02:05:00 AM', '2026-09-30 02:05:00' ],
			'midnight in 12 hour format' => [ '2026/09/30 12:00:00 AM', '2026-09-30 00:00:00' ],
			'noon in 12 hour format' => [ '2026/09/30 12:30:00 PM', '2026-09-30 12:30:00' ],
			'lower case pm' => [ '2026-09-30 2:05 pm', '2026-09-30 14:05:00' ],
			'American with time' => [ 'September 30, 2026 02:05:00 PM', '2026-09-30 14:05:00' ],
			'day, month name, year and time' => [ '30 September 2026 14:05', '2026-09-30 14:05:00' ],
			'timezone abbreviation' => [ '2026-09-30 14:05 CEST', '2026-09-30 14:05:00 CEST' ],
			'UTC marker' => [ '2026-09-30T14:05:00Z', '2026-09-30T14:05:00Z' ],
			'UTC marker with milliseconds, as the picker submits it' => [
				'2026-09-30T14:05:00.000Z', '2026-09-30T14:05:00Z'
			],
			'offset' => [ '2026-09-30 14:05:00 +02:00', '2026-09-30 14:05:00 +02:00' ],
		];
	}

	/**
	 * @dataProvider provideTimes
	 */
	public function testReadsTimes( string $raw, string $expected ): void {
		$this->assertSame( $expected, $this->parse( $raw ) );
	}

	public static function provideGerman(): array {
		return [
			'month name and year' => [ 'März 2026', '2026-03' ],
			'day, month name, year' => [ '30 März 2026', '2026-03-30' ],
			'day with a dot' => [ '30. März 2026', '2026-03-30' ],
			'with time' => [ '30. März 2026 14:05', '2026-03-30 14:05:00' ],
			'upper case' => [ 'MÄRZ 2026', '2026-03' ],
			'English names stay readable' => [ 'March 2026', '2026-03' ],
		];
	}

	/**
	 * @dataProvider provideGerman
	 */
	public function testReadsMonthNamesOfTheGivenLanguages( string $raw, string $expected ): void {
		$this->assertSame( $expected, $this->parse( $raw, [ self::GERMAN ] ) );
	}

	public function testReadsMonthNamesOfSeveralLanguages(): void {
		$french = [ 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
			'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' ];

		$this->assertSame( '2026-02', $this->parse( 'février 2026', [ self::GERMAN, $french ] ) );
		$this->assertSame( '2026-03', $this->parse( 'März 2026', [ self::GERMAN, $french ] ) );
	}

	public static function provideUnreadable(): array {
		return [
			'empty' => [ '' ],
			'blank' => [ '   ' ],
			'text' => [ 'foo' ],
			'month 13' => [ '2026-13-01' ],
			'30th of February' => [ '2026-02-30' ],
			'31st of February, dotted' => [ '31.02.2026' ],
			'hour 25' => [ '2026-09-30 25:00' ],
			'13 on the 12 hour clock' => [ '2026-09-30 13:00 PM' ],
			'five-digit year' => [ '20266' ],
			'two-digit year only' => [ '99' ],
			'German month name without German names' => [ 'März 2026' ],
			'relative date' => [ 'tomorrow' ],
			'now' => [ 'now' ],
			'month without year' => [ 'September' ],
			'time only' => [ '14:05' ],
		];
	}

	/**
	 * @dataProvider provideUnreadable
	 */
	public function testUnreadableValuesGiveNoDate( string $raw ): void {
		$this->assertNull( $this->parse( $raw ) );
	}

	public function testMonthNumberKnowsAllLanguages(): void {
		$parser = new FormDateParser( [ self::GERMAN ] );

		$this->assertSame( 3, $parser->monthNumber( 'März' ) );
		$this->assertSame( 3, $parser->monthNumber( ' march ' ) );
		$this->assertNull( $parser->monthNumber( 'Smarch' ) );
	}

	public function testResultKeepsTheParts(): void {
		$date = ( new FormDateParser( [] ) )->parse( '2026/09/30 02:05:09 PM CEST' );

		$this->assertSame( [ 2026, 9, 30, 14, 5, 9, 'CEST' ], [
			$date->getYear(), $date->getMonth(), $date->getDay(),
			$date->getHour(), $date->getMinute(), $date->getSecond(), $date->getTimezone()
		] );
	}
}
