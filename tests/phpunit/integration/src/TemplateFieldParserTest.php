<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\TemplateText\TemplateFieldParser;
use MediaWikiIntegrationTestCase;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * The reading of the text of a template on its own, without a Template and without a page. The
 * fields found for each syntax are pinned by TemplateFieldsCharacterizationTest.
 *
 * @group PF
 * @group Database
 * @covers \MediaWiki\Extension\PageForms\TemplateText\TemplateFieldParser
 */
class TemplateFieldParserTest extends MediaWikiIntegrationTestCase {

	/**
	 * An "#arraymap" call that the regular expression for it can only fail on, if the limit is low.
	 */
	private function tooComplexText(): string {
		return '{{#arraymap:{{{' . str_repeat( 'x', 120 ) . '|}}}';
	}

	/**
	 * @param TemplateFieldParser $parser
	 * @param string $text
	 * @param array|null $params
	 * @return string[] The name of each field by its key
	 */
	private function names( TemplateFieldParser $parser, string $text, ?array $params = null ): array {
		return array_map(
			static fn ( $field ) => $field->getFieldName(),
			$parser->parse( $text, $params )
		);
	}

	public function testTheFieldsAreKeyedByThePositionOfTheirNamesInTheText() {
		$this->assertSame(
			[ 3 => 'Title', 16 => 'Author' ],
			$this->names( new TemplateFieldParser(), '{{{Title|}}} {{{Author|}}}' )
		);
	}

	public function testTheFieldsAreInTheOrderTheyWereFoundAndNotInTheOrderOfTheText() {
		// A property call is looked for before a field without a property.
		$this->assertSame(
			[ 29 => 'Apple', 3 => 'Zebra' ],
			$this->names( new TemplateFieldParser(), '{{{Zebra|}}} [[Has apple::{{{Apple|}}}]]' )
		);
	}

	public function testAPropertyIsAttachedToItsField() {
		$fields = ( new TemplateFieldParser() )->parse( '[[Has apple::{{{Apple|}}}]]', null );

		$this->assertCount( 1, $fields );
		$field = reset( $fields );
		$this->assertSame( 'Has apple', $field->getSemanticProperty() );
		$this->assertFalse( (bool)$field->isList() );
	}

	public function testAnArraymapPropertyIsAList() {
		$fields = ( new TemplateFieldParser() )->parse(
			'{{#arraymap:{{{Authors|}}}|,|x|[[Has author::x]]}}',
			null
		);

		$field = reset( $fields );
		$this->assertSame( 'Authors', $field->getFieldName() );
		$this->assertSame( 'Has author', $field->getSemanticProperty() );
		$this->assertTrue( $field->isList() );
	}

	public function testTheFieldsOfTemplateParamsComeAfterTheFieldsOfTheTextUnderTheirNames() {
		$names = $this->names(
			new TemplateFieldParser(),
			'{{{Title|}}}',
			[ 'Author' => [ 'label' => 'Written by' ], 'Title' => [ 'label' => 'ignored' ] ]
		);

		$this->assertSame( [ 3 => 'Title', 'Author' => 'Author' ], $names );
	}

	public function testPropertyFieldReturnsThePositionAndTheField() {
		[ $position, $field ] = ( new TemplateFieldParser() )->propertyField(
			'{{{Title|}}} {{{Tags|}}}',
			'Tags',
			'Has tag',
			true
		);

		$this->assertSame( 16, $position );
		$this->assertSame( 'Tags', $field->getFieldName() );
		$this->assertSame( 'Has tag', $field->getSemanticProperty() );
		$this->assertTrue( $field->isList() );
	}

	public function testPropertyFieldWithoutAPipeAfterTheNameGetsThePositionOfTheName() {
		[ $position ] = ( new TemplateFieldParser() )->propertyField( '{{{Tags}}}', 'Tags', 'Has tag', false );

		$this->assertSame( 3, $position );
	}

	public function testATextThatIsTooComplexIsReportedAndNotThrown() {
		$limit = ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.backtrack_limit', '2000' );
		$reported = 0;
		try {
			$parser = new TemplateFieldParser( static function () use ( &$reported ) {
				$reported++;
			} );
			$parser->parse( $this->tooComplexText(), null );
		} finally {
			ini_set( 'pcre.backtrack_limit', $limit );
		}

		$this->assertSame( 1, $reported );
	}

	public function testATextThatIsTooComplexIsNotReportedWithoutAHandler() {
		$limit = ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.backtrack_limit', '2000' );
		try {
			$fields = ( new TemplateFieldParser() )->parse( $this->tooComplexText(), null );
		} finally {
			ini_set( 'pcre.backtrack_limit', $limit );
		}

		$this->assertIsArray( $fields );
	}

	public function testTwoFieldsWithoutAPipeAfterTheirNamesAreBothFound() {
		$names = $this->names( new TemplateFieldParser(), '[[Has a::{{{A}}}]] [[Has b::{{{B}}}]]' );

		$this->assertSame( [ 'A', 'B' ], array_values( $names ) );
	}

	public function testAFieldWithoutAPipeKeepsThePositionOfItsName() {
		$this->assertSame(
			[ 12 => 'A' ],
			$this->names( new TemplateFieldParser(), '[[Has a::{{{A}}}]]' )
		);
	}

	public function testAFieldWhoseNameStartsAnotherNameIsNotLost() {
		$names = $this->names( new TemplateFieldParser(), '{{{AB|}}} {{{A|}}}' );

		$this->assertSame( [ 'AB', 'A' ], array_values( $names ) );
	}
}
