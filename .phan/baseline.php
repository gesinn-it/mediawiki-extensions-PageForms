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
    // PhanTypeMismatchArgumentProbablyReal : 20+ occurrences
    // PhanUndeclaredClassMethod : 15+ occurrences
    // PhanTypeMismatchArgumentNullable : 10+ occurrences
    // PhanUndeclaredMethod : 10+ occurrences
    // PhanTypeMismatchArgument : 7 occurrences
    // PhanTypeMismatchArgumentInternal : 7 occurrences
    // PhanTypeMismatchArgumentNullableInternal : 7 occurrences
    // PhanTypeMismatchProperty : 7 occurrences
    // PhanNonClassMethodCall : 4 occurrences
    // PhanPossiblyUndeclaredVariable : 3 occurrences
    // PhanTypeMismatchPropertyProbablyReal : 3 occurrences
    // MediaWikiNoEmptyIfDefined : 2 occurrences
    // PhanRedundantCondition : 2 occurrences
    // PhanTypeMismatchDimFetchNullable : 2 occurrences
    // PhanUndeclaredClassReference : 2 occurrences
    // PhanUndeclaredConstant : 2 occurrences
    // PhanPluginDuplicateConditionalNullCoalescing : 1 occurrence
    // PhanRedundantValueComparison : 1 occurrence
    // PhanTypeArraySuspiciousNullable : 1 occurrence
    // PhanTypeInvalidLeftOperandOfNumericOp : 1 occurrence
    // PhanTypeMismatchArgumentInternalReal : 1 occurrence
    // PhanTypeMismatchDimEmpty : 1 occurrence
    // PhanUndeclaredConstantOfClass : 1 occurrence
    // PhanUndeclaredStaticMethod : 1 occurrence

    'file_suppressions' => [
        'includes/PF_AutocompleteAPI.php' => [
            'PhanTypeMismatchArgument' => ['\\PFAutocompleteAPI::getAllValuesForProperty', '\\closure'],
            'PhanTypeMismatchArgumentInternal' => ['\\PFAutocompleteAPI::getAllValuesForProperty'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFAutocompleteAPI::computeAllValuesForProperty'],
            'PhanUndeclaredMethod' => ['\\PFAutocompleteAPI::computeAllValuesForProperty']
        ],
        'includes/PF_AutoeditAPI.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFAutoeditAPI::doStore'],
            'PhanRedundantCondition' => ['\\PFAutoeditAPI::doStore'],
            'PhanRedundantValueComparison' => ['\\PFAutoeditAPI::doStore'],
            'PhanTypeMismatchArgumentInternalReal' => ['\\PFAutoeditAPI::generateTargetName'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFAutoeditAPI::doStore'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFAutoeditAPI::finalizeResults'],
            'PhanUndeclaredConstantOfClass' => ['\\PFAutoeditAPI::generateTargetName'],
            'PhanUndeclaredMethod' => ['\\PFAutoeditAPI::getFormTitle']
        ],
        'includes/PF_FormCache.php' => [
            'PhanTypeMismatchArgument' => ['\\PFFormCache::getFormDefinition'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormCache::purgeCache']
        ],
        'includes/PF_FormField.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFFormField::newFromFormFieldTag'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormField::additionalHTMLForInput', '\\PFFormField::newFromFormFieldTag']
        ],
        'includes/PF_FormPrinter.php' => [
            'PhanNonClassMethodCall' => ['\\PFFormPrinter::showDeletionLog'],
            'PhanPossiblyUndeclaredVariable' => ['\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFFormPrinter::__construct', '\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchProperty' => ['\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchPropertyProbablyReal' => ['\\PFFormPrinter::__construct', '\\PFFormPrinter::formHTML'],
            'PhanUndeclaredMethod' => ['\\PFFormPrinter::formHTML'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormPrinter::formHTML']
        ],
        'includes/PF_FormUtils.php' => [
            'PhanTypeInvalidLeftOperandOfNumericOp' => ['\\PFFormUtils::getStringForCurrentTime'],
            'PhanTypeMismatchArgumentInternal' => ['\\PFFormUtils::getStringForCurrentTime'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormUtils::minorEditInputHTML', '\\PFFormUtils::watchInputHTML']
        ],
        'includes/PF_Hooks.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFHooks::showFormPreview'],
            'PhanUndeclaredClassMethod' => ['\\PFHooks::addToAdminLinks', '\\PFHooks::setGlobalJSVariables'],
            'PhanUndeclaredClassReference' => ['\\PFHooks::setGlobalJSVariables']
        ],
        'includes/PF_TemplateField.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFTemplateField::setTypeAndPossibleValues']
        ],
        'includes/PF_ValuesUtils.php' => [
            'MediaWikiNoEmptyIfDefined' => ['\\PFValuesUtils::getValuesFromExternalURL'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFValuesUtils::getAllCategories', '\\PFValuesUtils::getAllValuesForProperty'],
            'PhanUndeclaredClassMethod' => ['\\PFValuesUtils::getValuesFromExternalURL'],
            'PhanUndeclaredConstant' => ['\\PFValuesUtils::buildCountDescription', '\\PFValuesUtils::getAllPagesForConcept'],
            'PhanUndeclaredMethod' => ['\\PFValuesUtils::getAllValuesForProperty']
        ],
        'includes/forminputs/PF_DateInput.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFDateInput::parseDate'],
            'PhanTypeMismatchArgumentInternal' => ['\\PFDateInput::monthDropdownHTML'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFDateInput::monthDropdownHTML']
        ],
        'includes/forminputs/PF_DateTimePicker.php' => [
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFDateTimePicker::__construct'],
            'SecurityCheck-DoubleEscaped' => ['\\PFDateTimePicker::getHtmlText']
        ],
        'includes/forminputs/PF_OpenLayersInput.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFOpenLayersInput::mapLookupHTML'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFOpenLayersInput::mapLookupHTML']
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
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFTextInput::uploadableHTML'],
            'PhanUndeclaredMethod' => ['\\PFTextInput::getPreviewImage'],
            'SecurityCheck-DoubleEscaped' => ['\\PFTextInput::uploadableHTML']
        ],
        'includes/forminputs/PF_Tree.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFTree::addSubCategories']
        ],
        'includes/forminputs/PF_TreeInput.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFTreeInput::getHTML']
        ],
        'includes/parserfunctions/PF_AutoEdit.php' => [
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFAutoEdit::convertQueryString'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFAutoEdit::run'],
            'SecurityCheck-DoubleEscaped' => ['\\PFAutoEdit::run']
        ],
        'includes/parserfunctions/PF_AutoEditRating.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFAutoEditRating::run'],
            'SecurityCheck-DoubleEscaped' => ['\\PFAutoEditRating::run']
        ],
        'includes/parserfunctions/PF_FormInputParserFunction.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFFormInputParserFunction::run'],
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFFormInputParserFunction::run'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormInputParserFunction::run'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormInputParserFunction::run']
        ],
        'includes/parserfunctions/PF_FormLink.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFFormLink::createFormLink']
        ],
        'specials/PF_FormEdit.php' => [
            'PhanUndeclaredClassMethod' => ['\\PFFormEdit::showCaptcha'],
            'PhanUndeclaredClassReference' => ['\\PFFormEdit::showCaptcha'],
            'PhanUndeclaredMethod' => ['\\PFFormEdit::printForm']
        ],
        'specials/PF_FormStart.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormStart::execute'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormStart::execute']
        ],
        'specials/PF_Forms.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFForms::getPageHeader']
        ],
        'specials/PF_MultiPageEdit.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFMultiPageEdit::displaySpreadsheet', '\\PFMultiPageEdit::getPageHeader']
        ],
        'specials/PF_RunQuery.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFRunQuery::printPage']
        ],
        'specials/PF_Templates.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFTemplates::getPageHeader']
        ],
        'specials/PF_UploadForm.php' => [
            'PhanTypeMismatchDimEmpty' => ['\\PFUploadForm::__construct'],
            'PhanTypeMismatchProperty' => ['\\PFUploadForm::__construct'],
            'PhanTypeMismatchPropertyProbablyReal' => ['\\PFUploadForm::__construct']
        ],
        'specials/PF_UploadSourceField.php' => [
            'PhanUndeclaredStaticMethod' => ['\\PFUploadSourceField::userCanExecute']
        ],
        'specials/PF_UploadWindow.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFUploadWindow::execute'],
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFUploadWindow::processUpload'],
            'PhanUndeclaredMethod' => ['\\PFUploadWindow::getUploadForm', '\\PFUploadWindow::processUpload', '\\PFUploadWindow::showViewDeletedLinks', '\\PFUploadWindow::watchCheck'],
            'SecurityCheck-DoubleEscaped' => ['\\PFUploadWindow::getExistsWarning']
        ],
        'src/CalendarHtmlBuilder.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\CalendarHtmlBuilder::calendarHTML']
        ],
        'src/FormDefParser.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\MediaWiki\\Extension\\PageForms\\FormDefParser::preparePreloadData']
        ],
        'src/FormFieldHtmlBuilder.php' => [
            'PhanTypeMismatchArgument' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML'],
            'PhanTypeMismatchArgumentInternal' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML'],
            'PhanTypeMismatchDimFetchNullable' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML'],
            'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML']
        ],
        'src/FormSectionHtmlBuilder.php' => [
            'PhanTypeMismatchArgument' => ['\\MediaWiki\\Extension\\PageForms\\FormSectionHtmlBuilder::buildHtml']
        ],
        'src/SpreadsheetHtmlBuilder.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::spreadsheetHTML', '\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::tableHTML']
        ],
    ],
    // 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
    // (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
