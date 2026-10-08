<?php

use MediaWiki\Extension\PageForms\FormButtons;
use MediaWiki\Extension\PageForms\FormCounters;
use MediaWiki\MediaWikiServices;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FormButtons.
 *
 * @group PF
 */
class FormButtonsTest extends TestCase {

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
	 * Test for minorEditInputHTML method.
	 *
	 * Verifies that the minor edit input HTML is generated correctly with the given parameters.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::minorEditInputHTML
	 */
	public function testMinorEditInputHTML() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::minorEditInputHTML( false, false, false, "Test" );

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
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::watchInputHTML
	 */
	public function testWatchInputHTML() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::watchInputHTML( false, false, false, "Watch this" );

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
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTML() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::saveAndContinueButtonHTML( false, "Save and continue editing" );

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
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTMLWithDisabled() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::saveAndContinueButtonHTML( true, "Save and continue editing" );

		// Check if the button contains the disabled class and other expected attributes
		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'id=\'wpSaveAndContinue\'', $output );
		$this->assertStringContainsString( 'pf-save_and_continue disabled', $output );
		$this->assertStringContainsString( 'accesskey=\'', $output );
		$this->assertStringContainsString( 'title=\'', $output );
		$this->assertStringContainsString( 'Save and continue editing', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::summaryInputHTML
	 */
	public function testSummaryInputHTMLDisabledWithClassAttribute() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::summaryInputHTML( true, "Summary", [ 'class' => 'pf-test-summary-class' ] );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLDefaultLabelCheckedDisabledWithClass() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::minorEditInputHTML(
			true, true, true, null, [ 'class' => 'pf-test-minoredit-class' ]
		);

		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'checked=\'checked\'', $output );
		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::watchInputHTML
	 */
	public function testWatchInputHTMLDefaultLabelDisabledWithClass() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::watchInputHTML(
			true, true, false, null, [ 'class' => 'pf-test-watch-class' ]
		);

		$this->assertStringContainsString( '<input type=\'checkbox\'', $output );
		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::watchInputHTML
	 */
	public function testWatchInputHTMLWatchCreationsForNonExistentPage() {
		FormCounters::current()->tabIndex = 1;

		$originalTitle = RequestContext::getMain()->getTitle();
		$originalUser = RequestContext::getMain()->getUser();
		try {
			$user = User::newSystemUser( 'PFTestFormUtilsWatchCreationsUser01', [ 'steal' => true ] );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchdefault', 0 );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchcreations', 1 );
			RequestContext::getMain()->setUser( $user );
			RequestContext::getMain()->setTitle( Title::newFromText( 'PFTestFormUtilsWatchCreationsPage01' ) );

			$output = FormButtons::watchInputHTML( false, false, false );

			$this->assertStringContainsString( 'checked=\'checked\'', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
			RequestContext::getMain()->setUser( $originalUser );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::watchInputHTML
	 * @group Database
	 */
	public function testWatchInputHTMLAlreadyWatchedPage() {
		FormCounters::current()->tabIndex = 1;

		$originalTitle = RequestContext::getMain()->getTitle();
		$originalUser = RequestContext::getMain()->getUser();
		try {
			$user = User::newSystemUser( 'PFTestFormUtilsAlreadyWatchedUser01', [ 'steal' => true ] );
			$title = Title::newFromText( 'PFTestFormUtilsAlreadyWatchedPage01' );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchdefault', 0 );
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption( $user, 'watchcreations', 0 );
			MediaWikiServices::getInstance()->getWatchlistManager()->addWatch( $user, $title );
			RequestContext::getMain()->setUser( $user );
			RequestContext::getMain()->setTitle( $title );

			$output = FormButtons::watchInputHTML( false, false, false );

			$this->assertStringContainsString( 'checked=\'checked\'', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
			RequestContext::getMain()->setUser( $originalUser );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::saveButtonHTML
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::buttonHTML
	 */
	public function testSaveButtonHTMLDisabledWithClassAttribute() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::saveButtonHTML( true, "Save", [ 'class' => 'pf-test-save-class' ] );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
		$this->assertStringContainsString( 'pf-test-save-class', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTMLDefaultLabel() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::saveAndContinueButtonHTML( false );

		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'id=\'wpSaveAndContinue\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::showPreviewButtonHTML
	 */
	public function testShowPreviewButtonHTMLDisabled() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::showPreviewButtonHTML( true );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::showChangesButtonHTML
	 */
	public function testShowChangesButtonHTMLDisabled() {
		FormCounters::current()->tabIndex = 1;

		$output = FormButtons::showChangesButtonHTML( true );

		$this->assertStringContainsString( 'disabled=\'disabled\'', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::cancelLinkHTML
	 */
	public function testCancelLinkHTMLOnFormEditWithReturnTo() {
		$originalTitle = RequestContext::getMain()->getTitle();
		$originalRequest = RequestContext::getMain()->getRequest();
		try {
			RequestContext::getMain()->setTitle( Title::newFromText( 'Special:FormEdit' ) );
			RequestContext::getMain()->setRequest(
				new FauxRequest( [ 'returnto' => 'PFTestFormUtilsCancelReturnToPage01' ] )
			);

			$output = FormButtons::cancelLinkHTML( false );

			$this->assertStringContainsString( 'PFTestFormUtilsCancelReturnToPage01', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
			RequestContext::getMain()->setRequest( $originalRequest );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::cancelLinkHTML
	 */
	public function testCancelLinkHTMLOnRegularPageWithClassAttribute() {
		$originalTitle = RequestContext::getMain()->getTitle();
		try {
			RequestContext::getMain()->setTitle( Title::newFromText( 'PFTestFormUtilsCancelRegularPage01' ) );

			$output = FormButtons::cancelLinkHTML( false, "Cancel", [ 'class' => 'pf-test-cancel-class' ] );

			$this->assertStringContainsString( '<a', $output );
		} finally {
			RequestContext::getMain()->setTitle( $originalTitle );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::runQueryButtonHTML
	 */
	public function testRunQueryButtonHTMLDefaultLabel() {
		FormCounters::current()->tabIndex = 1;

		$output = (string)FormButtons::runQueryButtonHTML();

		$this->assertStringContainsString( 'wpRunQuery', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::queryFormBottom
	 */
	public function testQueryFormBottom() {
		FormCounters::current()->tabIndex = 1;

		$output = (string)FormButtons::queryFormBottom();

		$this->assertStringContainsString( 'wpRunQuery', $output );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\FormButtons::formBottom
	 * @group Database
	 */
	public function testFormBottomWithRegisteredUser() {
		FormCounters::current()->tabIndex = 1;

		$originalUser = RequestContext::getMain()->getUser();
		$originalTitle = RequestContext::getMain()->getTitle();
		try {
			$user = User::newSystemUser( 'PFTestFormUtilsFormBottomUser01', [ 'steal' => true ] );
			RequestContext::getMain()->setUser( $user );
			RequestContext::getMain()->setTitle( Title::newFromText( 'PFTestFormUtilsFormBottomPage01' ) );

			$output = FormButtons::formBottom( false, false );

			$this->assertStringContainsString( 'editOptions', $output );
			$this->assertStringContainsString( 'wpMinoredit', $output );
			$this->assertStringContainsString( 'wpWatchthis', $output );
		} finally {
			RequestContext::getMain()->setUser( $originalUser );
			RequestContext::getMain()->setTitle( $originalTitle );
		}
	}
}
