<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\HiddenInputsBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\HiddenInputsBuilder
 */
class HiddenInputsBuilderTest extends TestCase {

	public function testEmptyQueryArrayReturnsEmptyString(): void {
		$html = HiddenInputsBuilder::fromQueryArray( [] );

		$this->assertSame( '', $html );
	}

	public function testSingleValueProducesOneHiddenInput(): void {
		$html = HiddenInputsBuilder::fromQueryArray( [ 'form' => 'MyForm' ] );

		$this->assertStringContainsString( 'name="form"', $html );
		$this->assertStringContainsString( 'value="MyForm"', $html );
	}

	public function testMultipleValuesProduceOneHiddenInputPerPair(): void {
		$html = HiddenInputsBuilder::fromQueryArray( [ 'a' => '1', 'b' => '2' ] );

		$this->assertStringContainsString( 'name="a"', $html );
		$this->assertStringContainsString( 'value="1"', $html );
		$this->assertStringContainsString( 'name="b"', $html );
		$this->assertStringContainsString( 'value="2"', $html );
	}

	public function testSpecialCharactersAreUrlDecodedAndHtmlEscaped(): void {
		$html = HiddenInputsBuilder::fromQueryArray( [ 'parameter' => '1 & 2 + 3' ] );

		$this->assertStringContainsString( 'value="1 &amp; 2 + 3"', $html );
	}

	public function testAttributesCallbackReceivesDecodedNameAndValue(): void {
		$seen = [];
		HiddenInputsBuilder::fromQueryArray(
			[ 'Foo' => 'Bar' ],
			static function ( string $name, string $value ) use ( &$seen ) {
				$seen[] = [ $name, $value ];
				return [];
			}
		);

		$this->assertSame( [ [ 'Foo', 'Bar' ] ], $seen );
	}

	public function testAttributesCallbackCanAddExtraAttributes(): void {
		$html = HiddenInputsBuilder::fromQueryArray(
			[ 'rating' => '' ],
			static fn ( string $name, string $value ) => $value === '' ? [ 'id' => 'ratingInput' ] : []
		);

		$this->assertStringContainsString( 'id="ratingInput"', $html );
	}

	public function testAttributesCallbackOmittedAddsNoExtraAttributes(): void {
		$html = HiddenInputsBuilder::fromQueryArray( [ 'rating' => '' ] );

		$this->assertStringNotContainsString( 'id=', $html );
	}
}
