<?php

use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\MediaWikiServices;
use OOUI\BlankTheme;

/**
 * Characterization (golden master) tests for FormPrinter::formHTML().
 *
 * Each case renders a form definition and compares everything formHTML() returns - the form
 * HTML, the generated page text, the page title, the generated page name, the resource loader
 * modules and the "query form at top" flag - with a snapshot in tests/phpunit/integration/golden/formhtml.
 * They pin the behaviour as it is, so that the structure of formHTML() can be changed without
 * changing what it produces. A difference is not necessarily a bug in the change, but it has to
 * be looked at.
 *
 * To record the snapshots again after an intended change, run the tests with the environment
 * variable PF_UPDATE_GOLDEN=1 and review the diff of the snapshot files.
 *
 * @covers \MediaWiki\Extension\PageForms\FormPrinter::formHTML
 * @group Database
 */
class FormHtmlCharacterizationTest extends MediaWikiIntegrationTestCase {

	private const GOLDEN_DIR = __DIR__ . '/../golden/formhtml';

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );

		// Same as in FormPrinterTest: skip the permission check by a hook.
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			}
		);

		// A FormPrinter bound to the current service container (see FormPrinterTest::setUp()).
		global $wgPageFormsFormPrinter;
		$wgPageFormsFormPrinter = new FormPrinter();

		parent::setUp();
	}

	/**
	 * @dataProvider provideCases
	 * @param array $case
	 */
	public function testFormHtmlOutputIsUnchanged( array $case ): void {
		global $wgPageFormsFormPrinter, $wgOut;

		// Query and embedded forms take the title of the page they are shown on from the request
		// context; set it in every case so that no case depends on the ones before it.
		$title = Title::makeTitle( NS_MAIN, 'PFCharPage' );
		$wgOut->getContext()->setTitle( $title );
		RequestContext::getMain()->setTitle( $title );
		$user = $this->getTestUser()->getUser();

		$request = $case['request'] !== null
			? new FauxRequest( $case['request'], $case['posted'] ?? true )
			: null;

		[ $formText, $pageText, $formPageTitle, $generatedPageName, $parserOutput, $queryFormAtTop ] =
			$wgPageFormsFormPrinter->formHTML(
				$case['form_def'],
				$case['submitted'] ?? false,
				$case['source_is_page'] ?? false,
				null,
				$case['existing'] ?? null,
				array_key_exists( 'page_name', $case ) ? $case['page_name'] : 'PFCharPage',
				$case['formula'] ?? null,
				$case['is_query'] ?? false,
				$case['is_embedded'] ?? false,
				$case['is_autocreate'] ?? false,
				$case['autocreate_query'] ?? [],
				$user,
				$request
			);

		$actual = [
			'form_text' => $this->lines( $formText ),
			'page_text' => $this->lines( $pageText ),
			'form_page_title' => $formPageTitle,
			'generated_page_name' => $generatedPageName,
			'modules' => $this->ownModules( $parserOutput?->getModules() ),
			'module_styles' => $this->ownModules( $parserOutput?->getModuleStyles() ),
			'query_form_at_top' => $queryFormAtTop,
		];

		$this->assertMatchesSnapshot( $case['name'], $actual );
	}

	/**
	 * @return array<string, array{0: array}>
	 */
	public static function provideCases(): array {
		$cases = [];
		foreach ( self::cases() as $name => $case ) {
			$cases[$name] = [ $case + [ 'name' => $name, 'request' => null ] ];
		}
		return $cases;
	}

	/**
	 * @return array<string, array>
	 */
	private static function cases(): array {
		$tpl = static fn ( string $name, string $options = '', string $fields = '' ): string =>
			"{{{for template|$name$options}}}\n$fields{{{end template}}}\n";
		$save = "{{{standard input|save}}}\n";
		$simple = "{{{field|Title}}}\n{{{field|Kind|input type=dropdown|values=A,B,C}}}\n"
			. "{{{field|Done|input type=checkbox}}}\n{{{field|Notes|input type=textarea|rows=3}}}\n";

		return [
			'single template, new page' => [
				'form_def' => $tpl( 'PFCharSingle', '', $simple ) . $save,
			],
			'single template, submitted' => [
				'form_def' => $tpl( 'PFCharSingle', '', $simple ) . $save,
				'submitted' => true,
				'request' => [ 'PFCharSingle' => [
					'Title' => 'Some title', 'Kind' => 'B', 'Done' => 'on', 'Notes' => "line1\nline2",
				] ],
			],
			'single template, query string values on a new page' => [
				'form_def' => $tpl( 'PFCharSingle', '', $simple ) . $save,
				'request' => [ 'PFCharSingle' => [ 'Title' => 'From query', 'Kind' => 'C' ] ],
				'posted' => false,
			],
			'single template, default values and mandatory, hidden and restricted fields' => [
				'form_def' => $tpl( 'PFCharArgs', '',
					"{{{field|Title|mandatory|default=Dflt|placeholder=Type here}}}\n"
					. "{{{field|Secret|hidden|default=abc}}}\n"
					. "{{{field|Locked|restricted=sysop|default=x}}}\n"
					. "{{{field|Mail|input type=text|size=20|maxlength=40|class=extra|label=E-mail|help=Address}}}\n"
				) . $save,
			],
			'single template, edit existing page with unhandled parameter' => [
				'form_def' => $tpl( 'PFCharSingle', '', $simple ) . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharSingle\n|Title=Existing\n|Kind=A\n|Done=Yes\n|Notes=n\n|Extra=kept}}\n",
			],
			'single template, edit existing page, submitted' => [
				'form_def' => $tpl( 'PFCharSingle', '', $simple ) . $save,
				'source_is_page' => true,
				'submitted' => true,
				'existing' => "{{PFCharSingle\n|Title=Existing\n|Kind=A\n|Extra=kept}}\n",
				'request' => [ 'PFCharSingle' => [ 'Title' => 'Changed', 'Kind' => 'B', 'Extra' => 'kept' ] ],
			],
			'two templates with intro and label' => [
				'form_def' => $tpl( 'PFCharFirst', '|label=First part|intro=Intro text', "{{{field|A}}}\n" )
					. "Between the templates\n"
					. $tpl( 'PFCharSecond', '', "{{{field|B}}}\n" ) . $save,
			],
			'multiple template, new page' => [
				'form_def' => $tpl( 'PFCharMulti', '|multiple|add button text=Add one|minimum instances=2',
					"{{{field|Name}}}\n{{{field|Qty|input type=text|size=4}}}\n" ) . $save,
			],
			'multiple template, edit existing page with two instances' => [
				'form_def' => $tpl( 'PFCharMulti', '|multiple', "{{{field|Name}}}\n{{{field|Qty}}}\n" ) . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharMulti|Name=One|Qty=1|Extra=e1}}\n{{PFCharMulti|Name=Two|Qty=2|Extra=e2}}\n",
			],
			'multiple template, submitted with three instances' => [
				'form_def' => $tpl( 'PFCharMulti', '|multiple', "{{{field|Name}}}\n{{{field|Qty}}}\n" ) . $save,
				'submitted' => true,
				'request' => [ 'PFCharMulti' => [
					'0a' => [ 'Name' => 'One', 'Qty' => '1' ],
					'1a' => [ 'Name' => 'Two', 'Qty' => '2' ],
					'2a' => [ 'Name' => 'Three', 'Qty' => '3' ],
				] ],
			],
			'multiple template with maximum instances and label' => [
				'form_def' => $tpl( 'PFCharMulti', '|multiple|maximum instances=3|label=Rows', "{{{field|Name}}}\n" )
					. $save,
			],
			'multiple template, display table' => [
				'form_def' => $tpl( 'PFCharTable', '|multiple|display=table', "{{{field|Name}}}\n{{{field|Qty}}}\n" )
					. $save,
				'source_is_page' => true,
				'existing' => "{{PFCharTable|Name=One|Qty=1}}\n{{PFCharTable|Name=Two|Qty=2}}\n",
			],
			'single template, display table' => [
				'form_def' => $tpl( 'PFCharTable', '|display=table', "{{{field|Name}}}\n{{{field|Qty}}}\n" ) . $save,
			],
			'multiple template, display spreadsheet' => [
				'form_def' => $tpl( 'PFCharSheet', '|multiple|display=spreadsheet|label=Items',
					"{{{field|Name}}}\n{{{field|Kind|input type=dropdown|values=A,B}}}\n" ) . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharSheet|Name=One|Kind=A}}\n{{PFCharSheet|Name=Two|Kind=B}}\n",
			],
			'multiple template, display calendar' => [
				'form_def' => $tpl( 'PFCharCal',
					'|multiple|display=calendar|event title field=Name|event date field=When',
					"{{{field|Name}}}\n{{{field|When|input type=date}}}\n" ) . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharCal|Name=Meeting|When=2026-10-06}}\n",
			],
			'sections, new page' => [
				'form_def' => "==First==\n{{{section|First|level=2|rows=4}}}\n"
					. "==Second==\n{{{section|Second|level=2|mandatory|hidden}}}\n" . $save,
			],
			'sections, existing page' => [
				'form_def' => "==First==\n{{{section|First|level=2}}}\n==Second==\n{{{section|Second|level=2}}}\n"
					. $save,
				'source_is_page' => true,
				'existing' => "==First==\ntext one\n==Second==\ntext two\n",
			],
			'sections, submitted' => [
				'form_def' => "==First==\n{{{section|First|level=2}}}\n==Second==\n{{{section|Second|level=2}}}\n"
					. $save,
				'submitted' => true,
				'request' => [ '_section' => [ 'First' => 'new one', 'Second' => 'new two' ] ],
			],
			'free text standard input, new page' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{standard input|free text|rows=5}}}\n" . $save,
			],
			'free text standard input, existing page' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{standard input|free text}}}\n" . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharSingle|Title=T}}\nFree text after the template\n",
			],
			'free text as field, hidden' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{field|free text|hidden}}}\n" . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharSingle|Title=T}}\nKept text\n",
			],
			'free text as field, with edit tools' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{field|free text|edittools}}}\n" . $save,
			],
			'free text, submitted' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{standard input|free text}}}\n" . $save,
				'submitted' => true,
				'request' => [ 'PFCharSingle' => [ 'Title' => 'T' ], 'pf_free_text' => 'Typed free text' ],
			],
			'query form' => [
				'form_def' => "{{{info|query form at top}}}\n"
					. $tpl( 'PFCharQuery', '', "{{{field|Title}}}\n{{{field|Kind|input type=dropdown|values=A,B}}}\n" )
					. "{{{standard input|run query}}}\n{{{standard input|save}}}\n",
				'is_query' => true,
				'page_name' => null,
			],
			'query form with query string values' => [
				'form_def' => $tpl( 'PFCharQuery', '', "{{{field|Title}}}\n" ) . "{{{standard input|run query}}}\n",
				'is_query' => true,
				'page_name' => null,
				'request' => [ 'PFCharQuery' => [ 'Title' => 'queried' ] ],
				'posted' => false,
			],
			'autocreate with query values' => [
				'form_def' => $tpl( 'PFCharSingle', '', $simple ) . $save,
				'is_autocreate' => true,
				'page_name' => null,
				'autocreate_query' => [ 'PFCharSingle' => [ 'Title' => 'Created', 'Kind' => 'B' ] ],
			],
			'page name formula, submitted' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n{{{field|Year}}}\n" ) . $save,
				'submitted' => true,
				'page_name' => null,
				'formula' => 'Report <PFCharSingle[Title]> <PFCharSingle[Year]>',
				'request' => [ 'PFCharSingle' => [ 'Title' => 'Alpha', 'Year' => '2026' ] ],
			],
			'info tag with create title and edit title' => [
				'form_def' => "{{{info|create title=Create a thing|edit title=Edit a thing"
					. "|page name=Thing <PFCharSingle[Title]>}}}\n"
					. $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" ) . $save,
			],
			'info tag with query title' => [
				'form_def' => "{{{info|query title=Search things|query form at top}}}\n"
					. $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" ) . "{{{standard input|run query}}}\n",
				'is_query' => true,
				'page_name' => null,
			],
			'embedded template with holds template and embed in field' => [
				'form_def' => $tpl( 'PFCharParent', '', "{{{field|Name}}}\n{{{field|Items|holds template}}}\n" )
					. $tpl( 'PFCharChild', '|multiple|embed in field=PFCharParent[Items]', "{{{field|Label}}}\n" )
					. $save,
			],
			'embedded template, edit existing page' => [
				'form_def' => $tpl( 'PFCharParent', '', "{{{field|Name}}}\n{{{field|Items|holds template}}}\n" )
					. $tpl( 'PFCharChild', '|multiple|embed in field=PFCharParent[Items]', "{{{field|Label}}}\n" )
					. $save,
				'source_is_page' => true,
				'existing' => "{{PFCharParent|Name=P|Items={{PFCharChild|Label=One}}{{PFCharChild|Label=Two}}}}\n",
			],
			'insertion point in existing page text, submitted' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{standard input|free text}}}\n" . $save,
				'source_is_page' => true,
				'submitted' => true,
				'existing' => "Intro text\n{{{insertionpoint}}}\nOutro text",
				'request' => [ 'PFCharSingle' => [ 'Title' => 'Inserted' ] ],
			],
			'custom standard inputs' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{standard input|summary}}}\n{{{standard input|minor edit}}}\n{{{standard input|watch}}}\n"
					. "{{{standard input|save}}}\n{{{standard input|preview}}}\n{{{standard input|changes}}}\n"
					. "{{{standard input|cancel}}}\n",
			],
			'standard inputs with labels' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" )
					. "{{{standard input|save|label=Store it}}}\n{{{standard input|summary|label=Why}}}\n",
			],
			'field with list, mapping and values from a template' => [
				'form_def' => $tpl( 'PFCharLists', '',
					"{{{field|Tags|list|input type=checkboxes|values=a,b,c}}}\n"
					. "{{{field|Pick|input type=radiobutton|values=x,y}}}\n"
					. "{{{field|Many|list|input type=tokens|values=p,q,r|delimiter=;}}}\n"
				) . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharLists|Tags=a, c|Pick=y|Many=p;r}}\n",
			],
			'field values with modifiers in the request' => [
				'form_def' => $tpl( 'PFCharMods', '', "{{{field|Tags|list}}}\n" ) . $save,
				'source_is_page' => true,
				'existing' => "{{PFCharMods|Tags=one, two}}\n",
				'request' => [ 'PFCharMods' => [ 'Tags+' => 'three' ] ],
				'posted' => false,
			],
			'unknown tag and plain wikitext between tags' => [
				'form_def' => "Plain '''wikitext''' and a [[link]].\n{{{unknown tag|x}}}\n"
					. $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" ) . $save,
			],
			'parser function and template call inside the form definition' => [
				'form_def' => "{{#if:1|Shown by a parser function}}\n"
					. $tpl( 'PFCharSingle', '', "{{{field|Title|default={{PAGENAME}}}}}\n" ) . $save,
			],
			'embedded form' => [
				'form_def' => $tpl( 'PFCharSingle', '', "{{{field|Title}}}\n" ) . $save,
				'is_embedded' => true,
			],
		];
	}

	/**
	 * Compare $actual with the snapshot of the case, or record the snapshot if asked to.
	 */
	private function assertMatchesSnapshot( string $name, array $actual ): void {
		$file = self::GOLDEN_DIR . '/' . preg_replace( '/[^A-Za-z0-9]+/', '-', $name ) . '.json';
		$json = json_encode( $actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";

		if ( getenv( 'PF_UPDATE_GOLDEN' ) ) {
			if ( !is_dir( self::GOLDEN_DIR ) ) {
				mkdir( self::GOLDEN_DIR, 0775, true );
			}
			file_put_contents( $file, $json );
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->assertFileExists( $file, "No snapshot for '$name'. Record it with PF_UPDATE_GOLDEN=1." );
		$this->assertSame( file_get_contents( $file ), $json, "The output of formHTML() changed for '$name'" );
	}

	/**
	 * The text as a list of lines (readable diffs), without the parts that differ between runs
	 * or between the MediaWiki versions of the CI matrix: generated ids, timestamps and the edit
	 * token, and the class lists of OOUI widgets.
	 *
	 * @return string[]|null
	 */
	private function lines( ?string $text ): ?array {
		if ( $text === null ) {
			return null;
		}
		$uuid = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/';
		$text = preg_replace( $uuid, '<uuid>', $text );
		// OOUI numbers its generated ids over the whole process, so they depend on the tests run before.
		$text = preg_replace( '/ooui-php-\d+/', 'ooui-php-<n>', $text );
		$text = preg_replace( '/value="\d{14}"/', 'value="<timestamp>"', $text );
		$text = preg_replace( '/value="[^"]*" name="wpEditToken"/', 'value="<token>" name="wpEditToken"', $text );
		$text = preg_replace_callback(
			'/class=([\'"])([^\'"]*\boo-ui-[^\'"]*)\1/',
			static fn ( array $m ): string => 'class=' . $m[1] . '<oo-ui>' . $m[1],
			$text
		);
		return explode( "\n", $text );
	}

	/**
	 * The resource loader modules that belong to this extension. Others come from other
	 * extensions (Semantic MediaWiki) or from the MediaWiki version and are not pinned here.
	 *
	 * @param string[]|null $modules
	 * @return string[]|null
	 */
	private function ownModules( ?array $modules ): ?array {
		if ( $modules === null ) {
			return null;
		}
		return array_values( array_filter(
			$modules,
			static fn ( string $module ): bool => str_starts_with( $module, 'ext.pageforms' )
		) );
	}
}
