<?php

use MediaWiki\Extension\PageForms\FormLinker;
use MediaWiki\MediaWikiServices;

/**
 * Handles the formedit action.
 *
 * @author Yaron Koren
 * @author Stephan Gambke
 * @ingroup PF
 */
class PFFormEditAction extends Action {

	/**
	 * Return the name of the action this object responds to
	 * @return string lowercase
	 */
	public function getName() {
		return 'formedit';
	}

	/**
	 * The main action entry point.  Do all output for display and send it to the context
	 * output. Do not use globals $wgOut, $wgRequest, etc, in implementations; use
	 * $this->getOutput(), etc.
	 * @throws ErrorPageError
	 * @return bool
	 */
	public function show() {
		return self::displayForm( $this, $this->getArticle() );
	}

	/**
	 * Execute the action in a silent fashion: do not display anything or release any errors.
	 * @return bool whether execution was successful
	 */
	public function execute() {
		return true;
	}

	/**
	 * Adds an "action" (i.e., a tab) to edit the current article with
	 * a form
	 * @param IContextSource $obj
	 * @param array &$links
	 * @return true
	 */
	public static function displayTab( $obj, &$links ) {
		$title = $obj->getTitle();
		$user = $obj->getUser();

		// Make sure that this is not a special page, and
		// that the user is allowed to edit it
		// - this function is almost never called on special pages,
		// but before SMW is fully initialized, it's called on
		// Special:SMWAdmin for some reason, which is why the
		// special-page check is there.
		if ( $title === null ||
			( $title->getNamespace() == NS_SPECIAL ) ) {
			return true;
		}

		$form_names = FormLinker::getDefaultFormsForPage( $title );
		if ( count( $form_names ) == 0 ) {
			return true;
		}

		global $wgPageFormsRenameEditTabs, $wgPageFormsRenameMainEditTab;

		$content_actions = &$links['views'];

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$user_can_edit = $permissionManager->userCan( 'edit', $user, $title );

		// Create the form edit tab, and apply whatever changes are
		// specified by the edit-tab global variables.
		if ( $wgPageFormsRenameEditTabs ) {
			$form_edit_tab_msg = $user_can_edit ? 'edit' : 'pf_viewform';
			if ( array_key_exists( 'edit', $content_actions ) ) {
				$msg = $user_can_edit ? 'pf_editsource' : 'viewsource';
				$content_actions['edit']['text'] = wfMessage( $msg )->text();
			}
		} else {
			if ( $user_can_edit ) {
				$form_edit_tab_msg = $title->exists() ? 'formedit' : 'pf_formcreate';
			} else {
				$form_edit_tab_msg = 'pf_viewform';
			}
			// Check for renaming of main edit tab only if
			// $wgPageFormsRenameEditTabs is off.
			if ( $wgPageFormsRenameMainEditTab ) {
				if ( array_key_exists( 'edit', $content_actions ) ) {
					$msg = $user_can_edit ? 'pf_editsource' : 'viewsource';
					$content_actions['edit']['text'] = wfMessage( $msg )->text();
				}
			}
		}

		$class_name = ( $obj->getRequest()->getVal( 'action' ) == 'formedit' ) ? 'selected' : '';
		$form_edit_tab = [
			'class' => $class_name,
			'text' => wfMessage( $form_edit_tab_msg )->text(),
			'href' => $title->getLocalURL( 'action=formedit' )
		];

		// Find the location of the 'edit' tab, and add 'edit
		// with form' right before it.
		// This is a "key-safe" splice - it preserves both the keys
		// and the values of the array, by editing them separately
		// and then rebuilding the array. Based on the example at
		// http://us2.php.net/manual/en/function.array-splice.php#31234
		$tab_keys = array_keys( $content_actions );
		$tab_values = array_values( $content_actions );
		$edit_tab_location = array_search( 'edit', $tab_keys );

		// If there's no 'edit' tab, look for the 'view source' tab
		// instead.
		if ( $edit_tab_location === false ) {
			$edit_tab_location = array_search( 'viewsource', $tab_keys );
		}

		// This should rarely happen, but if there was no edit *or*
		// view source tab, set the location index to -1, so the
		// tab shows up near the end.
		if ( $edit_tab_location === false ) {
			$edit_tab_location = -1;
		}
		array_splice( $tab_keys, $edit_tab_location, 0, 'formedit' );
		array_splice( $tab_values, $edit_tab_location, 0, [ $form_edit_tab ] );
		$content_actions = [];
		foreach ( $tab_keys as $i => $key ) {
			$content_actions[$key] = $tab_values[$i];
		}

		if ( !$obj->getUser()->isAllowed( 'viewedittab' ) ) {
			// The tab can have either of these two actions.
			unset( $content_actions['edit'] );
			unset( $content_actions['viewsource'] );
		}

		// always return true, in order not to stop MW's hook processing!
		return true;
	}

