<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\TemplatePageValues;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\TemplatePageValues
 */
class TemplatePageValuesTest extends TestCase {

	private function read( string $template, string $page ): TemplatePageValues {
		$values = new TemplatePageValues();
		$values->setPageRelatedInfo( $template, $page );
		$values->setFieldValuesFromPage( $page );
		return $values;
	}

	public function testNamedParameters(): void {
		$values = $this->read( 'Tpl', "{{Tpl\n|a=1\n| b = two \n}}" );

		$this->assertTrue( (bool)$values->pageCallsThisTemplate() );
		$named = array_filter( $values->getValuesFromPage(), 'is_string', ARRAY_FILTER_USE_KEY );
		$this->assertSame( [ 'a' => '1', 'b' => 'two' ], $named );
		$this->assertSame( "{{Tpl\n|a=1\n| b = two \n}}", $values->getFullTextInPage() );
	}

	public function testParameterWithoutEqualsSignGetsItsIndexAsKey(): void {
		$values = $this->read( 'Tpl', '{{Tpl|first|x=1|second}}' );

		$found = $values->getValuesFromPage();
		$this->assertSame( 'first', $found[1] );
		$this->assertSame( '1', $found['x'] );
		$this->assertSame( 'second', $found[2] );
	}

	public function testPipesInLinksAndNestedTemplatesDoNotSplitTheParameter(): void {
		$values = $this->read( 'Tpl', '{{Tpl|a=[[Page|label]]|b={{Inner|x=1|y={{Deep|z}}}}|c=3}}' );

		$this->assertSame( '[[Page|label]]', $values->getValuesFromPage()['a'] );
		$this->assertSame( '{{Inner|x=1|y={{Deep|z}}}}', $values->getValuesFromPage()['b'] );
		$this->assertSame( '3', $values->getValuesFromPage()['c'] );
	}

	public function testEqualsSignInTheValueIsKept(): void {
		$values = $this->read( 'Tpl', '{{Tpl|formula=a=b+c}}' );

		$this->assertSame( 'a=b+c', $values->getValuesFromPage()['formula'] );
	}

	/**
	 * @dataProvider provideUnparsedTags
	 */
	public function testBracketsAndPipesInsideUnparsedTagsAreValueText( string $tagText ): void {
		$values = $this->read( 'Tpl', "{{Tpl|a=$tagText|b=2}}" );

		$this->assertSame( $tagText, $values->getValuesFromPage()['a'] );
		$this->assertSame( '2', $values->getValuesFromPage()['b'] );
	}

	public static function provideUnparsedTags(): array {
		return [
			'pre' => [ '<pre>{{ | [[ </pre>' ],
			'nowiki' => [ '<nowiki>}} | ]]</nowiki>' ],
			'ref' => [ '<ref>see {{cite|x}}</ref>' ],
			'syntaxhighlight' => [ '<syntaxhighlight lang="php">{{ | }}</syntaxhighlight>' ],
		];
	}

	public function testMismatchedBracketsAreReported(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'PageFormsMismatchedBrackets' );

