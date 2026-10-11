<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * The values of a "values from wikidata" field: the SPARQL query for the filters of the
 * annotation, the request to the Wikidata endpoint and the reading of its answer.
 *
 * @ingroup PF
 */
class WikidataValues {

	/**
	 * Escape a string for safe interpolation into a SPARQL string literal
	 * delimited by double quotes (SPARQL 1.1 grammar: STRING_LITERAL2).
	 *
	 * Backslash must be escaped first, otherwise escaping the quotes
	 * afterwards would double-escape the backslashes just introduced.
	 *
	 * @param string $value
	 * @return string
	 */
	private static function escapeSparqlStringLiteral( string $value ): string {
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( '"', '\\"', $value );
		$value = str_replace( "\n", '\\n', $value );
		$value = str_replace( "\r", '\\r', $value );
		$value = str_replace( "\t", '\\t', $value );
		return $value;
	}

	/**
	 * This function is used for fetching the values from wikidata based on the provided
	 * annotations. For queries with substring, the function returns all the values which
	 * have the substring in it.
	 *
	 * @param string $query
	 * @param string|null $substring
	 * @return array
	 */
	public static function fetch( $query, $substring = null ) {
		$endpointUrl = "https://query.wikidata.org/sparql";
		$sparqlQueryString = self::buildQuery( $query, $substring );
		$opts = [
			'http' => [
				'method' => 'GET',
				'header' => [
					'Accept: application/sparql-results+json',
					'User-Agent: PageForms_API PHP/8.0'
				],
			],
		];
		$context = stream_context_create( $opts );

		$url = $endpointUrl . '?query=' . urlencode( $sparqlQueryString );
		$response = file_get_contents( $url, false, $context );
		return self::parseResponse( $response );
	}

	/**
	 * Builds the SPARQL query for a "values from wikidata" annotation.
	 *
	 * @param string $query The urlencoded filters, e.g. "P31=Q6256"
	 * @param string|null $substring
	 * @return string
	 */
	public static function buildQuery( $query, $substring = null ) {
		global $wgLanguageCode;

		$query = urldecode( $query );

		$filter_strings = explode( '&', $query );
		$filters = [];

		foreach ( $filter_strings as $filter ) {
			$temp = explode( "=", $filter );
			// The key is placed into the query as a property reference, so only
			// Wikidata property ids are accepted.
			if ( count( $temp ) < 2 || !preg_match( '/^P\d+$/', $temp[ 0 ] ) ) {
				continue;
			}
			$filters[ $temp[ 0 ] ] = $temp[ 1 ];
		}

		$attributesQuery = "";
		$count = 0;
		foreach ( $filters as $key => $val ) {
			$attributesQuery .= "wdt:" . $key;
			if ( is_numeric( str_replace( "Q", "", $val ) ) ) {
				$attributesQuery .= " wd:" . $val . ";";
			} else {
				$escapedVal = self::escapeSparqlStringLiteral( $val );
				$attributesQuery .= "?customLabel" . $count . " .
				?customLabel" . $count . " rdfs:label \"" . $escapedVal . "\"@" . $wgLanguageCode . " . ";
				$count++;
				$attributesQuery .= "?value ";
			}
		}
		unset( $count );
		$attributesQuery = rtrim( $attributesQuery, ";" );
		$attributesQuery = rtrim( $attributesQuery, ". ?value " );

		$escapedSubstring = self::escapeSparqlStringLiteral( strtolower( $substring ?? '' ) );
		$sparqlQueryString = "
SELECT DISTINCT ?valueLabel WHERE {
{
SELECT ?value  WHERE {
?value " . $attributesQuery . " .
?value rdfs:label ?valueLabel .
FILTER(LANG(?valueLabel) = \"" . $wgLanguageCode . "\") .
FILTER(REGEX(LCASE(?valueLabel), \"\\\\b" . $escapedSubstring . "\"))
} ";
		if ( $substring != null ) {
			global $wgPageFormsMaxAutocompleteValues;
			$sparqlQueryString .= "LIMIT " . ( $wgPageFormsMaxAutocompleteValues + 10 );
		}
		$sparqlQueryString .= "}
SERVICE wikibase:label { bd:serviceParam wikibase:language \"" . $wgLanguageCode . "\". }
}";
		if ( $substring != null ) {
			global $wgPageFormsMaxAutocompleteValues;
			$sparqlQueryString .= "LIMIT " . $wgPageFormsMaxAutocompleteValues;
		}
		return $sparqlQueryString;
	}

	/**
	 * Extracts the values from the JSON response of the Wikidata SPARQL endpoint.
	 *
	 * @param string|false $response
	 * @return string[]
	 */
	public static function parseResponse( $response ) {
		$results = [];
		if ( !is_string( $response ) ) {
			// The request failed.
			return $results;
		}
		$apiResults = json_decode( $response, true );
		if ( $apiResults != null ) {
			$apiResults = $apiResults[ 'results' ][ 'bindings' ];
			foreach ( $apiResults as $result ) {
				foreach ( $result as $key => $val ) {
					array_push( $results, $val[ 'value' ] );
				}
			}
		}
		return $results;
	}
}
