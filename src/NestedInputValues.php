<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Builds the nested array of field values that PFAutoeditAPI::$mOptions holds from input
 * names such as "Template[field]".
 *
 * This class has no MediaWiki dependencies and can be unit-tested directly
 * without a database or API context.
 */
class NestedInputValues {

	/**
	 * Recursively inserts $value into $array at the path described by $key.
	 *
	 * Key format: `TemplateName[fieldName]` or `TemplateName[fieldName][subKey]`.
	 * Top-level keys have their spaces replaced with underscores to match the
	 * encoding MediaWiki applies to form input names.
	 *
	 * @param array &$array Root array to insert into
	 * @param string $key Dot-bracket path, e.g. "MyTpl[field]"
	 * @param mixed $value Value to insert
	 * @param bool $toplevel Whether this is a top-level call (affects key normalisation)
	 */
	public static function addToArray( array &$array, string $key, $value, bool $toplevel = true ): void {
		$matches = [];
		if ( preg_match( '/^([^\[\]]*)\[([^\[\]]*)\](.*)/', $key, $matches ) ) {
			// for some reason toplevel keys get their spaces encoded by MW.
			// We have to imitate that.
			if ( $toplevel ) {
				$key = str_replace( ' ', '_', $matches[1] );
			} else {
				if ( is_numeric( $matches[1] ) && isset( $matches[2] ) ) {
					// Multiple instances are indexed like 0a,1a,2a... to differentiate
					// the inputs the form starts out with from any inputs added by the Javascript.
					// Append the character "a" only if the instance number is numeric.
					// If the key(i.e. the instance) doesn't exists then the numerically next
					// instance is created whatever be the key.
					$key = $matches[1] . 'a';
				} else {
					$key = $matches[1];
				}
			}
			// if subsequent element does not exist yet or is a string (we prefer arrays over strings)
			if ( !array_key_exists( $key, $array ) || is_string( $array[$key] ) ) {
				$array[$key] = [];
			}

			self::addToArray( $array[$key], $matches[2] . $matches[3], $value, false );
		} else {
			if ( $key ) {
				// only add the string value if there is no child array present
				if ( !array_key_exists( $key, $array ) || !is_array( $array[$key] ) ) {
					$array[$key] = $value;
				}
			} else {
				array_push( $array, $value );
			}
		}
	}
}
