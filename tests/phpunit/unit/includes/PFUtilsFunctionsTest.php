<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * The functions of PFUtils that only work on strings and arrays, and need no wiki.
 *
 * @covers PFUtils::convertBackToPipes
 * @covers PFUtils::smartSplitFormTag
 * @covers PFUtils::getFormTagComponents
 * @covers PFUtils::arrayMergeRecursiveDistinct
 */
class PFUtilsFunctionsTest extends TestCase {

	/**
	 * @dataProvider provideConvertBackToPipes
	 */
	public function testConvertBackToPipes( string $text, string $expected ): void {
		$this->assertSame( $expected, PFUtils::convertBackToPipes( $text ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provideConvertBackToPipes(): array {
		return [
			'replaces the control character' => [ "a\1b\1c", 'a|b|c' ],
			'leaves a text without it alone' => [ 'abc', 'abc' ],
		];
	}

	/**
	 * @dataProvider provideSmartSplitFormTag
	 * @param string $tag
	 * @param string[] $expected
	 */
	public function testSmartSplitFormTag( string $tag, array $expected ): void {
		$this->assertSame( $expected, PFUtils::smartSplitFormTag( $tag ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string[]}>
	 */
	public static function provideSmartSplitFormTag(): array {
		return [
			'an empty string gives an empty array' => [ '', [] ],
			'a single token' => [ 'foo', [ 'foo' ] ],
			'a simple split' => [ 'foo|bar|baz', [ 'foo', 'bar', 'baz' ] ],
			'no split inside curly brackets' => [ '{{tmpl|arg}}|after', [ '{{tmpl|arg}}', 'after' ] ],
			'whitespace is trimmed' => [ ' foo | bar ', [ 'foo', 'bar' ] ],
			'nested curly brackets' => [ '{{outer|{{inner|x}}}}|y', [ '{{outer|{{inner|x}}}}', 'y' ] ],
		];
	}

	/**
	 * @dataProvider provideFormTagComponents
	 * @param string $tag
	 * @param string[] $expected
	 */
	public function testGetFormTagComponents( string $tag, array $expected ): void {
		$this->assertSame( $expected, PFUtils::getFormTagComponents( $tag ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string[]}>
	 */
	public static function provideFormTagComponents(): array {
		return [
			'simple' => [ 'a|b|c', [ 'a', 'b', 'c' ] ],
			'keeps a pipe inside a template call' => [
				'field|default={{tmpl|arg}}|label=test',
				[ 'field', 'default={{tmpl|arg}}', 'label=test' ],
			],
			'nested template call' => [ 'x|{{f|{{g|y}}}}|z', [ 'x', '{{f|{{g|y}}}}', 'z' ] ],
		];
	}

	/**
	 * @dataProvider provideArrayMerges
	 * @param array $a
	 * @param array $b
	 * @param array $expected
	 */
	public function testArrayMergeRecursiveDistinct( array $a, array $b, array $expected ): void {
		$this->assertSame( $expected, PFUtils::arrayMergeRecursiveDistinct( $a, $b ) );
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
