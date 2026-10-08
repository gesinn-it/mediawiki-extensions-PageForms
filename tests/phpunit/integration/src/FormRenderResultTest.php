<?php

use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\Extension\PageForms\FormRenderResult;
use MediaWiki\MediaWikiServices;
use OOUI\BlankTheme;

/**
 * @covers \MediaWiki\Extension\PageForms\FormRenderResult
 * @covers \MediaWiki\Extension\PageForms\FormPrinter::render
 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
 * @group Database
 */
class FormRenderResultTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			}
		);
		parent::setUp();
	}

	public function testGettersReturnWhatTheResultWasBuiltWith(): void {
		$parserOutput = new ParserOutput( 'text' );

		$result = new FormRenderResult( 'form', 'page', 'Title', 'Name', $parserOutput, true );

		$this->assertSame( 'form', $result->getFormText() );
		$this->assertSame( 'page', $result->getPageText() );
		$this->assertSame( 'Title', $result->getFormPageTitle() );
		$this->assertSame( 'Name', $result->getGeneratedPageName() );
		$this->assertSame( $parserOutput, $result->getParserOutput() );
		$this->assertTrue( $result->isQueryFormAtTop() );
	}

	public function testToArrayKeepsTheOrderOfTheListFormHtmlHasAlwaysReturned(): void {
		$parserOutput = new ParserOutput( 'text' );

		$result = new FormRenderResult( 'form', 'page', null, null, $parserOutput, false );

		$this->assertSame(
			[ 'form', 'page', null, null, $parserOutput, false ],
			$result->toArray()
		);
	}

	private function render( string $formDef, bool $isQuery = false, ?string $pageName = 'PFRenderResultPage' ) {
		global $wgOut;
		$title = Title::makeTitle( NS_MAIN, 'PFRenderResultPage' );
		$wgOut->getContext()->setTitle( $title );
		RequestContext::getMain()->setTitle( $title );
		$printer = new FormPrinter();
		$user = $this->getTestUser()->getUser();

		return [
			$printer->render(
				$formDef, false, false, null, null, $pageName, null, $isQuery, false, false, [], $user
			),
			$printer->formHTML(
				$formDef, false, false, null, null, $pageName, null, $isQuery, false, false, [], $user
			),
		];
	}

	public function testRenderAndTheDeprecatedFormHtmlReturnTheSameValues(): void {
		$formDef = "{{{info|create title=Create it}}}\n{{{for template|PFRenderResultTpl}}}\n"
			. "{{{field|Name}}}\n{{{end template}}}\n{{{standard input|save}}}";

		[ $result, $list ] = $this->render( $formDef );

		$this->assertInstanceOf( FormRenderResult::class, $result );
		$this->assertSame( 'Create it', $result->getFormPageTitle() );
		$this->assertStringContainsString( 'PFRenderResultTpl[Name]', $result->getFormText() );
		$this->assertCount( 6, $list );
		// The values are the same, but the volatile hidden inputs (edit time and token) of the
		// two forms are not compared.
		$this->assertSame( $result->getPageText(), $list[1] );
		$this->assertSame( $result->getFormPageTitle(), $list[2] );
		$this->assertSame( $result->getGeneratedPageName(), $list[3] );
		$this->assertInstanceOf( ParserOutput::class, $list[4] );
		$this->assertSame( $result->isQueryFormAtTop(), $list[5] );
	}

	public function testQueryFormAtTopIsPartOfTheResultAndDoesNotCarryOver(): void {
		$withTag = "{{{info|query form at top}}}\n{{{for template|PFRenderResultTpl}}}\n"
			. "{{{field|Name}}}\n{{{end template}}}\n{{{standard input|run query}}}";
		$withoutTag = "{{{for template|PFRenderResultTpl}}}\n{{{field|Name}}}\n{{{end template}}}\n"
			. "{{{standard input|run query}}}";
		$title = Title::makeTitle( NS_MAIN, 'PFRenderResultPage' );
		RequestContext::getMain()->setTitle( $title );
		$printer = new FormPrinter();
		$user = $this->getTestUser()->getUser();

		$first = $printer->render( $withTag, false, false, null, null, null, null, true, false, false, [], $user );
		$second = $printer->render( $withoutTag, false, false, null, null, null, null, true, false, false, [], $user );

		$this->assertTrue( $first->isQueryFormAtTop() );
		$this->assertFalse( $second->isQueryFormAtTop() );
	}

	public function testRenderingAFormWhileAnotherRenderIsInProgressDoesNotAffectEitherResult(): void {
		$outerDef = "{{{for template|PFNestedTpl}}}\n{{{field|Name}}}\n{{{end template}}}\n"
			. "{{{standard input|save}}}";
		$innerDef = "{{{for template|PFNestedTpl}}}\n{{{field|Other}}}\n{{{end template}}}";
		$this->editPage( 'PFNestedInnerPage', 'existing' );
		$user = $this->getTestUser()->getUser();
		$printer = new FormPrinter();
		$renderOuter = static fn () => $printer->render(
			$outerDef, false, false, null, null, 'PFNestedOuterPage', null, false, false, false, [], $user
		);
		// The hidden start time, edit time and edit token are the only parts that differ between
		// two renders; the token embeds a timestamp, so it changes when a second boundary passes.
		$stable = static fn ( string $html ) => preg_replace(
			"/<input[^>]*wp(Starttime|Edittime|EditToken)[^>]*>/", '', $html
		);
		$expectedOuter = $stable( $renderOuter()->getFormText() );

		$inner = null;
		$started = false;
		$this->setTemporaryHook(
			'PageForms::BeforeFreeTextSubst',
			static function () use ( &$inner, &$started, $printer, $innerDef, $user ) {
				if ( !$started ) {
					$started = true;
					$inner = $printer->render(
						$innerDef, false, false, null, null, 'PFNestedInnerPage', null, false, false, false, [], $user
					);
				}
			}
		);
		$outer = $renderOuter();

		$this->assertNotNull( $inner );
		$this->assertSame( $expectedOuter, $stable( $outer->getFormText() ) );
		// The inner form has no standard input of its own, so it got the default form bottom;
		// the outer one defines its own, which the comparison above
		// shows it kept as it was.
		$this->assertStringContainsString( 'wpSave', $inner->getFormText() );
	}
}
