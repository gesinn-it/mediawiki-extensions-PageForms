<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Helpers for dates and the names of months in the wiki's content language.
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
	 * Returns a string representing the current date (and optionally time).
	 *
	 * Moved here from FormUtils, which keeps a deprecated forward for
	 * backward compatibility with external callers.
	 *
	 * @param bool $includeTime Whether to append the current time.
	 * @param bool $includeTimezone Whether to append the timezone abbreviation.
	 * @return string
	 */
	public static function getStringForCurrentTime( $includeTime, $includeTimezone ) {
		global $wgLocaltimezone, $wgAmericanDates, $wgPageForms24HourTime;

		$serverTimezone = '';
		if ( $wgLocaltimezone !== null ) {
			$serverTimezone = date_default_timezone_get();
			date_default_timezone_set( $wgLocaltimezone );
		}
		$cur_time = time();
		$year = date( "Y", $cur_time );
		$month = date( "n", $cur_time );
		$day = date( "j", $cur_time );
		if ( $wgAmericanDates == true ) {
			$month_names = self::getMonthNames();
			$month_name = $month_names[(int)$month - 1];
			$curTimeString = "$month_name $day, $year";
		} else {
			$curTimeString = "$year-$month-$day";
		}
		if ( $wgLocaltimezone !== null ) {
			date_default_timezone_set( $serverTimezone );
		}
		if ( !$includeTime ) {
			return $curTimeString;
		}

		if ( $wgPageForms24HourTime ) {
			$hour = str_pad( (string)intval( substr( date( "G", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		} else {
			$hour = str_pad( (string)intval( substr( date( "g", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		}
		$minute = str_pad( (string)intval( substr( date( "i", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		$second = str_pad( (string)intval( substr( date( "s", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		if ( $wgPageForms24HourTime ) {
			$curTimeString .= " $hour:$minute:$second";
		} else {
			$ampm = date( "A", $cur_time );
			$curTimeString .= " $hour:$minute:$second $ampm";
		}

		if ( $includeTimezone ) {
			$timezone = date( "T", $cur_time );
			$curTimeString .= " $timezone";
		}

		return $curTimeString;
	}

}
