<?php
/**
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\FormUtils;
use MediaWiki\Extension\PageForms\PossibleValueList;

/**
 * @ingroup PFFormInput
 */
class PFDropdownInput extends PFEnumInput {

	public static function getName(): string {
		return 'dropdown';
	}

	public static function getDefaultPropTypes() {
		return [
			'enumeration' => []
		];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_boo' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;

		// Standardize $cur_value
		if ( $cur_value === null ) {
			$cur_value = '';
		}

		$className = ( $is_mandatory ) ? 'mandatoryField' : 'createboxInput';
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}
		$input_id = "input_$wgPageFormsFieldNum";
		if ( array_key_exists( 'show on select', $other_args ) ) {
			$className .= ' pfShowIfSelected';
			FormUtils::setShowOnSelect( $other_args['show on select'], $input_id );
		}
		$innerDropdown = '';
		// Add a blank value at the beginning, unless this is a
		// mandatory field and there's a current value in place
		// (either through a default value or because we're editing
		// an existing page).
		if ( !$is_mandatory || $cur_value === '' ) {
			$innerDropdown .= "	<option value=\"\"></option>\n";
		}
		$possible_values = $other_args['possible_values'];
		if ( $possible_values == null ) {
			// If it's a Boolean property, display 'Yes' and 'No'
			// as the values.
			if ( array_key_exists( 'property_type', $other_args ) && $other_args['property_type'] == '_boo' ) {
				$possible_values = [
					PFUtils::getWordForYesOrNo( true ),
					PFUtils::getWordForYesOrNo( false ),
				];
			} else {
				$possible_values = [];
			}
		}
		// Dropdown historically ignores possible_values' array keys (unlike
		// PFTokensInput/PFComboBoxInput, it never supports a canonical
		// value => displayLabel map) - build the list from values only, so
		// PossibleValueList's key-as-canonical-value handling doesn't change
		// behavior here.
		$valueLabels = $other_args['value_labels'] ?? null;
		$possibleValueList = new PossibleValueList( array_values( $possible_values ), $valueLabels );
		foreach ( $possibleValueList->all() as $possibleValue ) {
			$possible_value = $possibleValue->getValue();
			$optionAttrs = [ 'value' => $possible_value ];
			if ( $possible_value == $cur_value ) {
				$optionAttrs['selected'] = "selected";
			}
			$innerDropdown .= Html::element( 'option', $optionAttrs, $possibleValue->getLabel() );
		}
		$selectAttrs = [
			'id' => $input_id,
			'tabindex' => $wgPageFormsTabIndex,
			'name' => $input_name,
			'class' => $className
		];
		if ( $is_disabled ) {
			$selectAttrs['disabled'] = 'disabled';
		}
		if ( array_key_exists( 'origName', $other_args ) ) {
			$selectAttrs['origname'] = $other_args['origName'];
		}
		$text = Html::rawElement( 'select', $selectAttrs, $innerDropdown );
		$spanClass = self::buildSpanClass( 'inputSpan', $is_mandatory );
		$text = Html::rawElement( 'span', [ 'class' => $spanClass ], $text );
		return $text;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
