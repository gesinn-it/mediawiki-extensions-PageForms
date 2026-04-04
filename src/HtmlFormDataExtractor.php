<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use DOMDocument;

/**
 * Parses the HTML fragment produced by PFFormPrinter::formHTML() and extracts
 * the field values encoded in it as a nested array suitable for merging into
 * PFAutoeditAPI::$mOptions.
 *
 * This class has no MediaWiki dependencies and can be unit-tested directly
 * without a database or API context.
 */
class HtmlFormDataExtractor {

	/**
	 * Extract form field values from an HTML fragment.
	 *
	 * Processes <input>, <select>, and <textarea> elements. Disabled or
	 * restricted fields are removed from $mOptions (passed by reference) so
	 * that a subsequent merge cannot re-introduce protected values.
	 *
	 * @param string $html HTML fragment (as produced by formHTML())
	 * @param array &$mOptions Autoedit options array; disabled fields are
	 *   unset from it as a security measure.
	 * @return array Nested array of extracted field values, keyed by
	 *   template name and field name.
	 */
	public static function extract( string $html, array &$mOptions = [] ): array {
		$data = [];
		$doc = new DOMDocument();

		$oldVal = false;
		if ( LIBXML_VERSION < 20900 ) {
			// PHP < 8
			$oldVal = libxml_disable_entity_loader( true );
		}

		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@$doc->loadHTML(
			'<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"' .
			' "http://www.w3.org/TR/REC-html40/loose.dtd">' .
			'<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/></head><body>'
			. $html
			. '</body></html>'
		);

		if ( LIBXML_VERSION < 20900 ) {
			// PHP < 8
			libxml_disable_entity_loader( $oldVal );
		}

		// Process input tags.
		$inputs = $doc->getElementsByTagName( 'input' );

		for ( $i = 0; $i < $inputs->length; $i++ ) {
			$input = $inputs->item( $i );
			'@phan-var \DOMElement $input';/** @var \DOMElement $input */
			$type = $input->getAttribute( 'type' );
			$name = trim( $input->getAttribute( 'name' ) );

			if ( !$name ) {
				continue;
			}
			if ( $input->hasAttribute( 'disabled' ) ) {
				// Remove fields from mOptions which are restricted or disabled
				// so that they do not get edited in an #autoedit call.
				$restrictedField = preg_split( "/[\[\]]/", $name, -1, PREG_SPLIT_NO_EMPTY );
				if ( $restrictedField && count( $restrictedField ) > 1 ) {
					unset( $mOptions[$restrictedField[0]][$restrictedField[1]] );
				}
				continue;
			}

			if ( $type === '' ) {
				$type = 'text';
			}

			switch ( $type ) {
				case 'checkbox':
				case 'radio':
					if ( $input->hasAttribute( 'checked' ) ) {
						self::addToArray( $data, $name, $input->getAttribute( 'value' ) );
					}
					break;

				// case 'button':
				case 'hidden':
				case 'image':
				case 'password':
				case 'date':
				case 'datetime':
				// case 'reset':
				// case 'submit':
				case 'text':
					self::addToArray( $data, $name, $input->getAttribute( 'value' ) );
					break;
			}
		}

		// Process select tags
		$selects = $doc->getElementsByTagName( 'select' );

		for ( $i = 0; $i < $selects->length; $i++ ) {
			$select = $selects->item( $i );
			'@phan-var \DOMElement $select';/** @var \DOMElement $select */
			$name = trim( $select->getAttribute( 'name' ) );

			if ( !$name || $select->hasAttribute( 'disabled' ) ) {
				// Remove fields from mOptions which are restricted or disabled
				// so that they do not get edited in an #autoedit call.
				$restrictedField = preg_split( "/[\[\]]/", $name, -1, PREG_SPLIT_NO_EMPTY );
				if ( $restrictedField ) {
					unset( $mOptions[$restrictedField[0]][$restrictedField[1]] );
				}
				continue;
			}

			$options = $select->getElementsByTagName( 'option' );

			// If the current $select is a radio button select
			// (i.e. not multiple) set the first option to selected
			// as default. This may be overwritten in the loop below.
			if ( $options->length > 0 && ( !$select->hasAttribute( 'multiple' ) ) ) {
				$firstOption = $options->item( 0 );
				'@phan-var \DOMElement $firstOption';/** @var \DOMElement $firstOption */
				self::addToArray( $data, $name, $firstOption->getAttribute( 'value' ) );
			}

			for ( $o = 0; $o < $options->length; $o++ ) {
				$option = $options->item( $o );
				'@phan-var \DOMElement $option';/** @var \DOMElement $option */
				if ( $option->hasAttribute( 'selected' ) ) {
					if ( $option->getAttribute( 'value' ) ) {
						self::addToArray( $data, $name, $option->getAttribute( 'value' ) );
					} else {
						self::addToArray( $data, $name, $option->nodeValue );
					}
				}
			}
		}

		// Process textarea tags
		$textareas = $doc->getElementsByTagName( 'textarea' );

		for ( $i = 0; $i < $textareas->length; $i++ ) {
			$textarea = $textareas->item( $i );
			'@phan-var \DOMElement $textarea';/** @var \DOMElement $textarea */
			$name = trim( $textarea->getAttribute( 'name' ) );

			if ( !$name ) {
				continue;
			}

			self::addToArray( $data, $name, $textarea->textContent );
		}

		return $data;
	}

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

			// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
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
