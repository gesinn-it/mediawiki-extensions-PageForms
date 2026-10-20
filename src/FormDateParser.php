<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Reads a date from the text of a wiki page, whoever wrote it: a form in any language,
 * an older version of PF, or a person typing into the wikitext.
 *
 * It is lenient on purpose and gives up (null) rather than guess when a value is
 * ambiguous beyond the rules below. Callers must then keep the original text.
 *
 * Rules for the cases that cannot be told apart by looking at the numbers:
 *  - `a/b/YYYY` is month first (like PHP), unless `a` cannot be a month.
 *  - `a-b-YYYY` and `a.b.YYYY` are day first.
 *
 * @ingroup PF
 */
class FormDateParser {

	private const ENGLISH = [
		'January', 'February', 'March', 'April', 'May', 'June',
		'July', 'August', 'September', 'October', 'November', 'December'
	];

	/** @var array<string,int> month name, lower case, to month number */
	private array $monthNumbers = [];

	/** @var string Regular expression alternative that matches any known month name */
	private string $monthPattern;

	/**
	 * @param array<int,string[]> $monthNames Lists of twelve month names, January first, for the
	 *  languages to read besides English.
	 */
	public function __construct( array $monthNames ) {
		foreach ( array_merge( [ self::ENGLISH ], $monthNames ) as $names ) {
			foreach ( array_values( $names ) as $index => $name ) {
				$this->monthNumbers[mb_strtolower( $name )] ??= $index + 1;
			}
		}
		$known = array_keys( $this->monthNumbers );
		usort( $known, static fn ( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );
		$this->monthPattern = implode(
			'|',
			array_map( static fn ( string $name ): string => preg_quote( $name, '/' ), $known )
		);
	}

	public function parse( string $text ): ?FormDate {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
		if ( $text === '' ) {
			return null;
		}

		$time = [ null, null, null, null ];
		if ( preg_match(
			'/^(.*?\d)(?: ?T ?| )(\d{1,2}):(\d{2})(?::(\d{2}))?(?: ?([AaPp])\.?[Mm]\.?)?'
				. '(?: ?(Z|[+-]\d{2}:?\d{2}|[A-Za-z]{2,5}))?$/u',
			$text,
			$m
		) ) {
			$text = $m[1];
			$hour = (int)$m[2];
			if ( ( $m[5] ?? '' ) !== '' ) {
				if ( $hour < 1 || $hour > 12 ) {
					return null;
				}
				$hour = $hour % 12 + ( strtolower( $m[5] ) === 'p' ? 12 : 0 );
			}
			$time = [ $hour, (int)$m[3], (int)( $m[4] ?? 0 ), ( $m[6] ?? '' ) === '' ? null : $m[6] ];
		}

		$parts = $this->parseDatePart( $text );
		if ( $parts === null ) {
			return null;
		}
		if ( $time[0] !== null && $parts[2] === null ) {
			// A time on a date without a day is not something a form can write.
			return null;
		}
		return FormDate::create( $parts[0], $parts[1], $parts[2], $time[0], $time[1], $time[2], $time[3] );
	}

	/**
	 * @return array{0:int,1:?int,2:?int}|null year, month, day
	 */
	private function parseDatePart( string $text ): ?array {
		// 2026, 2026-09, 2026/09/30 and the like.
		if ( preg_match( '#^(\d{4})(?:[-/](\d{1,2})(?:[-/](\d{1,2}))?)?$#', $text, $m ) ) {
			return [ (int)$m[1], isset( $m[2] ) ? (int)$m[2] : null, isset( $m[3] ) ? (int)$m[3] : null ];
		}
		// 30.09.2026
		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m ) ) {
			return [ (int)$m[3], (int)$m[2], (int)$m[1] ];
		}
		// 09.2026
		if ( preg_match( '/^(\d{1,2})\.(\d{4})$/', $text, $m ) ) {
			return [ (int)$m[2], (int)$m[1], null ];
		}
		// 09/30/2026, 30/09/2026
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text, $m ) ) {
			$first = (int)$m[1];
			$second = (int)$m[2];
			return $first > 12 ? [ (int)$m[3], $second, $first ] : [ (int)$m[3], $first, $second ];
		}
		// 30-09-2026
		if ( preg_match( '/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $text, $m ) ) {
			return [ (int)$m[3], (int)$m[2], (int)$m[1] ];
		}

		$names = $this->monthPattern;
		// 30 September 2026, 30. September 2026
		if ( preg_match( "/^(\\d{1,2})\\.? ($names)\\.?,? (\\d{4})$/iu", $text, $m ) ) {
			return [ (int)$m[3], $this->monthNumbers[mb_strtolower( $m[2] )], (int)$m[1] ];
		}
		// September 30, 2026
		if ( preg_match( "/^($names)\\.? (\\d{1,2})(?:st|nd|rd|th)?,? (\\d{4})$/iu", $text, $m ) ) {
			return [ (int)$m[3], $this->monthNumbers[mb_strtolower( $m[1] )], (int)$m[2] ];
		}
		// September 2026
		if ( preg_match( "/^($names)\\.?,? (\\d{4})$/iu", $text, $m ) ) {
			return [ (int)$m[2], $this->monthNumbers[mb_strtolower( $m[1] )], null ];
		}

		return $this->parseWithPhp( $text );
	}

	/**
	 * English formats that are not covered above, for example abbreviated month names.
	 *
	 * @return array{0:int,1:int,2:int}|null
	 */
	private function parseWithPhp( string $text ): ?array {
		$parsed = date_parse( $text );
		if ( $parsed['error_count'] > 0 || $parsed['warning_count'] > 0 || isset( $parsed['relative'] ) ) {
			return null;
		}
		$year = $parsed['year'];
		$month = $parsed['month'];
		$day = $parsed['day'];
		if ( !is_int( $year ) || !is_int( $month ) || !is_int( $day ) || $parsed['hour'] !== false ) {
			return null;
		}
		return [ $year, $month, $day ];
	}

}
