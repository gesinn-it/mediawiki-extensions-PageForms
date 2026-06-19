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

// Set at file scope so MW 1.39+ picks it up before any service resets.
// setUp() re-applies it for MW 1.35 + SMW 4.2.0 where resetGlobalInstance()
// wipes $wgExtraNamespaces between tests.
$GLOBALS['wgExtraNamespaces'][TEST_NAMESPACE] = 'Test_Namespace';

/**
 * @group PF
 * @group SMWExtension
 * @group Database
 *
 */
class JsonTestCaseScriptRunnerTest extends JSONScriptTestCaseRunnerTest {

	protected function setUp(): void {
		parent::setUp();

		// Re-register the namespace after parent::setUp() because SMW 4.2.0's
		// DatabaseTestCase::setUp() calls MediaWikiServices::resetGlobalInstance()
		// which reloads MW globals and wipes any file-scope $wgExtraNamespaces entries.
		// On SMW 5.x/7.x this is a no-op (the global survives the reset).
		// NamespaceManager::clear() drops any cached namespace maps so the next
		// lookup reads the updated $wgExtraNamespaces.
		$GLOBALS['wgExtraNamespaces'][TEST_NAMESPACE] = 'Test_Namespace';
		\SMW\NamespaceManager::clear();
		// On MW 1.39+ NamespaceInfo is a service; force it to rebuild from the
		// updated $wgExtraNamespaces. Not available on SMW 4.2.0 / MW 1.35.
		if ( method_exists( MediaWikiServices::getInstance(), 'resetServiceForTesting' ) ) {
			MediaWikiServices::getInstance()->resetServiceForTesting( 'NamespaceInfo' );
		}

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
