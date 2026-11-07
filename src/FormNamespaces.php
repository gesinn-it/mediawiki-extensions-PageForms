<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;

/**
 * Namespace facts that Page Forms needs: capitalisation, canonical and localised names, and which form
 * names are ignored.
 *
 * @ingroup PF
 */
class FormNamespaces {

	public static function isCapitalized( $index ) {
		return MediaWikiServices::getInstance()
			->getNamespaceInfo()
			->isCapitalized( $index );
	}

	public static function getCanonicalName( $index ) {
		return MediaWikiServices::getInstance()
			->getNamespaceInfo()
			->getCanonicalName( $index );
	}

	public static function getNsText( $index ) {
		return MediaWikiServices::getInstance()
			->getContentLanguage()
			->getNsText( $index ) ?: '';
	}

	/**
	 * Return whether to "ignore" (treat as a non-form) a form with this
	 * name, based on whether it matches any of the specified text patterns.
	 *
	 * @param string $formName
	 * @return bool
	 */
	public static function ignoreFormName( $formName ) {
		global $wgPageFormsIgnoreTitlePattern;

		if ( !is_array( $wgPageFormsIgnoreTitlePattern ) ) {
			$wgPageFormsIgnoreTitlePattern = [ $wgPageFormsIgnoreTitlePattern ];
		}

		foreach ( $wgPageFormsIgnoreTitlePattern as $pattern ) {
			if ( $pattern !== '' && preg_match( '/' . $pattern . '/', $formName ) ) {
				return true;
			}
		}

		return false;
	}

}
