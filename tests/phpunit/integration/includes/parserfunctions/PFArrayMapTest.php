<?php

declare( strict_types=1 );

/**
 * @covers \PFArrayMap
 * @group Database
 */
class PFArrayMapTest extends MediaWikiIntegrationTestCase {

	private function parse( string $wikitext ): string {
		$parserOutput = $this->getServiceContainer()->getParserFactory()->create()->parse(
			$wikitext,
			Title::makeTitle( NS_MAIN, 'Test' ),
			ParserOptions::newFromAnon()
		);
		return Parser::stripOuterParagraph( $parserOutput->getRawText() );
	}

	public static function provideArrayMap(): array {
		return [
			'default comma delimiter, default new_delimiter' => [
				'{{#arraymap:blue,red,yellow|,|x|(x)}}',
				'(blue), (red), (yellow)',
			],
			'custom delimiter and new_delimiter' => [
				'{{#arraymap:blue;red;yellow|;|x|[x]|;}}',
				'[blue];[red];[yellow]',
			],
			'custom conjunction for the last element' => [
				'{{#arraymap:a,b,c|,|x|x|,\s|and}}',
				'a, b and c',
			],
			'empty sections are skipped' => [
				'{{#arraymap:a,,b|,|x|x}}',
				'a, b',
			],
			'empty value produces empty result' => [
				'{{#arraymap:|,|x|(x)}}',
				'',
			],
			'formula without the var placeholder repeats verbatim per element' => [
				'{{#arraymap:a,b|,|x|CONST}}',
				'CONST, CONST',
			],
		];
	}

	/**
	 * @dataProvider provideArrayMap
	 */
	public function testRun( string $wikitext, string $expected ): void {
		$this->assertSame( $expected, $this->parse( $wikitext ) );
	}

	public function testNewlineEscapeInDelimiterIsHonoured(): void {
		$result = $this->parse( '{{#arraymap:a;b|;|x|(x)|\n}}' );

		$this->assertSame( "(a)\n(b)", $result );
	}
}
