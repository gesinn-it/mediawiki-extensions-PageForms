<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use Html;
use MediaWiki\Extension\PageForms\CalendarHtmlBuilder;
use MediaWiki\Extension\PageForms\FormFieldHtmlBuilder;
use MediaWiki\Extension\PageForms\FormMarkup;
use MediaWiki\Extension\PageForms\FormPlaceholder;
use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\Extension\PageForms\MultipleTemplateHtmlBuilder;
use MediaWiki\Extension\PageForms\SpreadsheetHtmlBuilder;
use MediaWiki\Extension\PageForms\TemplateInForm;

/**
 * Adds the HTML of a section of the form definition to the form, around the template it
 * belongs to: its fieldset, and the table, spreadsheet, calendar or instance markup of a
 * template that allows multiple instances.
 */
class SectionLayout {

	private MultipleTemplateHtmlBuilder $multipleTemplateHtmlBuilder;

	private SpreadsheetHtmlBuilder $spreadsheetHtmlBuilder;

	private CalendarHtmlBuilder $calendarHtmlBuilder;

	private FormFieldHtmlBuilder $formFieldHtmlBuilder;

	public function __construct(
		MultipleTemplateHtmlBuilder $multipleTemplateHtmlBuilder,
		SpreadsheetHtmlBuilder $spreadsheetHtmlBuilder,
		CalendarHtmlBuilder $calendarHtmlBuilder,
		FormFieldHtmlBuilder $formFieldHtmlBuilder
	) {
		$this->multipleTemplateHtmlBuilder = $multipleTemplateHtmlBuilder;
		$this->spreadsheetHtmlBuilder = $spreadsheetHtmlBuilder;
		$this->calendarHtmlBuilder = $calendarHtmlBuilder;
		$this->formFieldHtmlBuilder = $formFieldHtmlBuilder;
	}

	/**
	 * Adds the section ($context->section) to the form ($context->formText).
	 *
	 * @param FormRenderContext $context
	 * @return bool True if the template allows more instances, so the same section has to be
	 *  rendered again for the next one
	 */
	public function finish( FormRenderContext $context ): bool {
		global $wgOut, $wgPageFormsScriptPath;

		$tif = $context->tif;
		if ( $context->sourceIsPage && $tif && $tif->allowsMultiple()
			&& !$tif->allInstancesPrinted() ) {
			// The parameters of this instance's template call that the form does not define,
			// as hidden inputs of the instance. (The "end template" tag is only handled once
			// for all instances, so it cannot do this.)
			$context->section .= FormMarkup::unhandledFieldsHTML( $tif );
		}

		$multipleTemplateHTML = '';
		if ( $tif ) {
			if ( $tif->getLabel() != null ) {
				$fieldsetStartHTML = "<fieldset>\n"
					. Html::element( 'legend', [], $tif->getLabel() ) . "\n";
				$fieldsetStartHTML .= $tif->getIntro();
				if ( !$tif->allowsMultiple() ) {
					$context->formText .= $fieldsetStartHTML;
				} elseif ( $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) {
					$multipleTemplateHTML .= $fieldsetStartHTML;
				}
			} else {
				if ( !$tif->allowsMultiple() ) {
					$context->formText .= $tif->getIntro();
				}
				if ( $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) {
					$multipleTemplateHTML .= $tif->getIntro();
				}
			}
		}
		if ( $tif && $tif->allowsMultiple() ) {
			if ( $tif->getDisplay() == 'spreadsheet' ) {
				if ( $tif->allInstancesPrinted() ) {
					$multipleTemplateHTML .= $this->spreadsheetHtmlBuilder->spreadsheetHTML(
						$tif, $wgOut, $wgPageFormsScriptPath
					);
					// For spreadsheets, this needs
					// to be specially inserted.
					if ( $tif->getLabel() != null ) {
						$multipleTemplateHTML .= "</fieldset>\n";
					}
				}
			} elseif ( $tif->getDisplay() == 'calendar' ) {
				if ( $tif->allInstancesPrinted() ) {
					$multipleTemplateHTML .= $this->calendarHtmlBuilder->calendarHTML( $tif, $wgPageFormsScriptPath );
					$multipleTemplateHTML .= "</fieldset>\n";
				}
			} else {
				if ( $tif->getDisplay() == 'table' ) {
					$context->section = $this->tableHtml( $tif, $tif->getInstanceNum(), $context );
				}
				if ( $tif->getInstanceNum() == 0 ) {
					$multipleTemplateHTML .= $this->multipleTemplateHtmlBuilder->multipleTemplateStartHTML( $tif );
				}
				if ( !$tif->allInstancesPrinted() ) {
					$multipleTemplateHTML .= $this->multipleTemplateHtmlBuilder->multipleTemplateInstanceHTML(
						$tif, $context->formIsDisabled, $context->section
					);
				} else {
					$multipleTemplateHTML .= $this->multipleTemplateHtmlBuilder->multipleTemplateEndHTML(
						$tif, $context->formIsDisabled, $context->section, $context->counters
					);
				}
			}
			$placeholder = $tif->getPlaceholder();
			if ( $placeholder == null ) {
				// The normal process.
				$context->formText .= $multipleTemplateHTML;
			} else {
				// The template text won't be appended
				// at the end of the template like for
				// usual multiple template forms.
				// The HTML text will instead be stored in
				// the $multipleTemplateHTML variable,
				// and then added in the right
				// @insertHTML_".$placeHolderField."@"; position
				// Optimization: actually, instead of
				// separating the processes, the usual
				// multiple template forms could also be
				// handled this way if a fitting
				// placeholder tag was added.
				// We replace the HTML into the current
				// placeholder tag, but also add another
				// placeholder tag, to keep track of it.
				$multipleTemplateHTML .= FormPlaceholder::toHtmlMarker( $placeholder );
				$context->formText = str_replace(
					FormPlaceholder::toHtmlMarker( $placeholder ), $multipleTemplateHTML, $context->formText
				);
			}
			if ( !$tif->allInstancesPrinted() ) {
				// The section is rendered again for the next instance.
				$tif->incrementInstanceNum();
				return true;
			}
		} elseif ( $tif && $tif->getDisplay() == 'table' ) {
			$context->formText .= $this->tableHtml( $tif, 0, $context );
		} elseif ( $tif && !$tif->allowsMultiple() && $tif->getLabel() != null ) {
			$context->formText .= $context->section . "\n</fieldset>";
		} else {
			$context->formText .= $context->section;
		}

		return false;
	}

	private function tableHtml( TemplateInForm $tif, int $instanceNum, FormRenderContext $context ): string {
		return $this->spreadsheetHtmlBuilder->tableHTML(
			$tif, $instanceNum,
			fn ( $formField, $curValue ) => $this->formFieldHtmlBuilder->formFieldHTML(
				$formField, $curValue, $context->parser, $context->counters
			),
			$context->counters
		);
	}
}
