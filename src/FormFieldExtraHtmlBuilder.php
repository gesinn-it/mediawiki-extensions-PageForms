<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;

/**
 * The HTML that goes next to the input of a field and is not part of the input itself: the
 * marker for an embedded template, the hidden input that keeps the value of a disabled field, the
 * hidden input that marks a mapped field, and the hidden inputs of a unique field.
 *
 * @ingroup PF
 */
class FormFieldExtraHtmlBuilder {

	/**
	 * @param FormField $formField
	 * @param string|array|null $curValue The value the input shows
	 * @param string $fieldName
	 * @param string|null $templateName
	 * @param FormCounters|null $counters The counters of the form being built
	 * @return string
	 */
	public function build(
		FormField $formField, $curValue, string $fieldName, ?string $templateName, ?FormCounters $counters = null
	): string {
		return $this->embeddedTemplateMarker( $formField, $fieldName, $templateName )
			. $this->disabledFieldValue( $formField, $curValue, $fieldName )
			. $this->mappedFieldMarker( $formField, $fieldName, $templateName )
			. $this->uniqueFieldInputs( $formField, $counters ?? FormCounters::current() );
	}

	/**
	 * A marker just after the hidden field, within the HTML, to locate where the multiple-templates
	 * HTML, stored in $multipleTemplateString, should be inserted.
	 */
	private function embeddedTemplateMarker( FormField $formField, string $fieldName, ?string $templateName ): string {
		if ( !$formField->holdsTemplate() ) {
			return '';
		}
		return FormPlaceholder::toHtmlMarker( FormPlaceholder::format( (string)$templateName, $fieldName ) );
	}

	/**
	 * If the field is disabled, a hidden field holds its value, because disabled inputs for some
	 * reason don't submit their value.
	 *
	 * @param FormField $formField
	 * @param string|array|null $curValue
	 * @param string $fieldName
	 * @return string
	 */
	private function disabledFieldValue( FormField $formField, $curValue, string $fieldName ): string {
		if ( !$formField->isDisabled() ) {
			return '';
		}
		if ( $fieldName == 'free text' || $fieldName == '#freetext#' ) {
			return Html::hidden( 'pf_free_text', '!free_text!' );
		}
		if ( is_array( $curValue ) ) {
			return Html::hidden(
				$formField->getInputName() ?? '', implode( $formField->getFieldArg( 'delimiter' ), $curValue )
			);
		}
		return Html::hidden( $formField->getInputName() ?? '', $curValue );
	}

	/**
	 * A field that maps values to labels tells the save that the submitted value is a label.
	 */
	private function mappedFieldMarker( FormField $formField, string $fieldName, ?string $templateName ): string {
		if ( !$formField->hasFieldArg( 'mapping template' ) &&
			!$formField->hasFieldArg( 'mapping property' ) &&
			!$formField->getUseDisplayTitle()
		) {
			return '';
		}
		if ( $formField->hasFieldArg( 'part_of_multiple' ) ) {
			return Html::hidden( $templateName . '[num][map_field][' . $fieldName . ']', 'true' );
		}
		return Html::hidden( $templateName . '[map_field][' . $fieldName . ']', 'true' );
	}

	/**
	 * The hidden inputs that tell the check for unique values which property, category, namespace
	 * or concept to look in.
	 */
	private function uniqueFieldInputs( FormField $formField, FormCounters $counters ): string {
		if ( !$formField->hasFieldArg( 'unique' ) ) {
			return '';
		}
		$text = '';
		$prefix = 'input_' . $counters->fieldNum;

		$semanticProperty = $formField->getTemplateField()->getSemanticProperty();
		if ( $semanticProperty != null ) {
			$text .= Html::hidden( $prefix . '_unique_property', $semanticProperty );
		}
		foreach ( [ 'category', 'namespace', 'concept' ] as $scope ) {
			if ( $formField->hasFieldArg( "unique_for_$scope" ) ) {
				$text .= Html::hidden( $prefix . "_unique_for_$scope", $formField->getFieldArg( "unique_for_$scope" ) );
			}
		}
		return $text;
	}
}
