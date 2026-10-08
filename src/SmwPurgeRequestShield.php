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
// phpcs:disable MediaWiki.Commenting.FunctionComment.ObjectTypeHintParam -- the cache differs by SMW version
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
		$marker = self::read( $cache, $key );
		if ( $marker === false ) {
			return $callback();
		}

		self::remove( $cache, $key );
		try {
			return $callback();
		} finally {
			self::write( $cache, $key, $marker );
		}
	}

	/**
	 * The cache that holds the marker: a BagOStuff in SMW 7 (ServicesFactory::getObjectCache()),
	 * a Doctrine-style cache with fetch() and save() in SMW 5 (ApplicationFactory::getCache()).
	 */
	private static function getCache(): ?object {
		if ( !function_exists( 'smwfCacheKey' ) ) {
			return null;
		}
		foreach ( [ '\\SMW\\Services\\ServicesFactory', '\\SMW\\ApplicationFactory' ] as $factory ) {
			if ( !class_exists( $factory ) ) {
				continue;
			}
			$instance = $factory::getInstance();
			if ( method_exists( $instance, 'getObjectCache' ) ) {
				return $instance->getObjectCache();
			}
			if ( method_exists( $instance, 'getCache' ) ) {
				return $instance->getCache();
			}
		}
		return null;
	}

	/**
	 * @param BagOStuff|object $cache BagOStuff in SMW 7, a Doctrine-style cache in SMW 5
	 * @param string $key
	 * @return mixed The marker, or false if there is none
	 */
	private static function read( object $cache, string $key ) {
		return $cache instanceof BagOStuff ? $cache->get( $key ) : $cache->fetch( $key );
	}

	/**
	 * @param BagOStuff|object $cache BagOStuff in SMW 7, a Doctrine-style cache in SMW 5
	 * @param string $key
	 * @param mixed $marker
	 */
	private static function write( object $cache, string $key, $marker ): void {
		if ( $cache instanceof BagOStuff ) {
			$cache->set( $key, $marker );
		} else {
			$cache->save( $key, $marker );
		}
	}

	/**
	 * @param BagOStuff|object $cache BagOStuff in SMW 7, a Doctrine-style cache in SMW 5
	 * @param string $key
	 */
	private static function remove( object $cache, string $key ): void {
		$cache->delete( $key );
	}

}
