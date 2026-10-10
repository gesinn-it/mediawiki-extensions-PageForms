<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWikiIntegrationTestCase;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * Characterization (golden master) tests for Template::createText().
 *
 * The exact wikitext for every template format, with fields of each kind (plain, with a property,
 * as a list, "nonempty", "hidden", in a namespace), with a connecting property, an aggregating
 * property and a category, with the full wikitext documentation and with the hooks, is kept as a
 * snapshot in tests/phpunit/integration/golden/templatetext. It pins the output byte for byte, so
 * that the structure of createText() can change without a change of what it writes.
 *
 * The snapshots also pin a known defect on purpose: for a field in the main namespace the calls of
 * #set, #set_internal and #subobject get "0:" in front of the field parameter ("Has born=0:{{{Born|}}}"),
 * because Template::createText() tests getNamespace() against null while TemplateField defaults to 0.
 * Fix it in its own commit and record the snapshots again there, not as part of a refactoring.
 *
 * To record the snapshots again after an intended change, run the tests with the environment
 * variable PF_UPDATE_GOLDEN=1 and review the diff of the snapshot files.
 *
 * @group PF
 * @group Database
 * @covers \MediaWiki\Extension\PageForms\Template::createText
 * @covers \MediaWiki\Extension\PageForms\Template::createTextForField
 * @covers \MediaWiki\Extension\PageForms\Template::printCategoryTag
 */
class TemplateTextCharacterizationTest extends MediaWikiIntegrationTestCase {

	private const GOLDEN_DIR = __DIR__ . '/../golden/templatetext';

	protected function setUp(): void {
		parent::setUp();
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'The snapshots are recorded with SMW installed.' );
		}
		if ( defined( 'SIO_VERSION' ) ) {
			$this->markTestSkipped( 'The snapshots are recorded without Semantic Internal Objects.' );
		}
	}

	public static function provideTemplates(): iterable {
		foreach ( [ 'default' => null, 'standard' => 'standard', 'infobox' => 'infobox',
			'plain' => 'plain', 'sections' => 'sections' ] as $formatName => $format ) {
			yield "simple-$formatName" => [ 'simple', $format ];
			yield "mixed-$formatName" => [ 'mixed', $format ];
			yield "connected-$formatName" => [ 'connected', $format ];
		}
		yield 'no-fields' => [ 'none', 'standard' ];
		yield 'full-wikitext' => [ 'full-wikitext', null ];
		yield 'full-wikitext-no-fields' => [ 'full-wikitext-none', null ];
		yield 'field-hooks' => [ 'field-hooks', 'standard' ];
		yield 'create-hook' => [ 'create-hook', 'plain' ];
	}

	/**
	 * @dataProvider provideTemplates
	 */
	public function testCreateText( string $scenario, ?string $format ) {
		$template = $this->buildTemplate( $scenario );
		if ( $format !== null ) {
			$template->setFormat( $format );
		}

		$this->assertGolden( $scenario . '-' . ( $format ?? 'default' ), $template->createText() );
	}

	private function buildTemplate( string $scenario ): Template {
		switch ( $scenario ) {
			case 'none':
				return new Template( 'Empty', [] );
			case 'simple':
				return new Template( 'Book', [
					TemplateField::create( 'Title', 'Title' ),
					TemplateField::create( 'Author', 'Written by' ),
					// A field without a name is skipped everywhere.
					TemplateField::create( '', null ),
					TemplateField::create( 'Year', '' ),
				] );
			case 'mixed':
				return new Template( 'Person', $this->mixedFields() );
			case 'connected':
				$template = new Template( 'Person', $this->mixedFields() );
				$template->setConnectingProperty( 'Has person' );
				$template->setAggregatingInfo( 'Has parent', 'Children' );
				$template->setCategoryName( 'People' );
				return $template;
			case 'full-wikitext':
				$template = new Template( 'Person', $this->mixedFields() );
				$template->setFullWikiTextStatus( true );
				return $template;
			case 'full-wikitext-none':
				$template = new Template( 'Empty', [] );
				$template->setFullWikiTextStatus( true );
				return $template;
			case 'field-hooks':
				$this->setTemporaryHook( 'PageForms::TemplateFieldStart', static function ( $field, &$start ) {
					$start = '<' . $field->getFieldName() . '>';
				} );
				$this->setTemporaryHook( 'PageForms::TemplateFieldEnd', static function ( $field, &$end ) {
					$end = '</' . $field->getFieldName() . '>';
				} );
				return new Template( 'Hooked', [
					TemplateField::create( 'Name', 'Name' ),
					TemplateField::create( 'Born', 'Born', 'Has born' ),
				] );
			case 'create-hook':
				$this->setTemporaryHook( 'PageForms::CreateTemplateText', static function ( Template &$template ) {
					$template->setCategoryName( 'FromHook' );
					$template->setAggregatingInfo( 'Has hook', 'Hooked' );
				} );
				return new Template( 'Hooked', [ TemplateField::create( 'Name', 'Name' ) ] );
		}
		$this->fail( "Unknown scenario $scenario" );
	}

	/**
	 * One field of every kind the writer distinguishes.
	 *
	 * @return TemplateField[]
	 */
	private function mixedFields(): array {
		$inFile = TemplateField::create( 'Photo', 'Photo', 'Has photo' );
		$inFile->setFieldType( 'File' );

		return [
			TemplateField::create( 'Name', 'Name' ),
			TemplateField::create( 'Born', 'Born', 'Has born' ),
			TemplateField::create( 'Tags', 'Tags', 'Has tag', true, ';' ),
			TemplateField::create( 'Nickname', 'Nickname', null, false, null, 'nonempty' ),
			TemplateField::create( 'Note', 'Note', 'Has note', false, null, 'nonempty' ),
			TemplateField::create( 'Secret', 'Secret', 'Has secret', false, null, 'hidden' ),
			TemplateField::create( 'Codes', 'Codes', 'Has code', true, null, 'hidden' ),
			TemplateField::create( 'Draft', 'Draft', null, false, null, 'hidden' ),
			$inFile,
		];
	}

	private function assertGolden( string $name, string $actual ): void {
		$file = self::GOLDEN_DIR . '/' . preg_replace( '/[^A-Za-z0-9]+/', '-', $name ) . '.wikitext';

		if ( getenv( 'PF_UPDATE_GOLDEN' ) ) {
			if ( !is_dir( self::GOLDEN_DIR ) ) {
				mkdir( self::GOLDEN_DIR, 0775, true );
			}
			file_put_contents( $file, $actual );
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->assertFileExists( $file, "No snapshot for '$name'. Record it with PF_UPDATE_GOLDEN=1." );
		$this->assertSame( file_get_contents( $file ), $actual, "The output of createText() changed for '$name'" );
	}

}
