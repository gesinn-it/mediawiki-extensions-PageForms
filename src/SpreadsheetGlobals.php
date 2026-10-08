<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * The global variables that hold the wiki's yes, no and month names for the spreadsheet display.
 *
 * @ingroup PF
 */
class SpreadsheetGlobals {

	/** @var bool Guards against redundant re-computation within the same request. */
	private static $globalVarsForSpreadsheetSet = false;

	public static function setGlobalVarsForSpreadsheet() {
		if ( self::$globalVarsForSpreadsheetSet ) {
			return;
		}
		self::$globalVarsForSpreadsheetSet = true;

		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;

		// JS variables that hold boolean and date values in the wiki's
		// (as opposed to the user's) language.
		$wgPageFormsContLangYes = wfMessage( 'htmlform-yes' )->inContentLanguage()->text();
		$wgPageFormsContLangNo = wfMessage( 'htmlform-no' )->inContentLanguage()->text();
		$monthMessages = [
			"january", "february", "march", "april", "may_long", "june",
			"july", "august", "september", "october", "november", "december"
		];
		$wgPageFormsContLangMonths = [ '' ];
		foreach ( $monthMessages as $monthMsg ) {
			$wgPageFormsContLangMonths[] = wfMessage( $monthMsg )->inContentLanguage()->text();
		}
	}

	/**
	 * Test-only helper to reset the guard in setGlobalVarsForSpreadsheet().
	 *
	 * @internal
	 */
	public static function resetGlobalVarsForSpreadsheetGuard() {
		self::$globalVarsForSpreadsheetSet = false;
	}

}
