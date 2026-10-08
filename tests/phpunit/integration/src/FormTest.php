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

}
