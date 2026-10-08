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
}
