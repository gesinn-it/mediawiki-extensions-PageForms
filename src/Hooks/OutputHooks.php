<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Hooks;

use EDParserFunctions;

/**
 * Hook handlers that add Page Forms' data to the page output.
 *
 * @ingroup PF
 */
class OutputHooks {

	/**
	 * @param array &$vars JavaScript config variables
	 * @param \MediaWiki\Output\OutputPage $out
	 * @return bool Always true
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ) {
		global $wgPageFormsTargetName;
		global $wgPageFormsAutocompleteValues, $wgPageFormsAutocompleteOnAllChars;
		global $wgPageFormsFieldProperties, $wgPageFormsDependentFields;
		global $wgPageFormsGridValues, $wgPageFormsGridParams;
		global $wgPageFormsCalendarValues, $wgPageFormsCalendarParams, $wgPageFormsCalendarHTML;
		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;
		global $wgPageFormsHeightForMinimizingInstances;
		global $wgPageFormsShowOnSelect, $wgPageFormsScriptPath;
		global $edgValues, $wgPageFormsEDSettings;
		global $wgAmericanDates, $wgPageForms24HourTime;

		$vars['wgPageFormsTargetName'] = $wgPageFormsTargetName;
		$vars['wgPageFormsAutocompleteValues'] = $wgPageFormsAutocompleteValues;
		$vars['wgPageFormsAutocompleteOnAllChars'] = $wgPageFormsAutocompleteOnAllChars;
		$vars['wgPageFormsFieldProperties'] = $wgPageFormsFieldProperties;
		$vars['wgPageFormsDependentFields'] = $wgPageFormsDependentFields;
		$vars['wgPageFormsCalendarValues'] = $wgPageFormsCalendarValues;
		$vars['wgPageFormsCalendarParams'] = $wgPageFormsCalendarParams;
		$vars['wgPageFormsCalendarHTML'] = $wgPageFormsCalendarHTML;
		$vars['wgPageFormsGridValues'] = $wgPageFormsGridValues;
		$vars['wgPageFormsGridParams'] = $wgPageFormsGridParams;
		$vars['wgPageFormsContLangYes'] = $wgPageFormsContLangYes;
		$vars['wgPageFormsContLangNo'] = $wgPageFormsContLangNo;
		$vars['wgPageFormsContLangMonths'] = $wgPageFormsContLangMonths;
		$vars['wgPageFormsHeightForMinimizingInstances'] = $wgPageFormsHeightForMinimizingInstances;
		$vars['wgPageFormsShowOnSelect'] = $wgPageFormsShowOnSelect;
		$vars['wgPageFormsScriptPath'] = $wgPageFormsScriptPath;
		if ( method_exists( 'EDParserFunctions', 'getAllValues' ) ) {
			// External Data 2.3+
			$vars['edgValues'] = EDParserFunctions::getAllValues();
		} else {
			$vars['edgValues'] = $edgValues;
		}
		$vars['wgPageFormsEDSettings'] = $wgPageFormsEDSettings;
		$vars['wgAmericanDates'] = $wgAmericanDates;
		$vars['wgPageForms24HourTime'] = (bool)$wgPageForms24HourTime;

		global $wgPageFormsSFSelectConfig;
		$vars['sf_select'] = json_encode( $wgPageFormsSFSelectConfig ?? [] );

		return true;
	}

}
