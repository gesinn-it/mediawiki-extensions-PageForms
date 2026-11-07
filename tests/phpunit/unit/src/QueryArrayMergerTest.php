<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\QueryArrayMerger;
use PHPUnit\Framework\TestCase;

/**
 * QueryArrayMerger needs no wiki.
 *
 * @covers \MediaWiki\Extension\PageForms\QueryArrayMerger
 */
class QueryArrayMergerTest extends TestCase {

	/**
	 * @dataProvider provideArrayMerges
	 * @param array $a
	 * @param array $b
	 * @param array $expected
	 */
	public function testArrayMergeRecursiveDistinct( array $a, array $b, array $expected ): void {
		$this->assertSame( $expected, QueryArrayMerger::arrayMergeRecursiveDistinct( $a, $b ) );
	}

	/**
	 * @return array<string, array{0: array, 1: array, 2: array}>
	 */
	public static function provideArrayMerges(): array {
		return [
			'overwrites a scalar' => [ [ 'key' => 'old' ], [ 'key' => 'new' ], [ 'key' => 'new' ] ],
			'merges nested arrays' => [
				[ 'sub' => [ 'x' => 1, 'y' => 2 ] ],
				[ 'sub' => [ 'y' => 99, 'z' => 3 ] ],
				[ 'sub' => [ 'x' => 1, 'y' => 99, 'z' => 3 ] ],
			],
			'adds new keys' => [ [ 'a' => 1 ], [ 'b' => 2 ], [ 'a' => 1, 'b' => 2 ] ],
			'an empty second array changes nothing' => [ [ 'a' => 1 ], [], [ 'a' => 1 ] ],
		];
	}
}
