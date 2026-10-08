<?php

use MediaWiki\Extension\PageForms\FormInputValues;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FormInputValues.
 *
 * @group PF
 */
class FormInputValuesTest extends TestCase {

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
	 * Test for getChangedIndex method when the item is deleted.
	 *
	 * Verifies that the index is calculated correctly when the item is deleted.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormInputValues::getChangedIndex
	 */
	public function testGetChangedIndexWithDeletedItemLoc() {
		$result = FormInputValues::getChangedIndex( 5, null, 3 );
		$this->assertSame( 6, $result );

		$result = FormInputValues::getChangedIndex( 2, null, 3 );
		$this->assertSame( 2, $result );
	}

	/**
	 * Test for getChangedIndex method when a new item is added.
	 *
	 * Verifies that the index is calculated correctly when a new item is added.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormInputValues::getChangedIndex
	 */
	public function testGetChangedIndexWithNewItemLoc() {
		$result = FormInputValues::getChangedIndex( 5, 3, null );
		$this->assertSame( 4, $result );

		$result = FormInputValues::getChangedIndex( 3, 3, null );
		$this->assertSame( -1, $result );

		$result = FormInputValues::getChangedIndex( 2, 3, null );
		$this->assertSame( 2, $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormInputValues::getStringFromPassedInArray
	 */
	public function testGetStringFromPassedInArrayWithFullDateTimeComponents() {
		global $wgAmericanDates;
		$originalAmericanDates = $wgAmericanDates;
		$wgAmericanDates = false;

		try {
			$result = FormInputValues::getStringFromPassedInArray( [
				'month' => '5',
				'day' => '17',
				'year' => '2024',
				'hour' => '13',
				'minute' => '45',
				'second' => '30',
				'ampm24h' => 'PM',
				'timezone' => 'UTC',
			], ',' );

			$this->assertStringContainsString( '2024/5/17', $result );
			$this->assertStringContainsString( '13:45', $result );
			$this->assertStringContainsString( ':30', $result );
			$this->assertStringContainsString( 'PM', $result );
			$this->assertStringContainsString( 'UTC', $result );
		} finally {
			$wgAmericanDates = $originalAmericanDates;
		}
	}
}
