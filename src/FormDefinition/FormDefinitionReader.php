<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

use PFUtils;

/**
 * Turns a (parsed) form definition into a FormDefinition. This is the one place that
 * scans a form definition for {{{...}}} tags.
 */
class FormDefinitionReader {

	/**
	 * @param string $formDef Form definition wikitext, with the tags still in place
	 *   (as returned by FormCache::getFormDefinition())
	 * @return FormDefinition
	 * @throws FormDefinitionException If a tag is missing its closing braces
	 */
	public function read( string $formDef ): FormDefinition {
		$definition = new FormDefinition();
		$position = 0;

		$tag = $this->findNextTag( $formDef, $position );
		while ( $tag !== null ) {
			if ( count( $tag['components'] ) === 0 ) {
				break;
			}

			if ( $tag['start'] > $position ) {
				$definition->addElement( new TextSpec( substr( $formDef, $position, $tag['start'] - $position ) ) );
			}
			$definition->addElement( $this->newTag(
				$tag['components'], substr( $formDef, $tag['start'], $tag['end'] - $tag['start'] )
			) );
			$position = $tag['end'];
			$tag = $this->findNextTag( $formDef, $position );
		}

		if ( $position < strlen( $formDef ) ) {
			$definition->addElement( new TextSpec( substr( $formDef, $position ) ) );
		}
		return $definition;
	}

	/**
	 * Find the next {{{...}}} tag at or after $offset. This is the single place that knows where a
	 * tag begins and ends; everything that walks a form definition goes through it.
	 *
	 * @param string $text
	 * @param int $offset
	 * @return array{start: int, end: int, components: list<string>}|null The position of the opening
	 *   braces, the position just after the closing braces, and the tag split at its top-level pipes
	 *   (empty for a tag without content); null if there is no further tag
	 * @throws FormDefinitionException If the tag is missing its closing braces
	 */
	public function findNextTag( string $text, int $offset = 0 ): ?array {
		$open = strpos( $text, '{{{', $offset );
		if ( $open === false ) {
			return null;
		}
		$end = $this->findTagEnd( $text, $open );
		return [
			'start' => $open,
			'end' => $end,
			'components' => PFUtils::getFormTagComponents( substr( $text, $open + 3, $end - 3 - ( $open + 3 ) ) ),
		];
	}

	/**
	 * Find the position just after the closing braces of the tag that starts at $open.
	 *
	 * The tag can contain braces of its own ({{{field|f|default={{PAGENAME}}}}}), and it can sit
	 * at the end of a parser function or a template call ({{#if:x|{{{standard input|save}}}}}).
	 * Single braces occur too, in a JSON value (value_labels={"a":"b"}}}}). In all of these a run of
	 * closing braces is ambiguous, so the braces are matched with their openers: a "}}}" closes
	 * the innermost "{{{", a "}}" the innermost "{{", a "}" the innermost "{".
	 *
	 * @param string $text
	 * @param int $open Position of the tag's opening braces
	 * @return int
	 * @throws FormDefinitionException If the tag is missing its closing braces
	 */
	private function findTagEnd( string $text, int $open ): int {
		$length = strlen( $text );
		// The width of each opener that is still open: 3 for "{{{", 2 for "{{", 1 for "{"
		$openers = [ 3 ];
		$position = $open + 3;
		while ( $position < $length ) {
			if ( $text[$position] === '{' ) {
				$width = $this->openingWidth( $text, $position );
				$openers[] = $width;
				$position += $width;
				continue;
			}
			$width = end( $openers );
			if ( substr_compare( $text, str_repeat( '}', $width ), $position, $width ) === 0 ) {
				array_pop( $openers );
				$position += $width;
				if ( $openers === [] ) {
					return $position;
				}
			} else {
				$position++;
			}
		}

		// The braces do not match up. Be lenient and end the tag at the last of the first run of
		// closing braces, as the matching never did.
		$close = strpos( $text, '}}}', $open );
		if ( $close === false ) {
			throw new FormDefinitionException(
				"Error in form definition! The following tag is missing its closing '}}}':",
				substr( $text, $open )
			);
		}
		while ( ( $text[$close + 3] ?? '' ) === '}' ) {
			$close++;
		}
		return $close + 3;
	}

	/**
	 * @return int How many braces, 1 to 3, open a construct at $position
	 */
	private function openingWidth( string $text, int $position ): int {
		if ( substr_compare( $text, '{{{', $position, 3 ) === 0 ) {
			return 3;
		}
		return substr_compare( $text, '{{', $position, 2 ) === 0 ? 2 : 1;
	}

	/**
	 * Angled brackets in a tag could cause a security leak (and should not be necessary).
	 *
	 * @param list<string> $components
	 * @throws FormDefinitionException If a component contains both < and >
	 */
	private function assertNoForbiddenCharacters( array $components ): void {
		foreach ( $components as $component ) {
			// Allow them in "default filename", though.
			$parts = explode( '=', $component, 2 );
			if ( count( $parts ) == 2 && $parts[0] == 'default filename' ) {
				continue;
			}
			if ( str_contains( $component, '<' ) && str_contains( $component, '>' ) ) {
				throw new FormDefinitionException(
					'Error in form definition! The following field tag contains forbidden characters:',
					$component
				);
			}
		}
	}

	/**
	 * @param list<string> $components
	 * @param string $raw
	 * @return TagSpec
	 */
	private function newTag( array $components, string $raw ): TagSpec {
		if ( trim( $components[0] ) !== InfoSpec::TYPE ) {
			$this->assertNoForbiddenCharacters( $components );
		}
		switch ( trim( $components[0] ) ) {
			case TemplateSpec::TYPE:
				return new TemplateSpec( $components );
			case EndTemplateSpec::TYPE:
				return new EndTemplateSpec( $components );
			case FieldSpec::TYPE:
				return new FieldSpec( $components );
			case SectionSpec::TYPE:
				return new SectionSpec( $components );
			case StandardInputSpec::TYPE:
				return new StandardInputSpec( $components );
			case InfoSpec::TYPE:
				return new InfoSpec( $components );
			default:
				return new UnknownTagSpec( $components, $raw );
		}
	}
}