	public static function displayFormChooser( $output, $title ) {
		$output->addModules( 'ext.pageforms.main' );
		$output->addModuleStyles( 'ext.pageforms.main.styles' );

		$targetName = $title->getPrefixedText();
		$output->setPageTitle( wfMessage( "creating", $targetName )->text() );

		try {
			$formNames = PFUtils::getAllForms();
		} catch ( MWException $e ) {
			$output->addHTML( Html::element( 'div', [ 'class' => 'error' ], $e->getMessage() ) );
			return;
		}

		$pagesPerForm = self::getNumPagesPerForm();
		[ 'main' => $mainForms, 'other' => $otherForms ] = self::classifyForms( $formNames, $pagesPerForm );

		$fe = PFUtils::getSpecialPage( 'FormEdit' );
		$hasMain = count( $mainForms ) > 0;
		$hasOther = count( $otherForms ) > 0;

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$noFormLink = $linkRenderer->makeKnownLink(
			$title,
			new HtmlArmor( wfMessage( 'pf-formedit-donotuseform' )->escaped() ),
			[],
			[ 'action' => 'edit', 'redlink' => true ]
		);

		$templateParser = new TemplateParser( __DIR__ . '/../templates' );
		$output->addHTML( $templateParser->processTemplate( 'FormChooser', [
			'intro'          => wfMessage( 'pf-formedit-selectform' )->text(),
			'hasMainForms'   => $hasMain,
			'mainFormsLabel' => ( $hasMain && $hasOther ) ? wfMessage( 'pf-formedit-mainforms' )->text() : '',
			'mainForms'      => self::buildFormLinkData( $mainForms, $targetName, $fe ),
			'hasOtherForms'  => $hasOther,
			'otherFormsLabel' => ( $hasMain && $hasOther ) ? wfMessage( 'pf-formedit-otherforms' )->text() : '',
			'otherForms'     => self::buildFormLinkData( $otherForms, $targetName, $fe ),
			'noFormLink'     => $noFormLink,
		] ) );
	}

	/**
	 * Classify forms into "main" and "other" groups.
	 *
	 * When $wgPageFormsMainForms is set, those forms are used as main forms
	 * directly (unknown form names are silently ignored). Otherwise, the top
	 * N forms by page count are used, where N is $wgPageFormsMainFormsLimit.
	 *
	 * @param string[] $allFormNames
	 * @param int[] $pagesPerForm form name => page count, ordered by count DESC
	 * @return array{main: string[], other: string[]}
	 */
	private static function classifyForms( array $allFormNames, array $pagesPerForm ): array {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$mainForms = $config->get( 'PageFormsMainForms' );

		if ( $mainForms ) {
			$mainForms = array_values( array_intersect( $mainForms, $allFormNames ) );
		} else {
			$limit = $config->get( 'PageFormsMainFormsLimit' );
			$mainForms = array_slice( array_keys( $pagesPerForm ), 0, $limit );
		}

		$otherForms = array_values( array_diff( $allFormNames, $mainForms ) );
		return [ 'main' => $mainForms, 'other' => $otherForms ];
	}

	/**
	 * Find the number of pages on the wiki that use each form.
	 *
	 * Counts both category-based and namespace-based #default_form assignments
	 * and merges the results. Individual-page assignments are not counted.
	 *
	 * @return int[] form name => page count, sorted by count DESC
	 */
	private static function getNumPagesPerForm(): array {
		$pagesPerForm = self::getNumPagesPerFormFromCategories();

		foreach ( self::getNumPagesPerFormFromNamespaces() as $formName => $count ) {
			$pagesPerForm[$formName] = ( $pagesPerForm[$formName] ?? 0 ) + $count;
		}

		arsort( $pagesPerForm );
		return $pagesPerForm;
	}

