<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormCounters;
use OOUI\BlankTheme;

/**
 * Every date format a wiki page may contain (written by PF in the past or typed by hand
 * in the wikitext) must be shown correctly when the page is opened in a form.
 *
 * @covers \PFDateInput
 * @covers \PFDateTimeInput
 * @covers \PFDatePickerInput
 * @covers \PFDateTimePicker
 * @group Database
 */
class PFDateLegacyFormatsTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		\OOUI\Theme::setSingleton( new BlankTheme() );
		FormCounters::current()->tabIndex = 1;
		FormCounters::current()->fieldNum = 1;
		$this->setMwGlobals( [
			'wgAmericanDates' => false,
			'wgPageForms24HourTime' => false,
		] );
	}

	private function useGerman(): void {
		$this->setMwGlobals( 'wgLanguageCode', 'de' );
		$this->setContentLang( 'de' );
	}

	/**
	 * @param string $html
	 * @return string[] day, month and year as shown in the three-part date input
	 */
	private function dayMonthYear( string $html ): array {
		preg_match( '/class="dayInput" name="[^"]*" type="text" value="([^"]*)"/', $html, $day );
		preg_match( '/class="yearInput" name="[^"]*" type="text" value="([^"]*)"/', $html, $year );
		preg_match( '/<option value="(\d\d)" selected="">/', $html, $month );
		return [ $day[1] ?? '?', $month[1] ?? '', $year[1] ?? '?' ];
	}

	public static function provideDateStrings(): array {
		return [
			'ISO' => [ '2024-06-15', [ '15', '06', '2024' ] ],
			'slashes, as PF wrote it' => [ '2024/06/15', [ '15', '06', '2024' ] ],
			'single-digit month and day' => [ '2024-6-5', [ '5', '06', '2024' ] ],
			'day, month name, year' => [ '15 June 2024', [ '15', '06', '2024' ] ],
			'month name, day, year (American)' => [ 'June 15, 2024', [ '15', '06', '2024' ] ],
			'dotted' => [ '15.06.2024', [ '15', '06', '2024' ] ],
			'year and month name' => [ 'June 2024', [ '', '06', '2024' ] ],
			'ISO year and month' => [ '2024-06', [ '', '06', '2024' ] ],
			'dotted month and year' => [ '06.2024', [ '', '06', '2024' ] ],
			'year only' => [ '2024', [ '', '', '2024' ] ],
		];
	}

	/**
	 * @dataProvider provideDateStrings
	 */
	public function testDateInputPreloadsStoredValue( string $stored, array $expected ): void {
		$html = ( new PFDateInput( 1, $stored, 'PFDLField01', false, [] ) )->getHtmlText();

		$this->assertSame( $expected, $this->dayMonthYear( $html ) );
	}

	public static function provideGermanDateStrings(): array {
		return [
			'day, German month name, year' => [ '15 März 2024', [ '15', '03', '2024' ] ],
			'German month name and year' => [ 'März 2024', [ '', '03', '2024' ] ],
			'English month name still readable' => [ '15 June 2024', [ '15', '06', '2024' ] ],
		];
	}

	/**
	 * @dataProvider provideGermanDateStrings
	 */
	public function testDateInputPreloadsGermanMonthNamesInGermanWiki( string $stored, array $expected ): void {
		$this->useGerman();

		$html = ( new PFDateInput( 1, $stored, 'PFDLField01', false, [] ) )->getHtmlText();

		$this->assertSame( $expected, $this->dayMonthYear( $html ) );
	}

	public static function provideFullDates(): array {
		return [
			'ISO' => [ '2024-06-15' ],
			'slashes' => [ '2024/06/15' ],
			'day, month name, year' => [ '15 June 2024' ],
			'American' => [ 'June 15, 2024' ],
			'dotted' => [ '15.06.2024' ],
			'with time' => [ '2024-06-15 14:05' ],
		];
	}

	/**
	 * @dataProvider provideFullDates
	 */
	public function testDatePickerPreloadsFullDate( string $stored ): void {
		$html = ( new PFDatePickerInput( 1, $stored, 'PFDLField02', false, [] ) )->getHtmlText();

		$this->assertStringContainsString( '"value":"2024-06-15"', $html );
	}

	public static function provideDateTimes(): array {
		return [
			'ISO with minutes' => [ '2024-06-15 14:05', '2024-06-15T14:05:00' ],
			'ISO with seconds' => [ '2024-06-15 14:05:09', '2024-06-15T14:05:09' ],
			'slashes with AM/PM, as PF wrote it' => [ '2024/06/15 02:05:00 PM', '2024-06-15T14:05:00' ],
			'American with AM/PM' => [ 'June 15, 2024 02:05:00 PM', '2024-06-15T14:05:00' ],
			'midnight' => [ '2024-06-15 00:00:00', '2024-06-15T00:00:00' ],
		];
	}

	/**
	 * @dataProvider provideDateTimes
	 */
	public function testDateTimePickerPreloadsDateAndTime( string $stored, string $expected ): void {
		$html = ( new PFDateTimePicker( 1, $stored, 'PFDLField03', false, [] ) )->getHtmlText();

		$this->assertStringContainsString( '"value":"' . $expected, $html );
	}

	public function testDateTimeInputPreloads12HourTimeWithAmPm(): void {
		$html = ( new PFDateTimeInput( 1, '2024/06/15 02:05:00 PM', 'PFDLField04', false, [] ) )->getHtmlText();

		$this->assertStringContainsString( 'class="hoursInput" value="2"', $html );
		$this->assertStringContainsString( 'class="minutesInput" value="05"', $html );
		$this->assertStringContainsString( '<option value="PM" selected="selected">', $html );
		$this->assertSame( [ '15', '06', '2024' ], $this->dayMonthYear( $html ) );
	}

	public function testDateTimeInputPreloads24HourTime(): void {
		$this->setMwGlobals( 'wgPageForms24HourTime', true );

		$html = ( new PFDateTimeInput( 1, '2024-06-15 14:05:00', 'PFDLField04', false, [] ) )->getHtmlText();

		$this->assertStringContainsString( 'class="hoursInput" value="14"', $html );
		$this->assertStringContainsString( 'class="minutesInput" value="05"', $html );
	}
}
