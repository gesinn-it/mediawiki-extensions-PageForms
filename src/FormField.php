<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\FieldSpec;
use Parser;
use PFUtils;
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

	public function setDefaultValue( ?string $defaultValue ): void {
		$this->mDefaultValue = $defaultValue;
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

	public function setAutocapitalize( ?string $autocapitalize ): void {
		$this->mAutocapitalize = $autocapitalize;
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

	public function setIsList( bool $isList ): void {
		$this->mIsList = $isList;
	}

	public function getPreloadPage() {
		return $this->mPreloadPage;
	}

	public function setPreloadPage( ?string $preloadPage ): void {
		$this->mPreloadPage = $preloadPage;
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
	 * The possible values of this field itself, without falling back to those of the template
	 * field.
	 *
	 * @return array|null Null if the field has none (see hasOwnPossibleValues())
	 */
	public function getOwnPossibleValues() {
		return $this->mPossibleValues;
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

	public function setUseDisplayTitle( bool $useDisplayTitle ): void {
		$this->mUseDisplayTitle = $useDisplayTitle;
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

	public function setLabel( ?string $label ): void {
		$this->mLabel = $label;
	}

	public function getLabelMsg() {
		return $this->mLabelMsg;
	}

	public function setLabelMsg( ?string $labelMsg ): void {
		$this->mLabelMsg = $labelMsg;
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
		$f = new FormField();
		if ( $mappingLabels !== null ) {
			$f->mappingLabels = $mappingLabels;
		}
		$f->mFieldArgs = [];
		$f->mInstanceField = new FormInstanceField( $f );

		( new FormFieldTagReader( $f ) )->read(
			$spec, $template, $template_in_form, $form_is_disabled, $user, $parser
		);
		return $f;
	}

	/**
	 * @deprecated use FormFieldCurrentValue::cleanupTranslateTags()
	 * @param string &$value
	 */
	public function cleanupTranslateTags( &$value ) {
		( new FormFieldCurrentValue( $this ) )->cleanupTranslateTags( $value );
	}

	public function autocapitalize( $val ) {
		if ( $this->mAutocapitalize === 'words' ) {
			return ucwords( $val );
		} else {
			return $val;
		}
	}

	/**
	 * @param array|null $template_instance_query_values The request values of the template instance
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param bool $all_instances_printed
	 * @param string|null &$val_modifier Set to "+" or "-" if the value is to be added to or removed
	 *   from the existing ones
	 * @return string|array|null
	 * @see FormFieldCurrentValue::get()
	 */
	public function getCurrentValue(
		$template_instance_query_values,
		$form_submitted,
		$source_is_page,
		$all_instances_printed,
		&$val_modifier = null
	) {
		return ( new FormFieldCurrentValue( $this ) )->get(
			$template_instance_query_values, $form_submitted, $source_is_page, $all_instances_printed, $val_modifier
		);
	}

	public function setMappedValues( $mappingType ) {
		if ( $mappingType == 'template' ) {
			$this->setValuesWithMappingTemplate();
		} elseif ( $mappingType == 'property' ) {
			$this->setValuesWithMappingProperty();
		}

		$this->mPossibleValues = MappingLabels::disambiguate( $this->mPossibleValues );
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