	/**
	 * Count pages per form via category-based #default_form assignments.
	 *
	 * Sums cat_pages for all categories whose category page carries a
	 * PFDefaultForm / SFDefaultForm page property.
	 *
	 * @return int[]
	 */
	private static function getNumPagesPerFormFromCategories(): array {
		$dbr = PFUtils::getReplicaDB();
		$res = $dbr->select(
			[ 'category', 'page', 'page_props' ],
			[ 'pp_value', 'SUM(cat_pages) AS total_pages' ],
			[
				// Keep backward compatibility with
				// the page property name for
				// Semantic Forms.
				'pp_propname' => [ 'PFDefaultForm', 'SFDefaultForm' ]
			],
			__METHOD__,
			[
				'GROUP BY' => 'pp_value',
				'ORDER BY' => 'total_pages DESC',
				'LIMIT' => 100
			],
			[
				'page' => [ 'JOIN', 'cat_title = page_title' ],
				'page_props' => [ 'JOIN', 'page_id = pp_page' ]
			]
		);

		$pagesPerForm = [];
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			$pagesPerForm[$row['pp_value']] = (int)$row['total_pages'];
		}
		return $pagesPerForm;
	}

	/**
	 * Count pages per form via namespace-based #default_form assignments.
	 *
	 * Namespace default forms are stored as PFDefaultForm page properties on
	 * Project-namespace pages named after each namespace (e.g. "Project:User").
	 * NS_MAIN uses the localised "pf_blank_namespace" message as its page title.
	 *
	 * @return int[]
	 */
	private static function getNumPagesPerFormFromNamespaces(): array {
		$dbr = PFUtils::getReplicaDB();

		// Fetch all Project-namespace pages that carry a default-form property.
		// Both tables are aliased so that MW's SQLPlatform resolves qualified
		// column names consistently across all supported MW versions (1.39+).
		$res = $dbr->select(
			[ 'ns_page' => 'page', 'pp' => 'page_props' ],
			[ 'ns_page.page_title AS ns_title', 'pp.pp_value AS form_name' ],
			[
				'ns_page.page_namespace' => NS_PROJECT,
				'pp.pp_propname' => [ 'PFDefaultForm', 'SFDefaultForm' ],
			],
			__METHOD__,
			[],
			[
				'pp' => [ 'JOIN', 'pp.pp_page = ns_page.page_id' ],
			]
		);

		// Build label→namespace index reverse map once.
		$namespacesByLabel = array_flip( PFUtils::getContLang()->getNamespaces() );
		$blankLabel = wfMessage( 'pf_blank_namespace' )->inContentLanguage()->text();

		$pagesPerForm = [];
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			$nsTitle = str_replace( '_', ' ', $row['ns_title'] );
			$formName = $row['form_name'];

			if ( $nsTitle === $blankLabel ) {
				$namespaceIndex = NS_MAIN;
			} elseif ( isset( $namespacesByLabel[$nsTitle] ) ) {
				$namespaceIndex = $namespacesByLabel[$nsTitle];
			} else {
				continue;
			}

			$pageCount = (int)$dbr->selectField(
				'page',
				'COUNT(*)',
				[ 'page_namespace' => $namespaceIndex, 'page_is_redirect' => 0 ],
				__METHOD__
			);

			$pagesPerForm[$formName] = ( $pagesPerForm[$formName] ?? 0 ) + $pageCount;
		}

		return $pagesPerForm;
	}

	/**
	 * @param string[] $formNames
	 * @param string $targetName
	 * @param SpecialPage $fe
	 * @return array{href: string, name: string}[]
	 */
	private static function buildFormLinkData( array $formNames, string $targetName, $fe ): array {
		$items = [];
		foreach ( $formNames as $i => $formName ) {
			// Special handling for forms whose name contains a slash.
			if ( str_contains( $formName, '/' ) ) {
				$href = $fe->getPageTitle()->getLocalURL( [ 'form' => $formName, 'target' => $targetName ] );
			} else {
				$href = $fe->getPageTitle( "$formName/$targetName" )->getLocalURL();
			}
			$items[] = [ 'href' => $href, 'name' => $formName, 'separator' => $i > 0 ];
		}
		return $items;
	}

	/**
	 * The function called if we're in index.php (as opposed to one of the
	 * special pages)
	 * @param Action $action
	 * @param Article $article
	 * @return bool
	 */
	public static function displayForm( $action, $article ) {
		$output = $action->getOutput();
		$title = $article->getTitle();
		$form_names = FormLinker::getDefaultFormsForPage( $title );
		if ( count( $form_names ) == 0 ) {
			// If no form is set, display an interface to let the
			// user choose out of all the forms defined on this wiki
			// (or none at all).
			self::displayFormChooser( $output, $title );
			return true;
		}

		if ( count( $form_names ) > 1 ) {
			$warning_text = "\t" . '<div class="warningbox">' .
				wfMessage( 'pf_formedit_morethanoneform' )->text() . "</div>\n";
			$output->addWikiTextAsInterface( $warning_text );
		}

		$form_name = $form_names[0];
		$page_name = $title->getPrefixedText();

		$pfFormEdit = new PFFormEdit();
		$pfFormEdit->printForm( $form_name, $page_name );

		return false;
	}
}
