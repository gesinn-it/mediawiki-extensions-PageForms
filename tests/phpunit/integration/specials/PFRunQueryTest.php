<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * Integration test class for the PFRunQuery special page.
 *
 * @covers \PFRunQuery
 *
 * @group Database
 *
 * @author gesinn-it-ilm
 */
class PFRunQueryTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
	}

	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'RunQuery' );
	}

	public function testExecuteWithoutQuery() {
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [ 'form' => '' ], true ) );

		$this->assertStringContainsString( 'error', $html );
	}

	/**
	 * Visiting the bare special page with no subpage and no 'form' request
	 * parameter leaves $query (and therefore $form_name) null. PHP 8.1+
	 * deprecates passing null to str_replace()'s non-nullable $subject
	 * parameter, which phpunit.xml.dist's convertWarningsToExceptions turns
	 * into a fatal test exception.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/141
	 */
	public function testExecuteWithNoSubpageAndNoFormParamDoesNotThrow() {
		[ $html ] = $this->executeSpecialPage( null, new FauxRequest( [] ) );

		$this->assertStringContainsString( 'error', $html );
	}

	public function testExecuteWithNonexistentForm() {
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [ 'form' => 'NonExistentForm' ] ) );

		$this->assertStringContainsString( 'class="error"', $html );
		$this->assertStringContainsString( 'NonExistentForm', $html );
	}

	public function testExecuteWithValidForm() {
		$formTitle = Title::newFromText( 'ValidForm', PF_NS_FORM );
		$this->insertPage( $formTitle, 'Form content here' );

		[ $html ] = $this->executeSpecialPage( 'ValidForm', new FauxRequest( [ 'form' => 'ValidForm' ] ) );

		$this->assertStringContainsString( 'Form content here', $html );
	}

	public function testSubmitFormQuery() {
		$formTitle = Title::newFromText( 'ValidForm', PF_NS_FORM );
		$this->insertPage( $formTitle, 'Form content here' );

		$request = new FauxRequest( [
			'form' => 'ValidForm',
			'_run' => 'true',
			'wpTextbox1' => 'Some content for query',
		] );

		[ $html ] = $this->executeSpecialPage( 'ValidForm', $request );

		$this->assertStringContainsString( 'Some content for query', $html );
	}

	/**
	 * An embedded query form submits to Special:RunQuery without a subpage when the request of the embedding page
	 * has no 'title' parameter (for example 'index.php?oldid=...'). The form name then only travels in the
	 * 'pfRunQueryFormName' parameter that the form adds itself.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/30
	 */
	public function testSubmitWithoutFormNameInTheUrlUsesPfRunQueryFormName() {
		$this->insertPage( Title::newFromText( 'PFRunQueryTestFormNameFallback', PF_NS_FORM ), 'Form content here' );

		$request = new FauxRequest( [
			'pfRunQueryFormName' => 'PFRunQueryTestFormNameFallback',
			'wpTextbox1' => 'Some content for query',
		] );

		[ $html ] = $this->executeSpecialPage( '', $request );

		$this->assertStringContainsString( 'Some content for query', $html );
		$this->assertStringNotContainsString( wfMessage( 'pf_runquery_badurl' )->text(), $html );
	}

	public function testFormNameInTheUrlWinsOverPfRunQueryFormName() {
		$this->insertPage( Title::newFromText( 'PFRunQueryTestFormNameWins', PF_NS_FORM ), 'Form content here' );

		$request = new FauxRequest( [
			'pfRunQueryFormName' => 'PFRunQueryTestFormNameOther',
			'wpTextbox1' => 'Some content for query',
		] );

		[ $html ] = $this->executeSpecialPage( 'PFRunQueryTestFormNameWins', $request );

		// The form echoes pfRunQueryFormName as a hidden field, so the form in use shows in its action.
		$this->assertStringContainsString( 'Special:RunQuery/PFRunQueryTestFormNameWins', $html );
		$this->assertStringNotContainsString( 'Special:RunQuery/PFRunQueryTestFormNameOther', $html );
	}

	public function testFormParameterWinsOverPfRunQueryFormName() {
		$this->insertPage( Title::newFromText( 'PFRunQueryTestFormParamWins', PF_NS_FORM ), 'Form content here' );

		$request = new FauxRequest( [
			'form' => 'PFRunQueryTestFormParamWins',
			'pfRunQueryFormName' => 'PFRunQueryTestFormParamOther',
		] );

		[ $html ] = $this->executeSpecialPage( '', $request );

		$this->assertStringContainsString( 'Special:RunQuery/PFRunQueryTestFormParamWins', $html );
		$this->assertStringNotContainsString( 'Special:RunQuery/PFRunQueryTestFormParamOther', $html );
	}

	public function testUnknownFormNameInPfRunQueryFormNameIsReported() {
		$request = new FauxRequest( [ 'pfRunQueryFormName' => 'PFRunQueryTestNoSuchForm' ] );

		[ $html ] = $this->executeSpecialPage( '', $request );

		$this->assertStringContainsString( 'class="error"', $html );
		$this->assertStringContainsString( 'PFRunQueryTestNoSuchForm', $html );
		$this->assertStringNotContainsString( wfMessage( 'pf_runquery_badurl' )->text(), $html );
	}

	/**
	 * The global Parser's $mOutput is only populated once something has
	 * called Parser::clearState() (directly, or via a first parse()). If
	 * nothing did so yet this request, PFRunQuery::printPage() must not
	 * assume it's already there before calling parse() with
	 * $clearState = false, since MW 1.42+ made Parser::$mOutput a typed
	 * property - accessing it uninitialized now throws instead of just
	 * being deprecated.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/191
	 */
	public function testSubmitFormQueryWithUninitializedGlobalParserDoesNotThrow() {
		$formTitle = Title::newFromText( 'ValidForm', PF_NS_FORM );
		$this->insertPage( $formTitle, 'Form content here' );

		MediaWikiServices::getInstance()->resetServiceForTesting( 'Parser' );

		$request = new FauxRequest( [
			'form' => 'ValidForm',
			'_run' => 'true',
			'wpTextbox1' => 'Some content for query',
		] );

		[ $html ] = $this->executeSpecialPage( 'ValidForm', $request );

		$this->assertStringContainsString( 'Some content for query', $html );
	}

	/**
	 * An embedded form with no {{{field|...}}} tags never runs
	 * FormField::newFromFormFieldTag(), which is what otherwise initializes
	 * the global parser during field rendering. printPage() must not assume
	 * the global parser is already initialized before reading its
	 * ParserOutput via addFormRLModules() - same root cause as issue #191,
	 * different trigger.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/191
	 */
	public function testEmbeddedFormWithNoFieldsAndUninitializedGlobalParserDoesNotThrow() {
		$formTitle = Title::newFromText( 'NoFieldsForm', PF_NS_FORM );
		$this->insertPage( $formTitle, "PFTestRunQueryNoFieldsMarker01\n"
			. "{{{standard input|save}}}" );

		MediaWikiServices::getInstance()->resetServiceForTesting( 'Parser' );

		$page = $this->newSpecialPage();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( new FauxRequest( [ 'form' => 'NoFieldsForm' ] ) );
		$context->setOutput( new OutputPage( $context ) );
		$page->setContext( $context );

		$page->printPage( 'NoFieldsForm', true );

		$this->assertStringContainsString( 'PFTestRunQueryNoFieldsMarker01', $context->getOutput()->getHTML() );
	}

	/**
	 * Renders an embedded query form for the given request of the embedding page and returns the form tag.
	 *
	 * @param string $formName
	 * @param FauxRequest $request Request of the page that embeds the form
	 * @return string
	 */
	private function renderEmbeddedFormTag( string $formName, FauxRequest $request ): string {
		$this->setMwGlobals( 'wgRequest', $request );
		$main = RequestContext::getMain();
		$originalRequest = $main->getRequest();
		$main->setRequest( $request );

		try {
			$page = $this->newSpecialPage();
			$context = new DerivativeContext( $main );
			$context->setOutput( new OutputPage( $context ) );
			// A user that has no request of its own reads the request of the main context.
			$context->setUser( $this->getTestUser()->getUser() );
			$page->setContext( $context );

			$page->printPage( $formName, true );
		} finally {
			$main->setRequest( $originalRequest );
		}

		$html = $context->getOutput()->getHTML();
		$this->assertSame( 1, preg_match( '/<form id="pfForm"[^>]*>/', $html, $matches ) );
		return $matches[0];
	}

	/**
	 * Without the 'title' parameter the form cannot name the embedding page. It must then submit to the address
	 * the user is on (no action attribute), so that the result shows on the embedding page.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/30
	 */
	public function testEmbeddedFormWithoutTitleInTheRequestSubmitsToTheCurrentAddress() {
		$this->insertPage( Title::newFromText( 'PFRunQueryTestEmbeddedNoTitle', PF_NS_FORM ), 'Form content here' );

		$formTag = $this->renderEmbeddedFormTag(
			'PFRunQueryTestEmbeddedNoTitle', new FauxRequest( [ 'oldid' => '5' ] )
		);

		$this->assertStringNotContainsString( 'action=', $formTag );
		$this->assertStringContainsString( 'method="get"', $formTag );
	}

	public function testEmbeddedFormWithoutTitleKeepsTheValuesOfTheRequestAsHiddenFields() {
		$this->insertPage( Title::newFromText( 'PFRunQueryTestEmbeddedHidden', PF_NS_FORM ), 'Form content here' );
		$request = new FauxRequest( [ 'oldid' => '5' ] );
		$this->setMwGlobals( 'wgRequest', $request );
		$main = RequestContext::getMain();
		$originalRequest = $main->getRequest();
		$main->setRequest( $request );

		try {
			$page = $this->newSpecialPage();
			$context = new DerivativeContext( $main );
			$context->setOutput( new OutputPage( $context ) );
			$context->setUser( $this->getTestUser()->getUser() );
			$page->setContext( $context );
			$page->printPage( 'PFRunQueryTestEmbeddedHidden', true );
		} finally {
			$main->setRequest( $originalRequest );
		}

		$html = $context->getOutput()->getHTML();
		// MediaWiki 1.39 writes self-closing hidden inputs, later versions do not.
		$this->assertMatchesRegularExpression( '#<input type="hidden" value="5" name="oldid"\s*/?>#', $html );
		$this->assertMatchesRegularExpression(
			'#<input type="hidden" value="PFRunQueryTestEmbeddedHidden" name="pfRunQueryFormName"\s*/?>#', $html
		);
	}

	public function testEmbeddedFormWithTitleInTheRequestSubmitsToTheEmbeddingPage() {
		$this->insertPage( Title::newFromText( 'PFRunQueryTestEmbeddedTitle', PF_NS_FORM ), 'Form content here' );

		$formTag = $this->renderEmbeddedFormTag(
			'PFRunQueryTestEmbeddedTitle', new FauxRequest( [ 'title' => 'PFRunQueryTestEmbeddingPage' ] )
		);

		$expectedAction = Title::newFromText( 'PFRunQueryTestEmbeddingPage' )->getLocalURL();
		$this->assertStringContainsString( 'action="' . $expectedAction . '"', $formTag );
	}

	public function testEmbeddedFormWithoutTitleAfterAPostSubmitsToTheSpecialPage() {
		$this->insertPage( Title::newFromText( 'PFRunQueryTestEmbeddedPost', PF_NS_FORM ), 'Form content here' );

		$formTag = $this->renderEmbeddedFormTag( 'PFRunQueryTestEmbeddedPost', new FauxRequest( [], true ) );

		$expectedAction = SpecialPage::getTitleFor( 'RunQuery' )->getLocalURL();
		$this->assertStringContainsString( 'action="' . $expectedAction . '"', $formTag );
	}

	/**
	 * ResourceLoader modules registered by parser tag hooks (e.g. ext.headertabs
	 * from <headertabs />) while rendering the form definition must reach the
	 * real OutputPage, not just the internal parser used by formHTML().
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/15
	 */
	public function testFormDefinitionParserTagModulesAreForwardedToOutputPage() {
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'ParserFirstCallInit',
			static function ( Parser $parser ) {
				$parser->setHook( 'pf-test-runquery-module-tag', static function (
					$input, array $args, Parser $p
				) {
					$p->getOutput()->addModules( [ 'ext.pageforms.test.sentinel' ] );
					return '';
				} );
			}
		);

		// A field tag is required to trigger FormField::clearState(), which
		// resets the global parser's ParserOutput and drops the module
		// registered above — reproducing the conditions of issue #15.
		$formTitle = Title::newFromText( 'ModuleForm', PF_NS_FORM );
		$this->insertPage( $formTitle, "<pf-test-runquery-module-tag />\n"
			. "{{{for template|PFTestRunQueryModTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}" );

		$page = $this->newSpecialPage();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( new FauxRequest( [ 'form' => 'ModuleForm' ] ) );
		$context->setOutput( new OutputPage( $context ) );
		$page->setContext( $context );

		$page->printPage( 'ModuleForm' );

		$this->assertContains( 'ext.pageforms.test.sentinel', $context->getOutput()->getModules() );
	}

	/**
	 * The {{{info|query form at top}}} tag only takes effect once formHTML()
	 * has parsed the form definition, since that parsing is what determines
	 * whether the tag is present. PFRunQuery::printPage() must therefore
	 * read formHTML()'s "run query form at top" return value, not query it
	 * beforehand - otherwise the form is always rendered below the query
	 * results, regardless of the tag.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/97
	 */
	public function testQueryFormAtTopTagMovesFormBeforeResults() {
		$formTitle = Title::newFromText( 'QueryFormAtTopForm', PF_NS_FORM );
		$this->insertPage( $formTitle, "{{{info|query form at top}}}\n"
			. "PFTestRunQueryFormAtTopMarker01" );

		$request = new FauxRequest( [
			'form' => 'QueryFormAtTopForm',
			'_run' => 'true',
			// The query results text is taken from "pf_free_text", not
			// "wpTextbox1" - the latter only seeds $existing_page_content,
			// which formHTML() ignores for RunQuery ($source_is_page = false).
			// Wikitext bold markup is parsed into a <b> tag in the results,
			// while the hidden "pf_free_text" input that preserves the query
			// string on the form reflects the value verbatim - this lets the
			// two occurrences be told apart unambiguously.
			'pf_free_text' => "'''PFTestRunQueryFormAtTopResultMarker01'''",
		] );

		[ $html ] = $this->executeSpecialPage( 'QueryFormAtTopForm', $request );

		$formPos = strpos( $html, 'PFTestRunQueryFormAtTopMarker01' );
		$resultPos = strpos( $html, '<b>PFTestRunQueryFormAtTopResultMarker01</b>' );

		$this->assertNotFalse( $formPos, 'Form marker not found in output' );
		$this->assertNotFalse( $resultPos, 'Result marker not found in output' );
		$this->assertLessThan( $resultPos, $formPos, 'Form should be rendered before the query results' );
	}
}
