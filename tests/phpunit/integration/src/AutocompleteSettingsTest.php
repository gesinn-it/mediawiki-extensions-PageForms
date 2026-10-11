<?php

use MediaWiki\Extension\PageForms\AutocompleteSettings;
use MediaWiki\Extension\PageForms\FormCounters;

/**
 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings
 * @group PF
 */
class AutocompleteSettingsTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgPageFormsUseDisplayTitle' => true,
			'wgPageFormsMaxLocalAutocompleteValues' => 100,
			'wgPageFormsAutocompleteValues' => [],
			'wgCapitalLinks' => false,
		] );
	}

	protected function tearDown(): void {
		FormCounters::resetStandalone();
		parent::tearDown();
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::maybeDisambiguateLabels
	 */
	public function testMaybeDisambiguateLabelsOnlyForMappedSources() {
		$labels = [
			'FooAlpha' => 'Paris',
			'FooBeta' => 'Paris'
		];
		$expected = [
			'FooAlpha' => 'Paris (FooAlpha)',
			'FooBeta' => 'Paris (FooBeta)'
		];

		foreach ( [ 'category', 'concept', 'namespace', 'property' ] as $sourceType ) {
			$this->assertSame( $expected, AutocompleteSettings::maybeDisambiguateLabels( $labels, $sourceType ) );
		}

		$GLOBALS['wgPageFormsUseDisplayTitle'] = false;
		$this->assertSame( $labels, AutocompleteSettings::maybeDisambiguateLabels( $labels, 'category' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::remoteDataType
	 */
	public function testRemoteDataTypeReturnsRemoteForCategoryWhenSmwAbsent() {
		// When SMW is not installed, AutocompleteSourceCount::get() returns null → always remote.
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$this->markTestSkipped( 'SMW is installed; this test requires SMW to be absent.' );
		}

		$fieldArgs = [
			'possible_values' => [
				'FooAlpha' => 'Paris',
				'FooBeta' => 'Paris'
			]
		];

		$remoteDataType = AutocompleteSettings::remoteDataType(
			'category',
			'DisambiguationCategory',
			$fieldArgs,
			'disambiguation-settings'
		);

		$this->assertSame( 'category', $remoteDataType );
		$this->assertArrayNotHasKey( 'disambiguation-settings', $GLOBALS['wgPageFormsAutocompleteValues'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::typeAndSource
	 * @dataProvider provideTypeAndSource
	 */
	public function testTypeAndSource( array $fieldArgs, array $expected ): void {
		$this->assertSame( $expected, AutocompleteSettings::typeAndSource( $fieldArgs ) );
	}

	public static function provideTypeAndSource(): array {
		return [
			'values from property' => [
				[ 'values from property' => 'MyProp' ],
				[ 'property', 'MyProp' ],
			],
			'values from category' => [
				[ 'values from category' => 'MyCat' ],
				[ 'category', 'MyCat' ],
			],
			'values from concept' => [
				[ 'values from concept' => 'MyConcept' ],
				[ 'concept', 'MyConcept' ],
			],
			'values from namespace' => [
				[ 'values from namespace' => 'Template' ],
				[ 'namespace', 'Template' ],
			],
			'values from url' => [
				[ 'values from url' => 'myalias' ],
				[ 'external_url', 'myalias' ],
			],
			'values from wikidata' => [
				[ 'values from wikidata' => 'Q123' ],
				[ 'wikidata', 'Q123' ],
			],
			'autocomplete field type passthrough' => [
				[ 'autocomplete field type' => 'custom', 'autocompletion source' => 'src' ],
				[ 'custom', 'src' ],
			],
			'semantic_property' => [
				[ 'semantic_property' => 'HasName' ],
				[ 'property', 'HasName' ],
			],
			'no match returns nulls' => [
				[],
				[ null, null ],
			],
		];
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::typeAndSource
	 */
	public function testTypeAndSourceForValues(): void {
		FormCounters::current()->fieldNum = 7;
		$fieldArgs = [ 'values' => 'a,b,c' ];
		$this->assertSame( [ 'values', 'values-7' ], AutocompleteSettings::typeAndSource( $fieldArgs ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::typeAndSource
	 */
	public function testTypeAndSourceCapitalizesTheSourceWhenLinksAreCapitalized(): void {
		$this->setMwGlobals( 'wgCapitalLinks', true );

		$this->assertSame(
			[ 'category', 'MyCat' ],
			AutocompleteSettings::typeAndSource( [ 'values from category' => 'myCat' ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::typeAndSource
	 */
	public function testTypeAndSourceKeepsTheAliasOfAnExternalUrl(): void {
		$this->setMwGlobals( 'wgCapitalLinks', true );

		$this->assertSame(
			[ 'external_url', 'myalias' ],
			AutocompleteSettings::typeAndSource( [ 'values from url' => 'myalias' ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::forField
	 */
	public function testForFieldLoadsTheValuesOfAFieldThatIsNoList(): void {
		FormCounters::current()->fieldNum = 3;

		$result = AutocompleteSettings::forField( [ 'values' => 'a,b' ], false );

		$this->assertSame( [ 'values-3', null, null ], $result );
		$this->assertSame( [ 'a', 'b' ], $GLOBALS['wgPageFormsAutocompleteValues']['values-3'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::forField
	 */
	public function testForFieldAddsTheListAndItsDelimiterToTheSettings(): void {
		FormCounters::current()->fieldNum = 3;

		$withDelimiter = AutocompleteSettings::forField( [ 'values' => 'a;b', 'delimiter' => ';' ], true );
		$withoutDelimiter = AutocompleteSettings::forField( [ 'values' => 'a,b' ], true );

		$this->assertSame( [ 'values-3,list,;', null, ';' ], $withDelimiter );
		$this->assertSame( [ 'values-3,list', null, ',' ], $withoutDelimiter );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::forField
	 */
	public function testForFieldWithoutASourceHasNoAutocompletion(): void {
		$this->assertSame( [ null, null, null ], AutocompleteSettings::forField( [], false ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::remoteDataType
	 */
	public function testRemoteDataTypeIsAlwaysRemoteForAnExternalUrlAndWikidata(): void {
		$this->assertSame( 'external_url', AutocompleteSettings::remoteDataType( 'external_url', 'alias', [], 'k' ) );
		$this->assertSame( 'wikidata', AutocompleteSettings::remoteDataType( 'wikidata', 'P31=Q5', [], 'k' ) );
		$this->assertArrayNotHasKey( 'k', $GLOBALS['wgPageFormsAutocompleteValues'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::remoteDataType
	 */
	public function testRemoteDataTypeIsNullWithoutASource(): void {
		$this->assertNull( AutocompleteSettings::remoteDataType( 'category', '', [], 'k' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::remoteDataType
	 */
	public function testRemoteDataTypeStaysRemoteWhenTheCallerForcesIt(): void {
		$this->assertSame(
			'category',
			AutocompleteSettings::remoteDataType( 'category', 'Cat', [], 'k', true )
		);
		$this->assertArrayNotHasKey( 'k', $GLOBALS['wgPageFormsAutocompleteValues'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::remoteDataType
	 */
	public function testRemoteDataTypeStaysRemoteForAMappedWikiSource(): void {
		foreach ( [ 'mapping template', 'mapping property' ] as $mappingArg ) {
			$this->assertSame(
				'category',
				AutocompleteSettings::remoteDataType( 'category', 'Cat', [ $mappingArg => 'X' ], 'k' )
			);
		}
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::remoteDataType
	 */
	public function testRemoteDataTypeLoadsAFewValuesAndFetchesManyWhileTyping(): void {
		$this->setMwGlobals( 'wgPageFormsMaxLocalAutocompleteValues', 3 );
		$few = [ 'autocomplete field type' => 'custom', 'autocompletion source' => 'src',
			'possible_values' => [ 'a', 'b', 'c' ] ];
		$many = [ 'autocomplete field type' => 'custom', 'autocompletion source' => 'src',
			'possible_values' => [ 'a', 'b', 'c', 'd' ] ];

		$this->assertNull( AutocompleteSettings::remoteDataType( 'custom', 'src', $few, 'few' ) );
		$this->assertSame( 'custom', AutocompleteSettings::remoteDataType( 'custom', 'src', $many, 'many' ) );

		$this->assertSame( [ 'a', 'b', 'c' ], $GLOBALS['wgPageFormsAutocompleteValues']['few'] );
		$this->assertArrayNotHasKey( 'many', $GLOBALS['wgPageFormsAutocompleteValues'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\AutocompleteSettings::remoteDataType
	 */
	public function testRemoteDataTypeLoadsManyValuesOfAMappedOrDependentFieldAnyway(): void {
		$this->setMwGlobals( 'wgPageFormsMaxLocalAutocompleteValues', 1 );

		foreach ( [ 'mapping template', 'mapping property', 'values dependent on' ] as $arg ) {
			$fieldArgs = [ $arg => 'X', 'possible_values' => [ 'a', 'b' ] ];

			$this->assertNull( AutocompleteSettings::remoteDataType( 'custom', 'src', $fieldArgs, $arg ), $arg );
			$this->assertSame( [ 'a', 'b' ], $GLOBALS['wgPageFormsAutocompleteValues'][$arg] );
		}
	}
}
