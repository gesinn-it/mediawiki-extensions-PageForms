<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Hooks;

use ALItem;
use ALRow;
use ALSection;

/**
 * Handler for the AdminLinks hook of the Admin Links extension. Registered
 * by Setup::initialize() because it must run after SMW's own handler.
 *
 * @ingroup PF
 */
class AdminLinksHooks {

	/**
	 * @param \ALTree &$admin_links_tree
	 * @return bool
	 */
	public function onAdminLinks( &$admin_links_tree ) {
		$data_structure_label = wfMessage( 'smw_adminlinks_datastructure' )->text();
		$data_structure_section = $admin_links_tree->getSection( $data_structure_label );
		if ( $data_structure_section === null ) {
			$data_structure_section = new ALSection( wfMessage( 'pf-adminlinks-datastructure' )->text() );

			// If we are here, it most likely means that SMW is
			// not installed. Still, we'll refer to everything as
			// SMW, to make the rest of the code more
			// straightforward.
			$smw_row = new ALRow( 'smw' );
			$smw_row->addItem( ALItem::newFromSpecialPage( 'Categories' ) );
			$data_structure_section->addRow( $smw_row );
			$smw_admin_row = new ALRow( 'smw_admin' );
			$data_structure_section->addRow( $smw_admin_row );

			// If SMW is not installed, don't bother with a "links
			// to the documentation" row - it would only have one
			// link.
			$admin_links_tree->addSection( $data_structure_section, wfMessage( 'adminlinks_browsesearch' )->text() );
		} else {
			$smw_row = $data_structure_section->getRow( 'smw' );
			$smw_admin_row = $data_structure_section->getRow( 'smw_admin' );
			$smw_docu_row = $data_structure_section->getRow( 'smw_docu' );
		}
		$smw_row->addItem( ALItem::newFromSpecialPage( 'Templates' ), 'Properties' );
		$smw_row->addItem( ALItem::newFromSpecialPage( 'Forms' ), 'SemanticStatistics' );
		$smw_row->addItem( ALItem::newFromSpecialPage( 'MultiPageEdit' ) );
		if ( isset( $smw_docu_row ) ) {
			$pf_name = wfMessage( 'specialpages-group-pf_group' )->text();
			$pf_docu_label = wfMessage( 'adminlinks_documentation', $pf_name )->text();
			$smw_docu_row->addItem( ALItem::newFromExternalLink(
				"https://www.mediawiki.org/wiki/Extension:Page_Forms", $pf_docu_label
			) );
		}

		return true;
	}

}
