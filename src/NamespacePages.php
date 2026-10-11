<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MWException;
use PFUtils;
use Title;

/**
 * The pages of one or more namespaces, as the values of a "values from namespace" field.
 *
 * @ingroup PF
 */
class NamespacePages {

	/**
	 * @param string $namespaceStr The names of the namespaces, separated by commas
	 * @param string|null $substring
	 * @return array<string, string> The display title of each page by its name, sorted by the
	 *   sort key of the page. The name has the namespace prefix if there is more than one namespace.
	 * @throws MWException If the wiki has no namespace of one of the names
	 */
	public static function get( string $namespaceStr, ?string $substring = null ): array {
		global $wgPageFormsUseDisplayTitle;

		$namespaceNames = explode( ',', $namespaceStr );
		$queriedNamespaces = self::findNamespaces( $namespaceNames );

		$conditions = [
			implode( ' OR ', array_map( static fn ( $code ) => "page_namespace = $code", $queriedNamespaces ) ),
			'page_is_redirect' => 0,
		];
		$tables = [ 'page' ];
		$columns = [ 'page_title' ];
		if ( count( $namespaceNames ) > 1 ) {
			$columns[] = 'page_namespace';
		}
		$join = [];
		if ( $wgPageFormsUseDisplayTitle ) {
			[ $displayTables, $displayColumns, $join ] = AutocompleteSql::displayTitleAndSortKeyParts();
			$tables = array_merge( $tables, $displayTables );
			$columns = array_merge( $columns, $displayColumns );
		}
		if ( $substring != null ) {
			$conditions[] = self::substringCondition( $substring, $queriedNamespaces );
		}

		$res = PFUtils::getReplicaDB()->select( $tables, $columns, $conditions, __METHOD__, [], $join );
		return self::readPages( $res );
	}

	/**
	 * @param string[] $namespaceNames
	 * @return int[]
	 * @throws MWException
	 */
	private static function findNamespaces( array $namespaceNames ): array {
		$codes = [];
		foreach ( $namespaceNames as $namespaceName ) {
			$namespaceName = NamespaceFinder::normalize( $namespaceName );
			$code = NamespaceFinder::findCode( $namespaceName );
			if ( $code === null ) {
				throw new MWException(
					wfMessage( 'pf-missingnamespace', wfEscapeWikiText( $namespaceName ) )->parse()
				);
			}
			$codes[] = $code;
		}
		return $codes;
	}

	/**
	 * With display titles, a page matches by its display title, or by its name if it has none.
	 * Category pages always match, so that the categories below them are found.
	 *
	 * @param string $substring
	 * @param int[] $queriedNamespaces
	 * @return string
	 */
	private static function substringCondition( string $substring, array $queriedNamespaces ): string {
		global $wgPageFormsUseDisplayTitle;

		if ( !$wgPageFormsUseDisplayTitle ) {
			return AutocompleteSql::conditionForColumn( 'page_title', $substring );
		}
		$condition = '(pp_displaytitle.pp_value IS NULL AND (' .
			AutocompleteSql::conditionForColumn( 'page_title', $substring ) .
			')) OR ' .
			AutocompleteSql::conditionForColumn( 'pp_displaytitle.pp_value', $substring, false );
		if ( !in_array( NS_CATEGORY, $queriedNamespaces ) ) {
			$condition .= ' OR page_namespace = ' . NS_CATEGORY;
		}
		return $condition;
	}

	/**
	 * @param \Wikimedia\Rdbms\IResultWrapper $res
	 * @return array<string, string>
	 */
	private static function readPages( $res ): array {
		$pages = [];
		$sortkeys = [];
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			// If there's more than one namespace, include the
			// namespace prefix in the results - otherwise, don't.
			if ( array_key_exists( 'page_namespace', $row ) ) {
				$title = Title::newFromText( $row['page_title'], (int)$row['page_namespace'] )->getPrefixedText();
			} else {
				$title = str_replace( '_', ' ', $row['page_title'] );
			}
			$pages[$title] = DisplayTitles::resolve( $row['pp_displaytitle_value'] ?? null, $title );
			$sortkeys[$title] = $row['pp_defaultsort_value'] ?? $title;
		}
		$res->free();

		array_multisort( $sortkeys, $pages );
		return $pages;
	}
}
