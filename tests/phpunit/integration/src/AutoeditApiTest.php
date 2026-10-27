<?php

use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\PageForms\AutoeditApi;
use MediaWiki\Extension\PageForms\FormPrinterFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\PageForms\AutoeditApi
 * @group PF
 * @group Database
 * @group medium
 */
class AutoeditApiTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );

		// $wgPageFormsFormPrinter is a global singleton that captures services of the container it
		// was built in. Tests that run earlier in the same process can leave one behind from a
		// container that was reset since (ContainerDisabledException when a form is saved), so
		// every test here starts from a form printer bound to the current container.
		global $wgPageFormsFormPrinter;
		$wgPageFormsFormPrinter = FormPrinterFactory::create();
	}

	// -------------------------------------------------------------------------
	// makeRandomNumber
	// -------------------------------------------------------------------------

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::makeRandomNumber
	 */
	public function testMakeRandomNumberDefaultIsOneDigit(): void {
		$result = AutoeditApi::makeRandomNumber();
		$this->assertRegex( '/^\d$/', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::makeRandomNumber
	 */
	public function testMakeRandomNumberWithSixDigitsHasAtMostSixChars(): void {
		$result = AutoeditApi::makeRandomNumber( 6 );
		$this->assertLessThanOrEqual( 6, strlen( $result ) );
		$this->assertRegex( '/^\d+$/', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::makeRandomNumber
	 */
	public function testMakeRandomNumberWithPaddingHasExactLength(): void {
		$result = AutoeditApi::makeRandomNumber( 4, true );
		$this->assertSame( 4, strlen( $result ) );
		$this->assertRegex( '/^\d{4}$/', $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::makeRandomNumber
	 */
	public function testMakeRandomNumberWithoutPaddingHasNoLeadingZeros(): void {
		// Run several times to reduce the probability of a false pass.
		$allTrimmed = true;
		for ( $i = 0; $i < 20; $i++ ) {
			$result = AutoeditApi::makeRandomNumber( 6, false );
			if ( strlen( $result ) > 1 && $result[0] === '0' ) {
				$allTrimmed = false;
				break;
			}
		}
		$this->assertTrue( $allTrimmed, 'makeRandomNumber() without padding must not return leading zeros' );
	}

	// -------------------------------------------------------------------------
	// finalizeResults – 'ok text' / 'error text' copy-paste bug
	// -------------------------------------------------------------------------

	/**
	 * When the action succeeded (status 200) and the caller provided an
	 * 'ok text' option, finalizeResults() must read from mOptions['ok text'],
	 * not from mOptions['error text'].
	 *
	 * Before the fix, mOptions['error text'] was read unconditionally even in
	 * the 200 branch.  With PHP 8 and convertWarningsToExceptions="true", this
	 * throws an exception when 'error text' is absent.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::finalizeResults
	 */
	public function testFinalizeResultsUsesOkTextOptionOnSuccess(): void {
		$context = RequestContext::getMain();
		$main = new ApiMain( $context );
		$module = new AutoeditApi( $main, 'pfautoedit' );

		$ref = new ReflectionClass( AutoeditApi::class );

		$mStatus = $ref->getProperty( 'mStatus' );
		$mStatus->setAccessible( true );
		$mStatus->setValue( $module, 200 );

		// 'error text' is deliberately absent — the bug reads this key even on
		// success, which triggers "Undefined array key 'error text'" in PHP 8.
		$mOptions = $ref->getProperty( 'mOptions' );
		$mOptions->setAccessible( true );
		$mOptions->setValue( $module, [
			'ok text' => 'Saved successfully.',
			'target'  => 'TestPage',
			'form'    => 'TestForm',
		] );

		$mAction = $ref->getProperty( 'mAction' );
		$mAction->setAccessible( true );
		$mAction->setValue( $module, AutoeditApi::ACTION_SAVE );

		$finalizeResults = $ref->getMethod( 'finalizeResults' );
		$finalizeResults->setAccessible( true );

		// Must not throw "Undefined array key 'error text'".
		$finalizeResults->invoke( $module );

		$result = $module->getResult()->getResultData();
		$this->assertStringContainsString( 'Saved successfully.', $result['responseText'] );
	}

	// -------------------------------------------------------------------------
	// getters / setters
	// -------------------------------------------------------------------------

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getOptions
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::setOptions
	 */
	public function testSetAndGetOptions(): void {
		[ $module ] = $this->newModule();
		$module->setOptions( [ 'form' => 'MyForm', 'target' => 'MyPage' ] );
		$this->assertSame( [ 'form' => 'MyForm', 'target' => 'MyPage' ], $module->getOptions() );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::setOption
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getOptions
	 */
	public function testSetOptionUpdatesASingleKey(): void {
		[ $module ] = $this->newModule();
		$module->setOptions( [ 'form' => 'A', 'target' => 'B' ] );
		$module->setOption( 'target', 'C' );
		$this->assertSame( 'C', $module->getOptions()['target'] );
		$this->assertSame( 'A', $module->getOptions()['form'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getAction
	 */
	public function testGetActionReturnsNullByDefault(): void {
		[ $module ] = $this->newModule();
		$this->assertNull( $module->getAction() );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getStatus
	 */
	public function testGetStatusReturnsNullByDefault(): void {
		[ $module ] = $this->newModule();
		$this->assertNull( $module->getStatus() );
	}

	// -------------------------------------------------------------------------
	// isWriteMode / getAllowedParams / getParamDescription
	// -------------------------------------------------------------------------

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::isWriteMode
	 */
	public function testIsWriteModeReturnsTrue(): void {
		[ $module ] = $this->newModule();
		$this->assertTrue( $module->isWriteMode() );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getAllowedParams
	 */
	public function testGetAllowedParamsContainsRequiredKeys(): void {
		[ $module ] = $this->newModule();
		$params = $module->getAllowedParams();
		$this->assertIsArray( $params );
		foreach ( [ 'form', 'target', 'query', 'preload' ] as $key ) {
			$this->assertArrayHasKey( $key, $params );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getParamDescription
	 */
	public function testGetParamDescriptionReturnsStringsForAllAllowedParams(): void {
		[ $module ] = $this->newModule();
		$desc = $module->getParamDescription();
		$this->assertIsArray( $desc );
		foreach ( array_keys( $module->getAllowedParams() ) as $key ) {
			$this->assertArrayHasKey( $key, $desc );
		}
	}

	// -------------------------------------------------------------------------
	// getVersion
	// -------------------------------------------------------------------------

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getVersion
	 */
	public function testGetVersionContainsClassName(): void {
		[ $module ] = $this->newModule();
		$version = $module->getVersion();
		$this->assertStringContainsString( 'AutoeditApi', $version );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::getVersion
	 */
	public function testGetVersionContainsPFVersion(): void {
		[ $module ] = $this->newModule();
		$version = $module->getVersion();
		$this->assertStringContainsString( PF_VERSION, $version );
	}

	// -------------------------------------------------------------------------
	// addOptionsFromString – exercises AutoeditRequest::parseQueryString
	// -------------------------------------------------------------------------

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::addOptionsFromString
	 */
	public function testAddOptionsFromStringParsesSimpleKeyValue(): void {
		[ $module ] = $this->newModule();
		$module->addOptionsFromString( 'MyTemplate%5Bfield%5D=hello' );
		$options = $module->getOptions();
		$this->assertArrayHasKey( 'MyTemplate', $options );
		$this->assertSame( 'hello', $options['MyTemplate']['field'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::addOptionsFromString
	 */
	public function testAddOptionsFromStringPreservesLiteralPlusSign(): void {
		// A literal '+' in a field value must survive round-trip as '+', not
		// be decoded to a space.  This is the behaviour the + → %2B patch ensures.
		[ $module ] = $this->newModule();
		$module->addOptionsFromString( 'T%5Bf%5D=a%2Bb' );
		$this->assertSame( 'a+b', $module->getOptions()['T']['f'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::addOptionsFromString
	 */
	public function testAddOptionsFromStringHandlesMultipleFields(): void {
		[ $module ] = $this->newModule();
		$module->addOptionsFromString( 'T%5Bx%5D=1&T%5By%5D=2' );
		$options = $module->getOptions();
		$this->assertSame( '1', $options['T']['x'] );
		$this->assertSame( '2', $options['T']['y'] );
	}

	// -------------------------------------------------------------------------
	// prepareAction – action dispatch logic
	// -------------------------------------------------------------------------

	/**
	 * The button that was used to submit the form chooses the action; without one it is the form editor.
	 * Whichever it is, preparing the action leaves the status at 200.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::prepareAction
	 * @dataProvider provideActionRequests
	 * @param array $requestParams
	 * @param int $expectedAction
	 */
	public function testPrepareActionChoosesTheActionOfTheRequest( array $requestParams, int $expectedAction ): void {
		[ $module ] = $this->newModule( [ 'form' => 'TestForm', 'target' => 'TestPage' ] + $requestParams );
		$module->prepareAction();
		$this->assertSame( $expectedAction, $module->getAction() );
		$this->assertSame( 200, $module->getStatus() );
	}

	/**
	 * @return array<string, array{0: array, 1: int}>
	 */
	public static function provideActionRequests(): array {
		return [
			'no button defaults to the form editor' => [ [], AutoeditApi::ACTION_FORMEDIT ],
			'save button' => [ [ 'wpSave' => '1' ], AutoeditApi::ACTION_SAVE ],
			'preview button' => [ [ 'wpPreview' => '1' ], AutoeditApi::ACTION_PREVIEW ],
			'show changes button' => [ [ 'wpDiff' => '1' ], AutoeditApi::ACTION_DIFF ],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::prepareAction
	 */
	public function testPrepareActionActionPfautoeditSetsSaveAndIsAutoEdit(): void {
		[ $module ] = $this->newModule( [
			'form'   => 'TestForm',
			'target' => 'TestPage',
			'action' => 'pfautoedit',
		] );
		$module->prepareAction();
		$this->assertSame( AutoeditApi::ACTION_SAVE, $module->getAction() );

		$ref = ( new ReflectionClass( AutoeditApi::class ) )->getProperty( 'mIsAutoEdit' );
		$ref->setAccessible( true );
		$this->assertTrue( $ref->getValue( $module ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::prepareAction
	 */
	public function testPrepareActionTitleParamAliasesTarget(): void {
		// MW submits 'title' instead of 'target' in some contexts;
		// prepareAction() must copy it to 'target' and remove 'title'.
		[ $module ] = $this->newModule( [
			'form'  => 'TestForm',
			'title' => 'SomePage',
		] );
		$module->prepareAction();
		$opts = $module->getOptions();
		$this->assertArrayHasKey( 'target', $opts );
		$this->assertSame( 'SomePage', $opts['target'] );
		$this->assertArrayNotHasKey( 'title', $opts );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::prepareAction
	 */
	public function testPrepareActionQueryParamIsUnpacked(): void {
		// If 'query' is present it must be parsed and merged into mOptions.
		[ $module ] = $this->newModule( [
			'form'   => 'TestForm',
			'target' => 'TestPage',
			'query'  => 'MyTpl%5Bfield%5D=qval',
		] );
		$module->prepareAction();
		$opts = $module->getOptions();
		$this->assertArrayNotHasKey( 'query', $opts, "'query' key must be removed after unpacking" );
		$this->assertSame( 'qval', $opts['MyTpl']['field'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::prepareAction
	 */
	public function testPrepareActionNormalizesFormAndTargetNames(): void {
		[ $module ] = $this->newModule( [
			'form'   => 'test form',
			'target' => 'test page',
		] );
		$module->prepareAction();
		$opts = $module->getOptions();
		// Title::newFromText normalises spaces to underscores and capitalises.
		$this->assertSame( 'Test form', $opts['form'] );
		$this->assertSame( 'Test page', $opts['target'] );
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Build a AutoeditApi instance with request values pre-set.
	 *
	 * @param array $requestData Key-value pairs for the FauxRequest
	 * @return array{0: AutoeditApi}
	 */
	private function newModule( array $requestData = [] ): array {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $requestData, true ) );
		$main = new ApiMain( $context );
		$module = new AutoeditApi( $main, 'pfautoedit' );
		return [ $module ];
	}

	// -------------------------------------------------------------------------
	// getFormTitle – redirect chain resolution
	// -------------------------------------------------------------------------

	/**
	 * Sets $module's protected mOptions['form'] via reflection and invokes
	 * the protected getFormTitle(), returning its result.
	 */
	private function callGetFormTitle( AutoeditApi $module, string $formName ): Title {
		$ref = new ReflectionClass( AutoeditApi::class );

		$mOptions = $ref->getProperty( 'mOptions' );
		$mOptions->setAccessible( true );
		$mOptions->setValue( $module, [ 'form' => $formName ] );

		$getFormTitle = $ref->getMethod( 'getFormTitle' );
		$getFormTitle->setAccessible( true );
		return $getFormTitle->invoke( $module );
	}

	/**
	 * getFormTitle() must follow a multi-hop redirect chain (form A → form B
	 * → form C) to its final, non-redirect target.
	 *
	 * Regression test for: "Call to undeclared method
	 * MediaWiki\Content\Content::getUltimateRedirectTarget"
	 */
	public function testGetFormTitleFollowsMultiHopRedirectChain(): void {
		$this->createTestForm( 'PFTestGetFormTitleFinalForm' );
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, 'PFTestGetFormTitleMiddleForm' ),
			'#REDIRECT [[Form:PFTestGetFormTitleFinalForm]]'
		);
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, 'PFTestGetFormTitleStartForm' ),
			'#REDIRECT [[Form:PFTestGetFormTitleMiddleForm]]'
		);

		[ $module ] = $this->newModule();
		$formTitle = $this->callGetFormTitle( $module, 'PFTestGetFormTitleStartForm' );

		$this->assertSame( 'PFTestGetFormTitleFinalForm', $formTitle->getText() );
	}

	/**
	 * getFormTitle() must throw when a redirect chain exceeds the hop limit
	 * (a redirect loop never resolves to a non-redirect target).
	 */
	public function testGetFormTitleThrowsOnRedirectLoop(): void {
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, 'PFTestGetFormTitleLoopA' ),
			'#REDIRECT [[Form:PFTestGetFormTitleLoopB]]'
		);
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, 'PFTestGetFormTitleLoopB' ),
			'#REDIRECT [[Form:PFTestGetFormTitleLoopA]]'
		);

		[ $module ] = $this->newModule();

		$this->expectException( MWException::class );
		$this->callGetFormTitle( $module, 'PFTestGetFormTitleLoopA' );
	}

	// -------------------------------------------------------------------------
	// generateTargetName – "{num}" collision retry loop
	// -------------------------------------------------------------------------

	/**
	 * Invokes the protected generateTargetName() via reflection.
	 */
	private function callGenerateTargetName( AutoeditApi $module, string $targetNameFormula ): string {
		$ref = new ReflectionClass( AutoeditApi::class );
		$generateTargetName = $ref->getMethod( 'generateTargetName' );
		$generateTargetName->setAccessible( true );
		return $generateTargetName->invoke( $module, $targetNameFormula );
	}

	/**
	 * When the page name generated from a "{num}" formula already exists,
	 * generateTargetName() must probe
	 * $targetTitle->getArticleID( IDBAccessObject::READ_LATEST ) in a loop
	 * and keep incrementing the number until it finds a free page name.
	 *
	 * Regression test for: "Class Wikimedia\Rdbms\IDBAccessObject not found"
	 * on MediaWiki versions where IDBAccessObject is not namespaced under
	 * Wikimedia\Rdbms (e.g. MW 1.39). This is the only test that reaches the
	 * loop body (i.e. an actual collision) rather than just the loop
	 * condition on a free name.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::generateTargetName
	 */
	public function testGenerateTargetNameSkipsExistingPagesWithNumFormula(): void {
		$this->insertPage( 'PFTestGenTargetNameCollision_1', 'existing page' );
		$this->insertPage( 'PFTestGenTargetNameCollision_2', 'existing page' );

		[ $module ] = $this->newModule();
		$targetName = $this->callGenerateTargetName( $module, 'PFTestGenTargetNameCollision_{num;start=1}' );

		$this->assertSame( 'PFTestGenTargetNameCollision 3', $targetName );
	}

	/**
	 * A "{num;random}" formula must produce a title made up entirely of
	 * digits (the random-number branch), rather than falling through to the
	 * "start=" or plain-numbering branches.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::generateTargetName
	 */
	public function testGenerateTargetNameWithRandomFormulaProducesNumericSuffix(): void {
		[ $module ] = $this->newModule();
		$targetName = $this->callGenerateTargetName( $module, 'PFTestGenTargetNameRandom_{num;random}' );

		$this->assertRegex( '/^PFTestGenTargetNameRandom \d+$/', $targetName );
	}

	/**
	 * "{num;random;4}" asks for a random number of 4 digits, and "{num;random;04}" for one padded
	 * with zeros.
	 *
	 * @dataProvider provideRandomNumberTags
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::generateTargetName
	 */
	public function testGenerateTargetNameHonoursTheDigitsOfARandomNumberTag( string $tag, string $pattern ): void {
		[ $module ] = $this->newModule();

		$targetName = $this->callGenerateTargetName( $module, "PFTestGenTargetNameDigits_$tag" );

		$this->assertRegex( $pattern, $targetName );
	}

	public static function provideRandomNumberTags(): array {
		return [
			'up to 4 digits' => [ '{num;random;4}', '/^PFTestGenTargetNameDigits \d{1,4}$/' ],
			'padded to 4 digits' => [ '{num;random;04}', '/^PFTestGenTargetNameDigits \d{4}$/' ],
		];
	}

	/**
	 * When the "{num}" substitution still leaves a syntactically invalid
	 * title (e.g. via forbidden characters coming from the formula itself),
	 * generateTargetName() must throw rather than pass an invalid title on
	 * to the caller.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::generateTargetName
	 */
	public function testGenerateTargetNameThrowsOnInvalidGeneratedTitle(): void {
		[ $module ] = $this->newModule();

		$this->expectException( MWException::class );
		$this->callGenerateTargetName( $module, 'PFTestGenTargetNameInvalid[{num}' );
	}

	// -------------------------------------------------------------------------
	// finalizeResults – 'ok text' / 'error text' copy-paste bug
	// -------------------------------------------------------------------------

	/**
	 * When the action failed (status 400) and the caller provided an
	 * 'error text' option, finalizeResults() must read from mOptions['error text'].
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::finalizeResults
	 */
	public function testFinalizeResultsUsesErrorTextOptionOnFailure(): void {
		$context = RequestContext::getMain();
		$main = new ApiMain( $context );
		$module = new AutoeditApi( $main, 'pfautoedit' );

		$ref = new ReflectionClass( AutoeditApi::class );

		$mStatus = $ref->getProperty( 'mStatus' );
		$mStatus->setAccessible( true );
		$mStatus->setValue( $module, 400 );

		$mOptions = $ref->getProperty( 'mOptions' );
		$mOptions->setAccessible( true );
		$mOptions->setValue( $module, [
			'error text' => 'Something went wrong.',
			'target'     => 'TestPage',
			'form'       => 'TestForm',
		] );

		$mAction = $ref->getProperty( 'mAction' );
		$mAction->setAccessible( true );
		$mAction->setValue( $module, AutoeditApi::ACTION_SAVE );

		$finalizeResults = $ref->getMethod( 'finalizeResults' );
		$finalizeResults->setAccessible( true );
		$finalizeResults->invoke( $module );

		$result = $module->getResult()->getResultData();
		$this->assertStringContainsString( 'Something went wrong.', $result['responseText'] );
	}

	// -------------------------------------------------------------------------
	// FauxRequest / formHTML integration
	//
	// These tests guard against the "dual request-access" bug: AutoeditApi
	// passes a FauxRequest with the submitted options directly into formHTML().
	// Any call site that reads 'global $wgRequest' instead of the passed-in
	// $request silently loses all submitted field values, producing a bare
	// template call like {{Tpl}} instead of {{Tpl|field=value}}.
	// -------------------------------------------------------------------------

	/**
	 * formHTML() called with an explicit FauxRequest must produce wikitext
	 * that contains the submitted field value.
	 *
	 * This is the regression test for the setFieldValuesFromSubmit() bug:
	 * the function used `global $wgRequest` which never saw the submitted
	 * options, so template params were always lost in the autoedit save path.
	 *
	 * @covers \MediaWiki\Extension\PageForms\TemplateInForm::setFieldValuesFromSubmit
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHtmlWithFauxRequestProducesTemplateCallWithParams(): void {
		global $wgPageFormsFormPrinter;

		$templateName = 'AETestTpl';
		$fieldName = 'text';
		$fieldValue = 'Hello FauxRequest';

		$formDef = "{{{for template|$templateName}}}\n"
			. "{{{field|$fieldName}}}\n"
			. "{{{end template}}}";

		// Build the same FauxRequest that AutoeditApi would pass into formHTML().
		$options = [
			$templateName => [ $fieldName => $fieldValue ],
		];
		$fauxRequest = new FauxRequest( $options, true );

		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = true,
			$source_is_page = false,
			$form_id = null,
			$existing_page_content = null,
			$page_name = 'AETest',
			$page_name_formula = null,
			$is_query = false,
			$is_embedded = false,
			$is_autocreate = false,
			$autocreate_query = [],
			$user = $this->getTestUser()->getUser(),
			$fauxRequest
		);

		$this->assertStringContainsString(
			"|$fieldName=$fieldValue",
			$pageText,
			'formHTML() with an explicit FauxRequest must include submitted field values in the page text'
		);
	}

	/**
	 * Counterpart: formHTML() called WITHOUT a FauxRequest (real-request path)
	 * must not include a field value for a key that was never submitted.
	 *
	 * Guards against false positives in the test above.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHtmlWithoutSubmittedValueProducesBarTemplateCall(): void {
		global $wgPageFormsFormPrinter;

		$templateName = 'AETestTpl';
		$fieldName = 'text';

		$formDef = "{{{for template|$templateName}}}\n"
			. "{{{field|$fieldName}}}\n"
			. "{{{end template}}}";

		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = true,
			$source_is_page = false,
			$form_id = null,
			$existing_page_content = null,
			$page_name = 'AETest',
			$page_name_formula = null,
			$is_query = false,
			$is_embedded = false,
			$is_autocreate = false,
			$autocreate_query = [],
			$user = $this->getTestUser()->getUser()
		);

		$this->assertStringNotContainsString(
			"|$fieldName=",
			$pageText,
			'formHTML() without submitted value must not include field params in the page text'
		);
		$this->assertStringContainsString(
			"{{$templateName}}",
			$pageText,
			'formHTML() without submitted value must produce a bare template call'
		);
	}

	// -------------------------------------------------------------------------
	// Append (+) and Remove (-) modifiers — Gesinn Patch
	//
	// These tests cover the val_modifier code path in FormPrinter::formHTML()
	// (the Gesinn Patch). PFFormFieldTest already verifies that getCurrentValue()
	// returns the raw submitted value; here we verify that the concatenation or
	// subtraction against the existing page value is performed correctly.
	// -------------------------------------------------------------------------

	/**
	 * The append modifier (field+=value) must concatenate the submitted value
	 * onto the existing field value using the field's delimiter.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHtmlWithAppendModifierAppendsToExistingFieldValue(): void {
		global $wgPageFormsFormPrinter;

		$templateName = 'AETestTpl';
		$fieldName = 'text';
		$delimiter = ';';

		$formDef = "{{{for template|$templateName}}}\n"
			. "{{{field|$fieldName|delimiter=$delimiter}}}\n"
			. "{{{end template}}}";

		$existingContent = '{{' . $templateName . "\n|" . $fieldName . "=val1{$delimiter}val2\n}}";

		$options = [
			$templateName => [ "{$fieldName}+" => 'val3' ],
		];
		$fauxRequest = new FauxRequest( $options, true );

		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = true,
			$source_is_page = true,
			$form_id = null,
			$existing_page_content = $existingContent,
			$page_name = 'AETest',
			$page_name_formula = null,
			$is_query = false,
			$is_embedded = false,
			$is_autocreate = false,
			$autocreate_query = [],
			$user = $this->getTestUser()->getUser(),
			$fauxRequest
		);

		$this->assertStringContainsString(
			"|{$fieldName}=val1{$delimiter}val2{$delimiter}val3",
			$pageText,
			'Append modifier (field+=value) must concatenate new value onto existing values'
		);
	}

	/**
	 * The remove modifier (field-=value) must remove the specified value from
	 * the existing field value list.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
	 */
	public function testFormHtmlWithRemoveModifierRemovesFromExistingFieldValue(): void {
		global $wgPageFormsFormPrinter;

		$templateName = 'AETestTpl';
		$fieldName = 'text';
		$delimiter = ';';

		$formDef = "{{{for template|$templateName}}}\n"
			. "{{{field|$fieldName|delimiter=$delimiter}}}\n"
			. "{{{end template}}}";

		$existingContent = '{{' . $templateName . "\n|" . $fieldName . "=val1{$delimiter}val2{$delimiter}val3\n}}";

		$options = [
			$templateName => [ "{$fieldName}-" => 'val2' ],
		];
		$fauxRequest = new FauxRequest( $options, true );

		[ , $pageText ] = $wgPageFormsFormPrinter->formHTML(
			$formDef,
			$form_submitted = true,
			$source_is_page = true,
			$form_id = null,
			$existing_page_content = $existingContent,
			$page_name = 'AETest',
			$page_name_formula = null,
			$is_query = false,
			$is_embedded = false,
			$is_autocreate = false,
			$autocreate_query = [],
			$user = $this->getTestUser()->getUser(),
			$fauxRequest
		);

		$this->assertStringContainsString(
			"|{$fieldName}=val1{$delimiter}val3",
			$pageText,
			'Remove modifier (field-=value) must remove specified value from existing list'
		);
	}

	// -------------------------------------------------------------------------
	// Modifiers and instance numbers of a request, saved through pfautoedit
	// -------------------------------------------------------------------------

	/**
	 * Saves $formDef's form through pfautoedit onto $targetName, whose text is $pageText.
	 */
	private function saveOntoPage(
		string $formName, string $formDef, string $targetName, string $pageText, array $request
	): string {
		$this->insertPage( Title::makeTitle( PF_NS_FORM, $formName ), $formDef );
		$this->insertPage( $targetName, $pageText );
		$module = $this->executeStore( $formName, $targetName, $request );
		$this->assertSame( 200, $module->getStatus(), json_encode( $module->getResult()->getResultData() ) );
		return $this->getExistingTestPage( $targetName )->getContent()->getText();
	}

	/**
	 * A "+" modifier adds to the list the page has, with the delimiter of the field.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormValues::mergeRequest
	 */
	public function testAppendModifierAddsToTheListOnThePage(): void {
		$text = $this->saveOntoPage(
			'AEModForm1',
			"{{{for template|AEModTpl1}}}\n{{{field|tags|delimiter=;}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}",
			'AEModTarget1', "{{AEModTpl1\n|tags=a;b\n}}\n",
			[ 'AEModTpl1' => [ 'tags+' => 'c' ] ]
		);

		$this->assertStringContainsString( 'tags=a;b;c', $text );
	}

	/**
	 * A "-" modifier removes from the list the page has.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormValues::mergeRequest
	 */
	public function testRemoveModifierRemovesFromTheListOnThePage(): void {
		$text = $this->saveOntoPage(
			'AEModForm2',
			"{{{for template|AEModTpl2}}}\n{{{field|tags|delimiter=;}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}",
			'AEModTarget2', "{{AEModTpl2\n|tags=a;b;c\n}}\n",
			[ 'AEModTpl2' => [ 'tags-' => 'b' ] ]
		);

		$this->assertStringContainsString( 'tags=a;c', $text );
	}

	/**
	 * A modifier on one field must not change the other fields: a checked checkbox
	 * stays checked (it used to be saved as "No").
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormValues::mergeRequest
	 */
	public function testModifierKeepsACheckedCheckbox(): void {
		$text = $this->saveOntoPage(
			'AEModForm3',
			"{{{for template|AEModTpl3}}}\n{{{field|done|input type=checkbox}}}\n{{{field|tags|delimiter=;}}}\n"
			. "{{{end template}}}\n{{{standard input|free text}}}\n{{{standard input|save}}}",
			'AEModTarget3', "{{AEModTpl3\n|done=Yes\n|tags=a\n}}\n",
			[ 'AEModTpl3' => [ 'tags+' => 'b' ] ]
		);

		$this->assertRegex( '/\|done=Yes\b/', $text );
		$this->assertStringContainsString( 'tags=a;b', $text );
	}

	/**
	 * A modifier together with a multiple-instance template (and free text only otherwise)
	 * must not empty the page: the instances stay, the modifier applies to the instance it names.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormValues::mergeRequest
	 */
	public function testModifierOnAnInstanceKeepsTheOtherInstances(): void {
		$text = $this->saveOntoPage(
			'AEModForm4',
			"{{{for template|AEModTpl4|multiple|minimum instances=2}}}\n{{{field|tags|delimiter=;}}}\n"
			. "{{{end template}}}\n{{{standard input|free text}}}\n{{{standard input|save}}}",
			'AEModTarget4', "{{AEModTpl4\n|tags=a\n}}\n{{AEModTpl4\n|tags=b\n}}\n",
			[ 'AEModTpl4' => [ '1a' => [ 'tags+' => 'c' ] ] ]
		);

		$this->assertSame( 2, substr_count( $text, '{{AEModTpl4' ), $text );
		$this->assertStringContainsString( 'tags=a', $text );
		$this->assertStringContainsString( 'tags=b;c', $text );
	}

	/**
	 * A modifier on a multiple-instance template that does not say which instance it applies to
	 * is refused; it used to be saved as an empty instance of its own.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormValues::mergeRequest
	 */
	public function testModifierWithoutInstanceOnAMultipleTemplateIsRefused(): void {
		$formName = 'AEModForm5';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEModTpl5|multiple}}}\n{{{field|tags}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}"
		);
		$this->insertPage( 'AEModTarget5', "{{AEModTpl5\n|tags=a\n}}\n{{AEModTpl5\n|tags=b\n}}\n" );

		$module = $this->executeStore( $formName, 'AEModTarget5', [ 'AEModTpl5' => [ 'tags+' => 'c' ] ] );

		$this->assertSame( 400, $module->getStatus() );
		$text = $this->getExistingTestPage( 'AEModTarget5' )->getContent()->getText();
		$this->assertSame( 2, substr_count( $text, '{{AEModTpl5' ), $text );
	}

	/**
	 * "Tpl[<n>][field]" names the instance n (counted from 0) of a multiple-instance
	 * template. It used to add an instance and leave the instance n unchanged.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormValues::mergeRequest
	 */
	public function testInstanceNumberEditsThatInstance(): void {
		$text = $this->saveOntoPage(
			'AEInstForm1',
			"{{{for template|AEInstTpl1|multiple}}}\n{{{field|note}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}",
			'AEInstTarget1', "{{AEInstTpl1\n|note=one\n}}\n{{AEInstTpl1\n|note=two\n}}\n",
			[ 'AEInstTpl1' => [ '1' => [ 'note' => 'CHG' ] ] ]
		);

		$this->assertSame( 2, substr_count( $text, '{{AEInstTpl1' ), $text );
		$this->assertStringContainsString( 'note=one', $text );
		$this->assertStringContainsString( 'note=CHG', $text );
		$this->assertStringNotContainsString( 'note=two', $text );
	}

	/**
	 * An instance number beyond the instances of the page still adds an instance.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormValues::mergeRequest
	 */
	public function testInstanceNumberBeyondThePageAddsAnInstance(): void {
		$text = $this->saveOntoPage(
			'AEInstForm2',
			"{{{for template|AEInstTpl2|multiple}}}\n{{{field|note}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}",
			'AEInstTarget2', "{{AEInstTpl2\n|note=one\n}}\n",
			[ 'AEInstTpl2' => [ '5' => [ 'note' => 'added' ] ] ]
		);

		$this->assertSame( 2, substr_count( $text, '{{AEInstTpl2' ), $text );
		$this->assertStringContainsString( 'note=one', $text );
		$this->assertStringContainsString( 'note=added', $text );
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

	// -------------------------------------------------------------------------
	// doStore() – regression tests for issue #126
	//
	// doStore() signals failure via two different conventions depending on the
	// EditPage::AS_* status: some branches throw (caught by execute()'s
	// try/catch and turned into an ERROR-level logMessage(), status 400),
	// others call logMessage() directly with a non-ERROR level and return,
	// leaving status 200. These tests pin down the CURRENT, intentional
	// behavior of each branch as a regression safety net before doStore() is
	// refactored to a single documented contract.
	//
	// All scenarios are driven through the public execute() entry point,
	// exactly like a real API request, rather than by calling doStore()
	// directly (it is protected and expects an EditPage built by
	// setupEditPage()).
	// -------------------------------------------------------------------------

	/**
	 * Creates a minimal Form: page with one template field and returns its name.
	 */
	private function createTestForm( string $formName, string $templateName = 'AEStoreTpl' ): string {
		$formDef = "{{{for template|$templateName}}}\n"
			. "{{{field|text}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";
		$this->insertPage( Title::makeTitle( PF_NS_FORM, $formName ), $formDef );
		return $formName;
	}

	/**
	 * Drives AutoeditApi::execute() as a real 'save' submission and returns
	 * the module so the caller can inspect getStatus() / getResult().
	 *
	 * Uses ApiTestCase::doApiRequest() (rather than hand-building an ApiMain
	 * with a local RequestContext) so that RequestContext::getMain() is set
	 * up and torn down the same way the MediaWiki test framework expects —
	 * AutoeditApi reads RequestContext::getMain() internally (e.g. in
	 * doStore()/formHTML()'s preload path), and a hand-rolled local context
	 * left that global context mutated for later tests in the same process.
	 *
	 * Defaults to a fresh getMutableTestUser() rather than getTestUser(): the
	 * latter returns a single shared, registry-cached user reused by many
	 * other test classes (e.g. PFFormPrinterTest reads/mutates its group
	 * membership and RequestContext::getMain()'s user); saving pages as that
	 * shared user here caused cross-test pollution (issue #126) when the
	 * full suite ran in one process. Pass $testUser explicitly when the
	 * caller needs to act on that same user afterwards (e.g. blocking it).
	 *
	 * Explicitly restores RequestContext::getMain()'s title and user
	 * afterwards: AutoeditApi::doStore() temporarily repoints
	 * RequestContext::getMain() at the target title for the duration of
	 * attemptSave(), and ApiTestCase::doApiRequest() itself points
	 * RequestContext::getMain() at $testUser for the duration of the
	 * request — neither is undone once execute()/doApiRequest() returns,
	 * which otherwise leaks into later tests that read
	 * RequestContext::getMain()->getTitle()/getUser() (e.g.
	 * PFFormPrinterTest's watchcreations check, which expects the shared
	 * getTestUser() user to still be set there).
	 */
	private function executeStore(
		string $formName, string $targetName, array $extraOptions = [], ?TestUser $testUser = null
	): AutoeditApi {
		$testUser ??= $this->getMutableTestUser();
		$user = $testUser->getUser();

		$requestData = array_merge( [
			'action' => 'pfautoedit',
			'form' => $formName,
			'target' => $targetName,
			'wpSave' => '1',
			'wpEditToken' => $user->getEditToken(),
		], $extraOptions );
		// An option set to null is left out of the request.
		$requestData = array_filter( $requestData, static fn ( $value ) => $value !== null );

		$mainContext = RequestContext::getMain();
		$originalTitle = $mainContext->getTitle();
		$originalUser = $mainContext->getUser();
		try {
			[ , , , $main ] = $this->doApiRequest( $requestData, null, true, $testUser->getAuthority() );
			return $main->getModuleFromPath( 'pfautoedit' );
		} finally {
			$mainContext->setTitle( $originalTitle );
			$mainContext->setUser( $originalUser );
		}
	}

	/**
	 * The form is shown again after a preview and a failed save (and a diff), so the modules that the
	 * parser functions and tags of the form definition ask for must reach the page then, too, not
	 * only when the form is shown for the first time. The slideshow gallery is one that asks for a
	 * module.
	 *
	 * @dataProvider provideFormModulesCases
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doAction
	 */
	public function testModulesOfTheFormDefinitionReachThePage( array $extraOptions ): void {
		static $counter = 0;
		$counter++;
		$formName = "AEModulesForm$counter";
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"<gallery mode=\"slideshow\">\nFile:AEModules.png|x\n</gallery>\n"
			. "{{{for template|AEModulesTpl}}}\n{{{field|text}}}\n{{{end template}}}\n"
			. "{{{standard input|save}}} {{{standard input|preview}}} {{{standard input|changes}}}"
		);

		$module = $this->executeStore( $formName, "AEModulesTarget$counter", $extraOptions );

		$this->assertContains( 'mediawiki.page.gallery.slideshow', $module->getOutput()->getModules() );
	}

	public static function provideFormModulesCases(): array {
		return [
			'preview' => [ [ 'wpSave' => null, 'wpPreview' => '1' ] ],
			'failed save' => [ [ 'wpEditToken' => 'invalid' ] ],
		];
	}

	/**
	 * A user without 'edit' rights must be blocked from saving: doStore()
	 * logs the permission errors via logMessage() (default ERROR level) and
	 * returns early, without throwing. This must surface as status 400.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreWithoutEditRightsReturnsErrorStatus(): void {
		$this->setGroupPermissions( '*', 'edit', false );
		$this->setGroupPermissions( 'user', 'edit', false );

		try {
			$formName = $this->createTestForm( 'AEStoreFormPermission' );

			$module = $this->executeStore( $formName, 'AEStoreTargetPermission' );

			$this->assertSame( 400, $module->getStatus() );
			$result = $module->getResult()->getResultData();
			$this->assertArrayHasKey( 'errors', $result );
		} finally {
			// PermissionManager::isEveryoneAllowed() caches its result per
			// service instance and is not invalidated by
			// overrideConfigValue() reverting wgGroupPermissions in
			// tearDown(); left uncleared, the cached "edit is denied" answer
			// leaks into later, unrelated tests that share the same service
			// instance within the process.
			$this->resetServices();
		}
	}

	/**
	 * A page that is new needs the right to create it as well as the right to edit it. If the user
	 * has only the right to edit, the error of the missing right to create is the one shown.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreOfANewPageWithoutTheRightToCreateIsRefused(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );
		try {
			$formName = $this->createTestForm( 'AEStoreFormCreate' );

			$module = $this->executeStore( $formName, 'AEStoreTargetCreate' );

			$this->assertSame( 400, $module->getStatus() );
			$this->assertCount( 1, $module->getResult()->getResultData()['errors'] );
			$this->assertFalse( Title::newFromText( 'AEStoreTargetCreate' )->exists() );
		} finally {
			$this->resetServices();
		}
	}

	/**
	 * The errors of the right to edit and of the right to create are listed together, and an error
	 * that both have is listed once.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreListsAnErrorOfTheEditAndCreateRightsOnce(): void {
		$this->setGroupPermissions( '*', 'edit', false );
		$this->setGroupPermissions( 'user', 'edit', false );
		try {
			$formName = $this->createTestForm( 'AEStoreFormBothDenied' );

			$module = $this->executeStore( $formName, 'AEStoreTargetBothDenied' );

			$messages = array_column( $module->getResult()->getResultData()['errors'], 'message' );
			$this->assertSame( array_values( array_unique( $messages ) ), $messages );
		} finally {
			$this->resetServices();
		}
	}

	/**
	 * The "Minor edit" checkbox of a form is passed on to the save: an edit of an existing page
	 * with it checked is a minor edit, and without it it is not.
	 *
	 * @dataProvider provideMinorEditFlags
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreMarksAnEditAsMinorOnlyIfAsked( ?string $minorFlag, bool $expectedMinor ): void {
		$formName = 'AEMinorForm' . (int)$expectedMinor;
		$target = 'AEMinorTarget' . (int)$expectedMinor;
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEMinorTpl}}}\n{{{field|note}}}\n{{{end template}}}\n{{{standard input|save}}}"
		);
		$this->insertPage( $target, "{{AEMinorTpl\n|note=old\n}}\n" );

		$module = $this->executeStore( $formName, $target, [
			'AEMinorTpl' => [ 'note' => 'new' ],
			'wpMinoredit' => $minorFlag,
		] );

		$this->assertSame( 200, $module->getStatus() );
		$revision = $this->getServiceContainer()->getRevisionLookup()
			->getRevisionByTitle( Title::newFromText( $target ) );
		$this->assertStringContainsString( 'note=new', $revision->getContent( 'main' )->getText() );
		$this->assertSame( $expectedMinor, $revision->isMinor() );
	}

	public static function provideMinorEditFlags(): array {
		return [
			'the minor edit box is checked' => [ '1', true ],
			'the minor edit box is not checked' => [ null, false ],
		];
	}

	/**
	 * An invalid edit token makes tokenOk() fail, which throws MWException
	 * (session_fail_preview). execute() catches it and reports status 400.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreWithBadEditTokenReturnsErrorStatus(): void {
		$formName = $this->createTestForm( 'AEStoreFormBadToken' );

		$module = $this->executeStore( $formName, 'AEStoreTargetBadToken', [
			'wpEditToken' => 'not-a-valid-token',
		] );

		$this->assertSame( 400, $module->getStatus() );
		$result = $module->getResult()->getResultData();
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString(
			'session',
			strtolower( $result['errors'][0]['message'] ),
			'Bad-token failure should surface the session_fail_preview message'
		);
	}

	/**
	 * A malformed form definition throws a FormDefinitionException, whose message is plain
	 * text; the error shown to the caller is its escaped HTML, with the detail in a pre element.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::execute
	 */
	public function testAFormDefinitionErrorIsShownAsHtml(): void {
		$formName = 'AEStoreFormMalformed';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreTpl}}}\n{{{field|<b>x</b>}}}\n{{{end template}}}"
		);

		$module = $this->executeStore( $formName, 'AEStoreTargetMalformed' );

		$this->assertSame( 400, $module->getStatus() );
		$message = $module->getResult()->getResultData()['errors'][0]['message'];
		$this->assertStringContainsString(
			'<div class="error">Error in form definition! The following field tag contains '
				. 'forbidden characters:</div>',
			$message
		);
		$this->assertStringContainsString( '<pre>', $message );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $message );
	}

	/**
	 * Successfully creating a brand-new page (AS_SUCCESS_NEW_ARTICLE) must
	 * keep status 200 and set a 'redirect' result value, and the page must
	 * actually be created.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreCreatingNewPageSucceeds(): void {
		$formName = $this->createTestForm( 'AEStoreFormNewPage' );
		$targetName = 'AEStoreTargetNewPage';

		$module = $this->executeStore( $formName, $targetName, [
			'AEStoreTpl' => [ 'text' => 'Hello new page' ],
		] );

		$this->assertSame( 200, $module->getStatus() );
		$result = $module->getResult()->getResultData();
		$this->assertArrayHasKey( 'redirect', $result );
		$this->assertArrayNotHasKey( 'errors', $result );

		$title = Title::newFromText( $targetName );
		$this->assertTrue( $title->exists(), 'Target page must have been created' );
	}

	/**
	 * Successfully updating an existing page (AS_SUCCESS_UPDATE) must keep
	 * status 200, set a 'redirect' result value, and persist the new content.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreUpdatingExistingPageSucceeds(): void {
		$formName = $this->createTestForm( 'AEStoreFormUpdatePage' );
		$targetName = 'AEStoreTargetUpdatePage';

		$this->insertPage( $targetName, '{{AEStoreTpl|text=old value}}' );

		$module = $this->executeStore( $formName, $targetName, [
			'AEStoreTpl' => [ 'text' => 'new value' ],
		] );

		$this->assertSame( 200, $module->getStatus() );
		$result = $module->getResult()->getResultData();
		$this->assertArrayHasKey( 'redirect', $result );
		$this->assertArrayNotHasKey( 'errors', $result );

		$title = Title::newFromText( $targetName );
		$page = PFUtils::newWikiPageFromTitle( $title );
		$text = $page->getContent()->getWikitextForTransclusion();
		$this->assertStringContainsString( 'new value', $text );
	}

	/**
	 * AS_BLANK_ARTICLE (submitting no content for a brand-new page) is
	 * treated as a non-fatal, recoverable outcome by historical convention
	 * ("return false; // success"): doStore() logs at DEBUG level and issues
	 * a redirect, but must NOT flip status to 400. This is one of the two
	 * branches central to issue #126 — pinned down explicitly so a future
	 * refactor cannot silently turn it into an error.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreWithBlankArticleIsNonFatal(): void {
		// A free-text-only form maps directly onto the page's wikitext, so
		// submitting an empty free_text produces genuinely blank wikitext,
		// which EditPage reports as AS_BLANK_ARTICLE.
		$formDef = "{{{standard input|free text}}}\n"
			. "{{{standard input|save}}}";
		$formName = 'AEStoreFormBlankArticle';
		$this->insertPage( Title::makeTitle( PF_NS_FORM, $formName ), $formDef );

		$targetName = 'AEStoreTargetBlankArticle';

		$module = $this->executeStore( $formName, $targetName, [
			'free_text' => '',
		] );

		$this->assertSame(
			200,
			$module->getStatus(),
			'AS_BLANK_ARTICLE must not flip the status to 400 (historical "return false; // success" contract)'
		);
		$result = $module->getResult()->getResultData();
		$this->assertArrayHasKey( 'redirect', $result );

		$title = Title::newFromText( $targetName );
		$this->assertFalse( $title->exists(), 'Blank article must not have been created' );
	}

	/**
	 * AS_HOOK_ERROR (a hook aborts the save, e.g. via EditFilterMergedContent
	 * returning false) is treated as non-fatal by historical convention: only
	 * a DEBUG-level logMessage(), no exception, status stays 200. This is the
	 * second branch central to issue #126.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreWithHookAbortedSaveIsNonFatal(): void {
		$this->setTemporaryHook( 'EditFilterMergedContent', static function ( $context, $content, $status, $summary ) {
			$status->value = EditPage::AS_HOOK_ERROR;
			return false;
		} );

		$formName = $this->createTestForm( 'AEStoreFormHookAbort' );
		$targetName = 'AEStoreTargetHookAbort';

		$module = $this->executeStore( $formName, $targetName, [
			'AEStoreTpl' => [ 'text' => 'Hello hook abort' ],
		] );

		$this->assertSame(
			200,
			$module->getStatus(),
			'AS_HOOK_ERROR must not flip the status to 400 (historical "return false" contract) even though'
				. ' logMessage() still records a DEBUG-level entry in the result'
		);

		$title = Title::newFromText( $targetName );
		$this->assertFalse( $title->exists(), 'Page must not have been created when a hook aborts the save' );
	}

	/**
	 * AS_SPAM_ERROR (summary matches $wgSummarySpamRegex) throws MWException,
	 * caught by execute() and reported as status 400 — one of the genuinely
	 * fatal branches, to contrast with the two non-fatal ones above.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreWithSpamSummaryReturnsErrorStatus(): void {
		$this->setMwGlobals( 'wgSummarySpamRegex', [ '/viagra/i' ] );

		$formName = $this->createTestForm( 'AEStoreFormSpam' );
		$targetName = 'AEStoreTargetSpam';

		$module = $this->executeStore( $formName, $targetName, [
			'AEStoreTpl' => [ 'text' => 'Hello spam' ],
			'wpSummary' => 'buy viagra now',
		] );

		$this->assertSame( 400, $module->getStatus() );
		$result = $module->getResult()->getResultData();
		$this->assertArrayHasKey( 'errors', $result );

		$title = Title::newFromText( $targetName );
		$this->assertFalse( $title->exists(), 'Page must not have been created when the summary is flagged as spam' );
	}

	/**
	 * A blocked user attempting to save triggers AS_BLOCKED_PAGE_FOR_USER,
	 * which throws UserBlockedError. execute() catches it and reports status
	 * 400.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doStore
	 */
	public function testDoStoreWithBlockedUserReturnsErrorStatus(): void {
		// A dedicated mutable user, not the shared getTestUser() one: blocking
		// that shared, registry-cached user would leak into unrelated tests
		// that also call getTestUser() expecting an unblocked user.
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		$block = new \MediaWiki\Block\DatabaseBlock( [
			'address' => $user->getName(),
			'by' => $user,
			'reason' => 'AEStoreBlockTest',
			'expiry' => 'infinity',
		] );
		$block->setTarget( $user );
		$blockStore = \MediaWiki\MediaWikiServices::getInstance()->getDatabaseBlockStore();
		$blockStore->insertBlock( $block );

		try {
			$formName = $this->createTestForm( 'AEStoreFormBlockedUser' );
			$targetName = 'AEStoreTargetBlockedUser';

			$module = $this->executeStore( $formName, $targetName, [
				'AEStoreTpl' => [ 'text' => 'Hello blocked user' ],
			], $testUser );

			$this->assertSame( 400, $module->getStatus() );
			$result = $module->getResult()->getResultData();
			$this->assertArrayHasKey( 'errors', $result );

			$title = Title::newFromText( $targetName );
			$this->assertFalse( $title->exists(), 'Page must not have been created for a blocked user' );
		} finally {
			$blockStore->deleteBlock( $block );
		}
	}

	/**
	 * Adding an instance to a page that already has one (the "transfer step"
	 * workflow) must keep the existing instance: the preload path used for
	 * save must read every instance of a multiple-instance template, not only
	 * the first one — otherwise the existing instance is dropped and replaced
	 * by empty ones.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::execute
	 * @covers \MediaWiki\Extension\PageForms\FormDefParser::preparePreloadData
	 */
	public function testStoreAddsInstanceToMultipleInstanceTemplateKeepingExistingOnes(): void {
		$formName = 'AEStoreFormMultiInstance';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreMultiTpl|multiple}}}\n"
			. "{{{field|day}}}\n"
			. "{{{field|note}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}"
		);
		$targetName = 'AEStoreTargetMultiInstance';
		$this->insertPage(
			$targetName,
			"{{AEStoreMultiTpl\n|day=2026-10-06\n|note=existing\n}}\n"
		);

		$module = $this->executeStore( $formName, $targetName, [
			'AEStoreMultiTpl' => [ 'new' => [ 'day' => '2026-10-07', 'note' => 'added' ] ],
		] );

		$this->assertSame( 200, $module->getStatus() );
		$text = $this->getExistingTestPage( $targetName )->getContent()->getText();
		$this->assertSame( 2, preg_match_all( '/\{\{AEStoreMultiTpl/', $text ), $text );
		$this->assertStringContainsString( 'day=2026-10-06', $text );
		$this->assertStringContainsString( 'note=existing', $text );
		$this->assertStringContainsString( 'day=2026-10-07', $text );
		$this->assertStringContainsString( 'note=added', $text );
		$this->assertStringNotContainsString( '{{AEStoreMultiTpl}}', $text, 'no empty instances' );
	}

	/**
	 * A template embedded in a "holds template" field must survive a save that does not touch it
	 * (issue #213): both the outer call and the inner calls stay on the page.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormDefParser::readPageValues
	 */
	public function testStoreKeepsTemplateEmbeddedInAHoldsTemplateField(): void {
		$formName = 'AEStoreFormHolds';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreOuter}}}\n{{{field|f|holds template}}}\n{{{end template}}}\n"
			. "{{{for template|AEStoreInner|multiple|embed in field=AEStoreOuter[f]}}}\n"
			. "{{{field|x}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}"
		);
		$targetName = 'AEStoreTargetHolds';
		$this->insertPage( $targetName, "{{AEStoreOuter\n|f={{AEStoreInner|x=1}}{{AEStoreInner|x=2}}\n}}\n" );

		$module = $this->executeStore( $formName, $targetName, [ 'pf_free_text' => 'note' ] );

		$this->assertSame( 200, $module->getStatus() );
		$text = $this->getExistingTestPage( $targetName )->getContent()->getText();
		$this->assertSame( 1, preg_match_all( '/\{\{AEStoreOuter/', $text ), $text );
		$this->assertSame( 2, preg_match_all( '/\{\{AEStoreInner/', $text ), $text );
		$this->assertRegex( '/x=1\s*\}\}.*x=2\s*\}\}/s', $text );
		$this->assertStringContainsString( 'note', $text );
	}

	/**
	 * "restricted" only says who may change what in the form; the API does not enforce it. The
	 * value a request carries for a restricted field is written, for #autoedit (no wpSave) as well
	 * as for a form save, whoever the user is (issue #218 was reverted: pages lost the values of
	 * their restricted fields when they were saved through the form).
	 *
	 * @dataProvider provideRestrictedFieldCases
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doAction
	 */
	public function testStoreDoesNotEnforceRestrictedFields( string $restriction, ?string $wpSave ): void {
		static $counter = 0;
		$counter++;
		$formName = "AEStoreFormRestricted$counter";
		$targetName = "AEStoreTargetRestricted$counter";
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreRestrictedTpl}}}\n{{{field|locked$restriction}}}\n{{{field|open}}}\n"
			. "{{{end template}}}\n{{{standard input|save}}}"
		);
		$this->insertPage( $targetName, "{{AEStoreRestrictedTpl\n|locked=original\n|open=1\n}}\n" );

		$module = $this->executeStore( $formName, $targetName, [
			'wpSave' => $wpSave,
			'AEStoreRestrictedTpl' => [ 'locked' => 'changed', 'open' => '2' ],
		] );

		$this->assertSame( 200, $module->getStatus() );
		$text = $this->getExistingTestPage( $targetName )->getContent()->getText();
		$this->assertStringContainsString( 'open=2', $text );
		$this->assertStringContainsString( 'locked=changed', $text );
	}

	public static function provideRestrictedFieldCases(): array {
		return [
			'plain restriction, form save' => [ '|restricted', '1' ],
			'plain restriction, #autoedit' => [ '|restricted', null ],
			'group restriction, form save' => [ '|restricted=aerestricted', '1' ],
			'group restriction, #autoedit' => [ '|restricted=aerestricted', null ],
		];
	}

	/**
	 * A request to the API that does not carry a restricted field keeps the value the page has,
	 * because the API reads the page. This does not hold for Special:FormEdit: it sets preload=false
	 * on a save and takes the request as complete, so the form has to post the values of its
	 * restricted fields (it does, as hidden inputs). That is why the API must not drop them.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doAction
	 */
	public function testStoreKeepsRestrictedFieldTheRequestDoesNotCarry(): void {
		$formName = 'AEStoreFormRestrictedKept';
		$targetName = 'AEStoreTargetRestrictedKept';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreRestrictedTpl2}}}\n{{{field|locked|restricted}}}\n{{{field|open}}}\n"
			. "{{{end template}}}\n{{{standard input|save}}}"
		);
		$this->insertPage( $targetName, "{{AEStoreRestrictedTpl2\n|locked=original\n|open=1\n}}\n" );

		$module = $this->executeStore( $formName, $targetName, [ 'AEStoreRestrictedTpl2' => [ 'open' => '2' ] ] );

		$this->assertSame( 200, $module->getStatus() );
		$text = $this->getExistingTestPage( $targetName )->getContent()->getText();
		$this->assertStringContainsString( 'locked=original', $text );
		$this->assertStringContainsString( 'open=2', $text );
	}

	/**
	 * Parameters of a multiple-instance template that the form does not define stay with the
	 * instance they were on (issue #216), also when an instance is added.
	 *
	 * @covers \MediaWiki\Extension\PageForms\FormDefParser::readPageValues
	 * @covers \PFWikiPage::addTemplate
	 */
	public function testStoreKeepsUnhandledParametersOfMultipleInstanceTemplatePerInstance(): void {
		$formName = 'AEStoreFormMultiUnhandled';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreMultiUnh|multiple}}}\n{{{field|name}}}\n{{{end template}}}\n"
			. "{{{standard input|save}}}"
		);
		$targetName = 'AEStoreTargetMultiUnhandled';
		$this->insertPage(
			$targetName,
			"{{AEStoreMultiUnh|name=a|legacy=one}}\n{{AEStoreMultiUnh|name=b|legacy=two|other=o}}\n"
			. "{{AEStoreMultiUnh|name=c}}\n"
		);

		$module = $this->executeStore( $formName, $targetName, [
			'AEStoreMultiUnh' => [ 'new' => [ 'name' => 'd' ] ],
		] );

		$this->assertSame( 200, $module->getStatus() );
		$text = $this->getExistingTestPage( $targetName )->getContent()->getText();
		preg_match_all( '/\{\{AEStoreMultiUnh.*?\}\}/s', $text, $calls );
		$this->assertCount( 4, $calls[0], $text );
		$this->assertRegex( '/name=a\s*\|legacy=one\s*\}\}/', $calls[0][0] );
		$this->assertRegex( '/name=b/', $calls[0][1] );
		$this->assertStringContainsString( 'legacy=two', $calls[0][1] );
		$this->assertStringContainsString( 'other=o', $calls[0][1] );
		$this->assertStringNotContainsString( 'legacy', $calls[0][2] );
		$this->assertStringNotContainsString( 'legacy', $calls[0][3] );
		$this->assertStringNotContainsString( 'other', $calls[0][0] );
	}

	/**
	 * Saves AEStoreTpl through pfautoedit while the page has been changed since the
	 * revision the request claims to be based on (what #autoedit embeds when it is rendered),
	 * which EditPage reports as an edit conflict.
	 *
	 * @return AutoeditApi
	 */
	private function executeStoreWithStaleBaseRevision(
		string $formName, string $targetName, bool $asForm = true
	): AutoeditApi {
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreConflictTpl|multiple}}}\n{{{field|text}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}"
		);
		// EditPage compares timestamps with second resolution, so the two edits must not
		// fall into the same second.
		try {
			ConvertibleTimestamp::setFakeTime( '20260101120000' );
			$this->insertPage( $targetName, "{{AEStoreConflictTpl\n|text=first\n}}\n" );
			$page = PFUtils::newWikiPageFromTitle( Title::newFromText( $targetName ) );
			$page->clear();
			$staleTimestamp = $page->getTimestamp();
			$staleRevId = $page->getLatest();

			ConvertibleTimestamp::setFakeTime( '20260101120100' );
			$this->editPage(
				$targetName, "{{AEStoreConflictTpl\n|text=first\n}}\nadded by someone else\n", 'other edit',
				NS_MAIN, $this->getTestSysop()->getAuthority()
			);
		} finally {
			ConvertibleTimestamp::setFakeTime( false );
		}

		return $this->executeStore( $formName, $targetName, [
			// Without wpSave the request is what #autoedit sends.
			'wpSave' => $asForm ? '1' : null,
			'wpEdittime' => $staleTimestamp,
			'editRevId' => $staleRevId,
			'AEStoreConflictTpl' => [ 'new' => [ 'text' => 'second' ] ],
		] );
	}

	/**
	 * #autoedit embeds the target's revision at the time its page is rendered, which may be long
	 * before the click (or served from the parser cache). A change made to the target since then
	 * must not be reported as an edit conflict: the new text is computed from the current text.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doAction
	 */
	public function testAutoeditIgnoresTheRevisionEmbeddedAtRenderTime(): void {
		$module = $this->executeStoreWithStaleBaseRevision( 'AEStoreFormStale', 'AEStoreTargetStale', false );

		$this->assertSame( 200, $module->getStatus() );
		$text = $this->getExistingTestPage( 'AEStoreTargetStale' )->getContent()->getText();
		$this->assertStringContainsString( 'text=second', $text );
		$this->assertStringContainsString( 'added by someone else', $text );
	}

	/**
	 * A change to the target made after its text was read must not be overwritten silently.
	 * Two #autoedit requests that run at the same time both read the target and then save a
	 * text computed from what they read; the later save would otherwise drop the other's change.
	 *
	 * pfautoedit bases the save on the revision it read, so EditPage either merges the changes
	 * or reports a conflict. The change is made in the EditFormInitialText hook, which runs
	 * after the text was read and before the save.
	 *
	 * The two edits are by different users and the second one falls into the same second as
	 * the base: going by the timestamp, EditPage would take the second edit for the saving
	 * user's own and let it pass.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doAction
	 */
	public function testAutoeditDoesNotOverwriteAChangeMadeWhileSaving(): void {
		$formName = 'AEStoreFormRace';
		$targetName = 'AEStoreTargetRace';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEStoreRaceTpl|multiple}}}\n{{{field|text}}}\n{{{end template}}}\n"
			. "{{{standard input|free text}}}\n{{{standard input|save}}}"
		);
		$this->insertPage( $targetName, "{{AEStoreRaceTpl\n|text=first\n}}\n" );

		$sysop = $this->getTestSysop()->getAuthority();
		$testUser = $this->getMutableTestUser();
		$this->setTemporaryHook(
			'PageForms::EditFormInitialText',
			function () use ( $targetName, $sysop, $testUser ) {
				$this->editPage(
					$targetName, "{{AEStoreRaceTpl\n|text=first\n}}\n\n\n\nadded meanwhile\n", 'meanwhile',
					NS_MAIN, $sysop
				);
				// editPage() makes the editor the user of the request; the save is by $testUser.
				RequestContext::getMain()->setUser( $testUser->getUser() );
			}
		);

		$module = $this->executeStore( 'AEStoreFormRace', $targetName, [
			'wpSave' => null,
			'AEStoreRaceTpl' => [ 'new' => [ 'text' => 'second' ] ],
		], $testUser );

		$text = $this->getExistingTestPage( $targetName )->getContent()->getText();
		$this->assertTrue(
			str_contains( $text, 'added meanwhile' ) || $module->getStatus() === 400,
			"The change made meanwhile was overwritten:\n$text"
		);
	}

	/**
	 * An edit conflict used to be reported with the same generic "Modifying ... failed."
	 * text as five other EditPage outcomes, so nobody could tell why a save failed. The
	 * message must now name the EditPage status.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::handleSaveStatus
	 */
	public function testEditConflictIsReportedWithItsReason(): void {
		$module = $this->executeStoreWithStaleBaseRevision( 'AEStoreFormConflict', 'AEStoreTargetConflict' );

		$this->assertSame( 400, $module->getStatus() );
		$errors = $module->getResult()->getResultData()['errors'];
		$this->assertStringContainsString( 'AS_CONFLICT_DETECTED', $errors[0]['message'] );
		$this->assertStringContainsString( 'AEStoreTargetConflict', $errors[0]['message'] );
	}

	/**
	 * A failed save is written to the "PageForms" log channel with the form, target and user,
	 * so that the reason can be found on the server and not only in the browser's response.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::logMessage
	 */
	public function testFailedSaveIsLogged(): void {
		$logger = $this->createMock( \Psr\Log\LoggerInterface::class );
		$logger->expects( $this->atLeastOnce() )->method( 'warning' )->with(
			$this->stringContains( 'pfautoedit' ),
			$this->callback( static function ( array $context ) {
				return $context['form'] === 'AEStoreFormLogged'
					&& $context['target'] === 'AEStoreTargetLogged'
					&& str_contains( $context['reason'], 'AS_CONFLICT_DETECTED' );
			} )
		);
		$this->setLogger( 'PageForms', $logger );

		$this->executeStoreWithStaleBaseRevision( 'AEStoreFormLogged', 'AEStoreTargetLogged' );
	}

	/**
	 * Successful saves and non-error messages must not be logged as failures.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::logMessage
	 */
	public function testSuccessfulSaveIsNotLoggedAsFailure(): void {
		$formName = $this->createTestForm( 'AEStoreFormNoLog' );
		$logger = $this->createMock( \Psr\Log\LoggerInterface::class );
		$logger->expects( $this->never() )->method( 'warning' );
		$logger->expects( $this->never() )->method( 'error' );
		$this->setLogger( 'PageForms', $logger );

		$module = $this->executeStore( $formName, 'AEStoreTargetNoLog', [
			'AEStoreTpl' => [ 'text' => 'fine' ],
		] );

		$this->assertSame( 200, $module->getStatus() );
	}

	// -------------------------------------------------------------------------
	// doAction – read-only mode, target name formula, preload
	// -------------------------------------------------------------------------

	/**
	 * Calls the private checkReadOnlyMode() of a module that performs $action. (A read-only wiki
	 * already refuses a write module in ApiMain, so the check is only reached from the module
	 * itself.)
	 */
	private function callCheckReadOnlyMode( int $action ): AutoeditApi {
		$this->overrideConfigValue( MainConfigNames::ReadOnly, 'AEDoActionReadOnlyReason' );
		[ $module ] = $this->newModule();
		$ref = new ReflectionClass( AutoeditApi::class );
		$mAction = $ref->getProperty( 'mAction' );
		$mAction->setAccessible( true );
		$mAction->setValue( $module, $action );
		$check = $ref->getMethod( 'checkReadOnlyMode' );
		$check->setAccessible( true );
		$check->invoke( $module );
		return $module;
	}

	/**
	 * A save is refused while the wiki is read-only.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::checkReadOnlyMode
	 */
	public function testSaveIsRefusedWhileTheWikiIsReadOnly(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'AEDoActionReadOnlyReason' );

		$this->callCheckReadOnlyMode( AutoeditApi::ACTION_SAVE );
	}

	/**
	 * Any other action only reports that the wiki is read-only, and goes on.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::checkReadOnlyMode
	 */
	public function testPreviewWhileTheWikiIsReadOnlyReportsANoticeAndGoesOn(): void {
		$module = $this->callCheckReadOnlyMode( AutoeditApi::ACTION_PREVIEW );

		$errors = $module->getResult()->getResultData()['errors'];
		$this->assertCount( 1, $errors );
		$this->assertSame( AutoeditApi::NOTICE, $errors[0]['level'] );
		$this->assertStringContainsString( 'AEDoActionReadOnlyReason', $errors[0]['message'] );
	}

	/**
	 * A wiki that is not read-only reports nothing.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::checkReadOnlyMode
	 */
	public function testCheckReadOnlyModeReportsNothingOnAWritableWiki(): void {
		[ $module ] = $this->newModule();
		$check = ( new ReflectionClass( AutoeditApi::class ) )->getMethod( 'checkReadOnlyMode' );
		$check->setAccessible( true );
		$check->invoke( $module );

		$this->assertArrayNotHasKey( 'errors', $module->getResult()->getResultData() ?? [] );
	}

	/**
	 * Without a target, the name of the page comes from the "page name" of the form.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::findTargetNameFormula
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::performSubmittedAction
	 */
	public function testStoreWithoutTargetTakesTheNameFromThePageNameOfTheForm(): void {
		$formName = 'AEDoActionFormPageName';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{info|page name=AEDoActionGeneratedPage}}}\n{{{for template|AEDoActionTpl}}}\n"
			. "{{{field|text}}}\n{{{end template}}}\n{{{standard input|save}}}"
		);

		$module = $this->executeStore( $formName, '', [ 'AEDoActionTpl' => [ 'text' => 'generated' ] ] );

		$this->assertSame( 200, $module->getStatus(), json_encode( $module->getResult()->getResultData() ) );
		$text = $this->getExistingTestPage( 'AEDoActionGeneratedPage' )->getContent()->getText();
		$this->assertStringContainsString( 'text=generated', $text );
	}

	/**
	 * Without a target and without a "page name" in the form, there is no page to save.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::findTargetNameFormula
	 */
	public function testStoreWithoutTargetAndWithoutPageNameOfTheFormFails(): void {
		$formName = $this->createTestForm( 'AEDoActionFormNoPageName' );

		$module = $this->executeStore( $formName, '' );

		$this->assertSame( 400, $module->getStatus() );
		$this->assertCount( 1, $module->getResult()->getResultData()['errors'] );
	}

	/**
	 * A "preload" option that names no existing page is reported as a warning; the save goes on
	 * without preloaded text.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::loadPreloadContent
	 */
	public function testStoreWithAnUnknownPreloadPageWarnsAndGoesOn(): void {
		$formName = $this->createTestForm( 'AEDoActionFormUnknownPreload' );

		$module = $this->executeStore( $formName, 'AEDoActionTargetUnknownPreload', [
			'preload' => 'AEDoActionPreloadPageThatDoesNotExist',
			'AEStoreTpl' => [ 'text' => 'kept' ],
		] );

		$this->assertSame( 200, $module->getStatus() );
		$errors = $module->getResult()->getResultData()['errors'];
		$this->assertSame( AutoeditApi::WARNING, $errors[0]['level'] );
		$this->assertTrue( Title::newFromText( 'AEDoActionTargetUnknownPreload' )->exists() );
	}

	/**
	 * The values of the page named by "preload" are the ones a new target starts with, and the
	 * request is laid over them.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::loadPreloadContent
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::doAction
	 */
	public function testStoreStartsFromTheValuesOfThePreloadPage(): void {
		$formName = 'AEDoActionFormPreload';
		$this->insertPage(
			Title::makeTitle( PF_NS_FORM, $formName ),
			"{{{for template|AEDoActionPreloadTpl}}}\n{{{field|first}}}\n{{{field|second}}}\n"
			. "{{{end template}}}\n{{{standard input|save}}}"
		);
		$this->insertPage(
			'AEDoActionPreloadSource',
			"{{AEDoActionPreloadTpl\n|first=from source\n|second=also\n}}\n"
		);

		$module = $this->executeStore( $formName, 'AEDoActionTargetPreload', [
			'preload' => 'AEDoActionPreloadSource',
			'AEDoActionPreloadTpl' => [ 'second' => 'from request' ],
		] );

		$this->assertSame( 200, $module->getStatus(), json_encode( $module->getResult()->getResultData() ) );
		$text = $this->getExistingTestPage( 'AEDoActionTargetPreload' )->getContent()->getText();
		$this->assertStringContainsString( 'first=from source', $text );
		$this->assertStringContainsString( 'second=from request', $text );
	}

	/**
	 * A submitted form is previewed, diffed or stored, as the action says.
	 *
	 * @dataProvider provideSubmittedActions
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::performSubmittedAction
	 */
	public function testPerformSubmittedActionRunsTheRequestedAction( int $action, string $expectedMethod ): void {
		$methods = [ 'doPreview', 'doDiff', 'doStore' ];
		$module = $this->getMockBuilder( AutoeditApi::class )
			->disableOriginalConstructor()
			->onlyMethods( array_merge( $methods, [ 'setupEditPage' ] ) )
			->getMock();
		$editor = $this->createMock( EditPage::class );
		$module->method( 'setupEditPage' )->willReturn( $editor );
		foreach ( $methods as $method ) {
			$module->expects( $method === $expectedMethod ? $this->once() : $this->never() )
				->method( $method )->with( $editor );
		}
		$this->setProtectedProperties( $module, [
			'mAction' => $action,
			'mOptions' => [ 'form' => 'AEDoActionDispatchForm', 'target' => 'AEDoActionDispatchTarget' ],
		] );

		$this->callPerformSubmittedAction( $module, '', 'text' );
	}

	public static function provideSubmittedActions(): array {
		return [
			'preview' => [ AutoeditApi::ACTION_PREVIEW, 'doPreview' ],
			'diff' => [ AutoeditApi::ACTION_DIFF, 'doDiff' ],
			'save' => [ AutoeditApi::ACTION_SAVE, 'doStore' ],
		];
	}

	/**
	 * Without a target, the name generated from the page name formula is the target, and the text
	 * is the one other code changed in the PageForms::WritePageData hook.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::performSubmittedAction
	 */
	public function testPerformSubmittedActionNamesTheTargetAndLetsHooksChangeTheText(): void {
		$module = $this->getMockBuilder( AutoeditApi::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'setupEditPage', 'doStore' ] )
			->getMock();
		$module->expects( $this->once() )->method( 'setupEditPage' )
			->with( 'text changed by hook' )
			->willReturn( $this->createMock( EditPage::class ) );
		$this->setTemporaryHook( 'PageForms::WritePageData', static function ( $form, $target, &$text ) {
			$text = 'text changed by hook';
		} );
		$this->setProtectedProperties( $module, [
			'mAction' => AutoeditApi::ACTION_SAVE,
			'mOptions' => [ 'form' => 'AEDoActionDispatchForm', 'target' => '' ],
		] );

		$this->callPerformSubmittedAction( $module, 'AEDoActionNamedPage', 'text' );

		$this->assertSame( 'AEDoActionNamedPage', $module->getOptions()['target'] );
	}

	/**
	 * Without a target and without a generated page name, there is nothing to save.
	 *
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::performSubmittedAction
	 */
	public function testPerformSubmittedActionWithoutAnyTargetNameFails(): void {
		$module = $this->getMockBuilder( AutoeditApi::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'setupEditPage' ] )
			->getMock();
		$module->expects( $this->never() )->method( 'setupEditPage' );
		$this->setProtectedProperties( $module, [
			'mAction' => AutoeditApi::ACTION_SAVE,
			'mOptions' => [ 'form' => 'AEDoActionDispatchForm', 'target' => '' ],
		] );

		$this->expectException( MWException::class );

		$this->callPerformSubmittedAction( $module, '', 'text' );
	}

	private function setProtectedProperties( AutoeditApi $module, array $values ): void {
		$ref = new ReflectionClass( AutoeditApi::class );
		foreach ( $values as $name => $value ) {
			$property = $ref->getProperty( $name );
			$property->setAccessible( true );
			$property->setValue( $module, $value );
		}
	}

	private function callPerformSubmittedAction(
		AutoeditApi $module, string $generatedTargetNameFormula, string $targetContent
	): void {
		$method = ( new ReflectionClass( AutoeditApi::class ) )->getMethod( 'performSubmittedAction' );
		$method->setAccessible( true );
		$method->invoke( $module, $generatedTargetNameFormula, $targetContent );
	}

	// -------------------------------------------------------------------------
	// handleSaveStatus – where a successful save redirects to
	// -------------------------------------------------------------------------

	/**
	 * A save goes to the page of the "returnto" parameter instead of the saved page, whether the
	 * saved page was created or updated.
	 *
	 * @dataProvider provideCreatedAndUpdated
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::redirectTo
	 */
	public function testSaveRedirectsToTheReturnToPage( bool $targetExists ): void {
		$formName = $this->createTestForm( 'AEHandleFormReturnTo' . (int)$targetExists );
		$targetName = 'AEHandleTargetReturnTo' . (int)$targetExists;
		if ( $targetExists ) {
			$this->insertPage( $targetName, "{{AEStoreTpl\n|text=old\n}}\n" );
		}
		$this->insertPage( 'AEHandleReturnToPage', 'back here' );

		$module = $this->executeStore( $formName, $targetName, [
			'returnto' => 'AEHandleReturnToPage',
			// "reload" purges the returnto page; the redirect is the same.
			'reload' => '1',
			'AEStoreTpl' => [ 'text' => 'new' ],
		] );

		$this->assertSame( 200, $module->getStatus(), json_encode( $module->getResult()->getResultData() ) );
		$this->assertSame(
			Title::newFromText( 'AEHandleReturnToPage' )->getFullURL(),
			$module->getResult()->getResultData()['redirect']
		);
	}

	/**
	 * Without "returnto" a save goes to the saved page, and extensions can add to the query.
	 *
	 * @dataProvider provideCreatedAndUpdated
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::redirectAfterCreate
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::redirectAfterUpdate
	 */
	public function testSaveRedirectsToTheSavedPageWithTheQueryOfTheHook( bool $targetExists ): void {
		$formName = $this->createTestForm( 'AEHandleFormQuery' . (int)$targetExists );
		$targetName = 'AEHandleTargetQuery' . (int)$targetExists;
		if ( $targetExists ) {
			$this->insertPage( $targetName, "{{AEStoreTpl\n|text=old\n}}\n" );
		}
		$this->setTemporaryHook(
			'ArticleUpdateBeforeRedirect',
			static function ( $article, &$sectionanchor, &$extraQuery ) {
				$extraQuery = 'aehook=1';
			}
		);

		$module = $this->executeStore( $formName, $targetName, [ 'AEStoreTpl' => [ 'text' => 'new' ] ] );

		$this->assertSame( 200, $module->getStatus(), json_encode( $module->getResult()->getResultData() ) );
		$redirect = $module->getResult()->getResultData()['redirect'];
		$this->assertStringContainsString( 'aehook=1', $redirect );
		$this->assertSame(
			Title::newFromText( $targetName )->getFullURL( 'aehook=1' ),
			$redirect
		);
	}

	/**
	 * The section anchor an extension sets in the ArticleUpdateBeforeRedirect hook ends the
	 * redirect, for a created page as well as for an updated one.
	 *
	 * @dataProvider provideCreatedAndUpdated
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::redirectAfterCreate
	 * @covers \MediaWiki\Extension\PageForms\AutoeditApi::redirectAfterUpdate
	 */
	public function testSaveRedirectsToTheSectionAnchorOfTheHook( bool $targetExists ): void {
		$formName = $this->createTestForm( 'AEHandleFormAnchor' . (int)$targetExists );
		$targetName = 'AEHandleTargetAnchor' . (int)$targetExists;
		if ( $targetExists ) {
			$this->insertPage( $targetName, "{{AEStoreTpl\n|text=old\n}}\n" );
		}
		$this->setTemporaryHook(
			'ArticleUpdateBeforeRedirect',
			static function ( $article, &$sectionanchor, &$extraQuery ) {
				$sectionanchor = '#AEHookAnchor';
			}
		);

		$module = $this->executeStore( $formName, $targetName, [ 'AEStoreTpl' => [ 'text' => 'new' ] ] );

		$this->assertSame( 200, $module->getStatus(), json_encode( $module->getResult()->getResultData() ) );
		$this->assertSame(
			Title::newFromText( $targetName )->getFullURL() . '#AEHookAnchor',
			$module->getResult()->getResultData()['redirect']
		);
	}

	public static function provideCreatedAndUpdated(): array {
		return [
			'page is created' => [ false ],
			'page is updated' => [ true ],
		];
	}
}
