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
    // PhanUndeclaredClassMethod : 50+ occurrences
    // SecurityCheck-DoubleEscaped : 45+ occurrences
    // PhanTypeMismatchArgumentProbablyReal : 25+ occurrences
    // PhanPossiblyUndeclaredVariable : 20+ occurrences
    // PhanPluginDuplicateConditionalNullCoalescing : 10+ occurrences
    // PhanTypeMismatchArgumentNullable : 10+ occurrences
    // PhanUndeclaredMethod : 10+ occurrences
    // PhanTypeMismatchArgument : 7 occurrences
    // PhanTypeMismatchArgumentInternal : 7 occurrences
    // PhanTypeMismatchArgumentNullableInternal : 7 occurrences
    // PhanTypeMismatchProperty : 7 occurrences
    // PhanUndeclaredConstant : 7 occurrences
    // PhanUndeclaredClassConstant : 6 occurrences
    // PhanRedundantCondition : 5 occurrences
    // PhanTypeMismatchReturn : 5 occurrences
    // PhanTypeMismatchReturnProbablyReal : 4 occurrences
    // PhanTypeMismatchPropertyProbablyReal : 3 occurrences
    // MediaWikiNoEmptyIfDefined : 2 occurrences
    // PhanNonClassMethodCall : 2 occurrences
    // PhanTypeMismatchDimFetchNullable : 2 occurrences
    // PhanUndeclaredClassInstanceof : 2 occurrences
    // PhanUndeclaredClassReference : 2 occurrences
    // PhanUndeclaredStaticMethod : 2 occurrences
    // PhanParamTooFewInPHPDoc : 1 occurrence
    // PhanRedundantValueComparison : 1 occurrence
    // PhanTypeArraySuspiciousNullable : 1 occurrence
    // PhanTypeInvalidLeftOperandOfNumericOp : 1 occurrence
    // PhanTypeMismatchArgumentInternalReal : 1 occurrence
    // PhanTypeMismatchDimEmpty : 1 occurrence
    // PhanTypeMismatchReturnNullable : 1 occurrence
    // PhanTypeSuspiciousStringExpression : 1 occurrence
    // PhanUndeclaredClassProperty : 1 occurrence
    // PhanUndeclaredConstantOfClass : 1 occurrence
    // PhanUndeclaredTypeReturnType : 1 occurrence
    // SecurityCheck-ReDoS : 1 occurrence
    // SecurityCheck-XSS : 1 occurrence

    'file_suppressions' => [
        'includes/PF_AutocompleteAPI.php' => [
            'PhanTypeMismatchArgument' => ['\\PFAutocompleteAPI::getAllValuesForProperty', '\\closure'],
            'PhanTypeMismatchArgumentInternal' => ['\\PFAutocompleteAPI::getAllValuesForProperty'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFAutocompleteAPI::computeAllValuesForProperty'],
            'PhanUndeclaredClassMethod' => ['\\PFAutocompleteAPI::computeAllValuesForProperty']
        ],
        'includes/PF_AutoeditAPI.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFAutoeditAPI::doStore', '\\PFAutoeditAPI::setupEditPage'],
            'PhanRedundantCondition' => ['\\PFAutoeditAPI::doStore'],
            'PhanRedundantValueComparison' => ['\\PFAutoeditAPI::doStore'],
            'PhanTypeMismatchArgumentInternalReal' => ['\\PFAutoeditAPI::generateTargetName'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFAutoeditAPI::doStore'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFAutoeditAPI::finalizeResults'],
            'PhanTypeMismatchReturnNullable' => ['\\PFAutoeditAPI::getStatus'],
            'PhanUndeclaredConstantOfClass' => ['\\PFAutoeditAPI::generateTargetName'],
            'PhanUndeclaredMethod' => ['\\PFAutoeditAPI::getFormTitle']
        ],
        'includes/PF_CreatePageJob.php' => [
            'PhanRedundantCondition' => ['\\PFCreatePageJob::run']
        ],
        'includes/PF_FormCache.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFFormCache::getFormCache'],
            'PhanTypeMismatchArgument' => ['\\PFFormCache::getFormDefinition'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormCache::purgeCache']
        ],
        'includes/PF_FormEditAction.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormEditAction::displayFormChooser'],
            'PhanTypeMismatchReturn' => ['\\PFFormEditAction::displayForm', '\\PFFormEditAction::show'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormEditAction::displayFormChooser']
        ],
        'includes/PF_FormField.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFFormField::newFromFormFieldTag'],
            'PhanTypeSuspiciousStringExpression' => ['\\PFFormField::createMarkup'],
            'PhanUndeclaredStaticMethod' => ['\\PFFormField::setValuesWithMappingCargoField'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormField::additionalHTMLForInput', '\\PFFormField::newFromFormFieldTag']
        ],
        'includes/PF_FormLinker.php' => [
            'PhanParamTooFewInPHPDoc' => ['\\PFFormLinker::getDefaultForm']
        ],
        'includes/PF_FormPrinter.php' => [
            'PhanNonClassMethodCall' => ['\\PFFormPrinter::showDeletionLog'],
            'PhanPossiblyUndeclaredVariable' => ['\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchArgument' => ['\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFFormPrinter::__construct', '\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchProperty' => ['\\PFFormPrinter::formHTML'],
            'PhanTypeMismatchPropertyProbablyReal' => ['\\PFFormPrinter::__construct', '\\PFFormPrinter::formHTML'],
            'PhanUndeclaredMethod' => ['\\PFFormPrinter::formHTML'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormPrinter::formHTML'],
            'SecurityCheck-ReDoS' => ['\\PFFormPrinter::formHTML']
        ],
        'includes/PF_FormUtils.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFFormUtils::getStringForCurrentTime'],
            'PhanTypeInvalidLeftOperandOfNumericOp' => ['\\PFFormUtils::getStringForCurrentTime'],
            'PhanTypeMismatchArgumentInternal' => ['\\PFFormUtils::getStringForCurrentTime'],
            'PhanTypeMismatchReturn' => ['\\PFFormUtils::queryFormBottom'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormUtils::minorEditInputHTML', '\\PFFormUtils::watchInputHTML']
        ],
        'includes/PF_Hooks.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFHooks::showFormPreview'],
            'PhanUndeclaredClassMethod' => ['\\PFHooks::addToAdminLinks', '\\PFHooks::setGlobalJSVariables'],
            'PhanUndeclaredClassReference' => ['\\PFHooks::setGlobalJSVariables']
        ],
        'includes/PF_Template.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFTemplate::createText']
        ],
        'includes/PF_TemplateField.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFTemplateField::setTypeAndPossibleValues'],
            'PhanUndeclaredClassMethod' => ['\\PFTemplateField::setTypeAndPossibleValues']
        ],
        'includes/PF_Utils.php' => [
            'PhanTypeMismatchReturnProbablyReal' => ['\\PFUtils::makeLink'],
            'PhanUndeclaredClassMethod' => ['\\PFUtils::getSMWStore'],
            'PhanUndeclaredTypeReturnType' => ['\\PFUtils::getSMWStore'],
            'SecurityCheck-DoubleEscaped' => ['\\PFUtils::linkForSpecialPage']
        ],
        'includes/PF_ValuesUtils.php' => [
            'MediaWikiNoEmptyIfDefined' => ['\\PFValuesUtils::getValuesFromExternalURL'],
            'PhanPossiblyUndeclaredVariable' => ['\\PFValuesUtils::getAllPagesForNamespace'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFValuesUtils::getAllCategories', '\\PFValuesUtils::getAllValuesForProperty'],
            'PhanUndeclaredClassConstant' => ['\\PFValuesUtils::getAllPagesForConcept', '\\PFValuesUtils::getAllPagesForQuery'],
            'PhanUndeclaredClassInstanceof' => ['\\PFValuesUtils::getSMWPropertyValues'],
            'PhanUndeclaredClassMethod' => ['\\PFValuesUtils::getAllPagesForConcept', '\\PFValuesUtils::getAllPagesForQuery', '\\PFValuesUtils::getAllValuesForProperty', '\\PFValuesUtils::getSMWPropertyValues', '\\PFValuesUtils::getValuesFromExternalURL'],
            'PhanUndeclaredClassProperty' => ['\\PFValuesUtils::getAllValuesForProperty'],
            'PhanUndeclaredConstant' => ['\\PFValuesUtils::getAllPagesForConcept']
        ],
        'includes/forminputs/PF_DateInput.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFDateInput::parseDate'],
            'PhanTypeMismatchArgumentInternal' => ['\\PFDateInput::monthDropdownHTML'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFDateInput::monthDropdownHTML']
        ],
        'includes/forminputs/PF_DateTimeInput.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFDateTimeInput::getHTML']
        ],
        'includes/forminputs/PF_DateTimePicker.php' => [
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFDateTimePicker::__construct'],
            'SecurityCheck-DoubleEscaped' => ['\\PFDateTimePicker::getHtmlText']
        ],
        'includes/forminputs/PF_FormInput.php' => [
            'PhanTypeMismatchReturnProbablyReal' => ['\\PFFormInput::getDefaultParameters', '\\PFFormInput::getHandledPropertyTypes']
        ],
        'includes/forminputs/PF_LeafletInput.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFLeafletInput::getHTML']
        ],
        'includes/forminputs/PF_OpenLayersInput.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFOpenLayersInput::mapLookupHTML'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFOpenLayersInput::mapLookupHTML'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFOpenLayersInput::mapLookupHTML'],
            'PhanTypeMismatchReturn' => ['\\PFOpenLayersInput::coordinatePartToNumber']
        ],
        'includes/forminputs/PF_RadioButtonInput.php' => [
            'PhanTypeArraySuspiciousNullable' => ['\\PFRadioButtonInput::getHTML']
        ],
        'includes/forminputs/PF_RegExpInput.php' => [
            'PhanNonClassMethodCall' => ['\\PFRegExpInput::__construct']
        ],
        'includes/forminputs/PF_SFSelectAPIRequestProcessor.php' => [
            'PhanUndeclaredClassConstant' => ['\\PFSFSelectAPIRequestProcessor::defaultGetSmwResultFromFunctionParams'],
            'PhanUndeclaredClassMethod' => ['\\PFSFSelectAPIRequestProcessor::defaultGetSmwResultFromFunctionParams'],
            'PhanUndeclaredConstant' => ['\\PFSFSelectAPIRequestProcessor::defaultGetSmwResultFromFunctionParams']
        ],
        'includes/forminputs/PF_SFSelectField.php' => [
            'PhanUndeclaredClassConstant' => ['\\PFSFSelectField::setQuery'],
            'PhanUndeclaredClassMethod' => ['\\PFSFSelectField::setQuery'],
            'PhanUndeclaredConstant' => ['\\PFSFSelectField::setQuery']
        ],
        'includes/forminputs/PF_TextInput.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFTextInput::uploadableHTML'],
            'PhanUndeclaredMethod' => ['\\PFTextInput::getPreviewImage'],
            'SecurityCheck-DoubleEscaped' => ['\\PFTextInput::uploadableHTML']
        ],
        'includes/forminputs/PF_TimePickerInput.php' => [
            'PhanTypeMismatchReturn' => ['\\PFTimePickerInput::setupJsInitAttribs']
        ],
        'includes/forminputs/PF_Tree.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFTree::addSubCategories']
        ],
        'includes/forminputs/PF_TreeInput.php' => [
            'PhanRedundantCondition' => ['\\PFTreeInput::makeTitle'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFTreeInput::getHTML']
        ],
        'includes/parserfunctions/PF_ArrayMap.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFArrayMap::run']
        ],
        'includes/parserfunctions/PF_AutoEdit.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFAutoEdit::run'],
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFAutoEdit::convertQueryString'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFAutoEdit::run'],
            'SecurityCheck-DoubleEscaped' => ['\\PFAutoEdit::run']
        ],
        'includes/parserfunctions/PF_AutoEditRating.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFAutoEditRating::run'],
            'SecurityCheck-DoubleEscaped' => ['\\PFAutoEditRating::run']
        ],
        'includes/parserfunctions/PF_FormInputParserFunction.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFFormInputParserFunction::run'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFFormInputParserFunction::run'],
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFFormInputParserFunction::run'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormInputParserFunction::run'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormInputParserFunction::run']
        ],
        'includes/parserfunctions/PF_FormLink.php' => [
            'PhanPossiblyUndeclaredVariable' => ['\\PFFormLink::createFormLink'],
            'PhanTypeMismatchArgumentNullable' => ['\\PFFormLink::createFormLink'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormLink::createFormLink']
        ],
        'specials/PF_FormEdit.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFFormEdit::execute'],
            'PhanPossiblyUndeclaredVariable' => ['\\PFFormEdit::printForm'],
            'PhanUndeclaredClassMethod' => ['\\PFFormEdit::showCaptcha'],
            'PhanUndeclaredClassReference' => ['\\PFFormEdit::showCaptcha'],
            'PhanUndeclaredMethod' => ['\\PFFormEdit::printForm']
        ],
        'specials/PF_FormStart.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFFormStart::execute'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFFormStart::execute'],
            'SecurityCheck-DoubleEscaped' => ['\\PFFormStart::execute']
        ],
        'specials/PF_Forms.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFForms::getPageHeader'],
            'PhanTypeMismatchReturnProbablyReal' => ['\\PFForms::formatResult'],
            'SecurityCheck-DoubleEscaped' => ['\\PFForms::formatResult']
        ],
        'specials/PF_MultiPageEdit.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFMultiPageEdit::displaySpreadsheet', '\\PFMultiPageEdit::getPageHeader'],
            'SecurityCheck-DoubleEscaped' => ['\\PFMultiPageEdit::displaySpreadsheet']
        ],
        'specials/PF_RunQuery.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFRunQuery::printPage']
        ],
        'specials/PF_Templates.php' => [
            'PhanTypeMismatchArgumentProbablyReal' => ['\\PFTemplates::getPageHeader'],
            'SecurityCheck-DoubleEscaped' => ['\\PFTemplates::formatResult']
        ],
        'specials/PF_UploadForm.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFUploadForm::__construct'],
            'PhanTypeMismatchDimEmpty' => ['\\PFUploadForm::__construct'],
            'PhanTypeMismatchProperty' => ['\\PFUploadForm::__construct'],
            'PhanTypeMismatchPropertyProbablyReal' => ['\\PFUploadForm::__construct']
        ],
        'specials/PF_UploadSourceField.php' => [
            'PhanPluginDuplicateConditionalNullCoalescing' => ['\\PFUploadSourceField::getSize'],
            'PhanUndeclaredStaticMethod' => ['\\PFUploadSourceField::userCanExecute']
        ],
        'specials/PF_UploadWindow.php' => [
            'PhanTypeMismatchArgumentNullable' => ['\\PFUploadWindow::execute'],
            'PhanTypeMismatchArgumentNullableInternal' => ['\\PFUploadWindow::processUpload'],
            'PhanUndeclaredMethod' => ['\\PFUploadWindow::getUploadForm', '\\PFUploadWindow::processUpload', '\\PFUploadWindow::showViewDeletedLinks', '\\PFUploadWindow::watchCheck'],
            'SecurityCheck-DoubleEscaped' => ['\\PFUploadWindow::getExistsWarning'],
            'SecurityCheck-XSS' => ['\\PFUploadWindow::processUpload']
        ],
        'src/FormDefParser.php' => [
            'PhanRedundantCondition' => ['\\MediaWiki\\Extension\\PageForms\\FormDefParser::preparePreloadData'],
            'PhanTypeMismatchArgumentProbablyReal' => ['\\MediaWiki\\Extension\\PageForms\\FormDefParser::preparePreloadData'],
            'PhanUndeclaredMethod' => ['\\MediaWiki\\Extension\\PageForms\\FormDefParser::preparePreloadData']
        ],
        'src/FormFieldHtmlBuilder.php' => [
            'PhanTypeMismatchArgument' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML'],
            'PhanTypeMismatchArgumentInternal' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML'],
            'PhanTypeMismatchDimFetchNullable' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML'],
            'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\FormFieldHtmlBuilder::formFieldHTML']
        ],
        'src/SpreadsheetHtmlBuilder.php' => [
            'SecurityCheck-DoubleEscaped' => ['\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::spreadsheetHTML', '\\MediaWiki\\Extension\\PageForms\\SpreadsheetHtmlBuilder::tableHTML']
        ],
    ],
    // 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
    // (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
