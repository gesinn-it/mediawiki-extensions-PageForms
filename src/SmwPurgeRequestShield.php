<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use BagOStuff;
use Title;

/**
 * Keeps a pending Semantic MediaWiki purge request for a page out of reach of a parse that is not
 * the content of that page.
 *
 * The definition of a form is parsed in the context of the page the form edits. Semantic MediaWiki
 * takes every parse of a page for its content: when a purge request is pending for the page (the
 * marker its ArticlePurge hook sets for action=purge and null edits), it consumes the marker in the
 * first parse and writes the data of that parse to the store. For the parse of a form definition
 * that is no data at all, and the page loses all its properties. The marker is therefore taken away
 * for the duration of the parse and put back afterwards, so that the parse of the real page content
 * consumes it as before. Nothing about the parse itself changes, hence neither does its output.
 *
 * Does nothing when Semantic MediaWiki is not installed.
 */
class SmwPurgeRequestShield {

	/** Cache namespace of the marker, see SMW\MediaWiki\Hooks\ArticlePurge::CACHE_NAMESPACE. */
	private const MARKER_NAMESPACE = 'smw:arc';

	/**
	 * @template T
	 * @param Title $title Page in whose context the callback parses
	 * @param callable():T $callback
	 * @return T
	 */
	public static function run( Title $title, callable $callback ) {
		$cache = self::getCache();
		$articleId = $title->getArticleID();
		if ( $cache === null || $articleId <= 0 ) {
			return $callback();
		}

		$key = smwfCacheKey( self::MARKER_NAMESPACE, (string)$articleId );
		$marker = $cache->get( $key );
		if ( $marker === false ) {
			return $callback();
		}

		$cache->delete( $key );
		try {
			return $callback();
		} finally {
			$cache->set( $key, $marker );
		}
	}

	private static function getCache(): ?BagOStuff {
		if ( !function_exists( 'smwfCacheKey' ) ) {
			return null;
		}
		// SMW 7 hands out the cache that holds the marker via ServicesFactory::getObjectCache(),
		// SMW 5 via ApplicationFactory::getCache()
		$servicesFactory = '\\SMW\\Services\\ServicesFactory';
		if ( class_exists( $servicesFactory ) ) {
			return $servicesFactory::getInstance()->getObjectCache();
		}
		$applicationFactory = '\\SMW\\ApplicationFactory';
		if ( class_exists( $applicationFactory ) ) {
			return $applicationFactory::getInstance()->getCache();
		}
		return null;
	}

}
