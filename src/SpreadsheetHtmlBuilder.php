<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;
use PFFormUtils;

/**
 * Assembles the HTML fragments for spreadsheet/table display mode within
 * a PageForms form: the per-row table and the jspreadsheet container.
 */
class SpreadsheetHtmlBuilder {

	/**
	 * Creates the HTML for the inner table used in display=table mode for
	 * every instance of a template.
	 *
	 * @param \PFTemplateInForm $tif
	 * @param int $instanceNum
	 * @param callable $fieldHtmlCallback Signature: ( $formField, $curValue ): string
	 * @return string
	 */
	public function tableHTML(
		\PFTemplateInForm $tif, int $instanceNum, callable $fieldHtmlCallback, ?FormCounters $counters = null
	): string {
		global $wgPageFormsFieldNum;

		$allGridValues = $tif->getGridValues();
		$gridValues = $allGridValues[$instanceNum] ?? null;

		$html = '';
		foreach ( $tif->getFields() as $formField ) {
			$fieldName = $formField->template_field->getFieldName();
			$curValue = $gridValues !== null ? ( $gridValues[$fieldName] ?? null ) : null;

			if ( $formField->holdsTemplate() ) {
				$attribs = [];
				if ( $formField->hasFieldArg( 'class' ) ) {
					$attribs['class'] = $formField->getFieldArg( 'class' );
				}
				$html .= '</table>' . "\n";
				$html .= Html::hidden( $formField->getInputName(), $curValue, $attribs );
				$html .= $formField->additionalHTMLForInput( $curValue, $fieldName, $tif->getTemplateName() );
				$html .= '<table class="formtable">' . "\n";
				continue;
			}

			if ( $formField->isHidden() ) {
				$attribs = [];
				if ( $formField->hasFieldArg( 'class' ) ) {
					$attribs['class'] = $formField->getFieldArg( 'class' );
				}
				$html .= Html::hidden( $formField->getInputName(), $curValue, $attribs );
				continue;
			}

			if ( $counters !== null ) {
				$counters->fieldNum++;
				$wgPageFormsFieldNum = $counters->fieldNum;
			} else {
				$wgPageFormsFieldNum++;
			}
			if ( $formField->getLabel() !== null ) {
				$labelText = $formField->getLabel();
				// @HACK - for a checkbox within display=table, 'label' is used for two
				// purposes: the label column, and the text after the checkbox. Unset
				// the value here so that it's only used for the first purpose.
				$formField->setFieldArg( 'label', '' );
			} elseif ( $formField->getLabelMsg() !== null ) {
				$labelText = wfMessage( $formField->getLabelMsg() )->parse();
			} elseif ( $formField->template_field->getLabel() !== null ) {
				$labelText = $formField->template_field->getLabel() . ':';
			} else {
				$labelText = $fieldName . ': ';
			}
			$label = Html::element( 'label',
				[ 'for' => "input_$wgPageFormsFieldNum" ],
				$labelText );

			$labelCellAttrs = [];
			if ( $formField->hasFieldArg( 'tooltip' ) ) {
				$labelCellAttrs['data-tooltip'] = $formField->getFieldArg( 'tooltip' );
			}

			$labelCell = Html::rawElement( 'th', $labelCellAttrs, $label );
			$inputHTML = $fieldHtmlCallback( $formField, $curValue );
			$inputHTML .= $formField->additionalHTMLForInput( $curValue, $fieldName, $tif->getTemplateName() );
			$inputCell = Html::rawElement( 'td', [], $inputHTML );
			$html .= Html::rawElement( 'tr', [], $labelCell . $inputCell ) . "\n";
		}

		return Html::rawElement( 'table', [ 'class' => 'formtable' ], $html );
	}

	/**
	 * Returns the autocomplete data-type and settings string pair for a
	 * spreadsheet field, derived from its form-field arguments.
	 *
	 * @param array $formFieldArgs
	 * @return array{0: string, 1: string}
	 */
	public function getSpreadsheetAutocompleteAttributes( array $formFieldArgs ): array {
		if ( array_key_exists( 'values from category', $formFieldArgs ) ) {
			return [ 'category', $formFieldArgs['values from category'] ];
		} elseif ( array_key_exists( 'values from property', $formFieldArgs ) ) {
			return [ 'property', $formFieldArgs['values from property'] ];
		} elseif ( array_key_exists( 'values from concept', $formFieldArgs ) ) {
			return [ 'concept', $formFieldArgs['values from concept'] ];
		} elseif ( array_key_exists( 'values dependent on', $formFieldArgs ) ) {
			return [ 'dep_on', '' ];
		} elseif ( array_key_exists( 'values from external data', $formFieldArgs ) ) {
			return [ 'external data', $formFieldArgs['origName'] ];
		} elseif ( array_key_exists( 'values from wikidata', $formFieldArgs ) ) {
			return [ 'wikidata', $formFieldArgs['values from wikidata'] ];
		} else {
			return [ '', '' ];
		}
	}

