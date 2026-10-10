<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

use MediaWiki\Extension\PageForms\TemplateField;
use PFUtils;

/**
 * Finds the fields of a template, along with the semantic property attached to each one (if
 * any), by parsing the text of the template.
 *
 * The fields are found in this order, and one that is found by an earlier step is not recorded
 * again by a later one: the properties of "#arraymap" calls, normal property calls, "#set",
 * "#set_internal" and "#subobject" calls, "#declare" calls, the fields without a property, and
 * the fields that "#template_params" declares and the text did not have.
 *
 * The parser reads text and does not touch the wiki.
 */
class TemplateFieldParser {

	/** @var callable|null */
	private $onBacktrackLimitExceeded;

	/**
	 * @param callable|null $onBacktrackLimitExceeded Called if the text is too complex for the
	 *  regular expression of the "#arraymap" calls
	 */
	public function __construct( ?callable $onBacktrackLimitExceeded = null ) {
		$this->onBacktrackLimitExceeded = $onBacktrackLimitExceeded;
	}

	/**
	 * @param string $templateText The text of the template, without its "noinclude" parts
	 * @param array|null $templateParams The values of its "#template_params" call, if it has one
	 * @return TemplateField[] In the order in which they were found. A field of the text has the
	 *  position of its name in the text as its key, a field of the "#template_params" its name.
	 */
	public function parse( string $templateText, ?array $templateParams ): array {
		$found = new FoundTemplateFields();

		// Replace all calls to #set within #arraymap with standard
		// SMW tags. This is done so that they will later get
		// parsed correctly.
		// This is "cheating", since it modifies the template text
		// (the rest of the function doesn't do that), but trying to
		// get the #arraymap check regexp to find both kinds of SMW
		// property tags seemed too hard to do.
		$text = (string)preg_replace(
			'/#arraymap.*{{\s*#set:\s*([^=]*)=([^}]*)}}/',
			'[[$1:$2]]',
			$templateText
		);

		$this->findArraymapProperties( $text, $found );
		$this->findPropertyCalls( $text, $found );
		$this->findSetCalls( $text, $found );
		$this->findDeclares( $text, $found );
		$this->findFieldsWithoutProperty( $text, $found );
		if ( $templateParams !== null ) {
			$this->findTemplateParams( $templateParams, $found );
		}
		return $found->fields();
	}

	/**
	 * For a field name and its attached property name located in the text of a template, creates
	 * the field and its key.
	 *
	 * @param string $templateText
	 * @param string $fieldName
	 * @param string $propertyName
	 * @param bool $isList
	 * @return array [ the position of the field in the text, the TemplateField ]. The position is
	 *  that of the name followed by "|", or if there is none that of the name followed by "}",
	 *  as in "{{{Name}}}".
	 */
	public function propertyField(
		string $templateText, string $fieldName, string $propertyName, bool $isList
	): array {
		$templateField = TemplateField::create(
			$fieldName, PFUtils::getContLang()->ucfirst( $fieldName ), $propertyName,
			$isList
		);
		$position = stripos( $templateText, $fieldName . '|' );
		if ( $position === false ) {
			$position = stripos( $templateText, $fieldName . '}' );
		}
		return [ (int)$position, $templateField ];
	}

	private function findProperty(
		string $text, string $fieldName, string $propertyName, bool $isList, FoundTemplateFields $found
	): void {
		[ $position, $field ] = $this->propertyField( $text, $fieldName, $propertyName, $isList );
		$found->add( $fieldName, $position, $field );
	}

	/**
	 * Look for "arraymap" parser function calls that map a property onto a list.
	 */
	private function findArraymapProperties( string $text, FoundTemplateFields $found ): void {
		$ret = preg_match_all(
			'/{{#arraymap:{{{([^|}]*:?[^|}]*)[^\[]*\[\[([^:]*:?[^:]*)::/mis',
			$text,
			$matches
		);
		if ( $ret ) {
			foreach ( $matches[1] as $i => $fieldName ) {
				if ( !$found->has( $fieldName ) ) {
					$this->findProperty( $text, $fieldName, $matches[2][$i], true, $found );
				}
			}
		} elseif ( $ret === false && preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR
			&& $this->onBacktrackLimitExceeded !== null ) {
			// There was an error in the preg_match_all()
			// call - let the user know about it.
			( $this->onBacktrackLimitExceeded )();
		}
	}

