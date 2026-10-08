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
		// so that the array key matches the underscore form used by NestedInputValues.
		$formDef = "{{{for template|PFTest_FDP_Tpl07}}}\n"
			. "{{{field|Value}}}\n"
			. "{{{end template}}}";

		$pageContent = '{{PFTest FDP Tpl07|Value=hello}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertArrayHasKey( 'PFTest_FDP_Tpl07', $data );
		$this->assertSame( 'hello', $data['PFTest_FDP_Tpl07']['Value'] );
	}

	// ---------------------------------------- preparePreloadData: multiple-instance templates

	public function testMultipleInstanceTemplateReturnsEveryInstanceFromPage(): void {
		$formDef = "{{{for template|PFTestFDPMulti01|multiple}}}\n"
			. "{{{field|Step}}}\n"
			. "{{{field|Note}}}\n"
			. "{{{end template}}}";

		$pageContent = "{{PFTestFDPMulti01|Step=one|Note=first}}\n{{PFTestFDPMulti01|Step=two|Note=second}}";

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		// Instances are keyed like NestedInputValues does ("0a", "1a", ...).
		$this->assertSame(
			[
				'0a' => [ 'Step' => 'one', 'Note' => 'first' ],
				'1a' => [ 'Step' => 'two', 'Note' => 'second' ],
			],
			$data['PFTestFDPMulti01']
		);
	}

	public function testMultipleInstanceTemplateWithSingleInstanceIsKeyedAsInstance(): void {
		$formDef = "{{{for template|PFTestFDPMulti02|multiple}}}\n"
			. "{{{field|Step}}}\n"
			. "{{{end template}}}";

		$data = $this->parser->preparePreloadData( $formDef, '{{PFTestFDPMulti02|Step=only}}' );

		$this->assertSame( [ '0a' => [ 'Step' => 'only' ] ], $data['PFTestFDPMulti02'] );
	}

	public function testMultipleInstanceTemplateKeepsEmptyInstanceToPreserveCount(): void {
		$formDef = "{{{for template|PFTestFDPMulti03|multiple}}}\n"
			. "{{{field|Step}}}\n"
			. "{{{end template}}}";

		$pageContent = "{{PFTestFDPMulti03}}\n{{PFTestFDPMulti03|Step=second}}";

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame(
			[ '0a' => [], '1a' => [ 'Step' => 'second' ] ],
			$data['PFTestFDPMulti03']
		);
	}

	public function testMultipleInstanceTemplateFreeTextExcludesAllInstances(): void {
		$formDef = "{{{for template|PFTestFDPMulti04|multiple}}}\n"
			. "{{{field|Step}}}\n"
			. "{{{end template}}}\n"
			. "{{{standard input|free text}}}";

		$pageContent = "{{PFTestFDPMulti04|Step=a}}{{PFTestFDPMulti04|Step=b}}\n\nBody text.";

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'Body text.', $data['pf_free_text'] );
	}

	public function testMultipleInstanceTemplateDoesNotAffectSurroundingSingleTemplates(): void {
		$formDef = "{{{for template|PFTestFDPSingle05}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "{{{for template|PFTestFDPMulti05|multiple}}}\n"
			. "{{{field|Step}}}\n"
			. "{{{end template}}}";

		$pageContent = '{{PFTestFDPSingle05|Name=Alice}}{{PFTestFDPMulti05|Step=a}}{{PFTestFDPMulti05|Step=b}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( [ 'Name' => 'Alice' ], $data['PFTestFDPSingle05'] );
		$this->assertCount( 2, $data['PFTestFDPMulti05'] );
	}

	public function testSingleInstanceTemplateStillReadsOnlyFirstInstance(): void {
		// Unchanged behaviour: without the `multiple` attribute only the first call is read.
		$formDef = "{{{for template|PFTestFDPSingle06}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		$data = $this->parser->preparePreloadData(
			$formDef, '{{PFTestFDPSingle06|Name=first}}{{PFTestFDPSingle06|Name=second}}'
		);

		$this->assertSame( [ 'Name' => 'first' ], $data['PFTestFDPSingle06'] );
	}

	// ---------------------------------------- preparePreloadData: parameters the form does not define

	public function testParameterNotDefinedInFormIsReturnedAsUnhandled(): void {
		$formDef = "{{{for template|PFTestFDPUnh01}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		$pageContent = "{{PFTestFDPUnh01\n|Name=Alice\n|Legacy=keep\n}}";

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		// Same key format as FormMarkup::unhandledFieldsHTML(), read back by
		// PFWikiPageTemplate::addUnhandledParams().
		$this->assertSame( 'keep', $data['_unhandled_PFTestFDPUnh01_Legacy'] );
		$this->assertSame( [ 'Name' => 'Alice' ], $data['PFTestFDPUnh01'] );
	}

	public function testUnhandledParameterNameIsUrlEncodedAndTemplateNameUsesUnderscores(): void {
		$formDef = "{{{for template|PFTestFDP Unh 02}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		$pageContent = '{{PFTestFDP Unh 02|Name=Alice|my param=x y}}';

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( 'x y', $data['_unhandled_PFTestFDP_Unh_02_my+param'] );
	}

	public function testPositionalParametersAreNotReturnedAsUnhandled(): void {
		$formDef = "{{{for template|PFTestFDPUnh03}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		$data = $this->parser->preparePreloadData( $formDef, '{{PFTestFDPUnh03|Name=Alice|positional}}' );

		$this->assertSame(
			[ 'PFTestFDPUnh03' ],
			array_keys( $data ),
			'only the template values, no _unhandled_ key'
		);
	}

	public function testNoUnhandledKeyWhenAllParametersAreHandledOrTemplateIsAbsent(): void {
		$formDef = "{{{for template|PFTestFDPUnh04}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		$all = $this->parser->preparePreloadData( $formDef, '{{PFTestFDPUnh04|Name=Alice}}' );
		$absent = $this->parser->preparePreloadData( $formDef, 'plain text' );

		$this->assertSame( [ 'PFTestFDPUnh04' ], array_keys( $all ) );
		$this->assertSame( [ 'pf_free_text' ], array_keys( $absent ) );
	}

	public function testMultipleInstanceTemplateKeepsUnhandledParametersWithTheirInstance(): void {
		$formDef = "{{{for template|PFTestFDPUnh05|multiple}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}";

		$pageContent = "{{PFTestFDPUnh05|Name=a}}\n{{PFTestFDPUnh05|Name=b|Legacy=second|Other=o}}"
			. "\n{{PFTestFDPUnh05|Name=c|Legacy=third}}";

		$data = $this->parser->preparePreloadData( $formDef, $pageContent );

		$this->assertSame( [ 'Name' => 'a' ], $data['PFTestFDPUnh05']['0a'] );
		$this->assertSame(
			[ 'Name' => 'b', '_unhandled' => [ 'Legacy' => 'second', 'Other' => 'o' ] ],
			$data['PFTestFDPUnh05']['1a']
		);
		$this->assertSame(
			[ 'Name' => 'c', '_unhandled' => [ 'Legacy' => 'third' ] ],
			$data['PFTestFDPUnh05']['2a']
		);
		$this->assertArrayNotHasKey( '_unhandled_PFTestFDPUnh05_Legacy', $data );
	}

	// ------------------------------------------------------------------ splitIntoSections

	private function sectionTexts( string $formDef ): array {
		$reader = new \MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader();
		$definition = $reader->read( $formDef );
		return array_map(
			static fn ( array $els ) => implode( '', array_map( static fn ( $e ) => $e->toWikitext(), $els ) ),
			$this->parser->splitIntoSections( $definition )
		);
	}

	/**
	 * Regular {{{for template}}} / {{{end template}}} boundaries must split into separate sections.
	 */
	public function testSplitIntoSectionsSplitsOnTemplateBoundaries(): void {
		$formDef = "intro text\n"
			. "{{{for template|Tpl}}}\n"
			. "{{{field|Name}}}\n"
			. "{{{end template}}}\n"
			. "outro text";

		$sections = $this->sectionTexts( $formDef );

		$this->assertCount( 3, $sections );
		$this->assertSame( "intro text\n", $sections[0] );
		$this->assertStringStartsWith( '{{{for template|Tpl}}}', $sections[1] );
		$this->assertStringStartsWith( '{{{end template}}}', $sections[2] );
		$this->assertStringEndsWith( 'outro text', $sections[2] );
	}

	public function testSplitIntoSectionsTrimsOnlyTheLastSection(): void {
		$sections = $this->sectionTexts( "  a {{{for template|T}}} b {{{end template}}} c  \n" );

		$this->assertSame( [ '  a ', '{{{for template|T}}} b ', '{{{end template}}} c' ], $sections );
	}

	public function testSplitIntoSectionsKeepsTheFormDefinitionIntact(): void {
		$formDef = "before {{{}}} {{{for template|Tpl}}}middle{{{end template}}} after";

		$this->assertSame( $formDef, implode( '', $this->sectionTexts( $formDef ) ) );
	}
}
