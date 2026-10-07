<?php

use OOUI\BlankTheme;

/**
 * An autoedit SAVE on an existing page reads the page's current field values in one of two
 * ways: directly from the page wikitext (FormDefParser::preparePreloadData()), or through
 * formHTML() plus HtmlFormDataExtractor. The second route is taken as soon as a request key
 * ends in "+" or "-" (see PFAutoeditAPI::hasModifierKeys()), which this test uses to force
 * it with a dummy template key.
 *
 * Both routes must save the same page text. Cases where they are known to differ are listed
 * in provideKnownGaps(): they are reported as incomplete on every run, and fail as soon as the
 * routes agree, so the entry gets removed together with the fix.
 *
 * @covers \PFAutoeditAPI::execute
 * @covers \MediaWiki\Extension\PageForms\FormDefParser::preparePreloadData
 * @group PF
 * @group Database
 * @group medium
 */
class AutoeditPreloadParityTest extends ApiTestCase {

	private const SAVE_INPUTS = "{{{standard input|free text}}}\n{{{standard input|save}}}";

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );
	}

	/**
	 * Saves $formDef/$page through pfautoedit with $requestOptions and returns the page text.
	 *
	 * @param string $formDef
	 * @param string $page Wikitext of the existing target page
	 * @param array $requestOptions
	 * @param bool $viaFormHtml Force the formHTML() + HtmlFormDataExtractor route
	 * @return string
	 */
	private function savedText( string $formDef, string $page, array $requestOptions, bool $viaFormHtml ): string {
		static $counter = 0;
		$counter++;
		$formName = "AEParityForm$counter";
		$targetName = "AEParityTarget$counter";
		$this->insertPage( Title::makeTitle( PF_NS_FORM, $formName ), $formDef );
		$this->insertPage( $targetName, $page );

		if ( $viaFormHtml ) {
			$requestOptions['AEParityForceFormHtml'] = [ 'x+' => '' ];
		}
		$testUser = $this->getMutableTestUser();
		$mainContext = RequestContext::getMain();
		$originalTitle = $mainContext->getTitle();
		$originalUser = $mainContext->getUser();
		try {
			$this->doApiRequest(
				array_merge( [
					'action' => 'pfautoedit',
					'form' => $formName,
					'target' => $targetName,
					'wpSave' => '1',
					'wpEditToken' => $testUser->getUser()->getEditToken(),
				], $requestOptions ),
				null, true, $testUser->getAuthority()
			);
		} finally {
			$mainContext->setTitle( $originalTitle );
			$mainContext->setUser( $originalUser );
		}
		return $this->getExistingTestPage( $targetName )->getContent()->getText();
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: array}> form definition, page, request
	 */
	private static function cases(): array {
		$s = self::SAVE_INPUTS;
		return [
			'single template, one field changed' => [
				"{{{for template|AEParityA}}}\n{{{field|a}}}\n{{{field|b}}}\n{{{end template}}}\n$s",
				"{{AEParityA\n|a=1\n|b=2\n}}\n",
				[ 'AEParityA' => [ 'a' => '9' ] ],
			],
			'mapping template, raw key submitted' => [
				"{{{for template|AEParityMap}}}\n"
					. "{{{field|m|input type=dropdown|values=a,b,c|mapping template=AEParityMapTpl}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityMap\n|m=b\n}}\n",
				[ 'AEParityMap' => [ 'm' => 'c' ] ],
			],
			'mapping template, field untouched' => [
				"{{{for template|AEParityMap2}}}\n"
					. "{{{field|m|input type=dropdown|values=a,b,c|mapping template=AEParityMapTpl}}}\n"
					. "{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParityMap2\n|m=b\n|o=1\n}}\n",
				[ 'AEParityMap2' => [ 'o' => '2' ] ],
			],
			'list field untouched' => [
				"{{{for template|AEParityList}}}\n"
					. "{{{field|l|list|delimiter=;|input type=tokens|values=a,b,c}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityList\n|l=a;b\n|o=1\n}}\n",
				[ 'AEParityList' => [ 'o' => '2' ] ],
			],
			'date field untouched' => [
				"{{{for template|AEParityDate}}}\n{{{field|d|input type=date}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDate\n|d=2026-10-06\n|o=1\n}}\n",
				[ 'AEParityDate' => [ 'o' => '2' ] ],
			],
			'datetime field untouched' => [
				"{{{for template|AEParityDateTime}}}\n{{{field|d|input type=datetime}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDateTime\n|d=2026-10-06 12:30:00\n|o=1\n}}\n",
				[ 'AEParityDateTime' => [ 'o' => '2' ] ],
			],
			'hidden field untouched' => [
				"{{{for template|AEParityHidden}}}\n{{{field|a|hidden}}}\n{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParityHidden\n|a=h\n|o=1\n}}\n",
				[ 'AEParityHidden' => [ 'o' => '2' ] ],
			],
			'default value does not overwrite the page value' => [
				"{{{for template|AEParityDefault}}}\n{{{field|a|default=DEF}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDefault\n|a=real\n|o=1\n}}\n",
				[ 'AEParityDefault' => [ 'o' => '2' ] ],
			],
			'template in the form but not on the page' => [
				"{{{for template|AEParityX}}}\n{{{field|a}}}\n{{{end template}}}\n"
					. "{{{for template|AEParityY}}}\n{{{field|b}}}\n{{{end template}}}\n$s",
				"{{AEParityX\n|a=1\n}}\n",
				[ 'AEParityY' => [ 'b' => '5' ] ],
			],
			'template on the page but not in the form is kept' => [
				"{{{for template|AEParityZ}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityZ\n|a=1\n}}\n{{AEParityNotInForm|q=1}}\n",
				[ 'AEParityZ' => [ 'a' => '2' ] ],
			],
			'template name with a space' => [
				"{{{for template|AEParity Space}}}\n{{{field|a}}}\n{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParity Space\n|a=1\n|o=1\n}}\n",
				[ 'AEParity_Space' => [ 'o' => '2' ] ],
			],
		];
	}

	/**
	 * Cases in which the two routes are known to produce different pages, with a short note.
	 * The fast path (first column of the result) is the one that is wrong in each of them.
	 *
	 * @return array<string, array{0: string, 1: string, 2: array, 3: string}>
	 */
	private static function knownGaps(): array {
		$s = self::SAVE_INPUTS;
		return [
			'template parameter not in the form is lost' => [
				"{{{for template|AEParityUnhandled}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityUnhandled\n|a=1\n|legacy=keep\n}}\n",
				[ 'AEParityUnhandled' => [ 'a' => '2' ] ],
				'parameters of a template call that the form does not define are dropped'
					. ' (formHTML() keeps them through the "_unhandled_" fields)',
			],
			'embedded template inside a "holds template" field is lost' => [
				"{{{for template|AEParityOuter}}}\n{{{field|f|holds template}}}\n{{{end template}}}\n"
					. "{{{for template|AEParityInner|multiple|embed in field=AEParityOuter[f]}}}\n"
					. "{{{field|x}}}\n{{{end template}}}\n$s",
				"{{AEParityOuter\n|f={{AEParityInner|x=1}}\n}}\n",
				[ 'pf_free_text' => 'note' ],
				'the outer template and the template embedded in its field disappear'
					. ' (formHTML() re-reads the embedded call from the field value)',
			],
			'mapping template: submitted label is not mapped back to its value' => [
				"{{{for template|AEParityMapLabel}}}\n"
					. "{{{field|m|input type=dropdown|values=a,b,c|mapping template=AEParityMapTpl}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityMapLabel\n|m=b\n}}\n",
				[ 'AEParityMapLabel' => [ 'm' => 'Label c' ] ],
				'a label submitted for a mapped field is stored as the label, not as its value'
					. ' (formHTML() maps it back through the "map_field" marker)',
			],
		];
	}

	private function insertMappingTemplate(): void {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'AEParityMapTpl' ), 'Label {{{1}}}' );
	}

	public static function provideParityCases(): iterable {
		foreach ( self::cases() as $label => $case ) {
			yield $label => $case;
		}
	}

	public static function provideKnownGaps(): iterable {
		foreach ( self::knownGaps() as $label => $case ) {
			yield $label => $case;
		}
	}

	/**
	 * @dataProvider provideParityCases
	 */
	public function testBothRoutesSaveTheSamePage( string $formDef, string $page, array $request ): void {
		$this->insertMappingTemplate();

		$direct = $this->savedText( $formDef, $page, $request, false );
		$viaFormHtml = $this->savedText( $formDef, $page, $request, true );

		$this->assertSame( $viaFormHtml, $direct );
	}

	/**
	 * @dataProvider provideKnownGaps
	 */
	public function testKnownGap( string $formDef, string $page, array $request, string $note ): void {
		$this->insertMappingTemplate();

		$direct = $this->savedText( $formDef, $page, $request, false );
		$viaFormHtml = $this->savedText( $formDef, $page, $request, true );

		if ( $direct === $viaFormHtml ) {
			$this->fail( 'The routes now agree: remove this case from knownGaps(). ' . $note );
		}
		$this->markTestIncomplete(
			"Known gap in the direct route: $note.\n  direct:      " . json_encode( $direct )
			. "\n  formHTML(): " . json_encode( $viaFormHtml )
		);
	}
}
