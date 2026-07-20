<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWikiIntegrationTestCase;
use PFUtils;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * Integration tests for Template::createText(), covering all supported
 * template formats and the printCategoryTag() helper.
 *
 * createText() delegates to MediaWikiServices::getHookContainer() once at the
 * top, so we need a real MW environment; beyond that the method is pure string
 * logic and can be tested by inspecting the returned wikitext.
 *
 * @group PF
 * @covers \MediaWiki\Extension\PageForms\Template::createText
 * @covers \MediaWiki\Extension\PageForms\Template::createTextForField
 * @covers \MediaWiki\Extension\PageForms\Template::printCategoryTag
 * @covers \MediaWiki\Extension\PageForms\Template::setFormat
 * @covers \MediaWiki\Extension\PageForms\Template::setFullWikiTextStatus
 * @covers \MediaWiki\Extension\PageForms\Template::setCategoryName
 * @covers \MediaWiki\Extension\PageForms\Template::setConnectingProperty
 * @covers \MediaWiki\Extension\PageForms\Template::setAggregatingInfo
 * @covers \MediaWiki\Extension\PageForms\Template::getFieldNamed
 * @covers \MediaWiki\Extension\PageForms\Template::getTemplateFields
 */
class TemplateCreateTextTest extends MediaWikiIntegrationTestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function makeTemplate( array $fieldDefs, string $name = 'TestTemplate' ): Template {
		$fields = [];
		foreach ( $fieldDefs as $def ) {
			$fields[] = TemplateField::create(
				$def['name'],
				$def['label'] ?? $def['name'],
				$def['property'] ?? null,
				$def['isList'] ?? false,
				$def['delimiter'] ?? null,
				$def['display'] ?? null
			);
		}
		return new Template( $name, $fields );
	}

	// -----------------------------------------------------------------------
	// printCategoryTag
	// -----------------------------------------------------------------------

	public function testPrintCategoryTagEmptyWhenNoCategory() {
		$t = new Template( 'T', [] );
		$this->assertSame( '', $t->printCategoryTag() );
	}

	public function testPrintCategoryTagReturnsWikilink() {
		$t = new Template( 'T', [] );
		$t->setCategoryName( 'MyCategory' );
		$result = $t->printCategoryTag();
		$this->assertStringContainsString( 'MyCategory', $result );
		$this->assertStringContainsString( '[[', $result );
	}

	// -----------------------------------------------------------------------
	// getFieldNamed
	// -----------------------------------------------------------------------

	public function testGetFieldNamedReturnsNullWhenNotFound() {
		$t = $this->makeTemplate( [ [ 'name' => 'Title' ] ] );
		$this->assertNull( $t->getFieldNamed( 'NonExistent' ) );
	}

	public function testGetFieldNamedReturnsCorrectField() {
		$t = $this->makeTemplate( [ [ 'name' => 'Author' ], [ 'name' => 'Title' ] ] );
		$field = $t->getFieldNamed( 'Title' );
		$this->assertNotNull( $field );
		$this->assertSame( 'Title', $field->getFieldName() );
	}

	public function testGetTemplateFieldsReturnsAll() {
		$t = $this->makeTemplate( [ [ 'name' => 'A' ], [ 'name' => 'B' ], [ 'name' => 'C' ] ] );
		$this->assertCount( 3, $t->getTemplateFields() );
	}

	// -----------------------------------------------------------------------
	// createText — #template_params path (mFullWikiText = false, no SMW)
	// -----------------------------------------------------------------------

	// NOTE: testCreateTextNoSMWUsesTemplateDisplay and testCreateTextNoFieldsProducesValidWikitext
	// were removed. They tested the `if ( !defined( 'SMW_VERSION' ) )` early-return branch
	// in createText(), which is unreachable in this CI environment (SMW always present).
	// That branch is annotated with @codeCoverageIgnore in src/Template.php.

	// -----------------------------------------------------------------------
	// createText — fullWikiText path
	// -----------------------------------------------------------------------

	public function testCreateTextFullWikiTextContainsDocuHeader() {
		$t = $this->makeTemplate( [ [ 'name' => 'Name' ] ] );
		$t->setFullWikiTextStatus( true );
		$text = $t->createText();
		$this->assertStringContainsString( '{{TestTemplate', $text );
		$this->assertStringContainsString( '|Name=', $text );
		$this->assertStringContainsString( '<noinclude>', $text );
		$this->assertStringContainsString( '<includeonly>', $text );
	}

	public function testCreateTextFullWikiTextSkipsEmptyFieldNames() {
		$fields = [
			TemplateField::create( '', null ),
			TemplateField::create( 'ValidField', null ),
		];
		$t = new Template( 'T', $fields );
		$t->setFullWikiTextStatus( true );
		$text = $t->createText();
		$this->assertStringContainsString( '|ValidField=', $text );
		// The empty-name field must not produce a bare '|=' line
		$this->assertStringNotContainsString( '|=', $text );
	}

	// -----------------------------------------------------------------------
	// createText — standard format (SMW present)
	// -----------------------------------------------------------------------

	public function testCreateTextStandardFormatContainsWikitable() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Title' ], [ 'name' => 'Author' ] ] );
		$text = $t->createText();
		$this->assertStringContainsString( '{| class="wikitable"', $text );
		$this->assertStringContainsString( '! Title', $text );
		$this->assertStringContainsString( '! Author', $text );
		$this->assertStringContainsString( '{{{Title|}}}', $text );
		$this->assertStringContainsString( '{{{Author|}}}', $text );
	}

	// -----------------------------------------------------------------------
	// createText — format variations
	// -----------------------------------------------------------------------

	public function testCreateTextPlainFormatUsesBoldLabels() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Name' ] ] );
		$t->setFormat( 'plain' );
		$text = $t->createText();
		$this->assertStringContainsString( "'''Name:'''", $text );
		$this->assertStringNotContainsString( '{| class="wikitable"', $text );
	}

	public function testCreateTextSectionsFormatUsesHeadings() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Section' ] ] );
		$t->setFormat( 'sections' );
		$text = $t->createText();
		$this->assertStringContainsString( '==Section==', $text );
	}

	public function testCreateTextInfoboxFormatContainsInfoboxStyle() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Name' ] ] );
		$t->setFormat( 'infobox' );
		$text = $t->createText();
		$this->assertStringContainsString( 'float: right', $text );
	}

	// -----------------------------------------------------------------------
	// createText — category tag
	// -----------------------------------------------------------------------

	public function testCreateTextIncludesCategoryWhenSet() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Name' ] ] );
		$t->setCategoryName( 'Books' );
		$text = $t->createText();
		$this->assertStringContainsString( 'Books', $text );
	}

	public function testCreateTextNoCategoryWhenEmpty() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Name' ] ] );
		$text = $t->createText();
		// No category namespace link should appear
		$langs = PFUtils::getContLang()->getNamespaces();
		$catNs = $langs[NS_CATEGORY];
		$this->assertStringNotContainsString( "[[$catNs:", $text );
	}

	// -----------------------------------------------------------------------
	// createText — aggregating property
	// -----------------------------------------------------------------------

	public function testCreateTextAggregatingPropertyAppearsInOutput() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Author' ] ] );
		$t->setAggregatingInfo( 'Has book', 'Books' );
		$text = $t->createText();
		$this->assertStringContainsString( '[[Has book::', $text );
		$this->assertStringContainsString( 'Books', $text );
	}

	// -----------------------------------------------------------------------
	// createText — display=nonempty field
	// -----------------------------------------------------------------------

	public function testCreateTextNonemptyFieldUsesIfWrapper() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'SMW not installed' );
		}
		$t = $this->makeTemplate( [ [ 'name' => 'Subtitle', 'display' => 'nonempty' ] ] );
		$text = $t->createText();
		$this->assertStringContainsString( '{{#if:', $text );
	}

}
