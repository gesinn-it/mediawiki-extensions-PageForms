<?php
/**
 * Static functions for handling lists of values and labels.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

class PFValuesUtils {

	/**
	 * Helper function to handle getPropertyValues().
	 *
	 * @param Store $store
	 * @param Title $subject
	 * @param string $propID
	 * @param \SMW\RequestOptions|null $requestOptions
	 * @return array
	 * @suppress PhanUndeclaredTypeParameter For Store
	 */
	public static function getSMWPropertyValues( $store, $subject, $propID, $requestOptions = null ) {
		// If SMW is not installed, exit out.
		if ( !class_exists( '\SMW\DIWikiPage' ) ) {
			return [];
		}
		if ( $subject === null ) {
			$page = null;
		} else {
			$page = \SMW\DIWikiPage::newFromTitle( $subject );
		}
		$property = \SMW\DIProperty::newFromUserLabel( $propID );
		$res = $store->getPropertyValues( $page, $property, $requestOptions );
		$values = [];
		foreach ( $res as $value ) {
			if ( $value instanceof SMWDIUri ) {
				$values[] = $value->getURI();
			} elseif ( $value instanceof \SMW\DIWikiPage ) {
				$realValue = str_replace( '_', ' ', $value->getDBKey() );
				if ( $value->getNamespace() != 0 ) {
					$realValue = PFUtils::getNsText( $value->getNamespace() ) . ":$realValue";
				}
				$values[] = $realValue;
			} else {
				// getSortKey() seems to return the correct
				// value for all the other data types.
				$values[] = str_replace( '_', ' ', $value->getSortKey() );
			}
		}
		$values = self::shiftShortestMatch( $values );
		return $values;
	}

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
			 null,
			__METHOD__
		);
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			$categories[] = $row['cat_title'];
		}
		$res->free();
		return $categories;
	}

	/**
	 * This function, unlike the others, doesn't take in a substring
	 * because it uses the SMW data store, which can't perform
	 * case-insensitive queries; for queries with a substring, the
	 * function PFAutocompleteAPI::getAllValuesForProperty() exists.
	 *
	 * @param string $property_name
	 * @return array
	 */
	public static function getAllValuesForProperty( $property_name ) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

		$store = PFUtils::getSMWStore();
		if ( $store == null ) {
			return [];
		}
		$requestoptions = new \SMW\RequestOptions();
		$requestoptions->limit = $wgPageFormsMaxAutocompleteValues;
		$values = self::getSMWPropertyValues( $store, null, $property_name, $requestoptions );
		sort( $values );
		$values = self::shiftShortestMatch( $values );

		if ( !$wgPageFormsUseDisplayTitle || empty( $values ) ) {
			return $values;
		}

		$property = SMW\DataValueFactory::getInstance()->newPropertyValueByLabel( $property_name );
		if ( $property->getPropertyTypeID() !== '_wpg' ) {
			return $values;
		}

		return self::addDisplayTitlesForPageValues( $values );
	}

	/**
	 * Returns the decoded display title if it is non-empty after stripping
	 * HTML tags and non-breaking spaces, or $fallback otherwise.
	 *
	 * @param string|null $raw Raw pp_value or PageProps value
	 * @param string $fallback Value to return when $raw is absent or blank
	 * @return string
	 */
	public static function resolveDisplayTitle( ?string $raw, string $fallback ): string {
		if ( $raw !== null && trim( str_replace( '&#160;', '', strip_tags( $raw ) ) ) !== '' ) {
			return htmlspecialchars_decode( $raw );
		}
		return $fallback;
	}

	/**
	 * Given an array of page title strings (possibly namespace-prefixed),
	 * returns a map of [pageTitle => displayTitle], falling back to the
	 * title itself when no display title is set.
	 *
	 * @param string[] $pageTitles
	 * @return array
	 */
	public static function addDisplayTitlesForPageValues( array $pageTitles ): array {
		if ( empty( $pageTitles ) ) {
			return [];
		}

		$titleMap = [];
		foreach ( $pageTitles as $titleText ) {
			$t = Title::newFromText( $titleText );
			if ( $t !== null ) {
				$titleMap[$titleText] = $t;
			}
		}

		$services = MediaWikiServices::getInstance();
		if ( method_exists( $services, 'getPageProps' ) ) {
			$pageProps = $services->getPageProps();
		} else {
			$pageProps = PageProps::getInstance();
		}
		$properties = $pageProps->getProperties( array_values( $titleMap ), [ 'displaytitle' ] );

		$result = [];
		foreach ( $pageTitles as $titleText ) {
			if ( !isset( $titleMap[$titleText] ) ) {
				$result[$titleText] = $titleText;
				continue;
			}
			$articleId = $titleMap[$titleText]->getArticleID();
			$displayTitle = $properties[$articleId]['displaytitle'] ?? null;
			$result[$titleText] = self::resolveDisplayTitle( $displayTitle, $titleText );
		}

		return $result;
	}

	/**
	 * This function is used for fetching the values from wikidata based on the provided
	 * annotations. For queries with substring, the function returns all the values which
	 * have the substring in it.
	 *
	 * @param string $query
	 * @param string|null $substring
	 * @return array
	 */
	public static function getAllValuesFromWikidata( $query, $substring = null ) {
		$endpointUrl = "https://query.wikidata.org/sparql";
		global $wgLanguageCode;

		$query = urldecode( $query );

		$filter_strings = explode( '&', $query );
		$filters = [];

		foreach ( $filter_strings as $filter ) {
			$temp = explode( "=", $filter );
			$filters[ $temp[ 0 ] ] = $temp[ 1 ];
		}

		$attributesQuery = "";
		$count = 0;
		foreach ( $filters as $key => $val ) {
			$attributesQuery .= "wdt:" . $key;
			if ( is_numeric( str_replace( "Q", "", $val ) ) ) {
				$attributesQuery .= " wd:" . $val . ";";
			} else {
				$attributesQuery .= "?customLabel" . $count . " .
				?customLabel" . $count . " rdfs:label \"" . $val . "\"@" . $wgLanguageCode . " . ";
				$count++;
				$attributesQuery .= "?value ";
			}
		}
		unset( $count );
		$attributesQuery = rtrim( $attributesQuery, ";" );
		$attributesQuery = rtrim( $attributesQuery, ". ?value " );

		$sparqlQueryString = "
SELECT DISTINCT ?valueLabel WHERE {
{
SELECT ?value  WHERE {
?value " . $attributesQuery . " .
?value rdfs:label ?valueLabel .
FILTER(LANG(?valueLabel) = \"" . $wgLanguageCode . "\") .
FILTER(REGEX(LCASE(?valueLabel), \"\\\\b" . strtolower( $substring ) . "\"))
} ";
		if ( $substring != null ) {
			global $wgPageFormsMaxAutocompleteValues;
			$sparqlQueryString .= "LIMIT " . ( $wgPageFormsMaxAutocompleteValues + 10 );
		}
		$sparqlQueryString .= "}
SERVICE wikibase:label { bd:serviceParam wikibase:language \"" . $wgLanguageCode . "\". }
}";
		if ( $substring != null ) {
			global $wgPageFormsMaxAutocompleteValues;
			$sparqlQueryString .= "LIMIT " . $wgPageFormsMaxAutocompleteValues;
		}
		$opts = [
			'http' => [
				'method' => 'GET',
				'header' => [
					'Accept: application/sparql-results+json',
					'User-Agent: PageForms_API PHP/8.0'
				],
			],
		];
		$context = stream_context_create( $opts );

		$url = $endpointUrl . '?query=' . urlencode( $sparqlQueryString );
		$response = file_get_contents( $url, false, $context );
		$apiResults = json_decode( $response, true );
		$results = [];
		if ( $apiResults != null ) {
			$apiResults = $apiResults[ 'results' ][ 'bindings' ];
			foreach ( $apiResults as $result ) {
				foreach ( $result as $key => $val ) {
					array_push( $results, $val[ 'value' ] );
				}
			}
		}
		return $results;
	}

	/**
	 * Get all the pages that belong to a category and all its
	 * subcategories, down a certain number of levels - heavily based on
	 * SMW's SMWInlineQuery::includeSubcategories().
	 *
	 * @param string $top_category
	 * @param int $num_levels
	 * @param string|null $substring
	 * @return string[]
	 */
	public static function getAllPagesForCategory( $top_category, $num_levels, $substring = null ) {
		if ( $num_levels == 0 ) {
			return [ $top_category ];
		}
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

		$db = PFUtils::getReplicaDB();
		$top_category = str_replace( ' ', '_', $top_category );
		$categories = [ $top_category ];
		$checkcategories = [ $top_category ];
		$pages = [];
		$sortkeys = [];
		for ( $level = $num_levels; $level > 0; $level-- ) {
			$newcategories = [];
			foreach ( $checkcategories as $category ) {
				$tables = [ 'categorylinks', 'page' ];
				$columns = [ 'page_title', 'page_namespace' ];
				$conditions = [];
				$conditions[] = 'cl_from = page_id';
				$conditions['cl_to'] = $category;
				if ( $wgPageFormsUseDisplayTitle ) {
					$tables['pp_displaytitle'] = 'page_props';
					$tables['pp_defaultsort'] = 'page_props';
					$columns['pp_displaytitle_value'] = 'pp_displaytitle.pp_value';
					$columns['pp_defaultsort_value'] = 'pp_defaultsort.pp_value';
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
					if ( $substring != null ) {
						$conditions[] = '((pp_displaytitle.pp_value IS NULL OR pp_displaytitle.pp_value = \'\') AND (' .
							self::getSQLConditionForAutocompleteInColumn( 'page_title', $substring ) .
							')) OR ' .
							self::getSQLConditionForAutocompleteInColumn(
								'pp_displaytitle.pp_value', $substring, false
							) .
							' OR page_namespace = ' . NS_CATEGORY;
					}
				} else {
					$join = [];
					if ( $substring != null ) {
						$conditions[] = self::getSQLConditionForAutocompleteInColumn(
							'page_title', $substring
						) . ' OR page_namespace = ' . NS_CATEGORY;
					}
				}
				// Make the query.
				$res = $db->select(
					$tables,
					$columns,
					$conditions,
					__METHOD__,
					$options = [
						'ORDER BY' => 'cl_type, cl_sortkey',
						'LIMIT' => $wgPageFormsMaxAutocompleteValues
					],
					$join );
				if ( $res ) {
					for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
						if ( !array_key_exists( 'page_title', $row ) ) {
							continue;
						}
						$page_namespace = $row['page_namespace'];
						$page_name = $row[ 'page_title' ];
						if ( $page_namespace == NS_CATEGORY ) {
							if ( !in_array( $page_name, $categories ) ) {
								$newcategories[] = $page_name;
							}
						} else {
							$cur_title = Title::makeTitleSafe( $page_namespace, $page_name );
							if ( $cur_title === null ) {
								// This can happen if it's
								// a "phantom" page, in a
								// namespace that no longer exists.
								continue;
							}
							$cur_value = $cur_title->getPrefixedText();
							if ( !in_array( $cur_value, $pages ) ) {
								$pages[ $cur_value . '@' ] = self::resolveDisplayTitle(
									$row['pp_displaytitle_value'] ?? null, $cur_value );
								if ( array_key_exists( 'pp_defaultsort_value', $row ) &&
									( $row[ 'pp_defaultsort_value' ] ) !== null ) {
									$sortkeys[ $cur_value ] = $row[ 'pp_defaultsort_value'];
								} else {
									$sortkeys[ $cur_value ] = $cur_value;
								}
							}
						}
					}
					$res->free();
				}
			}
			if ( count( $newcategories ) == 0 ) {
				return self::fixedMultiSort( $sortkeys, $pages );
			} else {
				$categories = array_merge( $categories, $newcategories );
			}
			$checkcategories = array_diff( $newcategories, [] );
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
	public static function fixedMultiSort( $sortkeys, $pages ) {
		array_multisort( $sortkeys, $pages );
		$newPages = [];
		foreach ( $pages as $key => $value ) {
			$fixedKey = rtrim( $key, '@' );
			$newPages[$fixedKey] = $value;
		}
		return $newPages;
	}

	/**
	 * @param string $conceptName
	 * @param string|null $substring
	 * @return string[]
	 */
	public static function getAllPagesForConcept( $conceptName, $substring = null ) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsAutocompleteOnAllChars;

		$store = PFUtils::getSMWStore();
		if ( $store == null ) {
			return [];
		}

		$conceptTitle = Title::makeTitleSafe( SMW_NS_CONCEPT, $conceptName );

		if ( $substring !== null ) {
			$substring = strtolower( $substring );
		}

		// Escape if there's no such concept.
		if ( $conceptTitle == null || !$conceptTitle->exists() ) {
			throw new MWException( wfMessage( 'pf-missingconcept', wfEscapeWikiText( $conceptName ) ) );
		}

		global $wgPageFormsUseDisplayTitle;
		$conceptDI = \SMW\DIWikiPage::newFromTitle( $conceptTitle );
		$conceptDesc = new \SMW\Query\Language\ConceptDescription( $conceptDI );

		// When filtering by substring there are two strategies, depending on the mode:
		//
		// Non-display-title mode (!$wgPageFormsUseDisplayTitle):
		//   Push a title-LIKE condition into the SMW query via Conjunction, so the
		//   LIMIT applies only to already-filtered results. Without this, the LIMIT
		//   truncates the full concept list before PHP filtering, silently dropping
		//   pages beyond the limit even if they match the search term.
		//   ValueDescription(DIWikiPage, SMW_CMP_LIKE) maps to smw_sortkey LIKE '%pattern%'
		//   in the SQLStore, equivalent to the [[~*pattern*]] ask-query syntax.
		//
		// Display-title mode ($wgPageFormsUseDisplayTitle, the default):
		//   smw_sortkey stores the page title, not the display title, so the LIKE
		//   filter cannot pre-screen by display title. Instead the SMW query fetches
		//   all concept pages (up to smwgQMaxLimit) and the PHP pass below does the
		//   authoritative display-title filtering. The result is then truncated to
		//   $wgPageFormsMaxAutocompleteValues at the end of this function.
		if ( $substring !== null && !$wgPageFormsUseDisplayTitle ) {
			$pattern = $wgPageFormsAutocompleteOnAllChars
				? '*' . $substring . '*'
				: $substring . '*';
			$titleDI = new \SMW\DIWikiPage( $pattern, NS_MAIN );
			$titleFilter = new \SMW\Query\Language\ValueDescription( $titleDI, null, SMW_CMP_LIKE );
			$desc = new \SMW\Query\Language\Conjunction( [ $conceptDesc, $titleFilter ] );
		} else {
			$desc = $conceptDesc;
		}

		$printout = new \SMW\Query\PrintRequest( \SMW\Query\PrintRequest::PRINT_THIS, "" );
		$desc->addPrintRequest( $printout );
		$query = new SMWQuery( $desc );

		// In display-title mode with a substring filter the PHP pass is the authoritative
		// filter and needs to see all concept pages. Skip the autocomplete-values cap here
		// and let the SMW default limit ($smwgQMaxLimit, typically 10,000) apply; the result
		// is truncated to $wgPageFormsMaxAutocompleteValues after PHP filtering below.
		// In all other cases — no filter, or non-display-title mode with Conjunction — the
		// autocomplete cap is applied directly to the SMW query.
		if ( $substring === null || !$wgPageFormsUseDisplayTitle ) {
			$query->setLimit( $wgPageFormsMaxAutocompleteValues );
		}
		$query_result = $store->getQueryResult( $query );

		$pages = [];
		$sortkeys = [];
		$titles = [];

		for ( $res = $query_result->getNext(); $res; $res = $query_result->getNext() ) {
			$page = $res[0]->getNextText( SMW_OUTPUT_WIKI );
			if ( $wgPageFormsUseDisplayTitle ) {
				$title = Title::newFromText( $page );
				if ( $title !== null ) {
					$titles[] = $title;
				}
			} else {
				$pages[$page] = $page;
				$sortkeys[$page] = $page;
			}
		}

		if ( $wgPageFormsUseDisplayTitle ) {
			$services = MediaWikiServices::getInstance();
			if ( method_exists( $services, 'getPageProps' ) ) {
				// MW 1.36+
				$pageProps = $services->getPageProps();
			} else {
				$pageProps = PageProps::getInstance();
			}
			$properties = $pageProps->getProperties( $titles,
				[ 'displaytitle', 'defaultsort' ] );
			foreach ( $titles as $title ) {
				if ( array_key_exists( $title->getArticleID(), $properties ) ) {
					$titleprops = $properties[$title->getArticleID()];
				} else {
					$titleprops = [];
				}

				$titleText = $title->getPrefixedText();
				$pages[$titleText] = self::resolveDisplayTitle(
					$titleprops['displaytitle'] ?? null, $titleText );
				if ( array_key_exists( 'defaultsort', $titleprops ) ) {
					$sortkeys[$titleText] = $titleprops['defaultsort'];
				} else {
					$sortkeys[$titleText] = $titleText;
				}
			}
		}

		if ( $substring !== null ) {
			$filtered_pages = [];
			$filtered_sortkeys = [];
			foreach ( $pages as $index => $pageName ) {
				// For !$wgPageFormsUseDisplayTitle: the SMW query already pre-filters
				// via title-LIKE so this pass only refines word-boundary matching
				// when $wgPageFormsAutocompleteOnAllChars is false.
				// For $wgPageFormsUseDisplayTitle: smw_sortkey holds the page title,
				// not the display title, so this remains the authoritative filter.
				$lowercasePageName = strtolower( $pageName );
				$position = strpos( $lowercasePageName, $substring );
				if ( $position !== false ) {
					if ( $wgPageFormsAutocompleteOnAllChars ) {
						if ( $position >= 0 ) {
							$filtered_pages[$index] = $pageName;
							$filtered_sortkeys[$index] = $sortkeys[$index];
						}
					} else {
						if ( $position === 0 ||
							strpos( $lowercasePageName, ' ' . $substring ) > 0 ) {
							$filtered_pages[$index] = $pageName;
							$filtered_sortkeys[$index] = $sortkeys[$index];
						}
					}
				}
			}
			$pages = $filtered_pages;
			$sortkeys = $filtered_sortkeys;
		}
		array_multisort( $sortkeys, $pages );
		// In display-title mode with a substring filter the SMW query fetches more
		// pages than $wgPageFormsMaxAutocompleteValues; truncate here after filtering.
		$pages = array_slice( $pages, 0, $wgPageFormsMaxAutocompleteValues );
		return $pages;
	}

	public static function getAllPagesForNamespace( $namespaceStr, $substring = null ) {
		global $wgLanguageCode, $wgPageFormsUseDisplayTitle;

		$namespaceNames = explode( ',', $namespaceStr );

		$allNamespaces = PFUtils::getContLang()->getNamespaces();

		if ( $wgLanguageCode !== 'en' ) {
			$englishLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
			$allEnglishNamespaces = $englishLang->getNamespaces();
		}

		$queriedNamespaces = [];
		$namespaceConditions = [];

		foreach ( $namespaceNames as $namespace_name ) {
			$namespace_name = self::standardizeNamespace( $namespace_name );
			// Cycle through all the namespace names for this language, and
			// if one matches the namespace specified in the form, get the
			// names of all the pages in that namespace.

			// Switch to blank for the string 'Main'.
			if ( $namespace_name == 'Main' || $namespace_name == 'main' ) {
				$namespace_name = '';
			}
			$matchingNamespaceCode = null;
			foreach ( $allNamespaces as $curNSCode => $curNSName ) {
				if ( $curNSName == $namespace_name ) {
					$matchingNamespaceCode = $curNSCode;
				}
			}

			// If that didn't find anything, and we're in a language
			// other than English, check English as well.
			if ( $matchingNamespaceCode === null && $wgLanguageCode != 'en' ) {
				foreach ( $allEnglishNamespaces as $curNSCode => $curNSName ) {
					if ( $curNSName == $namespace_name ) {
						$matchingNamespaceCode = $curNSCode;
					}
				}
			}

			if ( $matchingNamespaceCode === null ) {
				throw new MWException( wfMessage( 'pf-missingnamespace', wfEscapeWikiText( $namespace_name ) ) );
			}

			$queriedNamespaces[] = $matchingNamespaceCode;
			$namespaceConditions[] = "page_namespace = $matchingNamespaceCode";
		}

		$db = PFUtils::getReplicaDB();
		$conditions = [];
		$conditions[] = implode( ' OR ', $namespaceConditions );
		$tables = [ 'page' ];
		$columns = [ 'page_title' ];
		if ( count( $namespaceNames ) > 1 ) {
			$columns[] = 'page_namespace';
		}
		if ( $wgPageFormsUseDisplayTitle ) {
			$tables['pp_displaytitle'] = 'page_props';
			$tables['pp_defaultsort'] = 'page_props';
			$columns['pp_displaytitle_value'] = 'pp_displaytitle.pp_value';
			$columns['pp_defaultsort_value'] = 'pp_defaultsort.pp_value';
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
			if ( $substring != null ) {
				$substringCondition = '(pp_displaytitle.pp_value IS NULL AND (' .
					self::getSQLConditionForAutocompleteInColumn( 'page_title', $substring ) .
					')) OR ' .
					self::getSQLConditionForAutocompleteInColumn( 'pp_displaytitle.pp_value', $substring, false );
				if ( !in_array( NS_CATEGORY, $queriedNamespaces ) ) {
					$substringCondition .= ' OR page_namespace = ' . NS_CATEGORY;
				}
				$conditions[] = $substringCondition;
			}
		} else {
			$join = [];
			if ( $substring != null ) {
				$conditions[] = self::getSQLConditionForAutocompleteInColumn( 'page_title', $substring );
			}
		}
		$res = $db->select( $tables, $columns, $conditions, __METHOD__, $options = [], $join );

		$pages = [];
		$sortkeys = [];
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			// If there's more than one namespace, include the
			// namespace prefix in the results - otherwise, don't.
			if ( array_key_exists( 'page_namespace', $row ) ) {
				$actualTitle = Title::newFromText( $row['page_title'], $row['page_namespace'] );
				$title = $actualTitle->getPrefixedText();
			} else {
				$title = str_replace( '_', ' ', $row['page_title'] );
			}
			$pages[ $title ] = self::resolveDisplayTitle(
				$row['pp_displaytitle_value'] ?? null, $title );
			if ( array_key_exists( 'pp_defaultsort_value', $row ) &&
				( $row[ 'pp_defaultsort_value' ] ) !== null ) {
				$sortkeys[ $title ] = $row[ 'pp_defaultsort_value'];
			} else {
				$sortkeys[ $title ] = $title;
			}
		}
		$res->free();

		array_multisort( $sortkeys, $pages );
		return $pages;
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
			$names_array = self::getAllValuesForProperty( $source_name );
		} elseif ( $source_type == 'category' ) {
			$names_array = self::getAllPagesForCategory( $source_name, 10 );
		} elseif ( $source_type == 'concept' ) {
			$names_array = self::getAllPagesForConcept( $source_name );
		} elseif ( $source_type == 'query' ) {
			$names_array = self::getAllPagesForQuery( $source_name );
		} elseif ( $source_type == 'wikidata' ) {
			$names_array = self::getAllValuesFromWikidata( $source_name );
			sort( $names_array );
		} else {
			// i.e., $source_type == 'namespace'
			$names_array = self::getAllPagesForNamespace( $source_name );
		}
		return $names_array;
	}

	public static function getAutocompletionTypeAndSource( &$field_args ) {
		global $wgCapitalLinks;

		if ( array_key_exists( 'values from property', $field_args ) ) {
			$autocompletionSource = $field_args['values from property'];
			$autocompleteFieldType = 'property';
		} elseif ( array_key_exists( 'values from category', $field_args ) ) {
			$autocompleteFieldType = 'category';
			$autocompletionSource = $field_args['values from category'];
		} elseif ( array_key_exists( 'values from concept', $field_args ) ) {
			$autocompleteFieldType = 'concept';
			$autocompletionSource = $field_args['values from concept'];
		} elseif ( array_key_exists( 'values from namespace', $field_args ) ) {
			$autocompleteFieldType = 'namespace';
			$autocompletionSource = $field_args['values from namespace'];
		} elseif ( array_key_exists( 'values from url', $field_args ) ) {
			$autocompleteFieldType = 'external_url';
			$autocompletionSource = $field_args['values from url'];
		} elseif ( array_key_exists( 'values from wikidata', $field_args ) ) {
			$autocompleteFieldType = 'wikidata';
			$autocompletionSource = $field_args['values from wikidata'];
		} elseif ( array_key_exists( 'values', $field_args ) ) {
			global $wgPageFormsFieldNum;
			$autocompleteFieldType = 'values';
			$autocompletionSource = "values-$wgPageFormsFieldNum";
		} elseif ( array_key_exists( 'autocomplete field type', $field_args ) ) {
			$autocompleteFieldType = $field_args['autocomplete field type'];
			$autocompletionSource = $field_args['autocompletion source'];
		} elseif ( array_key_exists( 'semantic_property', $field_args ) ) {
			$autocompletionSource = $field_args['semantic_property'];
			$autocompleteFieldType = 'property';
		} else {
			$autocompleteFieldType = null;
			$autocompletionSource = null;
		}

		if ( $autocompletionSource !== null && $wgCapitalLinks &&
			$autocompleteFieldType != 'external_url' && $autocompleteFieldType != 'cargo field' ) {
			$autocompletionSource = PFUtils::getContLang()->ucfirst( $autocompletionSource );
		}

		return [ $autocompleteFieldType, $autocompletionSource ];
	}

	public static function getRemoteDataTypeAndPossiblySetAutocompleteValues(
		$autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings
	) {
		global $wgPageFormsMaxLocalAutocompleteValues, $wgPageFormsAutocompleteValues;

		if ( $autocompleteFieldType == 'external_url' || $autocompleteFieldType == 'wikidata' ) {
			// Autocompletion from URL is always done remotely.
			return $autocompleteFieldType;
		}
		if ( $autocompletionSource == '' ) {
			// No autocompletion.
			return null;
		}
		// @TODO - that empty() check shouldn't be necessary.
		if ( array_key_exists( 'possible_values', $field_args ) &&
		!empty( $field_args['possible_values'] ) ) {
			$autocompleteValues = $field_args['possible_values'];
		} elseif ( $autocompleteFieldType == 'values' ) {
			$autocompleteValues = explode( ',', $field_args['values'] );
		} else {
			$autocompleteValues = self::getAutocompleteValues( $autocompletionSource, $autocompleteFieldType );
		}

		$autocompleteValues = self::maybeDisambiguateAutocompleteLabels(
			$autocompleteValues,
			$autocompleteFieldType
		);

		if ( count( $autocompleteValues ) > $wgPageFormsMaxLocalAutocompleteValues &&
			$autocompleteFieldType != 'values' &&
			!array_key_exists( 'values dependent on', $field_args ) &&
			!array_key_exists( 'mapping template', $field_args ) &&
			!array_key_exists( 'mapping property', $field_args )
		) {
			return $autocompleteFieldType;
		} else {
			$wgPageFormsAutocompleteValues[$autocompleteSettings] = $autocompleteValues;
			return null;
		}
	}

	/**
	 * For local autocomplete values, disambiguate duplicate displaytitle labels
	 * for sources that map page titles to display titles.
	 *
	 * @param array $autocompleteValues
	 * @param string|null $autocompleteFieldType
	 * @return array
	 */
	public static function maybeDisambiguateAutocompleteLabels( array $autocompleteValues, $autocompleteFieldType ) {
		global $wgPageFormsUseDisplayTitle;

		if ( !$wgPageFormsUseDisplayTitle ) {
			return $autocompleteValues;
		}

		if ( !in_array( $autocompleteFieldType, [ 'category', 'concept', 'namespace', 'property' ], true ) ) {
			return $autocompleteValues;
		}

		return self::disambiguateLabels( $autocompleteValues );
	}

	/**
	 * Get all autocomplete-related values, plus delimiter value
	 * (it's needed also for the 'uploadable' link, if there is one).
	 *
	 * @param array $field_args
	 * @param bool $is_list
	 * @return string[]
	 */
	public static function setAutocompleteValues( $field_args, $is_list ) {
		[ $autocompleteFieldType, $autocompletionSource ] =
			self::getAutocompletionTypeAndSource( $field_args );
		$autocompleteSettings = $autocompletionSource;
		if ( $is_list ) {
			$autocompleteSettings .= ',list';
			if ( array_key_exists( 'delimiter', $field_args ) ) {
				$delimiter = $field_args['delimiter'];
				$autocompleteSettings .= ',' . $delimiter;
			} else {
				$delimiter = ',';
			}
		} else {
			$delimiter = null;
		}

		$remoteDataType = self::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			$autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings
		);
		return [ $autocompleteSettings, $remoteDataType, $delimiter ];
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
		if ( empty( $wgPageFormsAutocompletionURLs ) ) {
			return wfMessage( 'pf-nocompletionurls' );
		}
		if ( !array_key_exists( $external_url_alias, $wgPageFormsAutocompletionURLs ) ) {
			return wfMessage( 'pf-invalidexturl' );
		}
		$url = $wgPageFormsAutocompletionURLs[$external_url_alias];
		if ( empty( $url ) ) {
			return wfMessage( 'pf-blankexturl' );
		}
		$url = str_replace( '<substr>', urlencode( $substring ), $url );
		$page_contents = Http::get( $url );
		if ( empty( $page_contents ) ) {
			return wfMessage( 'pf-externalpageempty' );
		}
		$data = json_decode( $page_contents );
		if ( empty( $data ) ) {
			return wfMessage( 'pf-externalpagebadjson' );
		}
		$return_values = [];
		foreach ( $data->pfautocomplete as $val ) {
			$return_values[] = (array)$val;
		}
		return $return_values;
	}

	/**
	 * Returns a SQL condition for autocompletion substring value in a column.
	 *
	 * @param string $column Value column name
	 * @param string $substring Substring to look for
	 * @param bool $replaceSpaces
	 * @return string SQL condition for use in WHERE clause
	 */
	public static function getSQLConditionForAutocompleteInColumn( $column, $substring, $replaceSpaces = true ) {
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
	 * Returns an array of the names of pages that are the result of an SMW query.
	 *
	 * @param string $rawQuery the query string like [[Category:Trees]][[age::>1000]]
	 * @return array
	 */
	public static function getAllPagesForQuery( $rawQuery ) {
		$rawQueryArray = [ $rawQuery ];
		SMWQueryProcessor::processFunctionParams( $rawQueryArray, $queryString, $processedParams, $printouts );
		SMWQueryProcessor::addThisPrintout( $printouts, $processedParams );
		$processedParams = SMWQueryProcessor::getProcessedParams( $processedParams, $printouts );
		$queryObj = SMWQueryProcessor::createQuery( $queryString,
			$processedParams,
			SMWQueryProcessor::SPECIAL_PAGE, '', $printouts );
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
	 * Get the exact canonical namespace string, given a user-created string
	 *
	 * @param string $namespaceStr
	 * @return string
	 */
	public static function standardizeNamespace( $namespaceStr ) {
		$dummyTitle = Title::newFromText( "$namespaceStr:ABC" );
		return $dummyTitle ? $dummyTitle->getNsText() : $namespaceStr;
	}

	/**
	 * We want the result string matching what the user has currently typed (if
	 * there is one) to be at the top. However, with local autocompletion, we
	 * unfortunately don't know what the user's current input is. So instead we
	 * just take the shortest result string and move that to the top, and hope
	 * that it's a match.
	 *
	 * @param array $values
	 * @return array $values
	 */
	public static function shiftShortestMatch( $values ) {
		if ( empty( $values ) ) {
			return $values;
		}
		$shortestString = $values[ 0 ];
		foreach ( $values as $val ) {
			if ( strlen( $val ) < strlen( $shortestString ) ) {
				$shortestString = $val;
			}
		}
		$firstMatchIdx = array_search( $shortestString, $values );
		unset( $values[ $firstMatchIdx ] );
		array_unshift( $values, $shortestString );
		return $values;
	}
}
