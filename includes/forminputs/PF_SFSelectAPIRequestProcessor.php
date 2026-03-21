<?php
/**
 * Processes API requests for the SF_Select input type.
 *
 * Executes an SMW #ask query or a parser function and returns the resulting
 * values in plainlist format for JS consumption.
 *
 * Originally maintained as src/ApiSemanticFormsSelectRequestProcessor.php in
 * the SemanticFormsSelect extension.
 *
 * @author Jason Zhang
 * @author Toni Hermoso Pulido
 * @author mwjames
 * @file
 * @ingroup PFFormInput
 */

/**
 * @ingroup PFFormInput
 */
class PFSFSelectAPIRequestProcessor {

	/** @var \Parser */
	private $parser;

	/** @var callable */
	private $getSmwResultFromFunctionParams;

	/**
	 * @param \Parser $parser
	 * @param callable|null $getSmwResultFromFunctionParams
	 *   Injectable for testing. Defaults to direct SMWQueryProcessor call.
	 */
	public function __construct( $parser, $getSmwResultFromFunctionParams = null ) {
		$this->parser = $parser;
		$this->getSmwResultFromFunctionParams = $getSmwResultFromFunctionParams
			?? [ self::class, 'defaultGetSmwResultFromFunctionParams' ];
	}

	/**
	 * @param array $parameters Keys: query, sep, approach
	 * @return stdClass { values: string[], count: int }
	 * @throws InvalidArgumentException
	 */
	public function getJsonDecodedResultValuesForRequestParameters( array $parameters ) {
		if ( !isset( $parameters['query'] ) || !isset( $parameters['sep'] ) ) {
			throw new InvalidArgumentException( 'Missing required query/sep parameter' );
		}

		$this->parser->firstCallInit();

		if ( isset( $parameters['approach'] ) && $parameters['approach'] === 'smw' ) {
			$json = $this->doProcessQueryFor( $parameters['query'], $parameters['sep'] );
		} else {
			$json = $this->doProcessFunctionFor( $parameters['query'], $parameters['sep'] );
		}

		return json_decode( $json );
	}

	private function doProcessQueryFor( $querystr, $sep = ',' ) {
		$querystr = str_replace( [ '&lt;', '&gt;' ], [ '<', '>' ], $querystr );

		$rawparams = $this->extractRawParameters( $querystr, $sep );
		$f = str_replace( ';', '|', $rawparams[0] );
		$rawparams[0] = $this->parser->replaceVariables( $f );

		$result = ( $this->getSmwResultFromFunctionParams )( $rawparams );
		$values = $this->getFormattedValuesFrom( $sep, $result );

		return json_encode( [ 'values' => $values, 'count' => count( $values ) ] );
	}

	private function extractRawParameters( string $querystr, string $sep ): array {
		$rawparams = explode( ';', $querystr );
		// Strip any existing format= and sep= params; inject canonical values below.
		$rawparams = array_filter( $rawparams, static function ( string $param ): bool {
			return substr_compare( $param, 'format=', 0, 7 ) !== 0
				&& substr_compare( $param, 'sep=', 0, 4 ) !== 0;
		} );
		$rawparams = array_values( $rawparams );
		$rawparams[] = 'format=plainlist';
		$rawparams[] = 'sep=' . $sep;
		return $rawparams;
	}

	private function doProcessFunctionFor( $query, $sep = ',' ) {
		$query = str_replace(
			[ '&lt;', '&gt;', 'sep=;' ],
			[ '<', '>', "sep={$sep};" ],
			$query
		);

		$f = str_replace( ';', '|', $query );
		$values = $this->getFormattedValuesFrom( $sep, $this->parser->replaceVariables( $f ) );

		return json_encode( [ 'values' => $values, 'count' => count( $values ) ] );
	}

	private function getFormattedValuesFrom( $sep, $values ) {
		if ( strpos( $values ?? '', $sep ) === false ) {
			return [ $values ];
		}
		$values = explode( $sep, $values );
		$values = array_map( 'trim', $values );
		$values = array_unique( $values );
		array_unshift( $values, '' );
		return $values;
	}

	/**
	 * Default SMW query execution. Requires SemanticMediaWiki.
	 *
	 * @param array $rawparams
	 * @return string
	 */
	private static function defaultGetSmwResultFromFunctionParams( array $rawparams ): string {
		// @codeCoverageIgnoreStart
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' ) ) {
			return '';
		}
		[ $query, $params ] = SMWQueryProcessor::getQueryAndParamsFromFunctionParams(
			$rawparams, SMW_OUTPUT_WIKI, SMWQueryProcessor::INLINE_QUERY, false
		);
		return SMWQueryProcessor::getResultFromQuery(
			$query, $params, SMW_OUTPUT_WIKI, SMWQueryProcessor::INLINE_QUERY
		);
		// @codeCoverageIgnoreEnd
	}
}
