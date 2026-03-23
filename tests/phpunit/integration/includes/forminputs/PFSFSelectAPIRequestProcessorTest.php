<?php

/**
 * @covers \PFSFSelectAPIRequestProcessor
 */
class PFSFSelectAPIRequestProcessorTest extends MediaWikiIntegrationTestCase {

	private function makeProcessor( ?callable $smwMock = null ): PFSFSelectAPIRequestProcessor {
		// getServiceContainer() was introduced in MW 1.36.
		if ( version_compare( MW_VERSION, '1.36', '>=' ) ) {
			$services = $this->getServiceContainer();
		} else {
			$services = \MediaWiki\MediaWikiServices::getInstance();
		}
		$parser = $services->getParserFactory()->create();
		$parser->setTitle( Title::newFromText( 'Test' ) );
		$parser->setOptions( ParserOptions::newFromAnon() );
		$parser->resetOutput();
		return new PFSFSelectAPIRequestProcessor( $parser, $smwMock );
	}

	/**
	 * SMW must be called with format=plainlist and a sep parameter matching
	 * the field delimiter so that the result can be split into individual
	 * option values.
	 */
	public function testQueryUsesFormatPlainlistAndInjectsSep() {
		$capturedParams = null;
		$processor = $this->makeProcessor( static function ( array $rawparams ) use ( &$capturedParams ) {
			$capturedParams = $rawparams;
			return 'SFS Item 1;SFS Item 2';
		} );

		$processor->getJsonDecodedResultValuesForRequestParameters( [
			'query' => '[[Category:SFS Item]];format=list;link=none;limit=500',
			'sep' => ';',
			'approach' => 'smw',
		] );

		$this->assertContains( 'format=plainlist', $capturedParams,
			'SMW must be called with format=plainlist' );
		$this->assertContains( 'sep=;', $capturedParams,
			'sep matching the field delimiter must be injected' );
	}

	/**
	 * The API result must be split into separate option values by the sep
	 * character, with a leading empty option prepended.
	 */
	public function testResultIsSplitByDelimiter() {
		$processor = $this->makeProcessor( static function () {
			return 'SFS Item 1;SFS Item 2';
		} );

		$result = $processor->getJsonDecodedResultValuesForRequestParameters( [
			'query' => '[[Category:SFS Item]];format=list;link=none;limit=500',
			'sep' => ';',
			'approach' => 'smw',
		] );

		$this->assertSame( [ '', 'SFS Item 1', 'SFS Item 2' ], $result->values );
	}

	/**
	 * A custom sep (e.g. '@@') must be injected into the SMW params and used
	 * to split the result.
	 */
	public function testCustomSepIsRespected() {
		$capturedParams = null;
		$processor = $this->makeProcessor( static function ( array $rawparams ) use ( &$capturedParams ) {
			$capturedParams = $rawparams;
			return 'SFS Item 1@@SFS Item 2@@SFS Item 3';
		} );

		$result = $processor->getJsonDecodedResultValuesForRequestParameters( [
			'query' => '[[Category:SFS Item]];format=list;link=none;limit=500',
			'sep' => '@@',
			'approach' => 'smw',
		] );

		$this->assertContains( 'sep=@@', $capturedParams );
		$this->assertSame( [ '', 'SFS Item 1', 'SFS Item 2', 'SFS Item 3' ], $result->values );
	}

	/**
	 * If the query string already contains a sep= or format= parameter, it must
	 * be stripped and the canonical values injected exactly once.
	 */
	public function testExistingSepAndFormatInQueryAreNormalized() {
		$capturedParams = null;
		$processor = $this->makeProcessor( static function ( array $rawparams ) use ( &$capturedParams ) {
			$capturedParams = $rawparams;
			return 'SFS Item 1@@SFS Item 2';
		} );

		$processor->getJsonDecodedResultValuesForRequestParameters( [
			'query' => '[[Category:SFS Item]];format=plainlist;sep=@@;limit=500',
			'sep' => '@@',
			'approach' => 'smw',
		] );

		$sepParams = array_filter( $capturedParams, static function ( $p ) {
			return strncmp( $p, 'sep=', 4 ) === 0;
		} );
		$this->assertCount( 1, $sepParams, 'sep must appear exactly once in rawparams' );
		$this->assertContains( 'sep=@@', $capturedParams );

		$formatParams = array_filter( $capturedParams, static function ( $p ) {
			return strncmp( $p, 'format=', 7 ) === 0;
		} );
		$this->assertCount( 1, $formatParams, 'format must appear exactly once' );
		$this->assertContains( 'format=plainlist', $capturedParams );
	}
}
