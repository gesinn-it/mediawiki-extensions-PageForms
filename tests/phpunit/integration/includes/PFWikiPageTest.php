<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers PFWikiPage
 */
class PFWikiPageTest extends TestCase {

	/**
	 * Builds a stub matching the subset of TemplateInForm's interface that
	 * PFWikiPage::addTemplate() relies on.
	 * @return PHPUnit\Framework\MockObject\MockObject
	 */
	private function makeTemplateInForm(
		string $templateName,
		bool $allowsMultiple = false,
		int $instanceNum = 0,
		?string $embedInTemplate = null,
		?string $embedInField = null
	) {
		$tif = $this->getMockBuilder( stdClass::class )
			->addMethods( [
				'getTemplateName', 'getInstanceNum', 'getEmbedInTemplate', 'getEmbedInField', 'allowsMultiple'
			] )
			->getMock();
		$tif->method( 'getTemplateName' )->willReturn( $templateName );
		$tif->method( 'getInstanceNum' )->willReturn( $instanceNum );
		$tif->method( 'getEmbedInTemplate' )->willReturn( $embedInTemplate );
		$tif->method( 'getEmbedInField' )->willReturn( $embedInField );
		$tif->method( 'allowsMultiple' )->willReturn( $allowsMultiple );
		return $tif;
	}

	// -------------------------------------------------------------------------
	// addTemplate() / createPageText()
	// -------------------------------------------------------------------------

