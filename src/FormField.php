<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\FieldSpec;
use MediaWiki\MediaWikiServices;
use Parser;
use ParserOptions;
use PFUtils;
use PFValuesUtils;
use RequestContext;
use User;

/**
 * This class is distinct from TemplateField in that it represents a template
 * field defined in a form definition - it contains an TemplateField object
 * within it (the $template_field variable), along with the other properties
 * for that field that are set within the form.
 * @ingroup PF
 */
class FormField {

	/** @var MappingLabels|null Shared with the other fields of the form once the form hands it in */
	private ?MappingLabels $mappingLabels = null;

	/**
	 * @var TemplateField
	 */
	public $template_field;
	private $mAutocapitalize;
	private $mInputType;
	private $mIsMandatory;
	private $mIsHidden;
	private $mIsRestricted;
	private $mPossibleValues;
	private $mUseDisplayTitle;
	private $mIsList;
	/**
	 * The following fields are not set by the form-creation page
	 * (though they could be).
	 */
	private $mDefaultValue;
	private $mPreloadPage;
	private $mHoldsTemplate;
	private $mIsUploadable;
	private $mFieldArgs;
	private $mDescriptionArgs;
	private $mLabel;
	private $mLabelMsg;
	/**
	 * Holds the state specific to this field's use within one instance of
	 * the form being rendered - its input name, disabled status, and the
	 * deferred 'remote autocompletion' possible-values resolution (see
	 * #187) - as opposed to the static form-definition configuration held
	 * directly on this class. See FormInstanceField for details.
	 */
	private FormInstanceField $mInstanceField;

	/**
	 * @param TemplateField $template_field
	 *
	 * @return self
	 */
	public static function create( TemplateField $template_field ) {
		$f = new FormField();
		$f->template_field = $template_field;
		$f->mInputType = null;
		$f->mIsMandatory = false;
		$f->mIsHidden = false;
		$f->mIsRestricted = false;
		$f->mIsUploadable = false;
		$f->mPossibleValues = null;
		$f->mUseDisplayTitle = false;
		$f->mFieldArgs = [];
		$f->mDescriptionArgs = [];
		$f->mInstanceField = new FormInstanceField( $f );
		return $f;
	}

	/**
	 * @return FormInstanceField
	 */
	public function getInstanceField(): FormInstanceField {
		return $this->mInstanceField;
	}

	/**
	 * @return TemplateField
	 */
	public function getTemplateField() {
		return $this->template_field;
	}

	/**
	 * @param TemplateField $templateField
	 */
	public function setTemplateField( $templateField ) {
		$this->template_field = $templateField;
	}

	public function getInputType() {
		return $this->mInputType;
	}

	public function setInputType( $inputType ) {
		$this->mInputType = $inputType;
	}

	public function hasFieldArg( $key ) {
		return array_key_exists( $key, $this->mFieldArgs );
	}

	public function getFieldArgs() {
		return $this->mFieldArgs;
	}

	public function getFieldArg( $key ) {
		return $this->mFieldArgs[$key];
	}

	public function setFieldArg( $key, $value ) {
		$this->mFieldArgs[$key] = $value;
	}

	public function getDefaultValue() {
		return $this->mDefaultValue;
	}

	public function isMandatory() {
		return $this->mIsMandatory;
	}

	public function setIsMandatory( $isMandatory ) {
		$this->mIsMandatory = $isMandatory;
	}

	public function isHidden() {
		return $this->mIsHidden;
	}

	public function setIsHidden( $isHidden ) {
		$this->mIsHidden = $isHidden;
	}

	public function getAutocapitalize() {
		return $this->mAutocapitalize;
	}

	public function isRestricted() {
		return $this->mIsRestricted;
	}

	public function setIsRestricted( $isRestricted ) {
		$this->mIsRestricted = $isRestricted;
	}

	public function holdsTemplate() {
		return $this->mHoldsTemplate;
	}

	public function setHoldsTemplate( $val ) {
		$this->mHoldsTemplate = $val;
	}

	public function isList() {
		return $this->mIsList;
	}

	public function getPossibleValues() {
		if ( $this->hasOwnPossibleValues() ) {
			return $this->mPossibleValues;
		} else {
			return $this->template_field->getPossibleValues();
		}
	}

	public function setPossibleValues( $possibleValues ) {
		$this->mPossibleValues = $possibleValues;
	}

	/**
	 * Whether this FormField has its own possible-values list - including an
	 * intentionally empty one, e.g. a deferred 'remote autocompletion' fetch
	 * that resolved to no current value (see #187) - as opposed to never
	 * having one set at all, in which case callers fall back to the
	 * template field's list.
	 *
	 * A plain `!= null` check cannot make this distinction: PHP considers an
	 * empty array loosely equal to null, so it would incorrectly treat "we
	 * deliberately have zero possible values" the same as "none were ever
	 * set", and fall back to the template field's (possibly much larger,
	 * unrelated) list.
	 *
	 * @return bool
	 */
	public function hasOwnPossibleValues(): bool {
		return $this->mPossibleValues !== null;
	}

