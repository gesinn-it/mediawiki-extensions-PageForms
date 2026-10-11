<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;
use MWException;
use PFUtils;
use SMW\DataValueFactory;
use SMW\DataValues\PropertyValue;
use SMWDIUri;
use SMWQuery;
use SMWQueryProcessor;
use Title;

/**
 * Static functions for handling lists of values and labels.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class ValuesUtils {

	/** @var array<string, int> Source counts of the live store, by type and source */
	private static array $sourceCounts = [];

	/**
	 * Helper function to handle getPropertyValues().
	 *
	 * @param \SMW\Store $store
	 * @param Title|null $subject
	 * @param string $propID
	 * @param \SMW\RequestOptions|null $requestOptions
	 * @return array
	 */
	public static function getSMWPropertyValues( $store, ?Title $subject, $propID, $requestOptions = null ) {
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
				// Use the canonical (English) namespace name, matching how
				// MediaWiki serializes internal links in wikitext, regardless
				// of content language. The localized name (e.g. "Kategorie" on
				// a German wiki) would not match the "Category:"-prefixed value
				// actually stored on the page, breaking display-title mapping.
				$values[] = PageValue::newFromDIWikiPage( $value )->getCanonicalString();
			} else {
				// getSortKey() seems to return the correct
				// value for all the other data types.
				$values[] = str_replace( '_', ' ', $value->getSortKey() );
			}
		}
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
	 * This function, unlike the others, doesn't take in a substring
	 * because it uses the SMW data store, which can't perform
	 * case-insensitive queries; for queries with a substring, the
	 * function PFAutocompleteAPI::getAllValuesForProperty() exists.
	 *
	 * @param string $property_name
	 * @param \SMW\Store|null $store
	 * @param int|null $maxValues
	 * @param bool|null $useDisplayTitle
	 * @return array
	 */
	public static function getAllValuesForProperty(
		$property_name, $store = null, $maxValues = null, $useDisplayTitle = null
	) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

		$store ??= PFUtils::getSMWStore();
		if ( $store == null ) {
			return [];
		}
		$maxValues ??= $wgPageFormsMaxAutocompleteValues;
		$useDisplayTitle ??= $wgPageFormsUseDisplayTitle;

		$requestoptions = new \SMW\RequestOptions();
		$requestoptions->limit = (int)$maxValues;
		$values = self::getSMWPropertyValues( $store, null, $property_name, $requestoptions );
		sort( $values );

		if ( !$useDisplayTitle || $values === [] ) {
			return $values;
		}

		$property = DataValueFactory::getInstance()->newPropertyValueByLabel( $property_name );
		if ( !( $property instanceof PropertyValue ) || $property->getPropertyTypeID() !== '_wpg' ) {
			return $values;
		}

		return DisplayTitles::forPages( $values );
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
			throw new MWException( wfMessage( 'pf-missingconcept', wfEscapeWikiText( $conceptName ) )->parse() );
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
		// Without a substring filter (initial page load / pre-load), apply the cap directly
		// to SMW so the query stays bounded.
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
			$properties = $services->getPageProps()->getProperties( $titles,
				[ 'displaytitle', 'defaultsort' ] );
			foreach ( $titles as $title ) {
				if ( array_key_exists( $title->getArticleID(), $properties ) ) {
					$titleprops = $properties[$title->getArticleID()];
				} else {
					$titleprops = [];
				}

				$titleText = $title->getPrefixedText();
				$pages[$titleText] = DisplayTitles::resolve(
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
			$names_array = CategoryPages::get( $source_name, 10 );
		} elseif ( $source_type == 'concept' ) {
			$names_array = self::getAllPagesForConcept( $source_name );
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
			$counters = FormCounters::current();
			$autocompleteFieldType = 'values';
			$autocompletionSource = "values-$counters->fieldNum";
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
			$autocompleteFieldType != 'external_url' ) {
			$autocompletionSource = PFUtils::getContLang()->ucfirst( $autocompletionSource );
		}

		return [ $autocompleteFieldType, $autocompletionSource ];
	}

	/**
	 * Build an SMW query Description for a cheap MODE_COUNT query.
	 *
	 * Returns null when the type is unknown or required data (e.g. title) is
	 * missing; callers treat null as "count unavailable → always remote".
	 *
	 * Not used for the 'property' type: MODE_COUNT with a SomeProperty
	 * description counts *subjects/annotations* that use the property, not
	 * its distinct values, which is what the local-autocomplete threshold
	 * decision actually needs (see #190). getSourceCount() handles 'property'
	 * separately via getDistinctPropertyValueCount().
	 *
	 * @param string $autocompleteFieldType 'category'|'namespace'|'concept'
	 * @param string $autocompletionSource The source name/value from the form field
	 * @return \SMW\Query\Language\Description|null
	 */
	private static function buildCountDescription( string $autocompleteFieldType, string $autocompletionSource ) {
		switch ( $autocompleteFieldType ) {
			case 'category':
				$categoryTitle = Title::newFromText( $autocompletionSource, NS_CATEGORY );
				if ( $categoryTitle === null ) {
					return null;
				}
				return new \SMW\Query\Language\ClassDescription(
					\SMW\DIWikiPage::newFromTitle( $categoryTitle )
				);

			case 'namespace':
				$nsDescriptions = [];
				foreach ( explode( ',', $autocompletionSource ) as $namespaceName ) {
					$nsId = NamespaceFinder::findCode( NamespaceFinder::normalize( $namespaceName ) );
					if ( $nsId === null ) {
						return null;
					}
					$nsDescriptions[] = new \SMW\Query\Language\NamespaceDescription( $nsId );
				}
				if ( count( $nsDescriptions ) === 1 ) {
					return $nsDescriptions[0];
				}
				return new \SMW\Query\Language\Disjunction( $nsDescriptions );

			case 'concept':
				$conceptTitle = Title::makeTitleSafe( SMW_NS_CONCEPT, $autocompletionSource );
				if ( $conceptTitle === null ) {
					return null;
				}
				return new \SMW\Query\Language\ConceptDescription(
					\SMW\DIWikiPage::newFromTitle( $conceptTitle )
				);

			default:
				return null;
		}
	}

	/**
	 * Count of a property's *distinct* values, capped at $limit (via
	 * RequestOptions::$limit, applied as a DB-level LIMIT so this stays cheap
	 * even for properties with many annotations).
	 *
	 * Unlike a MODE_COUNT query on a SomeProperty description, this counts
	 * distinct values rather than the number of subjects/annotations using
	 * the property — a property annotated on hundreds of pages can still
	 * have only a handful of distinct values, and it's the latter that
	 * determines whether the local-autocomplete threshold is exceeded (#190).
	 *
	 * A note on RequestOptions::$limit here, because SMW's own source is
	 * misleading on this point: getPropertyValues( null, $property, ... )
	 * (subject = null, "get all values for the given property") goes through
	 * SemanticDataLookup::fetchSemanticDataFromTable(), which computes
	 * $isSubject = ( $dataItem instanceof DIWikiPage || $dataItem === null )
	 * — true here — and that method's DISTINCT handling explicitly skips
	 * DISTINCT for the $isSubject branch ("Don't use DISTINCT for subject
	 * related value match", #3531). Reading only that, $limit looks like it
	 * would cap raw (non-deduplicated) rows, i.e. this cheap count could
	 * undercount distinct values whenever duplicates sort before all
	 * distinct values are seen.
	 *
	 * Verified experimentally instead of trusting that reading (SMW 7.1.0,
	 * MW 1.43): seeded a property with 50 annotations of one value and 1 of
	 * another, then called getPropertyValues( null, $property, RequestOptions
	 * {limit: N} ) directly. limit=1 → ["V1"], limit=2 → ["V1","V2"], i.e.
	 * deduplication happens before $limit is applied, regardless of
	 * annotation order/skew — the DISTINCT-skip noted above evidently
	 * doesn't apply to this code path in practice (dedup likely happens at a
	 * different layer, e.g. inside getPropertyValues() itself). If this
	 * shared helper starts returning wrong counts after an SMW upgrade,
	 * re-verify this assumption first — don't assume the bug is elsewhere.
	 *
	 * @param string $propertyName
	 * @param int $limit
	 * @param \SMW\Store|null $store Injectable for testing; defaults to the live SMW store.
	 * @return int|null Null when SMW is unavailable.
	 */
	private static function getDistinctPropertyValueCount( string $propertyName, int $limit, $store = null ): ?int {
		$store ??= PFUtils::getSMWStore();
		if ( $store === null ) {
			return null;
		}
		$requestOptions = new \SMW\RequestOptions();
		// +1 so a value set exactly at $limit isn't mistaken for "more than
		// $limit"; callers only care whether the count exceeds $limit, not
		// its exact size once it does.
		$requestOptions->limit = $limit + 1;
		return count( self::getSMWPropertyValues( $store, null, $propertyName, $requestOptions ) );
	}

	/**
	 * Return a cheap count of autocomplete values for wiki-sourced types.
	 *
	 * For 'category'|'namespace'|'concept' this uses a MODE_COUNT query
	 * (single DB row fetch, no titles loaded). For 'property' it counts
	 * distinct property values instead (see getDistinctPropertyValueCount()),
	 * since annotation count and distinct-value count can differ wildly.
	 *
	 * Returns null when SMW is unavailable or the count cannot be determined;
	 * callers must treat null as "always remote".
	 *
	 * @param string $autocompleteFieldType
	 * @param string $autocompletionSource
	 * @param \SMW\Store|null $store Injectable for testing; defaults to the live SMW store.
	 * @return int|null
	 */
	public static function getSourceCount(
		string $autocompleteFieldType, string $autocompletionSource, $store = null
	): ?int {
		// Several fields of a form often share a source, and each field asks twice. Remember the
		// count of the live store for the rest of the request.
		if ( $store !== null ) {
			return self::countSource( $autocompleteFieldType, $autocompletionSource, $store );
		}
		$key = $autocompleteFieldType . "\0" . $autocompletionSource;
		if ( !array_key_exists( $key, self::$sourceCounts ) ) {
			$count = self::countSource( $autocompleteFieldType, $autocompletionSource, null );
			if ( $count === null ) {
				// Not cached: SMW may be missing or the query may have failed for now.
				return null;
			}
			self::$sourceCounts[$key] = $count;
		}
		return self::$sourceCounts[$key];
	}

	/**
	 * Forget the source counts remembered by getSourceCount().
	 */
	public static function resetSourceCounts(): void {
		self::$sourceCounts = [];
	}

	private static function countSource(
		string $autocompleteFieldType, string $autocompletionSource, $store
	): ?int {
		global $wgPageFormsMaxLocalAutocompleteValues;

		$store ??= PFUtils::getSMWStore();
		if ( $store === null ) {
			return null;
		}
		try {
			if ( $autocompleteFieldType === 'property' ) {
				return self::getDistinctPropertyValueCount(
					$autocompletionSource, $wgPageFormsMaxLocalAutocompleteValues, $store
				);
			}
			$desc = self::buildCountDescription( $autocompleteFieldType, $autocompletionSource );
			if ( $desc === null ) {
				return null;
			}
			$query = new SMWQuery( $desc );
			$query->querymode = SMWQuery::MODE_COUNT;
			$result = $store->getQueryResult( $query );
			return $result instanceof \SMW\Query\QueryResult
				? (int)$result->getCountValue()
				: null;
		} catch ( \Exception ) {
			return null;
		}
	}

	/**
	 * Whether a wiki-sourced 'values from ...' source has more members than
	 * $wgPageFormsMaxLocalAutocompleteValues, based on the cheap MODE_COUNT
	 * query in getSourceCount(). Used to decide whether 'remote
	 * autocompletion' should skip the full eager fetch of the source's
	 * values (see #187).
	 *
	 * When SMW is unavailable or the count cannot be determined, this
	 * conservatively returns true (i.e. treat the source as large/remote),
	 * matching getSourceCount()'s "null means always remote" contract.
	 *
	 * @param string $autocompleteFieldType 'category'|'namespace'|'concept'|'property'
	 * @param string $autocompletionSource
	 * @return bool
	 */
	public static function exceedsLocalAutocompleteThreshold(
		string $autocompleteFieldType, string $autocompletionSource
	): bool {
		global $wgPageFormsMaxLocalAutocompleteValues;
		$count = self::getSourceCount( $autocompleteFieldType, $autocompletionSource );
		return $count === null || $count > $wgPageFormsMaxLocalAutocompleteValues;
	}

	public static function getRemoteDataTypeAndPossiblySetAutocompleteValues(
		$autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings,
		bool $forceRemote = false
	) {
		global $wgPageFormsAutocompleteValues;

		if ( $autocompleteFieldType == 'external_url' || $autocompleteFieldType == 'wikidata' ) {
			// Autocompletion from URL is always done remotely.
			return $autocompleteFieldType;
		}
		if ( $autocompletionSource == '' ) {
			// No autocompletion.
			return null;
		}

		// For wiki-sourced types, do a cheap MODE_COUNT query to decide between
		// local and remote autocompletion. If the count is at or below the local
		// threshold, pre-load values into wgPageFormsAutocompleteValues so the
		// dropdown button shows all values without the user having to type.
		// When SMW is unavailable or the count cannot be determined, fall back to
		// always-remote (safe, same as the previous unconditional behaviour).
		//
		// Exceptions that skip the count and stay remote:
		// - $forceRemote: caller requires remote (e.g. FormLink target-input where
		//   no values are pre-loaded into the form).
		// - possible_values is a string-keyed (canonical→display) map: the remote
		//   type must be preserved so PF_ComboBoxInput writes the canonical title
		//   into the option's value attribute for DisplayTitle support.
		$countableTypes = [ 'concept', 'category', 'namespace', 'property' ];
		if ( in_array( $autocompleteFieldType, $countableTypes, true ) ) {
			// Stay remote when a mapping template/property is active: PF_ComboBoxInput
			// encodes the canonical title in the <option value> attribute so the JS can
			// bootstrap its DisplayTitle↔canonical maps from the DOM without an AJAX
			// round-trip.  wgPageFormsUseDisplayTitle also produces a string-keyed
			// possible_values map, but _fetchStaticValues() handles that correctly, so
			// we do NOT force remote for that case.
			$hasMappingArg = array_key_exists( 'mapping template', $field_args )
				|| array_key_exists( 'mapping property', $field_args );
			if ( $forceRemote || $hasMappingArg ) {
				return $autocompleteFieldType;
			}
			global $wgPageFormsMaxLocalAutocompleteValues;
			$count = self::getSourceCount( $autocompleteFieldType, $autocompletionSource );
			if ( $count === null || $count > $wgPageFormsMaxLocalAutocompleteValues ) {
				return $autocompleteFieldType;
			}
			// count <= threshold: fall through to load values locally below
		}

		if ( array_key_exists( 'possible_values', $field_args ) &&
		$field_args['possible_values'] !== [] ) {
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

		global $wgPageFormsMaxLocalAutocompleteValues;
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
	 * @param bool $forceRemote Skip the count check and always return the remote data type
	 * @return string[]
	 */
	public static function setAutocompleteValues( $field_args, $is_list, bool $forceRemote = false ) {
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
			$autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings, $forceRemote
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
}
