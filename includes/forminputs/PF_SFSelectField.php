<?php
/**
 * Holds the configuration for a single SF_Select field instance.
 *
 * Originally maintained as src/SelectField.php in the SemanticFormsSelect
 * extension.
 *
 * @author Alexander Gesinn
 * @file
 * @ingroup PFFormInput
 */

/**
 * @ingroup PFFormInput
 */
class PFSFSelectField {

	/** @var \Parser */
	private $mParser;

	/** @var string[]|null resolved static values (when query/function has no @@@@) */
	private $mValues = null;

	/** @var bool */
	private $mHasStaticValues = false;

	/** @var array JS-side config data accumulated by the setter methods */
	private $mData = [];

	private $mDelimiter = ',';

	public function __construct( $parser ) {
		$this->mParser = $parser;
	}

	/**
	 * Set up from a `query=` field arg.
	 *
	 * For queries without `@@@@`, the result is resolved immediately via SMW
	 * and stored as static values. Requires SemanticMediaWiki.
	 *
	 * @param array $other_args
	 */
	public function setQuery( array $other_args ) {
		$querystr = $other_args['query'];
		$querystr = str_replace( [ '~', '(', ')' ], [ '=', '[', ']' ], $querystr );
		$this->mData['selectquery'] = $querystr;

		if ( strpos( $querystr, '@@@@' ) === false ) {
			// Static query — resolve immediately if SMW is available.
			if ( !ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' ) ) {
				// @codeCoverageIgnoreStart
				return;
				// @codeCoverageIgnoreEnd
			}
			$rawparams = explode( ';', $querystr );
			[ $query, $params ] = SMWQueryProcessor::getQueryAndParamsFromFunctionParams(
				$rawparams, SMW_OUTPUT_WIKI, SMWQueryProcessor::INLINE_QUERY, false
			);
			$result = SMWQueryProcessor::getResultFromQuery(
				$query, $params, SMW_OUTPUT_WIKI, SMWQueryProcessor::INLINE_QUERY
			);
			$this->mValues = $this->getFormattedValuesFrom( $this->mDelimiter, $result );
			$this->mHasStaticValues = true;
		}
	}

	/**
	 * Set up from a `function=` field arg.
	 *
	 * For functions without `@@@@`, the value is resolved immediately via the
	 * parser.
	 *
	 * @param array $other_args
	 */
	public function setFunction( array $other_args ) {
		$function = $other_args['function'];
		$function = '{{#' . $function . '}}';
		$function = str_replace( [ '~', '(', ')' ], [ '=', '[', ']' ], $function );
		$this->mData['selectfunction'] = $function;

		if ( strpos( $function, '@@@@' ) === false ) {
			$f = str_replace( ';', '|', $function );
			$this->setValues( $this->mParser->replaceVariables( $f ) );
			$this->mHasStaticValues = true;
		}
	}

	public function setSelectIsMultiple( array $other_args ) {
		$this->mData['selectismultiple'] = array_key_exists( 'part_of_multiple', $other_args );
	}

	public function setSelectTemplate( $input_name = '' ) {
		$index = strpos( $input_name, '[' );
		$this->mData['selecttemplate'] = substr( $input_name, 0, $index );
	}

	public function setSelectField( $input_name = '' ) {
		$index = strrpos( $input_name, '[' );
		$this->mData['selectfield'] = substr( $input_name, $index + 1, strlen( $input_name ) - $index - 2 );
	}

	public function setValueTemplate( array $other_args ) {
		$this->mData['valuetemplate'] =
			array_key_exists( 'sametemplate', $other_args )
				? $this->mData['selecttemplate']
				: $other_args['template'];
	}

	public function setValueField( array $other_args ) {
		$this->mData['valuefield'] = $other_args['field'];
	}

	public function setSelectRemove( array $other_args ) {
		$this->mData['selectrm'] = array_key_exists( 'rmdiv', $other_args );
	}

	public function setLabel( array $other_args ) {
		$this->mData['label'] = array_key_exists( 'label', $other_args );
	}

	/**
	 * Set the delimiter from `sep=` (preferred) or `delimiter=` (legacy).
	 *
	 * @param array $other_args
	 */
	public function setDelimiter( array $other_args ) {
		$this->mDelimiter = $GLOBALS['wgPageFormsListSeparator'];
		if ( array_key_exists( 'sep', $other_args ) ) {
			$this->mDelimiter = $other_args['sep'];
		} elseif ( array_key_exists( 'delimiter', $other_args ) ) {
			$this->mDelimiter = $other_args['delimiter'];
		}
		$this->mData['sep'] = $this->mDelimiter;
	}

	public function getData(): array {
		return $this->mData;
	}

	public function getValues(): ?array {
		return $this->mValues;
	}

	public function getDelimiter(): string {
		return $this->mDelimiter;
	}

	public function hasStaticValues(): bool {
		return $this->mHasStaticValues;
	}

	private function setValues( $values ) {
		$values = explode( $this->mDelimiter, $values );
		$values = array_map( 'trim', $values );
		$values = array_unique( $values );
		$this->mValues = $values;
	}

	private function getFormattedValuesFrom( $sep, $values ) {
		if ( strpos( $values, $sep ) === false ) {
			return [ $values ];
		}
		$values = explode( $sep, $values );
		$values = array_map( 'trim', $values );
		$values = array_unique( $values );
		return $values;
	}
}
