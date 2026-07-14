<?php

declare( strict_types=1 );

/**
 * @covers \PFArrayMapTemplate
 * @group Database
 */
class PFArrayMapTemplateTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->insertPage(
			'Template:PFTestArrayMapTemplateBeautify',
			'<b>{{{1}}}</b>'
		);
	}

	private function parse( string $wikitext ): string {
		$parserOutput = $this->getServiceContainer()->getParserFactory()->create()->parse(
			$wikitext,
			Title::makeTitle( NS_MAIN, 'Test' ),
			ParserOptions::newFromAnon()
		);
		return Parser::stripOuterParagraph( $parserOutput->getRawText() );
	}

	public function testRunCallsTemplateOnceForEachDelimitedValue(): void {
		$result = $this->parse(
			'{{#arraymaptemplate:blue;red;yellow|PFTestArrayMapTemplateBeautify|;|;}}'
		);

		$this->assertSame( '<b>blue</b>;<b>red</b>;<b>yellow</b>', $result );
	}

	public function testRunDefaultsToCommaDelimiters(): void {
		$result = $this->parse(
			'{{#arraymaptemplate:blue,red|PFTestArrayMapTemplateBeautify}}'
		);

		$this->assertSame( '<b>blue</b>, <b>red</b>', $result );
	}

	public function testRunSkipsEmptySections(): void {
		$result = $this->parse(
			'{{#arraymaptemplate:blue,,red|PFTestArrayMapTemplateBeautify|,|,}}'
		);

		$this->assertSame( '<b>blue</b>,<b>red</b>', $result );
	}

	public function testRunWithEmptyValueProducesEmptyResult(): void {
		$result = $this->parse(
			'{{#arraymaptemplate:|PFTestArrayMapTemplateBeautify}}'
		);

		$this->assertSame( '', $result );
	}
}