	/**
	 * Whether the full 'values from ...' fetch for $autocompleteType/
	 * $autocompleteSource may be skipped in favor of resolving only the
	 * field's current value later, via resolveDeferredPossibleValues() (see
	 * #187). Requires the 'remote autocompletion' flag, a source that
	 * exceeds the local-autocomplete threshold, and no mapping (mapping
	 * needs the complete source to translate values) or dependent-field
	 * configuration (whose source is filtered by a parent value, not fixed).
	 *
	 * Also excluded: 'display=spreadsheet' templates. Those are rendered by
	 * SpreadsheetHtmlBuilder::spreadsheetHTML(), which reads possible_values
	 * directly - bypassing FormFieldHtmlBuilder::formFieldHTML(), the only
	 * place resolveDeferredPossibleValues() is called - and holds one
	 * current value per grid row rather than a single value, which is
	 * incompatible with this single-value resolution. ('display=table' is
	 * fine: SpreadsheetHtmlBuilder::tableHTML() calls formFieldHTML() once
	 * per row with that row's value.)
	 *
	 * @param string $autocompleteType
	 * @param string $autocompleteSource
	 * @param string|null $display The containing template's display mode
	 *  (TemplateInForm::getDisplay()).
	 * @return bool
	 */
	private function canDeferAutocompleteFetch(
		string $autocompleteType, string $autocompleteSource, ?string $display
	): bool {
		if ( $display === 'spreadsheet' ) {
			return false;
		}
		if ( !array_key_exists( 'remote autocompletion', $this->mFieldArgs ) ) {
			return false;
		}
		if ( array_key_exists( 'mapping template', $this->mFieldArgs ) ||
			array_key_exists( 'mapping property', $this->mFieldArgs ) ||
			array_key_exists( 'values dependent on', $this->mFieldArgs )
		) {
			return false;
		}
		return PFValuesUtils::exceedsLocalAutocompleteThreshold( $autocompleteType, $autocompleteSource );
	}

	/**
	 * Whether the eager 'values from ...' fetch was skipped for this field
	 * (see canDeferAutocompleteFetch()), leaving the instance field's
	 * possible-values empty until resolveDeferredPossibleValues() is called
	 * with the field's current value.
	 *
	 * @return bool
	 */
	public function hasDeferredPossibleValues(): bool {
		return $this->mInstanceField->hasDeferredPossibleValues();
	}

	/**
	 * Resolves the instance field's possible values for a field whose eager
	 * fetch was deferred (see #187), using only the field's current
	 * value(s) - the minimum needed to render it correctly - rather than
	 * the source's full, possibly very large, value list. Live
	 * browsing/searching of the rest of the source is handled client-side
	 * via PF_AutocompleteAPI.
	 *
	 * A no-op when nothing was deferred, or when there is no current value
	 * to resolve.
	 *
	 * @param string|null $curValue
	 */
	public function resolveDeferredPossibleValues( ?string $curValue ): void {
		$this->mInstanceField->resolveDeferredPossibleValues( $curValue );
	}

	public function getUseDisplayTitle() {
		return $this->mUseDisplayTitle;
	}

	public function getInputName() {
		return $this->mInstanceField->getInputName();
	}

	public function setInputName( $val ) {
		$this->mInstanceField->setInputName( $val );
	}

	public function getLabel() {
		return $this->mLabel;
	}

	public function getLabelMsg() {
		return $this->mLabelMsg;
	}

	public function isDisabled() {
		return $this->mInstanceField->isDisabled();
	}

	public function setIsDisabled( $val ) {
		$this->mInstanceField->setIsDisabled( $val );
	}

	/**
	 * @return array The description of the field, the text before it and whether the description
	 *   is a tooltip, as set with setDescriptionArg()
	 */
	public function getDescriptionArgs() {
		return $this->mDescriptionArgs;
	}

	public function setDescriptionArg( $key, $value ) {
		$this->mDescriptionArgs[$key] = $value;
	}

	public static function newFromFormFieldTag(
		FieldSpec $spec,
		$template,
		$template_in_form,
		$form_is_disabled,
		User $user,
		Parser $parser,
		?MappingLabels $mappingLabels = null
	) {
		self::prepareParser( $parser );

		$f = new FormField();
		if ( $mappingLabels !== null ) {
			$f->mappingLabels = $mappingLabels;
		}
		$f->mFieldArgs = [];
		$f->mInstanceField = new FormInstanceField( $f );

		$field_name = $spec->getName();
		$template_name = $template_in_form->getTemplateName();

		if ( !$f->setUpTemplateField( $template, $template_in_form, $field_name, $template_name ) ) {
			return $f;
		}

		$fullFieldName = $template_name . '[' . $field_name . ']';
		$f->applyTemplateFieldDefaults();
		$f->applySpecFlags( $spec, $user );
		$args = $f->readArguments( $spec, $parser, $user, $fullFieldName );

		$f->setUpPossibleValuesFromSources( $args, $template_in_form->getDisplay() );
		$f->setUpDelimiter();
		$f->setPossibleValuesFromList( $args['values'] );
		$f->setUpSemanticProperty( $args['semantic_property'], $fullFieldName );
		$f->setUpMapping( $args['valuesSourceType'], $args['valuesSource'] );

		if ( $template_in_form->allowsMultiple() ) {
			$f->mFieldArgs['part_of_multiple'] = true;
		}
		if ( count( $args['show_on_select'] ) > 0 ) {
			$f->mFieldArgs['show on select'] = $args['show_on_select'];
		}

		// Disable this field if either the whole form is disabled, or
		// it's a restricted field and user doesn't have sysop privileges.
		$f->mInstanceField->setIsDisabled( $form_is_disabled || $f->mIsRestricted );
		$f->setUpInputName( $template_in_form, $template_name, $field_name, $fullFieldName );

		return $f;
	}

