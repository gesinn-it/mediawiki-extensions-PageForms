<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;
use PFFormUtils;

/**
 * Assembles the HTML fragment for calendar display mode within a PageForms form:
 * the loading indicator and the FullCalendar container div.
 */
class CalendarHtmlBuilder {

	/**
	 * Builds the calendar params array and container HTML, and populates the
	 * global variables consumed by the FullCalendar ResourceLoader module.
	 *
	 * @param \PFTemplateInForm $tif
	 * @param string $scriptPath
	 * @return string HTML string
	 */
	public function calendarHTML( \PFTemplateInForm $tif, string $scriptPath ): string {
		global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;

		$params = [];
		foreach ( $tif->getFields() as $formField ) {
			$templateField = $formField->template_field;
			$inputType = $formField->getInputType();
			$values = [ 'name' => $templateField->getFieldName() ];
			if ( $formField->getLabel() !== null ) {
				$values['title'] = $formField->getLabel();
			}
			$possibleValues = $formField->getPossibleValues();
			if ( $inputType == 'textarea' ) {
				$values['type'] = 'textarea';
			} elseif ( $inputType == 'datetime' ) {
				$values['type'] = 'datetime';
			} elseif ( $inputType == 'checkbox' ) {
				$values['type'] = 'checkbox';
			} elseif ( $inputType == 'checkboxes' ) {
				$values['type'] = 'checkboxes';
			} elseif ( $inputType == 'listbox' ) {
				$values['type'] = 'listbox';
			} elseif ( $inputType == 'date' ) {
				$values['type'] = 'date';
			} elseif ( $inputType == 'rating' ) {
				$values['type'] = 'rating';
			} elseif ( $inputType == 'radiobutton' ) {
				$values['type'] = 'radiobutton';
			} elseif ( $inputType == 'tokens' ) {
				$values['type'] = 'tokens';
			} elseif ( $possibleValues != null ) {
				array_unshift( $possibleValues, '' );
				$completePossibleValues = [];
				foreach ( $possibleValues as $value ) {
					$completePossibleValues[] = [ 'Name' => $value, 'Id' => $value ];
				}
				$values['type'] = 'select';
				$values['items'] = $completePossibleValues;
				$values['valueField'] = 'Id';
				$values['textField'] = 'Name';
			} else {
				$values['type'] = 'text';
			}
			$params[] = $values;
		}

		$templateName = $tif->getTemplateName();
		$templateDivID = str_replace( ' ', '_', $templateName ) . "FullCalendar";
		$templateDivAttrs = [
			'class' => 'pfFullCalendarJS',
			'id' => $templateDivID,
			'template-name' => $templateName,
			'title-field' => $tif->getEventTitleField(),
			'event-date-field' => $tif->getEventDateField(),
			'event-start-date-field' => $tif->getEventStartDateField(),
			'event-end-date-field' => $tif->getEventEndDateField()
		];
		$loadingImage = Html::element( 'img', [ 'src' => "$scriptPath/skins/loading.gif" ] );
		$text = "<div id='fullCalendarLoading1' style='display: none;'>" . $loadingImage . "</div>";
		$text .= Html::rawElement( 'div', $templateDivAttrs, $text );

		$wgPageFormsCalendarParams[$templateName] = $params;
		$wgPageFormsCalendarValues[$templateName] = $tif->getGridValues();

		PFFormUtils::setGlobalVarsForSpreadsheet();

		return $text;
	}
}
