<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MWException;

/**
 * What the wikitext of a page says about one template: whether the page calls it, the full
 * text of the call and the values of its parameters. It reads text only and needs no
 * MediaWiki state.
 *
 * @ingroup PF
 */
class TemplatePageValues {
	private $mSearchTemplateStr;
	private $mPregMatchTemplateStr;
	private $mFullTextInPage;
	private $mValuesFromPage = [];
	private $mPageCallsThisTemplate = false;

	/**
	 * Set what depends on the template name and on whether the page calls the template.
	 *
	 * @param string $templateName
	 * @param string $existing_page_content
	 */
	public function setPageRelatedInfo( $templateName, $existing_page_content ) {
		// Replace underlines with spaces in template name, to allow for
		// searching on either.
		$this->mSearchTemplateStr = str_replace( '_', ' ', $templateName );
		$this->mPregMatchTemplateStr = str_replace(
			[ '/', '(', ')', '^' ],
			[ '\/', '\(', '\)', '\^' ],
			$this->mSearchTemplateStr );
		$this->mPageCallsThisTemplate = preg_match(
			'/{{' . $this->mPregMatchTemplateStr . '\s*[\|}]/i',
			str_replace( '_', ' ', $existing_page_content )
		);
	}

	/**
	 * Read the first call of the template from the page text: its values and its full text
	 * become available through the getters.
	 *
	 * @param string $templateName
	 * @param string $existing_page_content
	 * @return string The page text without that call; the page text as it was if the page does not
	 *   call the template
	 * @throws MWException if the brackets of the call do not match
	 */
	public function readFirstCall( $templateName, $existing_page_content ) {
		$this->setPageRelatedInfo( $templateName, $existing_page_content );
		if ( !$this->pageCallsThisTemplate() ) {
			return $existing_page_content;
		}
		$this->setFieldValuesFromPage( $existing_page_content );
		return \PFUtils::strReplaceFirst( $this->getFullTextInPage(), '', $existing_page_content );
	}

	/**
	 * Take the value of a field out of the values read from the page, so that what is left are
	 * the parameters no field of the form handles.
	 *
	 * The value of a field that holds an embedded template is the calls of that template. They
	 * are put back at the end of the remaining page text, where the form section of the
	 * embedded template finds them like any other call on the page.
	 *
	 * @param string $field_name
	 * @param bool $holdsTemplate
	 * @param string &$remainingPageText
	 * @return string
	 */
	public function takeValueFromPage( $field_name, $holdsTemplate, &$remainingPageText ) {
		$value = $this->getAndRemoveValueFromPageForField( $field_name );
		if ( $holdsTemplate ) {
			$remainingPageText .= $value;
		}
		return $value;
	}

	public function getPregMatchTemplateStr() {
		return $this->mPregMatchTemplateStr;
	}

	public function setPregMatchTemplateStr( $value ) {
		$this->mPregMatchTemplateStr = $value;
	}

	public function getSearchTemplateStr() {
		return $this->mSearchTemplateStr;
	}

	public function setSearchTemplateStr( $value ) {
		$this->mSearchTemplateStr = $value;
	}

	/**
	 * @return string
	 */
	public function getFullTextInPage() {
		return $this->mFullTextInPage;
	}

	public function pageCallsThisTemplate() {
		return $this->mPageCallsThisTemplate;
	}

	public function hasValueFromPageForField( $field_name ) {
		return array_key_exists( $field_name, $this->mValuesFromPage );
	}

	public function getAndRemoveValueFromPageForField( $field_name ) {
		$value = $this->mValuesFromPage[$field_name];
		unset( $this->mValuesFromPage[$field_name] );
		return $value;
	}

	public function getValuesFromPage() {
		return $this->mValuesFromPage;
	}

	/**
	 * This makes it possible for += and -= to modify values based on existing values.
	 *
	 * @param string $field_name
	 * @param string $new_value
	 * @param string|null $modifier
	 */
	public function changeFieldValues( $field_name, $new_value, $modifier = null ) {
		$this->mValuesFromPage[$field_name] = $new_value;
		if ( $modifier !== null && array_key_exists( $field_name . $modifier, $this->mValuesFromPage ) ) {
			// clean up old values with + or - in them from the array
			unset( $this->mValuesFromPage[$field_name . $modifier] );
		}
	}

	/**
	 * Remove all the bits that should not be parsed - those
	 * contained in <pre> tags, etc. - and place them in an array,
	 * so that they can be added back in later. This will prevent
	 * the brackets, curly braces and pipes within those bits from
	 * interfering with the parsing we need to do.
	 *
	 * @param string $str
	 * @param string[] &$replacements
	 * @return string
	 */
	public static function removeUnparsedText( $str, &$replacements ) {
		$startAndEndTags = [
			[ '<pre', 'pre>' ],
			[ '<syntaxhighlight', 'syntaxhighlight>' ],
			[ '<source', 'source>' ],
			[ '<ref', 'ref>' ],
			[ '<nowiki', 'nowiki>' ]
		];
		foreach ( $startAndEndTags as $tags ) {
			[ $startTag, $endTag ] = $tags;

			$startTagLoc = -1;
			while ( true ) {
				if ( $startTagLoc + strlen( $startTag ) >= strlen( $str ) ) {
					break;
				}
				$startTagLoc = strpos( $str, $startTag, $startTagLoc + strlen( $startTag ) );
				if ( $startTagLoc === false ) {
					break;
				}
				// Ignore "singleton" tags, like '<ref name="abc" />'.
				$possibleSingletonTagEnd = strpos( $str, '/>', $startTagLoc );
				if ( $possibleSingletonTagEnd !== false &&
					$possibleSingletonTagEnd < strpos( $str, '>', $startTagLoc ) ) {
					continue;
				}
				$endTagLoc = strpos( $str, $endTag, $startTagLoc + strlen( $startTag ) );
				// Also ignore unclosed tags.
				if ( $endTagLoc === false ) {
					continue;
				}
				$fullTagTextLength = $endTagLoc + strlen( $endTag ) - $startTagLoc;
				$replacements[] = substr( $str, $startTagLoc, $fullTagTextLength );
				$replacementNum = count( $replacements ) - 1;
				$str = substr_replace( $str, "\1" . $replacementNum . "\2", $startTagLoc, $fullTagTextLength );
			}
		}
		return $str;
	}