	/**
	 * MW 1.43 compat: Parser::$mStripState and $mOutputType are typed properties
	 * that are only initialised after clearState()/setOutputType() are called.
	 * $parser must already be titled by the caller (see FormPrinter::createFreshParser()) -
	 * wikitext evaluated while reading the field (e.g. a 'default=' value containing {{PAGENAME}})
	 * is resolved against $parser->getTitle(), and an untitled parser resolves it against a
	 * "Badtitle" placeholder instead (see issue #189).
	 *
	 * The state of the parser is cleared, which is a side effect on the parser of the caller.
	 */
	private static function prepareParser( Parser $parser ): void {
		if ( !$parser->getOptions() ) {
			$parser->setOptions( ParserOptions::newFromAnon() );
		}
		$parser->clearState();
		$parser->setOutputType( Parser::OT_HTML );
	}

	/**
	 * See if the field matches one of the fields defined for the template - if it does, use all
	 * available information about that field; if it doesn't, either include it in the form or
	 * not, depending on whether the template has a 'strict' setting in the form definition.
	 *
	 * @param Template $template
	 * @param TemplateInForm $template_in_form
	 * @param string $field_name
	 * @param string|null $template_name
	 * @return bool false if the field is not part of the form
	 */
	private function setUpTemplateField( $template, $template_in_form, $field_name, $template_name ): bool {
		global $wgPageFormsEmbeddedTemplates;

		$template_field = $template->getFieldNamed( $field_name );
		if ( $template_field != null ) {
			$this->template_field = $template_field;
		} else {
			if ( $template_in_form->strictParsing() ) {
				$this->template_field = new TemplateField();
				$this->mIsList = false;
				return false;
			}
			$this->template_field = TemplateField::create( $field_name, null );
		}

		$embeddedTemplate = $this->template_field->getHoldsTemplate();
		if ( $embeddedTemplate != '' ) {
			$this->mIsHidden = true;
			$this->mHoldsTemplate = true;
			// Store this information so that the embedded/"held"
			// template - which is hopefully after this one in the
			// form definition - can be handled correctly. In forms,
			// both the embedding field and the embedded template are
			// specified as such, but in templates (i.e., with
			// #template_params), it's only the embedding field.
			$wgPageFormsEmbeddedTemplates[$embeddedTemplate] = [ $template_name, $field_name ];
		}
		return true;
	}

	/**
	 * We set "values from ..." params if there are corresponding
	 * values set in #template_params - this is a bit of a @hack,
	 * since we should really just use these values directly, but
	 * there are various places in the code that check for "values
	 * from ...", so it's easier to just pretend that these params
	 * were set.
	 */
	private function applyTemplateFieldDefaults(): void {
		$categoryFromTemplate = $this->getTemplateField()->getCategory();
		if ( $categoryFromTemplate !== null ) {
			$this->mFieldArgs['values from category'] = $categoryFromTemplate;
		}
		$namespaceFromTemplate = $this->getTemplateField()->getNSText();
		if ( $namespaceFromTemplate !== null ) {
			$this->mFieldArgs['values from namespace'] = $namespaceFromTemplate;
		}
	}

	private function applySpecFlags( FieldSpec $spec, User $user ): void {
		if ( $spec->isMandatory() ) {
			$this->mIsMandatory = true;
		}
		if ( $spec->isHidden() ) {
			$this->mIsHidden = true;
		}
		if ( $spec->isList() ) {
			$this->mIsList = true;
		}
		if ( $spec->isUnique() ) {
			$this->mFieldArgs['unique'] = true;
		}
		if ( $spec->isFlag( 'restricted' ) ) {
			$this->mIsRestricted = !$user->isAllowed( 'editrestrictedfields' );
		}
		if ( $spec->isFlag( 'edittools' ) ) {
			// free text only
			$this->mFieldArgs['edittools'] = true;
		}
	}

	/**
	 * Go through the arguments of the field tag. What can only be used once all arguments are
	 * known (the values and where they come from, the property, "show on select") is returned.
	 *
	 * @param FieldSpec $spec
	 * @param Parser $parser
	 * @param User $user
	 * @param string $fullFieldName
	 * @return array<string, mixed> The keys semantic_property, show_on_select, values, valuesSourceType,
	 *   valuesSource and valuesFromPropertyName
	 */
	private function readArguments( FieldSpec $spec, Parser $parser, User $user, string $fullFieldName ): array {
		$args = [
			'semantic_property' => null,
			'show_on_select' => [],
			'values' => null,
			'valuesSourceType' => null,
			'valuesSource' => null,
			// The actual fetch is deferred to after the full loop has run, so that a 'remote
			// autocompletion' argument appearing later in the tag is already known.
			'valuesFromPropertyName' => null,
		];

		foreach ( $spec->getArgs() as $argName => $argValue ) {
			if ( $spec->isFlag( $argName ) ) {
				// add handling for single-value params, for custom input types
				$this->mFieldArgs[$argName] = true;

				if ( $argName == 'holds template' ) {
					$this->mIsHidden = true;
					$this->mHoldsTemplate = true;
				}
			} else {
				// First, set each value as its own entry in $this->mFieldArgs.
				$this->mFieldArgs[$argName] = $argValue;

				// Then, do all special handling.
				$this->readArgument( $argName, $argValue, $parser, $user, $fullFieldName, $args );
			}
		}

		return $args;
	}

