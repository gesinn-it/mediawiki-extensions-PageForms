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
    // SecurityCheck-DoubleEscaped : 35+ occurrences
    // PhanUndeclaredMethod : 6 occurrences
    // PhanTypeMismatchProperty : 4 occurrences
    // PhanNonClassMethodCall : 3 occurrences
    // MediaWikiNoEmptyIfDefined : 2 occurrences
    // PhanRedundantCondition : 2 occurrences
    // PhanPluginDuplicateConditionalNullCoalescing : 1 occurrence
    // PhanPossiblyUndeclaredVariable : 1 occurrence
    // PhanRedundantValueComparison : 1 occurrence
    // PhanTypeArraySuspiciousNullable : 1 occurrence
    // PhanTypeInvalidLeftOperandOfNumericOp : 1 occurrence
    // PhanTypeMismatchDimEmpty : 1 occurrence
    // PhanTypeMismatchPropertyProbablyReal : 1 occurrence
    // PhanUndeclaredClassMethod : 1 occurrence
    // PhanUndeclaredConstantOfClass : 1 occurrence

    'file_suppressions' => [
        'includes/PF_AutocompleteAPI.php' => [
            'PhanUndeclaredMethod' => ['\\PFAutocompleteAPI::computeAllValuesForProperty']
        ],
        'includes/PF_AutoeditAPI.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFAutoeditAPI::handleSaveStatus'],
            'PhanRedundantCondition' => ['\\PFAutoeditAPI::handleSaveStatus'],
            'PhanRedundantValueComparison' => ['\\PFAutoeditAPI::handleSaveStatus'],
            'PhanUndeclaredConstantOfClass' => ['\\PFAutoeditAPI::generateTargetName'],
            'PhanUndeclaredMethod' => ['\\PFAutoeditAPI::getFormTitle']
        ],
        'includes/PF_FormField.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\PFFormField::additionalHTMLForInput', '\\PFFormField::newFromFormFieldTag']
        ],
        'includes/PF_FormPrinter.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\PFFormPrinter::formHTML']
        ],
        'includes/PF_FormUtils.php' => [
            'PhanTypeInvalidLeftOperandOfNumericOp' => ['\\PFFormUtils::getStringForCurrentTime'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormUtils::minorEditInputHTML', '\\PFFormUtils::watchInputHTML']
        ],
        'includes/PF_ValuesUtils.php' => [
            'MediaWikiNoEmptyIfDefined' => ['\\PFValuesUtils::getValuesFromExternalURL'],
            'PhanUndeclaredClassMethod' => ['\\PFValuesUtils::getValuesFromExternalURL'],
            'PhanUndeclaredMethod' => ['\\PFValuesUtils::getAllValuesForProperty']
        ],
        'includes/forminputs/PF_DateInput.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFDateInput::parseDate']
        ],
        'includes/forminputs/PF_DateTimePicker.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\PFDateTimePicker::getHtmlText']
        ],
        'includes/forminputs/PF_RadioButtonInput.php' => [
            'PhanTypeArraySuspiciousNullable' => ['\\PFRadioButtonInput::getHTML']
        ],
        'includes/forminputs/PF_RegExpInput.php' => [
            'PhanNonClassMethodCall' => ['\\PFRegExpInput::__construct']
        ],
        'includes/forminputs/PF_SFSelectInput.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\PFSFSelectInput::getHTML']
        ],
        'includes/forminputs/PF_TextInput.php' => [
            'PhanUndeclaredMethod' => ['\\PFTextInput::getPreviewImage'],
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
        'specials/PF_FormEdit.php' => [
            'PhanUndeclaredMethod' => ['\\PFFormEdit::printForm']
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
        'src/FormFieldHtmlBuilder.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::addTranslatableInput', '\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML']
        ],
        'src/SpreadsheetHtmlBuilder.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::spreadsheetHTML', '\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::tableHTML']
        ],
    ],
    // 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
    // (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
