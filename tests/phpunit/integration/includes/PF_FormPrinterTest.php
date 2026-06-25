<?php

use MediaWiki\Extension\PageForms\HtmlFormDataExtractor;
use MediaWiki\MediaWikiServices;
use OOUI\BlankTheme;

/**
 * @covers \PFFormPrinter
 * @group Database
 *
 * @author Himeshi De Silva
 */
class PFFormPrinterTest extends MediaWikiIntegrationTestCase {

	/**
	 * Set up the environment
	 */
	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );

		// Make sure the form is not in "disabled" state. Unfortunately setting up the global state
		// environment in a proper way to have PFFormPrinter work on a mock title object is very
		// difficult. Therefore we just override the permission check by using a hook.
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			} );

		parent::setUp();
	}

	// Tests for page sections in the formHTML() method

	/**
	 * @dataProvider pageSectionDataProvider
	 */
	public function testPageSectionsWithoutExistingPages( $setup, $expected ) {
		global $wgPageFormsFormPrinter, $wgOut;

		$form_def = $setup['form_definition'];
		$form_submitted = true;
		// assign user minor edit right for test case no 6
		if ( strpos( $form_def, 'section 6' ) !== false || strpos( $form_def, 'section 7' ) !== false ) {
			$user = $this->getTestUser()->getUser();
			MediaWikiServices::getInstance()->getUserGroupManager()->addUserToGroup( $user, 'autoconfirmed' );
			RequestContext::getMain()->setUser( $user );
			$form_submitted = false;
		}

		$wgOut->getContext()->setTitle( $this->getTitle() );

		[ $form_text, $page_text, $form_page_title, $generated_page_name ] =
			$wgPageFormsFormPrinter->formHTML(
				$form_def,
				$form_submitted,
				$source_is_page = false,
				$form_id = null,
				$existing_page_content = null,
				$page_name = 'TestStringForFormPageTitle',
				$page_name_formula = null,
				$is_query = false,
				$is_embedded = false,
				$is_autocreate = false,
				$autocreate_query = [],
				$user = self::getTestUser()->getUser()
			);

		$this->assertStringContainsString(
			$expected['expected_form_text'],
			$form_text,
			'asserts that formHTML() returns the correct HTML text for the form for the given test input'
			);
		$this->assertStringContainsString(
			$expected['expected_page_text'],
			$page_text,
			'assert that formHTML() returns the correct text for the page created by the form'
			);
	}

	/**
	 * Data provider method
	 */
	public function pageSectionDataProvider() {
		$provider = [];

		// #1 form definition without other parameters
		$provider[] = [
		[
			'form_definition' => "==section1==
								 {{{section|section1|level=2}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection\">"
				. "<textarea tabindex=\"1\" name=\"_section[section1]\" id=\"input_1\""
				. " class=\"createboxInput\" rows=\"5\" cols=\"90\" style=\"width: 100%\"></textarea></span>",
			'expected_page_text' => "==section1==" ]
		];

		// #2 'rows' and 'colums' parameters set
		$provider[] = [
		[
			'form_definition' => "=====section 2=====
								 {{{section|section 2|level=5|rows=10|cols=5}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection\">"
				. "<textarea tabindex=\"1\" name=\"_section[section 2]\" id=\"input_1\""
				. " class=\"createboxInput\" rows=\"10\" cols=\"5\" style=\"width: auto\"></textarea></span>",
			'expected_page_text' => "=====section 2=====" ]
		];

		// #3 'mandatory' and 'autogrow' parameters set
		$provider[] = [
		[
			'form_definition' => "==section 3==
								 {{{section|section 3|level=2|mandatory|rows=20|cols=50|autogrow}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection mandatoryFieldSpan\">"
				. "<textarea tabindex=\"1\" name=\"_section[section 3]\" id=\"input_1\""
				. " class=\"mandatoryField autoGrow\" rows=\"20\" cols=\"50\" style=\"width: auto\"></textarea></span>",
			'expected_page_text' => "==section 3==" ]
		];

		// #4 'restricted' parameter set
		$provider[] = [
		[
			'form_definition' => "===Section 5===
								 {{{section|Section 5|level=3|restricted|class=FormTest}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection\">"
				. "<textarea tabindex=\"1\" name=\"_section[Section 5]\" id=\"input_1\""
				. " class=\"createboxInput FormTest\" rows=\"5\" cols=\"90\""
				. " style=\"width: 100%\" disabled=\"\"></textarea></span>",
			'expected_page_text' => "===Section 5===" ]
		];

		// #5 'hidden' parameter set
		$provider[] = [
		[
			'form_definition' => "====section 4====
								 {{{section|section 4|level=4|hidden}}}" ],
		[
			'expected_form_text' => "<input type=\"hidden\" name=\"_section[section 4]\"",
			'expected_page_text' => "====section 4====" ]
		];

		$ariaDisabled = " ";

		// #6 check when minor edit checkbox is enabled for user and $form_submitted is false
		$provider[] = [
			[
				'form_definition' => "=====section 6=====
									 {{{section|section 6|level=5}}}" ],
			[
				'expected_form_text' => "<span id='wpMinoredit'{$ariaDisabled}"
					. "class='oo-ui-widget oo-ui-widget-enabled oo-ui-inputWidget oo-ui-checkboxInputWidget'>"
					. "<input type='checkbox' tabindex='3'{$ariaDisabled}accesskey='i' name='wpMinoredit' value=''",
				'expected_page_text' => "=====section 6=====" ]
		];

		// #7 check when watch checkbox is enabled for user and $form_submitted is false
		$provider[] = [
			[
				'form_definition' => "=====section 7=====
									{{{section|section 7|level=5|rows=5|cols=8}}}" ],
			[
				'expected_form_text' => "<span id='wpWatchthis'{$ariaDisabled}"
					. "class='oo-ui-widget oo-ui-widget-enabled oo-ui-inputWidget oo-ui-checkboxInputWidget'>"
					. "<input type='checkbox' tabindex='4'{$ariaDisabled}accesskey='w'"
					. " name='wpWatchthis' value='' checked='checked'",
				'expected_page_text' => "=====section 7=====" ]
		];
		return $provider;
	}

	// -------------------------------------------------------------------------
	// formHTML() – preload roundtrip (source_is_page = true)
	// -------------------------------------------------------------------------

	/**
	 * When an existing page contains {{Template|field=value}} and formHTML()
	 * is called with $source_is_page = true, the generated HTML must carry
	 * that value in the input, and HtmlFormDataExtractor must recover it.
	 *
	 * This is the spec-level regression test for the preload channel of
	 * PFAutoeditAPI::doAction():
	 *   formHTML() → HtmlFormDataExtractor::extract() → $data['Tpl']['field']
	 *
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHTMLWithSourceIsPagePreloadsTextFieldValueFromExistingPageContent(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestPreloadTpl01}}}\n"
			. "{{{field|Country}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = '{{PFTestPreloadTpl01|Country=DE}}';

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = false,
			$source_is_page = true,
			$form_id = null,
			$pageContent,
			$page_name = 'PFTestPreloadPage01',
			$page_name_formula = null,
			$is_query = false, $is_embedded = false, $is_autocreate = false,
			$autocreate_query = [],
			self::getTestUser()->getUser()
		);

		$mOptions = [];
		$data = HtmlFormDataExtractor::extract( $formHtml, $mOptions );

		$this->assertArrayHasKey( 'PFTestPreloadTpl01', $data,
			'formHTML() with source_is_page=true must produce inputs for the template' );
		$this->assertSame( 'DE', $data['PFTestPreloadTpl01']['Country'],
			'The field value from the existing page must survive the HTML round-trip unchanged' );
	}

	// -------------------------------------------------------------------------

	/**
	 * preparePreloadData() must return the same field values as the
	 * formHTML() + HtmlFormDataExtractor::extract() round-trip.
	 *
	 * @covers \PFFormPrinter::preparePreloadData
	 */
	public function testPreparePreloadDataReturnsFieldValuesFromExistingPageContent(): void {
		global $wgPageFormsFormPrinter;

		$formDef = "{{{for template|PFTestPreloadTpl02}}}\n"
			. "{{{field|Country}}}\n"
			. "{{{field|City}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = '{{PFTestPreloadTpl02|Country=DE|City=Berlin}}';

		$data = $wgPageFormsFormPrinter->preparePreloadData( $formDef, $pageContent );

		$this->assertArrayHasKey( 'PFTestPreloadTpl02', $data );
		$this->assertSame( 'DE', $data['PFTestPreloadTpl02']['Country'] );
		$this->assertSame( 'Berlin', $data['PFTestPreloadTpl02']['City'] );
	}

	/**
	 * preparePreloadData() must return no template fields when the page does
	 * not call the template defined in the form, but must still return the
	 * page content as pf_free_text.
	 *
	 * @covers \PFFormPrinter::preparePreloadData
	 */
	public function testPreparePreloadDataReturnsFreeTextWhenTemplateAbsentFromPage(): void {
		global $wgPageFormsFormPrinter;

		$formDef = "{{{for template|PFTestPreloadTpl03}}}\n"
			. "{{{field|Country}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = 'Some free text without any template call.';

		$data = $wgPageFormsFormPrinter->preparePreloadData( $formDef, $pageContent );

		$this->assertArrayNotHasKey( 'PFTestPreloadTpl03', $data );
		$this->assertSame( 'Some free text without any template call.', $data['pf_free_text'] );
	}

	/**
	 * preparePreloadData() must include pf_free_text with the remaining page
	 * content after all template text has been stripped out.
	 * This ensures that autoedit SAVE does not silently delete the free text
	 * section of an existing page.
	 *
	 * @covers \PFFormPrinter::preparePreloadData
	 */
	public function testPreparePreloadDataIncludesFreeTextAfterTemplateContent(): void {
		global $wgPageFormsFormPrinter;

		$formDef = "{{{for template|PFTestPreloadTpl05}}}\n"
			. "{{{field|Title}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|free text}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = "{{PFTestPreloadTpl05|Title=Introduction}}\n\nThis is the free text body.";

		$data = $wgPageFormsFormPrinter->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'Introduction', $data['PFTestPreloadTpl05']['Title'] );
		$this->assertSame( 'This is the free text body.', $data['pf_free_text'] );
	}

	// -------------------------------------------------------------------------
	// formHTML() – ParserOutput returned as 5th element (issue #15)
	// -------------------------------------------------------------------------

	/**
	 * formHTML() must return a ParserOutput as its 5th element with no
	 * unexpected modules when the form contains no parser-tag hooks.
	 *
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHTMLReturnsParserOutputWithNoSpuriousModules(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestModTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ , , , , $parserOutput ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null, 'PFTestModPage01',
			null, false, false, false, [], self::getTestUser()->getUser()
		);

		$this->assertInstanceOf( \ParserOutput::class, $parserOutput );
		$pfModules = array_filter(
			$parserOutput->getModules(),
			static fn ( string $m ) => str_starts_with( $m, 'ext.pageforms.' )
		);
		$this->assertSame(
			[],
			array_values( $pfModules ),
			'formHTML() must not return unexpected ResourceLoader modules in the '
			. 'ParserOutput when the form definition contains no parser-tag hooks.'
		);
	}

	/**
	 * formHTML() must return a ParserOutput as its 5th element containing any
	 * ResourceLoader modules registered by parser tag hooks during rendering.
	 * The caller (e.g. PFAutoeditAPI) uses addParserOutputMetadata() to forward
	 * these to the real OutputPage.
	 *
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHTMLReturnsParserOutputWithParserTagModules(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		MediaWikiServices::getInstance()->getHookContainer()->register(
			'ParserFirstCallInit',
			static function ( \Parser $parser ) {
				$parser->setHook( 'pf-test-module-tag', static function (
					$input, array $args, \Parser $p
				) {
					$p->getOutput()->addModules( [ 'ext.pageforms.test.sentinel' ] );
					return '';
				} );
			}
		);

		$formDef = "<pf-test-module-tag />\n{{{standard input|save}}}";

		[ , , , , $parserOutput ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null, 'PFTestModPage02',
			null, false, false, false, [], self::getTestUser()->getUser()
		);

		$this->assertInstanceOf( \ParserOutput::class, $parserOutput );
		$this->assertContains(
			'ext.pageforms.test.sentinel',
			$parserOutput->getModules(),
			'formHTML() must return the internal ParserOutput as its 5th element so '
			. 'callers can forward parser-tag-registered RL modules to OutputPage via '
			. 'addParserOutputMetadata().'
		);
	}

	// -------------------------------------------------------------------------
	// for-template / field loop — #90
	// -------------------------------------------------------------------------

	public function testFormHTMLFieldTagRendersTextInput(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		// A text input for the field must appear in the output
		$this->assertStringContainsString( 'PFTestFieldTpl01', $formHtml );
		$this->assertStringContainsString( 'input', strtolower( $formHtml ) );
	}

	public function testFormHTMLFieldTagWithDefaultValue(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl02}}}\n"
			. "{{{field|Status|default=active}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage02', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'active', $formHtml );
	}

	public function testFormHTMLWithFormSubmittedProcessesFieldValues(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl03}}}\n"
			. "{{{field|Title}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		// Simulate submitted form values via FauxRequest on the global context
		$fauxRequest = new \FauxRequest( [ 'PFTestFieldTpl03[Title]' => 'My Submitted Value' ], true );
		\RequestContext::getMain()->setRequest( $fauxRequest );

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestFieldPage03', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'PFTestFieldTpl03', $formHtml );

		// Restore default request
		\RequestContext::getMain()->setRequest( new \FauxRequest() );
	}

	public function testFormHTMLWithHiddenField(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl04}}}\n"
			. "{{{field|HiddenField|hidden}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage04', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'type="hidden"', $formHtml );
	}

	public function testFormHTMLWithMandatoryField(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl05}}}\n"
			. "{{{field|RequiredField|mandatory}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage05', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'mandatory', $formHtml );
	}

	public function testFormHTMLWithMultipleFieldsProducesTemplateCallInPageText(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl06}}}\n"
			. "{{{field|FirstName}}}\n"
			. "{{{field|LastName}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		// FauxRequest is pre-parsed: PFTemplateInForm::setFieldValuesFromSubmit reads
		// $request->getArray($templateName), so fields go under the template name key.
		$fauxRequest = new \FauxRequest(
			[
				'PFTestFieldTpl06' => [
					'FirstName' => 'Ada',
					'LastName' => 'Lovelace',
				],
			],
			true
		);
		\RequestContext::getMain()->setRequest( $fauxRequest );

		[ $formHtml, $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestFieldPage06', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		\RequestContext::getMain()->setRequest( new \FauxRequest() );

		$this->assertStringContainsString( 'PFTestFieldTpl06', $formHtml );
		$this->assertStringContainsString( '{{PFTestFieldTpl06', $pageText );
		$this->assertStringContainsString( 'FirstName=Ada', $pageText );
		$this->assertStringContainsString( 'LastName=Lovelace', $pageText );
	}

	public function testFormHTMLInfoTagSetsCreateTitle(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{info|create title=My New Page Title}}}\n"
			. "{{{for template|PFTestFieldTpl07}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ , , $formPageTitle ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage07', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertSame( 'My New Page Title', $formPageTitle );
	}

	public function testFormHTMLInfoTagReplacedWithHiddenSpan(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{info|create title=Ignored}}}\n"
			. "{{{for template|PFTestFieldTpl08}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage08', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( '<span style="visibility: hidden;"></span>', $formHtml );
		$this->assertStringNotContainsString( '{{{info', $formHtml );
	}

	public function testFormHTMLFreeTextStandardInputRendersTextarea(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl09}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|free text}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage09', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'pf_free_text', $formHtml );
		$this->assertStringContainsString( 'textarea', strtolower( $formHtml ) );
	}

	public function testFormHTMLPageNameFormulaSubstitutedFromFieldValue(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl10}}}\n"
			. "{{{field|Title}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		// The formula must use the full input name <TemplateName[FieldName]>,
		// which is what formHTML() matches against during field loop substitution.
		$fauxRequest = new \FauxRequest(
			[ 'PFTestFieldTpl10' => [ 'Title' => 'MyGeneratedPage' ] ],
			true
		);
		\RequestContext::getMain()->setRequest( $fauxRequest );

		[ , , , $generatedPageName ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			null, '<PFTestFieldTpl10[Title]>', false, false, false, [],
			self::getTestUser()->getUser()
		);

		\RequestContext::getMain()->setRequest( new \FauxRequest() );

		$this->assertSame( 'MyGeneratedPage', $generatedPageName );
	}

	public function testFormHTMLUnknownTagTypeDoesNotCrash(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		// Curly braces are not HTML special characters, so the escaped output
		// still contains the tag text literally. The contract is: no crash.
		$formDef = "{{{unknown tag type|some value}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage11', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertNotEmpty( $formHtml );
		$this->assertStringContainsString( 'unknown tag type', $formHtml );
	}

	public function testFormHTMLEndTemplateWithExtraParamThrowsMWException(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl12}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template|extra}}}\n"
			. "{{{standard input|save}}}";

		$this->expectException( \MWException::class );
		$wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage12', null, false, false, false, [],
			self::getTestUser()->getUser()
		);
	}

	public function testFormHTMLForbiddenCharactersInFieldTagThrowsMWException(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl13}}}\n"
			. "{{{field|Name|bad=<script>alert(1)</script>}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$this->expectException( \MWException::class );
		$wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFieldPage13', null, false, false, false, [],
			self::getTestUser()->getUser()
		);
	}

	public function testFormHTMLFreeTextFromRequestPopulatesPageText(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestFieldTpl14}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$fauxRequest = new \FauxRequest(
			[ 'pf_free_text' => 'Some free body text' ],
			true
		);
		\RequestContext::getMain()->setRequest( $fauxRequest );

		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestFieldPage14', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		\RequestContext::getMain()->setRequest( new \FauxRequest() );

		$this->assertStringContainsString( 'Some free body text', $pageText );
	}

	// -------------------------------------------------------------------------
	// Delegation methods — #89
	// -------------------------------------------------------------------------

	public function testGetInputTypeReturnsClassForRegisteredType(): void {
		global $wgPageFormsFormPrinter;
		$class = $wgPageFormsFormPrinter->getInputType( 'text' );
		$this->assertSame( 'PFTextInput', $class );
	}

	public function testGetInputTypeReturnsNullForUnknownType(): void {
		global $wgPageFormsFormPrinter;
		$class = $wgPageFormsFormPrinter->getInputType( 'nonexistent_xyz' );
		$this->assertNull( $class );
	}

	public function testGetAllInputTypesIncludesKnownTypes(): void {
		global $wgPageFormsFormPrinter;
		$types = $wgPageFormsFormPrinter->getAllInputTypes();
		$this->assertContains( 'text', $types );
		$this->assertContains( 'textarea', $types );
		$this->assertContains( 'checkbox', $types );
	}

	public function testGetDefaultInputTypeSMWReturnsSomethingForStringType(): void {
		global $wgPageFormsFormPrinter;
		// SMW type _str (plain string) with isList=false → should return PFTextInput or similar
		$result = $wgPageFormsFormPrinter->getDefaultInputTypeSMW( false, '' );
		// When there is no hook for this type, null is acceptable
		$this->assertTrue( $result === null || is_string( $result ) );
	}

	public function testGetPossibleInputTypesSMWReturnsArray(): void {
		global $wgPageFormsFormPrinter;
		$result = $wgPageFormsFormPrinter->getPossibleInputTypesSMW( false, '' );
		$this->assertIsArray( $result );
	}

	public function testStrReplaceFirstDelegates(): void {
		global $wgPageFormsFormPrinter;
		$result = $wgPageFormsFormPrinter->strReplaceFirst( 'a', 'X', 'a b a' );
		$this->assertSame( 'X b a', $result );
	}

	public function testPlaceholderFormat(): void {
		$result = PFFormPrinter::placeholderFormat( 'MyTemplate', 'MyField' );
		$this->assertStringContainsString( 'MyTemplate', $result );
		$this->assertStringContainsString( 'MyField', $result );
	}

	public function testMakePlaceholderInFormHTML(): void {
		$placeholder = PFFormPrinter::placeholderFormat( 'T', 'F' );
		$html = PFFormPrinter::makePlaceholderInFormHTML( $placeholder );
		$this->assertNotEmpty( $html );
		// The HTML marker must embed the placeholder string somewhere
		$this->assertStringContainsString( 'T', $html );
	}

	// -------------------------------------------------------------------------
	// Malformed tag guards — issue #23
	// -------------------------------------------------------------------------

	/**
	 * A bare {{{for template}}} tag (no template name) must throw an MWException
	 * with an actionable error message so form authors can diagnose the mistake.
	 *
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHTMLForTemplateTagWithoutNameThrowsMWException(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$this->expectException( \MWException::class );
		$this->expectExceptionMessageMatches( "/'for template' tag is missing the template name/" );
		$wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMalformedPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);
	}

	/**
	 * A bare {{{field}}} tag (no field name) must throw an MWException with an
	 * actionable error message so form authors can diagnose the mistake.
	 *
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHTMLFieldTagWithoutNameThrowsMWException(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestMalformedTpl02}}}\n"
			. "{{{field}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$this->expectException( \MWException::class );
		$this->expectExceptionMessageMatches( "/'field' tag is missing the field name/" );
		$wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMalformedPage02', null, false, false, false, [],
			self::getTestUser()->getUser()
		);
	}

	/**
	 * A bare {{{standard input}}} tag (no input name) must throw an MWException
	 * with an actionable error message so form authors can diagnose the mistake.
	 *
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHTMLStandardInputTagWithoutNameThrowsMWException(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestMalformedTpl03}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input}}}\n"
			. "{{{standard input|save}}}";

		$this->expectException( \MWException::class );
		$this->expectExceptionMessageMatches( "/'standard input' tag is missing the input name/" );
		$wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMalformedPage03', null, false, false, false, [],
			self::getTestUser()->getUser()
		);
	}

	// -------------------------------------------------------------------------
	// Regression: TypeError when list field with mapping template has >1 values
	// -------------------------------------------------------------------------

	/**
	 * When a list field uses `mapping template` and the existing page contains
	 * multiple delimited values, formHTML() must not throw a TypeError.
	 *
	 * Previously, valueStringToLabels() returned an array for multi-value lists,
	 * and that array was passed directly to FormFieldHtmlBuilder::formFieldHTML()
	 * which declares $cur_value as ?string — triggering a TypeError.
	 *
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHTMLListFieldWithMappingTemplateAndMultipleValuesDoesNotThrow(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$mappingTemplate = 'PFTestMappingTplMulti01';
		$this->editPage( "Template:$mappingTemplate", 'PFTestLabel' );

		$formDef = "{{{for template|PFTestMultiTpl01}}}\n"
			. "{{{field|Tags|list|delimiter=,|mapping template=$mappingTemplate}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = '{{PFTestMultiTpl01|Tags=alpha, beta}}';

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = false,
			$source_is_page = true,
			$form_id = null,
			$pageContent,
			$page_name = 'PFTestMultiPage01',
			$page_name_formula = null,
			$is_query = false, $is_embedded = false, $is_autocreate = false,
			$autocreate_query = [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'PFTestMultiTpl01', $formHtml );
	}

	// -------------------------------------------------------------------------

	/**
	 * Returns a mock Title for test
	 * @return Title
	 */
	private function getTitle() {
		$mockTitle = $this->getMockBuilder( 'Title' )
			->disableOriginalConstructor()
			->getMock();

		$mockTitle->expects( $this->any() )
			->method( 'getDBkey' )
			->willReturn( 'Sometitle' );

		$mockTitle->expects( $this->any() )
			->method( 'getNamespace' )
			->willReturn( PF_NS_FORM );

		return $mockTitle;
	}

}