	/**
	 * The special handling of one argument that has a value.
	 *
	 * @param string $argName
	 * @param string $argValue
	 * @param Parser $parser
	 * @param User $user
	 * @param string $fullFieldName
	 * @param array &$args What readArguments() returns
	 */
	private function readArgument(
		string $argName, $argValue, Parser $parser, User $user, string $fullFieldName, array &$args
	): void {
		if ( $this->readValuesSourceArgument( $argName, $argValue, $parser, $args ) ||
			$this->readUniqueArgument( $argName, $argValue, $parser )
		) {
			return;
		}
		if ( $argName == 'autocapitalize' ) {
			$this->mAutocapitalize = strtolower( $argValue );
		} elseif ( $argName == 'input type' ) {
			$this->mInputType = $argValue;
		} elseif ( $argName == 'default' ) {
			// We call recursivePreprocess() here,
			// and not the more standard
			// recursiveTagParse(), so that
			// wikitext in the value, and bare URLs,
			// will not get turned into HTML.
			$this->mDefaultValue = $parser->recursivePreprocess( $argValue );
		} elseif ( $argName == 'preload' ) {
			$this->mPreloadPage = $argValue;
		} elseif ( $argName == 'label' ) {
			$this->mLabel = $argValue;
		} elseif ( $argName == 'label msg' ) {
			$this->mLabelMsg = $argValue;
		} elseif ( $argName == 'show on select' ) {
			$this->readShowOnSelect( $argValue, $parser, $args['show_on_select'] );
		} elseif ( $argName == 'values dependent on' ) {
			global $wgPageFormsDependentFields;
			$wgPageFormsDependentFields[] = [ $argValue, $fullFieldName ];
		} elseif ( $argName == 'property' ) {
			$args['semantic_property'] = $argValue;
		} elseif ( $argName == 'default filename' ) {
			$this->mFieldArgs['default filename'] = $this->defaultFilename( $argValue, $parser );
		} elseif ( $argName == 'restricted' ) {
			$effectiveGroups = MediaWikiServices::getInstance()->getUserGroupManager()
				->getUserEffectiveGroups( $user );
			$this->mIsRestricted = !array_intersect(
				$effectiveGroups, array_map( 'trim', explode( ',', $argValue ) )
			);
		}
	}

	/**
	 * The arguments that say where the possible values come from, or give them.
	 *
	 * @param string $argName
	 * @param string $argValue
	 * @param Parser $parser
	 * @param array &$args What readArguments() returns
	 * @return bool Whether the argument is one of these
	 */
	private function readValuesSourceArgument( string $argName, $argValue, Parser $parser, array &$args ): bool {
		if ( $argName == 'values' ) {
			// Handle this one only after
			// 'delimiter' has also been set.
			$args['values'] = $parser->recursiveTagParse( $argValue );
		} elseif ( $argName == 'values from property' ) {
			$args['valuesFromPropertyName'] = $argValue;
		} elseif ( $argName == 'values from wikidata' ) {
			$args['valuesSourceType'] = 'wikidata';
			$args['valuesSource'] = urlencode( $argValue );
		} elseif ( $argName == 'values from query' ) {
			$args['valuesSourceType'] = 'query';
			$args['valuesSource'] = $argValue;
		} elseif ( $argName == 'values from category' ) {
			$valuesSource = $parser->recursiveTagParse( $argValue );
			global $wgCapitalLinks;
			if ( $wgCapitalLinks ) {
				$valuesSource = ucfirst( $valuesSource );
			}
			$args['valuesSource'] = $valuesSource;
			$args['valuesSourceType'] = 'category';
		} elseif ( $argName == 'values from concept' ) {
			$args['valuesSourceType'] = 'concept';
			$args['valuesSource'] = $parser->recursiveTagParse( $argValue );
		} elseif ( $argName == 'values from namespace' ) {
			$args['valuesSourceType'] = 'namespace';
			$args['valuesSource'] = $parser->recursiveTagParse( $argValue );
		} else {
			return false;
		}
		return true;
	}

	/**
	 * The arguments that make the field's values unique within a category, namespace or concept.
	 *
	 * @param string $argName
	 * @param string $argValue
	 * @param Parser $parser
	 * @return bool Whether the argument is one of these
	 */
	private function readUniqueArgument( string $argName, $argValue, Parser $parser ): bool {
		if ( $argName != 'unique for category' && $argName != 'unique for namespace' &&
			$argName != 'unique for concept'
		) {
			return false;
		}
		$this->mFieldArgs['unique'] = true;
		$scope = substr( $argName, strlen( 'unique for ' ) );
		$this->mFieldArgs["unique_for_$scope"] = $parser->recursiveTagParse( $argValue );
		return true;
	}