	/**
	 * @param string $str
	 * @param string[] $replacements
	 * @return string
	 */
	public static function restoreUnparsedText( $str, $replacements ) {
		foreach ( $replacements as $i => $fullTagText ) {
			$str = str_replace( "\1" . $i . "\2", $fullTagText, $str );
		}
		return $str;
	}

	public function setFieldValuesFromPage( $existing_page_content ) {
		$unparsedTextReplacements = [];
		$existing_page_content = self::removeUnparsedText( $existing_page_content, $unparsedTextReplacements );
		$matches = [];
		$search_pattern = '/{{' . $this->mPregMatchTemplateStr . '\s*[\|}]/i';
		$content_str = str_replace( '_', ' ', $existing_page_content );
		preg_match( $search_pattern, $content_str, $matches, PREG_OFFSET_CAPTURE );
		// is this check necessary?
		if ( array_key_exists( 0, $matches ) && array_key_exists( 1, $matches[0] ) ) {
			$start_char = $matches[0][1];
			$fields_start_char = $start_char + 2 + strlen( $this->mSearchTemplateStr );
			// Skip ahead to the first real character.
			while ( in_array( $existing_page_content[$fields_start_char], [ ' ', '\n' ] ) ) {
				$fields_start_char++;
			}
			// If the next character is a pipe, skip that too.
			if ( $existing_page_content[$fields_start_char] == '|' ) {
				$fields_start_char++;
			}
			$this->mValuesFromPage = [ '0' => '' ];
			// Cycle through template call, splitting it up by pipes ('|'),
			// except when that pipe is part of a piped link.
			$field = "";
			$uncompleted_square_brackets = 0;
			$uncompleted_curly_brackets = 2;
			$template_ended = false;
			for ( $i = $fields_start_char; !$template_ended && ( $i < strlen( $existing_page_content ) ); $i++ ) {
				$c = $existing_page_content[$i];
				if ( $i + 1 < strlen( $existing_page_content ) ) {
					$nextc = $existing_page_content[$i + 1];
				} else {
					$nextc = null;
				}
				if ( $i > 0 ) {
					$prevc = $existing_page_content[$i - 1];
				} else {
					$prevc = null;
				}
				if ( $c == '[' && ( $nextc == '[' || $prevc == '[' ) ) {
					$uncompleted_square_brackets++;
				} elseif ( $c == ']' && ( $nextc == ']' || $prevc == ']' ) && $uncompleted_square_brackets > 0 ) {
					$uncompleted_square_brackets--;
				} elseif ( $c == '{' && ( $nextc == '{' || $prevc == '{' ) ) {
					$uncompleted_curly_brackets++;
				} elseif ( $c == '}' && ( $nextc == '}' || $prevc == '}' ) && $uncompleted_curly_brackets > 0 ) {
					$uncompleted_curly_brackets--;
				}
				// handle an end to a field and/or template declaration
				$template_ended = ( $uncompleted_curly_brackets == 0 && $uncompleted_square_brackets == 0 );
				$field_ended = ( $c == '|' && $uncompleted_square_brackets == 0 && $uncompleted_curly_brackets <= 2 );
				if ( $template_ended || $field_ended ) {
					// If this was the last character in the template, remove
					// the closing curly brackets.
					if ( $template_ended ) {
						$field = substr( $field, 0, -1 );
					}
					$field = self::restoreUnparsedText( $field, $unparsedTextReplacements );
					// Either there's an equals sign near the beginning or not -
					// handling is similar in either way; if there's no equals
					// sign, the index of this field becomes the key.
					$sub_fields = explode( '=', $field, 2 );
					if ( count( $sub_fields ) > 1 ) {
						$this->mValuesFromPage[trim( $sub_fields[0] )] = trim( $sub_fields[1] );
					} else {
						$this->mValuesFromPage[] = trim( $sub_fields[0] );
					}
					$field = '';
				} else {
					$field .= $c;
				}
			}

			// If there are uncompleted opening brackets, the whole form will get messed up -
			// throw an exception.
			// (If there are too many *closing* brackets, some template stuff will end up in
			// the "free text" field - which is bad, but it's harder for the code to detect
			// the problem - though hopefully, easier for users.)
			if ( $uncompleted_curly_brackets > 0 || $uncompleted_square_brackets > 0 ) {
				throw new MWException( "PageFormsMismatchedBrackets" );
			}

			$fullText = substr( $existing_page_content, $start_char, $i - $start_char );
			$this->mFullTextInPage = self::restoreUnparsedText( $fullText, $unparsedTextReplacements );
		}
	}
}
