<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec;
use Parser;
use WebRequest;

/**
 * Represents a template in a user-defined form.
 * @author Yaron Koren
 * @ingroup PF
 */
class TemplateInForm {
	private $mTemplateName;
	private $mLabel;
	private $mIntro;
	private $mAddButtonText;
	private $mDisplay;
	private $mEventTitleField;
	private $mEventDateField;
	private $mEventStartDateField;
	private $mEventEndDateField;
	private $mAllowMultiple;
	private $mStrictParsing;
	private $mMinAllowed;
	private $mMaxAllowed;
	private $mFields = [];
	private $mEmbedInTemplate;
	private $mEmbedInField;
	private $mPlaceholder;
	private $mHeight = '200px';
	/**
	 * Conceptually, it would make more sense to define and store this
	 * parameter per-field, rather than per-template. However, it's a lot
	 * easier to handle it in just one place, rather than in every form
	 * input class. Also, this allows, in theory, to set the order of the
	 * fields being displayed - though that's not being done yet.
	 */
	private $mDisplayedFieldsWhenMinimized;

	/**
	 * These fields are for a specific usage of a form (or more
	 * specifically, a template in a form) to edit a particular page.
	 * Perhaps they should go in another class.
	 */
	private $mPageValues;
	private $mValuesFromSubmit = [];
	private $mNumInstancesFromSubmit = 0;
	private $mNumSeenInstancesOnThisPage = null;
	private $mInstanceNum = 0;
	private $mAllInstancesPrinted = false;
	private $mGridValues = [];

	public function __construct() {
		$this->mPageValues = new TemplatePageValues();
	}

	public static function create(
		$name, $label = null, $allowMultiple = null, $maxAllowed = null, $formFields = null
	) {
		$tif = new TemplateInForm();
		$tif->mTemplateName = str_replace( '_', ' ', $name );
		if ( $formFields === null ) {
			$template = Template::newFromName( $tif->mTemplateName );
			$fields = $template->getTemplateFields();
			foreach ( $fields as $field ) {
				$tif->mFields[] = FormField::create( $field );
			}
		} else {
			$tif->mFields = $formFields;
		}
		$tif->mLabel = $label;
		$tif->mAllowMultiple = $allowMultiple;
		$tif->mMaxAllowed = $maxAllowed;
		return $tif;
	}

	public static function newFromFormTag( TemplateSpec $spec, Parser $parser ) {
		global $wgPageFormsEmbeddedTemplates;

		$tif = new TemplateInForm();
		$tif->mTemplateName = str_replace( '_', ' ', trim( $parser->recursiveTagParse( $spec->getRawName() ) ) );

		$tif->mAddButtonText = wfMessage( 'pf_formedit_addanother' )->text();

		if ( array_key_exists( $tif->mTemplateName, $wgPageFormsEmbeddedTemplates ) ) {
			[ $tif->mEmbedInTemplate, $tif->mEmbedInField ] =
				$wgPageFormsEmbeddedTemplates[$tif->mTemplateName];
			$tif->mPlaceholder = FormPrinter::placeholderFormat( $tif->mEmbedInTemplate, $tif->mEmbedInField );
		}

		if ( $spec->isMultiple() ) {
			$tif->mAllowMultiple = true;
		}
		if ( $spec->isStrict() ) {
			$tif->mStrictParsing = true;
		}
		$label = $spec->getLabel();
		if ( $label !== null ) {
			$tif->mLabel = $parser->recursiveTagParse( $label );
		}
		if ( $spec->getIntro() !== null ) {
			$tif->mIntro = $spec->getIntro();
		}
		if ( $spec->getMinimumInstances() !== null ) {
			$tif->mMinAllowed = $spec->getMinimumInstances();
		}
		if ( $spec->getMaximumInstances() !== null ) {
			$tif->mMaxAllowed = $spec->getMaximumInstances();
		}
		$addButtonText = $spec->getAddButtonText();
		if ( $addButtonText !== null ) {
			$tif->mAddButtonText = $parser->recursiveTagParse( $addButtonText );
		}
		// Placeholder on form template level. Assume that the template form def
		// will have a multiple+placeholder parameters, and get the placeholder value.
		// The spec converts TemplateName[fieldName] to the pair used internally.
		$embedInField = $spec->getEmbedInField();
		if ( $embedInField !== null ) {
			[ $tif->mEmbedInTemplate, $tif->mEmbedInField ] = $embedInField;
			$tif->mPlaceholder = FormPrinter::placeholderFormat(
				$tif->mEmbedInTemplate, $tif->mEmbedInField
			);
		}
		if ( $spec->getDisplay() !== null ) {
			$tif->mDisplay = $spec->getDisplay();
		}
		if ( $spec->getHeight() !== null ) {
			$tif->mHeight = $spec->getHeight();
		}
		if ( $spec->getDisplayedFieldsWhenMinimized() !== null ) {
			$tif->mDisplayedFieldsWhenMinimized = $spec->getDisplayedFieldsWhenMinimized();
		}
		if ( $spec->getEventTitleField() !== null ) {
			$tif->mEventTitleField = $spec->getEventTitleField();
		}
		if ( $spec->getEventDateField() !== null ) {
			$tif->mEventDateField = $spec->getEventDateField();
		}
		if ( $spec->getEventStartDateField() !== null ) {
			$tif->mEventStartDateField = $spec->getEventStartDateField();
		}
		if ( $spec->getEventEndDateField() !== null ) {
			$tif->mEventEndDateField = $spec->getEventEndDateField();
		}

		return $tif;
	}

