<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\CategoryLinksSchema;
use MediaWiki\Linker\LinksMigration;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\CategoryLinksSchema
 */
class CategoryLinksSchemaTest extends TestCase {

	public function testCategoryColumnLayoutReadsCategoryFromCategorylinks(): void {
		$schema = CategoryLinksSchema::withCategoryColumn();

		$this->assertSame( [ 'categorylinks' ], $schema->tables() );
		$this->assertSame( [], $schema->joins() );
		$this->assertSame( 'cl_to', $schema->categoryField() );
	}

	public function testLinktargetLayoutIsTakenFromTheCore(): void {
		if ( !isset( LinksMigration::$mapping['categorylinks'] ) ) {
			$this->markTestSkipped( 'The core does not migrate the category links' );
		}
		$linksMigration = $this->createMock( LinksMigration::class );
		$linksMigration->method( 'getQueryInfo' )->with( 'categorylinks' )->willReturn( [
			'tables' => [ 'categorylinks', 'linktarget' ],
			'fields' => [ 'cl_target_id', 'lt_namespace', 'lt_title' ],
			'joins' => [ 'linktarget' => [ 'JOIN', [ 'cl_target_id = lt_id' ] ] ],
		] );
		$linksMigration->method( 'getTitleFields' )->with( 'categorylinks' )
			->willReturn( [ 'lt_namespace', 'lt_title' ] );

		$schema = CategoryLinksSchema::forLinksMigration( $linksMigration );

		$this->assertSame( [ 'categorylinks', 'linktarget' ], $schema->tables() );
		$this->assertSame( [ 'linktarget' => [ 'JOIN', [ 'cl_target_id = lt_id' ] ] ], $schema->joins() );
		$this->assertSame( 'lt_title', $schema->categoryField() );
	}

	public function testCoreWithoutCategoryMigrationKeepsTheCategoryColumn(): void {
		if ( isset( LinksMigration::$mapping['categorylinks'] ) ) {
			$this->markTestSkipped( 'The core migrates the category links' );
		}
		$linksMigration = $this->createMock( LinksMigration::class );
		$linksMigration->expects( $this->never() )->method( 'getQueryInfo' );

		$schema = CategoryLinksSchema::forLinksMigration( $linksMigration );

		$this->assertSame( 'cl_to', $schema->categoryField() );
		$this->assertSame( [ 'categorylinks' ], $schema->tables() );
	}
}