	public function testAddTemplateShowsUpAsTemplateCallInPageText() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate' ) );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( '{{MyTemplate}}', $pageText );
	}

	public function testAddTemplateParamSetsValueOnSingleInstanceTemplate() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate' ) );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, 'field1', 'value1' );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( '|field1=value1', $pageText );
	}

	public function testAddTemplateParamSetsValueOnCorrectInstanceWhenMultipleAllowed() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate', true, 0 ) );
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate', true, 1 ) );

		$wikiPage->addTemplateParam( 'MyTemplate', 0, 'field1', 'first' );
		$wikiPage->addTemplateParam( 'MyTemplate', 1, 'field1', 'second' );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$firstPos = strpos( $pageText, 'field1=first' );
		$secondPos = strpos( $pageText, 'field1=second' );
		$this->assertNotFalse( $firstPos );
		$this->assertNotFalse( $secondPos );
		$this->assertLessThan( $secondPos, $firstPos );
	}

	// -------------------------------------------------------------------------
	// getEmbeddedTemplateForParam()
	// -------------------------------------------------------------------------

	public function testGetEmbeddedTemplateForParamReturnsNullWhenNotFound() {
		$wikiPage = new PFWikiPage();
		$this->assertNull( $wikiPage->getEmbeddedTemplateForParam( 'Outer', 'field1' ) );
	}

	public function testGetEmbeddedTemplateForParamReturnsRegisteredEmbeddedTemplateName() {
		$wikiPage = new PFWikiPage();
		// getInstanceNum() must be 0 for the embed definition to be registered.
		$wikiPage->addTemplate(
			$this->makeTemplateInForm( 'Inner', false, 0, 'Outer', 'field1' )
		);

		$this->assertSame( 'Inner', $wikiPage->getEmbeddedTemplateForParam( 'Outer', 'field1' ) );
	}

	public function testGetEmbeddedTemplateForParamNotRegisteredWhenInstanceNumIsNotZero() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate(
			$this->makeTemplateInForm( 'Inner', true, 1, 'Outer', 'field1' )
		);

		$this->assertNull( $wikiPage->getEmbeddedTemplateForParam( 'Outer', 'field1' ) );
	}

	// -------------------------------------------------------------------------
	// addSection() / createPageText()
	// -------------------------------------------------------------------------

	public function testAddSectionRendersHeaderAtGivenLevel() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addSection( 'Background', 2, 'Some content', [ 'hideIfEmpty' => false ] );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( "==Background==\n", $pageText );
		$this->assertStringContainsString( "Some content\n", $pageText );
	}

	public function testAddSectionRendersHeaderLevelAsRepeatedEqualsSigns() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addSection( 'Details', 4, 'text', [ 'hideIfEmpty' => false ] );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( "====Details====\n", $pageText );
	}

	public function testAddSectionWithHideIfEmptyAndEmptyTextIsOmitted() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addSection( 'Background', 2, '', [ 'hideIfEmpty' => true ] );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringNotContainsString( 'Background', $pageText );
	}

	public function testAddSectionWithEmptyTextButHideIfEmptyFalseStillRendersHeader() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addSection( 'Background', 2, '', [ 'hideIfEmpty' => false ] );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( "==Background==\n", $pageText );
	}

	// -------------------------------------------------------------------------
	// addFreeTextSection() / setFreeText() / makeFreeTextOnlyInclude()
	// -------------------------------------------------------------------------

	public function testSetFreeTextIsIncludedInPageText() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addFreeTextSection();
		$wikiPage->setFreeText( 'Some free text body.' );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( 'Some free text body.', $pageText );
	}

	public function testFreeTextOnlyIncludeIsFalseByDefault() {
		$wikiPage = new PFWikiPage();
		$this->assertFalse( $wikiPage->freeTextOnlyInclude() );
	}

	public function testMakeFreeTextOnlyIncludeSetsFlagToTrue() {
		$wikiPage = new PFWikiPage();
		$wikiPage->makeFreeTextOnlyInclude();
		$this->assertTrue( $wikiPage->freeTextOnlyInclude() );
	}

	public function testMakeFreeTextOnlyIncludeWrapsFreeTextInOnlyIncludeTags() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addFreeTextSection();
		$wikiPage->setFreeText( 'Some free text body.' );
		$wikiPage->makeFreeTextOnlyInclude();

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( '<onlyinclude>Some free text body.</onlyinclude>', $pageText );
	}

	public function testFreeTextIsNotWrappedWhenOnlyIncludeNotSet() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addFreeTextSection();
		$wikiPage->setFreeText( 'Some free text body.' );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringNotContainsString( '<onlyinclude>', $pageText );
	}

	// -------------------------------------------------------------------------
	// createTemplateCallsForTemplateName()
	// -------------------------------------------------------------------------

	public function testCreateTemplateCallsForTemplateNameFiltersByName() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'WantedTemplate' ) );
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'OtherTemplate' ) );

		$text = $wikiPage->createTemplateCallsForTemplateName( 'WantedTemplate', new FauxRequest() );

		$this->assertStringContainsString( '{{WantedTemplate}}', $text );
		$this->assertStringNotContainsString( 'OtherTemplate', $text );
	}

	public function testCreateTemplateCallsForTemplateNameReturnsEmptyStringWhenNoMatch() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'SomeTemplate' ) );

		$text = $wikiPage->createTemplateCallsForTemplateName( 'NonExistentTemplate', new FauxRequest() );

		$this->assertSame( '', $text );
	}

	public function testCreateTemplateCallsForTemplateNameThreadsRequestIntoUnhandledParams() {
		$wikiPage = new PFWikiPage();
		// allowsMultiple = true => addUnhandledParams is a no-op ($mAddUnhandledParams = !allowsMultiple).
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate', false ) );

		$request = new FauxRequest( [ '_unhandled_MyTemplate_extra' => 'fromRequest' ] );
		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', $request );

		$this->assertStringContainsString( '|extra=fromRequest', $text );
	}

	// -------------------------------------------------------------------------
	// createTemplateCall() — param rendering
	// -------------------------------------------------------------------------

	public function testCreateTemplateCallRendersNonNumericParamWithNameAndValue() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate' ) );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, 'field1', 'value1' );

		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', new FauxRequest() );

		$this->assertStringContainsString( "{{MyTemplate\n|field1=value1", $text );
	}

	public function testCreateTemplateCallSkipsParamWithEmptyStringValue() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate' ) );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, 'field1', '' );

		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', new FauxRequest() );

		$this->assertStringNotContainsString( 'field1', $text );
		$this->assertStringContainsString( '{{MyTemplate}}', $text );
	}

	public function testCreateTemplateCallSkipsParamWithNullValue() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate' ) );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, 'field1', null );

		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', new FauxRequest() );

		$this->assertStringNotContainsString( 'field1', $text );
	}

	public function testCreateTemplateCallKeepsZeroValueParam() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate' ) );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, 'field1', '0' );

		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', new FauxRequest() );

		$this->assertStringContainsString( '|field1=0', $text );
	}

	public function testCreateTemplateCallRendersNumericParamsPositionallyWithPipes() {
		$wikiPage = new PFWikiPage();
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate' ) );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, '1', 'first' );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, '3', 'third' );

		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', new FauxRequest() );

		// Param "2" was never submitted, but a pipe placeholder must still be
		// emitted so "third" lands in position 3, not 2.
		$this->assertStringContainsString( '{{MyTemplate|first||third}}', $text );
	}

	public function testCreateTemplateCallIncludesUnhandledParamsFromRequest() {
		$wikiPage = new PFWikiPage();
		// allowsMultiple = false => $mAddUnhandledParams = true.
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate', false ) );
		$wikiPage->addTemplateParam( 'MyTemplate', 0, 'field1', 'value1' );

		$request = new FauxRequest( [ '_unhandled_MyTemplate_field2' => 'value2' ] );
		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', $request );

		$this->assertStringContainsString( '|field1=value1', $text );
		$this->assertStringContainsString( '|field2=value2', $text );
	}

	public function testCreateTemplateCallDoesNotIncludeUnhandledParamsWhenAllowsMultipleIsTrue() {
		$wikiPage = new PFWikiPage();
		// allowsMultiple = true => $mAddUnhandledParams = false.
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'MyTemplate', true ) );

		$request = new FauxRequest( [ '_unhandled_MyTemplate_field1' => 'value1' ] );
		$text = $wikiPage->createTemplateCallsForTemplateName( 'MyTemplate', $request );

		$this->assertStringNotContainsString( 'field1', $text );
	}

	// -------------------------------------------------------------------------
	// Embedded templates
	// -------------------------------------------------------------------------

	public function testEmbeddedTemplateCallIsNestedInsideOuterParamInsteadOfOuterValue() {
		$wikiPage = new PFWikiPage();
		// Outer template with a param "field1" that embeds "Inner".
		$wikiPage->addTemplate( $this->makeTemplateInForm( 'Outer' ) );
		// Inner template, embedded into Outer's "field1" param.
		$wikiPage->addTemplate(
			$this->makeTemplateInForm( 'Inner', false, 0, 'Outer', 'field1' )
		);
		$wikiPage->addTemplateParam( 'Inner', 0, 'innerField', 'innerValue' );
		// The outer param's own value must be ignored in favor of the embedded call.
		$wikiPage->addTemplateParam( 'Outer', 0, 'field1', 'ignoredOwnValue' );

		$pageText = $wikiPage->createPageText( new FauxRequest() );

		$this->assertStringContainsString( '{{Inner', $pageText );
		$this->assertStringContainsString( 'innerField=innerValue', $pageText );
		$this->assertStringNotContainsString( 'ignoredOwnValue', $pageText );
		// The embedded (Inner) template must not also be rendered standalone.
		$this->assertSame( 1, substr_count( $pageText, '{{Inner' ) );
	}
}
