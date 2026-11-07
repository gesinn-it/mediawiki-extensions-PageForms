<?php

use MediaWiki\Extension\PageForms\AutocompleteApi;

/**
 * @covers \MediaWiki\Extension\PageForms\AutocompleteApi
 * @group PF
 * @group Database
 * @group medium
 */
class PFAutocompleteAPITest extends ApiTestCase {

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteApi::shiftExactMatch
	 */
	public function testShiftExactMatchMovesExactMatchToFront() {
		$values = [ 'foobar', 'foo', 'foobaz' ];
		$result = AutocompleteApi::shiftExactMatch( 'foo', $values );
		$this->assertSame( [ 'foo', 'foobar', 'foobaz' ], array_values( $result ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteApi::shiftExactMatch
	 */
	public function testShiftExactMatchLeavesArrayUnchangedWhenNoMatch() {
		$values = [ 'foobar', 'foobaz' ];
		$result = AutocompleteApi::shiftExactMatch( 'foo', $values );
		$this->assertSame( [ 'foobar', 'foobaz' ], array_values( $result ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteApi::shiftExactMatch
	 */
	public function testShiftExactMatchLeavesArrayUnchangedWhenMatchIsAlreadyFirst() {
		$values = [ 'foo', 'foobar', 'foobaz' ];
		$result = AutocompleteApi::shiftExactMatch( 'foo', $values );
		$this->assertSame( [ 'foo', 'foobar', 'foobaz' ], array_values( $result ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteApi::shiftExactMatch
	 */
	public function testShiftExactMatchWithEmptyArray() {
		$result = AutocompleteApi::shiftExactMatch( 'foo', [] );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteApi::execute
	 */
	public function testExecuteThrowsErrorWhenSubstrIsMissing() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( [
			'action' => 'pfautocomplete',
		] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteApi::execute
	 */
	public function testExecuteReturnsEmptyResultWhenBasepropSetButPropertyOmitted() {
		[ $result ] = $this->doApiRequest( [
			'action' => 'pfautocomplete',
			'baseprop' => 'SomeProperty',
			'basevalue' => 'SomeValue',
		] );
		$this->assertSame( [], $result['pfautocomplete'] );
	}

}
