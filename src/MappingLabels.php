<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;
use PFValuesUtils;
use RequestContext;
use Title;

/**
 * The labels a field shows instead of its values, for the "mapping template" and
 * "mapping property" field arguments.
 *
 * The labels of a "mapping template" are remembered per template revision, so that every
 * field mapped by the same version of the template (such as the instances of a
 * multiple-instance template) expands each value once. The memory belongs to the
 * object: the FormPrinter creates one, which lives as long as the FormPrinter does.
 *
 * @ingroup PF
 */
class MappingLabels {

	/** @var array<string, string> Labels built so far, by template, revision and value */
	private array $templateLabels = [];

	/**
	 * The labels of values given by a mapping template: the template is called with the value
	 * as its first parameter and what it expands to is the label. A value whose label is
	 * empty (or a template that does not exist) is its own label.
	 *
	 * @param string $templateName
	 * @param string[]|int[] $values
	 * @return array<string, string> Label by value
	 */
	public function forTemplate( string $templateName, array $values ): array {
		$title = Title::makeTitleSafe( NS_TEMPLATE, $templateName );
		$templateExists = $title->exists();
		// See PFUtils::ensureParserReadyForTagParse() for why the global Parser
		// singleton needs both initialization and an output-type/title reset
		// before a recursiveTagParse() call like the one below.
		$parser = PFUtils::ensureParserReadyForTagParse(
			PFUtils::getParser(), RequestContext::getMain()->getUser(), RequestContext::getMain()->getTitle()
		);
		$prefix = '';
		if ( $templateExists ) {
			// The label of a value is the same for every field mapped by this version of the
			// template.
			$prefix = $templateName . "\0" . $title->getLatestRevID() . "\0";
			$missing = [];
			foreach ( $values as $value ) {
				if ( !isset( $this->templateLabels[$prefix . $value] ) ) {
					$missing[$value] = true;
				}
			}
			$this->parseLabels( $parser, $templateName, $prefix, array_map( 'strval', array_keys( $missing ) ) );
		}
		$labels = [];
		foreach ( $values as $value ) {
			$label = $templateExists ? $this->templateLabels[$prefix . $value] : '';
			$labels[$value] = $label == '' ? $value : $label;
		}
		return $labels;
	}

	/**
	 * Fills the label memory for the given values. Values that are plain text are expanded
	 * together, many per parser call, because the call overhead dominates for long lists.
	 * Anything that could affect its neighbours (markup, braces) is expanded on its own,
	 * as is every value if a batch does not come back in the expected pieces.
	 *
	 * @param \Parser $parser
	 * @param string $templateName
	 * @param string $prefix Memory key prefix (template, revision)
	 * @param string[] $values
	 */
	private function parseLabels( $parser, string $templateName, string $prefix, array $values ): void {
		$separator = '@@PFMAPSEP@@';
		$batchable = [];
		foreach ( $values as $value ) {
			if ( preg_match( '/[{}\[\]<>~\n@&]/', $value ) ) {
				$this->templateLabels[$prefix . $value] =
					trim( $parser->recursiveTagParse( '{{' . $templateName . '|' . $value . '}}' ) );
			} else {
				$batchable[] = $value;
			}
		}
		foreach ( array_chunk( $batchable, 100 ) as $chunk ) {
			$wikitext = '';
			foreach ( $chunk as $value ) {
				$wikitext .= '{{' . $templateName . '|' . $value . "}}\n" . $separator . "\n";
			}
			$pieces = explode( $separator, $parser->recursiveTagParse( $wikitext ) );
			array_pop( $pieces );
			if ( count( $pieces ) !== count( $chunk ) ) {
				$pieces = [];
				foreach ( $chunk as $value ) {
					$pieces[] = $parser->recursiveTagParse( '{{' . $templateName . '|' . $value . '}}' );
				}
			}
			foreach ( $chunk as $i => $value ) {
				$this->templateLabels[$prefix . $value] = trim( $pieces[$i] );
			}
		}
	}

	/**
	 * The labels of values given by a semantic property of the page named by the value: the
	 * first value of the property on that page. A value without one is its own label.
	 *
	 * @param \SMW\Store $store
	 * @param string $propertyName
	 * @param string[]|int[] $values
	 * @return array<string, string> Label by value
	 */
	public function forProperty( $store, string $propertyName, array $values ): array {
		$labels = [];
		foreach ( $values as $value ) {
			$labels[$value] = $value;
			$subject = Title::newFromText( (string)$value );
			if ( $subject != null ) {
				$vals = PFValuesUtils::getSMWPropertyValues( $store, $subject, $propertyName );
				if ( count( $vals ) > 0 ) {
					$labels[$value] = trim( $vals[0] );
				}
			}
		}
		return $labels;
	}
}
