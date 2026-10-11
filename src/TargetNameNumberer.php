<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MessageLocalizer;
use MWException;
use Title;

/**
 * Replaces the "unique number" tag of the name of a page to create ("Thing_{num}") with a number
 * that makes the name of a page that does not exist yet.
 *
 * @ingroup PF
 */
final class TargetNameNumberer {

	/** The number of digits of a random number, unless the tag asks for another. */
	private const DEFAULT_RANDOM_DIGITS = 6;

	/** The number of names that are tried before random numbers get one digit longer. */
	private const CROWDED_AFTER_ATTEMPTS = 20;

	public function __construct( private readonly MessageLocalizer $messages ) {
	}

	/**
	 * Returns a formatted (pseudo) random number
	 *
	 * @param int $numDigits the min width of the random number
	 * @param bool $hasPadding should the number should be padded with zeros instead of spaces?
	 * @return string
	 */
	public static function makeRandomNumber( int $numDigits = 1, bool $hasPadding = false ): string {
		$maxValue = (int)min( 10 ** $numDigits - 1, PHP_INT_MAX );
		$value = random_int( 0, $maxValue );
		$format = '%' . ( $hasPadding ? '0' : '' ) . $numDigits . 'd';
		// trim() removes leading spaces when padding is disabled
		return trim( sprintf( $format, $value ) );
	}

	/**
	 * @param string $targetName The name of the page, with the tag if it has one
	 * @param string $targetNameFormula The formula the name was made from, for the message
	 *  that names an invalid title
	 * @return string $targetName, or the name of a page that does not exist if it has a tag
	 * @throws MWException if the name is no valid title
	 */
	public function numberTargetName( string $targetName, string $targetNameFormula ): string {
		if ( !preg_match( '/{num.*}/', $targetName ) ) {
			return $targetName;
		}

		$isRandom = false;
		$randomNumHasPadding = false;
		$randomNumDigits = self::DEFAULT_RANDOM_DIGITS;
		$titleNumber = '';

		if ( preg_match( '/{num;random(;(0)?([1-9][0-9]*))?}/', $targetName, $matches ) ) {
			$isRandom = true;
			$randomNumHasPadding = array_key_exists( 2, $matches );
			$randomNumDigits = array_key_exists( 3, $matches ) ? $matches[3] : $randomNumDigits;
			$titleNumber = self::makeRandomNumber( $randomNumDigits, $randomNumHasPadding );
		} elseif ( preg_match( '/{num.*start[_]*=[_]*([^;]*).*}/', $targetName, $matches ) ) {
			// get unique number start value
			// from target name; if it's not
			// there, or it's not a positive
			// number, start it out as blank
			if ( count( $matches ) == 2 && is_numeric( $matches[1] ) && $matches[1] >= 0 ) {
				// the "start" value"
				$titleNumber = $matches[1];
			}
		} elseif ( preg_match( '/^(_?{num.*}?)*$/', $targetName ) ) {
			// the target name contains only underscores and number fields,
			// i.e. would result in an empty title without the number set
			$titleNumber = '1';
		}

		// set target title
		$targetTitle = Title::newFromText( preg_replace( '/{num.*}/', $titleNumber, $targetName ) );

		// if the specified target title is invalid, give up
		if ( !$targetTitle instanceof Title ) {
			$targetString = trim( preg_replace( '/<unique number(.*)>/', $titleNumber, $targetNameFormula ) );
			throw new MWException(
				$this->messages->msg( 'pf_autoedit_invalidtargetspecified', $targetString )->parse()
			);
		}

		// If title exists already, cycle through numbers for
		// this tag until we find one that gives a nonexistent
		// page title.
		// We cannot use $targetTitle->exists(); it does not read
		// the latest data from the primary DB, which is needed to
		// get correct data instead of a stale cached result; use
		// $targetTitle->getArticleID() instead.
		$numAttemptsAtTitle = 0;
		while ( $targetTitle->getArticleID( \IDBAccessObject::READ_LATEST ) !== 0 ) {
			$numAttemptsAtTitle++;

			if ( $isRandom ) {
				// If the set of pages is "crowded"
				// already, go one digit higher.
				if ( $numAttemptsAtTitle > self::CROWDED_AFTER_ATTEMPTS ) {
					$randomNumDigits++;
				}
				$titleNumber = self::makeRandomNumber( $randomNumDigits, $randomNumHasPadding );
			} else {
				$titleNumber = self::nextNumber( (string)$titleNumber );
			}

			$targetTitle = Title::newFromText( preg_replace( '/{num.*}/', $titleNumber, $targetName ) );
		}

		return $targetTitle->getPrefixedText();
	}

	/**
	 * If the number is blank, it becomes 2; otherwise it is incremented, and padded with
	 * leading 0s as well if it had some.
	 */
	private static function nextNumber( string $titleNumber ): string {
		if ( $titleNumber === '' ) {
			return '2';
		}

		// The number is numeric: only a numeric start value is taken from the tag.
		return str_pad( (string)( (float)$titleNumber + 1 ), strlen( $titleNumber ), '0', STR_PAD_LEFT );
	}
}
