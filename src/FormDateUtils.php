<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use DateTimeImmutable;
use DateTimeZone;
use Language;
use LogicException;
use MediaWiki\MediaWikiServices;
use RequestContext;

/**
 * Helpers for dates: the values stored in the wikitext are read and written through FormDate,
 * what a form shows (names of months, order of the fields) follows the language and the
 * date preference of the user.
 *
 * @ingroup PF
 */
class FormDateUtils {

	public static function getMonthNames() {
		return [
			wfMessage( 'january' )->inContentLanguage()->text(),
			wfMessage( 'february' )->inContentLanguage()->text(),
			wfMessage( 'march' )->inContentLanguage()->text(),
			wfMessage( 'april' )->inContentLanguage()->text(),
			// Needed to avoid using 3-letter abbreviation
			wfMessage( 'may_long' )->inContentLanguage()->text(),
			wfMessage( 'june' )->inContentLanguage()->text(),
			wfMessage( 'july' )->inContentLanguage()->text(),
			wfMessage( 'august' )->inContentLanguage()->text(),
			wfMessage( 'september' )->inContentLanguage()->text(),
			wfMessage( 'october' )->inContentLanguage()->text(),
			wfMessage( 'november' )->inContentLanguage()->text(),
			wfMessage( 'december' )->inContentLanguage()->text()
		];
	}

	/**
	 * Returns a string representing the current date (and optionally time), in the form it is stored in.
	 *
	 * Moved here from FormUtils, which keeps a deprecated forward for
	 * backward compatibility with external callers.
	 *
	 * @param bool $includeTime Whether to append the current time.
	 * @param bool $includeTimezone Whether to append the timezone abbreviation.
	 * @return string
	 */
	public static function getStringForCurrentTime( $includeTime, $includeTimezone ) {
		return self::now( (bool)$includeTime, (bool)$includeTimezone )->toIso();
	}

	/**
	 * The current date in the timezone of the wiki ($wgLocaltimezone, or the one of the server).
	 */
	public static function now( bool $includeTime, bool $includeTimezone = false ): FormDate {
		global $wgLocaltimezone;

		$zone = new DateTimeZone( $wgLocaltimezone ?? date_default_timezone_get() );
		$now = new DateTimeImmutable( 'now', $zone );
		$parts = array_map( 'intval', explode( ' ', $now->format( 'Y n j G i s' ) ) );
		[ $year, $month, $day, $hour, $minute, $second ] = $parts;
		$date = FormDate::create(
			$year,
			$month,
			$day,
			$includeTime ? $hour : null,
			$includeTime ? $minute : null,
			$includeTime ? $second : null,
			$includeTime && $includeTimezone ? $now->format( 'T' ) : null
		);
		// The parts come from a real date, so they are always valid.
		return $date ?? throw new LogicException( 'The current date is not a valid date' );
	}

	/**
	 * Reads a date from a stored value or from `now`; null if it cannot be read.
	 * Month names of the content language and of the language of the user are understood.
	 *
	 * @param string $text
	 * @param bool $includeTime Whether `now` includes the time.
	 */
	public static function parse( string $text, bool $includeTime = false ): ?FormDate {
		if ( trim( $text ) === 'now' ) {
			return self::now( $includeTime );
		}
		return self::getParser()->parse( $text );
	}

	/**
	 * The stored form of a date that is shown to people and sent back: unreadable text is returned
	 * as it is, never replaced by something empty.
	 */
	public static function normalize( string $text ): string {
		$date = self::getParser()->parse( $text );
		return $date === null ? $text : $date->toIso();
	}

	public static function getParser(): FormDateParser {
		$names = [];
		$languages = [ MediaWikiServices::getInstance()->getContentLanguage(), self::getUserLanguage() ];
		foreach ( $languages as $language ) {
			$names[$language->getCode()] = self::monthNamesOf( $language );
		}
		return new FormDateParser( array_values( $names ) );
	}

	/**
	 * Names of the twelve months in the language of the user, January first.
	 *
	 * @return string[]
	 */
	public static function getMonthNamesForUser(): array {
		return self::monthNamesOf( self::getUserLanguage() );
	}

	/**
	 * The order of the day, month and year inputs: the date preference of the user if they chose
	 * one, otherwise $wgAmericanDates.
	 *
	 * @return string[] 'day', 'month' and 'year' in the order they are shown
	 */
	public static function getFieldOrder(): array {
		global $wgAmericanDates;

		switch ( self::getUserLanguage()->dateFormat() ) {
			case 'mdy':
				return [ 'month', 'day', 'year' ];
			case 'dmy':
				return [ 'day', 'month', 'year' ];
			case 'ymd':
			case 'ISO 8601':
				return [ 'year', 'month', 'day' ];
			default:
				return $wgAmericanDates ? [ 'month', 'day', 'year' ] : [ 'day', 'month', 'year' ];
		}
	}

	/**
	 * The value to store for the inputs of a date, as sent by a form: `year`, `month`, `day`, and for a
	 * time `hour`, `minute`, `second`, `ampm24h` and `timezone`. The month is its number, but a name is
	 * understood too. If nothing was filled in, `raw` is the stored text a form could not show; it is
	 * kept so that saving a form does not lose it.
	 *
	 * @param array $value
	 */
	public static function getStringFromSubmission( array $value ): string {
		$field = static fn ( string $key ): string => trim( (string)( $value[$key] ?? '' ) );
		$year = $field( 'year' );
		$month = $field( 'month' );
		$day = $field( 'day' );
		if ( $year === '' && $month === '' && $day === '' ) {
			return $field( 'raw' );
		}
		if ( $year === '' ) {
			return '';
		}

		$monthNumber = ctype_digit( $month ) ? (int)$month : self::getParser()->monthNumber( $month );
		$time = [ null, null, null ];
		$hourText = $field( 'hour' );
		$minuteText = $field( 'minute' );
		if ( isset( $value['hour'] ) && ( $hourText !== '' || $minuteText !== '' ) ) {
			$hour = (int)$hourText;
			$ampm = strtoupper( $field( 'ampm24h' ) );
			if ( $ampm !== '' && $hour >= 1 && $hour <= 12 ) {
				$hour = $hour % 12 + ( $ampm === 'PM' ? 12 : 0 );
			}
			$time = [ $hour, (int)$minuteText, (int)$field( 'second' ) ];
		}
		$date = null;
		if ( preg_match( '/^\d{4}$/', $year ) && ( $month === '' || $monthNumber !== null )
			&& ( $day === '' || ctype_digit( $day ) )
		) {
			$date = FormDate::create(
				(int)$year, $monthNumber, $day === '' ? null : (int)$day, $time[0], $time[1], $time[2],
				$field( 'timezone' ) ?: null
			);
		}
		if ( $date !== null ) {
			return $date->toIso();
		}
		// Not a date that exists: keep what was typed, so that it can be corrected.
		return implode( '-', array_filter( [ $year, $month, $day ], 'strlen' ) );
	}

	private static function getUserLanguage(): Language {
		return RequestContext::getMain()->getLanguage();
	}

	/**
	 * @return string[]
	 */
	private static function monthNamesOf( Language $language ): array {
		return array_map( static fn ( int $month ): string => $language->getMonthName( $month ), range( 1, 12 ) );
	}

}
