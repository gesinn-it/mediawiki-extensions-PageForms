<?php

declare( strict_types=1 );

/**
 * @covers \PFAutoEditRating
 * @group Database
 */
class PFAutoEditRatingTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'PageFormsAutoeditNamespaces', [ NS_MAIN ] );
	}

	private function parse( string $wikitext ): string {
		$parserOutput = $this->getServiceContainer()->getParserFactory()->create()->parse(
			$wikitext,
			Title::makeTitle( NS_MAIN, 'PFTestAutoEditRatingPage' ),
			ParserOptions::newFromAnon()
		);
		return Parser::stripOuterParagraph( $parserOutput->getRawText() );
	}

	public function testDefaultRatingAttributesAreRendered(): void {
		$result = $this->parse( '{{#autoedit_rating:}}' );

		$this->assertStringContainsString( 'class="pfRating"', $result );
		$this->assertStringContainsString( 'data-curvalue="0"', $result );
		$this->assertStringContainsString( 'data-starwidth="24px"', $result );
		$this->assertStringContainsString( 'data-numstars="5"', $result );
		$this->assertStringContainsString( 'class="autoedit-data"', $result );
	}

	public function testCustomStarWidthNumStarsAndValueAreApplied(): void {
		$result = $this->parse(
			'{{#autoedit_rating:value=3|star width=30|num stars=10|allow half stars=1}}'
		);

		$this->assertStringContainsString( 'data-curvalue="3"', $result );
		$this->assertStringContainsString( 'data-starwidth="30px"', $result );
		$this->assertStringContainsString( 'data-numstars="10"', $result );
		$this->assertStringContainsString( 'data-allows-half="1"', $result );
	}

	public function testMinorAndConfirmFlagsAddHiddenFieldsAndClasses(): void {
		$result = $this->parse( '{{#autoedit_rating:minor=1|confirm=1}}' );

		$this->assertStringContainsString( 'name="wpMinoredit"', $result );
		$this->assertStringContainsString( 'confirm-edit', $result );
	}

	public function testTargetInDisallowedNamespaceRendersErrorInstead(): void {
		// Only NS_MAIN (and NS_CATEGORY, always allowed) are permitted by
		// setUp()'s config override; NS_TALK is not, so this must hit the
		// "invalid namespace" early return rather than build the form.
		$result = $this->parse( '{{#autoedit_rating:target=Talk:PFTestAutoEditRatingTarget}}' );

		$this->assertStringContainsString( 'class="error"', $result );
		$this->assertStringNotContainsString( 'class="autoedit"', $result );
	}

	public function testTargetInAllowedNamespaceBuildsFormNormally(): void {
		$this->insertPage( 'PFTestAutoEditRatingTarget', 'Some content' );

		$result = $this->parse( '{{#autoedit_rating:target=PFTestAutoEditRatingTarget}}' );

		$this->assertStringContainsString( 'class="autoedit"', $result );
		$this->assertStringContainsString( 'name="wpEdittime"', $result );
		$this->assertStringContainsString( 'name="editRevId"', $result );
	}

	public function testArbitraryKeyIsForwardedAsHiddenQueryField(): void {
		$result = $this->parse( '{{#autoedit_rating:Foo=Bar}}' );

		$this->assertStringContainsString( 'name="Foo"', $result );
		$this->assertStringContainsString( 'value="Bar"', $result );
	}
}
