<?php

use MediaWiki\Extension\PageForms\SpreadsheetGlobals;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SpreadsheetGlobals.
 *
 * @group PF
 */
class SpreadsheetGlobalsTest extends TestCase {

	/**
	 * Setup method for each test.
	 *
	 * Initializes the environment for each test, including setting the OOUI theme.
	 */
	protected function setUp(): void {
		parent::setUp();
		OOUI\Theme::setSingleton( new OOUI\WikimediaUITheme() );
	}

	/**
	 * Test for setGlobalVarsForSpreadsheet method.
	 *
	 * Verifies that a second call within the same request is a no-op: it must not
	 * recompute (and thus overwrite) the globals once they have already been set,
	 * so that CalendarHtmlBuilder and SpreadsheetHtmlBuilder calling it redundantly
	 * only pays the wfMessage() lookup cost once per request.
	 *
	 * @covers \MediaWiki\Extension\PageForms\SpreadsheetGlobals::setGlobalVarsForSpreadsheet
	 * @covers \MediaWiki\Extension\PageForms\SpreadsheetGlobals::resetGlobalVarsForSpreadsheetGuard
	 */
	public function testSetGlobalVarsForSpreadsheetSkipsRecomputationOnSecondCall() {
		global $wgPageFormsContLangYes;

		SpreadsheetGlobals::resetGlobalVarsForSpreadsheetGuard();

		SpreadsheetGlobals::setGlobalVarsForSpreadsheet();
		$firstValue = $wgPageFormsContLangYes;

		// Simulate the global having been externally changed after the first call;
		// a guarded second call must leave it untouched.
		$wgPageFormsContLangYes = 'sentinel-value-set-between-calls';

		SpreadsheetGlobals::setGlobalVarsForSpreadsheet();

		$this->assertSame(
			'sentinel-value-set-between-calls',
			$wgPageFormsContLangYes,
			'Second call must not recompute the global once the guard has been tripped'
		);
		$this->assertNotSame( '', $firstValue, 'First call must have computed a real value' );

		SpreadsheetGlobals::resetGlobalVarsForSpreadsheetGuard();
	}
}
