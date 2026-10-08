<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\HtmlFormDataExtractor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor
 */
class HtmlFormDataExtractorTest extends TestCase {

	public function testTopLevelKeyAddsStringValue(): void {
		$data = [];
		HtmlFormDataExtractor::addToArray( $data, 'key', 'value' );
		$this->assertSame( [ 'key' => 'value' ], $data );
	}

	public function testNestedKeyCreatesNestedArray(): void {
		$data = [];
		HtmlFormDataExtractor::addToArray( $data, 'template[field]', 'val' );
		$this->assertSame( [ 'template' => [ 'field' => 'val' ] ], $data );
	}

	public function testTopLevelSpaceIsEncodedAsUnderscore(): void {
		$data = [];
		HtmlFormDataExtractor::addToArray( $data, 'my template[field]', 'val' );
		$this->assertArrayHasKey( 'my_template', $data );
	}

	public function testEmptyKeyAppendsValue(): void {
		$data = [];
		HtmlFormDataExtractor::addToArray( $data, '', 'a' );
		HtmlFormDataExtractor::addToArray( $data, '', 'b' );
		$this->assertContains( 'a', $data );
		$this->assertContains( 'b', $data );
	}

	public function testNumericSubkeyOfAParentGetsTheInstanceSuffix(): void {
		$data = [];
		// Non-top-level numeric key inside a parent key: parent['0a'][...]
		HtmlFormDataExtractor::addToArray( $data, 'T[0][field]', 'v', false );
		$this->assertArrayHasKey( '0a', $data['T'] );
	}

	public function testAStringDoesNotOverwriteAnExistingChildArray(): void {
		$data = [ 'T' => [ 'f' => 'old' ] ];
		HtmlFormDataExtractor::addToArray( $data, 'T', 'should not overwrite' );
		$this->assertIsArray( $data['T'] );
	}

	/**
	 * What a submitted form sends for each kind of input, read from the HTML of the form.
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 * @dataProvider provideHtml
	 * @param string $html
	 * @param array $expected
	 */
	public function testExtractReadsTheValuesTheFormWouldSend( string $html, array $expected ): void {
		$mOptions = [];
		// The order of the fields does not matter, only what is sent for which field.
		$this->assertEquals( $expected, HtmlFormDataExtractor::extract( $html, $mOptions ) );
	}

	/**
	 * @return array<string, array{0: string, 1: array}>
	 */
	public static function provideHtml(): array {
		return [
			'text input' => [
				'<input type="text" name="MyTpl[field]" value="hello" />',
				[ 'MyTpl' => [ 'field' => 'hello' ] ],
			],
			'input without a type is a text input' => [
				'<input name="MyTpl[field]" value="implicit" />',
				[ 'MyTpl' => [ 'field' => 'implicit' ] ],
			],
			'hidden input' => [
				'<input type="hidden" name="MyTpl[h]" value="hv" />',
				[ 'MyTpl' => [ 'h' => 'hv' ] ],
			],
			'checked checkbox' => [
				'<input type="checkbox" name="MyTpl[cb]" value="yes" checked />',
				[ 'MyTpl' => [ 'cb' => 'yes' ] ],
			],
			'unchecked checkbox sends nothing' => [
				'<input type="checkbox" name="MyTpl[cb]" value="yes" />',
				[],
			],
			'checked radio button of two' => [
				'<input type="radio" name="MyTpl[r]" value="A" checked />'
					. '<input type="radio" name="MyTpl[r]" value="B" />',
				[ 'MyTpl' => [ 'r' => 'A' ] ],
			],
			'unchecked radio button sends nothing' => [
				'<input type="radio" name="MyTpl[r]" value="A" />',
				[],
			],
			'textarea' => [
				'<textarea name="MyTpl[txt]">some text</textarea>',
				[ 'MyTpl' => [ 'txt' => 'some text' ] ],
			],
			'textarea without a name is ignored' => [
				'<textarea>orphan</textarea>',
				[],
			],
			'input without a name is ignored' => [
				'<input type="text" value="orphan" />',
				[],
			],
			'single select without a selected option sends the first one' => [
				'<select name="MyTpl[s]"><option value="first">First</option>'
					. '<option value="second">Second</option></select>',
				[ 'MyTpl' => [ 's' => 'first' ] ],
			],
			'single select sends the selected option' => [
				'<select name="MyTpl[s]"><option value="first">First</option>'
					. '<option value="second" selected>Second</option></select>',
				[ 'MyTpl' => [ 's' => 'second' ] ],
			],
			'option without a value sends its text' => [
				'<select name="MyTpl[s]"><option selected>LabelOnly</option></select>',
				[ 'MyTpl' => [ 's' => 'LabelOnly' ] ],
			],
			// There is no default to the first option; of the selected ones the last overwrites the others.
			'multiple select sends the last selected option' => [
				'<select name="MyTpl[m]" multiple><option value="a" selected>A</option>'
					. '<option value="b">B</option><option value="c" selected>C</option></select>',
				[ 'MyTpl' => [ 'm' => 'c' ] ],
			],
			'several kinds of fields in one fragment' => [
				'<input type="text" name="T[a]" value="va" />'
					. '<textarea name="T[b]">vb</textarea>'
					. '<select name="T[c]"><option value="vc" selected>VC</option></select>',
				[ 'T' => [ 'a' => 'va', 'b' => 'vb', 'c' => 'vc' ] ],
			],
		];
	}

	/**
	 * A disabled field is not sent by a browser, so it is not read, and the value of it that
	 * the options already hold is removed (restricted field cleanup).
	 *
	 * @covers \MediaWiki\Extension\PageForms\HtmlFormDataExtractor::extract
	 * @dataProvider provideDisabledFields
	 * @param string $html
	 * @param string $field
	 */
	public function testExtractIgnoresADisabledFieldAndRemovesItFromTheOptions( string $html, string $field ): void {
		$mOptions = [ 'MyTpl' => [ $field => 'preexisting' ] ];

		$data = HtmlFormDataExtractor::extract( $html, $mOptions );

		$this->assertArrayNotHasKey( 'MyTpl', $data );
		$this->assertArrayNotHasKey( $field, $mOptions['MyTpl'] ?? [] );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provideDisabledFields(): array {
		return [
			'disabled input' => [
				'<input type="text" name="MyTpl[restricted]" value="secret" disabled />',
				'restricted',
			],
			'disabled select' => [
				'<select name="MyTpl[locked]" disabled><option value="v" selected>V</option></select>',
				'locked',
			],
		];
	}
}
