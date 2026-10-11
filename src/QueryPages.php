<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;
use SMWQueryProcessor;

/**
 * The pages of a Semantic MediaWiki query, as the values of a "values from query" field.
 *
 * @ingroup PF
 */
class QueryPages {

	/**
	 * Returns an array of the names of pages that are the result of an SMW query.
	 *
	 * @param string $rawQuery the query string like [[Category:Trees]][[age::>1000]]
	 * @return array
	 */
	public static function get( string $rawQuery ): array {
		$rawQueryArray = [ $rawQuery ];
		[ $queryObj ] = SMWQueryProcessor::getQueryAndParamsFromFunctionParams(
			$rawQueryArray, SMW_OUTPUT_WIKI, SMWQueryProcessor::SPECIAL_PAGE, false
		);
		$res = PFUtils::getSMWStore()->getQueryResult( $queryObj );
		$rows = $res->getResults();
		$pages = [];
		foreach ( $rows as $row ) {
			$pages[] = $row->getDbKey();
		}

		return $pages;
	}
}