	/**
	 * Look for normal property calls.
	 */
	private function findPropertyCalls( string $text, FoundTemplateFields $found ): void {
		if ( !preg_match_all(
			'/\[\[([^:|\[\]]*:*?[^:|\[\]]*)::{{{([^\]\|}]*).*?\]\]/mis',
			$text,
			$matches
		) ) {
			return;
		}
		foreach ( $matches[1] as $i => $propertyName ) {
			$fieldName = trim( $matches[2][$i] );
			if ( !$found->has( $fieldName ) ) {
				$this->findProperty( $text, $fieldName, trim( $propertyName ), false, $found );
			}
		}
	}

	/**
	 * Look for calls to #set, #set_internal and #subobject. (Thankfully, they all have similar
	 * syntax).
	 */
	private function findSetCalls( string $text, FoundTemplateFields $found ): void {
		if ( !preg_match_all( '/#(set|set_internal|subobject):(.*?}}})\s*}}/mis', $text, $matches ) ) {
			return;
		}
		foreach ( $matches[2] as $match ) {
			if ( !preg_match_all( '/([^|{]*?)=\s*{{{([^|}]*)/mis', $match, $matches2 ) ) {
				continue;
			}
			foreach ( $matches2[1] as $i => $propertyName ) {
				$fieldName = trim( $matches2[2][$i] );
				if ( !$found->has( $fieldName ) ) {
					$this->findProperty( $text, $fieldName, trim( $propertyName ), false, $found );
				}
			}
		}
	}

	/**
	 * Look for calls to #declare. (This is really rather optional, since no one seems to use
	 * #declare.)
	 */
	private function findDeclares( string $text, FoundTemplateFields $found ): void {
		if ( !preg_match_all( '/#declare:(.*?)}}/mis', $text, $matches ) ) {
			return;
		}
		foreach ( $matches[1] as $match ) {
			foreach ( explode( '|', $match ) as $valuePair ) {
				$keyAndVal = explode( '=', $valuePair );
				if ( count( $keyAndVal ) != 2 ) {
					continue;
				}
				$propertyName = trim( $keyAndVal[0] );
				$fieldName = trim( $keyAndVal[1] );
				if ( !$found->has( $fieldName ) ) {
					$this->findProperty( $text, $fieldName, $propertyName, false, $found );
				}
			}
		}
	}

	/**
	 * Get any non-semantic fields defined.
	 */
	private function findFieldsWithoutProperty( string $text, FoundTemplateFields $found ): void {
		if ( !preg_match_all( '/{{{([^|}]*)/mis', $text, $matches ) ) {
			return;
		}
		foreach ( $matches[1] as $fieldName ) {
			$fieldName = trim( $fieldName );
			if ( $fieldName !== '' && !$found->has( $fieldName ) ) {
				$found->add(
					$fieldName,
					(int)stripos( $text, $fieldName ),
					TemplateField::create( $fieldName, PFUtils::getContLang()->ucfirst( $fieldName ) )
				);
			}
		}
	}

	/**
	 * If #template_params was declared for this template, go through the declared fields, and,
	 * for any that were not already found by parsing the template, add them.
	 *
	 * @todo it would be good to combine the #template_params data with any SMW data found,
	 * instead of just getting one or the other. In practice, though, it doesn't really matter.
	 */
	private function findTemplateParams( array $templateParams, FoundTemplateFields $found ): void {
		foreach ( $templateParams as $fieldName => $fieldParams ) {
			if ( !$found->has( (string)$fieldName ) ) {
				$found->add( (string)$fieldName, $fieldName, TemplateField::newFromParams( $fieldName, $fieldParams ) );
			}
		}
	}
}
