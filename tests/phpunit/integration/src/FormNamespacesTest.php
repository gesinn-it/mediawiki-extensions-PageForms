<?php

use MediaWiki\Extension\PageForms\FormNamespaces;

/**
 * @covers \MediaWiki\Extension\PageForms\FormNamespaces
 * @group Database
 */
class FormNamespacesTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideIgnoreFormNameCases
	 * @param array|string $patterns The value of $wgPageFormsIgnoreTitlePattern
	 * @param string $formName
	 * @param bool $expected
	 */
	public function testIgnoreFormName( $patterns, string $formName, bool $expected ) {
		$this->setMwGlobals( 'wgPageFormsIgnoreTitlePattern', $patterns );
		$this->assertSame( $expected, FormNamespaces::ignoreFormName( $formName ) );
	}

	/**
	 * @return array<string, array{0: array|string, 1: string, 2: bool}>
	 */
	public static function provideIgnoreFormNameCases(): array {
		return [
			'no patterns set' => [ [], 'MyForm', false ],
			'a matching pattern' => [ [ 'Test.*' ], 'TestForm', true ],
			'a pattern that does not match' => [ [ 'Test.*' ], 'ProductionForm', false ],
			// When the global is set to a plain string (not an array), the code wraps it.
			'a plain string as the pattern' => [ 'Ignore', 'IgnoreMe', true ],
		];
	}

	public function testGetNsText() {
		// NS_MAIN (0) typically returns empty string in content language
		$this->assertIsString( FormNamespaces::getNsText( NS_MAIN ) );
		$this->assertNotEmpty( FormNamespaces::getNsText( NS_USER ) );
	}

}
