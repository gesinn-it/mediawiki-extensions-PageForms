<?php

use MediaWiki\Extension\PageForms\ExternalUrlValues;

/**
 * @covers \MediaWiki\Extension\PageForms\ExternalUrlValues
 * @group PF
 */
class ExternalUrlValuesTest extends MediaWikiIntegrationTestCase {

	private function answerWith( $body, ?string &$requestedUrl = null ): void {
		$factory = $this->createMock( \MediaWiki\Http\HttpRequestFactory::class );
		$factory->method( 'get' )->willReturnCallback(
			static function ( $url ) use ( $body, &$requestedUrl ) {
				$requestedUrl = $url;
				return $body;
			}
		);
		$this->setService( 'HttpRequestFactory', $factory );
	}

	public function testGetReportsThatNoUrlIsSetUp(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompletionURLs', [] );

		$this->assertSame( 'pf-nocompletionurls', ExternalUrlValues::get( 'alias', 'x' )->getKey() );
	}

	public function testGetReportsAnAliasThatIsNotSetUp(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompletionURLs', [ 'other' => 'https://example.org/?q=<substr>' ] );

		$this->assertSame( 'pf-invalidexturl', ExternalUrlValues::get( 'alias', 'x' )->getKey() );
	}

	public function testGetReportsABlankUrl(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompletionURLs', [ 'alias' => '' ] );

		$this->assertSame( 'pf-blankexturl', ExternalUrlValues::get( 'alias', 'x' )->getKey() );
	}

	public function testGetReportsAnEmptyAnswer(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompletionURLs', [ 'alias' => 'https://example.org/?q=<substr>' ] );
		$this->answerWith( '' );

		$this->assertSame( 'pf-externalpageempty', ExternalUrlValues::get( 'alias', 'x' )->getKey() );
	}

	public function testGetReportsAnAnswerThatIsNoJson(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompletionURLs', [ 'alias' => 'https://example.org/?q=<substr>' ] );
		$this->answerWith( 'not json' );

		$this->assertSame( 'pf-externalpagebadjson', ExternalUrlValues::get( 'alias', 'x' )->getKey() );
	}

	public function testGetReturnsTheValuesOfTheAnswerAndSendsTheEncodedSubstring(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompletionURLs', [ 'alias' => 'https://example.org/?q=<substr>' ] );
		$answer = [ 'pfautocomplete' => [ [ 'title' => 'One' ], [ 'title' => 'Two' ] ] ];
		$this->answerWith( json_encode( $answer ), $url );

		$values = ExternalUrlValues::get( 'alias', 'a b&c' );

		$this->assertSame( [ [ 'title' => 'One' ], [ 'title' => 'Two' ] ], $values );
		$this->assertSame( 'https://example.org/?q=a+b%26c', $url );
	}

	public function testGetSendsAnEmptySubstringIfThereIsNone(): void {
		$this->setMwGlobals( 'wgPageFormsAutocompletionURLs', [ 'alias' => 'https://example.org/?q=<substr>' ] );
		$this->answerWith( json_encode( [ 'pfautocomplete' => [] ] ), $url );

		ExternalUrlValues::get( 'alias', null );

		$this->assertSame( 'https://example.org/?q=', $url );
	}
}
