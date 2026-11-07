<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\MediaWikiServices;
use Parser;

/**
 * Adds the ResourceLoader modules that the form inputs and the form JavaScript need to the output.
 *
 * @ingroup PF
 */
class FormAssets {

	/**
	 * Includes the necessary ResourceLoader modules for the form
	 * to display and work correctly.
	 *
	 * Accepts an optional Parser instance, or uses $wgOut if omitted.
	 * @param Parser|null $parser
	 */
	public static function addFormRLModules( $parser = null ) {
		global $wgOut, $wgPageFormsSimpleUpload;

		// Handling depends on whether or not this form is embedded
		// in another page.
		if ( !$parser ) {
			$wgOut->addMeta( 'robots', 'noindex,nofollow' );
			$output = $wgOut;
		} else {
			$output = $parser->getOutput();
		}

		$mainModules = [
			'ext.pageforms.main',
			'ext.pageforms.submit',
			'ext.smw.tooltips',
			// @TODO - the inclusion of modules for specific
			// form inputs is wasteful, and should be removed -
			// it should only be done as needed for each input.
			// Unfortunately the use of multiple-instance
			// templates makes that tricky (every form input needs
			// to re-apply the JS on a new instance) - it can be
			// done via JS hooks, but it hasn't been done yet.
			'ext.pageforms.jstree',
			'ext.pageforms.imagepreview',
			'ext.pageforms.autogrow',
			'ext.pageforms.checkboxes',
			'ext.pageforms.select2',
			'ext.pageforms.rating',
			'ext.pageforms.fancybox',
			'ext.pageforms.fullcalendar',
			'jquery.makeCollapsible'
		];

		$mainModuleStyles = [
			'ext.pageforms.main.styles',
			'ext.pageforms.submit.styles',
			"ext.pageforms.checkboxes.styles",
			'ext.pageforms.select2.styles',
			'ext.pageforms.rating.styles',
			'ext.pageforms.fancybox.styles',
			"ext.pageforms.forminput.styles"
		];

		if ( $wgPageFormsSimpleUpload ) {
			$mainModules[] = 'ext.pageforms.simpleupload';
		}

		$output->addModules( $mainModules );
		$output->addModuleStyles( $mainModuleStyles );

		$otherModules = [];
		MediaWikiServices::getInstance()->getHookContainer()->run( 'PageForms::AddRLModules', [ &$otherModules ] );
		// @phan-suppress-next-line PhanEmptyForeach -- hook populates $otherModules via reference
		foreach ( $otherModules as $rlModule ) {
			$output->addModules( $rlModule );
		}
	}

}
