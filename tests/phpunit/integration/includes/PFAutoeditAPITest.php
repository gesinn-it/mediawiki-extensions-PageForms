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
