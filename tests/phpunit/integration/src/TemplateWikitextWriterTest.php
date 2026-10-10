<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWiki\Extension\PageForms\TemplateText\TemplateWikitextWriter;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * The parts of TemplateWikitextWriter that depend on what is installed (Semantic MediaWiki,
 * Semantic Internal Objects), which Template::createText() can only reach in the installation
 * the tests run in; and the small steps of the writer on their own.
 *
 * The exact text for every format is pinned by TemplateTextCharacterizationTest.
 *
 * @group PF
 * @group Database
 * @covers \MediaWiki\Extension\PageForms\TemplateText\TemplateWikitextWriter
 * @covers \MediaWiki\Extension\PageForms\TemplateText\InternalObjectCall
 * @covers \MediaWiki\Extension\PageForms\TemplateText\SetCall
 */
class TemplateWikitextWriterTest extends MediaWikiIntegrationTestCase {

	private function writer( bool $hasSmw = true, bool $hasSio = false ): TemplateWikitextWriter {
		return new TemplateWikitextWriter(
			MediaWikiServices::getInstance()->getHookContainer(),
			$hasSmw,
			$hasSio
		);
	}

	private function template( array $fields = [] ): Template {
		return new Template( 'Book', $fields );
	}

	public function testWithoutSmwTheFieldsAreOnlyDescribedAndDisplayedByTheFormat() {
		$template = $this->template( [ TemplateField::create( 'Title', 'Title' ) ] );
		$template->setFormat( 'plain' );
		$template->setCategoryName( 'Books' );

		$this->assertSame(
			"<noinclude>\n{{#template_params:Title}}\n</noinclude><includeonly>\n" .
				"{{#template_display:_format=plain}}\n[[Category:Books]]\n</includeonly>",
			$this->writer( false )->write( $template )
		);
	}

	public function testWithoutSmwAndWithoutAFormatTheDisplayHasNoFormat() {
		$text = $this->writer( false )->write( $this->template( [ TemplateField::create( 'Title', 'Title' ) ] ) );

		$this->assertStringEndsWith( "{{#template_display:}}</includeonly>", $text );
	}

	public function testWithoutSmwTheFullWikiTextIsWrittenLikeWithSmw() {
		$template = $this->template( [ TemplateField::create( 'Title', 'Title' ) ] );
		$template->setFullWikiTextStatus( true );

		$this->assertSame(
			$this->writer( true )->write( $template ),
			$this->writer( false )->write( $template )
		);
		$this->assertStringContainsString( '|Title=', $this->writer( false )->write( $template ) );
	}

	public function testTheConnectingPropertyIsSetBySubobjectWithoutSio() {
		$template = $this->template( [
			TemplateField::create( 'Name', 'Name' ),
			TemplateField::create( 'Tags', 'Tags', 'Has tag', true, ';' ),
			TemplateField::create( 'Born', 'Born', 'Has born' ),
		] );
		$template->setConnectingProperty( 'Has book' );

		$text = $this->writer( true, false )->write( $template );

		$this->assertStringContainsString(
			'<includeonly>{{#subobject:-|Has book={{PAGENAME}}' .
				'|Has tag={{{Tags|}}}|+sep=,|Has born={{{Born|}}}}}',
			$text
		);
		$this->assertStringNotContainsString( '#set_internal', $text );
	}

	public function testTheConnectingPropertyIsSetBySetInternalWithSio() {
		$template = $this->template( [
			TemplateField::create( 'Name', 'Name' ),
			TemplateField::create( 'Tags', 'Tags', 'Has tag', true, ';' ),
			TemplateField::create( 'Born', 'Born', 'Has born' ),
		] );
		$template->setConnectingProperty( 'Has book' );

		$text = $this->writer( true, true )->write( $template );

		$this->assertStringContainsString(
			'<includeonly>{{#set_internal:Has book|Has tag#list={{{Tags|}}}|Has born={{{Born|}}}}}',
			$text
		);
		$this->assertStringNotContainsString( '#subobject', $text );
	}

	public function testHiddenFieldsWithAPropertyAreStoredByOneSetCall() {
		$template = $this->template( [
			TemplateField::create( 'Secret', 'Secret', 'Has secret', false, null, 'hidden' ),
			TemplateField::create( 'Codes', 'Codes', 'Has code', true, null, 'hidden' ),
		] );

		$this->assertStringContainsString(
			"<includeonly>{{#set:Has secret={{{Secret|}}}|Has code#list={{{Codes|}}}|}}\n",
			$this->writer()->write( $template )
		);
	}

	public function testAFieldInAnotherNamespaceIsStoredWithTheNameOfTheNamespace() {
		$photo = TemplateField::create( 'Photo', 'Photo', 'Has photo' );
		$photo->setFieldType( 'File' );
		$hidden = TemplateField::create( 'Logo', 'Logo', 'Has logo', false, null, 'hidden' );
		$hidden->setFieldType( 'File' );
		$template = $this->template( [ $photo, $hidden ] );
		$template->setConnectingProperty( 'Has book' );

		$text = $this->writer()->write( $template );

		$this->assertStringContainsString( '|Has photo=File:{{{Photo|}}}|', $text . '|' );
		$this->assertStringNotContainsString( '6:{{{', $text );

		$template = $this->template( [ $hidden ] );
		$this->assertStringContainsString( '{{#set:Has logo=File:{{{Logo|}}}|}}', $this->writer()->write( $template ) );
	}

	public function testNoSetCallWithoutAHiddenField() {
		$this->assertStringNotContainsString(
			'#set:',
			$this->writer()->write( $this->template( [ TemplateField::create( 'Title', 'Title' ) ] ) )
		);
	}

	public function testCategoryTag() {
		$this->assertSame( '', $this->writer()->categoryTag( null ) );
		$this->assertSame( '', $this->writer()->categoryTag( '' ) );
		$this->assertSame( "\n[[Category:Books]]\n", $this->writer()->categoryTag( 'Books' ) );
	}

	public function testFieldTextWithoutHooksIsTheTextOfTheField() {
		$field = TemplateField::create( 'Title', 'Title' );

		$this->assertSame( $field->createText(), $this->writer()->fieldText( $field ) );
	}
}
