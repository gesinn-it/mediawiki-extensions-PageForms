<?php

use MediaWiki\Extension\PageForms\WikidataValues;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\WikidataValues
 * @group PF
 */
class WikidataValuesTest extends TestCase {

	/**
	 * Runs $callback with the given globals set and restores them afterwards.
	 *
	 * @param array $globals
	 * @param callable $callback
	 * @return mixed
	 */
	private function withGlobals( array $globals, callable $callback ) {
		$previous = [];
		foreach ( $globals as $name => $value ) {
			$previous[$name] = $GLOBALS[$name] ?? null;
			$GLOBALS[$name] = $value;
		}
		try {
			return $callback();
		} finally {
			foreach ( $previous as $name => $value ) {
				$GLOBALS[$name] = $value;
			}
		}
	}

	/**
	 * Regression test for a SPARQL injection in WikidataValues::fetch(): a
	 * "values from wikidata" filter value was spliced unescaped into a SPARQL
	 * string literal, letting a Form editor break out of the literal and inject
	 * arbitrary SPARQL. The payload must end up inside the label literal, with
	 * its quotes escaped.
	 *
	 * @covers \MediaWiki\Extension\PageForms\WikidataValues::buildQuery
	 */
	public function testWikidataQueryEscapesInjectionInFilterValue(): void {
		$payload = 'nomatch"@en . } UNION { BIND("INJECTED-MARKER" AS ?valueLabel) } #';

		$sparql = $this->withGlobals( [ 'wgLanguageCode' => 'en' ], static function () use ( $payload ) {
			return WikidataValues::buildQuery( urlencode( 'P31=' . $payload ) );
		} );

		$this->assertStringContainsString(
			'rdfs:label "nomatch\\"@en . } UNION { BIND(\\"INJECTED-MARKER\\" AS ?valueLabel) } #"@en',
			$sparql
		);
		$this->assertStringNotContainsString( 'BIND("INJECTED-MARKER"', $sparql );
	}

	/**
	 * Same injection class, but via the $substring parameter (the autocomplete
	 * search term), which is spliced into a REGEX() string literal.
	 *
	 * @covers \MediaWiki\Extension\PageForms\WikidataValues::buildQuery
	 */
	public function testWikidataQueryEscapesInjectionInSubstring(): void {
		$substringPayload = 'x")) } UNION { BIND("INJECTED-MARKER" AS ?valueLabel) } #';

		$sparql = $this->withGlobals(
			[ 'wgLanguageCode' => 'en', 'wgPageFormsMaxAutocompleteValues' => 100 ],
			static function () use ( $substringPayload ) {
				return WikidataValues::buildQuery( urlencode( 'P31=Q6256' ), $substringPayload );
			}
		);

		// The substring is lowercased, then escaped, and stays inside the REGEX() literal.
		$this->assertStringContainsString(
			'FILTER(REGEX(LCASE(?valueLabel), "\\\\bx\\")) } union { bind(\\"injected-marker\\" as ?valuelabel) } #"))',
			$sparql
		);
		$this->assertStringNotContainsString( 'BIND("INJECTED-MARKER"', $sparql );
		$this->assertStringNotContainsString( 'bind("injected-marker"', $sparql );
	}

	/**
	 * The filter key is spliced into the query as a property reference ("wdt:P31"), so
	 * anything but a Wikidata property id would let a Form editor inject query structure
	 * through it. Such filters are dropped; the valid ones are kept.
	 *
	 * @covers \MediaWiki\Extension\PageForms\WikidataValues::buildQuery
	 */
	public function testWikidataQueryDropsFiltersWithAnInvalidPropertyKey(): void {
		$injectedKey = 'P31 wd:Q1 . } UNION { BIND("INJECTED-MARKER" AS ?valueLabel) } #';

		$sparql = $this->withGlobals( [ 'wgLanguageCode' => 'en' ], static function () use ( $injectedKey ) {
			return WikidataValues::buildQuery(
				urlencode( $injectedKey . '=Q6256&P17=Q183' )
			);
		} );

		$this->assertStringNotContainsString( 'INJECTED-MARKER', $sparql );
		$this->assertStringNotContainsString( 'UNION', $sparql );
		$this->assertStringContainsString( '?value wdt:P17 wd:Q183 .', $sparql );
	}

	/**
	 * A filter without a value ("P31") has nothing to compare with and is dropped
	 * instead of raising an undefined array key warning.
	 *
	 * @covers \MediaWiki\Extension\PageForms\WikidataValues::buildQuery
	 */
	public function testWikidataQueryDropsFiltersWithoutAValue(): void {
		$sparql = $this->withGlobals( [ 'wgLanguageCode' => 'en' ], static function () {
			return WikidataValues::buildQuery( urlencode( 'P31&P17=Q183' ) );
		} );

		$this->assertStringNotContainsString( 'wdt:P31', $sparql );
		$this->assertStringContainsString( '?value wdt:P17 wd:Q183 .', $sparql );
	}

	/**
	 * A numeric (item) filter is used as a plain wd: reference, a text filter
	 * becomes a label match, and the autocomplete limits only apply with a substring.
	 *
	 * @covers \MediaWiki\Extension\PageForms\WikidataValues::buildQuery
	 */
	public function testWikidataQueryBuildsItemAndLabelFiltersAndLimits(): void {
		[ $itemOnly, $labelOnly, $withSubstring ] = $this->withGlobals(
			[ 'wgLanguageCode' => 'en', 'wgPageFormsMaxAutocompleteValues' => 100 ],
			static function () {
				return [
					WikidataValues::buildQuery( urlencode( 'P31=Q6256' ) ),
					WikidataValues::buildQuery( urlencode( 'P31=Some "quoted" label' ) ),
					WikidataValues::buildQuery( urlencode( 'P31=Q6256' ), 'Ger' ),
				];
			}
		);

		$this->assertStringContainsString( '?value wdt:P31 wd:Q6256 .', $itemOnly );
		$this->assertStringContainsString( 'rdfs:label "Some \\"quoted\\" label"@en', $labelOnly );
		$this->assertStringNotContainsString( 'LIMIT', $itemOnly );
		$this->assertStringContainsString( 'LIMIT 110', $withSubstring );
		$this->assertStringEndsWith( 'LIMIT 100', $withSubstring );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\WikidataValues::parseResponse
	 */
	public function testParseResponseReturnsTheLabelsOfAllBindings(): void {
		$response = json_encode( [ 'results' => [ 'bindings' => [
			[ 'valueLabel' => [ 'type' => 'literal', 'value' => 'Germany' ] ],
			[ 'valueLabel' => [ 'type' => 'literal', 'value' => 'France' ] ],
		] ] ] );

		$this->assertSame( [ 'Germany', 'France' ], WikidataValues::parseResponse( $response ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\WikidataValues::parseResponse
	 */
	public function testParseResponseReturnsNothingForAnUnusableResponse(): void {
		$this->assertSame( [], WikidataValues::parseResponse( false ) );
		$this->assertSame( [], WikidataValues::parseResponse( 'not json' ) );
	}
}
