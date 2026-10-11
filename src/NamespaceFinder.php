<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;
use PFUtils;

/**
 * Finds a namespace by the name a form author wrote in a "values from namespace" argument.
 *
 * @ingroup PF
 */
class NamespaceFinder {

	/**
	 * Get the exact canonical namespace string, given a user-created string
	 *
	 * A string that is not the name of a namespace is returned unchanged: "Foo:ABC" is a page of the
	 * main namespace, whose blank namespace text must not be taken for the name.
	 *
	 * @param string $namespaceStr
	 * @return string
	 */
	public static function standardize( string $namespaceStr ): string {
		$pageValue = PageValue::newFromText( "$namespaceStr:ABC" );
		$namespaceText = $pageValue ? $pageValue->getNamespaceText() : '';
		return $namespaceText === '' ? $namespaceStr : $namespaceText;
	}

	/**
	 * The name of a namespace as it is looked up: in canonical form, with the blank name for the
	 * main namespace, which a form author can write as 'Main'.
	 *
	 * @param string $namespaceName
	 * @return string
	 */
	public static function normalize( string $namespaceName ): string {
		$namespaceName = self::standardize( $namespaceName );
		if ( $namespaceName === 'Main' || $namespaceName === 'main' ) {
			return '';
		}
		return $namespaceName;
	}

	/**
	 * The number of the namespace with the name, in the language of the wiki or, failing that, in
	 * English.
	 *
	 * @param string $normalizedName A name as returned by normalize()
	 * @return int|null Null if the wiki has no such namespace
	 */
	public static function findCode( string $normalizedName ): ?int {
		global $wgLanguageCode;

		$code = array_search( $normalizedName, PFUtils::getContLang()->getNamespaces(), true );
		if ( $code === false && $wgLanguageCode !== 'en' ) {
			$english = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
			$code = array_search( $normalizedName, $english->getNamespaces(), true );
		}
		return $code === false ? null : $code;
	}
}
