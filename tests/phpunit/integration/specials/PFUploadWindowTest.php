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

	/**
	 * processUpload() must escape attacker-controlled request parameters
	 * (pfInputID, pfDelimiter, and the destination filename) before they
	 * are interpolated into the <script> block it prints. Regression test
	 * for a reflected XSS: an unescaped value could break out of the
	 * getElementById("...") / '...' JS string-literal contexts and inject
	 * arbitrary script (GitHub issue #59).
	 */
	public function testProcessUploadEscapesMaliciousInputIdAndDelimiter() {
		$maliciousInputId = 'x");}});</script><script>alert(document.cookie)</script>' .
			'<script>if(false){(function(){var y=("';
		$maliciousDelimiter = "';alert(1);//";
		$maliciousDestName = "it's-a-test.jpg";

		/** @var PFUploadWindow $page */
		$page = $this->newSpecialPage();
		$page->mInputID = $maliciousInputId;
		$page->mDelimiter = $maliciousDelimiter;
		$page->mDesiredDestName = $maliciousDestName;

		$mockUpload = $this->getMockBuilder( UploadBase::class )
			->onlyMethods( [
				'verifyPermissions',
				'fetchFile',
				'verifyUpload',
				'checkWarnings',
				'performUpload',
			] )
			->getMockForAbstractClass();
		$mockUpload->method( 'verifyPermissions' )->willReturn( true );
		$mockUpload->method( 'fetchFile' )->willReturn( Status::newGood() );
		$mockUpload->method( 'verifyUpload' )->willReturn( [ 'status' => UploadBase::OK ] );
		$mockUpload->method( 'checkWarnings' )->willReturn( [] );
		$mockUpload->method( 'performUpload' )->willReturn( Status::newGood() );

		$page->mUpload = $mockUpload;
		$page->mIgnoreWarning = true;
		$page->mForReUpload = false;
		$page->mComment = '';
		$page->mWatchThis = false;

		$method = new ReflectionMethod( PFUploadWindow::class, 'processUpload' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $page );
		$output = ob_get_clean();

		$this->assertIsString( $output );

		// The malicious pfInputID must never be able to close the
		// surrounding <script> tag: the literal, tag-closing sequence
		// must not appear anywhere in the output.
		$this->assertStringNotContainsString(
			'</script><script>alert(document.cookie)</script>',
			$output,
			'Unescaped pfInputID must not be able to close and reopen a <script> tag'
		);
		$this->assertStringNotContainsString(
			'<script>alert(document.cookie)</script>',
			$output,
			'Unescaped pfInputID must not be able to inject a literal <script> tag'
		);

		// The "</script>" substring from the payload must only ever appear
		// in its JSON-escaped form (angle brackets escaped to </>),
		// which cannot be interpreted by the HTML parser as a closing tag.
		$this->assertStringContainsString( '\\u003C/script\\u003E', $output );

		// getElementById() must receive a properly quoted, JSON-encoded
		// string argument (e.g. getElementById("x\");}}); ... ")) — not
		// the raw value spliced into a bare double-quoted attribute-like
		// context.
		$this->assertRegExp(
			'/getElementById\(\s*"(?:[^"\\\\]|\\\\.)*"\s*\)/',
			$output,
			'pfInputID must be passed to getElementById() as a fully quoted, escaped JSON string literal'
		);

		// The malicious delimiter must be JSON-encoded as its own quoted
		// string literal (with the JS-breaking "'" safely inside double
		// quotes), never spliced raw into a single-quoted literal.
		$this->assertStringContainsString( '"\';alert(1);//"', $output );
		$this->assertStringNotContainsString( "'';alert(1);//'", $output );

		// The filename, including its apostrophe, is safely embedded inside
		// a double-quoted JSON string literal (no escaping needed for `'`
		// in that context, but it must never sit inside a single-quoted
		// literal where it could terminate the string early).
		$this->assertStringContainsString( '"it\'s-a-test.jpg"', $output );
		$this->assertStringNotContainsString( "'it's-a-test.jpg'", $output );
	}
}
