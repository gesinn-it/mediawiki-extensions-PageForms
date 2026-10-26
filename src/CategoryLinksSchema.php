<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Linker\LinksMigration;
use MediaWiki\MediaWikiServices;

/**
 * How the categories of a page are stored in the database of the running MediaWiki.
 *
 * The category of a "categorylinks" row used to be the column "cl_to". MediaWiki moves it to the
 * "linktarget" table, and 1.46 no longer has the column. Queries take the tables, the joins and the
 * field with the name of the category from here, so that they work with both layouts.
 *
 * @ingroup PF
 */
final class CategoryLinksSchema {

	/** @var string[] */
	private array $tables;

	/** @var array[] */
	private array $joins;

	private string $categoryField;

	/**
	 * @param string[] $tables
	 * @param array[] $joins
	 * @param string $categoryField
	 */
	public function __construct( array $tables, array $joins, string $categoryField ) {
		$this->tables = $tables;
		$this->joins = $joins;
		$this->categoryField = $categoryField;
	}

	/**
	 * The layout of the running MediaWiki.
	 *
	 * @return self
	 */
	public static function current(): self {
		$services = MediaWikiServices::getInstance();
		if ( !method_exists( $services, 'getLinksMigration' ) ) {
			return self::withCategoryColumn();
		}
		return self::forLinksMigration( $services->getLinksMigration() );
	}

	/**
	 * @param LinksMigration $linksMigration
	 * @return self
	 */
	public static function forLinksMigration( LinksMigration $linksMigration ): self {
		// Cores before 1.45 know nothing about the migration of the category links
		if ( !isset( LinksMigration::$mapping['categorylinks'] ) ) {
			return self::withCategoryColumn();
		}
		$queryInfo = $linksMigration->getQueryInfo( 'categorylinks' );
		$titleFields = $linksMigration->getTitleFields( 'categorylinks' );
		return new self( $queryInfo['tables'], $queryInfo['joins'], (string)$titleFields[1] );
	}

	/**
	 * The layout with the category in "cl_to".
	 *
	 * @return self
	 */
	public static function withCategoryColumn(): self {
		return new self( [ 'categorylinks' ], [], 'cl_to' );
	}

	/**
	 * The tables to select from, "categorylinks" among them.
	 *
	 * @return string[]
	 */
	public function tables(): array {
		return $this->tables;
	}

	/**
	 * The join conditions of the tables, to be merged into those of the query.
	 *
	 * @return array[]
	 */
	public function joins(): array {
		return $this->joins;
	}

	/**
	 * The field that holds the name of the category, with underscores instead of spaces.
	 *
	 * @return string
	 */
	public function categoryField(): string {
		return $this->categoryField;
	}
}
