<?php

declare( strict_types=1 );

/**
 * @covers \PFSFSelectAPI
 * @group Database
 */
class PFSFSelectAPITest extends ApiTestCase {

	public function testExecuteReturnsSplitValuesAndCountForNonSmwApproach(): void {
		[ $result ] = $this->doApiRequest( [
			'action' => 'sformsselect',
			'approach' => 'parser',
			'query' => 'a,b,c',
			'sep' => ',',
		] );

		$this->assertSame( [ '', 'a', 'b', 'c' ], $result['sformsselect']['values'] );
		$this->assertSame( 4, $result['sformsselect']['count'] );
	}

	public function testExecuteRequiresApproachParameter(): void {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'sformsselect',
			'query' => 'a,b,c',
			'sep' => ',',
		] );
	}

	public function testExecuteRequiresQueryParameter(): void {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'sformsselect',
			'approach' => 'parser',
			'sep' => ',',
		] );
	}

	/**
	 * getAllowedParams() declares 'sep' as optional (PARAM_REQUIRED => false,
	 * no PARAM_DFLT), but PFSFSelectAPIRequestProcessor::getJsonDecoded-
	 * ResultValuesForRequestParameters() treats a missing 'sep' as a hard
	 * error: it throws a plain InvalidArgumentException, which propagates
	 * out of execute() uncaught rather than being surfaced as a clean
	 * ApiUsageException (e.g. "sep parameter is required"). This documents
	 * the actual (surprising) behaviour rather than the one the "optional"
	 * declaration implies; the mismatch is a pre-existing inconsistency
	 * between the param declaration and the processor, not something
	 * introduced by this test.
	 */
	public function testMissingSepParameterThrowsUncaughtInvalidArgumentException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Missing required query/sep parameter' );

		$this->doApiRequest( [
			'action' => 'sformsselect',
			'approach' => 'parser',
			'query' => 'a,b,c',
		] );
	}
}
