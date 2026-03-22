<?php

use MediaWiki\Extension\PageForms\HtmlFormDataExtractor;
use OOUI\BlankTheme;

/**
 * @covers \PFAutoeditAPI
 * @group PF
 * @group Database
 * @group medium
 */
class PFAutoeditAPITest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );
	}

	// -------------------------------------------------------------------------
	// makeRandomNumber
	// -------------------------------------------------------------------------

	/**
	 * @covers \PFAutoeditAPI::makeRandomNumber
	 */
	public function testMakeRandomNumberDefaultIsOneDigit(): void {
		$result = PFAutoeditAPI::makeRandomNumber();
		$this->assertRegExp( '/^\d$/', $result );
	}

	/**
	 * @covers \PFAutoeditAPI::makeRandomNumber
	 */
	public function testMakeRandomNumberWithSixDigitsHasAtMostSixChars(): void {
		$result = PFAutoeditAPI::makeRandomNumber( 6 );
		$this->assertLessThanOrEqual( 6, strlen( $result ) );
		$this->assertRegExp( '/^\d+$/', $result );
	}

	/**
	 * @covers \PFAutoeditAPI::makeRandomNumber
	 */
	public function testMakeRandomNumberWithPaddingHasExactLength(): void {
		$result = PFAutoeditAPI::makeRandomNumber( 4, true );
		$this->assertSame( 4, strlen( $result ) );
		$this->assertRegExp( '/^\d{4}$/', $result );
	}

	/**
	 * @covers \PFAutoeditAPI::makeRandomNumber
	 */
	public function testMakeRandomNumberWithoutPaddingHasNoLeadingZeros(): void {
		// Run several times to reduce the probability of a false pass.
		$allTrimmed = true;
		for ( $i = 0; $i < 20; $i++ ) {
			$result = PFAutoeditAPI::makeRandomNumber( 6, false );
			if ( strlen( $result ) > 1 && $result[0] === '0' ) {
				$allTrimmed = false;
				break;
			}
		}
		$this->assertTrue( $allTrimmed, 'makeRandomNumber() without padding must not return leading zeros' );
	}

	// -------------------------------------------------------------------------
	// addToArray
	// -------------------------------------------------------------------------

	/**
	 * @covers \PFAutoeditAPI::addToArray
	 */
	public function testAddToArrayTopLevelKeyAddsStringValue(): void {
		$data = [];
		PFAutoeditAPI::addToArray( $data, 'key', 'value' );
		$this->assertSame( [ 'key' => 'value' ], $data );
	}

	/**
	 * @covers \PFAutoeditAPI::addToArray
	 */
	public function testAddToArrayNestedKeyCreatesNestedArray(): void {
		$data = [];
		PFAutoeditAPI::addToArray( $data, 'template[field]', 'val' );
		$this->assertSame( [ 'template' => [ 'field' => 'val' ] ], $data );
	}

	/**
	 * @covers \PFAutoeditAPI::addToArray
	 */
	public function testAddToArrayTopLevelSpaceEncodedAsUnderscore(): void {
		$data = [];
		PFAutoeditAPI::addToArray( $data, 'my template[field]', 'val' );
		$this->assertArrayHasKey( 'my_template', $data );
	}

	/**
	 * @covers \PFAutoeditAPI::addToArray
	 */
	public function testAddToArrayEmptyKeyAppendsValue(): void {
		$data = [];
		PFAutoeditAPI::addToArray( $data, '', 'a' );
		PFAutoeditAPI::addToArray( $data, '', 'b' );
		$this->assertContains( 'a', $data );
		$this->assertContains( 'b', $data );
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
	 * @covers \PFAutoeditAPI::finalizeResults
	 */
	public function testFinalizeResultsUsesOkTextOptionOnSuccess(): void {
		$context = RequestContext::getMain();
		$main = new ApiMain( $context );
		$module = new PFAutoeditAPI( $main, 'pfautoedit' );

		$ref = new ReflectionClass( PFAutoeditAPI::class );

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
		$mAction->setValue( $module, PFAutoeditAPI::ACTION_SAVE );

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
	 * @covers \PFAutoeditAPI::getOptions
	 * @covers \PFAutoeditAPI::setOptions
	 */
	public function testSetAndGetOptions(): void {
		[ $module ] = $this->newModule();
		$module->setOptions( [ 'form' => 'MyForm', 'target' => 'MyPage' ] );
		$this->assertSame( [ 'form' => 'MyForm', 'target' => 'MyPage' ], $module->getOptions() );
	}

	/**
	 * @covers \PFAutoeditAPI::setOption
	 * @covers \PFAutoeditAPI::getOptions
	 */
	public function testSetOptionUpdatesASingleKey(): void {
		[ $module ] = $this->newModule();
		$module->setOptions( [ 'form' => 'A', 'target' => 'B' ] );
		$module->setOption( 'target', 'C' );
		$this->assertSame( 'C', $module->getOptions()['target'] );
		$this->assertSame( 'A', $module->getOptions()['form'] );
	}

	/**
	 * @covers \PFAutoeditAPI::getAction
	 */
	public function testGetActionReturnsNullByDefault(): void {
		[ $module ] = $this->newModule();
		$this->assertNull( $module->getAction() );
	}

	/**
	 * @covers \PFAutoeditAPI::getStatus
	 */
	public function testGetStatusReturnsNullByDefault(): void {
		[ $module ] = $this->newModule();
		$this->assertNull( $module->getStatus() );
	}

	// -------------------------------------------------------------------------
	// isWriteMode / getAllowedParams / getParamDescription
	// -------------------------------------------------------------------------

	/**
	 * @covers \PFAutoeditAPI::isWriteMode
	 */
	public function testIsWriteModeReturnsTrue(): void {
		[ $module ] = $this->newModule();
		$this->assertTrue( $module->isWriteMode() );
	}

	/**
	 * @covers \PFAutoeditAPI::getAllowedParams
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
	 * @covers \PFAutoeditAPI::getParamDescription
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
	 * @covers \PFAutoeditAPI::getVersion
	 */
	public function testGetVersionContainsClassName(): void {
		[ $module ] = $this->newModule();
		$version = $module->getVersion();
		$this->assertStringContainsString( 'PFAutoeditAPI', $version );
	}

	/**
	 * @covers \PFAutoeditAPI::getVersion
	 */
	public function testGetVersionContainsPFVersion(): void {
		[ $module ] = $this->newModule();
		$version = $module->getVersion();
		$this->assertStringContainsString( PF_VERSION, $version );
	}

	// -------------------------------------------------------------------------
	// addOptionsFromString – exercises parseDataFromQueryString
	// -------------------------------------------------------------------------

	/**
	 * @covers \PFAutoeditAPI::addOptionsFromString
	 */
	public function testAddOptionsFromStringParsesSimpleKeyValue(): void {
		[ $module ] = $this->newModule();
		$module->addOptionsFromString( 'MyTemplate%5Bfield%5D=hello' );
		$options = $module->getOptions();
		$this->assertArrayHasKey( 'MyTemplate', $options );
		$this->assertSame( 'hello', $options['MyTemplate']['field'] );
	}

	/**
	 * @covers \PFAutoeditAPI::addOptionsFromString
	 */
	public function testAddOptionsFromStringPreservesLiteralPlusSign(): void {
		// A literal '+' in a field value must survive round-trip as '+', not
		// be decoded to a space.  This is the behaviour the + → %2B patch ensures.
		[ $module ] = $this->newModule();
		$module->addOptionsFromString( 'T%5Bf%5D=a%2Bb' );
		$this->assertSame( 'a+b', $module->getOptions()['T']['f'] );
	}

	/**
	 * @covers \PFAutoeditAPI::addOptionsFromString
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
	 * @covers \PFAutoeditAPI::prepareAction
	 */
	public function testPrepareActionDefaultsToFormedit(): void {
		[ $module ] = $this->newModule( [ 'form' => 'TestForm', 'target' => 'TestPage' ] );
		$module->prepareAction();
		$this->assertSame( PFAutoeditAPI::ACTION_FORMEDIT, $module->getAction() );
	}

	/**
	 * @covers \PFAutoeditAPI::prepareAction
	 */
	public function testPrepareActionWpSaveSetsActionSave(): void {
		[ $module ] = $this->newModule( [
			'form'   => 'TestForm',
			'target' => 'TestPage',
			'wpSave' => '1',
		] );
		$module->prepareAction();
		$this->assertSame( PFAutoeditAPI::ACTION_SAVE, $module->getAction() );
	}

	/**
	 * @covers \PFAutoeditAPI::prepareAction
	 */
	public function testPrepareActionWpPreviewSetsActionPreview(): void {
		[ $module ] = $this->newModule( [
			'form'      => 'TestForm',
			'target'    => 'TestPage',
			'wpPreview' => '1',
		] );
		$module->prepareAction();
		$this->assertSame( PFAutoeditAPI::ACTION_PREVIEW, $module->getAction() );
	}

	/**
	 * @covers \PFAutoeditAPI::prepareAction
	 */
	public function testPrepareActionWpDiffSetsActionDiff(): void {
		[ $module ] = $this->newModule( [
			'form'   => 'TestForm',
			'target' => 'TestPage',
			'wpDiff' => '1',
		] );
		$module->prepareAction();
		$this->assertSame( PFAutoeditAPI::ACTION_DIFF, $module->getAction() );
	}

	/**
	 * @covers \PFAutoeditAPI::prepareAction
	 */
	public function testPrepareActionActionPfautoeditSetsSaveAndIsAutoEdit(): void {
		[ $module ] = $this->newModule( [
			'form'   => 'TestForm',
			'target' => 'TestPage',
			'action' => 'pfautoedit',
		] );
		$module->prepareAction();
		$this->assertSame( PFAutoeditAPI::ACTION_SAVE, $module->getAction() );

		$ref = ( new ReflectionClass( PFAutoeditAPI::class ) )->getProperty( 'mIsAutoEdit' );
		$ref->setAccessible( true );
		$this->assertTrue( $ref->getValue( $module ) );
	}

	/**
	 * @covers \PFAutoeditAPI::prepareAction
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
	 * @covers \PFAutoeditAPI::prepareAction
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
	 * @covers \PFAutoeditAPI::prepareAction
	 */
	public function testPrepareActionSetsStatusTo200(): void {
		[ $module ] = $this->newModule( [ 'form' => 'TestForm', 'target' => 'TestPage' ] );
		$module->prepareAction();
		$this->assertSame( 200, $module->getStatus() );
	}

	/**
	 * @covers \PFAutoeditAPI::prepareAction
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
	// addToArray – numeric instance key appends 'a' suffix (non-top-level)
	// -------------------------------------------------------------------------

	/**
	 * @covers \PFAutoeditAPI::addToArray
	 */
	public function testAddToArrayNumericSubkeyGetsSuffix(): void {
		$data = [];
		// Non-top-level numeric key inside a parent key → parent['0a'][...]
		PFAutoeditAPI::addToArray( $data, 'T[0][field]', 'v', false );
		$this->assertArrayHasKey( '0a', $data['T'] );
	}

	/**
	 * @covers \PFAutoeditAPI::addToArray
	 */
	public function testAddToArrayDoesNotOverwriteExistingChildArray(): void {
		$data = [ 'T' => [ 'f' => 'old' ] ];
		// Trying to set a string on a key that already holds an array → no-op
		PFAutoeditAPI::addToArray( $data, 'T', 'should not overwrite' );
		$this->assertIsArray( $data['T'] );
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Build a PFAutoeditAPI instance with request values pre-set.
	 *
	 * @param array $requestData Key-value pairs for the FauxRequest
	 * @return array{0: PFAutoeditAPI}
	 */
	private function newModule( array $requestData = [] ): array {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $requestData, true ) );
		$main = new ApiMain( $context );
		$module = new PFAutoeditAPI( $main, 'pfautoedit' );
		return [ $module ];
	}

	// -------------------------------------------------------------------------
	// finalizeResults – 'ok text' / 'error text' copy-paste bug
	// -------------------------------------------------------------------------

	/**
	 * When the action failed (status 400) and the caller provided an
	 * 'error text' option, finalizeResults() must read from mOptions['error text'].
	 *
	 * @covers \PFAutoeditAPI::finalizeResults
	 */
	public function testFinalizeResultsUsesErrorTextOptionOnFailure(): void {
		$context = RequestContext::getMain();
		$main = new ApiMain( $context );
		$module = new PFAutoeditAPI( $main, 'pfautoedit' );

		$ref = new ReflectionClass( PFAutoeditAPI::class );

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
		$mAction->setValue( $module, PFAutoeditAPI::ACTION_SAVE );

		$finalizeResults = $ref->getMethod( 'finalizeResults' );
		$finalizeResults->setAccessible( true );
		$finalizeResults->invoke( $module );

		$result = $module->getResult()->getResultData();
		$this->assertStringContainsString( 'Something went wrong.', $result['responseText'] );
	}

	// -------------------------------------------------------------------------
	// HtmlFormDataExtractor::extract – characterises every HTML input variant
	// -------------------------------------------------------------------------

	/**
	 * Helper: call HtmlFormDataExtractor::extract() directly.
	 * Returns the extracted data and the (possibly modified) mOptions array.
	 *
	 * @param string $html HTML fragment to parse
	 * @param array $mOptions Initial mOptions (disabled-field removal tests use this)
	 * @return array{data: array, mOptions: array}
	 */
	private function parseHTML( string $html, array $mOptions = [] ): array {
		$data = HtmlFormDataExtractor::extract( $html, $mOptions );
		return [
			'data'     => $data,
			'mOptions' => $mOptions,
		];
	}

	// --- text input ---

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLTextInputExtractsValue(): void {
		$result = $this->parseHTML( '<input type="text" name="MyTpl[field]" value="hello" />' );
		$this->assertSame( 'hello', $result['data']['MyTpl']['field'] );
	}

	/**
	 * A bare <input> with no type attribute defaults to "text".
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLInputWithNoTypeDefaultsToText(): void {
		$result = $this->parseHTML( '<input name="MyTpl[field]" value="implicit" />' );
		$this->assertSame( 'implicit', $result['data']['MyTpl']['field'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLHiddenInputExtractsValue(): void {
		$result = $this->parseHTML( '<input type="hidden" name="MyTpl[h]" value="hv" />' );
		$this->assertSame( 'hv', $result['data']['MyTpl']['h'] );
	}

	// --- checkbox ---

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLCheckedCheckboxExtractsValue(): void {
		$result = $this->parseHTML(
			'<input type="checkbox" name="MyTpl[cb]" value="yes" checked />'
		);
		$this->assertSame( 'yes', $result['data']['MyTpl']['cb'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLUncheckedCheckboxProducesNoEntry(): void {
		$result = $this->parseHTML(
			'<input type="checkbox" name="MyTpl[cb]" value="yes" />'
		);
		$this->assertArrayNotHasKey( 'MyTpl', $result['data'] );
	}

	// --- radio ---

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLCheckedRadioExtractsValue(): void {
		$result = $this->parseHTML(
			'<input type="radio" name="MyTpl[r]" value="A" checked />' .
			'<input type="radio" name="MyTpl[r]" value="B" />'
		);
		$this->assertSame( 'A', $result['data']['MyTpl']['r'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLUncheckedRadioProducesNoEntry(): void {
		$result = $this->parseHTML(
			'<input type="radio" name="MyTpl[r]" value="A" />'
		);
		$this->assertArrayNotHasKey( 'MyTpl', $result['data'] );
	}

	// --- textarea ---

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLTextareaExtractsContent(): void {
		$result = $this->parseHTML( '<textarea name="MyTpl[txt]">some text</textarea>' );
		$this->assertSame( 'some text', $result['data']['MyTpl']['txt'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLTextareaWithNoNameIsIgnored(): void {
		$result = $this->parseHTML( '<textarea>orphan</textarea>' );
		$this->assertSame( [], $result['data'] );
	}

	// --- select (single) ---

	/**
	 * Single-select: when no option is marked selected, the first option
	 * is used as the default value.
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLSingleSelectDefaultsToFirstOption(): void {
		$result = $this->parseHTML(
			'<select name="MyTpl[s]">' .
			'<option value="first">First</option>' .
			'<option value="second">Second</option>' .
			'</select>'
		);
		$this->assertSame( 'first', $result['data']['MyTpl']['s'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLSingleSelectSelectedOptionOverridesDefault(): void {
		$result = $this->parseHTML(
			'<select name="MyTpl[s]">' .
			'<option value="first">First</option>' .
			'<option value="second" selected>Second</option>' .
			'</select>'
		);
		$this->assertSame( 'second', $result['data']['MyTpl']['s'] );
	}

	/**
	 * When an option has no value attribute and is selected, its text
	 * content is used as the value.
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLSelectOptionWithNoValueUsesTextContent(): void {
		$result = $this->parseHTML(
			'<select name="MyTpl[s]">' .
			'<option selected>LabelOnly</option>' .
			'</select>'
		);
		$this->assertSame( 'LabelOnly', $result['data']['MyTpl']['s'] );
	}

	// --- select (multiple) ---

	/**
	 * Multi-select: no default to first; only explicitly selected options feed
	 * into addToArray(), which overwrites on repeated calls with the same key,
	 * so the *last* selected option wins.
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLMultiSelectLastSelectedOptionWins(): void {
		$result = $this->parseHTML(
			'<select name="MyTpl[m]" multiple>' .
			'<option value="a" selected>A</option>' .
			'<option value="b">B</option>' .
			'<option value="c" selected>C</option>' .
			'</select>'
		);
		// addToArray overwrites on the same key — last selected ('c') wins.
		$this->assertSame( 'c', $result['data']['MyTpl']['m'] );
	}

	// --- disabled / restricted fields ---

	/**
	 * A disabled input must NOT appear in $data, and its key must be
	 * removed from $this->mOptions (restricted-field cleanup).
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLDisabledInputIsNotExtractedAndIsRemovedFromOptions(): void {
		$result = $this->parseHTML(
			'<input type="text" name="MyTpl[restricted]" value="secret" disabled />',
			[ 'MyTpl' => [ 'restricted' => 'preexisting' ] ]
		);
		$this->assertArrayNotHasKey( 'MyTpl', $result['data'] );
		// the restricted field must be removed from mOptions
		$this->assertArrayNotHasKey( 'restricted', $result['mOptions']['MyTpl'] ?? [] );
	}

	/**
	 * A disabled select must NOT appear in $data, and its key must be
	 * removed from $this->mOptions.
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLDisabledSelectIsNotExtractedAndIsRemovedFromOptions(): void {
		$result = $this->parseHTML(
			'<select name="MyTpl[locked]" disabled>' .
			'<option value="v" selected>V</option>' .
			'</select>',
			[ 'MyTpl' => [ 'locked' => 'preexisting' ] ]
		);
		$this->assertArrayNotHasKey( 'MyTpl', $result['data'] );
		$this->assertArrayNotHasKey( 'locked', $result['mOptions']['MyTpl'] ?? [] );
	}

	/**
	 * An input with no name attribute must be silently ignored.
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLInputWithNoNameIsIgnored(): void {
		$result = $this->parseHTML( '<input type="text" value="orphan" />' );
		$this->assertSame( [], $result['data'] );
	}

	/**
	 * Multiple fields in one fragment are all captured.
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 */
	public function testParseHTMLMixedFragmentExtractsAllFields(): void {
		$html  = '<input type="text" name="T[a]" value="va" />';
		$html .= '<textarea name="T[b]">vb</textarea>';
		$html .= '<select name="T[c]"><option value="vc" selected>VC</option></select>';

		$result = $this->parseHTML( $html );
		$this->assertSame( 'va', $result['data']['T']['a'] );
		$this->assertSame( 'vb', $result['data']['T']['b'] );
		$this->assertSame( 'vc', $result['data']['T']['c'] );
	}

	// -------------------------------------------------------------------------
	// FauxRequest / formHTML integration
	//
	// These tests guard against the "dual request-access" bug: PF_AutoeditAPI
	// spoofs the request context via RequestContext::getMain()->setRequest()
	// before calling formHTML(). Any call site that reads 'global $wgRequest'
	// instead of RequestContext::getMain()->getRequest() silently bypasses the
	// spoof and loses all submitted field values, producing a bare template call
	// like {{Tpl}} instead of {{Tpl|field=value}}.
	// -------------------------------------------------------------------------

	/**
	 * formHTML() called with a FauxRequest on the RequestContext must produce
	 * wikitext that contains the submitted field value.
	 *
	 * This is the regression test for the setFieldValuesFromSubmit() bug:
	 * the function used `global $wgRequest` which is not updated by
	 * RequestContext::setRequest(), so template params were always lost in
	 * the autoedit save path.
	 *
	 * @covers \PFTemplateInForm::setFieldValuesFromSubmit
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testFormHtmlWithFauxRequestProducesTemplateCallWithParams(): void {
		global $wgPageFormsFormPrinter;

		$templateName = 'AETestTpl';
		$fieldName = 'text';
		$fieldValue = 'Hello FauxRequest';

		$formDef = "{{{for template|$templateName}}}\n"
			. "{{{field|$fieldName}}}\n"
			. "{{{end template}}}";

		// Build the same FauxRequest that PF_AutoeditAPI would set on the context.
		$options = [
			$templateName => [ $fieldName => $fieldValue ],
		];
		$oldRequest = RequestContext::getMain()->getRequest();
		RequestContext::getMain()->setRequest( new FauxRequest( $options, true ) );

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

		RequestContext::getMain()->setRequest( $oldRequest );

		$this->assertStringContainsString(
			"|$fieldName=$fieldValue",
			$pageText,
			'formHTML() with a FauxRequest on RequestContext must include submitted field values in the page text'
		);
	}

	/**
	 * Counterpart: formHTML() called WITHOUT a FauxRequest (real-request path)
	 * must not include a field value for a key that was never submitted.
	 *
	 * Guards against false positives in the test above.
	 *
	 * @covers \PFFormPrinter::formHTML
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
	// These tests cover the val_modifier code path in PFFormPrinter::formHTML()
	// (the Gesinn Patch). PFFormFieldTest already verifies that getCurrentValue()
	// returns the raw submitted value; here we verify that the concatenation or
	// subtraction against the existing page value is performed correctly.
	// -------------------------------------------------------------------------

	/**
	 * The append modifier (field+=value) must concatenate the submitted value
	 * onto the existing field value using the field's delimiter.
	 *
	 * @covers \PFFormPrinter::formHTML
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
		$oldRequest = RequestContext::getMain()->getRequest();
		RequestContext::getMain()->setRequest( new FauxRequest( $options, true ) );

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
			$user = $this->getTestUser()->getUser()
		);

		RequestContext::getMain()->setRequest( $oldRequest );

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
	 * @covers \PFFormPrinter::formHTML
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
		$oldRequest = RequestContext::getMain()->getRequest();
		RequestContext::getMain()->setRequest( new FauxRequest( $options, true ) );

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
			$user = $this->getTestUser()->getUser()
		);

		RequestContext::getMain()->setRequest( $oldRequest );

		$this->assertStringContainsString(
			"|{$fieldName}=val1{$delimiter}val3",
			$pageText,
			'Remove modifier (field-=value) must remove specified value from existing list'
		);
		$this->assertStringNotContainsString(
			'val2',
			$pageText,
			'Remove modifier must not leave the removed value in the page text'
		);
	}

}
