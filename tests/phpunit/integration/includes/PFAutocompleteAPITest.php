<?php

/**
 * @covers \PFAutocompleteAPI
 * @group PF
 * @group Database
 * @group medium
 */
class PFAutocompleteAPITest extends ApiTestCase {

	/**
	 * @covers \PFAutocompleteAPI::shiftExactMatch
	 */
	public function testShiftExactMatchMovesExactMatchToFront() {
		$values = [ 'foobar', 'foo', 'foobaz' ];
		$result = PFAutocompleteAPI::shiftExactMatch( 'foo', $values );
		$this->assertSame( [ 'foo', 'foobar', 'foobaz' ], array_values( $result ) );
	}

	/**
	 * @covers \PFAutocompleteAPI::shiftExactMatch
	 */
	public function testShiftExactMatchLeavesArrayUnchangedWhenNoMatch() {
		$values = [ 'foobar', 'foobaz' ];
		$result = PFAutocompleteAPI::shiftExactMatch( 'foo', $values );
		$this->assertSame( [ 'foobar', 'foobaz' ], array_values( $result ) );
	}

	/**
	 * @covers \PFAutocompleteAPI::shiftExactMatch
	 */
	public function testShiftExactMatchLeavesArrayUnchangedWhenMatchIsAlreadyFirst() {
		$values = [ 'foo', 'foobar', 'foobaz' ];
		$result = PFAutocompleteAPI::shiftExactMatch( 'foo', $values );
		$this->assertSame( [ 'foo', 'foobar', 'foobaz' ], array_values( $result ) );
	}

	/**
	 * @covers \PFAutocompleteAPI::shiftExactMatch
	 */
	public function testShiftExactMatchWithEmptyArray() {
		$result = PFAutocompleteAPI::shiftExactMatch( 'foo', [] );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \PFAutocompleteAPI::execute
	 */
	public function testExecuteThrowsErrorWhenSubstrIsMissing() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( [
			'action' => 'pfautocomplete',
		] );
	}

}