	public function getTemplateName() {
		return $this->mTemplateName;
	}

	public function setTemplateName( $value ) {
		$this->mTemplateName = $value;
	}

	public function getHeight() {
		return $this->mHeight;
	}

	public function getFields() {
		return $this->mFields;
	}

	public function getEmbedInTemplate() {
		return $this->mEmbedInTemplate;
	}

	public function getEmbedInField() {
		return $this->mEmbedInField;
	}

	public function getLabel() {
		return $this->mLabel;
	}

	public function getIntro() {
		return $this->mIntro;
	}

	public function getAddButtonText() {
		return $this->mAddButtonText;
	}

	public function getDisplay() {
		return $this->mDisplay;
	}

	public function getEventTitleField() {
		return $this->mEventTitleField;
	}

	public function getEventDateField() {
		return $this->mEventDateField;
	}

	public function getEventStartDateField() {
		return $this->mEventStartDateField;
	}

	public function getEventEndDateField() {
		return $this->mEventEndDateField;
	}

	public function getPlaceholder() {
		return $this->mPlaceholder;
	}

	public function getDisplayedFieldsWhenMinimized() {
		return $this->mDisplayedFieldsWhenMinimized;
	}

	public function allowsMultiple() {
		return $this->mAllowMultiple;
	}

	public function setAllowsMultiple( $value ) {
		$this->mAllowMultiple = $value;
	}

	public function strictParsing() {
		return $this->mStrictParsing;
	}

	public function getMinInstancesAllowed() {
		return $this->mMinAllowed;
	}

	public function getMaxInstancesAllowed() {
		return $this->mMaxAllowed;
	}

	public function setInstanceNum( $value ) {
		$this->mInstanceNum = $value;
	}

	public function getPregMatchTemplateStr() {
		return $this->mPageValues->getPregMatchTemplateStr();
	}

	public function setPregMatchTemplateStr( $value ) {
		$this->mPageValues->setPregMatchTemplateStr( $value );
	}

	public function getSearchTemplateStr() {
		return $this->mPageValues->getSearchTemplateStr();
	}

	public function setSearchTemplateStr( $value ) {
		$this->mPageValues->setSearchTemplateStr( $value );
	}

	public function numSeenInstancesOnThisPage() {
		return $this->mNumSeenInstancesOnThisPage;
	}

	public function createMarkup() {
		$text = "{{{for template|" . $this->mTemplateName;
		if ( $this->mAllowMultiple ) {
			$text .= "|multiple";
		}
		if ( $this->mLabel != '' ) {
			$text .= "|label=" . $this->mLabel;
		}
		$text .= "}}}\n";
		// For now, HTML for templates differs for multiple-instance
		// templates; this may change if handling of form definitions
		// gets more sophisticated.
		if ( !$this->mAllowMultiple ) {
			$text .= "{| class=\"formtable\"\n";
		}
		foreach ( $this->mFields as $i => $field ) {
			$is_last_field = ( $i == count( $this->mFields ) - 1 );
			$text .= $field->createMarkup( $this->mAllowMultiple, $is_last_field );
		}
		if ( !$this->mAllowMultiple ) {
			$text .= "|}\n";
		}
		$text .= "{{{end template}}}\n";
		return $text;
	}

