<?php

use MediaWiki\Extension\PageForms\FormUtils;
use MediaWiki\Extension\PageForms\TemplateInForm;
use MediaWiki\MediaWikiServices;
use PHPUnit\Framework\TestCase;

/**
 * Test class for FormUtils functionality.
 *
 * This class contains unit tests for various utility methods used in the PageForms
 * extension. It tests methods that generate HTML for form elements, manage preload
 * text, handle headers, and calculate changed indices.
 *
 * @group PF
 */
class FormUtilsTest extends TestCase {

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
	 * Test for unhandledFieldsHTML method.
	 *
	 * This test verifies that unhandled form fields are correctly processed into
	 * HTML input elements with the expected attributes.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTML() {
		$mockTemplate = $this->createMock( TemplateInForm::class );

		// Mock methods for TemplateInForm
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'ExampleTemplate' );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [
			'field1' => 'value1',
			'field 2' => 'value2',
			3 => 'numeric_key_value',
			null => 'null_key_value'
		] );

		$output = FormUtils::unhandledFieldsHTML( $mockTemplate );

		// Load the HTML output and find input elements
		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $output );
		$inputs = $doc->getElementsByTagName( 'input' );

		// Expected values for input name and value
		$expectedValues = [
			'_unhandled_ExampleTemplate_field1' => 'value1',
			'_unhandled_ExampleTemplate_field_2' => 'value2'
		];

		// Verify input elements
		foreach ( $inputs as $input ) {
			$name = $input->getAttribute( 'name' );
			$value = $input->getAttribute( 'value' );
			if ( isset( $expectedValues[ $name ] ) ) {
				$this->assertSame( $expectedValues[ $name ], $value );
				unset( $expectedValues[ $name ] );
			}
		}
	}

	/**
	 * Test for unhandledFieldsHTML method when the template is null.
	 *
	 * This test ensures that when the template is null, the method returns an empty string.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLWithNullTemplate() {
		$output = FormUtils::unhandledFieldsHTML( null );
		$this->assertSame( '', $output );
	}

	/**
	 * Test for minorEditInputHTML method.
	 *
	 * Verifies that the minor edit input HTML is generated correctly with the given parameters.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTML() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::minorEditInputHTML( false, false, false, "Test" );

		// Check if the checkbox input is present with expected attributes
		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'id=\'wpMinoredit\'', $output );
		$this->assertStringContainsString( 'Test', $output );
	}

	/**
	 * Test for watchInputHTML method.
	 *
	 * Verifies that the watch input HTML is generated correctly, including the 'checked' attribute.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::watchInputHTML
	 */
	public function testWatchInputHTML() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::watchInputHTML( false, false, false, "Watch this" );

		// Check if the checkbox input is present with expected attributes
		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'id=\'wpWatchthis\'', $output );
		$this->assertStringContainsString( 'tabindex=\'2\'', $output );
		$this->assertStringContainsString( ' checked=\'checked\'', $output );
	}

	/**
	 * Test for saveAndContinueButtonHTML method.
	 *
	 * Verifies that the "Save and continue" button HTML is generated correctly with expected attributes.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTML() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::saveAndContinueButtonHTML( false, "Save and continue editing" );

		// Check if the button contains the expected string and attributes
		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'pf-save_and_continue', $output );
		$this->assertStringContainsString( 'id=\'wpSaveAndContinue\'', $output );
		$this->assertStringContainsString( 'accesskey=\'', $output );
		$this->assertStringContainsString( 'title=\'', $output );
		$this->assertStringContainsString( 'Save and continue editing', $output );
	}

	/**
	 * Test for saveAndContinueButtonHTML method when the button is disabled.
	 *
	 * Verifies that the "Save and continue" button is rendered correctly with the disabled class and attributes.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTMLWithDisabled() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::saveAndContinueButtonHTML( true, "Save and continue editing" );

		// Check if the button contains the disabled class and other expected attributes
		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'id=\'wpSaveAndContinue\'', $output );
		$this->assertStringContainsString( 'pf-save_and_continue disabled', $output );
		$this->assertStringContainsString( 'accesskey=\'', $output );
		$this->assertStringContainsString( 'title=\'', $output );
		$this->assertStringContainsString( 'Save and continue editing', $output );
	}

	/**
	 * Test for headerHTML method with valid header levels.
	 *
	 * Verifies that the headerHTML method returns the correct HTML for valid header levels.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::headerHTML
	 */
	public function testHeaderHTMLWithValidLevel() {
		$result = FormUtils::headerHTML( 'Sample Header', 2 );
		$this->assertSame( '<h2>Sample Header</h2>', $result );

		$result = FormUtils::headerHTML( 'Sample Header', 3 );
		$this->assertSame( '<h3>Sample Header</h3>', $result );

		$result = FormUtils::headerHTML( 'Sample Header', 6 );
		$this->assertSame( '<h6>Sample Header</h6>', $result );
	}

	/**
	 * Test for headerHTML method with invalid header levels.
	 *
	 * Verifies that the headerHTML method returns a fallback header when an invalid level is provided.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::headerHTML
	 */
	public function testHeaderHTMLWithInvalidLevel() {
		$result = FormUtils::headerHTML( 'Sample Header', "test" );
		$this->assertSame( '<h2>Sample Header</h2>', $result );

		$result = FormUtils::headerHTML( 'Sample Header', 10 );
		$this->assertSame( '<h6>Sample Header</h6>', $result );
	}

	/**
	 * Test for getChangedIndex method when the item is deleted.
	 *
	 * Verifies that the index is calculated correctly when the item is deleted.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithDeletedItemLoc() {
		$result = FormUtils::getChangedIndex( 5, null, 3 );
		$this->assertSame( 6, $result );

		$result = FormUtils::getChangedIndex( 2, null, 3 );
		$this->assertSame( 2, $result );
	}

	/**
	 * Test for getChangedIndex method when a new item is added.
	 *
	 * Verifies that the index is calculated correctly when a new item is added.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithNewItemLoc() {
		$result = FormUtils::getChangedIndex( 5, 3, null );
		$this->assertSame( 4, $result );

		$result = FormUtils::getChangedIndex( 3, 3, null );
		$this->assertSame( -1, $result );

		$result = FormUtils::getChangedIndex( 2, 3, null );
		$this->assertSame( 2, $result );
	}

	/**
	 * Test for setGlobalVarsForSpreadsheet method.
	 *
	 * Verifies that a second call within the same request is a no-op: it must not
	 * recompute (and thus overwrite) the globals once they have already been set,
	 * so that CalendarHtmlBuilder and SpreadsheetHtmlBuilder calling it redundantly
	 * only pays the wfMessage() lookup cost once per request.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::setGlobalVarsForSpreadsheet
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::resetGlobalVarsForSpreadsheetGuard
	 */
	public function testSetGlobalVarsForSpreadsheetSkipsRecomputationOnSecondCall() {
		global $wgPageFormsContLangYes;

		FormUtils::resetGlobalVarsForSpreadsheetGuard();

		FormUtils::setGlobalVarsForSpreadsheet();
		$firstValue = $wgPageFormsContLangYes;

		// Simulate the global having been externally changed after the first call;
		// a guarded second call must leave it untouched.
		$wgPageFormsContLangYes = 'sentinel-value-set-between-calls';

		FormUtils::setGlobalVarsForSpreadsheet();

		$this->assertSame(
			'sentinel-value-set-between-calls',
			$wgPageFormsContLangYes,
			'Second call must not recompute the global once the guard has been tripped'
		);
		$this->assertNotSame( '', $firstValue, 'First call must have computed a real value' );

		FormUtils::resetGlobalVarsForSpreadsheetGuard();
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::summaryInputHTML
	 */
	public function testSummaryInputHTMLDisabledWithClassAttribute() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::summaryInputHTML( true, "Summary", [ 'class' => 'pf-test-summary-class' ] );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLDefaultLabelCheckedDisabledWithClass() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::minorEditInputHTML(
			true, true, true, null, [ 'class' => 'pf-test-minoredit-class' ]
		);

		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'checked=\'checked\'', $output );
		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLDefaultLabelDisabledWithClass() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::watchInputHTML(
			true, true, false, null, [ 'class' => 'pf-test-watch-class' ]
		);

		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLWatchCreationsForNonExistentPage() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$originalTitle = RequestContext::getMain()->getTitle();
		$originalUser = RequestContext::getMain()->getUser();
		try {
			$user = MediaWiki\User\User::newSystemUser( 'PFTestFormUtilsWatchCreationsUser01', [ 'steal' => true ] );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchdefault', 0 );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchcreations', 1 );
			RequestContext::getMain()->setUser( $user );
			RequestContext::getMain()->setTitle( Title::newFromText( 'PFTestFormUtilsWatchCreationsPage01' ) );

			$output = FormUtils::watchInputHTML( false, false, false );

			$this->assertStringContainsString( 'checked=\'checked\'', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
			RequestContext::getMain()->setUser( $originalUser );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::watchInputHTML
	 * @group Database
	 */
	public function testWatchInputHTMLAlreadyWatchedPage() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$originalTitle = RequestContext::getMain()->getTitle();
		$originalUser = RequestContext::getMain()->getUser();
		try {
			$user = MediaWiki\User\User::newSystemUser( 'PFTestFormUtilsAlreadyWatchedUser01', [ 'steal' => true ] );
			$title = Title::newFromText( 'PFTestFormUtilsAlreadyWatchedPage01' );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchdefault', 0 );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchcreations', 0 );
			MediaWikiServices::getInstance()->getWatchlistManager()->addWatch( $user, $title );
			RequestContext::getMain()->setUser( $user );
			RequestContext::getMain()->setTitle( $title );

			$output = FormUtils::watchInputHTML( false, false, false );

			$this->assertStringContainsString( 'checked=\'checked\'', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
			RequestContext::getMain()->setUser( $originalUser );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::saveButtonHTML
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::buttonHTML
	 */
	public function testSaveButtonHTMLDisabledWithClassAttribute() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::saveButtonHTML( true, "Save", [ 'class' => 'pf-test-save-class' ] );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
		$this->assertStringContainsString( 'pf-test-save-class', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTMLDefaultLabel() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::saveAndContinueButtonHTML( false );

		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'id=\'wpSaveAndContinue\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::showPreviewButtonHTML
	 */
	public function testShowPreviewButtonHTMLDisabled() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::showPreviewButtonHTML( true );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::showChangesButtonHTML
	 */
	public function testShowChangesButtonHTMLDisabled() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = FormUtils::showChangesButtonHTML( true );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::cancelLinkHTML
	 */
	public function testCancelLinkHTMLOnFormEditWithReturnTo() {
		$originalTitle = RequestContext::getMain()->getTitle();
		$originalRequest = RequestContext::getMain()->getRequest();
		try {
			RequestContext::getMain()->setTitle( Title::newFromText( 'Special:FormEdit' ) );
			RequestContext::getMain()->setRequest(
				new FauxRequest( [ 'returnto' => 'PFTestFormUtilsCancelReturnToPage01' ] )
			);

			$output = FormUtils::cancelLinkHTML( false );

			$this->assertStringContainsString( 'PFTestFormUtilsCancelReturnToPage01', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
			RequestContext::getMain()->setRequest( $originalRequest );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::cancelLinkHTML
	 */
	public function testCancelLinkHTMLOnRegularPageWithClassAttribute() {
		$originalTitle = RequestContext::getMain()->getTitle();
		try {
			RequestContext::getMain()->setTitle( Title::newFromText( 'PFTestFormUtilsCancelRegularPage01' ) );

			$output = FormUtils::cancelLinkHTML( false, "Cancel", [ 'class' => 'pf-test-cancel-class' ] );

			$this->assertStringContainsString( '<a', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::runQueryButtonHTML
	 */
	public function testRunQueryButtonHTMLDefaultLabel() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = (string)FormUtils::runQueryButtonHTML();

		$this->assertStringContainsString( 'wpRunQuery', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::queryFormBottom
	 */
	public function testQueryFormBottom() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$output = (string)FormUtils::queryFormBottom();

		$this->assertStringContainsString( 'wpRunQuery', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::formBottom
	 * @group Database
	 */
	public function testFormBottomWithRegisteredUser() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		$originalUser = RequestContext::getMain()->getUser();
		$originalTitle = RequestContext::getMain()->getTitle();
		try {
			$user = MediaWiki\User\User::newSystemUser( 'PFTestFormUtilsFormBottomUser01', [ 'steal' => true ] );
			RequestContext::getMain()->setUser( $user );
			RequestContext::getMain()->setTitle( Title::newFromText( 'PFTestFormUtilsFormBottomPage01' ) );

			$output = FormUtils::formBottom( false, false );

			$this->assertStringContainsString( 'editOptions', $output );
			$this->assertStringContainsString( 'wpMinoredit', $output );
			$this->assertStringContainsString( 'wpWatchthis', $output );
		} finally {
			RequestContext::getMain()->setUser( $originalUser );
			RequestContext::getMain()->setTitle( $originalTitle );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getPreloadedText
	 */
	public function testGetPreloadedTextForwardsToFormCache() {
		$this->assertSame( '', FormUtils::getPreloadedText( '' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getFormDefinition
	 */
	public function testGetFormDefinitionForwardsToFormCache() {
		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$result = FormUtils::getFormDefinition( $parser, null, null );
		$this->assertSame( '', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::purgeCache
	 */
	public function testPurgeCacheForwardsToFormCache() {
		$title = Title::newFromText( 'PFTestFormUtilsPurgeCachePage01', NS_MAIN );
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );

		$result = FormUtils::purgeCache( $wikiPage );

		$this->assertTrue( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::purgeCacheOnSave
	 */
	public function testPurgeCacheOnSaveForwardsToFormCache() {
		$revisionRecord = $this->createMock( MediaWiki\Revision\RevisionRecord::class );
		$revisionRecord->method( 'getPageId' )->willReturn( 0 );

		$renderedRevision = $this->createMock( MediaWiki\Revision\RenderedRevision::class );
		$renderedRevision->method( 'getRevision' )->willReturn( $revisionRecord );

		$result = FormUtils::purgeCacheOnSave( $renderedRevision );

		$this->assertTrue( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getFormCache
	 */
	public function testGetFormCacheForwardsToFormCache() {
		$cache = FormUtils::getFormCache();
		$this->assertInstanceOf( BagOStuff::class, $cache );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getCacheKey
	 */
	public function testGetCacheKeyForwardsToFormCache() {
		$key = FormUtils::getCacheKey( 42 );
		$this->assertIsString( $key );
		$this->assertStringContainsString( '42', $key );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::setShowOnSelect
	 */
	public function testSetShowOnSelectAppendsToExistingInputID() {
		global $wgPageFormsShowOnSelect;
		$wgPageFormsShowOnSelect = [];

		FormUtils::setShowOnSelect( [ 'div1' => [ 'val1' ] ], 'pf_test_show_on_select_input01' );
		FormUtils::setShowOnSelect( [ 'div2' => [ 'val2' ] ], 'pf_test_show_on_select_input01' );

		$this->assertCount( 2, $wgPageFormsShowOnSelect['pf_test_show_on_select_input01'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getStringFromPassedInArray
	 */
	public function testGetStringFromPassedInArrayWithFullDateTimeComponents() {
		global $wgAmericanDates;
		$originalAmericanDates = $wgAmericanDates;
		$wgAmericanDates = false;

		try {
			$result = FormUtils::getStringFromPassedInArray( [
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

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTimeDateOnly() {
		global $wgAmericanDates, $wgLocaltimezone;
		$originalAmericanDates = $wgAmericanDates;
		$originalLocaltimezone = $wgLocaltimezone;
		$wgAmericanDates = true;
		$wgLocaltimezone = 'UTC';

		try {
			$result = FormUtils::getStringForCurrentTime( false, false );
			$this->assertIsString( $result );
			$this->assertNotSame( '', $result );
		} finally {
			$wgAmericanDates = $originalAmericanDates;
			$wgLocaltimezone = $originalLocaltimezone;
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getStringForCurrentTime
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
			$result = FormUtils::getStringForCurrentTime( true, true );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2} .+$/', $result );
		} finally {
			$wgAmericanDates = $originalAmericanDates;
			$wgLocaltimezone = $originalLocaltimezone;
			$wgPageForms24HourTime = $original24Hour;
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormUtils::getStringForCurrentTime
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
			$result = FormUtils::getStringForCurrentTime( true, false );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2} (AM|PM)$/', $result );
		} finally {
			$wgAmericanDates = $originalAmericanDates;
			$wgLocaltimezone = $originalLocaltimezone;
			$wgPageForms24HourTime = $original24Hour;
		}
	}
}
