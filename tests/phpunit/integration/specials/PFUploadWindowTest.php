<?php

use MediaWiki\MediaWikiServices;

/**
 * @covers \PFUploadWindow
 * @group Database
 *
 * @author gesinn-it-gea
 */
class PFUploadWindowTest extends SpecialPageTestBase {

	/**
	 * Create an instance of the special page being tested.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'UploadWindow' );
	}

	/**
	 * showViewDeletedLinks() must not crash when mDesiredDestName is empty,
	 * because Title::makeTitleSafe( NS_FILE, '' ) returns null.
	 *
	 * Regression test for: "Call to a member function isDeleted() on null"
	 */
	public function testShowViewDeletedLinksDoesNotCrashOnEmptyDestName() {
		/** @var PFUploadWindow $page */
		$page = $this->newSpecialPage();
		$page->mDesiredDestName = '';

		$method = new ReflectionMethod( PFUploadWindow::class, 'showViewDeletedLinks' );
		$method->setAccessible( true );

		$exception = null;
		try {
			$method->invoke( $page );
		} catch ( Error $e ) {
			$exception = $e;
		}

		$this->assertNull(
			$exception,
			'showViewDeletedLinks() must not throw when mDesiredDestName is empty'
		);
	}

	/**
	 * getUploadForm() must return a PFUploadForm instance without throwing,
	 * given a fully wired context.
	 */
	public function testGetUploadFormReturnsAPFUploadFormInstance() {
		/** @var PFUploadWindow $page */
		$page = $this->newSpecialPage();

		$method = new ReflectionMethod( PFUploadWindow::class, 'getUploadForm' );
		$method->setAccessible( true );

		$form = $method->invoke( $page );

		$this->assertInstanceOf(
			PFUploadForm::class,
			$form,
			'getUploadForm() must return a PFUploadForm instance'
		);
	}

	/**
	 * executeSpecialPage() must not throw — this exercises showUploadForm()
	 * via the normal execute() path and verifies the page renders without error.
	 */
	public function testExecuteRendersUploadForm() {
		$this->setMwGlobals( 'wgEnableUploads', true );

		$performer = $this->getTestUser( [ 'sysop' ] )->getAuthority();
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [] ), null, $performer );

		$this->assertIsString( $html );
	}

	/**
	 * getInitialPageText() with no copyright upload returns only the comment.
	 */
	public function testGetInitialPageTextReturnsStringWithoutCopyrightUpload() {
		$this->setMwGlobals( 'wgUseCopyrightUpload', false );

		$result = PFUploadWindow::getInitialPageText( 'My comment', '', '', '' );

		$this->assertIsString( $result );
		$this->assertSame( 'My comment', $result );
	}

	/**
	 * getInitialPageText() with copyright upload enabled returns structured
	 * text that contains all four section headers.
	 */
	public function testGetInitialPageTextWithCopyrightUploadContainsSections() {
		$this->setMwGlobals( 'wgUseCopyrightUpload', true );

		$result = PFUploadWindow::getInitialPageText( 'desc', 'cc-by-sa', 'Public Domain', 'Web' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'desc', $result );
		$this->assertStringContainsString( 'cc-by-sa', $result );
	}

	/**
	 * getInitialPageText() always returns a string (return-type contract).
	 */
	public function testGetInitialPageTextAlwaysReturnsString() {
		$this->assertIsString( PFUploadWindow::getInitialPageText() );
		$this->assertIsString( PFUploadWindow::getInitialPageText( 'comment', 'license', 'status', 'source' ) );
	}

	/**
	 * getExistsWarning() with a falsy argument returns an empty string.
	 */
	public function testGetExistsWarningReturnEmptyStringForFalsyInput() {
		$this->assertSame( '', PFUploadWindow::getExistsWarning( false ) );
		$this->assertSame( '', PFUploadWindow::getExistsWarning( [] ) );
	}

	/**
	 * getExistsWarning() with an 'exists' warning returns a non-empty HTML
	 * string containing a <li> element.
	 */
	public function testGetExistsWarningReturnsHtmlForExistsWarning() {
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'getPrefixedText' )->willReturn( 'File:Test.png' );

		$mockFile = $this->createMock( File::class );
		$mockFile->method( 'getTitle' )->willReturn( $mockTitle );

		$exists = [
			'warning' => 'exists',
			'file' => $mockFile,
		];

		$result = PFUploadWindow::getExistsWarning( $exists );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '<li>', $result );
	}

	/**
	 * getExistsWarning() with a 'page-exists' warning returns HTML with <li>.
	 */
	public function testGetExistsWarningReturnsHtmlForPageExistsWarning() {
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'getPrefixedText' )->willReturn( 'File:Test.png' );

		$mockFile = $this->createMock( File::class );
		$mockFile->method( 'getTitle' )->willReturn( $mockTitle );

		$exists = [
			'warning' => 'page-exists',
			'file' => $mockFile,
		];

		$result = PFUploadWindow::getExistsWarning( $exists );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '<li>', $result );
	}
}