	/**
	 * This method, and all methods below it, are intended for an instance
	 * of a template in a specific form, and perhaps should be moved into
	 * another class.
	 *
	 * @return string
	 */
	public function getFullTextInPage() {
		return $this->mPageValues->getFullTextInPage();
	}

	public function pageCallsThisTemplate() {
		return $this->mPageValues->pageCallsThisTemplate();
	}

	public function hasValueFromPageForField( $field_name ) {
		return $this->mPageValues->hasValueFromPageForField( $field_name );
	}

	public function getAndRemoveValueFromPageForField( $field_name ) {
		return $this->mPageValues->getAndRemoveValueFromPageForField( $field_name );
	}

	public function getValuesFromPage() {
		return $this->mPageValues->getValuesFromPage();
	}

	public function getInstanceNum() {
		return $this->mInstanceNum;
	}

	public function getGridValues() {
		return $this->mGridValues;
	}

	public function incrementInstanceNum() {
		$this->mInstanceNum++;
	}

	public function allInstancesPrinted() {
		return $this->mAllInstancesPrinted;
	}

	public function addGridValue( $field_name, $cur_value ) {
		if ( !array_key_exists( $this->mInstanceNum, $this->mGridValues ) ) {
			$this->mGridValues[$this->mInstanceNum] = [];
		}
		$this->mGridValues[$this->mInstanceNum][$field_name] = $cur_value;
	}

	public function addField( $form_field ) {
		$this->mFields[] = $form_field;
	}

	/**
	 * This makes it possible for += and -= to modify values based on existing values.
	 *
	 * @param string $field_name
	 * @param string $new_value
	 * @param string|null $modifier
	 */
	public function changeFieldValues( $field_name, $new_value, $modifier = null ) {
		$this->mPageValues->changeFieldValues( $field_name, $new_value, $modifier );
	}

	public function setFieldValuesFromSubmit( WebRequest $request ) {
		// Reset values for every new instance, if this is a
		// multiple-instance template.
		if ( $this->mInstanceNum > 0 ) {
			$this->mValuesFromSubmit = [];
		}

		$query_template_name = str_replace( ' ', '_', $this->mTemplateName );
		// Also replace periods with underlines, since that's what
		// POST does to strings anyway.
		$query_template_name = str_replace( '.', '_', $query_template_name );

		$allValuesFromSubmit = $request->getArray( $query_template_name );
		if ( $allValuesFromSubmit === null ) {
			return;
		}
		// If this is a multiple-instance template, get the values for
		// this instance of the template.
		if ( $this->mAllowMultiple ) {
			// If this data came from a spreadsheet, unescape some characters.
			$spreadsheetTemplates = $request->getArray( 'spreadsheet_templates' );
			if ( is_array( $spreadsheetTemplates ) &&
				array_key_exists( $query_template_name, $spreadsheetTemplates ) ) {
				foreach ( $allValuesFromSubmit as &$rowValues ) {
					foreach ( $rowValues as &$curValue ) {
						$curValue = str_replace( [ '&lt;', '&gt;' ], [ '<', '>' ], $curValue );
					}
				}
			}
			$valuesFromSubmitKeys = [];
			foreach ( array_keys( $allValuesFromSubmit ) as $key ) {
				if ( $key != 'num' ) {
					$valuesFromSubmitKeys[] = $key;
				}
			}
			$this->mNumInstancesFromSubmit = count( $valuesFromSubmitKeys );
			# First search if there are keys \d+a? (keys are made of an integer plus optional letter a),
			# return them if the main loop modify existing templates
			for ( $i = 0; $i < $this->mNumInstancesFromSubmit; $i++ ) {
				$intkey = preg_filter( '/^(\d+)a?$/', '$1', (string)$valuesFromSubmitKeys[$i] );
				if ( $intkey !== null ) {
					$intkey = (int)$intkey;
				}
				if ( $intkey !== null && $this->mNumSeenInstancesOnThisPage !== null &&
					$intkey < $this->mNumSeenInstancesOnThisPage ) {
					if ( $intkey === $this->mInstanceNum ) {
						$instanceKey = $valuesFromSubmitKeys[$intkey];
						$this->mValuesFromSubmit = $allValuesFromSubmit[$instanceKey];
						return;
					}
					unset( $valuesFromSubmitKeys[$i] );
				} else {
					break;
				}
			}
			# The main loop is still in existing templates
			if ( $this->mInstanceNum < $this->mNumSeenInstancesOnThisPage ) {
				return;
			}
			# Now the main loop is adding new templates
			$valuesFromSubmitKeys = array_values( $valuesFromSubmitKeys );
			$offset = $this->mInstanceNum - $this->mNumSeenInstancesOnThisPage;
			if ( $offset < count( $valuesFromSubmitKeys ) ) {
				$instanceKey = $valuesFromSubmitKeys[$offset];
				$this->mValuesFromSubmit = $allValuesFromSubmit[$instanceKey];
			}
		} else {
			$this->mValuesFromSubmit = $allValuesFromSubmit;
		}
	}

