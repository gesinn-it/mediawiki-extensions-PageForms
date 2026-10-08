<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;

/**
 * Helpers for the values of form inputs: array values from a submit, changed indices of lists and unique ids.
 *
 * @ingroup PF
 */
class FormInputValues {

	/**
	 * If the value passed in for a certain field, when a form is submitted,
	 * is an array, then it might be from a checkbox or date input — in that
	 * case, convert it into a string.
	 *
	 * Moved here from FormUtils, which keeps a deprecated forward for
	 * backward compatibility with external callers.
	 *
	 * @param array $value
	 * @param string $delimiter
	 * @return string
	 */
	public static function getStringFromPassedInArray( $value, $delimiter ) {
		// If it's just a regular list, concatenate it.
		// This is needed due to some strange behavior
		// in PF, where, if a preload page is passed in
		// in the query string, the form ends up being
		// parsed twice.
		if ( array_key_exists( 'is_list', $value ) ) {
			unset( $value['is_list'] );
			return str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], implode( "$delimiter ", $value ) );
		}

		// if it has 1 or 2 elements, assume it's a checkbox; if it has
		// 3 elements, assume it's a date
		// - this handling will have to get more complex if other
		// possibilities get added
		if ( count( $value ) == 1 ) {
			return PFUtils::getWordForYesOrNo( false );
		} elseif ( count( $value ) == 2 ) {
			return PFUtils::getWordForYesOrNo( true );
		// if it's 3 or greater, assume it's a date or datetime
		} elseif ( count( $value ) >= 3 ) {
			$month = $value['month'];
			$day = $value['day'];
			if ( $day !== '' ) {
				global $wgAmericanDates;
				if ( $wgAmericanDates == false ) {
					// pad out day to always be two digits
					$day = str_pad( $day, 2, "0", STR_PAD_LEFT );
				}
			}
			$year = $value['year'];
			$hour = $minute = $second = $ampm24h = $timezone = null;
			if ( isset( $value['hour'] ) ) {
				$hour = $value['hour'];
			}
			if ( isset( $value['minute'] ) ) {
				$minute = $value['minute'];
			}
			if ( isset( $value['second'] ) ) {
				$second = $value['second'];
			}
			if ( isset( $value['ampm24h'] ) ) {
				$ampm24h = $value['ampm24h'];
			}
			if ( isset( $value['timezone'] ) ) {
				$timezone = $value['timezone'];
			}
			if ( $year !== '' ) {
				global $wgAmericanDates;

				if ( $month == '' ) {
					return $year;
				} elseif ( $day == '' ) {
					if ( !$wgAmericanDates ) {
						// The month is a number - we need it to be a string,
						// so that the date will be parsed correctly if
						// strtotime() is used.
						$monthNames = FormDateUtils::getMonthNames();
						$month = $monthNames[$month - 1];
					}
					return "$month $year";
				} else {
					if ( $wgAmericanDates == true ) {
						$new_value = "$month $day, $year";
					} else {
						$new_value = "$year/$month/$day";
					}
					// If there's a day, include whatever time information we have.
					if ( $hour !== null ) {
						$new_value .= " "
						. str_pad( (string)intval( substr( $hour, 0, 2 ) ), 2, '0', STR_PAD_LEFT )
						. ":"
						. str_pad( (string)intval( substr( $minute, 0, 2 ) ), 2, '0', STR_PAD_LEFT );
					}
					if ( $second !== null ) {
						$new_value .= ":" . str_pad( (string)intval( substr( $second, 0, 2 ) ), 2, '0', STR_PAD_LEFT );
					}
					if ( $ampm24h !== null ) {
						$new_value .= " $ampm24h";
					}
					if ( $timezone !== null ) {
						$new_value .= " $timezone";
					}
					return $new_value;
				}
			}
		}
		return '';
	}

	/**
	 * Get the changed index if a new template or section was
	 * inserted before the end, or one was deleted in the form
	 * @param int $i
	 * @param int|null $new_item_loc
	 * @param int|null $deleted_item_loc
	 * @return int
	 */
	public static function getChangedIndex( $i, $new_item_loc, $deleted_item_loc ) {
		$old_i = $i;
		if ( $new_item_loc != null ) {
			if ( $i > $new_item_loc ) {
				$old_i = $i - 1;
			} elseif ( $i == $new_item_loc ) {
				// it's the new template; it shouldn't
				// get any query-string data
				$old_i = -1;
			}
		} elseif ( $deleted_item_loc != null ) {
			if ( $i >= $deleted_item_loc ) {
				$old_i = $i + 1;
			}
		}
		return $old_i;
	}

	/**
	 * Generates a random UUID v4 string.
	 *
	 * Extracted from FormPrinter (was private), now public so it can be
	 * tested and reused outside the form rendering pipeline.
	 *
	 * @return string
	 */
	public static function generateUUID() {
		// Copied from https://www.php.net/manual/en/function.uniqid.php#94959
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,
			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

}
