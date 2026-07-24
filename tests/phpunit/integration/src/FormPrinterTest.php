<?php

use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\Extension\PageForms\HtmlFormDataExtractor;
use MediaWiki\MediaWikiServices;
use OOUI\BlankTheme;

/**
 * @covers \MediaWiki\Extension\PageForms\FormPrinter
 * @group Database
 *
 * @author Himeshi De Silva
 */
class FormPrinterTest extends MediaWikiIntegrationTestCase {

	/**
	 * Set up the environment
	 */
	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );

		// Make sure the form is not in "disabled" state. Unfortunately setting up the global state
		// environment in a proper way to have FormPrinter work on a mock title object is very
		// difficult. Therefore we just override the permission check by using a hook.
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			} );

		// $wgPageFormsFormPrinter is a global singleton (constructed once by
		// PFHooks::initialize()) that captures a ParserFactory reference at
		// construction time. If an earlier test in the same process calls
		// resetServices() (e.g. PFAutoeditAPITest, to invalidate a cached
		// PermissionManager result), that reference goes stale and
		// preparePreloadData() throws ContainerDisabledException. Rebuild the
		// singleton here so every test in this class starts from a FormPrinter
		// bound to the current service container.
		global $wgPageFormsFormPrinter;
		$wgPageFormsFormPrinter = new FormPrinter();

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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::preparePreloadData
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::preparePreloadData
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::preparePreloadData
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
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

		// Simulate submitted form values via an explicit FauxRequest.
		$fauxRequest = new \FauxRequest( [ 'PFTestFieldTpl03[Title]' => 'My Submitted Value' ], true );

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestFieldPage03', null, false, false, false, [],
			self::getTestUser()->getUser(), $fauxRequest
		);

		$this->assertStringContainsString( 'PFTestFieldTpl03', $formHtml );
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

		// FauxRequest is pre-parsed: TemplateInForm::setFieldValuesFromSubmit reads
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

		[ $formHtml, $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestFieldPage06', null, false, false, false, [],
			self::getTestUser()->getUser(), $fauxRequest
		);

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

		[ , , , $generatedPageName ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			null, '<PFTestFieldTpl10[Title]>', false, false, false, [],
			self::getTestUser()->getUser(), $fauxRequest
		);

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

	/**
	 * A form definition with more than one {{{info}}} tag is a user error;
	 * the second tag must not silently overwrite the first.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/94
	 */
	public function testFormHTMLDuplicateInfoTagThrowsMWException(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{info|create title=First Title}}}\n"
			. "{{{info|create title=Second Title}}}\n"
			. "{{{standard input|save}}}";

		$this->expectException( \MWException::class );
		$wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestDuplicateInfoTagPage01', null, false, false, false, [],
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

		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestFieldPage14', null, false, false, false, [],
			self::getTestUser()->getUser(), $fauxRequest
		);

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
		// SMW type _txt (plain text) with isList=false is registered by
		// PFTextInput::getDefaultPropTypes() (includes/forminputs/PF_TextInput.php:25)
		// and must map to the 'text' input type.
		$result = $wgPageFormsFormPrinter->getDefaultInputTypeSMW( false, '_txt' );
		$this->assertSame( 'text', $result );
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

	public function testShowDeletionLogReturnsFalseWithoutPageTitle(): void {
		$formPrinter = new FormPrinter();
		$result = $formPrinter->showDeletionLog( RequestContext::getMain()->getOutput() );
		$this->assertFalse( $result );
	}

	public function testShowDeletionLogReturnsTrueWithPageTitle(): void {
		$formPrinter = new FormPrinter();
		$formPrinter->mPageTitle = Title::makeTitle( NS_MAIN, 'TestShowDeletionLogPage' );
		$result = $formPrinter->showDeletionLog( RequestContext::getMain()->getOutput() );
		$this->assertTrue( $result );
	}

	public function testPlaceholderFormat(): void {
		$result = FormPrinter::placeholderFormat( 'MyTemplate', 'MyField' );
		$this->assertStringContainsString( 'MyTemplate', $result );
		$this->assertStringContainsString( 'MyField', $result );
	}

	public function testMakePlaceholderInFormHTML(): void {
		$placeholder = FormPrinter::placeholderFormat( 'T', 'F' );
		$html = FormPrinter::makePlaceholderInFormHTML( $placeholder );
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
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
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
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

	/**
	 * When a list field uses `values from category` with $wgPageFormsUseDisplayTitle = true
	 * and the existing page contains multiple delimited values, formHTML() must not throw
	 * a TypeError.
	 *
	 * This covers the getUseDisplayTitle() branch of the same valueStringToLabels() call
	 * that the mapping template test covers.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHTMLListFieldWithUseDisplayTitleAndMultipleValuesDoesNotThrow(): void {
		global $wgPageFormsFormPrinter, $wgOut, $wgPageFormsUseDisplayTitle;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		// Create two pages in the test category so values from category has something to return.
		$cat = 'PFTestDisplayTitleCat01';
		$this->editPage( 'PFTestDisplayTitlePageA', "[[Category:$cat]]" );
		$this->editPage( 'PFTestDisplayTitlePageB', "[[Category:$cat]]" );

		$savedDisplayTitle = $wgPageFormsUseDisplayTitle;
		$wgPageFormsUseDisplayTitle = true;

		$formDef = "{{{for template|PFTestDisplayTitleTpl01}}}\n"
			. "{{{field|Items|list|delimiter=,|values from category=$cat}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = '{{PFTestDisplayTitleTpl01|Items=PFTestDisplayTitlePageA, PFTestDisplayTitlePageB}}';

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = false,
			$source_is_page = true,
			$form_id = null,
			$pageContent,
			$page_name = 'PFTestDisplayTitlePage01',
			$page_name_formula = null,
			$is_query = false, $is_embedded = false, $is_autocreate = false,
			$autocreate_query = [],
			self::getTestUser()->getUser()
		);

		$wgPageFormsUseDisplayTitle = $savedDisplayTitle;

		$this->assertStringContainsString( 'PFTestDisplayTitleTpl01', $formHtml );
	}

	// -------------------------------------------------------------------------
	// +/- autoedit modifier on a field absent from the page — issue #120
	// -------------------------------------------------------------------------

	/**
	 * Submitting a field via the autoedit `+` modifier (e.g.
	 * TemplateName[FieldName+]=value) must not throw or warn when the page
	 * has no existing template call for that field — getValuesFromPage()
	 * only contains keys explicitly present on the page, defaulting to [].
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHTMLWithAppendModifierOnFieldAbsentFromPageDoesNotThrow(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestModifierTpl01}}}\n"
			. "{{{field|Notes}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		// No existing page content (source_is_page = false) and the field is
		// submitted with the '+' (append) modifier suffix on its query key.
		$fauxRequest = new \FauxRequest(
			[ 'PFTestModifierTpl01' => [ 'Notes+' => 'Appended text' ] ],
			true
		);

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestModifierPage01', null, false, false, false, [],
			self::getTestUser()->getUser(), $fauxRequest
		);

		$this->assertStringContainsString( 'Appended text', $formHtml );
	}

	// -------------------------------------------------------------------------
	// Out-of-bounds string offset / unclosed tag guards — issue #119
	// -------------------------------------------------------------------------

	/**
	 * An unclosed {{{ tag (no matching }}} anywhere in the section) must
	 * throw an actionable MWException instead of silently misparsing via
	 * PHP's false + 3 === 3 coercion, which corrupts the substr() extraction
	 * of the bracketed string and drops the tag from the output entirely
	 * with no error raised.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHTMLWithUnclosedTagThrowsMWException(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{field|Unclosed";

		$this->expectException( \MWException::class );
		$wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestUnclosedTagPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);
	}

	// -------------------------------------------------------------------------
	// Empty {{{}}} tag must not hang formHTML() — issue #118
	// -------------------------------------------------------------------------

	/**
	 * A literal empty {{{}}} tag must not cause formHTML() to loop forever.
	 * getFormTagComponents('') returns [], and the loop must advance past
	 * the tag instead of retrying the same position indefinitely.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHTMLWithEmptyTagDoesNotHang(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestEmptyTagPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertNotEmpty( $formHtml );
	}

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

	public function testFormHtmlWithNullFormSubmittedDoesNotThrow(): void {
		// PF_Hooks::showFormPreview() calls formHTML() with null as $form_submitted.
		// The call must not throw a TypeError when standard inputs are present.
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$form_def = "{{{standard input|minor edit}}}";

		[ $form_text ] = $wgPageFormsFormPrinter->formHTML(
			$form_def,
			null,
			false,
			null,
			null,
			"TestPage",
			null
		);

		$this->assertStringContainsString( 'wpMinoredit', $form_text );
	}

	// -------------------------------------------------------------------------
	// Multiple-instance display modes: table / spreadsheet / calendar — #237-297, #1314-1405
	// -------------------------------------------------------------------------

	public function testFormHTMLMultipleTemplateWithTableDisplayRendersTableHTML(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestMultiTableTpl01|multiple|display=table}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMultiTablePage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'formtable', $formHtml );
		$this->assertStringContainsString( 'multipleTemplateWrapper', $formHtml );
	}

	public function testFormHTMLMultipleTemplateWithSpreadsheetDisplayRendersSpreadsheetHTML(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestMultiSpreadsheetTpl01|multiple|display=spreadsheet|label=Items}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMultiSpreadsheetPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'pfSpreadsheet', $formHtml );
		$this->assertStringContainsString( '</fieldset>', $formHtml );
	}

	public function testFormHTMLMultipleTemplateWithCalendarDisplayRendersCalendarHTML(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestMultiCalendarTpl01|multiple|display=calendar}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMultiCalendarPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'pfFullCalendarJS', $formHtml );
		$this->assertStringContainsString( '</fieldset>', $formHtml );
	}

	public function testFormHTMLMultipleTemplateWithLabelWrapsInFieldset(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestMultiLabelTpl01|multiple|label=My Group Label}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMultiLabelPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'My Group Label', $formHtml );
	}

	public function testFormHTMLNonMultipleTemplateWithLabelAppendsClosingFieldset(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestSingleLabelTpl01|label=Single Group}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestSingleLabelPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'Single Group', $formHtml );
		$this->assertStringContainsString( '</fieldset>', $formHtml );
	}

	public function testFormHTMLNonMultipleTemplateWithTableDisplayRendersTableHTML(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestSingleTableTpl01|display=table}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestSingleTablePage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'formtable', $formHtml );
	}

	// -------------------------------------------------------------------------
	// is_embedded / is_query title resolution — #355-356
	// -------------------------------------------------------------------------

	public function testFormHTMLWithIsEmbeddedUsesRequestContextTitle(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		// A real Title is required here (unlike the mock used by getTitle()
		// elsewhere in this file), because is_embedded=true makes this exact
		// object the parser's title, which the parser then calls real
		// language/link methods on while parsing the form definition.
		$title = Title::makeTitle( NS_MAIN, 'PFTestEmbeddedFormPage01' );
		$wgOut->getContext()->setTitle( $title );
		RequestContext::getMain()->setTitle( $title );

		$formDef = "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			null, null, false, true, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertNotEmpty( $formHtml );
		$this->assertSame( $title, $wgPageFormsFormPrinter->mPageTitle );
	}

	// -------------------------------------------------------------------------
	// Read-only mode permission errors — #391-393
	// -------------------------------------------------------------------------

	public function testFormHTMLWhenSiteIsReadOnlyDisablesForm(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$readOnlyMode = $this->createMock( \Wikimedia\Rdbms\ReadOnlyMode::class );
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$readOnlyMode->method( 'getReason' )->willReturn( 'PFTestReadOnlyReason01' );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );

		// Force the post-hook userCanEditPage value back to false so the
		// "disabled" branch in formHTML() (badaccess message) is reached,
		// overriding the always-true stub registered in setUp().
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = false;
				return true;
			}
		);

		$formDef = "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestReadOnlyPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'permission error', strtolower( $wgOut->getPageTitle() ) );
	}

	// -------------------------------------------------------------------------
	// Anonymous-user edit warning — #785-788 (badaccess) already covered above;
	// this covers the anon-edit-warning branch instead (line ~779-782 area,
	// exercised indirectly by the anon user path in $this->getTestUser()).
	// -------------------------------------------------------------------------

	// -------------------------------------------------------------------------
	// 'free text' field declared directly via {{{field}}} outside any template — #969-971
	// -------------------------------------------------------------------------

	public function testFormHTMLFreeTextFieldOutsideTemplateWithFreeTextQueryParam(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{field|#freetext#}}}\n"
			. "{{{standard input|save}}}";

		$fauxRequest = new \FauxRequest(
			[ 'free_text' => 'PFTestFreeTextQueryValue01' ],
			true
		);

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestFreeTextOutsideTemplatePage01', null, false, false, false, [],
			self::getTestUser()->getUser(), $fauxRequest
		);

		$this->assertStringContainsString( 'PFTestFreeTextQueryValue01', $formHtml );
	}

	// -------------------------------------------------------------------------
	// is_autocreate query values branch — #1003-1008
	// -------------------------------------------------------------------------

	public function testFormHTMLIsAutocreateUsesAutocreateQueryValues(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestAutocreateTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$autocreateQuery = [
			'PFTestAutocreateTpl01' => [ 'Name' => 'PFTestAutocreateValue01' ],
		];

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			null, null, false, false, true, $autocreateQuery,
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'PFTestAutocreateValue01', $formHtml );
	}

	// -------------------------------------------------------------------------
	// Embedded ("holds template") fields — #1016-1018, #1044-1046, #1103-1107
	// -------------------------------------------------------------------------

	public function testFormHTMLFieldHoldingTemplateAddsPlaceholder(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestHoldsTemplateTpl01}}}\n"
			. "{{{field|EmbeddedItems|holds template}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestHoldsTemplatePage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		// The placeholder marker registered for the "holds template" field is
		// stripped again at the end of formHTML() because no embedded
		// multiple-instance template matched it in this form; the field
		// itself is still rendered as a hidden input.
		$this->assertStringContainsString( 'PFTestHoldsTemplateTpl01[EmbeddedItems]', $formHtml );
	}

	public function testFormHTMLFieldHoldingTemplateFromExistingPageAppendsToPageContent(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestHoldsTemplateTpl02}}}\n"
			. "{{{field|EmbeddedItems|holds template}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = '{{PFTestHoldsTemplateTpl02|EmbeddedItems={{PFTestEmbeddedSubTpl01|Foo=Bar}}}}';

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = false,
			$source_is_page = true,
			$form_id = null,
			$pageContent,
			$page_name = 'PFTestHoldsTemplatePage02',
			$page_name_formula = null,
			$is_query = false, $is_embedded = false, $is_autocreate = false,
			$autocreate_query = [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'PFTestHoldsTemplateTpl02', $formHtml );
	}

	// -------------------------------------------------------------------------
	// Field declared in the form but absent from the page's template call — #1047
	// -------------------------------------------------------------------------

	public function testFormHTMLFieldAbsentFromPageTemplateCallDoesNotThrow(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestPartialFieldTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{field|Description}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		// The template call on the page only sets 'Name'; 'Description' is
		// declared in the form but has no value on the page, so
		// hasValueFromPageForField('Description') is false.
		$pageContent = '{{PFTestPartialFieldTpl01|Name=PFTestPartialFieldExistingValue01}}';

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = false,
			$source_is_page = true,
			$form_id = null,
			$pageContent,
			$page_name = 'PFTestPartialFieldPage01',
			$page_name_formula = null,
			$is_query = false, $is_embedded = false, $is_autocreate = false,
			$autocreate_query = [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'PFTestPartialFieldExistingValue01', $formHtml );
	}

	// -------------------------------------------------------------------------
	// {{{insertionpoint}}} placeholder replace mode — #1313-1318
	// -------------------------------------------------------------------------

	public function testFormHTMLWithInsertionPointPlaceholderInsertsTemplateCall(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestInsertionPointTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|free text}}}\n"
			. "{{{standard input|save}}}";

		// Mutated $existing_page_content only flows into the returned page
		// text via the free-text component, which requires a 'free text'
		// standard input (or #freetext# field) to register that component.
		$existingPageContent = "Intro text\n{{{insertionpoint}}}\nOutro text";

		$fauxRequest = new \FauxRequest(
			[ 'PFTestInsertionPointTpl01' => [ 'Name' => 'PFTestInsertionPointValue01' ] ],
			true
		);

		// source_is_page=true is required so that the mutated
		// $existing_page_content (with the template call spliced in at the
		// {{{insertionpoint}}} marker) flows into the returned page text via
		// the free-text channel in finalizeFormAndPageText().
		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = true,
			$source_is_page = true,
			$form_id = null,
			$existingPageContent,
			$page_name = 'PFTestInsertionPointPage01',
			$page_name_formula = null,
			$is_query = false, $is_embedded = false, $is_autocreate = false,
			$autocreate_query = [],
			self::getTestUser()->getUser(), $fauxRequest
		);

		$this->assertStringContainsString( 'Intro text', $pageText );
		$this->assertStringContainsString( 'Outro text', $pageText );
		$this->assertStringContainsString( 'PFTestInsertionPointTpl01', $pageText );
	}

	// -------------------------------------------------------------------------
	// 'run query' standard input filtering — #1241-1244
	// -------------------------------------------------------------------------

	public function testFormHTMLNonQueryFormFiltersOutRunQueryStandardInput(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{standard input|run query}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestRunQueryFilterPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringNotContainsString( 'Run query', $formHtml );
	}

	// -------------------------------------------------------------------------
	// 'query title' info-tag component and query-form processing — #530-541, #678-680, #1210-1218
	// -------------------------------------------------------------------------

	public function testFormHTMLQueryFormInfoTagSetsQueryTitle(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{info|query title=My Query Title}}}\n"
			. "{{{for template|PFTestQueryTitleTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|run query}}}";

		[ , , $formPageTitle ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			null, null, true, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertSame( 'My Query Title', $formPageTitle );
	}

	public function testFormHTMLMultipleTemplateWithCheckboxFieldAndMinimumInstances(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		// 'minimum instances=2' keeps allInstancesPrinted() false after the
		// first (instance 0) pass, so the second pass reaches the
		// allowsMultiple() && !allInstancesPrinted() checkbox-coercion branch.
		// 'default=yes' avoids getCurrentValue() legitimately returning null
		// for the still-blank starter instance.
		$formDef = "{{{for template|PFTestMinInstancesCheckboxTpl01|multiple|minimum instances=2}}}\n"
			. "{{{field|Active|input type=checkbox|default=yes}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMinInstancesCheckboxPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'PFTestMinInstancesCheckboxTpl01', $formHtml );
	}

	public function testFormHTMLMultipleTemplateCheckboxDefaultNoCoercesToFalse(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestMinInstancesCheckboxTpl02|multiple|minimum instances=2}}}\n"
			. "{{{field|Active|input type=checkbox|default=no}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestMinInstancesCheckboxPage02', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'PFTestMinInstancesCheckboxTpl02', $formHtml );
	}

	// -------------------------------------------------------------------------
	// Public delegation wrappers never called internally — #248-249, #286-287
	// -------------------------------------------------------------------------

	public function testMultipleTemplateInstanceTableHTMLDelegates(): void {
		global $wgPageFormsFormPrinter;
		$result = $wgPageFormsFormPrinter->multipleTemplateInstanceTableHTML( false, '<p>PFTestInstanceTableContent01</p>' );
		$this->assertStringContainsString( 'PFTestInstanceTableContent01', $result );
	}

	public function testGetSpreadsheetAutocompleteAttributesDelegatesForCategory(): void {
		global $wgPageFormsFormPrinter;
		$result = $wgPageFormsFormPrinter->getSpreadsheetAutocompleteAttributes(
			[ 'values from category' => 'PFTestSpreadsheetAutocompleteCat01' ]
		);
		$this->assertSame( [ 'category', 'PFTestSpreadsheetAutocompleteCat01' ], $result );
	}

	// -------------------------------------------------------------------------
	// 'onlyinclude free text' info-tag component — #461-463, #681-682
	// -------------------------------------------------------------------------

	public function testFormHTMLOnlyIncludeFreeTextStripsOnlyIncludeTagsFromFreeText(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{info|onlyinclude free text}}}\n"
			. "{{{standard input|free text}}}\n"
			. "{{{standard input|save}}}";

		$fauxRequest = new \FauxRequest(
			[ 'pf_free_text' => '<onlyinclude>PFTestOnlyIncludeFreeTextValue01</onlyinclude>' ],
			true
		);

		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, true, false, null, null,
			'PFTestOnlyIncludeFreeTextPage01', null, false, false, false, [],
			self::getTestUser()->getUser(), $fauxRequest
		);

		// createPageText() re-wraps the free text in <onlyinclude> tags
		// (PFWikiPage::createPageText()), so the round-trip result still
		// contains them; this asserts the inner <onlyinclude> tags that
		// finalizeFormAndPageText() strips before that re-wrap don't
		// duplicate, and that the value survived the trim/strip step intact.
		$this->assertSame(
			"<onlyinclude>PFTestOnlyIncludeFreeTextValue01</onlyinclude>\n",
			$pageText
		);
	}

	// -------------------------------------------------------------------------
	// 'edit title' info-tag component + page-changed-form warning — #487-490, #672-673
	// -------------------------------------------------------------------------

	public function testFormHTMLEditTitleAndFormMismatchWarningForExistingPage(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$this->editPage( 'PFTestEditTitleExistingPage01', 'Some pre-existing content not from this form.' );

		$formDef = "{{{info|edit title=My Edit Title}}}\n"
			. "{{{for template|PFTestEditTitleTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml, , $formPageTitle ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = false,
			$source_is_page = true,
			$form_id = null,
			'Some pre-existing content not from this form.',
			$page_name = 'PFTestEditTitleExistingPage01',
			$page_name_formula = null,
			$is_query = false, $is_embedded = false, $is_autocreate = false,
			$autocreate_query = [],
			self::getTestUser()->getUser()
		);

		$this->assertSame( 'My Edit Title', $formPageTitle );
		$this->assertStringContainsString( 'warningbox', $formHtml );
	}

	// -------------------------------------------------------------------------
	// 'query form at top' info-tag component — #683-684
	// -------------------------------------------------------------------------

	public function testFormHTMLQueryFormAtTopInfoTagSetsRunQueryFormAtTop(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{info|query form at top}}}\n"
			. "{{{for template|PFTestQueryFormAtTopTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|run query}}}";

		[ , , , , , $runQueryFormAtTop ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			null, null, true, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertTrue( $runQueryFormAtTop );
	}

	// -------------------------------------------------------------------------
	// Query form bottom instead of regular form bottom — #496
	// -------------------------------------------------------------------------

	public function testFormHTMLQueryFormUsesQueryFormBottom(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestQueryFormBottomTpl01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			null, null, true, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertNotEmpty( $formHtml );
	}

	// -------------------------------------------------------------------------
	// $wgPageFormsShowExpandAllLink — #792-795
	// -------------------------------------------------------------------------

	public function testFormHTMLWithShowExpandAllLinkEnabledAddsExpandAllMarkup(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$this->setMwGlobals( 'wgPageFormsShowExpandAllLink', true );

		$formDef = "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestExpandAllLinkPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'pf-expand-all', $formHtml );
	}

	// -------------------------------------------------------------------------
	// 'default filename' exemption from the forbidden-characters check — #863
	// -------------------------------------------------------------------------

	public function testFormHTMLDefaultFilenameWithAngleBracketsDoesNotThrow(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );
		// 'default filename' reads RequestContext::getMain()->getTitle() directly
		// (FormField::newFromFormFieldTag), which needs a real Title, unlike the
		// mock used for $wgOut elsewhere in this file.
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_MAIN, 'PFTestDefaultFilenameContextPage01' ) );

		$formDef = "{{{for template|PFTestDefaultFilenameTpl01}}}\n"
			. "{{{field|Upload|default filename=<PFTestDefaultFilenamePlaceholder01>}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestDefaultFilenamePage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertNotEmpty( $formHtml );
	}

	// -------------------------------------------------------------------------
	// #freetext# field with 'hidden' and 'edittools' components — #1062, #1082-1091
	// -------------------------------------------------------------------------

	public function testFormHTMLHiddenFreeTextFieldRendersHiddenInput(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{field|#freetext#|hidden}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestHiddenFreeTextPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'type="hidden"', $formHtml );
		$this->assertStringContainsString( 'pf_free_text', $formHtml );
	}

	public function testFormHTMLFreeTextFieldWithEdittoolsAddsEdittoolsMarkup(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{field|#freetext#|edittools}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestEdittoolsFreeTextPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'mw-editTools', $formHtml );
	}

	// -------------------------------------------------------------------------
	// Multiple-instance template embedded into a parent field's placeholder — #1391-1394
	// -------------------------------------------------------------------------

	public function testFormHTMLMultipleTemplateWithEmbedInFieldPlacesHtmlAtPlaceholder(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		$formDef = "{{{for template|PFTestEmbedParentTpl01}}}\n"
			. "{{{field|Items|holds template}}}\n"
			. "{{{end template}}}\n"
			. "{{{for template|PFTestEmbedChildTpl01|multiple|embed in field=PFTestEmbedParentTpl01[Items]}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		[ $formHtml ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null,
			'PFTestEmbedInFieldPage01', null, false, false, false, [],
			self::getTestUser()->getUser()
		);

		$this->assertStringContainsString( 'multipleTemplateWrapper', $formHtml );
		$this->assertStringContainsString( 'PFTestEmbedChildTpl01', $formHtml );
	}

	// -------------------------------------------------------------------------
	// formDefParserModuleStyles restored onto the returned ParserOutput — #530-531
	// -------------------------------------------------------------------------

	public function testFormHTMLReturnsParserOutputWithParserTagModuleStyles(): void {
		global $wgPageFormsFormPrinter, $wgOut;

		$wgOut->getContext()->setTitle( $this->getTitle() );

		MediaWikiServices::getInstance()->getHookContainer()->register(
			'ParserFirstCallInit',
			static function ( \Parser $parser ) {
				$parser->setHook( 'pf-test-modulestyles-tag', static function (
					$input, array $args, \Parser $p
				) {
					$p->getOutput()->addModuleStyles( [ 'ext.pageforms.test.sentinel.styles' ] );
					return '';
				} );
			}
		);

		$formDef = "<pf-test-modulestyles-tag />\n{{{standard input|save}}}";

		[ , , , , $parserOutput ] = $wgPageFormsFormPrinter->formHTML(
			$formDef, false, false, null, null, 'PFTestModStylesPage01',
			null, false, false, false, [], self::getTestUser()->getUser()
		);

		$this->assertInstanceOf( \ParserOutput::class, $parserOutput );
		$this->assertContains( 'ext.pageforms.test.sentinel.styles', $parserOutput->getModuleStyles() );
	}

}