	/**
	 * Creates the jspreadsheet container div and populates the global
	 * variables consumed by PFFormUtils::setGlobalVarsForSpreadsheet().
	 *
	 * @param \PFTemplateInForm $tif
	 * @param \OutputPage $out
	 * @param string $scriptPath
	 * @return string|null HTML string, or null when the template has no fields
	 */
	public function spreadsheetHTML( \PFTemplateInForm $tif, \OutputPage $out, string $scriptPath ): ?string {
		global $wgPageFormsGridValues, $wgPageFormsGridParams;

		if ( count( $tif->getFields() ) === 0 ) {
			return null;
		}

		$out->addModules( 'ext.pageforms.spreadsheet' );

		$gridParams = [];
		foreach ( $tif->getFields() as $formField ) {
			$templateField = $formField->template_field;
			$formFieldArgs = $formField->getFieldArgs();
			$possibleValues = $formField->getPossibleValues();

			$inputType = $formField->getInputType();
			$gridParamValues = [ 'name' => $templateField->getFieldName() ];
			[ $autocompletedatatype, $autocompletesettings ] =
				$this->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
			if ( $formField->getLabel() !== null ) {
				$gridParamValues['label'] = $formField->getLabel();
			}
			if ( $formField->getDefaultValue() !== null ) {
				$gridParamValues['default'] = $formField->getDefaultValue();
			}
			// Spreadsheets in Page Forms don't support the tokens input,
			// so fall back to a plain text jspreadsheet editor for tokens.
			if ( $formField->isList() || $inputType == 'tokens' ) {
				$autocompletedatatype = '';
				$autocompletesettings = '';
				$gridParamValues['type'] = 'text';
			} elseif ( $possibleValues !== null && count( $possibleValues ) > 0
				&& $autocompletedatatype != 'category'
				&& $autocompletedatatype != 'concept'
				&& $autocompletedatatype != 'property' ) {
				$gridParamValues['values'] = $possibleValues;
				if ( $formField->isList() ) {
					$gridParamValues['list'] = true;
					$gridParamValues['delimiter'] = $formField->getFieldArg( 'delimiter' );
				}
			} elseif ( $inputType == 'textarea' ) {
				$gridParamValues['type'] = 'textarea';
			} elseif ( $inputType == 'checkbox' ) {
				$gridParamValues['type'] = 'checkbox';
			} elseif ( $inputType == 'date' ) {
				$gridParamValues['type'] = 'date';
			} elseif ( $inputType == 'datetime' ) {
				$gridParamValues['type'] = 'datetime';
			} elseif ( $possibleValues != null ) {
				array_unshift( $possibleValues, '' );
				$completePossibleValues = [];
				foreach ( $possibleValues as $value ) {
					$completePossibleValues[] = [ 'Name' => $value, 'Id' => $value ];
				}
				$gridParamValues['type'] = 'select';
				$gridParamValues['items'] = $completePossibleValues;
				$gridParamValues['valueField'] = 'Id';
				$gridParamValues['textField'] = 'Name';
			} else {
				$gridParamValues['type'] = 'text';
			}
			$gridParamValues['autocompletedatatype'] = $autocompletedatatype;
			$gridParamValues['autocompletesettings'] = $autocompletesettings;
			$gridParamValues['inputType'] = $inputType;
			$gridParams[] = $gridParamValues;
		}

		$templateName = $tif->getTemplateName();
		$templateDivID = str_replace( ' ', '', $templateName ) . "Grid";
		$templateDivAttrs = [
			'class' => 'pfSpreadsheet',
			'id' => $templateDivID,
			'data-template-name' => $templateName
		];
		if ( $tif->getHeight() != null ) {
			$templateDivAttrs['height'] = $tif->getHeight();
		}

		$loadingImage = Html::element( 'img', [ 'src' => "$scriptPath/skins/loading.gif" ] );
		$loadingImageDiv = '<div class="loadingImage">' . $loadingImage . '</div>';
		$text = Html::rawElement( 'div', $templateDivAttrs, $loadingImageDiv );

		$wgPageFormsGridParams[$templateName] = $gridParams;
		$wgPageFormsGridValues[$templateName] = $tif->getGridValues();

		PFFormUtils::setGlobalVarsForSpreadsheet();

		return $text;
	}
}
