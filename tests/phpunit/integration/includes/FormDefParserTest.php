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
}
