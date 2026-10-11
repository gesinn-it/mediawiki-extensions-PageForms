<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;
use MWException;
use PFUtils;
use SMW\DIWikiPage;
use SMW\Query\Language\ConceptDescription;
use SMW\Query\Language\Conjunction;
use SMW\Query\Language\Description;
use SMW\Query\Language\ValueDescription;
use SMW\Query\PrintRequest;
use SMWQuery;
use Title;

/**
 * The pages of a Semantic MediaWiki concept, as the values of a "values from concept" field.
 *
 * @ingroup PF
 */
class ConceptPages {

	/**
	 * @param string $conceptName
	 * @param string|null $substring
	 * @return string[] The display title of each page by its name, sorted by the sort key of the
	 *   page, and cut off after $wgPageFormsMaxAutocompleteValues
	 * @throws MWException If there is no such concept
	 */
	public static function get( string $conceptName, ?string $substring = null ): array {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

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

		$desc = self::buildDescription( $conceptTitle, $substring );
		$desc->addPrintRequest( new PrintRequest( PrintRequest::PRINT_THIS, "" ) );
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

		[ $pages, $sortkeys ] = self::readPages( $store->getQueryResult( $query ) );

		if ( $substring !== null ) {
			[ $pages, $sortkeys ] = self::filterBySubstring( $pages, $sortkeys, $substring );
		}
		array_multisort( $sortkeys, $pages );
		// In display-title mode with a substring filter the SMW query fetches more
		// pages than $wgPageFormsMaxAutocompleteValues; truncate here after filtering.
		return array_slice( $pages, 0, $wgPageFormsMaxAutocompleteValues );
	}

	/**
	 * When filtering by substring there are two strategies, depending on the mode:
	 *
	 * Non-display-title mode (!$wgPageFormsUseDisplayTitle):
	 *   Push a title-LIKE condition into the SMW query via Conjunction, so the
	 *   LIMIT applies only to already-filtered results. Without this, the LIMIT
	 *   truncates the full concept list before PHP filtering, silently dropping
	 *   pages beyond the limit even if they match the search term.
	 *   ValueDescription(DIWikiPage, SMW_CMP_LIKE) maps to smw_sortkey LIKE '%pattern%'
	 *   in the SQLStore, equivalent to the [[~*pattern*]] ask-query syntax.
	 *
	 * Display-title mode ($wgPageFormsUseDisplayTitle, the default):
	 *   smw_sortkey stores the page title, not the display title, so the LIKE
	 *   filter cannot pre-screen by display title. Instead the SMW query fetches
	 *   all concept pages (up to smwgQMaxLimit) and the PHP pass in filterBySubstring()
	 *   does the authoritative display-title filtering.
	 *
	 * @param Title $conceptTitle
	 * @param string|null $substring Lowercase
	 * @return Description
	 */
	private static function buildDescription( Title $conceptTitle, ?string $substring ): Description {
		global $wgPageFormsUseDisplayTitle, $wgPageFormsAutocompleteOnAllChars;

		$conceptDesc = new ConceptDescription( DIWikiPage::newFromTitle( $conceptTitle ) );
		if ( $substring === null || $wgPageFormsUseDisplayTitle ) {
			return $conceptDesc;
		}
		$pattern = $wgPageFormsAutocompleteOnAllChars
			? '*' . $substring . '*'
			: $substring . '*';
		$titleFilter = new ValueDescription( new DIWikiPage( $pattern, NS_MAIN ), null, SMW_CMP_LIKE );
		return new Conjunction( [ $conceptDesc, $titleFilter ] );
	}

	/**
	 * @param \SMW\Query\QueryResult $queryResult
	 * @return array{0: array<string, string>, 1: array<string, string>} The display title and the
	 *   sort key of each page, by its name
	 */
	private static function readPages( $queryResult ): array {
		global $wgPageFormsUseDisplayTitle;

		$pages = [];
		$sortkeys = [];
		$titles = [];

		for ( $res = $queryResult->getNext(); $res; $res = $queryResult->getNext() ) {
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
			$properties = MediaWikiServices::getInstance()->getPageProps()
				->getProperties( $titles, [ 'displaytitle', 'defaultsort' ] );
			foreach ( $titles as $title ) {
				$titleprops = $properties[$title->getArticleID()] ?? [];
				$titleText = $title->getPrefixedText();
				$pages[$titleText] = DisplayTitles::resolve( $titleprops['displaytitle'] ?? null, $titleText );
				$sortkeys[$titleText] = $titleprops['defaultsort'] ?? $titleText;
			}
		}
		return [ $pages, $sortkeys ];
	}

	/**
	 * For !$wgPageFormsUseDisplayTitle: the SMW query already pre-filters
	 * via title-LIKE so this pass only refines word-boundary matching
	 * when $wgPageFormsAutocompleteOnAllChars is false.
	 * For $wgPageFormsUseDisplayTitle: smw_sortkey holds the page title,
	 * not the display title, so this remains the authoritative filter.
	 *
	 * @param array<string, string> $pages
	 * @param array<string, string> $sortkeys
	 * @param string $substring Lowercase
	 * @return array{0: array<string, string>, 1: array<string, string>}
	 */
	private static function filterBySubstring( array $pages, array $sortkeys, string $substring ): array {
		global $wgPageFormsAutocompleteOnAllChars;

		$filteredPages = [];
		$filteredSortkeys = [];
		foreach ( $pages as $index => $pageName ) {
			$lowercasePageName = strtolower( $pageName );
			$position = strpos( $lowercasePageName, $substring );
			if ( $position === false ) {
				continue;
			}
			if ( $wgPageFormsAutocompleteOnAllChars ||
				$position === 0 || strpos( $lowercasePageName, ' ' . $substring ) > 0
			) {
				$filteredPages[$index] = $pageName;
				$filteredSortkeys[$index] = $sortkeys[$index];
			}
		}
		return [ $filteredPages, $filteredSortkeys ];
	}
}
