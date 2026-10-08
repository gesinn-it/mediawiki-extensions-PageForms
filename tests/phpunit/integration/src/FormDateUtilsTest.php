<?php

use MediaWiki\Extension\PageForms\FormDateUtils;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FormDateUtils.
 *
 * @group PF
 */
class FormDateUtilsTest extends TestCase {

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
	 * @covers \MediaWiki\Extension\PageForms\FormDateUtils::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTimeDateOnly() {
		global $wgAmericanDates, $wgLocaltimezone;
		$originalAmericanDates = $wgAmericanDates;
		$originalLocaltimezone = $wgLocaltimezone;
		$wgAmericanDates = true;
		$wgLocaltimezone = 'UTC';

		try {
			$result = FormDateUtils::getStringForCurrentTime( false, false );
			$this->assertIsString( $result );
			$this->assertNotSame( '', $result );
		} finally {
			$wgAmericanDates = $originalAmericanDates;
			$wgLocaltimezone = $originalLocaltimezone;
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormDateUtils::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTimeWithTimeAnd24HourFormat() {
		global $wgAmericanDates, $wgLocaltimezone, $wgPageForms24HourTime;
		$originalAmericanDates = $wgAmericanDates;
		$originalLocaltimezone = $wgLocaltimezone;
		$original24Hour = $wgPageForms24HourTime;
		$wgAmericanDates = false;
		$wgLocaltimezone = null;
		$wgPageForms24HourTime = true;

		try {
			$result = FormDateUtils::getStringForCurrentTime( true, true );
			$pattern = '/^\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2} .+$/';
			// PHPUnit >= 9 (MW 1.43): assertMatchesRegularExpression; PHPUnit 8 (MW 1.39): assertRegExp.
			if ( method_exists( $this, 'assertMatchesRegularExpression' ) ) {
				$this->assertMatchesRegularExpression( $pattern, $result );
			} else {
				// @codeCoverageIgnoreStart
				$this->assertRegExp( $pattern, $result );
				// @codeCoverageIgnoreEnd
			}
		} finally {
			$wgAmericanDates = $originalAmericanDates;
			$wgLocaltimezone = $originalLocaltimezone;
			$wgPageForms24HourTime = $original24Hour;
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormDateUtils::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTimeWithTimeAnd12HourFormat() {
		global $wgAmericanDates, $wgLocaltimezone, $wgPageForms24HourTime;
		$originalAmericanDates = $wgAmericanDates;
		$originalLocaltimezone = $wgLocaltimezone;
		$original24Hour = $wgPageForms24HourTime;
		$wgAmericanDates = false;
		$wgLocaltimezone = null;
		$wgPageForms24HourTime = false;

		try {
			$result = FormDateUtils::getStringForCurrentTime( true, false );
			$pattern = '/^\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2} (AM|PM)$/';
			// PHPUnit >= 9 (MW 1.43): assertMatchesRegularExpression; PHPUnit 8 (MW 1.39): assertRegExp.
			if ( method_exists( $this, 'assertMatchesRegularExpression' ) ) {
				$this->assertMatchesRegularExpression( $pattern, $result );
			} else {
				// @codeCoverageIgnoreStart
				$this->assertRegExp( $pattern, $result );
				// @codeCoverageIgnoreEnd
			}
		} finally {
			$wgAmericanDates = $originalAmericanDates;
			$wgLocaltimezone = $originalLocaltimezone;
			$wgPageForms24HourTime = $original24Hour;
		}
	}
}
