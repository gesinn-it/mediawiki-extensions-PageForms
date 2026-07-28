<?php
/**
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\FormUtils;
use MediaWiki\Extension\PageForms\PossibleValueList;

/**
 * @ingroup PFFormInput
 */
class PFRadioButtonInput extends PFEnumInput {

	public static function getName(): string {
		return 'radiobutton';
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;

		if ( array_key_exists( 'possible_values', $other_args ) && ( count( $other_args['possible_values'] ) > 0 ) ) {
			$possible_values = $other_args['possible_values'];
		} elseif (
			array_key_exists( 'property_type', $other_args ) &&
			$other_args['property_type'] == '_boo'
		) {
			// If it's a Boolean property, display 'Yes' and 'No'
			// as the values.
			$possible_values = [
				PFUtils::getWordForYesOrNo( true ),
				PFUtils::getWordForYesOrNo( false ),
			];
		} else {
			$possible_values = [];
		}

		// Add a "None" value at the beginning, unless this is a
		// mandatory field and there's a current value in place (either
		// through a default value or because we're editing an existing
		// page).
		if ( !$is_mandatory || $cur_value === '' ) {
			array_unshift( $possible_values, '' );
		}

		$possibleValueList = new PossibleValueList( $possible_values, $other_args['value_labels'] ?? null );

		// If $cur_value sorted outside a truncated 'values from ...' fetch
		// window and is not among the allowed options, add it as an extra
		// option instead of discarding it - clearing it here would risk
		// silently persisting an empty value if the form is saved unchanged.
		if ( $cur_value !== null && $cur_value !== '' && !$possibleValueList->contains( $cur_value ) ) {
			$possible_values[] = $cur_value;
		}

		$text = "\n";
		$itemClass = 'radioButtonItem';
		if ( array_key_exists( 'class', $other_args ) ) {
			$itemClass .= ' ' . $other_args['class'];
		}

		foreach ( $possible_values as $originalValue => $value ) {
			$wgPageFormsTabIndex++;
			$wgPageFormsFieldNum++;
			$input_id = "input_$wgPageFormsFieldNum";

			$radiobutton_attrs = [
				'value' => $value,
				'id' => $input_id,
				'tabindex' => $wgPageFormsTabIndex,
				'data-original-value' => $originalValue
			];
			if ( array_key_exists( 'origName', $other_args ) ) {
				$radiobutton_attrs['origname'] = $other_args['origName'];
			}
			$isChecked = ( $cur_value == $value );
			if ( $is_disabled ) {
				$radiobutton_attrs['disabled'] = true;
			}
			if ( $value === '' ) {
				// blank/"None" value
				$label = wfMessage( 'pf_formedit_none' )->text();
			} else {
				$possibleValue = $possibleValueList->find( $value );
				if ( $possibleValue !== null && $possibleValue->labelIsOverride() ) {
					$label = htmlspecialchars( $possibleValue->getLabel() );
				} elseif ( $possibleValue === null ) {
					// $value is the unmatched current value appended above.
					$label = htmlspecialchars( $possibleValueList->resolveMissingLabel( $value ) );
				} else {
					$label = $value;
				}
			}

			$itemAttrs = [ 'class' => $itemClass ];
			$text .= "\t" . Html::rawElement( 'label', $itemAttrs,
				Html::radio( $input_name, $isChecked, $radiobutton_attrs ) .
				'&nbsp;' . $label ) . "\n";
		}

		$spanClass = 'radioButtonSpan';
		if ( array_key_exists( 'class', $other_args ) ) {
			$spanClass .= ' ' . $other_args['class'];
		}
		$spanClass = self::buildSpanClass( $spanClass, $is_mandatory );

		$spanID = "span_$wgPageFormsFieldNum";

		// Do the 'show on select' handling.
		if ( array_key_exists( 'show on select', $other_args ) ) {
			$spanClass .= ' pfShowIfChecked';
			FormUtils::setShowOnSelect( $other_args['show on select'], $spanID );
		}
		$spanAttrs = [
			'id' => $spanID,
			'class' => $spanClass
		];
		$text = Html::rawElement( 'span', $spanAttrs, $text );

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
