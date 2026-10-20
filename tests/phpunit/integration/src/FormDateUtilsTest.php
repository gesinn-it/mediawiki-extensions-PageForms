<?php

use MediaWiki\Extension\PageForms\FormDateUtils;

/**
 * Tests for FormDateUtils.
 *
 * @group PF
 */
class FormDateUtilsTest extends MediaWikiIntegrationTestCase {

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
	public function testGetStringForCurrentTimeDateOnlyIsIso() {
		$this->setMwGlobals( [ 'wgAmericanDates' => true, 'wgLocaltimezone' => 'UTC' ] );

		$result = FormDateUtils::getStringForCurrentTime( false, false );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormDateUtils::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTimeWithTimeIsIsoIn24HourFormat() {
		$this->setMwGlobals( [ 'wgLocaltimezone' => null, 'wgPageForms24HourTime' => false ] );

		$result = FormDateUtils::getStringForCurrentTime( true, false );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormDateUtils::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTimeWithTimezone() {
		$this->setMwGlobals( 'wgLocaltimezone', 'UTC' );

		$result = FormDateUtils::getStringForCurrentTime( true, true );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC$/', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormDateUtils::now
	 */
	public function testNowUsesTheTimezoneOfTheWiki() {
		$this->setMwGlobals( 'wgLocaltimezone', 'Pacific/Kiritimati' );

		$now = FormDateUtils::now( true )->toIso();
		$expected = ( new DateTimeImmutable( 'now', new DateTimeZone( 'Pacific/Kiritimati' ) ) )->format( 'Y-m-d' );

		$this->assertStringStartsWith( $expected, $now );
	}
}
