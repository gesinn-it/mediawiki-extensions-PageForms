<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use BagOStuff;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinition;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use ObjectCache;
use Parser;
use ParserOptions;
use ParserOutput;
use PFUtils;
use RequestContext;
use StringUtils;
use Title;
use WikiPage;

/**
 * Form-definition caching subsystem for PageForms.
 *
 * Responsible for storing, retrieving and invalidating parsed form definitions
 * in the MW object cache, and for loading preload-page text used to pre-fill
 * form fields.
 *
 * Extracted from FormUtils to give the caching concern a single, testable
 * home.  The hooks ArticlePurge and MultiContentSave are registered directly
 * against this class in extension.json.
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 * @ingroup PF
 */
class FormCache {

	/**
	 * Part of the key of a cached form definition. Raise it when the stored array format
	 * changes: entries of an older format are then never read and are rebuilt on the next
	 * request (and removed from the cache when the form is purged).
	 */
	private const FORMAT_VERSION = 2;

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
		if ( $preloadTitle === null ) {
			return '';
		}

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$user = RequestContext::getMain()->getUser();
		if ( !$permissionManager->userCan( 'read', $user, $preloadTitle ) ) {
			return '';
		}

		$text = PFUtils::getPageText( $preloadTitle ) ?? '';
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
	 * @param int|null $form_id Page ID of the form page (optional)
	 * @return string Parsed form definition HTML/wikitext
	 */
	public static function getFormDefinition( Parser $parser, ?string $form_def = null, ?int $form_id = null ): string {
		return self::getFormDefinitionModel( $parser, $form_def, $form_id )->toWikitext();
	}

	/**
	 * Parse the form definition and return it as a model, using or populating the cache.
	 *
	 * @param Parser $parser
	 * @param string|null $form_def Raw wikitext of the form definition (optional)
	 * @param int|null $form_id Page ID of the form page (optional)
	 * @return FormDefinition The parsed form definition
	 */
	public static function getFormDefinitionModel(
		Parser $parser, ?string $form_def = null, ?int $form_id = null
	): FormDefinition {
		if ( $form_id !== null ) {
			$cachedDef = self::getFormDefinitionFromCache( $form_id, $parser );

			if ( $cachedDef !== null ) {
				return $cachedDef;
			}
		}

		if ( $form_id !== null ) {
			$form_title = Title::newFromID( $form_id );
			$form_def = PFUtils::getPageText( $form_title ) ?? '';
		} elseif ( $form_def == null ) {
			return new FormDefinition();
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

		// Replace all PF tags by strip markers. FormDefinitionReader::findNextTag() finds the tags
		// (including correct handling of contained braces), i.e. {{{field|foo|default={{Bar}}}}} is
		// not a problem.
		$items = [];
		$reader = new FormDefinitionReader();
		$stripped = '';
		$position = 0;
		$tag = $reader->findNextTag( $form_def );
		while ( $tag !== null ) {
			$markerIndex = count( $items );
			$items[] = substr( $form_def, $tag['start'], $tag['end'] - $tag['start'] );
			$stripped .= substr( $form_def, $position, $tag['start'] - $position ) . "$rnd-item-$markerIndex-$rnd";
			$position = $tag['end'];
			$tag = $reader->findNextTag( $form_def, $position );
		}
		$form_def = $stripped . substr( $form_def, $position );

		// Parse wiki-text.
		$title = $parser->getTitle();
		// We need to pass "false" in to the parse() $clearState param so that
		// embedding Special:RunQuery will work.
		//
		// Semantic MediaWiki must not take this parse for the content of the page: see
		// SmwPurgeRequestShield.
		$options = $parser->getOptions();
		$output = SmwPurgeRequestShield::run(
			$title,
			static fn (): ParserOutput => $parser->parse( $form_def, $title, $options, true, false )
		);
		$form_def = $output->getText();
		$form_def = preg_replace_callback(
			"/{$rnd}-item-(\d+)-{$rnd}/",
			static function ( array $matches ) use ( $items ) {
				$markerIndex = (int)$matches[1];
				return $items[$markerIndex];
			},
			$form_def
		);

		$definition = $reader->read( $form_def );

		if ( $output->getCacheTime() == -1 ) {
			$form_wikipage = $form_id !== null
				? MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $form_id )
				: null;
			if ( $form_wikipage !== null ) {
				self::purgeCache( $form_wikipage );
			}
			wfDebug( "Caching disabled for form definition $form_id\n" );
		} elseif ( $form_id !== null ) {
			self::cacheFormDefinition( $form_id, $definition, $parser );
		}

		return $definition;
	}

	/**
	 * Get a form definition from cache.
	 *
	 * @param int $form_id
	 * @param Parser $parser
	 * @return FormDefinition|null Cached definition, or null on miss / cache disabled
	 */
	protected static function getFormDefinitionFromCache( int $form_id, Parser $parser ): ?FormDefinition {
		global $wgPageFormsCacheFormDefinitions;

		if ( !$wgPageFormsCacheFormDefinitions ) {
			return null;
		}

		$cache = self::getFormCache();
		$cacheKeyForForm = self::getCacheKey( $form_id, $parser );
		$cached_def = $cache->get( $cacheKeyForForm );

		if ( is_array( $cached_def ) ) {
			wfDebug( "Cache hit: Got form definition $cacheKeyForForm from cache\n" );
			return FormDefinition::fromArray( $cached_def );
		}

		wfDebug( "Cache miss: Form definition $cacheKeyForForm not found in cache\n" );
		return null;
	}

	/**
	 * Store a form definition in cache.
	 *
	 * @param int $form_id
	 * @param FormDefinition $form_def
	 * @param Parser $parser
	 */
	protected static function cacheFormDefinition( int $form_id, FormDefinition $form_def, Parser $parser ): void {
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
		$cache->set( $cacheKeyForForm, $form_def->toArray() );
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
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $articleID );
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
		return ObjectCache::getInstance( $wgPageFormsFormCacheType ?? $wgParserCacheType );
	}

	/**
	 * Get a cache key for a form definition.
	 *
	 * @param int $formId Page ID of the form
	 * @param Parser|null $parser Provide to get a user-options-specific key
	 * @return string
	 */
	public static function getCacheKey( int $formId, ?Parser $parser = null ): string {
		$cache = self::getFormCache();

		return ( $parser === null )
			? $cache->makeKey( 'ext.PageForms.formdefinition', $formId )
			: $cache->makeKey(
				'ext.PageForms.formdefinition',
				'v' . self::FORMAT_VERSION,
				$formId,
				$parser->getOptions()->optionsHash( ParserOptions::allCacheVaryingOptions() )
			);
	}
}
