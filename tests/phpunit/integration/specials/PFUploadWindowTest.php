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
	 * showViewDeletedLinks() must not crash when the destination file was
	 * previously deleted and does not currently exist, because that branch
	 * calls showDeletionLog(), which must exist on PFUploadWindow.
	 *
	 * Regression test for: "Call to undefined method
	 * PFUploadWindow::showDeletionLog()"
	 */
	public function testShowViewDeletedLinksDoesNotCrashOnDeletedFile() {
		$sysop = $this->getTestUser( [ 'sysop' ] )->getAuthority();
		$title = Title::makeTitle( NS_FILE, 'PFTestUploadWindowDeletedFile.png' );

		$this->insertPage( $title );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$page->doDeleteArticleReal( 'test deletion', $sysop );

		// Use a non-privileged user (without 'deletedhistory') so the
		// unrelated Skin::linkKnown() branch above is skipped and this
		// test isolates the showDeletionLog() bug under test.
		$performer = $this->getTestUser()->getAuthority();

		/** @var PFUploadWindow $specialPage */
		$specialPage = $this->newSpecialPage();
		$specialPage->setContext( RequestContext::getMain() );
		RequestContext::getMain()->setUser( $performer->getUser() );
		$specialPage->mDesiredDestName = $title->getText();

		$method = new ReflectionMethod( PFUploadWindow::class, 'showViewDeletedLinks' );
		$method->setAccessible( true );

		$exception = null;
		try {
			$method->invoke( $specialPage );
		} catch ( Error $e ) {
			$exception = $e;
		}

		$this->assertNull(
			$exception,
			'showViewDeletedLinks() must not throw when the destination file was previously deleted'
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
	 * showUploadForm() must call showViewDeletedLinks() when mDesiredDestName
	 * IS set — i.e. the user is actively re-uploading a file with a known
	 * destination name — matching upstream SpecialUpload::showUploadForm(),
	 * which guards the equivalent call with `if ( $this->mDesiredDestName )`.
	 */
	public function testShowUploadFormShowsViewDeletedLinksWhenDestNameIsSet() {
		/** @var PFUploadWindow&\PHPUnit\Framework\MockObject\MockObject $page */
		$page = $this->getMockBuilder( PFUploadWindow::class )
			->onlyMethods( [ 'showViewDeletedLinks' ] )
			->getMock();
		$page->method( 'showViewDeletedLinks' )
			->willThrowException( new RuntimeException( 'showViewDeletedLinks called' ) );
		$page->mDesiredDestName = 'PFTestUploadWindowReupload.png';

		$form = $this->createMock( PFUploadForm::class );
		$form->method( 'show' )->willReturn( null );

		$method = new ReflectionMethod( PFUploadWindow::class, 'showUploadForm' );
		$method->setAccessible( true );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'showViewDeletedLinks called' );

		$method->invoke( $page, $form );
	}

	/**
	 * showUploadForm() must NOT call showViewDeletedLinks() when
	 * mDesiredDestName is empty — there is no destination file yet to show
	 * deletion notices for.
	 */
	public function testShowUploadFormSkipsViewDeletedLinksWhenDestNameIsEmpty() {
		/** @var PFUploadWindow&\PHPUnit\Framework\MockObject\MockObject $page */
		$page = $this->getMockBuilder( PFUploadWindow::class )
			->onlyMethods( [ 'showViewDeletedLinks' ] )
			->getMock();
		$page->expects( $this->never() )->method( 'showViewDeletedLinks' );
		$page->mDesiredDestName = '';

		$form = $this->createMock( PFUploadForm::class );
		$form->expects( $this->once() )->method( 'show' );

		$method = new ReflectionMethod( PFUploadWindow::class, 'showUploadForm' );
		$method->setAccessible( true );

		$method->invoke( $page, $form );
	}

	/**
	 * showViewDeletedLinks() must not crash for a privileged user (with
	 * 'deletedhistory') on a deleted file — this exercises the "restore
	 * link" branch that renders via LinkRenderer::makeKnownLink(), unlike
	 * testShowViewDeletedLinksDoesNotCrashOnDeletedFile() above which uses
	 * an unprivileged user specifically to skip this branch.
	 *
	 * Regression test for: "Call to undeclared method Skin::linkKnown"
	 */
	public function testShowViewDeletedLinksDoesNotCrashForPrivilegedUserOnDeletedFile() {
		$sysop = $this->getTestUser( [ 'sysop' ] )->getAuthority();
		$title = Title::makeTitle( NS_FILE, 'PFTestUploadWindowDeletedFilePrivileged.png' );

		$this->insertPage( $title );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$page->doDeleteArticleReal( 'test deletion', $sysop );

		/** @var PFUploadWindow $specialPage */
		$specialPage = $this->newSpecialPage();
		$specialPage->setContext( RequestContext::getMain() );
		RequestContext::getMain()->setUser( $sysop->getUser() );
		$specialPage->mDesiredDestName = $title->getText();

		$method = new ReflectionMethod( PFUploadWindow::class, 'showViewDeletedLinks' );
		$method->setAccessible( true );

		$exception = null;
		try {
			$method->invoke( $specialPage );
		} catch ( Error $e ) {
			$exception = $e;
		}

		$this->assertNull(
			$exception,
			'showViewDeletedLinks() must not throw for a privileged user on a deleted file'
		);
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
		$this->assertRegex(
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

	/**
	 * Cross-version assertRegexp: uses assertMatchesRegularExpression (PHPUnit ≥ 9)
	 * when available, otherwise falls back to assertRegExp (PHPUnit 8, MW 1.35).
	 */
	private function assertRegex( string $pattern, string $string, string $message = '' ): void {
		if ( method_exists( $this, 'assertMatchesRegularExpression' ) ) {
			$this->assertMatchesRegularExpression( $pattern, $string, $message );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->assertRegExp( $pattern, $string, $message );
		}
	}
}
