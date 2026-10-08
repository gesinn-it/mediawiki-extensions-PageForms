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
	// SecurityCheck-DoubleEscaped : 10+ occurrences
	// PhanTypeMismatchProperty : 4 occurrences
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
		'src/FormField.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormField::newFromFormFieldTag']
		],
		'src/FormRender/PageTextAssembler.php' => [
			'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormRender\\PageTextAssembler::createPageText']
		],
	],
	// 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
	// (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