	/**
	 * @param string $argValue
	 * @param Parser $parser
	 * @param array &$showOnSelect The div IDs, each with the options that show it
	 */
	private function readShowOnSelect( $argValue, Parser $parser, array &$showOnSelect ): void {
		// html_entity_decode() is needed to turn '&gt;' to '>'
		$vals = explode( ';', html_entity_decode( $argValue ) );
		foreach ( $vals as $val ) {
			$val = trim( $val );
			if ( $val === '' ) {
				continue;
			}
			$option_div_pair = explode( '=>', $val, 2 );
			if ( count( $option_div_pair ) > 1 ) {
				$option = trim( $parser->recursiveTagParse( $option_div_pair[0] ) );
				$div_id = $option_div_pair[1];
				if ( array_key_exists( $div_id, $showOnSelect ) ) {
					$showOnSelect[$div_id][] = $option;
				} else {
					$showOnSelect[$div_id] = [ $option ];
				}
			} else {
				$showOnSelect[$val] = [];
			}
		}
	}

	private function defaultFilename( string $argValue, Parser $parser ): string {
		$titleGlobal = RequestContext::getMain()->getTitle();
		$page_name = $titleGlobal->getText();
		if ( $titleGlobal->isSpecialPage() ) {
			// If it's of the form
			// Special:FormEdit/form/target,
			// get just the target.
			$pageNameComponents = explode( '/', $page_name, 3 );
			if ( count( $pageNameComponents ) == 3 ) {
				$page_name = $pageNameComponents[2];
			}
		}
		$default_filename = str_replace( '<page name>', $page_name, $argValue );
		// Parse value, so default filename can
		// include parser functions.
		return $parser->recursiveTagParse( $default_filename );
	}

	/**
	 * Fetch the possible values from "values from ..." - or note that it is left to the
	 * autocomplete request, if the field has 'remote autocompletion'.
	 *
	 * The 'values from property' fetch is made here, after all arguments were read, so that a
	 * 'remote autocompletion' argument appearing later in the tag is known when deciding whether
	 * to defer the fetch.
	 *
	 * @param array $args What readArguments() returns
	 * @param string $display The display mode of the template in the form
	 */
	private function setUpPossibleValuesFromSources( array $args, $display ): void {
		$valuesFromPropertyName = $args['valuesFromPropertyName'];
		$valuesSourceType = $args['valuesSourceType'];
		$valuesSource = $args['valuesSource'];

		if ( $valuesFromPropertyName !== null ) {
			if ( $this->canDeferAutocompleteFetch( 'property', $valuesFromPropertyName, $display ) ) {
				$this->mInstanceField->deferPossibleValues( 'property' );
			} else {
				$this->mPossibleValues = PFValuesUtils::getAllValuesForProperty( $valuesFromPropertyName );
				$this->mUseDisplayTitle = is_string( array_key_first( $this->mPossibleValues ) );
			}
		}

		if ( $valuesSourceType !== null && $valuesSource !== null && ( $valuesSourceType !== 'wikidata' || (
				$this->mInputType !== 'combobox' && $this->mInputType !== 'tokens' ) ) ) {
			if ( in_array( $valuesSourceType, [ 'category', 'namespace', 'concept' ], true ) &&
				$this->canDeferAutocompleteFetch( $valuesSourceType, $valuesSource, $display )
			) {
				$this->mInstanceField->deferPossibleValues( $valuesSourceType );
			} else {
				$this->mPossibleValues = PFValuesUtils::getAutocompleteValues( $valuesSource, $valuesSourceType );
				if ( in_array( $valuesSourceType, [ 'category', 'namespace', 'concept' ], true ) ) {
					global $wgPageFormsUseDisplayTitle;
					$this->mUseDisplayTitle = $wgPageFormsUseDisplayTitle;
				}
			}
		}
	}

	private function setUpDelimiter(): void {
		if ( !array_key_exists( 'delimiter', $this->mFieldArgs ) ) {
			$delimiterFromTemplate = $this->getTemplateField()->getDelimiter();
			if ( $delimiterFromTemplate == '' ) {
				$this->mFieldArgs['delimiter'] = ',';
			} else {
				$this->mFieldArgs['delimiter'] = $delimiterFromTemplate;
				$this->mIsList = true;
			}
		}
	}

	/**
	 * If the 'values' parameter was set, separate it based on the 'delimiter' parameter, if any.
	 *
	 * @param string|null $values
	 */
	private function setPossibleValuesFromList( $values ): void {
		if ( $values != null ) {
			// Remove whitespaces, and un-escape characters
			$valuesArray = array_map( 'trim', explode( $this->mFieldArgs['delimiter'], $values ) );
			$this->mPossibleValues = array_map( 'htmlspecialchars_decode', $valuesArray );
		}
	}

