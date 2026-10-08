<?php

use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\MediaWikiServices;
use OOUI\BlankTheme;

/**
 * What FormDefParser::readPageValues() reads as "the values a form's fields have on an existing
 * page", which is what the autoedit API uses for SAVE, PREVIEW, DIFF and FORMEDIT.
 *
 * The cases cover mapped, list, date, datetime, hidden and defaulted fields, parameters the form
 * does not define and embedded templates. They were first written to compare this reader with the
 * values read back out of the rendered form HTML, which the FORMEDIT branch no longer does.
 *
 * @covers \MediaWiki\Extension\PageForms\FormDefParser::readPageValues
 * @group PF
 * @group Database
 * @group medium
 */
class ReadPageValuesTest extends MediaWikiIntegrationTestCase {

	private const SAVE_INPUTS = "{{{standard input|free text}}}\n{{{standard input|save}}}";

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			} );
		parent::setUp();
	}

	/**
	 * @return array<string, array{0: string, 1: string}> form definition, page
	 */
	private static function cases(): array {
		$s = self::SAVE_INPUTS;
		return [
			'single template, one field changed' => [
				"{{{for template|AEParityA}}}\n{{{field|a}}}\n{{{field|b}}}\n{{{end template}}}\n$s",
				"{{AEParityA\n|a=1\n|b=2\n}}\n",
			],
			'mapping template, raw key submitted' => [
				"{{{for template|AEParityMap}}}\n"
					. "{{{field|m|input type=dropdown|values=a,b,c|mapping template=AEParityMapTpl}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityMap\n|m=b\n}}\n",
			],
			'mapping template, field untouched' => [
				"{{{for template|AEParityMap2}}}\n"
					. "{{{field|m|input type=dropdown|values=a,b,c|mapping template=AEParityMapTpl}}}\n"
					. "{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParityMap2\n|m=b\n|o=1\n}}\n",
			],
			'list field untouched' => [
				"{{{for template|AEParityList}}}\n"
					. "{{{field|l|list|delimiter=;|input type=tokens|values=a,b,c}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityList\n|l=a;b\n|o=1\n}}\n",
			],
			'date field untouched' => [
				"{{{for template|AEParityDate}}}\n{{{field|d|input type=date}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDate\n|d=2026-10-06\n|o=1\n}}\n",
			],
			'datetime field untouched' => [
				"{{{for template|AEParityDateTime}}}\n{{{field|d|input type=datetime}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDateTime\n|d=2026-10-06 12:30:00\n|o=1\n}}\n",
			],
			'hidden field untouched' => [
				"{{{for template|AEParityHidden}}}\n{{{field|a|hidden}}}\n{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParityHidden\n|a=h\n|o=1\n}}\n",
			],
			'default value does not overwrite the page value' => [
				"{{{for template|AEParityDefault}}}\n{{{field|a|default=DEF}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n$s",
				"{{AEParityDefault\n|a=real\n|o=1\n}}\n",
			],
			'template parameter the form does not define is kept' => [
				"{{{for template|AEParityUnhandled}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityUnhandled\n|a=1\n|legacy=keep\n}}\n",
			],
			'template parameter the form does not define, positional and named' => [
				"{{{for template|AEParityUnhandled2}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityUnhandled2|a=1|legacy=keep|positional}}\n",
			],
			'embedded template in a holds-template field' => [
				"{{{for template|AEParityHolds}}}\n{{{field|Items|holds template}}}\n{{{field|o}}}\n"
					. "{{{end template}}}\n"
					. "{{{for template|AEParityEmb|multiple|embed in field=AEParityHolds[Items]}}}\n"
					. "{{{field|Foo}}}\n{{{end template}}}\n$s",
				"{{AEParityHolds\n|Items={{AEParityEmb|Foo=Bar}}{{AEParityEmb|Foo=Baz}}\n|o=1\n}}\n",
			],
			'template in the form but not on the page' => [
				"{{{for template|AEParityX}}}\n{{{field|a}}}\n{{{end template}}}\n"
					. "{{{for template|AEParityY}}}\n{{{field|b}}}\n{{{end template}}}\n$s",
				"{{AEParityX\n|a=1\n}}\n",
			],
			'template on the page but not in the form is kept' => [
				"{{{for template|AEParityZ}}}\n{{{field|a}}}\n{{{end template}}}\n$s",
				"{{AEParityZ\n|a=1\n}}\n{{AEParityNotInForm|q=1}}\n",
			],
			'template name with a space' => [
				"{{{for template|AEParity Space}}}\n{{{field|a}}}\n{{{field|o}}}\n{{{end template}}}\n$s",
				"{{AEParity Space\n|a=1\n|o=1\n}}\n",
			],
		];
	}

	/**
	 * What readPageValues() reads for each case, as the options the autoedit API merges the request into.
	 *
	 * @return array<string, array>
	 */
	private static function expectedValues(): array {
		return [
			'single template, one field changed' => [
				'AEParityA' => [
					'a' => '1',
					'b' => '2',
				],
			],
			'mapping template, raw key submitted' => [
				'AEParityMap' => [
					'm' => 'b',
				],
			],
			'mapping template, field untouched' => [
				'AEParityMap2' => [
					'm' => 'b',
					'o' => '1',
				],
			],
			'list field untouched' => [
				'AEParityList' => [
					'l' => 'a;b',
					'o' => '1',
				],
			],
			'date field untouched' => [
				'AEParityDate' => [
					'd' => '2026-10-06',
					'o' => '1',
				],
			],
			'datetime field untouched' => [
				'AEParityDateTime' => [
					'd' => '2026-10-06 12:30:00',
					'o' => '1',
				],
			],
			'hidden field untouched' => [
				'AEParityHidden' => [
					'a' => 'h',
					'o' => '1',
				],
			],
			'default value does not overwrite the page value' => [
				'AEParityDefault' => [
					'a' => 'real',
					'o' => '1',
				],
			],
			'template parameter the form does not define is kept' => [
				'AEParityUnhandled' => [
					'a' => '1',
				],
				'_unhandled_AEParityUnhandled_legacy' => 'keep',
			],
			'template parameter the form does not define, positional and named' => [
				'AEParityUnhandled2' => [
					'a' => '1',
				],
				'_unhandled_AEParityUnhandled2_legacy' => 'keep',
			],
			'embedded template in a holds-template field' => [
				'AEParityHolds' => [
					'Items' => '{{AEParityEmb|Foo=Bar}}{{AEParityEmb|Foo=Baz}}',
					'o' => '1',
				],
				'AEParityEmb' => [
					'0a' => [
						'Foo' => 'Bar',
					],
					'1a' => [
						'Foo' => 'Baz',
					],
				],
			],
			'template in the form but not on the page' => [
				'AEParityX' => [
					'a' => '1',
				],
			],
			'template on the page but not in the form is kept' => [
				'AEParityZ' => [
					'a' => '1',
				],
				'pf_free_text' => '{{AEParityNotInForm|q=1}}',
			],
			'template name with a space' => [
				'AEParity_Space' => [
					'a' => '1',
					'o' => '1',
				],
			],
		];
	}

	public static function provideCases(): iterable {
		$expected = self::expectedValues();
		foreach ( self::cases() as $label => [ $formDef, $page ] ) {
			yield $label => [ $formDef, $page, $expected[$label] ];
		}
	}

	/**
	 * @dataProvider provideCases
	 */
	public function testReadPageValues( string $formDef, string $page, array $expected ): void {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'AEParityMapTpl' ), 'Label {{{1}}}' );

		$values = ( new FormPrinter() )->readPageValues( $formDef, $page )->toOptions();

		$this->assertEquals( $expected, $values );
	}
}
