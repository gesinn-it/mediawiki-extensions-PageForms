<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MWException;
use TextContent;
use WikiPage;

/**
 * Access to the wiki that the forms depend on: page text, wiki pages, the database, the SMW store and the
 * list of forms.
 *
 * @ingroup PF
 */
class WikiAccess {

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

	public static function isTranslateEnabled() {
		return ExtensionRegistry::getInstance()->isLoaded( 'Translate' );
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
		$namespaceStrings = ParserAccess::getContLang()->getNamespaces();
		$formNSString = $namespaceStrings[PF_NS_FORM];
		return $formNSString . wfMessage( 'colon-separator' )->escaped();
	}

}
