<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormDate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDate
 * @group PF
 */
class FormDateTest extends TestCase {

	public function testYearOnlyIsWrittenAsTheYear(): void {
		$this->assertSame( '2026', FormDate::create( 2026 )->toIso() );
	}

	public function testYearIsPaddedToFourDigits(): void {
		$this->assertSame( '0999', FormDate::create( 999 )->toIso() );
	}

	public function testYearAndMonthAreWrittenWithTwoDigitMonth(): void {
		$this->assertSame( '2026-09', FormDate::create( 2026, 9 )->toIso() );
	}

	public function testFullDateIsWrittenWithTwoDigitMonthAndDay(): void {
		$this->assertSame( '2026-09-05', FormDate::create( 2026, 9, 5 )->toIso() );
	}

	public function testTimeIsWrittenIn24HourFormatWithSeconds(): void {
		$this->assertSame( '2026-09-30 14:05:00', FormDate::create( 2026, 9, 30, 14, 5 )->toIso() );
	}

	public function testMissingSecondsDefaultToZero(): void {
		$this->assertSame( '2026-09-30 00:00:00', FormDate::create( 2026, 9, 30, 0, 0 )->toIso() );
	}

	public function testTimezoneIsAppendedVerbatim(): void {
		$date = FormDate::create( 2026, 9, 30, 14, 5, 9, 'CEST' );

		$this->assertSame( '2026-09-30 14:05:09 CEST', $date->toIso() );
	}

	public function testTimeWithoutDayIsDiscarded(): void {
		$date = FormDate::create( 2026, 9, null, 14, 5 );

		$this->assertSame( '2026-09', $date->toIso() );
		$this->assertFalse( $date->hasTime() );
	}

	public function testHourWithoutMinuteIsInvalid(): void {
		$this->assertNull( FormDate::create( 2026, 9, 30, 14 ) );
	}

	public static function provideInvalidParts(): array {
		return [
			'month 13' => [ 2026, 13, 1 ],
			'month 0' => [ 2026, 0, 1 ],
			'day 0' => [ 2026, 9, 0 ],
			'31st of September' => [ 2026, 9, 31 ],
			'29th of February in a common year' => [ 2026, 2, 29 ],
			'day without month' => [ 2026, null, 5 ],
			'year 0' => [ 0, 1, 1 ],
			'year 5 digits' => [ 10000, 1, 1 ],
			'hour 24' => [ 2026, 9, 30, 24, 0 ],
			'minute 60' => [ 2026, 9, 30, 10, 60 ],
			'second 60' => [ 2026, 9, 30, 10, 0, 60 ],
		];
	}

	/**
	 * @dataProvider provideInvalidParts
	 */
	public function testInvalidPartsGiveNoDate(
		int $year, ?int $month, ?int $day, ?int $hour = null, ?int $minute = null, ?int $second = null
	): void {
		$this->assertNull( FormDate::create( $year, $month, $day, $hour, $minute, $second ) );
	}

	public function testLeapDayIsValid(): void {
		$this->assertSame( '2028-02-29', FormDate::create( 2028, 2, 29 )->toIso() );
	}

	public static function providePrecision(): array {
		return [
			'year' => [ FormDate::create( 2026 ), 'year' ],
			'month' => [ FormDate::create( 2026, 9 ), 'month' ],
			'day' => [ FormDate::create( 2026, 9, 30 ), 'day' ],
			'time' => [ FormDate::create( 2026, 9, 30, 1, 2 ), 'day' ],
		];
	}

	/**
	 * @dataProvider providePrecision
	 */
	public function testPrecisionIsTheMostDetailedPartThatIsKnown( FormDate $date, string $expected ): void {
		$this->assertSame( $expected, $date->getPrecision() );
	}

	public function testPickerDateNeedsAFullDate(): void {
		$this->assertNull( FormDate::create( 2026 )->toPickerDate() );
		$this->assertNull( FormDate::create( 2026, 9 )->toPickerDate() );
		$this->assertSame( '2026-09-30', FormDate::create( 2026, 9, 30 )->toPickerDate() );
		$this->assertSame( '2026-09-30', FormDate::create( 2026, 9, 30, 14, 5 )->toPickerDate() );
	}

	public function testPickerDateTimeNeedsAFullDateAndDefaultsToMidnightUtc(): void {
		$this->assertNull( FormDate::create( 2026, 9 )->toPickerDateTime() );
		$this->assertSame( '2026-09-30T00:00:00Z', FormDate::create( 2026, 9, 30 )->toPickerDateTime() );
	}

	public function testPickerDateTimeTakesATimeWithoutTimezoneAsUtc(): void {
		$this->assertSame( '2026-09-30T14:05:09Z', FormDate::create( 2026, 9, 30, 14, 5, 9 )->toPickerDateTime() );
	}

	public static function provideTimezonesForThePicker(): array {
		return [
			'UTC marker' => [ 'Z', '2026-09-30T14:05:00Z' ],
			'positive offset' => [ '+02:00', '2026-09-30T12:05:00Z' ],
			'negative offset' => [ '-0530', '2026-09-30T19:35:00Z' ],
			'abbreviation' => [ 'CEST', '2026-09-30T12:05:00Z' ],
			'unknown timezone is left alone' => [ 'XYZZY', '2026-09-30T14:05:00Z' ],
		];
	}

	/**
	 * @dataProvider provideTimezonesForThePicker
	 */
	public function testPickerDateTimeConvertsToUtc( string $timezone, string $expected ): void {
		$date = FormDate::create( 2026, 9, 30, 14, 5, 0, $timezone );

		$this->assertSame( $expected, $date->toPickerDateTime() );
	}

	public function testUtcMarkerIsWrittenInIso8601Form(): void {
		$date = FormDate::create( 2026, 9, 30, 14, 5, 0, 'Z' );

		$this->assertSame( '2026-09-30T14:05:00Z', $date->toIso() );
	}
}
