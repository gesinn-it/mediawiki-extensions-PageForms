<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers PFTemplateParams
 */
class PFTemplateParamsTest extends TestCase {

	// ── parseWikitextString() ────────────────────────────────────────────

	/**
	 * A plain field name with no parenthesised parameters is returned as-is
	 * with an empty params array.
	 */
	public function testPlainFieldNameNoParams() {
		[ $fieldName, $fieldParams ] = PFTemplateParams::parseWikitextString( 'MyField' );

		$this->assertSame( 'MyField', $fieldName );
		$this->assertSame( [], $fieldParams );
	}

	/**
	 * Leading/trailing whitespace around the field name is trimmed.
	 */
	public function testFieldNameIsTrimmed() {
		[ $fieldName, ] = PFTemplateParams::parseWikitextString( '  Trimmed  ' );
		$this->assertSame( 'Trimmed', $fieldName );
	}

	/**
	 * An empty string produces an empty field name and no params.
	 */
	public function testEmptyStringProducesEmptyFieldName() {
		[ $fieldName, $fieldParams ] = PFTemplateParams::parseWikitextString( '' );
		$this->assertSame( '', $fieldName );
		$this->assertSame( [], $fieldParams );
	}

	/**
	 * A boolean parameter (no equals sign) is stored with value true.
	 */
	public function testBooleanParamIsStoredAsTrue() {
		[ $fieldName, $fieldParams ] = PFTemplateParams::parseWikitextString( 'Color (mandatory)' );

		$this->assertSame( 'Color', $fieldName );
		$this->assertArrayHasKey( 'mandatory', $fieldParams );
		$this->assertTrue( $fieldParams['mandatory'] );
	}

	/**
	 * A key=value parameter stores the trimmed value under the lowercase key.
	 * Note: ';' is the param separator so values themselves must not contain it.
	 */
	public function testKeyValueParam() {
		[ $fieldName, $fieldParams ] = PFTemplateParams::parseWikitextString( 'Status (type=string)' );

		$this->assertSame( 'Status', $fieldName );
		$this->assertArrayHasKey( 'type', $fieldParams );
		$this->assertSame( 'string', $fieldParams['type'] );
	}

	/**
	 * Parameter key names are normalised to lowercase.
	 */
	public function testParamKeyIsLowercased() {
		[ , $fieldParams ] = PFTemplateParams::parseWikitextString( 'Field (Type=string)' );
		$this->assertArrayHasKey( 'type', $fieldParams );
		$this->assertSame( 'string', $fieldParams['type'] );
	}

	/**
	 * Multiple semicolon-separated parameters are all parsed.
	 */
	public function testMultipleParams() {
		[ $fieldName, $fieldParams ] = PFTemplateParams::parseWikitextString(
			'Field (type=string; mandatory; default=foo)'
		);

		$this->assertSame( 'Field', $fieldName );
		$this->assertSame( 'string', $fieldParams['type'] );
		$this->assertTrue( $fieldParams['mandatory'] );
		$this->assertSame( 'foo', $fieldParams['default'] );
	}

	/**
	 * Whitespace around semicolons and around the parenthesised block is ignored.
	 */
	public function testWhitespaceAroundSemicolonsIsIgnored() {
		[ $fieldName, $fieldParams ] = PFTemplateParams::parseWikitextString(
			'  MyProp  (  key1 = val1 ; key2 = val2  )'
		);

		$this->assertSame( 'MyProp', $fieldName );
		$this->assertSame( 'val1', $fieldParams['key1'] );
		$this->assertSame( 'val2', $fieldParams['key2'] );
	}

	/**
	 * When a param value contains an equals sign, only the first '=' is used
	 * as the key/value separator (explode with limit=2).
	 */
	public function testParamValueWithEqualsSign() {
		[ , $fieldParams ] = PFTemplateParams::parseWikitextString(
			'Field (query=[[Category:Foo]]|?Bar=baz)'
		);
		$this->assertArrayHasKey( 'query', $fieldParams );
		$this->assertSame( '[[Category:Foo]]|?Bar=baz', $fieldParams['query'] );
	}
}
