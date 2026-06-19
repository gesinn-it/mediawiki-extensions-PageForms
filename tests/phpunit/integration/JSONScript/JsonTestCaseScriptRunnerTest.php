<?php

namespace PF\Tests\Integration\JSONScript;

use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use Parser;
use PFArrayMap;
use PFArrayMapTemplate;
use PFAutoEdit;
use PFAutoEditRating;
use PFDefaultForm;
use PFFormInputParserFunction;
use PFFormLink;
use PFFormRedLink;
use PFQueryFormLink;
use PFTemplateDisplay;
use PFTemplateParams;
use SMW\Tests\Integration\JSONScript\JSONScriptTestCaseRunnerTest;

define( "TEST_NAMESPACE", 3000 );

/**
 * @group PF
 * @group SMWExtension
 * @group Database
 *
 */
class JsonTestCaseScriptRunnerTest extends JSONScriptTestCaseRunnerTest {

	/** @var array Saved Language object cache, restored in tearDown */
	private $savedLangObjCache = [];

	protected function setUp(): void {
		parent::setUp();

		// Ensure namespace 3000 is registered in the MW namespace system so that
		// {{FULLPAGENAME}} resolves to "Test_Namespace:…" instead of "Special:Badtitle/NS3000:…".
		// We cannot use setMwGlobals() here: on SMW 4.2.0 it is unavailable (DatabaseTestCase
		// bypasses MW test infrastructure), and on SMW 5.x its internal resetServices() call
		// crashes with ContainerDisabledException because SMW's setUp() leaves the service
		// container in a transitional state. Direct $GLOBALS mutation + Language cache flush
		// is the safe approach across all SMW versions.
		$this->savedLangObjCache = \Language::$mLangObjCache;
		$GLOBALS['wgExtraNamespaces'][TEST_NAMESPACE] = 'Test_Namespace';
		\Language::$mLangObjCache = [];
		\SMW\NamespaceManager::clear();

		// Register parser functions directly
		$parser = $this->getParser();
		$parser->setFunctionHook( 'default_form', [ PFDefaultForm::class, 'run' ] );
		$parser->setFunctionHook( 'forminput', [ PFFormInputParserFunction::class, 'run' ] );
		$parser->setFunctionHook( 'formlink', [ PFFormLink::class, 'run' ] );
		$parser->setFunctionHook( 'formredlink', [ PFFormRedLink::class, 'run' ] );
		$parser->setFunctionHook( 'queryformlink', [ PFQueryFormLink::class, 'run' ] );
		$parser->setFunctionHook( 'arraymap', [ PFArrayMap::class, 'run' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'arraymaptemplate', [ PFArrayMapTemplate::class, 'run' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'autoedit', [ PFAutoEdit::class, 'run' ] );
		$parser->setFunctionHook( 'autoedit_rating', [ PFAutoEditRating::class, 'run' ] );
		$parser->setFunctionHook( 'template_params', [ PFTemplateParams::class, 'run' ] );
		$parser->setFunctionHook( 'template_display', [ PFTemplateDisplay::class, 'run' ], Parser::SFH_OBJECT_ARGS );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wgExtraNamespaces'][TEST_NAMESPACE] );
		\Language::$mLangObjCache = $this->savedLangObjCache;
		parent::tearDown();
	}

	protected function getParser(): Parser {
		// Assuming you can retrieve a Parser instance here
		return MediaWikiServices::getInstance()->getParser();
	}

	protected function getTestCaseLocation(): string {
		return __DIR__ . '/TestCases';
	}

	protected function getPermittedSettings(): array {
		return array_merge( parent::getPermittedSettings(), [
			'wgPageFormsAutocompleteOnAllChars',
			'wgPageFormsCacheAutocompleteValues',
			'wgPageFormsMaxAutocompleteValues',
			'wgPageFormsMaxLocalAutocompleteValues',
			'wgPageFormsUseDisplayTitle',
			'wgAllowDisplayTitle',
			'wgRestrictDisplayTitle',
			'wgArticlePath',
			'wgPageForms24HourTime',
			'smwgEnabledDeferredUpdate',
		] );
	}

	protected function getDependencyDefinitions(): array {
		return [
			'DisplayTitle' => static function ( $val, &$reason ) {
				if ( !ExtensionRegistry::getInstance()->isLoaded( 'DisplayTitle' ) ) {
					$reason = "Dependency: DisplayTitle as requirement for the test is not available!";
					return false;
				}
				return true;
			}
		];
	}
}
