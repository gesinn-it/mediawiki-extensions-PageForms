<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormPlaceholder;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\FormPlaceholder
 */
class FormPlaceholderTest extends TestCase {

	/**
	 * @dataProvider provideFormat
	 */
	public function testFormat( string $templateName, string $fieldName, string $expected ): void {
		$this->assertSame( $expected, FormPlaceholder::format( $templateName, $fieldName ) );
	}

	public static function provideFormat(): array {
		return [
			'plain names' => [ 'MyTemplate', 'MyField', 'MyTemplate___MyField' ],
			'underscores converted to spaces' => [ 'My_Template', 'My_Field', 'My Template___My Field' ],
			'mixed underscores and spaces' => [ 'Foo_Bar', 'Baz', 'Foo Bar___Baz' ],
			'empty field name' => [ 'Tpl', '', 'Tpl___' ],
		];
	}

	/**
	 * @dataProvider provideToHtmlMarker
	 */
	public function testToHtmlMarker( string $placeholder, string $expected ): void {
		$this->assertSame( $expected, FormPlaceholder::toHtmlMarker( $placeholder ) );
	}

	public static function provideToHtmlMarker(): array {
		return [
			'simple placeholder' => [ 'MyTemplate___MyField', '@insert"HTML_MyTemplate___MyField@' ],
			'with spaces' => [ 'My Template___My Field', '@insert"HTML_My Template___My Field@' ],
			'empty string' => [ '', '@insert"HTML_@' ],
		];
	}

	public function testRoundTrip(): void {
		$template = 'Embed_Template';
		$field = 'Embed_Field';
		$placeholder = FormPlaceholder::format( $template, $field );
		$marker = FormPlaceholder::toHtmlMarker( $placeholder );

		$this->assertSame( 'Embed Template___Embed Field', $placeholder );
		$this->assertStringContainsString( $placeholder, $marker );
	}
}
