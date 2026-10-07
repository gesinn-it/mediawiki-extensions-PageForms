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
	 */
	public function read( string $formDef ): FormDefinition {
		$definition = new FormDefinition();
		$template = null;

		foreach ( $this->tokenize( $formDef ) as $components ) {
			$tagTitle = trim( $components[0] );

			if ( $tagTitle === 'for template' ) {
				// A new "for template" closes a block that was never ended.
				$template = new TemplateSpec( $components );
				$definition->addTemplate( $template );
			} elseif ( $tagTitle === 'end template' ) {
				$template = null;
			} elseif ( $tagTitle === 'field' && count( $components ) > 1 ) {
				// Fields outside a template have nothing to be read into.
				$template?->addField( new FieldSpec( $components ) );
			} elseif ( $tagTitle === 'standard input' && trim( $components[1] ?? '' ) === 'free text' ) {
				$definition->setHasFreeText( true );
				$template?->addField( FieldSpec::freeText() );
			}
		}

		return $definition;
	}

	/**
	 * @param string $formDef
	 * @return iterable<list<string>> The components of each tag, in order of appearance
	 */
	private function tokenize( string $formDef ): iterable {
		$start = 0;
		while ( true ) {
			$open = strpos( $formDef, '{{{', $start );
			if ( $open === false ) {
				return;
			}
			$close = strpos( $formDef, '}}}', $open );
			if ( $close === false ) {
				return;
			}
			$components = PFUtils::getFormTagComponents( substr( $formDef, $open + 3, $close - ( $open + 3 ) ) );
			if ( count( $components ) === 0 ) {
				return;
			}
			yield $components;
			$start = $open + 1;
		}
	}
}
