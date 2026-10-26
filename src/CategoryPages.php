<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Title\Title;
use PFUtils;

/**
 * The pages of a category and its subcategories, as the values of a "values from category" field.
 *
 * @ingroup PF
 */
class CategoryPages {

	/**
	 * Get all the pages that belong to a category and all its
	 * subcategories, down a certain number of levels - heavily based on
	 * SMW's SMWInlineQuery::includeSubcategories().
	 *
	 * @param string $topCategory
	 * @param int $numLevels
	 * @param string|null $substring
	 * @return string[] The display title of each page by its name, sorted by the sort key of the
	 *   page. With no levels, just the category.
	 */
	public static function get( string $topCategory, int $numLevels, ?string $substring = null ): array {
		if ( $numLevels === 0 ) {
			return [ $topCategory ];
		}

		$topCategory = str_replace( ' ', '_', $topCategory );
		$categories = [ $topCategory ];
		$categoriesToCheck = [ $topCategory ];
		$pages = [];
		$sortkeys = [];
		for ( $level = $numLevels; $level > 0; $level-- ) {
			$newCategories = [];
			foreach ( $categoriesToCheck as $category ) {
				$res = self::queryMembers( $category, $substring );
				if ( $res ) {
					$newCategories = array_merge(
						$newCategories, self::readMembers( $res, $categories, $pages, $sortkeys )
					);
				}
			}
			if ( $newCategories === [] ) {
				break;
			}
			$categories = array_merge( $categories, $newCategories );
			$categoriesToCheck = $newCategories;
		}
		return self::fixedMultiSort( $sortkeys, $pages );
	}

	/**
	 * array_multisort() unfortunately messes up array keys that are
	 * numeric - they get converted to 0, 1, etc. There are a few ways to
	 * get around this, but I (Yaron) couldn't get those working, so
	 * instead we're going with this hack, where all key values get
	 * appended with a '@' before sorting, which is then removed after
	 * sorting. It's inefficient, but it's probably good enough.
	 *
	 * @param string[] $sortkeys
	 * @param string[] $pages
	 * @return string[] a sorted version of $pages, sorted via $sortkeys
	 */
	public static function fixedMultiSort( array $sortkeys, array $pages ): array {
		array_multisort( $sortkeys, $pages );
		$newPages = [];
		foreach ( $pages as $key => $value ) {
			$fixedKey = rtrim( (string)$key, '@' );
			$newPages[$fixedKey] = $value;
		}
		return $newPages;
	}

	/**
	 * The names of the subcategories that are directly in the category, in the order of their sort keys.
	 *
	 * @param string $category
	 * @return string[] The names with spaces, without the namespace
	 */
	public static function subcategories( string $category ): array {
		$schema = CategoryLinksSchema::current();
		$res = PFUtils::getReplicaDB()->select(
			array_merge( $schema->tables(), [ 'page' ] ),
			[ 'page_title' ],
			[
				'cl_from = page_id',
				$schema->categoryField() => str_replace( ' ', '_', $category ),
				'page_namespace' => NS_CATEGORY,
			],
			__METHOD__,
			[ 'ORDER BY' => 'cl_type, cl_sortkey' ],
			$schema->joins()
		);
		$names = [];
		foreach ( $res as $row ) {
			$names[] = str_replace( '_', ' ', $row->page_title );
		}
		return $names;
	}

	/**
	 * The pages and subcategories that are directly in the category.
	 *
	 * @param string $category
	 * @param string|null $substring
	 * @return \Wikimedia\Rdbms\IResultWrapper|bool
	 */
	private static function queryMembers( string $category, ?string $substring ) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

		$schema = CategoryLinksSchema::current();
		$tables = array_merge( $schema->tables(), [ 'page' ] );
		$columns = [ 'page_title', 'page_namespace' ];
		$conditions = [ 'cl_from = page_id', $schema->categoryField() => $category ];
		$join = $schema->joins();
		if ( $wgPageFormsUseDisplayTitle ) {
			[ $displayTables, $displayColumns, $displayJoin ] = AutocompleteSql::displayTitleAndSortKeyParts();
			$tables = array_merge( $tables, $displayTables );
			$columns = array_merge( $columns, $displayColumns );
			$join = array_merge( $join, $displayJoin );
		}
		if ( $substring != null ) {
			$conditions[] = self::substringCondition( $substring );
		}
		return PFUtils::getReplicaDB()->select(
			$tables,
			$columns,
			$conditions,
			__METHOD__,
			[
				'ORDER BY' => 'cl_type, cl_sortkey',
				'LIMIT' => $wgPageFormsMaxAutocompleteValues
			],
			$join
		);
	}

	/**
	 * Subcategories always match, so that the pages below them are found.
	 *
	 * @param string $substring
	 * @return string
	 */
	private static function substringCondition( string $substring ): string {
		global $wgPageFormsUseDisplayTitle;

		if ( !$wgPageFormsUseDisplayTitle ) {
			return AutocompleteSql::conditionForColumn( 'page_title', $substring ) .
				' OR page_namespace = ' . NS_CATEGORY;
		}
		return '((pp_displaytitle.pp_value IS NULL OR pp_displaytitle.pp_value = \'\') AND (' .
			AutocompleteSql::conditionForColumn( 'page_title', $substring ) .
			')) OR ' .
			AutocompleteSql::conditionForColumn( 'pp_displaytitle.pp_value', $substring, false ) .
			' OR page_namespace = ' . NS_CATEGORY;
	}

	/**
	 * Add the pages of the rows to the pages found so far.
	 *
	 * @param \Wikimedia\Rdbms\IResultWrapper|bool $res
	 * @param string[] $knownCategories The categories that were already looked at
	 * @param string[] &$pages The display titles, by name and '@'
	 * @param string[] &$sortkeys The sort keys, by name
	 * @return string[] The subcategories that are not known yet
	 */
	private static function readMembers( $res, array $knownCategories, array &$pages, array &$sortkeys ): array {
		$newCategories = [];
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			if ( !array_key_exists( 'page_title', $row ) ) {
				continue;
			}
			$namespace = (int)$row['page_namespace'];
			$name = $row['page_title'];
			if ( $namespace === NS_CATEGORY ) {
				if ( !in_array( $name, $knownCategories ) ) {
					$newCategories[] = $name;
				}
				continue;
			}
			$title = Title::makeTitleSafe( $namespace, $name );
			if ( $title === null ) {
				// This can happen if it's a "phantom" page, in a namespace that no longer exists.
				continue;
			}
			$value = $title->getPrefixedText();
			if ( !array_key_exists( $value . '@', $pages ) ) {
				$pages[$value . '@'] = DisplayTitles::resolve( $row['pp_displaytitle_value'] ?? null, $value );
				$sortkeys[$value] = $row['pp_defaultsort_value'] ?? $value;
			}
		}
		$res->free();
		return $newCategories;
	}
}
