<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormDateUtils;

/**
 * What is written to the wikitext when a form with a date is saved, and how the form follows
 * the language and the date preference of the user.
 *
 * @covers \MediaWiki\Extension\PageForms\FormDateUtils
 * @group Database
 */
class FormDateUtilsSubmissionTest extends MediaWikiIntegrationTestCase {

	/**
	 * The 30th of September 2026 with the given parts added.
	 */
	private static function on30th( array $parts ): array {
		return array_merge( [ 'year' => '2026', 'month' => '09', 'day' => '30' ], $parts );
	}

	public static function provideSubmissions(): array {
		return [
			'nothing' => [ [ 'year' => '', 'month' => '', 'day' => '' ], '' ],
			'year' => [ [ 'year' => '2026', 'month' => '', 'day' => '' ], '2026' ],
			'year and month' => [ [ 'year' => '2026', 'month' => '09', 'day' => '' ], '2026-09' ],
			'month without leading zero' => [ [ 'year' => '2026', 'month' => '9', 'day' => '' ], '2026-09' ],
			'full date' => [ self::on30th( [] ), '2026-09-30' ],
			'day without leading zero' => [ self::on30th( [ 'day' => '5' ] ), '2026-09-05' ],
			'month name, as an older form sent it' => [ self::on30th( [ 'month' => 'September' ] ), '2026-09-30' ],
			'whitespace' => [ [ 'year' => ' 2026 ', 'month' => '09', 'day' => ' 30 ' ], '2026-09-30' ],
			'month without year' => [ [ 'year' => '', 'month' => '09', 'day' => '' ], '' ],
			'time' => [ self::on30th( [ 'hour' => '14', 'minute' => '5', 'second' => '9' ] ), '2026-09-30 14:05:09' ],
			'time without seconds' => [
				self::on30th( [ 'hour' => '14', 'minute' => '05', 'second' => '' ] ), '2026-09-30 14:05:00'
			],
			'PM' => [ self::on30th( [ 'hour' => '2', 'minute' => '05', 'ampm24h' => 'PM' ] ), '2026-09-30 14:05:00' ],
			'AM' => [ self::on30th( [ 'hour' => '9', 'minute' => '05', 'ampm24h' => 'AM' ] ), '2026-09-30 09:05:00' ],
			'midnight' => [
				self::on30th( [ 'hour' => '12', 'minute' => '00', 'ampm24h' => 'AM' ] ), '2026-09-30 00:00:00'
			],
			'noon' => [
				self::on30th( [ 'hour' => '12', 'minute' => '00', 'ampm24h' => 'PM' ] ), '2026-09-30 12:00:00'
			],
			'24 hour value with AM/PM empty' => [
				self::on30th( [ 'hour' => '14', 'minute' => '05', 'ampm24h' => '' ] ), '2026-09-30 14:05:00'
			],
			'timezone' => [
				self::on30th( [ 'hour' => '14', 'minute' => '05', 'timezone' => 'CEST' ] ), '2026-09-30 14:05:00 CEST'
			],
			'blank time is no time' => [
				self::on30th( [ 'hour' => '', 'minute' => '', 'second' => '00' ] ), '2026-09-30'
			],
			'time of a partial date is dropped' => [
				[ 'year' => '2026', 'month' => '09', 'day' => '', 'hour' => '14', 'minute' => '05' ], '2026-09'
			],
			'day that does not exist is kept for correcting' => [
				[ 'year' => '2026', 'month' => '02', 'day' => '30' ], '2026-02-30'
			],
			'short year is kept' => [ [ 'year' => '26', 'month' => '', 'day' => '' ], '26' ],
			'text that was not a date' => [
				[ 'year' => '', 'month' => '', 'day' => '', 'raw' => 'around 1900' ], 'around 1900'
			],
			'entered date wins over the kept text' => [
				[ 'year' => '2026', 'month' => '', 'day' => '', 'raw' => 'around 1900' ], '2026'
			],
		];
	}

	/**
	 * @dataProvider provideSubmissions
	 */
	public function testGetStringFromSubmission( array $submission, string $expected ): void {
		$this->assertSame( $expected, FormDateUtils::getStringFromSubmission( $submission ) );
	}

	public function testWrittenValueDoesNotDependOnTheWikiSettings(): void {
		$this->setUserLang( 'fr' );
		$this->setContentLang( 'de' );
		$this->setMwGlobals( 'wgAmericanDates', true );

		$this->assertSame(
			'2026-03-05',
			FormDateUtils::getStringFromSubmission( [ 'year' => '2026', 'month' => '3', 'day' => '5' ] )
		);
	}

	public static function provideNormalized(): array {
		return [
			'ISO stays' => [ '2026-09-30', '2026-09-30' ],
			'slashes' => [ '2026/09/30', '2026-09-30' ],
			'month name' => [ '30 September 2026', '2026-09-30' ],
			'picker value with milliseconds' => [ '2026-09-30T14:05:00.000Z', '2026-09-30T14:05:00Z' ],
			'not a date is kept as it is' => [ 'around 1900', 'around 1900' ],
			'empty' => [ '', '' ],
		];
	}

	/**
	 * @dataProvider provideNormalized
	 */
	public function testNormalize( string $stored, string $expected ): void {
		$this->assertSame( $expected, FormDateUtils::normalize( $stored ) );
	}

	public function testMonthNamesOfTheUserLanguageAreUsedForTheForm(): void {
		$this->setUserLang( 'de' );

		$this->assertSame( 'März', FormDateUtils::getMonthNamesForUser()[2] );
	}

	public function testStoredMonthNamesOfContentAndUserLanguageAreUnderstood(): void {
		$this->setUserLang( 'de' );

		$this->assertSame( '2026-03', FormDateUtils::parse( 'März 2026' )->toIso() );
		$this->assertSame( '2026-03', FormDateUtils::parse( 'March 2026' )->toIso() );
	}

	public function testNowIsRecognisedAsAValue(): void {
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', FormDateUtils::parse( 'now' )->toIso() );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			FormDateUtils::parse( 'now', true )->toIso()
		);
	}

	public static function provideFieldOrder(): array {
		return [
			'month first' => [ 'mdy', false, [ 'month', 'day', 'year' ] ],
			'day first' => [ 'dmy', true, [ 'day', 'month', 'year' ] ],
			'year first' => [ 'ymd', false, [ 'year', 'month', 'day' ] ],
			'ISO' => [ 'ISO 8601', false, [ 'year', 'month', 'day' ] ],
			'no preference, $wgAmericanDates off' => [ 'default', false, [ 'day', 'month', 'year' ] ],
			'no preference, $wgAmericanDates on' => [ 'default', true, [ 'month', 'day', 'year' ] ],
		];
	}

	/**
	 * @dataProvider provideFieldOrder
	 */
	public function testFieldOrderFollowsThePreferenceOfTheUser(
		string $preference, bool $american, array $expected
	): void {
		$this->setMwGlobals( 'wgAmericanDates', $american );
		$user = $this->getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()->setOption( $user, 'date', $preference );
		RequestContext::getMain()->setUser( $user );

		$this->assertSame( $expected, FormDateUtils::getFieldOrder() );
	}
}
