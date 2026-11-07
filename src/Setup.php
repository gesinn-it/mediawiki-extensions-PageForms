<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use ExtensionRegistry;
use MediaWiki\Extension\PageForms\Hooks\AdminLinksHooks;
use MediaWiki\MediaWikiServices;

/**
 * Extension registration callback and delayed initialisation, both referenced
 * from extension.json (`callback` and `ExtensionFunctions`). The handlers for
 * individual hooks live in the `Hooks` namespace.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class Setup {

	/**
	 * Old global class name => current class name. Registered as `class_alias()`
	 * shims during the includes/ -> src/ PSR-4 migration. See
	 * docs/developer/psr4-migration.adoc.
	 */
	public const CLASS_ALIASES = [
		'PFAutoeditAPI' => AutoeditApi::class,
		'PFAutocompleteAPI' => AutocompleteApi::class,
		'PFCreatePageJob' => CreatePageJob::class,
		'PFForm' => Form::class,
		'PFFormCache' => FormCache::class,
		'PFFormEditAction' => FormEditAction::class,
		'PFFormField' => FormField::class,
		'PFFormLinker' => FormLinker::class,
		'PFFormPrinter' => FormPrinter::class,
		'PFFormUtils' => FormUtils::class,
		'PFHelperFormAction' => HelperFormAction::class,
		'PFHooks' => self::class,
		'PFPageSection' => PageSection::class,
		'PFTemplate' => Template::class,
		'PFTemplateField' => TemplateField::class,
		'PFTemplateInForm' => TemplateInForm::class,
		'PFValuesUtils' => ValuesUtils::class,
	];

	/**
	 * Extension registration callback.
	 *
	 * @return int|null 1 if Page Forms had already been loaded
	 */
	public static function registerExtension() {
		if ( defined( 'PF_VERSION' ) ) {
			// Do not load Page Forms more than once.
			return 1;
		}

		require_once __DIR__ . '/../includes/PF_CoreAliases.php';

		define( 'PF_VERSION', ExtensionRegistry::getInstance()->getAllThings()['PageForms']['version'] );

		// BC shims for classes moved to the MediaWiki\Extension\PageForms namespace.
		// Registered here so the old global names are resolvable before any hook fires.
		foreach ( self::CLASS_ALIASES as $alias => $class ) {
			class_alias( $class, $alias );
		}

		$GLOBALS['wgPageFormsIP'] = dirname( __DIR__ ) . '/../';

		// Allow for popup windows for file upload
		$GLOBALS['wgEditPageFrameOptions'] = 'SAMEORIGIN';

		// Necessary setting for SMW 1.9+
		$GLOBALS['smwgEnabledSpecialPage'][] = 'RunQuery';

		return null;
	}

	/**
	 * Delayed init that makes sure that MediaWiki is set up properly before
	 * we add our stuff.
	 */
	public static function initialize(): void {
		$GLOBALS['wgPageFormsScriptPath'] = $GLOBALS['wgExtensionAssetsPath'] . '/PageForms';

		// Admin Links hook needs to be called in a delayed way so that it
		// will always be called after SMW's Admin Links addition; as of
		// SMW 1.9, SMW delays calling all its hook functions. For that reason
		// it cannot be listed in extension.json.
		MediaWikiServices::getInstance()->getHookContainer()->register(
			'AdminLinks',
			[ new AdminLinksHooks(), 'onAdminLinks' ]
		);

		// This global variable is needed so that other
		// extensions can hook into it to add their own
		// input types.
		FormPrinterFactory::initialize();
	}

}
