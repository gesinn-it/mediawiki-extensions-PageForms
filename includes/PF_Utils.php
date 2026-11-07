<?php
/**
 * Helper functions for the Page Forms extension.
 *
 * @author Yaron Koren
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\FormAssets;
use MediaWiki\Extension\PageForms\FormNamespaces;
use MediaWiki\Extension\PageForms\FormTagParser;
use MediaWiki\Extension\PageForms\PageLinks;
use MediaWiki\Extension\PageForms\ParserAccess;
use MediaWiki\Extension\PageForms\QueryArrayMerger;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

class PFUtils {

	/**
	 * @deprecated Use {@see ParserAccess::getContLang()}
	 */
	public static function getContLang() {
		return ParserAccess::getContLang();
	}

	/**
	 * @deprecated Use {@see ParserAccess::getParser()}
	 */
	public static function getParser() {
		return ParserAccess::getParser();
	}

	/**
	 * @deprecated Use {@see ParserAccess::ensureParserInitialized()}
	 */
	public static function ensureParserInitialized( Parser $parser, User $user ): Parser {
		return ParserAccess::ensureParserInitialized( $parser, $user );
	}

	/**
	 * @deprecated Use {@see ParserAccess::getParserOutputHtml()}
	 */
	public static function getParserOutputHtml( ParserOutput $output, ParserOptions $options ): string {
		return ParserAccess::getParserOutputHtml( $output, $options );
	}

	/**
	 * @deprecated Use {@see ParserAccess::ensureParserReadyForTagParse()}
	 */
	public static function ensureParserReadyForTagParse( Parser $parser, User $user, ?Title $title = null ): Parser {
		return ParserAccess::ensureParserReadyForTagParse( $parser, $user, $title );
	}

	/**
	 * @deprecated Use {@see PageLinks::linkForSpecialPage()}
	 */
	public static function linkForSpecialPage( $linkRenderer, $specialPageName ) {
		return PageLinks::linkForSpecialPage( $linkRenderer, $specialPageName );
	}

	/**
	 * @deprecated Use {@see PageLinks::makeLink()}
	 */
	public static function makeLink( $linkRenderer, $title, $msg = null, $attrs = [], $params = [] ) {
		return PageLinks::makeLink( $linkRenderer, $title, $msg, $attrs, $params );
	}

	/**
	 * @deprecated Use {@see PageLinks::titleURLString()}
	 */
	public static function titleURLString( $title ) {
		return PageLinks::titleURLString( $title );
	}

	/**
	 * Gets the text contents of a page with the passed-in Title object.
	 * @param Title $title
	 * @param int $audience
	 * @return string|null
	 */
	public static function getPageText( $title, $audience = RevisionRecord::FOR_PUBLIC ) {
		$wikiPage = self::newWikiPageFromTitle( $title );
		$content = $wikiPage->getContent( $audience );
		if ( $content instanceof TextContent ) {
			return $content->getText();
		} else {
			return null;
		}
	}

	/**
	 * Creates a WikiPage for the given Title, using the correct API for the current MW version.
	 *
	 * @param Title $title
	 * @return WikiPage
	 */
	public static function newWikiPageFromTitle( Title $title ): WikiPage {
		return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
	}

	/**
	 * @deprecated Use {@see PageLinks::getSpecialPage()}
	 */
	public static function getSpecialPage( $pageName ) {
		return PageLinks::getSpecialPage( $pageName );
	}

	/**
	 * Helper function to get the SMW data store, if SMW is installed.
	 * @return \SMW\Store|null
	 */
	public static function getSMWStore() {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			return \SMW\StoreFactory::getStore();
		} else {
			return null;
		}
	}

	/**
	 * @deprecated Use {@see PageLinks::linkText()}
	 */
	public static function linkText( $namespace, $name, $text = null ) {
		return PageLinks::linkText( $namespace, $name, $text );
	}

	/**
	 * @deprecated Use {@see PageLinks::printRedirectForm()}
	 */
	public static function printRedirectForm(
		$title,
		$page_contents,
		$edit_summary,
		$is_save,
		$user
	) {
		return PageLinks::printRedirectForm( $title, $page_contents, $edit_summary, $is_save, $user );
	}

	/**
	 * @deprecated Use {@see FormAssets::addFormRLModules()}
	 */
	public static function addFormRLModules( $parser = null ) {
		return FormAssets::addFormRLModules( $parser );
	}

	/**
	 * Returns a read-only database connection, using the correct API for the current MW version.
	 *
	 * @return \Wikimedia\Rdbms\IReadableDatabase|\Wikimedia\Rdbms\IDatabase
	 */
	public static function getReplicaDB() {
		if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
			return MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		}
		// MW < 1.42: getConnectionProvider() did not exist; getDBLoadBalancer() was the only option
		return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
	}

	/**
	 * Returns an array of all form names on this wiki.
	 * @return string[]
	 */
	public static function getAllForms() {
		$dbr = self::getReplicaDB();
		$res = $dbr->select( 'page',
			'page_title',
			[ 'page_namespace' => PF_NS_FORM,
				'page_is_redirect' => false ],
			__METHOD__,
			[ 'ORDER BY' => 'page_title' ] );
		$form_names = [];
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			$form_names[] = str_replace( '_', ' ', $row[0] );
		}
		$res->free();
		if ( count( $form_names ) == 0 ) {
			// This case requires special handling in the UI.
			throw new MWException( wfMessage( 'pf-noforms-error' )->parse() );
		}
		return $form_names;
	}

	public static function getFormDropdownLabel() {
		$namespaceStrings = \MediaWiki\Extension\PageForms\ParserAccess::getContLang()->getNamespaces();
		$formNSString = $namespaceStrings[PF_NS_FORM];
		return $formNSString . wfMessage( 'colon-separator' )->escaped();
	}

	/**
	 * @deprecated Use {@see FormTagParser::convertBackToPipes()}
	 */
	public static function convertBackToPipes( $s ) {
		return FormTagParser::convertBackToPipes( $s );
	}

	/**
	 * @deprecated Use {@see FormTagParser::smartSplitFormTag()}
	 */
	public static function smartSplitFormTag( $string ) {
		return FormTagParser::smartSplitFormTag( $string );
	}

	/**
	 * @deprecated Use {@see FormTagParser::getFormTagComponents()}
	 */
	public static function getFormTagComponents( $str ) {
		return FormTagParser::getFormTagComponents( $str );
	}

	/**
	 * @deprecated Use {@see FormTagParser::getWordForYesOrNo()}
	 */
	public static function getWordForYesOrNo( $isYes ) {
		return FormTagParser::getWordForYesOrNo( $isYes );
	}

	/**
	 * @deprecated Use {@see QueryArrayMerger::arrayMergeRecursiveDistinct()}
	 */
	public static function arrayMergeRecursiveDistinct( array &$array1, array &$array2 ) {
		return QueryArrayMerger::arrayMergeRecursiveDistinct( $array1, $array2 );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::ignoreFormName()}
	 */
	public static function ignoreFormName( $formName ) {
		return FormNamespaces::ignoreFormName( $formName );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::isCapitalized()}
	 */
	public static function isCapitalized( $index ) {
		return FormNamespaces::isCapitalized( $index );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::getCanonicalName()}
	 */
	public static function getCanonicalName( $index ) {
		return FormNamespaces::getCanonicalName( $index );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::getNsText()}
	 */
	public static function getNsText( $index ) {
		return FormNamespaces::getNsText( $index );
	}

	public static function isTranslateEnabled() {
		return ExtensionRegistry::getInstance()->isLoaded( 'Translate' );
	}

	/**
	 * @deprecated Use {@see FormTagParser::strReplaceFirst()}
	 */
	public static function strReplaceFirst( string $search, string $replace, string $subject ): string {
		return FormTagParser::strReplaceFirst( $search, $replace, $subject );
	}
}
