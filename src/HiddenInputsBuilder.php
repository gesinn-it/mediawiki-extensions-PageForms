<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;

/**
 * Converts a query-string-shaped array into a series of hidden <input>
 * elements, one per key/value pair. Centralizes a conversion that was
 * previously duplicated across the parser-function classes that recreate a
 * passed-in query string as hidden form fields.
 */
class HiddenInputsBuilder {

	/**
	 * @param array $queryArr Query-string-shaped array, as accepted by
	 *  http_build_query().
	 * @param callable|null $attributesForPair Optional callback receiving
	 *  ( string $name, string $value ) and returning the extra HTML
	 *  attributes array for that pair's hidden input. Defaults to no extra
	 *  attributes.
	 * @return string Concatenated HTML of one Html::hidden() per pair.
	 */
	public static function fromQueryArray( array $queryArr, ?callable $attributesForPair = null ): string {
		if ( $queryArr === [] ) {
			return '';
		}

		$html = '';
		$query_components = explode( '&', http_build_query( $queryArr, '', '&' ) );

		foreach ( $query_components as $query_component ) {
			$var_and_val = explode( '=', $query_component, 2 );
			if ( count( $var_and_val ) == 2 ) {
				$name = urldecode( $var_and_val[0] );
				$value = urldecode( $var_and_val[1] );
				$attributes = $attributesForPair !== null ? $attributesForPair( $name, $value ) : [];
				$html .= Html::hidden( $name, $value, $attributes );
			}
		}

		return $html;
	}
}
