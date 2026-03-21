<?php

/**
 * @covers \PFAutoeditAPI
 * @group PF
 * @group Database
 * @group medium
 */
class PFAutoeditAPITest extends ApiTestCase {

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

}
