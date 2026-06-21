<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use FauxRequest;
use MediaWiki\Extension\PageForms\StandardInputHtmlBuilder;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use Title;

/**
 * @covers \MediaWiki\Extension\PageForms\StandardInputHtmlBuilder
 * @group Database
 */
class StandardInputHtmlBuilderTest extends MediaWikiIntegrationTestCase {

	private StandardInputHtmlBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 0;
		\OOUI\Theme::setSingleton( new BlankTheme() );
		$this->builder = new StandardInputHtmlBuilder();
	}

	private function getParser(): \Parser {
		return $this->getServiceContainer()->getParserFactory()->create();
	}

	private function build( string $inputName, array $extraComponents = [], array $requestData = [] ): string {
		$tagComponents = [ 'standard input', $inputName, ...$extraComponents ];
		$request = new FauxRequest( $requestData );
		return $this->builder->buildHtml(
			$inputName, $tagComponents,
			false, false,
			$request, $this->getParser(),
			null, null
		);
	}

	// ------------------------------------------------------------------ individual inputs

	public function testSummaryReturnsInput(): void {
		$html = $this->build( 'summary' );
		$this->assertStringContainsString( 'wpSummary', $html );
	}

	public function testMinorEditReturnsCheckbox(): void {
		$html = $this->build( 'minor edit' );
		$this->assertStringContainsString( 'wpMinoredit', $html );
	}

	public function testWatchReturnsCheckbox(): void {
		$html = $this->build( 'watch' );
		$this->assertStringContainsString( 'wpWatchthis', $html );
	}

	public function testSaveReturnsButton(): void {
		$html = $this->build( 'save' );
		$this->assertStringContainsString( 'wpSave', $html );
	}

	public function testPreviewReturnsButton(): void {
		$html = $this->build( 'preview' );
		$this->assertStringContainsString( 'wpPreview', $html );
	}

	public function testChangesReturnsButton(): void {
		$html = $this->build( 'changes' );
		$this->assertStringContainsString( 'wpDiff', $html );
	}

	public function testCancelReturnsLink(): void {
		$html = $this->build( 'cancel' );
		// Cancel renders an anchor or form element; must be non-empty
		$this->assertNotEmpty( $html );
	}

	public function testRunQueryReturnsButton(): void {
		$html = $this->build( 'run query' );
		$this->assertStringContainsString( 'run query', strtolower( $html ) );
	}

	public function testUnknownInputNameReturnsEmpty(): void {
		$html = $this->build( 'nonexistent' );
		$this->assertSame( '', $html );
	}

	// ------------------------------------------------------------------ label component

	public function testCustomClassIsApplied(): void {
		// 'class' component sets $attr['class'] without going through the parser
		$tagComponents = [ 'standard input', 'save', 'class=my-custom-class' ];
		$request = new FauxRequest();
		$html = $this->builder->buildHtml(
			'save', $tagComponents, false, false,
			$request, $this->getParser(), null, null
		);
		$this->assertStringContainsString( 'my-custom-class', $html );
	}

	// ------------------------------------------------------------------ save-and-continue

	public function testSaveAndContinueRenderedWhenTitleMatchesPageName(): void {
		// $pageTitle == $pageName → button is shown (same page, so continue makes sense)
		$tagComponents = [ 'standard input', 'save and continue' ];
		$request = new FauxRequest();
		$html = $this->builder->buildHtml(
			'save and continue', $tagComponents, false, false,
			$request, $this->getParser(), null, null
		);
		$this->assertStringContainsString( 'wpSaveAndContinue', $html );
	}

	public function testSaveAndContinueOmittedWhenTitleDiffersFromPageName(): void {
		// $pageTitle != $pageName → button is omitted
		$tagComponents = [ 'standard input', 'save and continue' ];
		$request = new FauxRequest();
		$title = Title::newFromText( 'SomePage' );
		$html = $this->builder->buildHtml(
			'save and continue', $tagComponents, false, false,
			$request, $this->getParser(), $title, 'DifferentPage'
		);
		$this->assertSame( '', $html );
	}

	// ------------------------------------------------------------------ disabled state

	public function testSaveButtonDisabledAttribute(): void {
		$tagComponents = [ 'standard input', 'save' ];
		$request = new FauxRequest();
		$html = $this->builder->buildHtml(
			'save', $tagComponents, true, false,
			$request, $this->getParser(), null, null
		);
		$this->assertStringContainsString( 'disabled', $html );
	}
}
