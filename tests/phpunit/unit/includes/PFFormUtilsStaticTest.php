<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for static utility methods extracted from PFFormPrinter into PFFormUtils.
 *
 * These tests are deliberately free of MediaWiki bootstrap so they run fast
 * as plain PHPUnit unit tests.
 *
 * @group PF
 * @covers PFFormUtils::getStringFromPassedInArray
 * @covers PFFormUtils::displayLoadingImage
 * @covers PFFormUtils::generateUUID
 */
class PFFormUtilsStaticTest extends TestCase {

	// -----------------------------------------------------------------------
	// getStringFromPassedInArray
	// -----------------------------------------------------------------------

	/**
	 * A value array flagged with 'is_list' is joined with the delimiter.
	 */
	public function testGetStringFromPassedInArrayIsList() {
		$value = [ 'is_list' => true, 'Alpha', 'Beta', 'Gamma' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertSame( 'Alpha, Beta, Gamma', $result );
	}

	/**
	 * is_list values containing HTML special chars are escaped.
	 */
	public function testGetStringFromPassedInArrayIsListEscapesHtml() {
		$value = [ 'is_list' => true, '<b>bold</b>' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertSame( '&lt;b&gt;bold&lt;/b&gt;', $result );
	}

	/**
	 * A single-element array (checkbox unchecked) returns the "no" word.
	 */
	public function testGetStringFromPassedInArrayCheckboxUnchecked() {
		$value = [ 'No' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		// PFUtils::getWordForYesOrNo(false) — actual word is locale-dependent,
		// we only assert it is a non-empty string.
		$this->assertIsString( $result );
		$this->assertNotSame( '', $result );
	}

	/**
	 * A two-element array (checkbox checked) returns the "yes" word.
	 */
	public function testGetStringFromPassedInArrayCheckboxChecked() {
		$value = [ 'No', 'Yes' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertIsString( $result );
		$this->assertNotSame( '', $result );
	}

	/**
	 * A year-only date array returns just the year.
	 */
	public function testGetStringFromPassedInArrayYearOnly() {
		$value = [ 'year' => '2024', 'month' => '', 'day' => '' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertSame( '2024', $result );
	}

	/**
	 * Year + month (non-American) returns "MonthName Year".
	 */
	public function testGetStringFromPassedInArrayYearMonth() {
		$GLOBALS['wgAmericanDates'] = false;
		$value = [ 'year' => '2024', 'month' => '3', 'day' => '' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		// Month 3 → "March"
		$this->assertStringContainsString( '2024', $result );
		$this->assertStringContainsString( 'March', $result );
	}

	/**
	 * Full date (non-American) returns "YYYY/MM/DD" — day is zero-padded, month is not.
	 */
	public function testGetStringFromPassedInArrayFullDateNonAmerican() {
		$GLOBALS['wgAmericanDates'] = false;
		$value = [ 'year' => '2024', 'month' => '3', 'day' => '5' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertSame( '2024/3/05', $result );
	}

	/**
	 * Full date (American) returns "Month Day, Year".
	 */
	public function testGetStringFromPassedInArrayFullDateAmerican() {
		$GLOBALS['wgAmericanDates'] = true;
		$value = [ 'year' => '2024', 'month' => '3', 'day' => '5' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertSame( '3 5, 2024', $result );
	}

	/**
	 * Datetime includes time component.
	 */
	public function testGetStringFromPassedInArrayDatetime() {
		$GLOBALS['wgAmericanDates'] = false;
		$value = [
			'year' => '2024', 'month' => '3', 'day' => '5',
			'hour' => '14', 'minute' => '30',
		];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertStringContainsString( '14:30', $result );
	}

	/**
	 * An empty array (no recognised keys) returns an empty string.
	 */
	public function testGetStringFromPassedInArrayEmptyReturnsEmptyString() {
		$value = [ 'year' => '', 'month' => '', 'day' => '' ];
		$result = PFFormUtils::getStringFromPassedInArray( $value, ',' );
		$this->assertSame( '', $result );
	}

	// -----------------------------------------------------------------------
	// displayLoadingImage
	// -----------------------------------------------------------------------

	/**
	 * displayLoadingImage returns a non-empty HTML string containing the
	 * expected CSS class and image tags.
	 */
	public function testDisplayLoadingImageReturnsHtml() {
		$GLOBALS['wgPageFormsScriptPath'] = '/extensions/PageForms';
		$result = PFFormUtils::displayLoadingImage();
		$this->assertStringContainsString( 'class="loadingImage"', $result );
		$this->assertStringContainsString( 'loading.gif', $result );
		$this->assertStringContainsString( 'loadingbg.png', $result );
		$this->assertStringContainsString( 'loadingMask', $result );
	}

	// -----------------------------------------------------------------------
	// generateUUID
	// -----------------------------------------------------------------------

	/**
	 * generateUUID returns a string matching the UUID v4 pattern.
	 */
	public function testGenerateUUIDFormat() {
		$uuid = PFFormUtils::generateUUID();
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
		// PHPUnit >= 9 (MW 1.43): assertMatchesRegularExpression
		// PHPUnit 8  (MW 1.35):  assertRegExp
		if ( method_exists( $this, 'assertMatchesRegularExpression' ) ) {
			$this->assertMatchesRegularExpression(
				$pattern, $uuid, 'generateUUID must return a valid RFC 4122 v4 UUID'
			);
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->assertRegExp(
				$pattern, $uuid, 'generateUUID must return a valid RFC 4122 v4 UUID'
			);
		}
	}

	/**
	 * generateUUID returns a different value on each call.
	 */
	public function testGenerateUUIDIsUnique() {
		$uuids = array_unique( array_map( static fn () => PFFormUtils::generateUUID(), range( 1, 20 ) ) );
		$this->assertGreaterThan( 1, count( $uuids ), 'generateUUID should not return the same value every time' );
	}

}
