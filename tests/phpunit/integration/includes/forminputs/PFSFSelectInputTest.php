<?php

declare( strict_types=1 );

/**
 * @covers \PFSFSelectInput
 * @group Database
 */
class PFSFSelectInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsSFSelectConfig;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgPageFormsSFSelectConfig = null;
	}

	/**
	 * Helper: render SF_Select in dynamic mode (no function/query).
	 *
	 * Passes 'sametemplate' and 'field' to satisfy setValueTemplate / setValueField
	 * without triggering undefined-key warnings under PHP 8.
	 * Input name contains '[' so setSelectTemplate() can extract the template part.
	 */
	private function getHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		return PFSFSelectInput::getHTML(
			$curValue,
			'TestTpl[TestField]',
			$isMandatory,
			$isDisabled,
			array_merge( [ 'sametemplate' => true, 'field' => 'TestField' ], $extraArgs )
		);
	}

	public function testRendersInputSpanWithSelectSfsClass(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'select-sfs', $html );
		$this->assertStringContainsString( 'inputSpan', $html );
	}

	public function testRendersSelectElement(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( '<select ', $html );
		$this->assertStringContainsString( "name='TestTpl[TestField]'", $html );
	}

	public function testRendersBlankOptionFirst(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( '<option></option>', $html );
	}

	public function testEmptyValueRendersNoExtraOptions(): void {
		$html = $this->getHtml( '' );

		// Only the blank option — no extra <option> elements after it
		$this->assertSame( 1, substr_count( $html, '<option' ) );
	}

	public function testCurValueRendersAsSelectedOption(): void {
		$html = $this->getHtml( 'PFSFSOptA' );

		$this->assertStringContainsString( "<option selected='selected'>PFSFSOptA</option>", $html );
	}

	public function testMultipleCurValuesAreRenderedAsSelectedOptions(): void {
		$html = $this->getHtml( 'PFSFSOptA,PFSFSOptB' );

		$this->assertStringContainsString( "<option selected='selected'>PFSFSOptA</option>", $html );
		$this->assertStringContainsString( "<option selected='selected'>PFSFSOptB</option>", $html );
	}

	public function testMandatoryAddsMandatoryFieldClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryField', $html );
	}

	public function testMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	public function testNonMandatoryDoesNotAddMandatoryClasses(): void {
		$html = $this->getHtml( '', false );

		$this->assertStringNotContainsString( 'mandatoryField', $html );
	}

	public function testSingleSelectAddsSelectSfsSingleClass(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'select-sfs-single', $html );
	}

	public function testIsListAddsMultipleAttribute(): void {
		$html = $this->getHtml( '', false, false, [ 'is_list' => true ] );

		$this->assertStringContainsString( 'multiple="multiple"', $html );
	}

	public function testIsListUsesArrayInputName(): void {
		$html = $this->getHtml( '', false, false, [ 'is_list' => true ] );

		$this->assertStringContainsString( "name='TestTpl[TestField][]'", $html );
	}

	public function testIsListAppendsHiddenIsListInput(): void {
		$html = $this->getHtml( '', false, false, [ 'is_list' => true ] );

		$this->assertStringContainsString( "name='TestTpl[TestField][is_list]'", $html );
		$this->assertStringContainsString( "value='1'", $html );
	}

	public function testIsListOmitsSelectSfsSingleClass(): void {
		$html = $this->getHtml( '', false, false, [ 'is_list' => true ] );

		$this->assertStringNotContainsString( 'select-sfs-single', $html );
	}

	public function testSizeAttributeIsRendered(): void {
		$html = $this->getHtml( '', false, false, [ 'size' => 5 ] );

		$this->assertStringContainsString( 'size="5"', $html );
	}

	/**
	 * A `function=` field arg without '@@@@' is resolved immediately via
	 * PFSFSelectField::setFunction()'s $this->mParser->replaceVariables() call.
	 * Uses PageForms' own '#arraymap' parser function (registered via
	 * ParserFirstCallInit, no SMW/ParserFunctions dependency needed) with an
	 * identity formula, so the two options are just the two input values.
	 *
	 * @covers \PFSFSelectField::setFunction
	 */
	public function testFunctionArgWithoutPlaceholderIsResolvedImmediately(): void {
		$funcArg = implode( ';', [ 'arraymap:PFTestSFSValueA.PFTestSFSValueB', '.', 'x', 'x' ] );
		$html = $this->getHtml( '', false, false, [ 'function' => $funcArg ] );

		$this->assertStringContainsString( '<option>PFTestSFSValueA</option>', $html );
		$this->assertStringContainsString( '<option>PFTestSFSValueB</option>', $html );
	}

	/**
	 * PFAutoeditAPI::prepareAction() can leave the global Parser singleton in
	 * Parser::OT_WIKI mode (via startExternalParse()) earlier in the same
	 * request - e.g. an autoedit-driven preload/submit that runs before this
	 * SF_Select field is rendered on the resulting edit form. In OT_WIKI mode,
	 * braceSubstitution() deliberately leaves "{{#arraymap:...}}" unexpanded,
	 * so PFSFSelectInput::getHTML() must reset the singleton via
	 * PFUtils::ensureParserReadyForTagParse() before constructing
	 * PFSFSelectField, or the raw, unexpanded function call leaks into the
	 * rendered <option> text instead of the resolved values.
	 *
	 * @covers \PFSFSelectInput::getHTML
	 */
	public function testFunctionArgResolvesAfterParserLeftInOtWikiMode(): void {
		$parser = \MediaWiki\MediaWikiServices::getInstance()->getParser();
		$parser->startExternalParse( null, \ParserOptions::newFromAnon(), \Parser::OT_WIKI );

		$funcArg = implode( ';', [ 'arraymap:PFTestSFSValueA.PFTestSFSValueB', '.', 'x', 'x' ] );
		$html = $this->getHtml( '', false, false, [ 'function' => $funcArg ] );

		$this->assertStringNotContainsString( '{{#arraymap', $html );
		$this->assertStringContainsString( '<option>PFTestSFSValueA</option>', $html );
		$this->assertStringContainsString( '<option>PFTestSFSValueB</option>', $html );
	}
}
