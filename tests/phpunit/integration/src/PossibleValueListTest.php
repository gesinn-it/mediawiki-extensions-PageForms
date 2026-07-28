<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\PossibleValueList;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @group PF
 * @group Database
 * @covers \MediaWiki\Extension\PageForms\PossibleValueList
 * @covers \MediaWiki\Extension\PageForms\PossibleValue
 */
class PossibleValueListTest extends MediaWikiIntegrationTestCase {

	public function testPlainListValueAndLabelAreTheSame(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ] );

		$match = $list->find( 'Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Foo', $match->getValue() );
		$this->assertSame( 'Foo', $match->getLabel() );
	}

	public function testCanonicalValueLabelMapFindsByValue(): void {
		$list = new PossibleValueList( [ 'Category:Foo' => 'Foo (DisplayTitle)' ] );

		$match = $list->find( 'Category:Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Category:Foo', $match->getValue() );
		$this->assertSame( 'Foo (DisplayTitle)', $match->getLabel() );
	}

	public function testCanonicalValueLabelMapFindsByLabel(): void {
		$list = new PossibleValueList( [ 'Category:Foo' => 'Foo (DisplayTitle)' ] );

		$match = $list->find( 'Foo (DisplayTitle)' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Category:Foo', $match->getValue() );
	}

	public function testAmbiguousLabelDoesNotResolve(): void {
		$list = new PossibleValueList( [
			'Category:Foo' => 'Duplicate Label',
			'Category:Bar' => 'Duplicate Label',
		] );

		$this->assertNull( $list->find( 'Duplicate Label' ) );
	}

	public function testUnknownValueReturnsNull(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ] );

		$this->assertNull( $list->find( 'Unknown' ) );
		$this->assertFalse( $list->contains( 'Unknown' ) );
	}

	public function testContainsTrueForKnownValue(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ] );

		$this->assertTrue( $list->contains( 'Foo' ) );
	}

	public function testEmptyListIsEmpty(): void {
		$list = new PossibleValueList( [] );

		$this->assertTrue( $list->isEmpty() );
		$this->assertSame( 0, $list->count() );
		$this->assertSame( [], $list->all() );
	}

	public function testCountAndAllReflectConstructorInput(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar', 'Baz' ] );

		$this->assertSame( 3, $list->count() );
		$this->assertFalse( $list->isEmpty() );
	}

	public function testValueEqualsComparesCanonicalValueOnly(): void {
		$list = new PossibleValueList( [ 'Category:Foo' => 'Foo (DisplayTitle)' ] );
		$other = new PossibleValueList( [ 'Category:Foo' => 'Different Label' ] );

		$a = $list->find( 'Category:Foo' );
		$b = $other->find( 'Category:Foo' );

		$this->assertTrue( $a->equals( $b ) );
	}

	/**
	 * Regression scenario for issue #178: a value saved through a form during
	 * the #175 regression window can have the content language's localized
	 * namespace prefix (e.g. "Kategorie:" on a German wiki) persisted into
	 * the page's stored wikitext, while possible_values is always keyed by
	 * the canonical (English) prefix. find() must still resolve such a
	 * legacy-prefixed raw value to its canonically-keyed possible value.
	 */
	public function testFindResolvesLegacyLocalizedNamespacePrefixOnNonEnglishWiki(): void {
		// Warm up NamespaceInfo's canonical-namespaces cache before switching
		// the language - otherwise SMW's own language-change guard
		// (NamespaceManager) throws upon the first getCanonicalName() call.
		MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalNamespaces();

		global $wgLanguageCode;
		$oldLanguageCode = $wgLanguageCode;
		$wgLanguageCode = 'de';
		$services = MediaWikiServices::getInstance();
		$services->resetServiceForTesting( 'ContentLanguage' );
		// Title::newFromText() resolves namespace prefixes via the
		// MediaWikiTitleCodec (TitleParser/TitleFormatter), which captures its
		// own ContentLanguage reference at construction time; resetting
		// ContentLanguage alone leaves it parsing against the old language.
		$services->resetServiceForTesting( '_MediaWikiTitleCodec' );

		try {
			$list = new PossibleValueList( [
				'Category:Product Aspect Dimensions' => 'Abmessungen',
				'Category:Product Aspect Weight And Volume' => 'Gewicht (Masse) und Volumen',
			] );

			$legacyMatch = $list->find( 'Kategorie:Product Aspect Dimensions' );
			$canonicalMatch = $list->find( 'Category:Product Aspect Weight And Volume' );

			$this->assertNotNull( $legacyMatch );
			$this->assertSame( 'Category:Product Aspect Dimensions', $legacyMatch->getValue() );
			$this->assertSame( 'Abmessungen', $legacyMatch->getLabel() );

			$this->assertNotNull( $canonicalMatch );
			$this->assertSame( 'Category:Product Aspect Weight And Volume', $canonicalMatch->getValue() );
			$this->assertSame( 'Gewicht (Masse) und Volumen', $canonicalMatch->getLabel() );
		} finally {
			$wgLanguageCode = $oldLanguageCode;
			$services->resetServiceForTesting( 'ContentLanguage' );
			$services->resetServiceForTesting( '_MediaWikiTitleCodec' );
		}
	}

	public function testFindDoesNotFalselyMatchDifferentPagesAcrossNamespaces(): void {
		$list = new PossibleValueList( [ 'Category:Foo' => 'Foo (DisplayTitle)' ] );

		$this->assertNull( $list->find( 'Foo' ) );
	}

	public function testValueLabelsOverrideKeyedByCanonicalValueTakesPrecedence(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ], [ 'Foo' => 'Custom Foo Label' ] );

		$match = $list->find( 'Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Custom Foo Label', $match->getLabel() );
		$this->assertTrue( $match->labelIsOverride() );
	}

	public function testValueLabelsOverrideKeyedByOriginalLabelIsUsedWhenNotKeyedByValue(): void {
		$list = new PossibleValueList(
			[ 'Category:Foo' => 'Foo (DisplayTitle)' ],
			[ 'Foo (DisplayTitle)' => 'Custom Override Label' ]
		);

		$match = $list->find( 'Category:Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Custom Override Label', $match->getLabel() );
		$this->assertTrue( $match->labelIsOverride() );
	}

	public function testValueLabelsAsJsonStringIsDecoded(): void {
		$list = new PossibleValueList( [ 'Foo' ], json_encode( [ 'Foo' => 'Custom Foo Label' ] ) );

		$match = $list->find( 'Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Custom Foo Label', $match->getLabel() );
	}

	public function testNoValueLabelsOverrideLeavesLabelUnchanged(): void {
		$list = new PossibleValueList( [ 'Foo' ] );

		$match = $list->find( 'Foo' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Foo', $match->getLabel() );
		$this->assertFalse( $match->labelIsOverride() );
	}

	/**
	 * Regression coverage for issue #185: a non-page-type raw value (e.g. a
	 * plain string property value) has nothing to resolve - it is already
	 * its own label.
	 */
	public function testResolveMissingLabelReturnsRawValueForNonPageValue(): void {
		$list = new PossibleValueList( [ 'Foo', 'Bar' ] );

		$this->assertSame( 'Some Plain Text', $list->resolveMissingLabel( 'Some Plain Text' ) );
	}

	/**
	 * Regression coverage for issue #185: a page-type raw value with no
	 * DisplayTitle set falls back to its bare canonical title, not the
	 * namespace-prefixed raw string as originally typed/stored.
	 */
	public function testResolveMissingLabelFallsBackToCanonicalTitleWithoutDisplayTitle(): void {
		$list = new PossibleValueList( [] );

		$label = $list->resolveMissingLabel( 'Category:PFResolveMissingLabelNoTitle' );

		$this->assertSame( 'Category:PFResolveMissingLabelNoTitle', $label );
	}

	/**
	 * Regression coverage for issue #185: when the target page has a
	 * DisplayTitle set, resolveMissingLabel() must return it instead of the
	 * raw namespace-prefixed value.
	 */
	public function testResolveMissingLabelUsesDisplayTitleWhenSet(): void {
		$this->overrideConfigValue( 'RestrictDisplayTitle', false );

		$title = \Title::makeTitle( NS_CATEGORY, 'PFResolveMissingLabelWithTitle' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->editPage( $page, '{{DISPLAYTITLE:Resolved Display Title}}' );
		\DeferredUpdates::doUpdates();

		$list = new PossibleValueList( [] );
		$label = $list->resolveMissingLabel( 'Category:PFResolveMissingLabelWithTitle' );

		$this->assertSame( 'Resolved Display Title', $label );
	}
}
