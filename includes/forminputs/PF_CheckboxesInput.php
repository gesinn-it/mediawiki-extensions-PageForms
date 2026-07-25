<?php
/**
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\FormUtils;
use MediaWiki\Extension\PageForms\PossibleValueList;

/**
 * @ingroup PFFormInput
 */
class PFCheckboxesInput extends PFMultiEnumInput {

	public static function getName(): string {
		return 'checkboxes';
	}

	public static function getDefaultPropTypeLists() {
		return [
			'enumeration' => []
		];
	}

	public static function getOtherPropTypeListsHandled() {
		return [];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;

		$labelClass = 'checkboxLabel';
		if ( array_key_exists( 'class', $other_args ) ) {
			$labelClass .= ' ' . $other_args['class'];
		}
		$input_id = "input_$wgPageFormsFieldNum";
		// get list delimiter - default is comma
		if ( array_key_exists( 'delimiter', $other_args ) ) {
			$delimiter = $other_args['delimiter'];
		} else {
			$delimiter = ',';
		}
		$cur_values = PFValuesUtils::getValuesArray( $cur_value, $delimiter );

		$possible_values = $other_args['possible_values'];
		if ( $possible_values == null ) {
			$possible_values = [];
		}
		$possibleValueList = new PossibleValueList( $possible_values, $other_args['value_labels'] ?? null );
		$text = '';
		foreach ( $possibleValueList->all() as $key => $possibleValue ) {
			$possible_value = $possibleValue->getValue();
			$cur_input_name = $input_name . '[' . $key . ']';
			$label = $possibleValue->getLabel();

			$checkbox_attrs = [
				'name' => $cur_input_name,
				'value' => $possible_value,
				'id' => $input_id,
				'tabIndex' => $wgPageFormsTabIndex,
				'label' => 'checkbox'
			];
			if ( in_array( $possible_value, $cur_values ) ) {
				$checkbox_attrs['checked'] = 'checked';
				$checkbox_attrs['selected'] = true;
			}
			if ( $is_disabled ) {
				$checkbox_attrs['disabled'] = 'disabled';
			}
			$checkbox_input = new OOUI\CheckboxInputWidget( $checkbox_attrs ) . "<t />";

			// Put a <label> tag around each checkbox, for CSS
			// purposes as well as to clarify this element.
			$text .= "\t" . Html::rawElement( 'label',
				[ 'class' => $labelClass ],
				$checkbox_input . '&nbsp;' . $label
			) . " ";
			$wgPageFormsTabIndex++;
			$wgPageFormsFieldNum++;
		}

		$outerSpanID = "span_$wgPageFormsFieldNum";
		$outerSpanClass = self::buildSpanClass( 'checkboxesSpan', $is_mandatory );

		// @HACK! The current "select all/none" JS code doesn't work
		// when this input is part of a multiple-instance template, so
		// if that happens, just don't display those links.
		// Unfortunately, there's no easy way to know if we're in a
		// multiple-instance template, so look at the input name - if
		// it contains "[num][", we can assume that we are.
		// @TODO - get the JS working in multiple-instance templates -
		// this will probably require rewriting the checkboxes JS
		// to some extent, so the relevant part can be called each
		// time an instance is added.
		if ( str_contains( $input_name, '[num][' ) ) {
			// Multiple-instance template; do nothing.
		} elseif ( array_key_exists( 'show select all', $other_args ) ||
			( count( $possible_values ) >= $GLOBALS[ 'wgPageFormsCheckboxesSelectAllMinimum' ] &&
				!array_key_exists( 'hide select all', $other_args ) ) ) {
			$outerSpanClass .= ' select-all';
		}

		if ( array_key_exists( 'show on select', $other_args ) ) {
			$outerSpanClass .= ' pfShowIfChecked';
			FormUtils::setShowOnSelect( $other_args['show on select'], $outerSpanID );
		}

		$text .= Html::hidden( $input_name . '[is_list]', 1 );
		$outerSpanAttrs = [ 'id' => $outerSpanID, 'class' => $outerSpanClass ];
		$text = "\t" . Html::rawElement( 'span', $outerSpanAttrs, $text ) . "\n";

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
