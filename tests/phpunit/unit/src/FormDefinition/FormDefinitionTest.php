<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinition;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FormDefinition
 */
class FormDefinitionTest extends TestCase {

	private const FORM_DEF = "intro\n{{{for template|T|multiple|label=Things}}}\n{{{field|a|input type=text}}}\n"
		. "{{{field|b|holds template}}}\n{{{end template}}}\n{{{section|S|level=2}}}\n{{{info|Add Title=New}}}\n"
		. "{{{standard input|save}}}\n{{{bogus|x}}}\ntail";

	public function testAnEmptyDefinitionHasNoElements(): void {
		$definition = new FormDefinition();

		$this->assertSame( [], $definition->getElements() );
		$this->assertSame( [ 'elements' => [] ], $definition->toArray() );
		$this->assertSame( '', FormDefinition::fromArray( $definition->toArray() )->toWikitext() );
	}

	public function testArrayFormGivesBackTheSameElementsAndTypes(): void {
		$original = ( new FormDefinitionReader() )->read( self::FORM_DEF );

		$copy = FormDefinition::fromArray( $original->toArray() );

		$this->assertSame(
			array_map( 'get_class', $original->getElements() ),
			array_map( 'get_class', $copy->getElements() )
		);
		$this->assertSame( $original->toArray(), $copy->toArray() );
		$this->assertSame( self::FORM_DEF, $copy->toWikitext() );
	}

	public function testTheModelOfACopyIsUsable(): void {
		$copy = FormDefinition::fromArray( ( new FormDefinitionReader() )->read( self::FORM_DEF )->toArray() );

		$templates = $copy->getTemplates();
		$this->assertCount( 1, $templates );
		$this->assertTrue( $templates[0]->isMultiple() );
		$this->assertSame( [ 'a', 'b' ], $templates[0]->getFieldNames() );
		$this->assertTrue( $templates[0]->getFields()[1]->holdsTemplate() );
	}

	public function testArrayFormSurvivesJsonLikeTheCacheStoresIt(): void {
		$original = ( new FormDefinitionReader() )->read( self::FORM_DEF );

		$stored = json_decode( json_encode( $original->toArray() ), true );

		$this->assertSame( self::FORM_DEF, FormDefinition::fromArray( $stored )->toWikitext() );
	}

	public function testAnUnknownElementTypeIsRejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( "Unknown form element type 'nonsense'" );

		FormDefinition::fromArray( [ 'elements' => [ [ 'type' => 'nonsense' ] ] ] );
	}
}
