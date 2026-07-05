<?php

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;

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

		// A field tag is required to trigger PFFormField::clearState(), which
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
}
