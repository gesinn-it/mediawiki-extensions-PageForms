<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;
use Title;

/**
 * The categories of a page, and all the categories of the wiki.
 *
 * @ingroup PF
 */
class PageCategories {

	/**
	 * Helper function - gets names of categories for a page;
	 * based on Title::getParentCategories(), but simpler.
	 *
	 * @param Title $title
	 * @return array
	 */
	public static function forPage( Title $title ): array {
		$categories = [];
		$db = PFUtils::getReplicaDB();
		$titlekey = $title->getArticleID();
		if ( $titlekey == 0 ) {
			// Something's wrong - exit
			return $categories;
		}
		$conditions = [ 'cl_from' => $titlekey ];
		$res = $db->select(
			'categorylinks',
			'DISTINCT cl_to',
			$conditions,
			__METHOD__
		);
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			$categories[] = $row['cl_to'];
		}
		$res->free();
		return $categories;
	}

	/**
	 * Helper function - returns names of all the categories.
	 * @return array
	 */
	public static function all(): array {
		$categories = [];
		$db = PFUtils::getReplicaDB();
		$res = $db->select(
			'category',
			'cat_title',
			[],
			__METHOD__
		);
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			$categories[] = $row['cat_title'];
		}
		$res->free();
		return $categories;
	}
}
