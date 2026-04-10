<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFComboBoxInput
 * @group Database
 */
class PFComboBoxInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
	}

	/**
	 * Regression test: when `values from property` is used for a plain string-type SMW property,
	 * the possible_values array is numerically indexed ([0 => 'Foo', 1 => 'Bar', ...]).
	 * The DisplayTitle canonical-value override must NOT fire in this case — array_search()
	 * would return the numeric index (e.g. 2) instead of the actual value string, causing the
	 * option's value attribute to be set to "2" and subsequently saving "2" instead of the
	 * original text value on re-edit.
	 *
	 * The fix: skip the override unless possible_values has string keys
	 * (i.e. is a [canonicalTitle => displayTitle] map).
	 */
	public function testGetHtmlDoesNotReplaceValueWithIndexForNumericPossibleValues(): void {
		$curValue = 'Project Planning';
		$possibleValues = [ 'Alpha Meeting', 'Beta Discussion', 'Project Planning', 'Team Sync' ];

		$otherArgs = [
			'possible_values' => $possibleValues,
			'values from property' => 'Meeting Subject',
		];

		$html = PFComboBoxInput::getHTML( $curValue, 'Meeting[Subject]', false, false, $otherArgs );

		// The selected option must carry the actual text, not the numeric index "2".
		$this->assertStringNotContainsString(
			'value="2"',
			$html,
			'Option value must not be the numeric array index of the current value'
		);
		$this->assertStringContainsString(
			'Project Planning',
			$html,
			'Current value text must appear in the rendered HTML'
		);
	}

	/**
	 * Positive case: when possible_values is a [canonicalTitle => displayTitle] map
	 * (string keys), the selected option's value attribute must be set to the canonical
	 * key so that the JS widget can bootstrap its DisplayTitle↔canonical map from the DOM.
	 */
	public function testGetHtmlSetsCanonicalValueForDisplayTitleMap(): void {
		$curValue = 'My Display Title';
		$possibleValues = [
			'CanonicalPageA' => 'Another Page',
			'CanonicalPageB' => 'My Display Title',
			'CanonicalPageC' => 'Third Page',
		];

		$otherArgs = [
			'possible_values' => $possibleValues,
			'values from property' => 'SomePage',
		];

		$html = PFComboBoxInput::getHTML( $curValue, 'Field[Name]', false, false, $otherArgs );

		$this->assertStringContainsString(
			'value="CanonicalPageB"',
			$html,
			'Option value must be the canonical key from the display-title map'
		);
	}
}
