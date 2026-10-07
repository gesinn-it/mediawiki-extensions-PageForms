<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

use MWException;
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
	 * @throws MWException If a tag is missing its closing braces
	 */
	public function read( string $formDef ): FormDefinition {
		$definition = new FormDefinition();
		$position = 0;

		while ( true ) {
			$open = strpos( $formDef, '{{{', $position );
			if ( $open === false ) {
				break;
			}
			$close = strpos( $formDef, '}}}', $open );
			if ( $close === false ) {
				throw new MWException(
					'<div class="error">Error in form definition!'
					. ' The following tag is missing its closing \'}}}\':</div>'
					. "\n<pre>" . htmlspecialchars( substr( $formDef, $open ) ) . "</pre>"
				);
			}
			// For cases with more than 3 ending brackets, take the last 3 ones as the tag end.
			while ( ( $formDef[$close + 3] ?? '' ) === '}' ) {
				$close++;
			}
			$components = PFUtils::getFormTagComponents( substr( $formDef, $open + 3, $close - ( $open + 3 ) ) );
			if ( count( $components ) === 0 ) {
				break;
			}

			if ( $open > $position ) {
				$definition->addElement( new TextSpec( substr( $formDef, $position, $open - $position ) ) );
			}
			$definition->addElement(
				$this->newTag( $components, substr( $formDef, $open, $close + 3 - $open ) )
			);
			$position = $close + 3;
		}

		if ( $position < strlen( $formDef ) ) {
			$definition->addElement( new TextSpec( substr( $formDef, $position ) ) );
		}
		return $definition;
	}

	/**
	 * @param list<string> $components
	 * @param string $raw
	 * @return TagSpec
	 */
	private function newTag( array $components, string $raw ): TagSpec {
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
