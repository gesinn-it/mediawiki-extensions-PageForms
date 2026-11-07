<?php

use MediaWiki\Extension\PageForms\FormTagParser;

/**
 * @covers \MediaWiki\Extension\PageForms\FormTagParser
 * @group Database
 */
class FormTagParserWikiTest extends MediaWikiIntegrationTestCase {

	public function testGetWordForYesOrNoReturnsAWordThatDiffersForTrueAndFalse() {
		$yes = FormTagParser::getWordForYesOrNo( true );
		$no = FormTagParser::getWordForYesOrNo( false );

		$this->assertIsString( $yes );
		$this->assertNotEmpty( $yes );
		$this->assertIsString( $no );
		$this->assertNotEmpty( $no );
		$this->assertNotSame( $yes, $no );
	}

}
