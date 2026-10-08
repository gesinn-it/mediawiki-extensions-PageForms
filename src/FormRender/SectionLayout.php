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
	 * Adds the section ($context->section) to the form ($context->formText). For a template
	 * that allows multiple instances this is called once per instance, see
	 * TemplateInForm::hasInstancesLeftToPrint().
	 *
	 * @param FormRenderContext $context
	 */
	public function finish( FormRenderContext $context ): void {
		$tif = $context->tif;
		if ( !$tif ) {
			$context->formText .= $context->section;
		} elseif ( $tif->allowsMultiple() ) {
			$this->finishMultiple( $tif, $context );
		} else {
			$this->finishSingle( $tif, $context );
		}
	}

	private function finishSingle( TemplateInForm $tif, FormRenderContext $context ): void {
		$context->formText .= $this->openingHtml( $tif );
		if ( $tif->getDisplay() == 'table' ) {
			$context->formText .= $this->tableHtml( $tif, 0, $context );
		} elseif ( $tif->getLabel() != null ) {
			$context->formText .= $context->section . "\n</fieldset>";
		} else {
			$context->formText .= $context->section;
		}
	}

	private function finishMultiple( TemplateInForm $tif, FormRenderContext $context ): void {
		if ( $context->request->sourceIsPage && !$tif->allInstancesPrinted() ) {
			// The parameters of this instance's template call that the form does not define,
			// as hidden inputs of the instance. (The "end template" tag is only handled once
			// for all instances, so it cannot do this.)
			$context->section .= FormMarkup::unhandledFieldsHTML( $tif );
		}

		// The fieldset and the intro are only added before the first instance.
		$html = $tif->getInstanceNum() == 0 ? $this->openingHtml( $tif ) : '';
		if ( $tif->getDisplay() == 'spreadsheet' ) {
			$html .= $this->spreadsheetHtml( $tif, $context );
		} elseif ( $tif->getDisplay() == 'calendar' ) {
			$html .= $this->calendarHtml( $tif, $context );
		} else {
			$html .= $this->instancesHtml( $tif, $context );
		}

		$placeholder = $tif->getPlaceholder();
		if ( $placeholder == null ) {
			$context->formText .= $html;
		} else {
			// The HTML goes to the place of the placeholder in the form, followed by the
			// placeholder again, so that the next instance is added after this one.
			$marker = FormPlaceholder::toHtmlMarker( $placeholder );
			$context->formText = str_replace( $marker, $html . $marker, $context->formText );
		}
	}

	/**
	 * The fieldset with its legend, if the template has a label, followed by the intro.
	 */
	private function openingHtml( TemplateInForm $tif ): string {
		$html = '';
		if ( $tif->getLabel() != null ) {
			$html .= "<fieldset>\n" . Html::element( 'legend', [], $tif->getLabel() ) . "\n";
		}
		return $html . $tif->getIntro();
	}

	/**
	 * A spreadsheet is one grid for all instances, so it is added with the last one.
	 */
	private function spreadsheetHtml( TemplateInForm $tif, FormRenderContext $context ): string {
		if ( !$tif->allInstancesPrinted() ) {
			return '';
		}
		$html = (string)$this->spreadsheetHtmlBuilder->spreadsheetHTML(
			$tif, $context->request->out, $context->request->scriptPath
		);
		if ( $tif->getLabel() != null ) {
			$html .= "</fieldset>\n";
		}
		return $html;
	}

	/**
	 * A calendar shows all instances, so it is added with the last one.
	 */
	private function calendarHtml( TemplateInForm $tif, FormRenderContext $context ): string {
		if ( !$tif->allInstancesPrinted() ) {
			return '';
		}
		return $this->calendarHtmlBuilder->calendarHTML( $tif, $context->request->scriptPath ) . "</fieldset>\n";
	}

	/**
	 * The start of the list of instances, one instance and, after the last one, the end of
	 * the list with the template for new instances.
	 */
	private function instancesHtml( TemplateInForm $tif, FormRenderContext $context ): string {
		if ( $tif->getDisplay() == 'table' ) {
			$context->section = $this->tableHtml( $tif, $tif->getInstanceNum(), $context );
		}
		$html = '';
		if ( $tif->getInstanceNum() == 0 ) {
			$html .= $this->multipleTemplateHtmlBuilder->multipleTemplateStartHTML( $tif );
		}
		if ( !$tif->allInstancesPrinted() ) {
			return $html . $this->multipleTemplateHtmlBuilder->multipleTemplateInstanceHTML(
				$tif, $context->formIsDisabled, $context->section
			);
		}
		return $html . $this->multipleTemplateHtmlBuilder->multipleTemplateEndHTML(
			$tif, $context->formIsDisabled, $context->section, $context->counters
		);
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