	/**
	 * Do some data storage specific to the Semantic MediaWiki extension.
	 * This must happen before the "mapping template"/"mapping property"
	 * handling, since a 'property' set in the form definition can
	 * populate the template field's possible values (from the SMW
	 * property's "Allows value"/"Allows value list" annotations), and
	 * those values need to be in place before we decide what to map.
	 *
	 * @param string|null $semantic_property The property set in the form definition
	 * @param string $fullFieldName
	 */
	private function setUpSemanticProperty( $semantic_property, string $fullFieldName ): void {
		if ( defined( 'SMW_VERSION' ) ) {
			// If a property was set in the form definition,
			// overwrite whatever is set in the template field -
			// this is somewhat of a hack, since parameters set in
			// the form definition are meant to go into the
			// FormField object, not the TemplateField object
			// it contains;
			// it seemed like too much work, though, to create an
			// FormField::setSemanticProperty() function just for
			// this call.
			if ( $semantic_property !== null ) {
				$this->template_field->setSemanticProperty( $semantic_property );
			} else {
				$semantic_property = $this->template_field->getSemanticProperty();
			}
			if ( $semantic_property !== null ) {
				global $wgPageFormsFieldProperties;
				$wgPageFormsFieldProperties[$fullFieldName] = $semantic_property;
			}
		}
	}

	/**
	 * @param string|null $valuesSourceType
	 * @param string|null $valuesSource
	 */
	private function setUpMapping( $valuesSourceType, $valuesSource ): void {
		// A deferred field intentionally has an empty (but non-null)
		// mPossibleValues at this point (see above) - it must not be
		// overwritten by the template field's unrelated list.
		if ( $this->mPossibleValues === null && !$this->mInstanceField->hasDeferredPossibleValues() ) {
			$this->mPossibleValues = $this->template_field->getPossibleValues();
		}

		$mappingType = null;
		if ( array_key_exists( 'mapping template', $this->mFieldArgs ) ) {
			$mappingType = 'template';
		} elseif ( array_key_exists( 'mapping property', $this->mFieldArgs ) ) {
			$mappingType = 'property';
		} elseif ( $this->mUseDisplayTitle ) {
			$this->mPossibleValues = PFValuesUtils::disambiguateLabels( $this->mPossibleValues );
		}

		if ( $mappingType !== null && $this->mPossibleValues !== [] ) {
			// If we're going to be mapping values, we need to have
			// the exact page name - and if these values come from
			// "values from namespace", the namespace prefix was
			// not included, so we need to add it now.
			if ( $valuesSourceType == 'namespace' && $valuesSource != '' && $valuesSource != 'Main' ) {
				foreach ( $this->mPossibleValues as $index => &$value ) {
					$value = $valuesSource . ':' . $value;
				}
				// Has to be set to false to not mess up the
				// handling.
				$this->mUseDisplayTitle = false;
			}

			$this->setMappedValues( $mappingType );
		}
	}

	private function setUpInputName( $template_in_form, $template_name, $field_name, string $fullFieldName ): void {
		if ( $template_name === null || $template_name === '' ) {
			$this->mInstanceField->setInputName( $field_name );
		} elseif ( $template_in_form->allowsMultiple() ) {
			// 'num' will get replaced by an actual index, either in PHP
			// or in Javascript, later on
			$this->mInstanceField->setInputName( $template_name . '[num][' . $field_name . ']' );
			$this->setFieldArg( 'origName', $fullFieldName );
		} else {
			$this->mInstanceField->setInputName( $fullFieldName );
		}
	}

