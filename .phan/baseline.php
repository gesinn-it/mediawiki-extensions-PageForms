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
    // PhanUndeclaredClassMethod : 90+ occurrences
    // MediaWikiNoEmptyIfDefined : 30+ occurrences
    // PhanPossiblyUndeclaredVariable : 20+ occurrences
    // PhanTypeMismatchArgumentProbablyReal : 20+ occurrences
    // PhanTypeMismatchArgumentNullable : 15+ occurrences
    // PhanUndeclaredMethod : 15+ occurrences
    // PhanPluginDuplicateConditionalNullCoalescing : 10+ occurrences
    // PhanTypeMismatchArgument : 10+ occurrences
    // PhanTypeMismatchArgumentNullableInternal : 9 occurrences
    // PhanTypeMismatchArgumentInternal : 7 occurrences
    // PhanTypeMismatchProperty : 6 occurrences
    // PhanUndeclaredClassConstant : 6 occurrences
    // PhanUndeclaredConstant : 6 occurrences
    // PhanRedundantCondition : 5 occurrences
    // PhanTypeArraySuspiciousNullable : 5 occurrences
    // PhanTypeMismatchPropertyProbablyReal : 4 occurrences
    // PhanTypeMismatchReturn : 4 occurrences
    // PhanTypeMismatchReturnProbablyReal : 4 occurrences
    // PhanTypePossiblyInvalidDimOffset : 4 occurrences
    // PhanTypeMismatchDimFetchNullable : 2 occurrences
    // PhanUndeclaredClassInstanceof : 2 occurrences
    // PhanUndeclaredClassReference : 2 occurrences
    // PhanUndeclaredStaticMethod : 2 occurrences
    // PhanUndeclaredTypeReturnType : 2 occurrences
    // SecurityCheck-DoubleEscaped : 2 occurrences
    // PhanNonClassMethodCall : 1 occurrence
    // PhanTypeExpectedObjectOrClassName : 1 occurrence
    // PhanTypeInvalidLeftOperandOfNumericOp : 1 occurrence
    // PhanTypeMismatchArgumentInternalReal : 1 occurrence
    // PhanTypeMismatchDimEmpty : 1 occurrence
    // PhanTypeMismatchReturnNullable : 1 occurrence
    // PhanTypeSuspiciousStringExpression : 1 occurrence
    // PhanUndeclaredClassProperty : 1 occurrence
    // PhanUndeclaredFunction : 1 occurrence

    // Currently, file_suppressions and directory_suppressions are the only supported suppressions
    'file_suppressions' => [
        'includes/PF_AutocompleteAPI.php' => ['PhanTypeMismatchArgument', 'PhanTypeMismatchArgumentInternal', 'PhanTypeMismatchArgumentNullable', 'PhanUndeclaredClassMethod'],
        'includes/PF_AutoeditAPI.php' => ['PhanPluginDuplicateConditionalNullCoalescing', 'PhanRedundantCondition', 'PhanTypeMismatchArgumentInternalReal', 'PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchArgumentProbablyReal', 'PhanTypeMismatchReturnNullable', 'PhanTypePossiblyInvalidDimOffset', 'PhanUndeclaredMethod'],
        'includes/PF_CreatePageJob.php' => ['PhanRedundantCondition'],
        'includes/PF_Form.php' => ['MediaWikiNoEmptyIfDefined'],
        'includes/PF_FormEditAction.php' => ['PhanTypeMismatchArgumentProbablyReal', 'PhanTypeMismatchReturn', 'PhanUndeclaredClassMethod', 'PhanUndeclaredMethod'],
        'includes/PF_FormField.php' => ['MediaWikiNoEmptyIfDefined', 'PhanTypeMismatchArgumentNullable', 'PhanTypeSuspiciousStringExpression', 'PhanUndeclaredStaticMethod', 'SecurityCheck-DoubleEscaped'],
        'includes/PF_FormLinker.php' => ['PhanUndeclaredClassMethod'],
        'includes/PF_FormPrinter.php' => ['MediaWikiNoEmptyIfDefined', 'PhanNonClassMethodCall', 'PhanPossiblyUndeclaredVariable', 'PhanRedundantCondition', 'PhanTypeInvalidLeftOperandOfNumericOp', 'PhanTypeMismatchArgument', 'PhanTypeMismatchArgumentInternal', 'PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchArgumentNullableInternal', 'PhanTypeMismatchArgumentProbablyReal', 'PhanTypeMismatchDimFetchNullable', 'PhanTypeMismatchProperty', 'PhanTypeMismatchPropertyProbablyReal', 'PhanUndeclaredClassMethod', 'PhanUndeclaredMethod'],
        'includes/PF_FormUtils.php' => ['PhanPluginDuplicateConditionalNullCoalescing', 'PhanPossiblyUndeclaredVariable', 'PhanTypeMismatchArgument', 'PhanTypeMismatchArgumentNullable', 'PhanUndeclaredMethod'],
        'includes/PF_HelperFormAction.php' => ['PhanUndeclaredMethod'],
        'includes/PF_Hooks.php' => ['PhanTypeMismatchArgumentProbablyReal', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference'],
        'includes/PF_Template.php' => ['MediaWikiNoEmptyIfDefined', 'PhanPossiblyUndeclaredVariable'],
        'includes/PF_TemplateField.php' => ['MediaWikiNoEmptyIfDefined', 'PhanTypeMismatchArgumentNullable', 'PhanUndeclaredClassMethod'],
        'includes/PF_Utils.php' => ['PhanTypeMismatchReturnProbablyReal', 'PhanUndeclaredClassMethod', 'PhanUndeclaredFunction', 'PhanUndeclaredMethod', 'PhanUndeclaredTypeReturnType'],
        'includes/PF_ValuesUtils.php' => ['MediaWikiNoEmptyIfDefined', 'PhanPossiblyUndeclaredVariable', 'PhanTypeMismatchArgumentProbablyReal', 'PhanUndeclaredClassConstant', 'PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassProperty', 'PhanUndeclaredConstant'],
        'includes/forminputs/PF_ComboBoxInput.php' => ['PhanTypeArraySuspiciousNullable'],
        'includes/forminputs/PF_DateInput.php' => ['PhanPossiblyUndeclaredVariable', 'PhanTypeMismatchArgumentInternal', 'PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchArgumentNullableInternal'],
        'includes/forminputs/PF_DateTimeInput.php' => ['PhanPossiblyUndeclaredVariable'],
        'includes/forminputs/PF_DateTimePicker.php' => ['PhanTypeMismatchArgumentNullableInternal'],
        'includes/forminputs/PF_FormInput.php' => ['PhanTypeMismatchReturnProbablyReal', 'PhanUndeclaredClassMethod'],
        'includes/forminputs/PF_GoogleMapsInput.php' => ['PhanUndeclaredClassMethod'],
        'includes/forminputs/PF_LeafletInput.php' => ['PhanPossiblyUndeclaredVariable', 'PhanUndeclaredClassMethod'],
        'includes/forminputs/PF_OpenLayersInput.php' => ['PhanPossiblyUndeclaredVariable', 'PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchArgumentProbablyReal', 'PhanTypeMismatchReturn', 'PhanUndeclaredClassMethod'],
        'includes/forminputs/PF_RadioButtonInput.php' => ['PhanTypeArraySuspiciousNullable'],
        'includes/forminputs/PF_RegExpInput.php' => ['PhanTypeExpectedObjectOrClassName'],
        'includes/forminputs/PF_SFSelectAPIRequestProcessor.php' => ['PhanUndeclaredClassConstant', 'PhanUndeclaredClassMethod', 'PhanUndeclaredConstant'],
        'includes/forminputs/PF_SFSelectField.php' => ['PhanUndeclaredClassConstant', 'PhanUndeclaredClassMethod', 'PhanUndeclaredConstant'],
        'includes/forminputs/PF_TextAreaInput.php' => ['PhanUndeclaredClassMethod'],
        'includes/forminputs/PF_TextInput.php' => ['PhanTypeMismatchArgumentProbablyReal', 'PhanUndeclaredMethod'],
        'includes/forminputs/PF_TimePickerInput.php' => ['PhanTypeMismatchReturn'],
        'includes/forminputs/PF_TokensInput.php' => ['PhanTypeArraySuspiciousNullable'],
        'includes/forminputs/PF_Tree.php' => ['PhanTypeMismatchArgumentNullable', 'PhanUndeclaredClassMethod'],
        'includes/forminputs/PF_TreeInput.php' => ['PhanRedundantCondition', 'PhanTypeMismatchArgumentProbablyReal'],
        'includes/parserfunctions/PF_ArrayMap.php' => ['PhanPluginDuplicateConditionalNullCoalescing'],
        'includes/parserfunctions/PF_AutoEdit.php' => ['MediaWikiNoEmptyIfDefined', 'PhanPossiblyUndeclaredVariable', 'PhanTypeMismatchArgumentNullableInternal', 'PhanTypeMismatchArgumentProbablyReal'],
        'includes/parserfunctions/PF_AutoEditRating.php' => ['MediaWikiNoEmptyIfDefined', 'PhanTypeMismatchArgumentProbablyReal'],
        'includes/parserfunctions/PF_FormInputParserFunction.php' => ['MediaWikiNoEmptyIfDefined', 'PhanPossiblyUndeclaredVariable', 'PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchArgumentNullableInternal', 'PhanTypeMismatchArgumentProbablyReal'],
        'includes/parserfunctions/PF_FormLink.php' => ['MediaWikiNoEmptyIfDefined', 'PhanPossiblyUndeclaredVariable', 'PhanTypeMismatchArgumentNullable', 'SecurityCheck-DoubleEscaped'],
        'specials/PF_FormEdit.php' => ['MediaWikiNoEmptyIfDefined', 'PhanPluginDuplicateConditionalNullCoalescing', 'PhanPossiblyUndeclaredVariable', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference', 'PhanUndeclaredMethod'],
        'specials/PF_FormStart.php' => ['PhanPluginDuplicateConditionalNullCoalescing', 'PhanTypeMismatchArgumentProbablyReal'],
        'specials/PF_Forms.php' => ['PhanTypeMismatchArgumentProbablyReal', 'PhanTypeMismatchReturnProbablyReal'],
        'specials/PF_MultiPageEdit.php' => ['MediaWikiNoEmptyIfDefined', 'PhanTypeMismatchArgumentProbablyReal', 'PhanUndeclaredClassMethod'],
        'specials/PF_RunQuery.php' => ['PhanTypeMismatchArgumentProbablyReal'],
        'specials/PF_Templates.php' => ['PhanTypeMismatchArgumentProbablyReal'],
        'specials/PF_UploadForm.php' => ['PhanPluginDuplicateConditionalNullCoalescing', 'PhanTypeMismatchDimEmpty', 'PhanTypeMismatchProperty', 'PhanTypeMismatchPropertyProbablyReal'],
        'specials/PF_UploadSourceField.php' => ['PhanPluginDuplicateConditionalNullCoalescing', 'PhanUndeclaredStaticMethod'],
        'specials/PF_UploadWindow.php' => ['PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchArgumentNullableInternal', 'PhanUndeclaredMethod'],
    ],
    // 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
    // (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
