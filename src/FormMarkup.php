<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;

/**
 * Small pieces of markup of a form: hidden inputs for unhandled template parameters, section
 * headers, the loading overlay and the show-on-select setup.
 *
 * @ingroup PF
 */
class FormMarkup {

	/**
	 * Add a hidden input for each field in the template call that's
	 * not handled by the form itself.
	 *
	 * For a multiple-instance template, the inputs go into the instance's own request values
	 * ("Template[num][_unhandled][param]", with "num" replaced by the instance like for any
	 * other input of the instance), so each instance keeps its own parameters.
	 *
	 * @param TemplateInForm|null $template_in_form
	 * @return string
	 */
	public static function unhandledFieldsHTML( $template_in_form ) {
		// This shouldn't happen, but sometimes this value is null.
		// @TODO - fix the code that calls this function so the
		// value is never null.
		if ( $template_in_form === null ) {
			return '';
		}

		// HTML element names shouldn't contain spaces
		$templateName = str_replace( ' ', '_', $template_in_form->getTemplateName() );
		$text = "";
		foreach ( $template_in_form->getValuesFromPage() as $key => $value ) {
			if ( $key !== null && !is_numeric( $key ) ) {
				$key = urlencode( $key );
				if ( $template_in_form->allowsMultiple() ) {
					$text .= Html::hidden( $templateName . '[num][_unhandled][' . $key . ']', $value );
				} else {
					$text .= Html::hidden( '_unhandled_' . $templateName . '_' . $key, $value );
				}
			}
		}
		return $text;
	}

	/**
	 * Get section header HTML
	 * @param string $header_name
	 * @param int $header_level
	 * @return string
	 */
	public static function headerHTML( $header_name, $header_level = 2 ) {
		$counters = FormCounters::current();

		$counters->tabIndex++;
		$text = "";

		if ( !is_numeric( $header_level ) ) {
			// The default header level is set to 2
			$header_level = 2;
		}

		$header_level = min( $header_level, 6 );
		$elementName = 'h' . $header_level;
		$text = Html::rawElement( $elementName, [], $header_name );
		return $text;
	}

	/**
	 * Returns HTML for a loading overlay (spinner + background mask).
	 *
	 * Moved here from FormUtils, which keeps a deprecated forward for
	 * backward compatibility with external callers.
	 *
	 * @return string
	 */
	public static function displayLoadingImage() {
		global $wgPageFormsScriptPath;

		$text = '<div id="loadingMask"></div>';
		$loadingBGImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loadingbg.png" ] );
		$text .= '<div style="position: fixed; left: 50%; top: 50%;">' . $loadingBGImage . '</div>';
		$loadingImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ] );
		$text .= '<div style="position: fixed; left: 50%; top: 50%; padding: 48px;">' . $loadingImage . '</div>';

		return Html::rawElement( 'span', [ 'class' => 'loadingImage' ], $text );
	}

	public static function setShowOnSelect( $showOnSelectVals, $inputID, $isCheckbox = false ) {
		global $wgPageFormsShowOnSelect;

		foreach ( $showOnSelectVals as $divID => $options ) {
			// A checkbox will just have div ID(s).
			$data = $isCheckbox ? $divID : [ $options, $divID ];
			if ( array_key_exists( $inputID, $wgPageFormsShowOnSelect ) ) {
				$wgPageFormsShowOnSelect[$inputID][] = $data;
			} else {
				$wgPageFormsShowOnSelect[$inputID] = [ $data ];
			}
		}
	}

}
