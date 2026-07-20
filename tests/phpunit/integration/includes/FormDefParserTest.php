<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\FormDefParser;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDefParser
 * @group Database
 */
class FormDefParserTest extends MediaWikiIntegrationTestCase {

	private FormDefParser $parser;

	protected function setUp(): void {
		parent::setUp();
		$this->parser = new FormDefParser(
			$this->getServiceContainer()->getParserFactory()
		);
	}

	// ------------------------------------------------------------------ preparePreloadData

	public function testReturnsFieldValuesFromExistingPageContent(): void {
		$formDef = "{{{for template|PFTestFDPTpl01}}}\n"
			. "{{{field|Country}}}\n"
			. "{{{field|City}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = '{{PFTestFDPTpl01|Country=DE|City=Berlin}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertArrayHasKey( 'PFTestFDPTpl01', $data );
		$this->assertSame( 'DE', $data['PFTestFDPTpl01']['Country'] );
		$this->assertSame( 'Berlin', $data['PFTestFDPTpl01']['City'] );
	}

	public function testReturnsFreeTextWhenTemplateAbsentFromPage(): void {
		$formDef = "{{{for template|PFTestFDPTpl02}}}\n"
			. "{{{field|Country}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = 'Some free text without any template call.';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertArrayNotHasKey( 'PFTestFDPTpl02', $data );
		$this->assertSame( 'Some free text without any template call.', $data['pf_free_text'] );
	}

	public function testIncludesFreeTextAfterTemplateContent(): void {
		$formDef = "{{{for template|PFTestFDPTpl03}}}\n"
			. "{{{field|Title}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|free text}}}\n"
			. "{{{standard input|save}}}";

		$pageContent = "{{PFTestFDPTpl03|Title=Introduction}}\n\nThis is the free text body.";

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'Introduction', $data['PFTestFDPTpl03']['Title'] );
		$this->assertSame( 'This is the free text body.', $data['pf_free_text'] );
	}

	public function testEmptyPageContentReturnsNoFreeText(): void {
		$formDef = "{{{for template|PFTestFDPTpl04}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		$data = $this->parser->preparePreloadData( $formDef, '' );

		$this->assertArrayNotHasKey( 'pf_free_text', $data );
		$this->assertArrayNotHasKey( 'PFTestFDPTpl04', $data );
	}

	public function testFormDefWithNoTemplateTagsReturnsOnlyFreeText(): void {
		// A form with no {{{for template}}} at all — free text only
		$formDef = "{{{standard input|save}}}";
		$pageContent = 'Standalone free text.';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'Standalone free text.', $data['pf_free_text'] );
	}

	public function testMultipleTemplatesExtractedCorrectly(): void {
		$formDef = "{{{for template|PFTestFDPTplA}}}\n"
			. "{{{field|Alpha}}}\n"
			. "{{{end template}}}\n"
			. "{{{for template|PFTestFDPTplB}}}\n"
			. "{{{field|Beta}}}\n"
			. "{{{end template}}}";

		$pageContent = '{{PFTestFDPTplA|Alpha=first}}{{PFTestFDPTplB|Beta=second}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'first', $data['PFTestFDPTplA']['Alpha'] );
		$this->assertSame( 'second', $data['PFTestFDPTplB']['Beta'] );
	}

	public function testFieldNotInPageTemplateCallIsSkipped(): void {
		// Page calls the template but omits the 'Summary' field — hasValueFromPageForField() returns false.
		$formDef = "{{{for template|PFTestFDPTpl05}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{field|Summary}}}\n"
			. "{{{end template}}}";

		$pageContent = '{{PFTestFDPTpl05|Name=Alice}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'Alice', $data['PFTestFDPTpl05']['Name'] );
		$this->assertArrayNotHasKey( 'Summary', $data['PFTestFDPTpl05'] );
	}

	public function testFreeTextInputInsideTemplateBlockIsSkipped(): void {
		// 'standard input|free text' inside a for-template block becomes 'field|#freetext#'
		// after substitution; the field-handler must skip it (field_name === '#freetext#').
		$formDef = "{{{for template|PFTestFDPTpl06}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{standard input|free text}}}\n"
			. "{{{end template}}}";

		$pageContent = '{{PFTestFDPTpl06|Name=Bob}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'Bob', $data['PFTestFDPTpl06']['Name'] );
		$this->assertArrayNotHasKey( '#freetext#', $data['PFTestFDPTpl06'] ?? [] );
	}

	public function testTemplateNameWithUnderscoresNormalisedToSpaces(): void {
		// Template names using underscores in the form tag must be normalised to spaces
		// so that the array key matches the underscore form used by HtmlFormDataExtractor.
		$formDef = "{{{for template|PFTest_FDP_Tpl07}}}\n"
			. "{{{field|Value}}}\n"
			. "{{{end template}}}";

		$pageContent = '{{PFTest FDP Tpl07|Value=hello}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertArrayHasKey( 'PFTest_FDP_Tpl07', $data );
		$this->assertSame( 'hello', $data['PFTest_FDP_Tpl07']['Value'] );
	}

	// ------------------------------------------------------------------ splitFormDefIntoSections

	/**
	 * This is the single shared implementation used by both FormDefParser::preparePreloadData()
	 * and FormPrinter::formHTML() (see issue #124) — regular {{{for template}}} / {{{end
	 * template}}} boundaries must split into separate sections.
	 */
	public function testSplitFormDefIntoSectionsSplitsOnTemplateBoundaries(): void {
		$formDef = "intro text\n"
			. "{{{for template|Tpl}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "outro text";

		$sections = $this->parser->splitFormDefIntoSections( $formDef );

		$this->assertCount( 3, $sections );
		$this->assertSame( 'intro text', trim( $sections[0] ) );
		$this->assertStringStartsWith( '{{{for template|Tpl}}}', $sections[1] );
		$this->assertStringStartsWith( '{{{end template}}}', $sections[2] );
		$this->assertStringEndsWith( 'outro text', trim( $sections[2] ) );
	}

	/**
	 * Empty {{{ }}} tags must not crash the split. This guards against the drift introduced
	 * when PF_FormPrinter.php's copy of this method (which had this guard) and
	 * FormDefParser.php's copy (which lacked it) went out of sync — see issue #124.
	 */
	public function testSplitFormDefIntoSectionsIgnoresEmptyBracketedTag(): void {
		$formDef = "before {{{}}} {{{for template|Tpl}}}middle{{{end template}}} after";

		$sections = $this->parser->splitFormDefIntoSections( $formDef );

		$this->assertStringContainsString( 'for template|Tpl', implode( '', $sections ) );
		$this->assertStringContainsString( 'end template', implode( '', $sections ) );
	}
}
