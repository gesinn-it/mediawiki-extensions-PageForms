<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Revision\RenderedRevision;
use WikiPage;

/**
 * Forwards to the classes that took over the helpers of this class.
 *
 * @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons, FormDateUtils, FormInputValues, FormMarkup,
 *  SpreadsheetGlobals and FormCache instead.
 * @ingroup PF
 */
class FormUtils {

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormMarkup::unhandledFieldsHTML() instead. */
	public static function unhandledFieldsHTML( $template_in_form ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormMarkup::unhandledFieldsHTML( $template_in_form );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::summaryInputHTML() instead. */
	public static function summaryInputHTML( $is_disabled, $label = null, $attr = [], $value = '' ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::summaryInputHTML( $is_disabled, $label, $attr, $value );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::minorEditInputHTML() instead. */
	public static function minorEditInputHTML(
		$form_submitted, $is_disabled, $is_checked, $label = null, $attrs = []
	) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::minorEditInputHTML( $form_submitted, $is_disabled, $is_checked, $label, $attrs );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::watchInputHTML() instead. */
	public static function watchInputHTML(
		$form_submitted, $is_disabled, $is_checked = false, $label = null, $attrs = []
	) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::watchInputHTML( $form_submitted, $is_disabled, $is_checked, $label, $attrs );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::saveButtonHTML() instead. */
	public static function saveButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::saveButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::saveAndContinueButtonHTML() instead. */
	public static function saveAndContinueButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::saveAndContinueButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::showPreviewButtonHTML() instead. */
	public static function showPreviewButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::showPreviewButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::showChangesButtonHTML() instead. */
	public static function showChangesButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::showChangesButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::cancelLinkHTML() instead. */
	public static function cancelLinkHTML( $is_disabled, $label = null, $attr = [] ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::cancelLinkHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::runQueryButtonHTML() instead. */
	public static function runQueryButtonHTML( $is_disabled = false, $label = null, $attr = [] ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::runQueryButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::formBottom() instead. */
	public static function formBottom( $form_submitted, $is_disabled ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::formBottom( $form_submitted, $is_disabled );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormCache::getPreloadedText() instead. */
	public static function getPreloadedText( $preload ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormCache::getPreloadedText( $preload );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormButtons::queryFormBottom() instead. */
	public static function queryFormBottom() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormButtons::queryFormBottom();
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormDateUtils::getMonthNames() instead. */
	public static function getMonthNames() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormDateUtils::getMonthNames();
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use SpreadsheetGlobals::setGlobalVarsForSpreadsheet() instead. */
	public static function setGlobalVarsForSpreadsheet() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return SpreadsheetGlobals::setGlobalVarsForSpreadsheet();
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use SpreadsheetGlobals::resetGlobalVarsForSpreadsheetGuard() instead. */
	public static function resetGlobalVarsForSpreadsheetGuard() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return SpreadsheetGlobals::resetGlobalVarsForSpreadsheetGuard();
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormCache::purgeCache() instead. */
	public static function purgeCache( WikiPage $wikipage ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormCache::purgeCache( $wikipage );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormCache::purgeCacheOnSave() instead. */
	public static function purgeCacheOnSave( RenderedRevision $renderedRevision ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormCache::purgeCacheOnSave( $renderedRevision );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormCache::getFormCache() instead. */
	public static function getFormCache() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormCache::getFormCache();
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormCache::getCacheKey() instead. */
	public static function getCacheKey( $formId, $parser = null ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormCache::getCacheKey( $formId, $parser );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormMarkup::headerHTML() instead. */
	public static function headerHTML( $header_name, $header_level = 2 ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormMarkup::headerHTML( $header_name, $header_level );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormInputValues::getChangedIndex() instead. */
	public static function getChangedIndex( $i, $new_item_loc, $deleted_item_loc ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormInputValues::getChangedIndex( $i, $new_item_loc, $deleted_item_loc );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormMarkup::setShowOnSelect() instead. */
	public static function setShowOnSelect( $showOnSelectVals, $inputID, $isCheckbox = false ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormMarkup::setShowOnSelect( $showOnSelectVals, $inputID, $isCheckbox );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormInputValues::getStringFromPassedInArray() instead. */
	public static function getStringFromPassedInArray( $value, $delimiter ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormInputValues::getStringFromPassedInArray( $value, $delimiter );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormMarkup::displayLoadingImage() instead. */
	public static function displayLoadingImage() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormMarkup::displayLoadingImage();
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormDateUtils::getStringForCurrentTime() instead. */
	public static function getStringForCurrentTime( $includeTime, $includeTimezone ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormDateUtils::getStringForCurrentTime( $includeTime, $includeTimezone );
	}

	/** @deprecated since PageForms 2.4.0, removed in 3.0.0 — use FormInputValues::generateUUID() instead. */
	public static function generateUUID() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormInputValues::generateUUID();
	}

}
