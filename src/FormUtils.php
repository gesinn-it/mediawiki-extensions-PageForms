<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Revision\RenderedRevision;
use WikiPage;

/**
 * Forwards to the classes that took over the helpers of this class.
 *
 * @deprecated since PageForms 6.x — use FormButtons, FormDateUtils, FormInputValues, FormMarkup,
 *  SpreadsheetGlobals and FormCache instead.
 * @ingroup PF
 */
class FormUtils {

	/** @deprecated since PageForms 6.x — use FormMarkup::unhandledFieldsHTML() instead. */
	public static function unhandledFieldsHTML( $template_in_form ) {
		return FormMarkup::unhandledFieldsHTML( $template_in_form );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::summaryInputHTML() instead. */
	public static function summaryInputHTML( $is_disabled, $label = null, $attr = [], $value = '' ) {
		return FormButtons::summaryInputHTML( $is_disabled, $label, $attr, $value );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::minorEditInputHTML() instead. */
	public static function minorEditInputHTML(
		$form_submitted, $is_disabled, $is_checked, $label = null, $attrs = []
	) {
		return FormButtons::minorEditInputHTML( $form_submitted, $is_disabled, $is_checked, $label, $attrs );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::watchInputHTML() instead. */
	public static function watchInputHTML(
		$form_submitted, $is_disabled, $is_checked = false, $label = null, $attrs = []
	) {
		return FormButtons::watchInputHTML( $form_submitted, $is_disabled, $is_checked, $label, $attrs );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::saveButtonHTML() instead. */
	public static function saveButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		return FormButtons::saveButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::saveAndContinueButtonHTML() instead. */
	public static function saveAndContinueButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		return FormButtons::saveAndContinueButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::showPreviewButtonHTML() instead. */
	public static function showPreviewButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		return FormButtons::showPreviewButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::showChangesButtonHTML() instead. */
	public static function showChangesButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		return FormButtons::showChangesButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::cancelLinkHTML() instead. */
	public static function cancelLinkHTML( $is_disabled, $label = null, $attr = [] ) {
		return FormButtons::cancelLinkHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::runQueryButtonHTML() instead. */
	public static function runQueryButtonHTML( $is_disabled = false, $label = null, $attr = [] ) {
		return FormButtons::runQueryButtonHTML( $is_disabled, $label, $attr );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::formBottom() instead. */
	public static function formBottom( $form_submitted, $is_disabled ) {
		return FormButtons::formBottom( $form_submitted, $is_disabled );
	}

	/** @deprecated since PageForms 6.x — use FormCache::getPreloadedText() instead. */
	public static function getPreloadedText( $preload ) {
		return FormCache::getPreloadedText( $preload );
	}

	/** @deprecated since PageForms 6.x — use FormButtons::queryFormBottom() instead. */
	public static function queryFormBottom() {
		return FormButtons::queryFormBottom();
	}

	/** @deprecated since PageForms 6.x — use FormDateUtils::getMonthNames() instead. */
	public static function getMonthNames() {
		return FormDateUtils::getMonthNames();
	}

	/** @deprecated since PageForms 6.x — use SpreadsheetGlobals::setGlobalVarsForSpreadsheet() instead. */
	public static function setGlobalVarsForSpreadsheet() {
		return SpreadsheetGlobals::setGlobalVarsForSpreadsheet();
	}

	/** @deprecated since PageForms 6.x — use SpreadsheetGlobals::resetGlobalVarsForSpreadsheetGuard() instead. */
	public static function resetGlobalVarsForSpreadsheetGuard() {
		return SpreadsheetGlobals::resetGlobalVarsForSpreadsheetGuard();
	}

	/** @deprecated since PageForms 6.x — use FormCache::purgeCache() instead. */
	public static function purgeCache( WikiPage $wikipage ) {
		return FormCache::purgeCache( $wikipage );
	}

	/** @deprecated since PageForms 6.x — use FormCache::purgeCacheOnSave() instead. */
	public static function purgeCacheOnSave( RenderedRevision $renderedRevision ) {
		return FormCache::purgeCacheOnSave( $renderedRevision );
	}

	/** @deprecated since PageForms 6.x — use FormCache::getFormCache() instead. */
	public static function getFormCache() {
		return FormCache::getFormCache();
	}

	/** @deprecated since PageForms 6.x — use FormCache::getCacheKey() instead. */
	public static function getCacheKey( $formId, $parser = null ) {
		return FormCache::getCacheKey( $formId, $parser );
	}

	/** @deprecated since PageForms 6.x — use FormMarkup::headerHTML() instead. */
	public static function headerHTML( $header_name, $header_level = 2 ) {
		return FormMarkup::headerHTML( $header_name, $header_level );
	}

	/** @deprecated since PageForms 6.x — use FormInputValues::getChangedIndex() instead. */
	public static function getChangedIndex( $i, $new_item_loc, $deleted_item_loc ) {
		return FormInputValues::getChangedIndex( $i, $new_item_loc, $deleted_item_loc );
	}

	/** @deprecated since PageForms 6.x — use FormMarkup::setShowOnSelect() instead. */
	public static function setShowOnSelect( $showOnSelectVals, $inputID, $isCheckbox = false ) {
		return FormMarkup::setShowOnSelect( $showOnSelectVals, $inputID, $isCheckbox );
	}

	/** @deprecated since PageForms 6.x — use FormInputValues::getStringFromPassedInArray() instead. */
	public static function getStringFromPassedInArray( $value, $delimiter ) {
		return FormInputValues::getStringFromPassedInArray( $value, $delimiter );
	}

	/** @deprecated since PageForms 6.x — use FormMarkup::displayLoadingImage() instead. */
	public static function displayLoadingImage() {
		return FormMarkup::displayLoadingImage();
	}

	/** @deprecated since PageForms 6.x — use FormDateUtils::getStringForCurrentTime() instead. */
	public static function getStringForCurrentTime( $includeTime, $includeTimezone ) {
		return FormDateUtils::getStringForCurrentTime( $includeTime, $includeTimezone );
	}

	/** @deprecated since PageForms 6.x — use FormInputValues::generateUUID() instead. */
	public static function generateUUID() {
		return FormInputValues::generateUUID();
	}

}
