<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;

/**
 * The parts of the queries on the page table that find pages for an autocompletion substring.
 *
 * @ingroup PF
 */
class AutocompleteSql {

	/**
	 * Returns a SQL condition for autocompletion substring value in a column.
	 *
	 * @param string $column Value column name
	 * @param string $substring Substring to look for
	 * @param bool $replaceSpaces
	 * @return string SQL condition for use in WHERE clause
	 */
	public static function conditionForColumn( string $column, string $substring, bool $replaceSpaces = true ): string {
		global $wgPageFormsAutocompleteOnAllChars;

		$db = PFUtils::getReplicaDB();

		// CONVERT() is also supported in PostgreSQL, but it doesn't
		// seem to work the same way.
		if ( $db->getType() == 'mysql' ) {
			$column_value = "LOWER(CONVERT($column USING utf8))";
		} else {
			$column_value = "LOWER($column)";
		}

		$substring = strtolower( $substring );
		if ( $replaceSpaces ) {
			$substring = str_replace( ' ', '_', $substring );
		}

		if ( $wgPageFormsAutocompleteOnAllChars ) {
			return $column_value . $db->buildLike( $db->anyString(), $substring, $db->anyString() );
		} else {
			$sqlCond = $column_value . $db->buildLike( $substring, $db->anyString() );
			$spaceRepresentation = $replaceSpaces ? '_' : ' ';
			$wordSeparators = [ $spaceRepresentation, '/', '(', ')', '-', '\'', '\"' ];
			foreach ( $wordSeparators as $wordSeparator ) {
				$sqlCond .= ' OR ' . $column_value .
					$db->buildLike( $db->anyString(), $wordSeparator . $substring, $db->anyString() );
			}
			return $sqlCond;

		}
	}

	/**
	 * What a query on the page table needs to also get the display title and the default sort key
	 * of each page: the page_props tables and columns, and the joins for them. The columns
	 * are named pp_displaytitle_value and pp_defaultsort_value.
	 *
	 * @return array{0: array<string, string>, 1: array<string, string>, 2: array} The tables to add,
	 *   the columns to add and the join conditions
	 */
	public static function displayTitleAndSortKeyParts(): array {
		$tables = [
			'pp_displaytitle' => 'page_props',
			'pp_defaultsort' => 'page_props',
		];
		$columns = [
			'pp_displaytitle_value' => 'pp_displaytitle.pp_value',
			'pp_defaultsort_value' => 'pp_defaultsort.pp_value',
		];
		$join = [
			'pp_displaytitle' => [
				'LEFT JOIN', [
					'pp_displaytitle.pp_page = page_id',
					'pp_displaytitle.pp_propname = \'displaytitle\''
				]
			],
			'pp_defaultsort' => [
				'LEFT JOIN', [
					'pp_defaultsort.pp_page = page_id',
					'pp_defaultsort.pp_propname = \'defaultsort\''
				]
			]
		];
		return [ $tables, $columns, $join ];
	}
}
