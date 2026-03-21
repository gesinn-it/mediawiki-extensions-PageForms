<?php
/**
 * API module for the SF_Select input type.
 *
 * Accepts an SMW #ask query or parser function, executes it server-side, and
 * returns the resulting values as JSON for dynamic select population.
 *
 * Originally maintained as src/ApiSemanticFormsSelect.php in the
 * SemanticFormsSelect extension.
 *
 * @author Jason Zhang
 * @author Toni Hermoso Pulido
 * @file
 * @ingroup PFFormInput
 */

use MediaWiki\MediaWikiServices;

/**
 * @ingroup PFFormInput
 */
class PFSFSelectAPI extends ApiBase {

	/** @inheritDoc */
	public function execute() {
		$parser = $this->createParser();
		$processor = new PFSFSelectAPIRequestProcessor( $parser );

		$resultValues = $processor->getJsonDecodedResultValuesForRequestParameters(
			$this->extractRequestParams()
		);

		$result = $this->getResult();
		$result->setIndexedTagName( $resultValues->values, 'value' );
		$result->addValue( $this->getModuleName(), 'values', $resultValues->values );
		$result->addValue( $this->getModuleName(), 'count', $resultValues->count );

		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'approach' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'query' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'sep' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			]
		];
	}

	private function createParser() {
		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$parser->setTitle( Title::newFromText( 'NO TITLE' ) );
		$parser->setOptions( new ParserOptions( $this->getUser() ) );
		$parser->resetOutput();
		return $parser;
	}
}
