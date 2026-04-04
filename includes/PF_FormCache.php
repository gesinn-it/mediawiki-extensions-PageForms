<?php

declare( strict_types=1 );

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;

/**
 * Form-definition caching subsystem for PageForms.
 *
 * Responsible for storing, retrieving and invalidating parsed form definitions
 * in the MW object cache, and for loading preload-page text used to pre-fill
 * form fields.
 *
 * Extracted from PFFormUtils to give the caching concern a single, testable
 * home.  The hooks ArticlePurge and MultiContentSave are registered directly
 * against this class in extension.json.
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 * @file
 * @ingroup PF
 */
class PFFormCache {

	// -----------------------------------------------------------------------
	// Preload
	// -----------------------------------------------------------------------

	/**
	 * Return the text of a page to be used as preload content for a form.
	 *
	 * Strips <noinclude> sections and <includeonly> tags, just like template
	 * transclusion does.  Returns '' when the page does not exist, the title
	 * is invalid, or the current user lacks read permission.
	 *
	 * @param string $preload Page title string
	 * @return string
	 */
	public static function getPreloadedText( string $preload ): string {
		if ( $preload === '' ) {
			return '';
		}

		$preloadTitle = Title::newFromText( $preload );
		if ( !isset( $preloadTitle ) ) {
			return '';
		}

		if ( method_exists( 'MediaWiki\Permissions\PermissionManager', 'userCan' ) ) {
			// MW 1.33+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			$user = RequestContext::getMain()->getUser();
			if ( !$permissionManager->userCan( 'read', $user, $preloadTitle ) ) {
				return '';
			}
		} else {
			// @codeCoverageIgnoreStart
			if ( !$preloadTitle->userCan( 'read' ) ) {
				return '';
			}
			// @codeCoverageIgnoreEnd
		}

		$text = PFUtils::getPageText( $preloadTitle );
		// Remove <noinclude> sections and <includeonly> tags from text
		$text = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $text );
		$text = strtr( $text, [ '<includeonly>' => '', '</includeonly>' => '' ] );
		return $text;
	}

	// -----------------------------------------------------------------------
	// Form definition — parse + cache
	// -----------------------------------------------------------------------

	/**
	 * Parse the form definition and return it, using or populating the cache.
	 *
	 * @param Parser $parser
	 * @param string|null $form_def Raw wikitext of the form definition (optional)
	 * @param string|null $form_id Page ID of the form page (optional)
	 * @return string Parsed form definition HTML/wikitext
	 */
	public static function getFormDefinition( Parser $parser, $form_def = null, $form_id = null ): string {
		if ( $form_id !== null ) {
			$cachedDef = self::getFormDefinitionFromCache( $form_id, $parser );

			if ( $cachedDef !== null ) {
				return $cachedDef;
			}
		}

		if ( $form_id !== null ) {
			$form_title = Title::newFromID( $form_id );
			$form_def = PFUtils::getPageText( $form_title );
		} elseif ( $form_def == null ) {
			return '';
		}

		// Remove <noinclude> sections and <includeonly> tags from form definition
		$form_def = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $form_def );
		$form_def = strtr( $form_def, [ '<includeonly>' => '', '</includeonly>' => '' ] );

		// We need to replace all PF tags in the form definition by strip items. But we can not just use
		// the Parser strip state because the Parser would during parsing replace all strip items and then
		// mangle them into HTML code. So we have to use our own. Which means we also can not just use
		// Parser::insertStripItem() (see below).
		// Also include a quotation mark, to help avoid security leaks.
		$rnd = wfRandomString( 16 ) . '"' . wfRandomString( 15 );

		// This regexp will find any PF triple braced tags (including correct handling of contained braces), i.e.
		// {{{field|foo|default={{Bar}}}}} is not a problem. When used with preg_match and friends, $matches[0] will
		// contain the whole PF tag, $matches[1] will contain the tag without the enclosing triple braces.
		$regexp = '#\{\{\{((?>[^\{\}]+)|(\{((?>[^\{\}]+)|(?-2))*\}))*\}\}\}#';
		// Needed to restore highlighting in vi - <?

		$items = [];

		// Replace all PF tags by strip markers
		$form_def = preg_replace_callback(
			$regexp,

			// This is essentially a copy of Parser::insertStripItem().
			static function ( array $matches ) use ( &$items, $rnd ) {
				$markerIndex = count( $items );
				$items[] = $matches[0];
				return "$rnd-item-$markerIndex-$rnd";
			},

			$form_def
		);

		// Parse wiki-text.
		// @phan-suppress-next-line PhanRedundantCondition for BC with old MW
		$title = is_object( $parser->getTitle() ) ? $parser->getTitle() : $form_title;
		// We need to pass "false" in to the parse() $clearState param so that
		// embedding Special:RunQuery will work.
		$output = $parser->parse( $form_def, $title, $parser->getOptions(), true, false );
		$form_def = $output->getText();
		$form_def = preg_replace_callback(
			"/{$rnd}-item-(\d+)-{$rnd}/",
			static function ( array $matches ) use ( $items ) {
				$markerIndex = (int)$matches[1];
				return $items[$markerIndex];
			},
			$form_def
		);

		if ( $output->getCacheTime() == -1 ) {
			$form_article = Article::newFromID( $form_id );
			self::purgeCache( $form_article );
			wfDebug( "Caching disabled for form definition $form_id\n" );
		} elseif ( $form_id !== null ) {
			self::cacheFormDefinition( $form_id, $form_def, $parser );
		}

		return $form_def;
	}

	/**
	 * Get a form definition from cache.
	 *
	 * @param string $form_id
	 * @param Parser $parser
	 * @return string|null Cached definition, or null on miss / cache disabled
	 */
	protected static function getFormDefinitionFromCache( $form_id, Parser $parser ): ?string {
		global $wgPageFormsCacheFormDefinitions;

		if ( !$wgPageFormsCacheFormDefinitions ) {
			return null;
		}

		$cache = self::getFormCache();
		$cacheKeyForForm = self::getCacheKey( $form_id, $parser );
		$cached_def = $cache->get( $cacheKeyForForm );

		if ( is_string( $cached_def ) ) {
			wfDebug( "Cache hit: Got form definition $cacheKeyForForm from cache\n" );
			return $cached_def;
		}

		wfDebug( "Cache miss: Form definition $cacheKeyForForm not found in cache\n" );
		return null;
	}

	/**
	 * Store a form definition in cache.
	 *
	 * @param string $form_id
	 * @param string $form_def
	 * @param Parser $parser
	 */
	protected static function cacheFormDefinition( $form_id, $form_def, Parser $parser ): void {
		global $wgPageFormsCacheFormDefinitions;

		if ( !$wgPageFormsCacheFormDefinitions ) {
			return;
		}

		$cache = self::getFormCache();
		$cacheKeyForForm = self::getCacheKey( $form_id, $parser );
		$cacheKeyForList = self::getCacheKey( $form_id );

		// Update list of form definitions
		$listOfFormKeys = $cache->get( $cacheKeyForList );
		// The list of values is used by self::purgeCache; keys are ignored.
		// This way we automatically override duplicates.
		$listOfFormKeys[$cacheKeyForForm] = $cacheKeyForForm;

		// We cache indefinitely ignoring $wgParserCacheExpireTime.
		// The reasoning is that there really is not point in expiring
		// rarely changed forms automatically (after one day per
		// default). Instead the cache is purged on storing/purging a
		// form definition.
		$cache->set( $cacheKeyForForm, $form_def );
		$cache->set( $cacheKeyForList, $listOfFormKeys );
		wfDebug( "Cached form definition $cacheKeyForForm\n" );
	}

	// -----------------------------------------------------------------------
	// Cache invalidation — Hook handlers
	// -----------------------------------------------------------------------

	/**
	 * Deletes the form definition associated with the given wiki page
	 * from the main cache.
	 *
	 * Hook: ArticlePurge
	 *
	 * @param WikiPage $wikipage
	 * @return bool
	 */
	public static function purgeCache( WikiPage $wikipage ): bool {
		if ( !$wikipage->getTitle()->inNamespace( PF_NS_FORM ) ) {
			return true;
		}

		$cache = self::getFormCache();
		$cacheKeyForList = self::getCacheKey( $wikipage->getId() );
		$listOfFormKeys = $cache->get( $cacheKeyForList );

		if ( !is_array( $listOfFormKeys ) ) {
			return true;
		}

		foreach ( $listOfFormKeys as $key ) {
			$cache->delete( $key );
			wfDebug( "Deleted cached form definition $key.\n" );
		}

		$cache->delete( $cacheKeyForList );
		wfDebug( "Deleted cached form definition references $cacheKeyForList.\n" );

		return true;
	}

	/**
	 * Deletes the form definition associated with the given wiki page
	 * from the main cache on save.
	 *
	 * Hook: MultiContentSave
	 *
	 * @param RenderedRevision $renderedRevision
	 * @return bool
	 */
	public static function purgeCacheOnSave( RenderedRevision $renderedRevision ): bool {
		$articleID = $renderedRevision->getRevision()->getPageId();
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $articleID );
		} else {
			// @codeCoverageIgnoreStart
			$wikiPage = WikiPage::newFromID( $articleID );
			// @codeCoverageIgnoreEnd
		}
		if ( $wikiPage == null ) {
			return true;
		}
		return self::purgeCache( $wikiPage );
	}

	// -----------------------------------------------------------------------
	// Cache infrastructure
	// -----------------------------------------------------------------------

	/**
	 * Get the cache object used by the form definition cache.
	 *
	 * @return BagOStuff
	 */
	public static function getFormCache(): BagOStuff {
		global $wgPageFormsFormCacheType, $wgParserCacheType;
		return ObjectCache::getInstance(
			( $wgPageFormsFormCacheType !== null ) ? $wgPageFormsFormCacheType : $wgParserCacheType
		);
	}

	/**
	 * Get a cache key for a form definition.
	 *
	 * @param string $formId Page ID of the form
	 * @param Parser|null $parser Provide to get a user-options-specific key
	 * @return string
	 */
	public static function getCacheKey( $formId, $parser = null ): string {
		$cache = self::getFormCache();

		return ( $parser === null )
			? $cache->makeKey( 'ext.PageForms.formdefinition', $formId )
			: $cache->makeKey(
				'ext.PageForms.formdefinition',
				$formId,
				$parser->getOptions()->optionsHash( ParserOptions::allCacheVaryingOptions() )
			);
	}
}
