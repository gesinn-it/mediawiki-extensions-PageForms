<?php
/**
 * SF_Select input type — a dynamically-populated <select> element whose
 * option values are retrieved from an SMW #ask query or a parser function
 * via the `sformsselect` API.
 *
 * Originally maintained as the SemanticFormsSelect extension.
 * Integrated into Page Forms as a native input type.
 *
 * @author Jason Zhang
 * @author Toni Hermoso Pulido
 * @author Alexander Gesinn
 * @file
 * @ingroup PFFormInput
 */

use MediaWiki\MediaWikiServices;

/**
 * @ingroup PFFormInput
 */
class PFSFSelectInput extends PFFormInput {

	public static function getName(): string {
		return 'SF_Select';
	}

	public static function getParameters() {
		$params = parent::getParameters();
		return $params;
	}

	/**
	 * Returns the HTML code for this input.
	 *
	 * @param string $cur_value
	 * @param string $input_name
	 * @param bool $is_mandatory
	 * @param bool $is_disabled
	 * @param array $other_args
	 * @return string
	 */
	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsFieldNum, $wgPageFormsSFSelectConfig;

		$parser = MediaWikiServices::getInstance()->getParser();
		$selectField = new PFSFSelectField( $parser );

		// 'delimiter' must be read before 'query' or 'function'
		$selectField->setDelimiter( $other_args );

		if ( array_key_exists( 'query', $other_args ) ) {
			$selectField->setQuery( $other_args );
		} elseif ( array_key_exists( 'function', $other_args ) ) {
			$selectField->setFunction( $other_args );
		}

		if ( array_key_exists( 'label', $other_args ) ) {
			$selectField->setLabel( $other_args );
		}

		// Dynamic fields need their metadata pushed to the JS side.
		if ( !$selectField->hasStaticValues() ) {
			$selectField->setSelectIsMultiple( $other_args );
			$selectField->setSelectTemplate( $input_name );
			$selectField->setSelectField( $input_name );
			$selectField->setValueTemplate( $other_args );
			$selectField->setValueField( $other_args );
			$selectField->setSelectRemove( $other_args );

			// Accumulate SF_Select field data for JS export via sf_select.
			if ( !is_array( $wgPageFormsSFSelectConfig ) ) {
				$wgPageFormsSFSelectConfig = [];
			}
			$wgPageFormsSFSelectConfig[] = $selectField->getData();
		}

		// Build the <select> element.
		$isList = !empty( $other_args['is_list'] );
		$extraAttr = $isList ? ' multiple="multiple"' : '';

		if ( array_key_exists( 'size', $other_args ) ) {
			$extraAttr .= ' size="' . (int)$other_args['size'] . '"';
		}

		$classes = [];
		if ( $is_mandatory ) {
			$classes[] = 'mandatoryField';
		}
		if ( array_key_exists( 'class', $other_args ) ) {
			$classes[] = $other_args['class'];
		}
		if ( $classes ) {
			$extraAttr .= ' class="' . htmlspecialchars( implode( ' ', $classes ) ) . '"';
		}

		$inputId = "input_$wgPageFormsFieldNum";
		$inputName = $isList ? $input_name . '[]' : $input_name;

