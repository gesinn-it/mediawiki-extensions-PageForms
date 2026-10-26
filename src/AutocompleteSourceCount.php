<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Title\Title;
use PFUtils;
use SMWQuery;

/**
 * How many values a source of autocomplete values has, counted cheaply, to decide whether the values
 * are loaded into the form or fetched from the autocomplete API while the user types.
 *
 * @ingroup PF
 */
class AutocompleteSourceCount {

	/** @var array<string, int> Source counts of the live store, by type and source */
	private static array $sourceCounts = [];

	/**
	 * Build an SMW query Description for a cheap MODE_COUNT query.
	 *
	 * Returns null when the type is unknown or required data (e.g. title) is
	 * missing; callers treat null as "count unavailable → always remote".
	 *
	 * Not used for the 'property' type: MODE_COUNT with a SomeProperty
	 * description counts *subjects/annotations* that use the property, not
	 * its distinct values, which is what the local-autocomplete threshold
	 * decision actually needs (see #190). get() handles 'property'
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
		return count( PropertyValues::forSubject( $store, null, $propertyName, $requestOptions ) );
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
	public static function get(
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
	 * Forget the source counts remembered by get().
	 */
	public static function reset(): void {
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
	 * query in get(). Used to decide whether 'remote
	 * autocompletion' should skip the full eager fetch of the source's
	 * values (see #187).
	 *
	 * When SMW is unavailable or the count cannot be determined, this
	 * conservatively returns true (i.e. treat the source as large/remote),
	 * matching get()'s "null means always remote" contract.
	 *
	 * @param string $autocompleteFieldType 'category'|'namespace'|'concept'|'property'
	 * @param string $autocompletionSource
	 * @return bool
	 */
	public static function exceedsLocalThreshold(
		string $autocompleteFieldType, string $autocompletionSource
	): bool {
		global $wgPageFormsMaxLocalAutocompleteValues;
		$count = self::get( $autocompleteFieldType, $autocompletionSource );
		return $count === null || $count > $wgPageFormsMaxLocalAutocompleteValues;
	}
}
