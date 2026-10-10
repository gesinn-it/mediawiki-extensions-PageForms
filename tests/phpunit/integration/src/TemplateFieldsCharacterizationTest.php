<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWikiIntegrationTestCase;
use ReflectionProperty;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * Characterization (golden master) tests for Template::loadTemplateFieldsSMWAndOther(), which
 * finds the fields of a template in its text: which fields, with which property, in which order
 * and under which key of the list.
 *
 * The result for each text is kept as a snapshot in tests/phpunit/integration/golden/templatefields,
 * so that the structure of the code that reads the text can change without a change of what it
 * finds. The snapshots also hold two quirks on purpose, because a refactoring must not change them:
 *
 * - A field is found under the position of its name in the text, and a name that is not followed by
 *   "|" has no position, so that it gets the key 0 and replaces another field with that key.
 * - Names count as found already if they are loosely equal, so "1" and "01" are one field.
 *
 * To record the snapshots again after an intended change, run the tests with the environment
 * variable PF_UPDATE_GOLDEN=1 and review the diff of the snapshot files.
 *
 * @group PF
 * @group Database
 * @covers \MediaWiki\Extension\PageForms\Template::loadTemplateFieldsSMWAndOther
 * @covers \MediaWiki\Extension\PageForms\Template::loadPropertySettingInTemplate
 */
class TemplateFieldsCharacterizationTest extends MediaWikiIntegrationTestCase {

	private const GOLDEN_DIR = __DIR__ . '/../golden/templatefields';

	public static function provideTexts(): iterable {
		yield 'plain-fields' => [ "{{{Title|}}}\n{{{Author|}}}\n{{{ Year }}}", null ];
		yield 'a-field-is-found-once' => [ "{{{Title|}}} {{{Title|x}}} {{#if:{{{Title|}}}|a|b}}", null ];
		yield 'normal-property-calls' => [
			"[[Has title::{{{Title|}}}]]\n[[Has author::{{{Author|}}}]] [[Has year:: {{{ Year }}} ]]", null ];
		yield 'arraymap' => [
			"{{#arraymap:{{{Authors|}}}|,|x|[[Has author::x]]}} {{{Title|}}}", null ];
		yield 'set-inside-arraymap' => [
			"{{#arraymap:{{{Tags|}}}|,|x|{{#set:Has tag=x}}}} {{{Title|}}}", null ];
		yield 'set-set_internal-and-subobject' => [
			"{{#set:Has a={{{A|}}}|Has b={{{B|}}}}}{{#set_internal:Has c|Has d={{{D|}}}}}" .
			"{{#subobject:-|Has e={{{E|}}}}} {{{Z|}}}", null ];
		yield 'declare' => [ "{{#declare:Has color=Color|Has size=Size|bad}} {{{Z|}}}", null ];
		yield 'the-order-of-the-text-wins' => [
			"{{{Zebra|}}}\n[[Has apple::{{{Apple|}}}]]\n{{#arraymap:{{{Mango|}}}|,|x|[[Has mango::x]]}}", null ];
		yield 'noinclude-and-includeonly-are-not-looked-at-here' => [
			"<includeonly>{{{Shown|}}}</includeonly><noinclude>{{{Hidden|}}}</noinclude>", null ];
		yield 'a-name-without-a-pipe-has-no-position' => [
			"[[Has a::{{{A}}}]] [[Has b::{{{B}}}]] {{{C|}}}", null ];
		yield 'names-that-are-loosely-equal-are-one-field' => [ "{{{1|}}} {{{01|}}} {{{1e1|}}} {{{10|}}}", null ];
		yield 'a-label-is-capitalized' => [ "{{{first name|}}} [[Has surname::{{{surname|}}}]]", null ];
		yield 'no-fields' => [ 'Just text.', null ];
		yield 'params-only' => [
			'Just text.',
			[ 'Name' => [ 'label' => 'Full name', 'property' => 'Has name' ],
				'Tags' => [ 'list' => true, 'delimiter' => ';' ] ] ];
		yield 'params-after-the-fields-of-the-text-and-not-sorted' => [
			"{{{Zebra|}}}\n[[Has apple::{{{Apple|}}}]]",
			[ 'Mango' => [ 'label' => 'M' ], 'Apple' => [ 'label' => 'ignored' ], 'Banana' => [] ] ];
	}

	/**
	 * @dataProvider provideTexts
	 */
	public function testFields( string $text, ?array $params ) {
		$name = $this->dataName();
		$template = new Template( 'Probe', [] );
		$this->set( $template, 'mTemplateText', $text );
		$this->set( $template, 'mTemplateParams', $params );

		$template->loadTemplateFieldsSMWAndOther();

		$fields = [];
		foreach ( $template->getTemplateFields() as $key => $field ) {
			$fields[] = [
				'key' => $key,
				'name' => $field->getFieldName(),
				'label' => $field->getLabel(),
				'property' => $field->getSemanticProperty(),
				'list' => $field->isList(),
				'delimiter' => $field->getDelimiter(),
			];
		}
		$this->assertGolden( (string)$name, $fields );
	}

	/**
	 * Fields that are there already stay, and the new ones are put among them.
	 *
	 * The order is not asserted: the keys are a position (a number) and a name, and ksort() puts
	 * the two kinds in a different order before PHP 8.2 than since.
	 */
	public function testFieldsOfTheTemplateAreKept() {
		$template = new Template( 'Probe', [ 'Own' => TemplateField::create( 'Own', 'Own' ) ] );
		$this->set( $template, 'mTemplateText', "{{{A|}}}" );
		$this->set( $template, 'mTemplateParams', null );

		$template->loadTemplateFieldsSMWAndOther();

		$this->assertEquals(
			[ 3 => 'A', 'Own' => 'Own' ],
			array_map(
				static fn ( $field ) => $field->getFieldName(),
				$template->getTemplateFields()
			)
		);
	}

	private function set( Template $template, string $property, $value ): void {
		$reflection = new ReflectionProperty( Template::class, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $template, $value );
	}

	private function assertGolden( string $name, array $actual ): void {
		$file = self::GOLDEN_DIR . '/' . preg_replace( '/[^A-Za-z0-9]+/', '-', $name ) . '.json';
		$json = json_encode( $actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";

		if ( getenv( 'PF_UPDATE_GOLDEN' ) ) {
			if ( !is_dir( self::GOLDEN_DIR ) ) {
				mkdir( self::GOLDEN_DIR, 0775, true );
			}
			file_put_contents( $file, $json );
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->assertFileExists( $file, "No snapshot for '$name'. Record it with PF_UPDATE_GOLDEN=1." );
		$this->assertSame( file_get_contents( $file ), $json, "The fields found in the text changed for '$name'" );
	}
}
