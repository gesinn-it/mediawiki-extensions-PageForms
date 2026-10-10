<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\TemplateText\TemplateFieldParser;
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
		$parser = new TemplateFieldParser( static function () {
			print 'Page Forms error: backtrace limit exceeded during parsing!' .
				' Please increase the value of <a href="http://www.php.net/manual/en/' .
				'pcre.configuration.php#ini.pcre.backtrack-limit">pcre.backtrack_limit' .
				'</a> in php.ini or LocalSettings.php.';
		} );
		foreach ( $parser->parse( (string)$this->mTemplateText, $this->mTemplateParams ) as $key => $field ) {
			$this->mTemplateFields[$key] = $field;
		}

		// The fields are returned in the order in which they appear
		// in the template, not the order in which they were found.
		// The ones of #template_params stay as they are declared.
		if ( $this->mTemplateParams === null ) {
			ksort( $this->mTemplateFields );
		}
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
		[ $position, $templateField ] = ( new TemplateFieldParser() )->propertyField(
			(string)$this->mTemplateText, $fieldName, $propertyName, $isList
		);
		$this->mTemplateFields[$position] = $templateField;
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
