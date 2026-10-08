<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\NestedInputValues;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\NestedInputValues
 */
class NestedInputValuesTest extends TestCase {

	public function testTopLevelKeyAddsStringValue(): void {
		$data = [];
		NestedInputValues::addToArray( $data, 'key', 'value' );
		$this->assertSame( [ 'key' => 'value' ], $data );
	}

	public function testNestedKeyCreatesNestedArray(): void {
		$data = [];
		NestedInputValues::addToArray( $data, 'template[field]', 'val' );
		$this->assertSame( [ 'template' => [ 'field' => 'val' ] ], $data );
	}

	public function testTopLevelSpaceIsEncodedAsUnderscore(): void {
		$data = [];
		NestedInputValues::addToArray( $data, 'my template[field]', 'val' );
		$this->assertArrayHasKey( 'my_template', $data );
	}

	public function testEmptyKeyAppendsValue(): void {
		$data = [];
		NestedInputValues::addToArray( $data, '', 'a' );
		NestedInputValues::addToArray( $data, '', 'b' );
		$this->assertContains( 'a', $data );
		$this->assertContains( 'b', $data );
	}

	public function testNumericSubkeyOfAParentGetsTheInstanceSuffix(): void {
		$data = [];
		// Non-top-level numeric key inside a parent key: parent['0a'][...]
		NestedInputValues::addToArray( $data, 'T[0][field]', 'v', false );
		$this->assertArrayHasKey( '0a', $data['T'] );
	}

	public function testAStringDoesNotOverwriteAnExistingChildArray(): void {
		$data = [ 'T' => [ 'f' => 'old' ] ];
		NestedInputValues::addToArray( $data, 'T', 'should not overwrite' );
		$this->assertIsArray( $data['T'] );
	}

	public function testTheOldClassNameStillWorks(): void {
		$data = [];
		\MediaWiki\Extension\PageForms\HtmlFormDataExtractor::addToArray( $data, 'T[f]', 'v' );
		$this->assertSame( [ 'T' => [ 'f' => 'v' ] ], $data );
	}
}
