<?php

use MediaWiki\Extension\PageForms\ConceptPages;

/**
 * The pages of an existing concept are covered by the JSONScript cases of the pfautocomplete API.
 *
 * @covers \MediaWiki\Extension\PageForms\ConceptPages
 * @group Database
 * @group PF
 */
class ConceptPagesTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( !defined( 'SMW_NS_CONCEPT' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
	}

	public function testGetReportsAMissingConcept(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'PFConceptPagesNoSuchConcept' );

		ConceptPages::get( 'PFConceptPagesNoSuchConcept' );
	}

	public function testGetEscapesTheNameOfAMissingConceptInTheMessage(): void {
		try {
			ConceptPages::get( "PFConceptPagesNoSuchConcept<b>''bold''" );
			$this->fail( 'A missing concept must be reported' );
		} catch ( MWException $e ) {
			$this->assertStringNotContainsString( '<b>', $e->getMessage() );
			$this->assertStringNotContainsString( '<i>', $e->getMessage() );
		}
	}
}