		$spanClass = 'inputSpan select-sfs';
		if ( !$isList ) {
			$spanClass .= ' select-sfs-single';
		}
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}

		$ret = "<span class=\"$spanClass\"><select name='$inputName' id='$inputId'$extraAttr>";
		$ret .= '<option></option>';

		$curvalues = self::parseCurValues( $cur_value, $selectField->getDelimiter() );

		if ( $selectField->hasStaticValues() ) {
			$ret .= self::renderStaticOptions( $selectField->getValues(), $curvalues, $other_args );
		} else {
			foreach ( $curvalues as $cur ) {
				$selected = in_array( $cur, $curvalues ) ? " selected='selected'" : '';
				$ret .= "<option$selected>" . htmlspecialchars( $cur ) . '</option>';
			}
		}

		$ret .= '</select></span>';
		$ret .= "<span id=\"info_$wgPageFormsFieldNum\" class=\"errorMessage\"></span>";

		if ( $isList ) {
			$hiddenName = $input_name . '[is_list]';
			$ret .= "<input type='hidden' name='$hiddenName' value='1' />";
		}

		return $ret;
	}

	public function getHtmlText(): string {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			array_key_exists( 'mandatory', $this->mOtherArgs ),
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}

	public function getResourceModuleNames() {
		return [ 'ext.pageforms.sfselect' ];
	}

	// -------------------------------------------------------------------------

	/**
	 * Parse $cur_value into an array of trimmed values.
	 *
	 * @param string|null $cur_value
	 * @param string $delimiter
	 * @return string[]
	 */
	private static function parseCurValues( $cur_value, $delimiter ) {
		if ( !$cur_value ) {
			return [];
		}
		return array_map( 'trim', explode( $delimiter, $cur_value ) );
	}

	/**
	 * Render <option> elements for a static value list.
	 *
	 * When the `label` arg is present, values are in "Key (Label)" format
	 * as returned by SMW plainlist results.
	 *
	 * @param string[]|null $values
	 * @param string[] $curvalues
	 * @param array $other_args
	 * @return string HTML fragment
	 */
	private static function renderStaticOptions( $values, $curvalues, $other_args ) {
		if ( !is_array( $values ) ) {
			return '';
		}
		$html = '';
		$hasLabel = array_key_exists( 'label', $other_args );
		if ( $hasLabel ) {
			$labelArray = self::parseLabelValues( $values );
		}
		foreach ( $values as $val ) {
			// Strip HTML-formatted SMW values produced by recent SMW versions.
			if ( strpos( $val, '<span class="smw-value">' ) !== false ) {
				preg_match( '/<span class="smw-value">(.*?)<\/span>/', $val, $m );
				if ( isset( $m[1] ) ) {
					$val = $m[1];
				}
			}
			if ( $hasLabel && isset( $labelArray[$val] ) ) {
				$selected = in_array( $labelArray[$val][0], $curvalues ) ? " selected='selected'" : '';
				$html .= "<option$selected value='" . htmlspecialchars( $labelArray[$val][0] ) . "'>"
					. htmlspecialchars( $labelArray[$val][1] ) . '</option>';
			} else {
				$selected = in_array( $val, $curvalues ) ? " selected='selected'" : '';
				$html .= "<option$selected>" . htmlspecialchars( $val ) . '</option>';
			}
		}
		return $html;
	}

	/**
	 * Parse values in "Key (Label)" format into a lookup array.
	 *
	 * @param string[] $values
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function parseLabelValues( $values ) {
		$labelArray = [];
		foreach ( $values as $label ) {
			$labelKey = $label;
			$labelValue = $label;
			if ( strpos( $label, '(' ) !== false && strpos( $label, ')' ) !== false ) {
				$labelArr = str_split( $label );
				$end = count( $labelArr ) - 1;
				$iter = $end;
				$endBr = $end;
				$startBr = 0;
				$openBr = 0;
				$doneBr = 0;
				$num = 0;
				while ( $doneBr === 0 && $iter >= 0 ) {
					$char = $labelArr[$iter];
					if ( $char === ')' ) {
						$openBr -= 1;
						if ( $num === 0 ) {
							$endBr = $iter;
							$num += 1;
						}
					}
					if ( $char === '(' ) {
						$openBr += 1;
						if ( $num > 0 && $openBr === 0 ) {
							$startBr = $iter;
							$doneBr = 1;
						}
					}
					$iter -= 1;
				}
				$labelValue = implode( '', array_slice( $labelArr, $startBr + 1, $endBr - $startBr - 1 ) );
				$labelKey = implode( '', array_slice( $labelArr, 0, $startBr - 1 ) );
			}
			$labelArray[$label] = [ $labelKey, $labelValue ];
		}
		return $labelArray;
	}
}