	public function cleanupTranslateTags( &$value ) {
		$i = 0;
		// If there are two tags ("<!--T:X-->") with no content between them, remove the first one.
		while ( preg_match( '/(<!--T:[0-9]+-->\s*)(<!--T:[0-9]+-->)/', $value, $matches ) ) {
			$value = str_replace( $matches[1], '', $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}

		$i = 0;
		// If there is a tag ("<!--T:X-->") at the end, with nothing after, remove it.
		while ( preg_match( '#(<!--T:[0-9]+-->\s*)(</translate>)#', $value, $matches ) ) {
			$value = str_replace( $matches[1], '', $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}

		$i = 0;
		// If there is a tag ("<!--T:X-->") not separated from a template call ("{{ ..."),
		// add a new line between them.
		while ( preg_match( '/(<!--T:[0-9]+-->)({{[^}]+}}\s*)/', $value, $matches ) ) {
			$value = str_replace( $matches[1], $matches[1] . "\n", $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}
	}

	public function autocapitalize( $val ) {
		if ( $this->mAutocapitalize === 'words' ) {
			return ucwords( $val );
		} else {
			return $val;
		}
	}

	public function getCurrentValue(
		$template_instance_query_values,
		$form_submitted,
		$source_is_page,
		$all_instances_printed,
		&$val_modifier = null
	) {
		// Get the value from the request, if
		// it's there, and if it's not an array.
		$field_name = $this->template_field->getFieldName();
		$delimiter = $this->mFieldArgs['delimiter'];

		if ( PFUtils::isTranslateEnabled() &&
			$this->hasFieldArg( 'translatable' ) && $this->getFieldArg( 'translatable' ) ) {
			$this->addTranslateTagToQueryValue( $template_instance_query_values );
		}

		if ( is_array( $template_instance_query_values ) ) {
			$field_query_val = $this->findQueryValue( $template_instance_query_values, $val_modifier );

			if ( $form_submitted && $field_query_val != '' ) {
				return $this->valueFromSubmittedForm( $field_query_val, $template_instance_query_values );
			}
			if ( !$form_submitted && $field_query_val != '' ) {
				if ( is_array( $field_query_val ) ) {
					$str = FormInputValues::getStringFromPassedInArray( $field_query_val, $delimiter );
				} else {
					$str = $field_query_val;
				}
				return str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $str );

			}
		}

		return $this->defaultValue( $form_submitted, $source_is_page, $all_instances_printed );
	}

	/**
	 * If this is a translatable field, and both it and its corresponding translate ID tag are
	 * passed in, add the tag to the value.
	 *
	 * @param array|null &$template_instance_query_values
	 */
	private function addTranslateTagToQueryValue( &$template_instance_query_values ): void {
		$fieldName = $this->getTemplateField()->getFieldName();
		$fieldNameTag = $fieldName . '_translate_number_tag';
		if ( isset( $template_instance_query_values[$fieldName] ) &&
			isset( $template_instance_query_values[$fieldNameTag] ) ) {
			$tag = $template_instance_query_values[$fieldNameTag];
			if ( !preg_match( '/( |\n)$/', $tag ) ) {
				$tag .= "\n";
			}
			if ( trim( $template_instance_query_values[$fieldName] ) ) {
				// Don't add the tag if field content has been removed.
				$template_instance_query_values[$fieldName] = $tag . $template_instance_query_values[$fieldName];
			}
		}
		// If user has deleted some content, and there is some translate tag
		// ("<!--T:X-->") with no content, remove the tag.
		if ( isset( $template_instance_query_values[$fieldName] ) ) {
			$this->cleanupTranslateTags( $template_instance_query_values[$fieldName] );
		}
	}

	/**
	 * The value the request has for the field.
	 *
	 * @param array $template_instance_query_values
	 * @param string|null &$val_modifier Set to "+" or "-" if the value is to be added to or removed
	 *   from the existing ones
	 * @return string|array|null
	 */
	private function findQueryValue( array $template_instance_query_values, &$val_modifier ) {
		$field_name = $this->template_field->getFieldName();
		$escaped_field_name = str_replace( "'", "\'", $field_name );

		// If the field name contains an apostrophe, the array
		// sometimes has the apostrophe escaped, and sometimes
		// not. For now, just check for both versions.
		// @TODO - figure this out.
		$field_query_val = null;
		if ( array_key_exists( $escaped_field_name, $template_instance_query_values ) ) {
			$field_query_val = $template_instance_query_values[$escaped_field_name];
		} elseif ( array_key_exists( $field_name, $template_instance_query_values ) ) {
			$field_query_val = $template_instance_query_values[$field_name];
		} else {
			// The next checks are to allow for support for adding a value to ("+") and removing
			// one from ("-") the existing values with autoedit.
			if ( array_key_exists( "$field_name+", $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values["$field_name+"];
				$val_modifier = '+';
			} elseif ( array_key_exists( "$field_name-", $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values["$field_name-"];
				$val_modifier = '-';
			}
		}
		return $field_query_val;
	}

	/**
	 * The value of a field of a submitted form, with the labels turned back into values if the
	 * field maps them.
	 *
	 * @param string|array $field_query_val
	 * @param array $template_instance_query_values
	 * @return string
	 */
	private function valueFromSubmittedForm( $field_query_val, array $template_instance_query_values ) {
		$field_name = $this->template_field->getFieldName();
		$delimiter = $this->mFieldArgs['delimiter'];

		$map_field = false;
		if ( array_key_exists( 'map_field', $template_instance_query_values ) &&
			array_key_exists( $field_name, $template_instance_query_values['map_field'] ) ) {
			$map_field = true;
		}
		if ( is_array( $field_query_val ) ) {
			$cur_values = [];
			if ( $map_field && $this->mPossibleValues !== null ) {
				foreach ( $field_query_val as $key => $val ) {
					$val = $this->autocapitalize( trim( $val ) );
					if ( $key === 'is_list' ) {
						$cur_values[$key] = $val;
					} else {
						$cur_values[] = $this->labelToValue( $val );
					}
				}
			} else {
				foreach ( $field_query_val as $key => $val ) {
					$cur_values[$key] = $this->autocapitalize( $val );
				}
			}
			return FormInputValues::getStringFromPassedInArray( $cur_values, $delimiter );
		}

		$field_query_val = $this->autocapitalize( trim( $field_query_val ) );
		if ( $map_field && $this->mPossibleValues !== null ) {
			// this should be replaced with an input type neutral way of
			// figuring out if this scalar input type is a list
			if ( $this->mInputType == "tokens" ) {
				$this->mIsList = true;
			}
			if ( $this->mIsList ) {
				$cur_values = array_map( 'trim', explode( $delimiter, $field_query_val ) );
				foreach ( $cur_values as $key => $val ) {
					$cur_values[$key] = $this->labelToValue( $val );
				}
				return implode( $delimiter, $cur_values );
			}
			return $this->labelToValue( $field_query_val );
		}
		return $field_query_val;
	}

	/**
	 * The default or preloaded value of the field, if the form is not submitted and the page has
	 * no value for it.
	 *
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param bool $all_instances_printed
	 * @return string|null
	 */
	private function defaultValue( $form_submitted, $source_is_page, $all_instances_printed ) {
		// Default values in new instances of multiple-instance
		// templates should always be set, even for existing pages.
		$part_of_multiple = array_key_exists( 'part_of_multiple', $this->mFieldArgs );
		$printing_starter_instance = $part_of_multiple && $all_instances_printed;
		if ( ( !$source_is_page || $printing_starter_instance ) && !$form_submitted ) {
			if ( $this->mDefaultValue !== null ) {
				// Set to the default value specified in the form, if it's there.
				return $this->mDefaultValue;
			} elseif ( $this->mPreloadPage ) {
				return FormCache::getPreloadedText( $this->mPreloadPage );
			}
		}

		// We're still here...
		return null;
	}

	public function setMappedValues( $mappingType ) {
		if ( $mappingType == 'template' ) {
			$this->setValuesWithMappingTemplate();
		} elseif ( $mappingType == 'property' ) {
			$this->setValuesWithMappingProperty();
		}

		$this->mPossibleValues = PFValuesUtils::disambiguateLabels( $this->mPossibleValues );
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping template.
	 */
	public function setValuesWithMappingTemplate() {
		$this->mPossibleValues = $this->getMappingLabels()->forTemplate(
			$this->mFieldArgs['mapping template'], $this->getMappingKeys()
		);
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping property.
	 * @param \SMW\Store|null $store
	 */
	public function setValuesWithMappingProperty( $store = null ) {
		$store ??= PFUtils::getSMWStore();
		if ( $store == null ) {
			return;
		}

		$this->mPossibleValues = $this->getMappingLabels()->forProperty(
			$store, $this->mFieldArgs['mapping property'], $this->getMappingKeys()
		);
	}

	/**
	 * The values to look the labels up for: the page names, which are the keys of the possible
	 * values when those are display titles.
	 *
	 * @return array<int, string|int>
	 */
	private function getMappingKeys(): array {
		$keys = [];
		foreach ( $this->mPossibleValues as $index => $value ) {
			$keys[] = $this->mUseDisplayTitle ? $index : $value;
		}
		return $keys;
	}

	/**
	 * @param MappingLabels $mappingLabels The labels (and what is remembered about them) of the form
	 */
	public function setMappingLabels( MappingLabels $mappingLabels ): void {
		$this->mappingLabels = $mappingLabels;
	}

	private function getMappingLabels(): MappingLabels {
		$this->mappingLabels ??= new MappingLabels();
		return $this->mappingLabels;
	}

	/**
	 * The value that has the label, or the label itself if no value has it.
	 *
	 * @param string $label
	 * @return string
	 */
	public function labelToValue( $label ) {
		return MappingLabels::labelToValue( $this->mPossibleValues, $label );
	}

	/**
	 * Map a template field value into display labels using mPossibleValues.
	 *
	 * @param string|null $valueString
	 * @param string|null $delimiter
	 * @return string|null
	 */
	public function valueStringToLabels( $valueString, $delimiter ): ?string {
		return MappingLabels::valueStringToLabels( $this->mPossibleValues, $valueString, $delimiter );
	}

	/**
	 * @deprecated use FormFieldExtraHtmlBuilder::build()
	 * @param string|array|null $cur_value
	 * @param string $field_name
	 * @param string|null $template_name
	 * @return string
	 */
	public function additionalHTMLForInput( $cur_value, $field_name, $template_name ) {
		return ( new FormFieldExtraHtmlBuilder() )->build( $this, $cur_value, $field_name, $template_name );
	}

	/**
	 * @deprecated use FormDefinitionWriter::field()
	 * @param bool $part_of_multiple
	 * @param bool $is_last_field_in_template
	 * @return string
	 */
	public function createMarkup( $part_of_multiple, $is_last_field_in_template ) {
		return ( new FormDefinitionWriter() )->field(
			$this, (bool)$part_of_multiple, (bool)$is_last_field_in_template
		);
	}

	public function getArgumentsForInputCallSMW( array &$other_args ) {
		if ( $this->template_field->getSemanticProperty() !== '' &&
			!array_key_exists( 'semantic_property', $other_args ) ) {
			$other_args['semantic_property'] = $this->template_field->getSemanticProperty();
			$other_args['property_type'] = $this->template_field->getPropertyType();
		}
		// If autocompletion hasn't already been hardcoded in the form,
		// and it's a property of type page, or a property of another
		// type with 'autocomplete' specified, set the necessary
		// parameters.
		if ( !array_key_exists( 'autocompletion source', $other_args ) ) {
			if ( $this->template_field->getPropertyType() == '_wpg' ) {
				$other_args['autocompletion source'] = $this->template_field->getSemanticProperty();
				$other_args['autocomplete field type'] = 'property';
			} elseif ( array_key_exists( 'autocomplete', $other_args ) ||
				array_key_exists( 'remote autocompletion', $other_args ) ) {
				$other_args['autocompletion source'] = $this->template_field->getSemanticProperty();
				$other_args['autocomplete field type'] = 'property';
			}
		}
	}

	/**
	 * Since Page Forms uses a hook system for the functions that
	 * create HTML inputs, most arguments are contained in the "$other_args"
	 * array - create this array, using the attributes of this form
	 * field and the template field it corresponds to, if any.
	 * @param Parser $parser
	 * @param array|null $default_args
	 * @return array
	 */
	public function getArgumentsForInputCall( Parser $parser, ?array $default_args = null ) {
		return $this->mInstanceField->getArgumentsForInputCall( $parser, $default_args );
	}
}