	public function getValuesFromSubmit() {
		return $this->mValuesFromSubmit;
	}

	/**
	 * @param string $str
	 * @param string[] &$replacements
	 * @return string
	 * @see TemplatePageValues::removeUnparsedText()
	 */
	public static function removeUnparsedText( $str, &$replacements ) {
		return TemplatePageValues::removeUnparsedText( $str, $replacements );
	}

	/**
	 * @param string $str
	 * @param string[] $replacements
	 * @return string
	 * @see TemplatePageValues::restoreUnparsedText()
	 */
	public static function restoreUnparsedText( $str, $replacements ) {
		return TemplatePageValues::restoreUnparsedText( $str, $replacements );
	}

	public function setFieldValuesFromPage( $existing_page_content ) {
		$this->mPageValues->setFieldValuesFromPage( $existing_page_content );
	}

	/**
	 * Read the first call of this template from the page text (see
	 * TemplatePageValues::readFirstCall()) and note how many instances the page has up to here.
	 *
	 * @param string $existing_page_content
	 * @return string The page text without that call
	 */
	public function readFirstCallFromPage( $existing_page_content ) {
		$remaining = $this->mPageValues->readFirstCall( $this->mTemplateName, $existing_page_content );
		if ( $this->mPageValues->pageCallsThisTemplate() ) {
			$this->mNumSeenInstancesOnThisPage = $this->mInstanceNum + 1;
		}
		return $remaining;
	}

	/**
	 * @param string $field_name
	 * @param bool $holdsTemplate
	 * @param string &$remainingPageText
	 * @return string
	 * @see TemplatePageValues::takeValueFromPage()
	 */
	public function takeValueFromPage( $field_name, $holdsTemplate, &$remainingPageText ) {
		return $this->mPageValues->takeValueFromPage( $field_name, $holdsTemplate, $remainingPageText );
	}

	/**
	 * Set some vars based on the current contents of the page being
	 * edited - or at least vars that only need to be set if there's
	 * an existing page.
	 * @param string $existing_page_content
	 */
	public function setPageRelatedInfo( $existing_page_content ) {
		$this->mPageValues->setPageRelatedInfo( $this->mTemplateName, $existing_page_content );
		if ( $this->mPageValues->pageCallsThisTemplate() ) {
			$this->mNumSeenInstancesOnThisPage = $this->mInstanceNum + 1;
		}
	}

	public function checkIfAllInstancesPrinted( $form_submitted, $source_is_page ) {
		// Find additional instances of this template in the page
		// (if it's an existing page) or the query string (if it's a
		// new page).
		// If there's at least one, re-parse this section of the
		// definition form for the subsequent template instance;
		// if there's none, don't include fields at all.
		// @TODO - There has to be a more efficient way to handle
		// multiple instances of templates, one that doesn't involve
		// re-parsing the same tags, but I don't know what it is.
		// (Also add additional, blank instances if there's a minimum
		// number required in this form, and we haven't reached it yet.)
		if ( !$this->mAllowMultiple ) {
			return;
		}
		if ( $form_submitted && $this->mInstanceNum < $this->mNumInstancesFromSubmit ) {
			return;
		}
		if ( !$form_submitted && $this->mInstanceNum < $this->mMinAllowed ) {
			return;
		}
		if ( !$form_submitted && $source_is_page && $this->mPageValues->pageCallsThisTemplate() ) {
			return;
		}
		if ( !$form_submitted && !$source_is_page && $this->mValuesFromSubmit != null ) {
			return;
		}
		$this->mAllInstancesPrinted = true;
	}

}
