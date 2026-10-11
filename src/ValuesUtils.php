<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;
use PFUtils;
use SMWQueryProcessor;
use Title;

/**
 * Static functions for handling lists of values and labels.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class ValuesUtils {

	/**
	 * Helper function - gets names of categories for a page;
	 * based on Title::getParentCategories(), but simpler.
	 *
	 * @param Title $title
	 * @return array
	 */
	public static function getCategoriesForPage( $title ) {
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
	public static function getAllCategories() {
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

	/**
	 * Creates an array of values that match the specified source name and
	 * type, for use by both Javascript autocompletion and comboboxes.
	 *
	 * @param string|null $source_name
	 * @param string $source_type
	 * @return string[]
	 */
	public static function getAutocompleteValues( $source_name, $source_type ) {
		if ( $source_name === null ) {
			return [];
		}

		// The query depends on whether this is an SMW
		// property, category, SMW concept or namespace.
		if ( $source_type == 'property' ) {
			$names_array = PropertyValues::all( $source_name );
		} elseif ( $source_type == 'category' ) {
			$names_array = CategoryPages::get( $source_name, 10 );
		} elseif ( $source_type == 'concept' ) {
			$names_array = ConceptPages::get( $source_name );
		} elseif ( $source_type == 'query' ) {
			$names_array = self::getAllPagesForQuery( $source_name );
		} elseif ( $source_type == 'wikidata' ) {
			$names_array = WikidataValues::fetch( $source_name );
			sort( $names_array );
		} else {
			// i.e., $source_type == 'namespace'
			$names_array = NamespacePages::get( $source_name );
		}
		return $names_array;
	}

	/**
	 * Helper function to get an array of values out of what may be either
	 * an array or a delimited string.
	 *
	 * @param string[]|string $value
	 * @param string $delimiter
	 * @return string[]
	 */
	public static function getValuesArray( $value, $delimiter ) {
		if ( is_array( $value ) ) {
			return $value;
		} else {
			// Remove extra spaces.
			return array_map( 'trim', explode( $delimiter, $value ?? '' ) );
		}
	}

	public static function getValuesFromExternalURL( $external_url_alias, $substring ) {
		global $wgPageFormsAutocompletionURLs;
		if ( $wgPageFormsAutocompletionURLs === [] ) {
			return wfMessage( 'pf-nocompletionurls' );
		}
		if ( !array_key_exists( $external_url_alias, $wgPageFormsAutocompletionURLs ) ) {
			return wfMessage( 'pf-invalidexturl' );
		}
		$url = $wgPageFormsAutocompletionURLs[$external_url_alias];
		if ( $url === '' ) {
			return wfMessage( 'pf-blankexturl' );
		}
		$url = str_replace( '<substr>', urlencode( $substring ), $url );
		$page_contents = MediaWikiServices::getInstance()->getHttpRequestFactory()->get( $url, [], __METHOD__ );
		if ( $page_contents === null || $page_contents === '' ) {
			return wfMessage( 'pf-externalpageempty' );
		}
		$data = json_decode( $page_contents );
		if ( $data === null || $data === [] ) {
			return wfMessage( 'pf-externalpagebadjson' );
		}
		$return_values = [];
		foreach ( $data->pfautocomplete as $val ) {
			$return_values[] = (array)$val;
		}
		return $return_values;
	}

	/**
	 * Returns an array of the names of pages that are the result of an SMW query.
	 *
	 * @param string $rawQuery the query string like [[Category:Trees]][[age::>1000]]
	 * @return array
	 */
	public static function getAllPagesForQuery( $rawQuery ) {
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

	/**
	 * Doing "mapping" on values can potentially lead to more than one
	 * value having the same "label". To avoid this, we find duplicate
	 * labels, if there are any, add on the real value, in parentheses,
	 * to all of them.
	 *
	 * @param array $labels
	 * @return array
	 */
	public static function disambiguateLabels( array $labels ) {
		asort( $labels );
		if ( count( $labels ) == count( array_unique( $labels ) ) ) {
			return $labels;
		}
		$fixed_labels = [];
		foreach ( $labels as $value => $label ) {
			$fixed_labels[$value] = $labels[$value];
		}
		$counts = array_count_values( $fixed_labels );
		foreach ( $counts as $current_label => $count ) {
			if ( $count > 1 ) {
				$matching_keys = array_keys( $labels, $current_label );
				foreach ( $matching_keys as $key ) {
					$fixed_labels[$key] .= ' (' . $key . ')';
				}
			}
		}
		if ( count( $fixed_labels ) == count( array_unique( $fixed_labels ) ) ) {
			return $fixed_labels;
		}
		// If that didn't work, just add on " (value)" to *all* the
		// labels. @TODO - is this necessary?
		foreach ( $labels as $value => $label ) {
			$labels[$value] .= ' (' . $value . ')';
		}
		return $labels;
	}

	/**
	 * @deprecated use DisplayTitles::resolve()
	 * @param string|null $raw
	 * @param string $fallback
	 * @return string
	 */
	public static function resolveDisplayTitle( ?string $raw, string $fallback ): string {
		return DisplayTitles::resolve( $raw, $fallback );
	}

	/**
	 * @deprecated use DisplayTitles::forPages()
	 * @param string[] $pageTitles
	 * @return array
	 */
	public static function addDisplayTitlesForPageValues( array $pageTitles ): array {
		return DisplayTitles::forPages( $pageTitles );
	}

	/**
	 * @deprecated use WikidataValues::fetch()
	 * @param string $query
	 * @param string|null $substring
	 * @return array
	 */
	public static function getAllValuesFromWikidata( $query, $substring = null ) {
		return WikidataValues::fetch( $query, $substring );
	}

	/**
	 * @deprecated use WikidataValues::buildQuery()
	 * @param string $query
	 * @param string|null $substring
	 * @return string
	 */
	public static function buildWikidataSparqlQuery( $query, $substring = null ) {
		return WikidataValues::buildQuery( $query, $substring );
	}

	/**
	 * @deprecated use WikidataValues::parseResponse()
	 * @param string|false $response
	 * @return string[]
	 */
	public static function parseWikidataResponse( $response ) {
		return WikidataValues::parseResponse( $response );
	}

	/**
	 * @deprecated use AutocompleteSql::conditionForColumn()
	 * @param string $column
	 * @param string $substring
	 * @param bool $replaceSpaces
	 * @return string
	 */
	public static function getSQLConditionForAutocompleteInColumn( $column, $substring, $replaceSpaces = true ) {
		return AutocompleteSql::conditionForColumn( $column, $substring, $replaceSpaces );
	}

	/**
	 * @deprecated use NamespacePages::get()
	 * @param string $namespaceStr
	 * @param string|null $substring
	 * @return array
	 */
	public static function getAllPagesForNamespace( $namespaceStr, $substring = null ) {
		return NamespacePages::get( $namespaceStr, $substring );
	}

	/**
	 * @deprecated use NamespaceFinder::standardize()
	 * @param string $namespaceStr
	 * @return string
	 */
	public static function standardizeNamespace( $namespaceStr ) {
		return NamespaceFinder::standardize( $namespaceStr );
	}

	/**
	 * @deprecated use CategoryPages::get()
	 * @param string $top_category
	 * @param int $num_levels
	 * @param string|null $substring
	 * @return string[]
	 */
	public static function getAllPagesForCategory( $top_category, $num_levels, $substring = null ) {
		return CategoryPages::get( $top_category, $num_levels, $substring );
	}

	/**
	 * @deprecated use CategoryPages::fixedMultiSort()
	 * @param string[] $sortkeys
	 * @param string[] $pages
	 * @return string[]
	 */
	public static function fixedMultiSort( $sortkeys, $pages ) {
		return CategoryPages::fixedMultiSort( $sortkeys, $pages );
	}

	/**
	 * @deprecated use ConceptPages::get()
	 * @param string $conceptName
	 * @param string|null $substring
	 * @return string[]
	 */
	public static function getAllPagesForConcept( $conceptName, $substring = null ) {
		return ConceptPages::get( $conceptName, $substring );
	}

	/**
	 * @deprecated use AutocompleteSourceCount::get()
	 * @param string $autocompleteFieldType
	 * @param string $autocompletionSource
	 * @param \SMW\Store|null $store
	 * @return int|null
	 */
	public static function getSourceCount(
		string $autocompleteFieldType, string $autocompletionSource, $store = null
	): ?int {
		return AutocompleteSourceCount::get( $autocompleteFieldType, $autocompletionSource, $store );
	}

	/**
	 * @deprecated use AutocompleteSourceCount::reset()
	 */
	public static function resetSourceCounts(): void {
		AutocompleteSourceCount::reset();
	}

	/**
	 * @deprecated use AutocompleteSourceCount::exceedsLocalThreshold()
	 * @param string $autocompleteFieldType
	 * @param string $autocompletionSource
	 * @return bool
	 */
	public static function exceedsLocalAutocompleteThreshold(
		string $autocompleteFieldType, string $autocompletionSource
	): bool {
		return AutocompleteSourceCount::exceedsLocalThreshold( $autocompleteFieldType, $autocompletionSource );
	}

	/**
	 * @deprecated use AutocompleteSettings::typeAndSource()
	 * @param array &$field_args
	 * @return array
	 */
	public static function getAutocompletionTypeAndSource( &$field_args ) {
		return AutocompleteSettings::typeAndSource( $field_args );
	}

	/**
	 * @deprecated use AutocompleteSettings::remoteDataType()
	 * @param string|null $autocompleteFieldType
	 * @param string|null $autocompletionSource
	 * @param array $field_args
	 * @param string|null $autocompleteSettings
	 * @param bool $forceRemote
	 * @return string|null
	 */
	public static function getRemoteDataTypeAndPossiblySetAutocompleteValues(
		$autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings,
		bool $forceRemote = false
	) {
		return AutocompleteSettings::remoteDataType(
			$autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings, $forceRemote
		);
	}

	/**
	 * @deprecated use AutocompleteSettings::maybeDisambiguateLabels()
	 * @param array $autocompleteValues
	 * @param string|null $autocompleteFieldType
	 * @return array
	 */
	public static function maybeDisambiguateAutocompleteLabels( array $autocompleteValues, $autocompleteFieldType ) {
		return AutocompleteSettings::maybeDisambiguateLabels( $autocompleteValues, $autocompleteFieldType );
	}

	/**
	 * @deprecated use AutocompleteSettings::forField()
	 * @param array $field_args
	 * @param bool $is_list
	 * @param bool $forceRemote
	 * @return array
	 */
	public static function setAutocompleteValues( $field_args, $is_list, bool $forceRemote = false ) {
		return AutocompleteSettings::forField( $field_args, (bool)$is_list, $forceRemote );
	}

	/**
	 * @deprecated use PropertyValues::forSubject()
	 * @param \SMW\Store $store
	 * @param Title|null $subject
	 * @param string $propID
	 * @param \SMW\RequestOptions|null $requestOptions
	 * @return array
	 */
	public static function getSMWPropertyValues( $store, ?Title $subject, $propID, $requestOptions = null ) {
		return PropertyValues::forSubject( $store, $subject, $propID, $requestOptions );
	}

	/**
	 * @deprecated use PropertyValues::all()
	 * @param string $property_name
	 * @param \SMW\Store|null $store
	 * @param int|null $maxValues
	 * @param bool|null $useDisplayTitle
	 * @return array
	 */
	public static function getAllValuesForProperty(
		$property_name, $store = null, $maxValues = null, $useDisplayTitle = null
	) {
		return PropertyValues::all( $property_name, $store, $maxValues, $useDisplayTitle );
	}
}
