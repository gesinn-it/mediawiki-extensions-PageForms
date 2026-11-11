<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Hooks;

use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;

/**
 * Hook handlers that register Page Forms' namespaces, parser functions and
 * ResourceLoader modules.
 *
 * @ingroup PF
 */
class RegistrationHooks implements
	CanonicalNamespacesHook,
	ParserFirstCallInitHook,
	ResourceLoaderRegisterModulesHook
{

	private ExtensionRegistry $extensionRegistry;

	public function __construct( ?ExtensionRegistry $extensionRegistry = null ) {
		$this->extensionRegistry = $extensionRegistry ?? ExtensionRegistry::getInstance();
	}

	/**
	 * ResourceLoaderRegisterModules hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param \MediaWiki\ResourceLoader\ResourceLoader $resourceLoader
	 */
	public function onResourceLoaderRegisterModules( $resourceLoader ): void {
		$mapsModuleAttrs = [
			// The extension's root directory; apparently __DIR__-relative paths
			// resolved differently across installations, so set it explicitly.
			'localBasePath' => dirname( __DIR__, 2 ),
			'remoteExtPath' => 'PageForms',
			'dependencies' => [ 'oojs-ui.styles.icons-location' ]
		];

		if ( $this->extensionRegistry->isLoaded( 'OpenLayers' ) ) {
			$mapsModuleAttrs['scripts'] = '/libs/PF_maps.offline.js';
			$mapsModuleAttrs['dependencies'][] = 'ext.openlayers.main';
		} else {
			$mapsModuleAttrs['scripts'] = '/libs/PF_maps.js';
		}

		$resourceLoader->register( [ 'ext.pageforms.maps' => $mapsModuleAttrs ] );
	}

	/**
	 * Register the namespaces for Page Forms.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 *
	 * @since 2.4.1
	 *
	 * @param array &$list
	 * @return bool Always true
	 */
	public function onCanonicalNamespaces( &$list ) {
		global $wgNamespacesWithSubpages;

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
			define( 'PF_NS_FORM_TALK', 107 );
		}

		$list[PF_NS_FORM] = 'Form';
		$list[PF_NS_FORM_TALK] = 'Form_talk';

		// Support subpages only for talk pages by default
		$wgNamespacesWithSubpages[PF_NS_FORM_TALK] = true;

		return true;
	}

	/**
	 * @param Parser $parser
	 * @return bool Always true
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'default_form', [ 'PFDefaultForm', 'run' ] );
		$parser->setFunctionHook( 'forminput', [ 'PFFormInputParserFunction', 'run' ] );
		$parser->setFunctionHook( 'formlink', [ 'PFFormLink', 'run' ] );
		$parser->setFunctionHook( 'formredlink', [ 'PFFormRedLink', 'run' ] );
		$parser->setFunctionHook( 'queryformlink', [ 'PFQueryFormLink', 'run' ] );
		$parser->setFunctionHook( 'arraymap', [ 'PFArrayMap', 'run' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'arraymaptemplate', [ 'PFArrayMapTemplate', 'run' ], Parser::SFH_OBJECT_ARGS );

		$parser->setFunctionHook( 'autoedit', [ 'PFAutoEdit', 'run' ] );
		$parser->setFunctionHook( 'autoedit_rating', [ 'PFAutoEditRating', 'run' ] );
		$parser->setFunctionHook( 'template_params', [ 'PFTemplateParams', 'run' ] );
		$parser->setFunctionHook( 'template_display', [ 'PFTemplateDisplay', 'run' ], Parser::SFH_OBJECT_ARGS );

		return true;
	}

}
