<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormTagParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormTagParser
 */
class FormTagParserTest extends TestCase {

	/**
	 * @dataProvider provideConvertBackToPipes
	 */
	public function testConvertBackToPipes( string $text, string $expected ): void {
		$this->assertSame( $expected, FormTagParser::convertBackToPipes( $text ) );
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
		$this->assertSame( $expected, FormTagParser::smartSplitFormTag( $tag ) );
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
		$this->assertSame( $expected, FormTagParser::getFormTagComponents( $tag ) );
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

	public function testStrReplaceFirstReplacesOnlyTheFirstOccurrence(): void {
		$this->assertSame( 'X b a', FormTagParser::strReplaceFirst( 'a', 'X', 'a b a' ) );
	}

	public function testStrReplaceFirstLeavesATextWithoutTheNeedleAlone(): void {
		$this->assertSame( 'abc', FormTagParser::strReplaceFirst( 'z', 'X', 'abc' ) );
	}
}