		$this->read( 'Tpl', '{{Tpl|a={{Inner|x=1}}' );
	}

	public function testPageThatDoesNotCallTheTemplate(): void {
		$values = $this->read( 'Tpl', '{{Other|a=1}}' );

		$this->assertFalse( (bool)$values->pageCallsThisTemplate() );
		$this->assertSame( [], $values->getValuesFromPage() );
		$this->assertNull( $values->getFullTextInPage() );
	}

	public function testTemplateNameIsMatchedWithUnderscoresSpacesAndCase(): void {
		$values = $this->read( 'My_Template', '{{my template|a=1}}' );

		$this->assertTrue( (bool)$values->pageCallsThisTemplate() );
		$this->assertSame( 'My Template', $values->getSearchTemplateStr() );
		$this->assertSame( '1', $values->getValuesFromPage()['a'] );
	}

	public function testTemplateNameWithRegularExpressionCharacters(): void {
		$values = $this->read( 'A/B (old)^', '{{A/B (old)^|a=1}}' );

		$this->assertTrue( (bool)$values->pageCallsThisTemplate() );
		$this->assertSame( '1', $values->getValuesFromPage()['a'] );
	}

	public function testATemplateWhoseNameStartsWithTheSearchedNameIsNotMatched(): void {
		$values = $this->read( 'Tpl', '{{Tpl2|a=1}}' );

		$this->assertFalse( (bool)$values->pageCallsThisTemplate() );
	}

	public function testOnlyTheFirstCallIsRead(): void {
		$values = $this->read( 'Tpl', '{{Tpl|a=1}}{{Tpl|a=2}}' );

		$this->assertSame( '1', $values->getValuesFromPage()['a'] );
		$this->assertSame( '{{Tpl|a=1}}', $values->getFullTextInPage() );
	}

	public function testGetAndRemoveValueTakesTheValueOut(): void {
		$values = $this->read( 'Tpl', '{{Tpl|a=1|b=2}}' );

		$this->assertTrue( $values->hasValueFromPageForField( 'a' ) );
		$this->assertSame( '1', $values->getAndRemoveValueFromPageForField( 'a' ) );
		$this->assertFalse( $values->hasValueFromPageForField( 'a' ) );
		$this->assertTrue( $values->hasValueFromPageForField( 'b' ) );
	}

	public function testChangeFieldValuesReplacesTheValueAndDropsTheModifierKey(): void {
		$values = $this->read( 'Tpl', '{{Tpl|tags=a|tags+=b}}' );

		$values->changeFieldValues( 'tags', 'a,b', '+' );

		$this->assertSame( 'a,b', $values->getValuesFromPage()['tags'] );
		$this->assertArrayNotHasKey( 'tags+', $values->getValuesFromPage() );
	}

	public function testRemoveAndRestoreUnparsedTextRoundTrip(): void {
		$text = 'abc <pre>x|y</pre> b <ref name="n" /> c <nowiki>{{</nowiki> d <ref>unclosed';
		$replacements = [];

		$stripped = TemplatePageValues::removeUnparsedText( $text, $replacements );

		$this->assertStringNotContainsString( 'x|y', $stripped );
		$this->assertStringNotContainsString( '{{', $stripped );
		$this->assertStringContainsString( '<ref name="n" />', $stripped );
		$this->assertSame( $text, TemplatePageValues::restoreUnparsedText( $stripped, $replacements ) );
	}

	public function testReadFirstCallReturnsThePageWithoutThatCall(): void {
		$values = new TemplatePageValues();

		$remaining = $values->readFirstCall( 'Tpl', "intro\n{{Tpl|a=1}}{{Tpl|a=2}}\noutro" );

		$this->assertSame( "intro\n{{Tpl|a=2}}\noutro", $remaining );
		$this->assertSame( '1', $values->getValuesFromPage()['a'] );
		$this->assertSame( '{{Tpl|a=1}}', $values->getFullTextInPage() );
	}

	public function testReadFirstCallLeavesAPageThatDoesNotCallTheTemplateAlone(): void {
		$values = new TemplatePageValues();

		$this->assertSame( '{{Other|a=1}}', $values->readFirstCall( 'Tpl', '{{Other|a=1}}' ) );
		$this->assertFalse( (bool)$values->pageCallsThisTemplate() );
		$this->assertSame( [], $values->getValuesFromPage() );
	}

	public function testReadFirstCallRepeatedlyReadsEveryInstanceInPageOrder(): void {
		$values = new TemplatePageValues();
		$page = '{{Tpl|a=1}}{{Tpl|a=2}}{{Tpl|a=3}}';
		$seen = [];

		while ( true ) {
			$page = $values->readFirstCall( 'Tpl', $page );
			if ( !$values->pageCallsThisTemplate() ) {
				break;
			}
			$seen[] = $values->getValuesFromPage()['a'];
		}

		$this->assertSame( [ '1', '2', '3' ], $seen );
		$this->assertSame( '', $page );
	}

	public function testTakeValueFromPageAppendsTheValueOfAHoldsTemplateField(): void {
		$values = new TemplatePageValues();
		$values->readFirstCall( 'Tpl', '{{Tpl|items={{Emb|x=1}}{{Emb|x=2}}|o=1}}' );
		$remaining = 'rest';

		$this->assertSame( '{{Emb|x=1}}{{Emb|x=2}}', $values->takeValueFromPage( 'items', true, $remaining ) );
		$this->assertSame( 'rest{{Emb|x=1}}{{Emb|x=2}}', $remaining );

		$this->assertSame( '1', $values->takeValueFromPage( 'o', false, $remaining ) );
		$this->assertSame( 'rest{{Emb|x=1}}{{Emb|x=2}}', $remaining );
		$this->assertFalse( $values->hasValueFromPageForField( 'items' ) );
	}
}
