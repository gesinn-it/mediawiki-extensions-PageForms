<?php

use MediaWiki\Extension\PageForms\DisplayTitles;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\DisplayTitles
 * @group PF
 */
class DisplayTitlesTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveReturnsFallbackForNull(): void {
		$this->assertSame( 'Page', DisplayTitles::resolve( null, 'Page' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveReturnsFallbackForBlankString(): void {
		$this->assertSame( 'Page', DisplayTitles::resolve( '   ', 'Page' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveReturnsFallbackForTagsOnly(): void {
		$this->assertSame( 'Page', DisplayTitles::resolve( '<b></b>', 'Page' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveReturnsFallbackForNbspOnly(): void {
		$this->assertSame( 'Page', DisplayTitles::resolve( '&#160;', 'Page' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveReturnsRawWhenSet(): void {
		$this->assertSame( 'My Display Title', DisplayTitles::resolve( 'My Display Title', 'Page' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveDecodesHtmlEntities(): void {
		$this->assertSame( 'Hello "World"', DisplayTitles::resolve( 'Hello &quot;World&quot;', 'Page' ) );
	}

	/**
	 * MW stores displaytitle as parsed HTML (e.g. <i>MyPage</i> for ''MyPage'').
	 * resolve() must return plain text, not raw HTML, so that Select2
	 * renders the label correctly in the tokens dropdown.
	 *
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveStripsHtmlTagsFromParsedTitle(): void {
		$this->assertSame( 'MyPage', DisplayTitles::resolve( '<i>MyPage</i>', 'Page' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveStripsNestedHtmlTags(): void {
		$this->assertSame( 'My Page', DisplayTitles::resolve( '<b><i>My Page</i></b>', 'Page' ) );
	}

	/**
	 * HTML entities inside tagged content must be decoded to plain text.
	 *
	 * @covers \MediaWiki\Extension\PageForms\DisplayTitles::resolve
	 */
	public function testResolveDecodesEntitiesInsideHtmlTags(): void {
		$raw = '<i>Hello &quot;World&quot;</i>';
		$this->assertSame( 'Hello "World"', DisplayTitles::resolve( $raw, 'Page' ) );
	}
}
