<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\Form;
use MediaWikiIntegrationTestCase;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * @covers \MediaWiki\Extension\PageForms\Form
 *
 * @author Wandji Collins
 */
class FormTest extends MediaWikiIntegrationTestCase {
	private $pfForm;

	/**
	 * Set up environment
	 */
	public function setUp(): void {
		$this->pfForm = new Form();
		parent::setUp();
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\Form::getFormName
	 */
	public function testGetFormName() {
		// A freshly constructed Form() has no name until create() sets one.
		$this->assertNull( $this->pfForm->getFormName() );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\Form::getItems
	 */
	public function testGetItems() {
		// A freshly constructed Form() has no items until create() sets some.
		$this->assertNull( $this->pfForm->getItems() );
	}

	public function testCreateNormalizesUnderscoresToSpaces() {
		$form = Form::create( 'my_form', [] );
		$this->assertSame( 'My form', $form->getFormName() );
	}

	public function testCreateAppliesUcfirst() {
		$form = Form::create( 'test form', [] );
		$this->assertSame( 'Test form', $form->getFormName() );
	}

	public function testCreateHandlesNullFormName() {
		$form = Form::create( null, [] );
		$this->assertSame( '', $form->getFormName() );
	}

	public function testCreateSetsItems() {
		$form = Form::create( 'MyForm', [ 'item1', 'item2' ] );
		$this->assertSame( [ 'item1', 'item2' ], $form->getItems() );
	}

	public function testCreateMarkupContainsForminput() {
		$form = Form::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '{{#forminput:form=My Form}}', $markup );
	}

	public function testCreateMarkupContainsNoinclude() {
		$form = Form::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '<noinclude>', $markup );
		$this->assertStringContainsString( '</noinclude>', $markup );
	}

	public function testCreateMarkupContainsIncludeonly() {
		$form = Form::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '<includeonly>', $markup );
		$this->assertStringContainsString( '</includeonly>', $markup );
	}

	public function testCreateMarkupWithAssociatedCategory() {
		$form = Form::create( 'My Form', [] );
		$form->setAssociatedCategory( 'MyCategory' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|autocomplete on category=MyCategory', $markup );
	}

	public function testCreateMarkupWithPageNameFormula() {
		$form = Form::create( 'My Form', [] );
		$form->setPageNameFormula( '{{PAGENAME}}' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|page name={{PAGENAME}}', $markup );
	}

	public function testCreateMarkupWithCreateTitle() {
		$form = Form::create( 'My Form', [] );
		$form->setCreateTitle( 'Add entry' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|create title=Add entry', $markup );
	}

	public function testCreateMarkupWithEditTitle() {
		$form = Form::create( 'My Form', [] );
		$form->setEditTitle( 'Edit entry' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|edit title=Edit entry', $markup );
	}

	public function testCreateMarkupWithoutFreeTextOmitsFreeTextInput() {
		$form = Form::create( 'My Form', [] );
		$markup = $form->createMarkup( false );
		$this->assertStringNotContainsString( '{{{standard input|free text', $markup );
	}

	public function testCreateMarkupWithCustomFreeTextLabel() {
		$form = Form::create( 'My Form', [] );
		$markup = $form->createMarkup( true, 'Additional notes' );
		$this->assertStringContainsString( 'Additional notes', $markup );
		$this->assertStringContainsString( '{{{standard input|free text', $markup );
	}

	public function testCreateMarkupDefaultIncludesFreeText() {
		$form = Form::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '{{{standard input|free text', $markup );
	}

	public function testFormNameWithCommaEscapedInForminput() {
		$form = Form::create( 'Form,Name', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( 'form=Form\,Name', $markup );
	}
}
