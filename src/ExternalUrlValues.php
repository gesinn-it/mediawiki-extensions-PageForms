<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;

/**
 * The values of a "values from url" field.
 *
 * @ingroup PF
 */
class ExternalUrlValues {

	/**
	 * The values of a "values from url" field: what the URL that is set up for the alias answers
	 * for the substring.
	 *
	 * @param string $externalUrlAlias The key of the URL in $wgPageFormsAutocompletionURLs
	 * @param string|null $substring
	 * @return array|\Message The values, or the message for what went wrong
	 */
	public static function get( string $externalUrlAlias, ?string $substring ) {
		global $wgPageFormsAutocompletionURLs;
		if ( $wgPageFormsAutocompletionURLs === [] ) {
			return wfMessage( 'pf-nocompletionurls' );
		}
		if ( !array_key_exists( $externalUrlAlias, $wgPageFormsAutocompletionURLs ) ) {
			return wfMessage( 'pf-invalidexturl' );
		}
		$url = $wgPageFormsAutocompletionURLs[$externalUrlAlias];
		if ( $url === '' ) {
			return wfMessage( 'pf-blankexturl' );
		}
		$url = str_replace( '<substr>', urlencode( $substring ?? '' ), $url );
		$page_contents = MediaWikiServices::getInstance()->getHttpRequestFactory()->get( $url, [], __METHOD__ );
		if ( $page_contents === null || $page_contents === '' ) {
			return wfMessage( 'pf-externalpageempty' );
		}
		$data = json_decode( $page_contents );
		if ( $data === null || $data === [] ) {
			return wfMessage( 'pf-externalpagebadjson' );
		}
		$return_values = [];
		foreach ( $data->pfautocomplete as $val ) {
			$return_values[] = (array)$val;
		}
		return $return_values;
	}
}
