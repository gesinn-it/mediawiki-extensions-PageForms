<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * The display titles ("DISPLAYTITLE") of the pages that are offered as values of a field.
 *
 * @ingroup PF
 */
class DisplayTitles {

	/**
	 * Returns the decoded display title if it is non-empty after stripping
	 * HTML tags and non-breaking spaces, or $fallback otherwise.
	 *
	 * @param string|null $raw Raw pp_value or PageProps value
	 * @param string $fallback Value to return when $raw is absent or blank
	 * @return string
	 */
	public static function resolve( ?string $raw, string $fallback ): string {
		if ( $raw !== null ) {
			$decoded = htmlspecialchars_decode( $raw );
			$plain = trim( str_replace( '&#160;', '', strip_tags( $decoded ) ) );
			if ( $plain !== '' ) {
				return $plain;
			}
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
	public static function forPages( array $pageTitles ): array {
		if ( $pageTitles === [] ) {
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
		$properties = $services->getPageProps()->getProperties( array_values( $titleMap ), [ 'displaytitle' ] );

		$result = [];
		foreach ( $pageTitles as $titleText ) {
			if ( !isset( $titleMap[$titleText] ) ) {
				$result[$titleText] = $titleText;
				continue;
			}
			$articleId = $titleMap[$titleText]->getArticleID();
			$displayTitle = $properties[$articleId]['displaytitle'] ?? null;
			$result[$titleText] = self::resolve( $displayTitle, $titleText );
		}

		return $result;
	}
}
