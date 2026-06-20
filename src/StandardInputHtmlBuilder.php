<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Parser;
use PFFormUtils;
use Sanitizer;
use Title;
use WebRequest;

/**
 * Assembles the HTML fragment for a single standard-input tag within a PageForms form.
 */
class StandardInputHtmlBuilder {

	/**
	 * @param string $inputName
	 * @param array $tagComponents
	 * @param bool $formIsDisabled
	 * @param bool $formSubmitted
	 * @param WebRequest $request
	 * @param Parser $parser
	 * @param Title|null $pageTitle The resolved page title (mPageTitle from PFFormPrinter).
	 * @param string|null $pageName The raw page name string passed to formHTML().
	 * @return string HTML fragment
	 */
	public function buildHtml(
		string $inputName,
		array $tagComponents,
		bool $formIsDisabled,
		bool $formSubmitted,
		WebRequest $request,
		Parser $parser,
		?Title $pageTitle,
		?string $pageName
	): string {
		$inputLabel = null;
		$attr = [];

		for ( $i = 2; $i < count( $tagComponents ); $i++ ) {
			$component = $tagComponents[$i];
			$subComponents = array_map( 'trim', explode( '=', $component ) );
			if ( count( $subComponents ) == 2 ) {
				switch ( $subComponents[0] ) {
					case 'label':
						$inputLabel = $parser->recursiveTagParse( $subComponents[1] );
						break;
					case 'class':
						$attr['class'] = $subComponents[1];
						break;
					case 'style':
						$attr['style'] = Sanitizer::checkCSS( $subComponents[1] );
						break;
				}
			}
		}

		if ( $inputName == 'summary' ) {
			$value = $request->getVal( 'wpSummary' );
			return (string)PFFormUtils::summaryInputHTML( $formIsDisabled, $inputLabel, $attr, $value );
		} elseif ( $inputName == 'minor edit' ) {
			$isChecked = $request->getCheck( 'wpMinoredit' );
			return (string)PFFormUtils::minorEditInputHTML(
				$formSubmitted, $formIsDisabled, $isChecked, $inputLabel, $attr
			);
		} elseif ( $inputName == 'watch' ) {
			$isChecked = $request->getCheck( 'wpWatchthis' );
			return (string)PFFormUtils::watchInputHTML(
				$formSubmitted, $formIsDisabled, $isChecked, $inputLabel, $attr
			);
		} elseif ( $inputName == 'save' ) {
			return (string)PFFormUtils::saveButtonHTML( $formIsDisabled, $inputLabel, $attr );
		} elseif ( $inputName == 'save and continue' ) {
			// Omit the button in one-step-process where the page title already matches
			// the destination page name (embedded/query contexts set a different title).
			if ( $pageTitle == $pageName ) {
				return (string)PFFormUtils::saveAndContinueButtonHTML( $formIsDisabled, $inputLabel, $attr );
			}
			return '';
		} elseif ( $inputName == 'preview' ) {
			return (string)PFFormUtils::showPreviewButtonHTML( $formIsDisabled, $inputLabel, $attr );
		} elseif ( $inputName == 'changes' ) {
			return (string)PFFormUtils::showChangesButtonHTML( $formIsDisabled, $inputLabel, $attr );
		} elseif ( $inputName == 'cancel' ) {
			return (string)PFFormUtils::cancelLinkHTML( $formIsDisabled, $inputLabel, $attr );
		} elseif ( $inputName == 'run query' ) {
			return (string)PFFormUtils::runQueryButtonHTML( $formIsDisabled, $inputLabel, $attr );
		}

		return '';
	}
}
