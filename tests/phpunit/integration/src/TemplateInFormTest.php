<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec;
use MediaWiki\Extension\PageForms\TemplateInForm;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOptions;
use PFUtils;
use Title;

/**
 * The parts of TemplateInForm that read a template page, use a parser or a title. The rest of the class
 * is in the unit test of it, which needs no database.
 *
 * @covers \MediaWiki\Extension\PageForms\TemplateInForm
 * @group Database
 *
 * @author gesinn-it-ilm
 */
class TemplateInFormTest extends MediaWikiIntegrationTestCase {

	public function testCreateWithoutFormFields() {
		// TemplateInForm::create() with no $formFields argument loads the fields
		// from the real Template: page via Template::newFromName(). Create an
		// actual template page with two non-semantic placeholder fields.
		$this->insertPage(
			Title::newFromText( 'PFTestTemplateInFormCreate', NS_TEMPLATE ),
			'{{{field1}}} {{{field2}}}'
		);

		$templateInForm = TemplateInForm::create( 'PFTestTemplateInFormCreate' );

		$this->assertInstanceOf( TemplateInForm::class, $templateInForm );
		$this->assertEquals( 'PFTestTemplateInFormCreate', $templateInForm->getTemplateName() );
		$this->assertCount( 2, $templateInForm->getFields() );
		$this->assertSame( 'field1', $templateInForm->getFields()[0]->getTemplateField()->getFieldName() );
		$this->assertSame( 'field2', $templateInForm->getFields()[1]->getTemplateField()->getFieldName() );
	}

	private function createMockParser(): Parser {
		$mockParser = $this->createMock( Parser::class );
		$mockParser->method( 'recursiveTagParse' )
			->willReturnCallback( static fn ( $input ) => $input );
		return $mockParser;
	}

	public function testNewFromFormTagDefaultParser(): void {
		// $parser is a required argument (see issue #189: an untitled parser
		// silently resolved wikitext like {{PAGENAME}} against a "Badtitle"
		// placeholder). Callers must supply an already-titled Parser; using the
		// shared singleton here exercises the same MW 1.43 compat requirement
		// as FormField::newFromFormFieldTag() (ParserOptions before clearState()
		// can call resetOutput()).
		$parser = PFUtils::getParser();
		if ( !$parser->getOptions() ) {
			$parser->setOptions( ParserOptions::newFromAnon() );
		}
		$parser->clearState();
		$parser->setOutputType( Parser::OT_HTML );

		$template = TemplateInForm::newFromFormTag(
			new TemplateSpec( [ 'for template', 'PFTestTemplateInFormDefaultParser01' ] ),
			$parser
		);

		$this->assertInstanceOf( TemplateInForm::class, $template );
		$this->assertSame( 'PFTestTemplateInFormDefaultParser01', $template->getTemplateName() );
	}

	public function testNewFromFormTagBasicOptions(): void {
		$template = TemplateInForm::newFromFormTag(
			new TemplateSpec( [
				'for template',
				'PFTestTemplateInFormBasic01',
				'multiple',
				'strict',
				'label=My Label',
				'intro=My Intro',
				'minimum instances=2',
				'maximum instances=5',
				'add button text=Add Another',
				'display=custom',
				'height=300px',
				'displayed fields when minimized=field1, field2',
				'event title field=titleField',
				'event date field=dateField',
				'event start date field=startDateField',
				'event end date field=endDateField',
			] ),
			$this->createMockParser()
		);

		$this->assertTrue( $template->allowsMultiple() );
		$this->assertTrue( $template->strictParsing() );
		$this->assertSame( 'My Label', $template->getLabel() );
		$this->assertSame( 'My Intro', $template->getIntro() );
		$this->assertSame( '2', $template->getMinInstancesAllowed() );
		$this->assertSame( '5', $template->getMaxInstancesAllowed() );
		$this->assertSame( 'Add Another', $template->getAddButtonText() );
		$this->assertSame( 'custom', $template->getDisplay() );
		$this->assertSame( '300px', $template->getHeight() );
		$this->assertSame( 'field1, field2', $template->getDisplayedFieldsWhenMinimized() );
		$this->assertSame( 'titleField', $template->getEventTitleField() );
		$this->assertSame( 'dateField', $template->getEventDateField() );
		$this->assertSame( 'startDateField', $template->getEventStartDateField() );
		$this->assertSame( 'endDateField', $template->getEventEndDateField() );
	}

	public function testNewFromFormTagDefaults(): void {
		$template = TemplateInForm::newFromFormTag(
			new TemplateSpec( [ 'for template', 'PFTestTemplateInFormDefaults_01' ] ),
			$this->createMockParser()
		);

		$this->assertSame( 'PFTestTemplateInFormDefaults 01', $template->getTemplateName() );
		$this->assertNull( $template->allowsMultiple() );
		$this->assertNull( $template->strictParsing() );
		$this->assertNull( $template->getEmbedInTemplate() );
		$this->assertSame(
			wfMessage( 'pf_formedit_addanother' )->text(),
			$template->getAddButtonText()
		);
	}

	public function testNewFromFormTagEmbedInField(): void {
		$template = TemplateInForm::newFromFormTag(
			new TemplateSpec( [
				'for template',
				'PFTestTemplateInFormEmbed01',
				'embed in field=PFTestTemplateInFormEmbedParent01[fieldName]',
			] ),
			$this->createMockParser()
		);

		$this->assertSame( 'PFTestTemplateInFormEmbedParent01', $template->getEmbedInTemplate() );
		$this->assertSame( 'fieldName', $template->getEmbedInField() );
		$this->assertNotNull( $template->getPlaceholder() );
	}

	public function testNewFromFormTagUsesGlobalEmbeddedTemplates(): void {
		global $wgPageFormsEmbeddedTemplates;
		$previous = $wgPageFormsEmbeddedTemplates;
		$wgPageFormsEmbeddedTemplates['PFTestTemplateInFormGlobalEmbed01'] =
			[ 'PFTestTemplateInFormGlobalEmbedParent01', 'globalFieldName' ];

		try {
			$template = TemplateInForm::newFromFormTag(
				new TemplateSpec( [ 'for template', 'PFTestTemplateInFormGlobalEmbed01' ] ),
				$this->createMockParser()
			);

			$this->assertSame( 'PFTestTemplateInFormGlobalEmbedParent01', $template->getEmbedInTemplate() );
			$this->assertSame( 'globalFieldName', $template->getEmbedInField() );
			$this->assertNotNull( $template->getPlaceholder() );
		} finally {
			$wgPageFormsEmbeddedTemplates = $previous;
		}
	}

	public function testCheckIfAllInstancesPrintedBelowMinimumAllowed(): void {
		$template = TemplateInForm::newFromFormTag(
			new TemplateSpec(
				[ 'for template', 'PFTestTemplateInFormAllPrintedB01', 'multiple', 'minimum instances=3' ]
			),
			$this->createMockParser()
		);

		$template->checkIfAllInstancesPrinted( false, false );

		$this->assertFalse( $template->allInstancesPrinted() );
	}
}
