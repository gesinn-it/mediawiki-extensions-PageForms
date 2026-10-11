<?php

use MediaWiki\Extension\PageForms\AutocompleteSql;

/**
 * @covers \MediaWiki\Extension\PageForms\AutocompleteSql
 * @group Database
 * @group PF
 */
class AutocompleteSqlTest extends MediaWikiIntegrationTestCase {

	private function like( string ...$parts ): string {
		$db = $this->getDb();
		return $db->buildLike( ...array_map(
			static fn ( $part ) => $part === '*' ? $db->anyString() : $part,
			$parts
		) );
	}

	public function testConditionMatchesAnywhereWhenAutocompletingOnAllChars(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompleteOnAllChars', true );

		$condition = AutocompleteSql::conditionForColumn( 'page_title', 'Foo' );

		$this->assertStringContainsString( $this->like( '*', 'foo', '*' ), $condition );
		$this->assertStringNotContainsString( ' OR ', $condition );
	}

	public function testConditionMatchesTheStartOfAWordOtherwise(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompleteOnAllChars', false );

		$condition = AutocompleteSql::conditionForColumn( 'page_title', 'Foo' );

		$this->assertStringContainsString( $this->like( 'foo', '*' ), $condition );
		$this->assertStringContainsString( $this->like( '*', '_foo', '*' ), $condition );
		$this->assertStringContainsString( $this->like( '*', '/foo', '*' ), $condition );
		$this->assertStringContainsString( $this->like( '*', '-foo', '*' ), $condition );
	}

	public function testConditionTurnsSpacesIntoUnderscoresOnlyWhenAsked(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompleteOnAllChars', true );

		$title = AutocompleteSql::conditionForColumn( 'page_title', 'Foo Bar' );
		$displayTitle = AutocompleteSql::conditionForColumn( 'pp_displaytitle.pp_value', 'Foo Bar', false );

		$this->assertStringContainsString( $this->like( '*', 'foo_bar', '*' ), $title );
		$this->assertStringContainsString( $this->like( '*', 'foo bar', '*' ), $displayTitle );
	}

	public function testConditionLowersTheColumn(): void {
		$condition = AutocompleteSql::conditionForColumn( 'page_title', 'Foo' );

		$this->assertMatchesRegularExpression( '/^LOWER\(.*page_title.*\) LIKE /', $condition );
	}

	public function testDisplayTitleAndSortKeyPartsJoinBothPagePropsOnThePage(): void {
		[ $tables, $columns, $join ] = AutocompleteSql::displayTitleAndSortKeyParts();

		$this->assertSame( [ 'pp_displaytitle' => 'page_props', 'pp_defaultsort' => 'page_props' ], $tables );
		$this->assertSame(
			[
				'pp_displaytitle_value' => 'pp_displaytitle.pp_value',
				'pp_defaultsort_value' => 'pp_defaultsort.pp_value',
			],
			$columns
		);
		$this->assertSame( [ 'pp_displaytitle', 'pp_defaultsort' ], array_keys( $join ) );
		$this->assertSame( 'LEFT JOIN', $join['pp_displaytitle'][0] );
		$this->assertContains( "pp_displaytitle.pp_propname = 'displaytitle'", $join['pp_displaytitle'][1] );
		$this->assertContains( "pp_defaultsort.pp_propname = 'defaultsort'", $join['pp_defaultsort'][1] );
	}
}
