<?php

use MediaWiki\MediaWikiServices;

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
