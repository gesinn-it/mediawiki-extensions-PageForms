<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\TemplateText\TemplateWikitextWriter;
use MediaWiki\MediaWikiServices;
use PFUtils;
use StringUtils;
use Title;

/**
 * Defines a class, Template, that represents a MediaWiki "infobox"
 * template that holds structured data, which may be stored by Semantic MediaWiki.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class Template {
	private $mTemplateName;
	private $mTemplateText;
	private $mTemplateFields;
	private $mTemplateParams;
	private $mConnectingProperty;
	private $mCategoryName;
	private $mAggregatingProperty;
	private $mAggregationLabel;
	private $mTemplateFormat;
	private $mFullWikiText;

	public function __construct( $templateName, $templateFields ) {
		$this->mTemplateName = $templateName;
		$this->mTemplateFields = $templateFields;
	}

	public static function newFromName( $templateName ) {
		$template = new Template( $templateName, [] );
		$template->loadTemplateParams();
		$template->loadTemplateFields();
		return $template;
	}

	/**
	 * Get (and store in memory) the values from this template's
	 * #template_params call, if it exists.
	 */
	public function loadTemplateParams() {
		$embeddedTemplate = null;
		$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $this->mTemplateName );
		if ( $templateTitle === null ) {
			return;
		}
		$services = MediaWikiServices::getInstance();
		$properties = $services->getPageProps()->getProperties(
			[ $templateTitle ], [ 'PageFormsTemplateParams' ]
		);
		if ( count( $properties ) == 0 ) {
			return;
		}

		$paramsForPage = reset( $properties );
		$paramsForProperty = reset( $paramsForPage );
		$this->mTemplateParams = self::safeUnserializeTemplateParams( $paramsForProperty );
	}

	/**
	 * Safely deserialize the value stored in the 'PageFormsTemplateParams'
	 * page property, disallowing object deserialization to prevent PHP
	 * object injection, and failing gracefully (returning null) on a
	 * malformed payload instead of emitting warnings or a fatal error.
	 *
	 * @param string $serialized
	 * @return array|null
	 */
	private static function safeUnserializeTemplateParams( $serialized ) {
		set_error_handler( static function () {
			// Suppress unserialize()'s E_WARNING/E_NOTICE for malformed input;
			// the is_array() check below already handles the failure case.
			return true;
		} );
		try {
			$result = unserialize( $serialized, [ 'allowed_classes' => false ] );
		} finally {
			restore_error_handler();
		}
		return is_array( $result ) ? $result : null;
	}

	public function getTemplateParams() {
		return $this->mTemplateParams;
	}

	/**
	 * @todo - fix so that this function only gets called once per
	 * template; right now it seems to get called once per field. (!)
	 */
	public function loadTemplateFields() {
		$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $this->mTemplateName );
		if ( $templateTitle === null ) {
			return;
		}

		$templateText = PFUtils::getPageText( $templateTitle ) ?? '';
		// Ignore 'noinclude' sections and 'includeonly' tags.
		$templateText = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $templateText );
		$this->mTemplateText = strtr( $templateText, [ '<includeonly>' => '', '</includeonly>' => '' ] );

		$this->loadTemplateFieldsSMWAndOther();
	}

	/**
	 * Get the fields of the template, along with the semantic property
	 * attached to each one (if any), by parsing the text of the template.
	 */
	public function loadTemplateFieldsSMWAndOther() {
		$templateFields = [];
		$fieldNamesArray = [];

		// The way this works is that fields are found and then stored
		// in an array based on their location in the template text, so
		// that they can be returned in the order in which they appear
		// in the template, not the order in which they were found.
		// Some fields can be found more than once (especially if
		// they're part of an "#if" statement), so they're only
		// recorded the first time they're found.

		// Replace all calls to #set within #arraymap with standard
		// SMW tags. This is done so that they will later get
		// parsed correctly.
		// This is "cheating", since it modifies the template text
		// (the rest of the function doesn't do that), but trying to
		// get the #arraymap check regexp to find both kinds of SMW
		// property tags seemed too hard to do.
		$this->mTemplateText = preg_replace(
			'/#arraymap.*{{\s*#set:\s*([^=]*)=([^}]*)}}/',
			'[[$1:$2]]',
			$this->mTemplateText
		);

		// Look for "arraymap" parser function calls that map a
		// property onto a list.
		$ret = preg_match_all(
			'/{{#arraymap:{{{([^|}]*:?[^|}]*)[^\[]*\[\[([^:]*:?[^:]*)::/mis',
			$this->mTemplateText,
			$matches
		);
		if ( $ret ) {
			foreach ( $matches[1] as $i => $field_name ) {
				if ( !in_array( $field_name, $fieldNamesArray ) ) {
					$propertyName = $matches[2][$i];
					$this->loadPropertySettingInTemplate( $field_name, $propertyName, true );
					$fieldNamesArray[] = $field_name;
				}
			}
		} elseif ( $ret === false ) {
			// There was an error in the preg_match_all()
			// call - let the user know about it.
			if ( preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR ) {
					print 'Page Forms error: backtrace limit exceeded during parsing!' .
						' Please increase the value of <a href="http://www.php.net/manual/en/' .
						'pcre.configuration.php#ini.pcre.backtrack-limit">pcre.backtrack_limit' .
						'</a> in php.ini or LocalSettings.php.';
			}
		}

		// Look for normal property calls.
		if ( preg_match_all(
			'/\[\[([^:|\[\]]*:*?[^:|\[\]]*)::{{{([^\]\|}]*).*?\]\]/mis',
			$this->mTemplateText,
			$matches
		) ) {
			foreach ( $matches[1] as $i => $propertyName ) {
				$field_name = trim( $matches[2][$i] );
				if ( !in_array( $field_name, $fieldNamesArray ) ) {
					$propertyName = trim( $propertyName );
					$this->loadPropertySettingInTemplate( $field_name, $propertyName, false );
					$fieldNamesArray[] = $field_name;
				}
			}
		}

		// Then, get calls to #set, #set_internal and #subobject.
		// (Thankfully, they all have similar syntax).
		if ( preg_match_all( '/#(set|set_internal|subobject):(.*?}}})\s*}}/mis', $this->mTemplateText, $matches ) ) {
			foreach ( $matches[2] as $match ) {
				if ( preg_match_all( '/([^|{]*?)=\s*{{{([^|}]*)/mis', $match, $matches2 ) ) {
					foreach ( $matches2[1] as $i => $propertyName ) {
						$fieldName = trim( $matches2[2][$i] );
						if ( !in_array( $fieldName, $fieldNamesArray ) ) {
							$propertyName = trim( $propertyName );
							$this->loadPropertySettingInTemplate( $fieldName, $propertyName, false );
							$fieldNamesArray[] = $fieldName;
						}
					}
				}
			}
		}

		// Then, get calls to #declare. (This is really rather
		// optional, since no one seems to use #declare.)
		if ( preg_match_all( '/#declare:(.*?)}}/mis', $this->mTemplateText, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$setValues = explode( '|', $match );
				foreach ( $setValues as $valuePair ) {
					$keyAndVal = explode( '=', $valuePair );
					if ( count( $keyAndVal ) == 2 ) {
						$propertyName = trim( $keyAndVal[0] );
						$fieldName = trim( $keyAndVal[1] );
						if ( !in_array( $fieldName, $fieldNamesArray ) ) {
							$this->loadPropertySettingInTemplate( $fieldName, $propertyName, false );
							$fieldNamesArray[] = $fieldName;
						}
					}
				}
			}
		}

		// Finally, get any non-semantic fields defined.
		if ( preg_match_all( '/{{{([^|}]*)/mis', $this->mTemplateText, $matches ) ) {
			foreach ( $matches[1] as $fieldName ) {
				$fieldName = trim( $fieldName );
				if ( $fieldName !== '' && ( !in_array( $fieldName, $fieldNamesArray ) ) ) {
					$cur_pos = stripos( $this->mTemplateText, $fieldName );
					$this->mTemplateFields[$cur_pos] = TemplateField::create(
						$fieldName, PFUtils::getContLang()->ucfirst( $fieldName )
					);
					$fieldNamesArray[] = $fieldName;
				}
			}
		}

		// If #template_params was declared for this template, go
		// through the declared fields, and, for any that were not
		// already found by parsing the template, populate
		// $mTemplateFields with it.
		// @todo - it would be good to combine the #template_params
		// data with any SMW data found, instead of just getting one
		// or the other. In practice, though, it doesn't really matter.
		if ( $this->mTemplateParams !== null ) {
			foreach ( $this->mTemplateParams as $fieldName => $fieldParams ) {
				if ( in_array( $fieldName, $fieldNamesArray ) ) {
					continue;
				}
				$templateField = TemplateField::newFromParams( $fieldName, $fieldParams );
				$this->mTemplateFields[$fieldName] = $templateField;
			}
			return;
		}

		ksort( $this->mTemplateFields );
	}

	/**
	 * For a field name and its attached property name located in the
	 * template text, create an TemplateField object out of it, and
	 * add it to $this->mTemplateFields.
	 * @param string $fieldName
	 * @param string $propertyName
	 * @param bool $isList
	 */
	public function loadPropertySettingInTemplate( $fieldName, $propertyName, $isList ) {
		$templateField = TemplateField::create(
			$fieldName, PFUtils::getContLang()->ucfirst( $fieldName ), $propertyName,
			$isList
		);
		$cur_pos = stripos( $this->mTemplateText, $fieldName . '|' );
		$this->mTemplateFields[$cur_pos] = $templateField;
	}

	public function getTemplateFields() {
		return $this->mTemplateFields;
	}

	public function getFieldNamed( $fieldName ) {
		foreach ( $this->mTemplateFields as $curField ) {
			if ( $curField->getFieldName() == $fieldName ) {
				return $curField;
			}
		}
		return null;
	}

	public function setConnectingProperty( $connectingProperty ) {
		$this->mConnectingProperty = $connectingProperty;
	}

	public function setCategoryName( $categoryName ) {
		$this->mCategoryName = $categoryName;
	}

	public function setFullWikiTextStatus( $status ) {
		$this->mFullWikiText = $status;
	}

	public function setAggregatingInfo( $aggregatingProperty, $aggregationLabel ) {
		$this->mAggregatingProperty = $aggregatingProperty;
		$this->mAggregationLabel = $aggregationLabel;
	}

	public function getName() {
		return $this->mTemplateName;
	}

	public function getFormat() {
		return $this->mTemplateFormat;
	}

	public function getCategoryName() {
		return $this->mCategoryName;
	}

	public function getConnectingProperty() {
		return $this->mConnectingProperty;
	}

	public function getAggregatingProperty() {
		return $this->mAggregatingProperty;
	}

	public function getAggregationLabel() {
		return $this->mAggregationLabel;
	}

	public function isFullWikiText() {
		return (bool)$this->mFullWikiText;
	}

	public function setFormat( $templateFormat ) {
		$this->mTemplateFormat = $templateFormat;
	}

	/**
	 * Creates the text of a template, when called from
	 * Special:CreateTemplate or Special:CreateClass.
	 * @return string
	 */
	public function createText() {
		// Avoid PHP 7.1 warning from passing $this by reference
		$template = $this;
		MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::CreateTemplateText', [ &$template ] );
		return $this->newWriter()->write( $this );
	}

	public function createTextForField( $field ) {
		return $this->newWriter()->fieldText( $field );
	}

	public function printCategoryTag() {
		return $this->newWriter()->categoryTag( $this->mCategoryName );
	}

	private function newWriter(): TemplateWikitextWriter {
		return new TemplateWikitextWriter(
			MediaWikiServices::getInstance()->getHookContainer(),
			defined( 'SMW_VERSION' ),
			defined( 'SIO_VERSION' )
		);
	}

}
