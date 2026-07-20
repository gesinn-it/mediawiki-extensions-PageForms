<?php
/**
 * This is an automatically generated baseline for Phan issues.
 * When Phan is invoked with --load-baseline=path/to/baseline.php,
 * The pre-existing issues listed in this file won't be emitted.
 *
 * This file can be updated by invoking Phan with --save-baseline=path/to/baseline.php
 * (can be combined with --load-baseline)
 */
return [
	// # Issue statistics:
	// SecurityCheck-DoubleEscaped : 55+ occurrences
	// PhanTypeMismatchProperty : 4 occurrences
	// PhanTypeMismatchArgumentNullableInternal : 2 occurrences
	// PhanTypeMismatchDimEmpty : 1 occurrence
	// PhanTypeMismatchPropertyProbablyReal : 1 occurrence

	'file_suppressions' => [
		'includes/forminputs/PF_DateTimePicker.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFDateTimePicker::getHtmlText']
		],
		'includes/forminputs/PF_SFSelectInput.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFSFSelectInput::getHTML']
		],
		'includes/forminputs/PF_TextInput.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFTextInput::uploadableHTML']
		],
		'includes/parserfunctions/PF_AutoEdit.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFAutoEdit::run']
		],
		'includes/parserfunctions/PF_AutoEditRating.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFAutoEditRating::run']
		],
		'includes/parserfunctions/PF_FormInputParserFunction.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFFormInputParserFunction::run']
		],
		'specials/PF_FormStart.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFFormStart::execute']
		],
		'specials/PF_UploadForm.php' => [
			'PhanTypeMismatchDimEmpty' => ['\\PFUploadForm::__construct'],
			'PhanTypeMismatchProperty' => ['\\PFUploadForm::__construct'],
			'PhanTypeMismatchPropertyProbablyReal' => ['\\PFUploadForm::__construct']
		],
		'specials/PF_UploadWindow.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\PFUploadWindow::getExistsWarning', '\\PFUploadWindow::showViewDeletedLinks']
		],
		'src/CalendarHtmlBuilder.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\CalendarHtmlBuilder::calendarHTML']
		],
		'src/FormField.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormField::additionalHTMLForInput', '\\MediaWiki\\Extension\\PageForms\\FormField::newFromFormFieldTag']
		],
		'src/FormFieldHtmlBuilder.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::addTranslatableInput', '\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML']
		],
		'src/FormPrinter.php' => [
			'PhanTypeMismatchArgumentNullableInternal' => ['\\MediaWiki\\Extension\\PageForms\\FormPrinter::formHTML'],
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormPrinter::formHTML']
		],
		'src/FormUtils.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormUtils::minorEditInputHTML', '\\MediaWiki\\Extension\\PageForms\\FormUtils::unhandledFieldsHTML', '\\MediaWiki\\Extension\\PageForms\\FormUtils::watchInputHTML']
		],
		'src/SpreadsheetHtmlBuilder.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::spreadsheetHTML', '\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::tableHTML']
		],
	],
	// 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
	// (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
