<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Parsing of the arguments of form tags, and small string helpers for the form definition.
 *
 * @ingroup PF
 */
class FormTagParser {

	/**
	 * A helper function, used by getFormTagComponents().
	 * @param string $s
	 * @return string
	 */
	public static function convertBackToPipes( $s ) {
		return str_replace( "\1", '|', $s );
	}

	/**
	 * Splits the contents of a tag in a form definition based on pipes,
	 * but does not split on pipes that are contained within additional
	 * curly brackets, in case the tag contains any calls to parser
	 * functions or templates.
	 * @param string $string
	 * @return string[]
	 */
	public static function smartSplitFormTag( $string ) {
		if ( $string == '' ) {
			return [];
		}

		$delimiter = '|';
		$returnValues = [];
		$numOpenCurlyBrackets = 0;
		$curReturnValue = '';

		for ( $i = 0; $i < strlen( $string ); $i++ ) {
			$curChar = $string[$i];
			if ( $curChar == '{' ) {
				$numOpenCurlyBrackets++;
			} elseif ( $curChar == '}' ) {
				$numOpenCurlyBrackets--;
			}

			if ( $curChar == $delimiter && $numOpenCurlyBrackets == 0 ) {
				$returnValues[] = trim( $curReturnValue );
				$curReturnValue = '';
			} else {
				$curReturnValue .= $curChar;
			}
		}
		$returnValues[] = trim( $curReturnValue );

		return $returnValues;
	}

	/**
	 * This function is basically equivalent to calling
	 * explode( '|', $str ), except that it doesn't split on pipes
	 * that are within parser function calls - i.e., pipes within
	 * double curly brackets.
	 * @param string $str
	 * @return string[]
	 */
	public static function getFormTagComponents( $str ) {
		// Turn each pipe within double curly brackets into another,
		// unused character (here, "\1"), then do the explode, then
		// convert them back.
		// regex adapted from:
		// https://www.regular-expressions.info/recurse.html
		$pattern = '/{{(?>[^{}]|(?R))*?}}/';
		// needed to fix highlighting - <?
		$str = preg_replace_callback( $pattern, static function ( $match ) {
			$hasPipe = strpos( $match[0], '|' );
			return $hasPipe ? str_replace( "|", "\1", $match[0] ) : $match[0];
		}, $str );
		return array_map( [ self::class, 'convertBackToPipes' ], self::smartSplitFormTag( $str ) );
	}

	/**
	 * Gets the word in the wiki's language for either the value 'yes' or
	 * 'no'.
	 * @param bool $isYes
	 * @return string
	 */
	public static function getWordForYesOrNo( $isYes ) {
		// @TODO - should Page Forms define these messages itself?
		$message = $isYes ? 'htmlform-yes' : 'htmlform-no';
		return wfMessage( $message )->inContentLanguage()->text();
	}

	/**
	 * Like PHP's str_replace(), but only replaces the first occurrence.
	 * @param string $search
	 * @param string $replace
	 * @param string $subject
	 * @return string
	 */
	public static function strReplaceFirst( string $search, string $replace, string $subject ): string {
		$pos = strpos( $subject, $search );
		if ( $pos !== false ) {
			return substr( $subject, 0, $pos ) . $replace . substr( $subject, $pos + strlen( $search ) );
		}
		return $subject;
	}

}
