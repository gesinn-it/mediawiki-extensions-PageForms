<?php

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * @covers \PFForm
 *
 * @author Wandji Collins
 */
class PFFormTest extends MediaWikiIntegrationTestCase {
	private $pfForm;

	/**
	 * Set up environment
	 */
	public function setUp(): void {
		$this->pfForm = new PFForm();
		parent::setUp();
	}

	/**
	 * @covers PFForm::getFormName
	 */
	public function testGetFormName() {
		$actual = $this->pfForm->getFormName();
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->getFormName() );
	}

	/**
	 * @covers PFForm::getItems
	 */
	public function testGetItems() {
		$actual = $this->pfForm->getItems();
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->getItems() );
	}

	public function testCreateNormalizesUnderscoresToSpaces() {
		$form = PFForm::create( 'my_form', [] );
		$this->assertSame( 'My form', $form->getFormName() );
	}

	public function testCreateAppliesUcfirst() {
		$form = PFForm::create( 'test form', [] );
		$this->assertSame( 'Test form', $form->getFormName() );
	}

	public function testCreateHandlesNullFormName() {
		$form = PFForm::create( null, [] );
		$this->assertSame( '', $form->getFormName() );
	}

	public function testCreateSetsItems() {
		$form = PFForm::create( 'MyForm', [ 'item1', 'item2' ] );
		$this->assertSame( [ 'item1', 'item2' ], $form->getItems() );
	}

	public function testCreateMarkupContainsForminput() {
		$form = PFForm::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '{{#forminput:form=My Form}}', $markup );
	}

	public function testCreateMarkupContainsNoinclude() {
		$form = PFForm::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '<noinclude>', $markup );
		$this->assertStringContainsString( '</noinclude>', $markup );
	}

	public function testCreateMarkupContainsIncludeonly() {
		$form = PFForm::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '<includeonly>', $markup );
		$this->assertStringContainsString( '</includeonly>', $markup );
	}

	public function testCreateMarkupWithAssociatedCategory() {
		$form = PFForm::create( 'My Form', [] );
		$form->setAssociatedCategory( 'MyCategory' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|autocomplete on category=MyCategory', $markup );
	}

	public function testCreateMarkupWithPageNameFormula() {
		$form = PFForm::create( 'My Form', [] );
		$form->setPageNameFormula( '{{PAGENAME}}' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|page name={{PAGENAME}}', $markup );
	}

	public function testCreateMarkupWithCreateTitle() {
		$form = PFForm::create( 'My Form', [] );
		$form->setCreateTitle( 'Add entry' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|create title=Add entry', $markup );
	}

	public function testCreateMarkupWithEditTitle() {
		$form = PFForm::create( 'My Form', [] );
		$form->setEditTitle( 'Edit entry' );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '|edit title=Edit entry', $markup );
	}

	public function testCreateMarkupWithoutFreeTextOmitsFreeTextInput() {
		$form = PFForm::create( 'My Form', [] );
		$markup = $form->createMarkup( false );
		$this->assertStringNotContainsString( '{{{standard input|free text', $markup );
	}

	public function testCreateMarkupWithCustomFreeTextLabel() {
		$form = PFForm::create( 'My Form', [] );
		$markup = $form->createMarkup( true, 'Additional notes' );
		$this->assertStringContainsString( 'Additional notes', $markup );
		$this->assertStringContainsString( '{{{standard input|free text', $markup );
	}

	public function testCreateMarkupDefaultIncludesFreeText() {
		$form = PFForm::create( 'My Form', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( '{{{standard input|free text', $markup );
	}

	public function testFormNameWithCommaEscapedInForminput() {
		$form = PFForm::create( 'Form,Name', [] );
		$markup = $form->createMarkup();
		$this->assertStringContainsString( 'form=Form\,Name', $markup );
	}
}
