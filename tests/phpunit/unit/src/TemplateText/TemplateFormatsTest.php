<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\TemplateText\InfoboxTemplateFormat;
use MediaWiki\Extension\PageForms\TemplateText\PlainTemplateFormat;
use MediaWiki\Extension\PageForms\TemplateText\SectionsTemplateFormat;
use MediaWiki\Extension\PageForms\TemplateText\TableTemplateFormat;
use MediaWiki\Extension\PageForms\TemplateText\TemplateFormat;
use MediaWiki\Extension\PageForms\TemplateText\TemplateFormats;
use MediaWiki\Extension\PageForms\TemplateText\UnformattedTemplateFormat;
use PHPUnit\Framework\TestCase;

/**
 * The markup of each display format of a created template, and the choice of the format by name.
 *
 * @covers \MediaWiki\Extension\PageForms\TemplateText\TemplateFormats
 * @covers \MediaWiki\Extension\PageForms\TemplateText\TableTemplateFormat
 * @covers \MediaWiki\Extension\PageForms\TemplateText\InfoboxTemplateFormat
 * @covers \MediaWiki\Extension\PageForms\TemplateText\PlainTemplateFormat
 * @covers \MediaWiki\Extension\PageForms\TemplateText\SectionsTemplateFormat
 * @covers \MediaWiki\Extension\PageForms\TemplateText\UnformattedTemplateFormat
 */
class TemplateFormatsTest extends TestCase {

	public static function provideNames(): iterable {
		yield 'standard' => [ 'standard', TableTemplateFormat::class ];
		yield 'infobox' => [ 'infobox', InfoboxTemplateFormat::class ];
		yield 'plain' => [ 'plain', PlainTemplateFormat::class ];
		yield 'sections' => [ 'sections', SectionsTemplateFormat::class ];
		yield 'no name means standard' => [ null, TableTemplateFormat::class ];
		yield 'empty name means standard' => [ '', TableTemplateFormat::class ];
		yield 'zero as a name means standard' => [ '0', TableTemplateFormat::class ];
		yield 'unknown name' => [ 'fancy', UnformattedTemplateFormat::class ];
		yield 'the names are case sensitive' => [ 'Plain', UnformattedTemplateFormat::class ];
	}

	/**
	 * @dataProvider provideNames
	 */
	public function testFromName( ?string $name, string $expectedClass ) {
		$this->assertSame( $expectedClass, get_class( TemplateFormats::fromName( $name ) ) );
	}

	/**
	 * The text of every method of every format, so that a change of the markup shows up as a diff
	 * of one row.
	 */
	public static function provideMarkup(): iterable {
		$methods = static function ( TemplateFormat $f ): array {
			return [
				'open' => $f->open(),
				'header' => $f->header( 'L', false ),
				'header, not first' => $f->header( 'L', true ),
				'nonemptyLead' => $f->nonemptyLead(),
				'nonemptyHeader' => $f->nonemptyHeader( 'L', false ),
				'nonemptyHeader, not first' => $f->nonemptyHeader( 'L', true ),
				'nonemptySeparator' => $f->nonemptySeparator(),
				'valueCell' => $f->valueCell(),
				'nonemptyValueCell' => $f->nonemptyValueCell(),
				'aggregationHeader' => $f->aggregationHeader( 'L', false ),
				'aggregationHeader, with fields' => $f->aggregationHeader( 'L', true ),
				'close' => $f->close(),
				'trailingNewline' => $f->trailingNewline(),
			];
		};

		yield 'standard' => [ new TableTemplateFormat(), [
			'open' => "{| class=\"wikitable\"\n",
			'header' => "! L\n",
			'header, not first' => "|-\n! L\n",
			'nonemptyLead' => '',
			'nonemptyHeader' => "! L\n",
			'nonemptyHeader, not first' => "\n{{!}}-\n! L\n",
			'nonemptySeparator' => '{{!}}',
			'valueCell' => '| ',
			'nonemptyValueCell' => '{{!}} ',
			'aggregationHeader' => "! L\n|",
			'aggregationHeader, with fields' => "|-\n! L\n|",
			'close' => '|}',
			'trailingNewline' => "\n",
		], $methods ];

		yield 'plain' => [ new PlainTemplateFormat(), [
			'open' => '',
			'header' => "\n'''L:''' ",
			'header, not first' => "\n'''L:''' ",
			'nonemptyLead' => "\n",
			'nonemptyHeader' => "'''L:''' ",
			'nonemptyHeader, not first' => "'''L:''' ",
			'nonemptySeparator' => '',
			'valueCell' => '',
			'nonemptyValueCell' => '',
			'aggregationHeader' => "\n'''L:''' ",
			'aggregationHeader, with fields' => "\n'''L:''' ",
			'close' => '',
			'trailingNewline' => '',
		], $methods ];

		yield 'sections' => [ new SectionsTemplateFormat(), [
			'open' => '',
			'header' => "\n==L==\n",
			'header, not first' => "\n==L==\n",
			'nonemptyLead' => "\n",
			'nonemptyHeader' => "==L==\n",
			'nonemptyHeader, not first' => "==L==\n",
			'nonemptySeparator' => '',
			'valueCell' => '',
			'nonemptyValueCell' => '',
			'aggregationHeader' => "\n==L==\n",
			'aggregationHeader, with fields' => "\n==L==\n",
			'close' => '',
			'trailingNewline' => '',
		], $methods ];

		yield 'unformatted' => [ new UnformattedTemplateFormat(), [
			'open' => '',
			'header' => '',
			'header, not first' => '',
			'nonemptyLead' => '',
			'nonemptyHeader' => '',
			'nonemptyHeader, not first' => '',
			'nonemptySeparator' => '',
			'valueCell' => '',
			'nonemptyValueCell' => '',
			'aggregationHeader' => '',
			'aggregationHeader, with fields' => '',
			'close' => '',
			'trailingNewline' => '',
		], $methods ];
	}

	/**
	 * @dataProvider provideMarkup
	 */
	public function testMarkup( TemplateFormat $format, array $expected, callable $methods ) {
		$this->assertSame( $expected, $methods( $format ) );
	}

	public function testInfoboxOpensWithAFloatingTableAndATitleRow() {
		$open = ( new InfoboxTemplateFormat() )->open();

		$this->assertStringStartsWith( '{| style="width: 30em;', $open );
		$this->assertStringContainsString( 'float: right; clear: right;', $open );
		$this->assertStringContainsString( '{{PAGENAME}}', $open );
		$this->assertStringEndsWith( "|-\n\n", $open );
	}

	public function testInfoboxTakesTheRestFromTheStandardTable() {
		$infobox = new InfoboxTemplateFormat();
		$standard = new TableTemplateFormat();

		$this->assertSame( $standard->header( 'L', true ), $infobox->header( 'L', true ) );
		$this->assertSame( $standard->close(), $infobox->close() );
	}
}
