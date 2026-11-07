<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use CommentStoreComment;
use Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use PFUtils;
use User;
use WikitextContent;

/**
 * Background job to create a new page, for use by the 'CreateClass' special
 * page.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class CreatePageJob extends Job {

	public function __construct( Title $title, array $params ) {
		parent::__construct( 'pageFormsCreatePage', $title, $params );
		$this->removeDuplicates = true;
	}

	/**
	 * Run a pageFormsCreatePage job
	 * @return bool success
	 */
	public function run() {
		$wikiPage = PFUtils::newWikiPageFromTitle( $this->title );
		$pageText = $this->params['page_text'];
		if ( array_key_exists( 'edit_summary', $this->params ) ) {
			$editSummary = $this->params['edit_summary'];
		} else {
			$editSummary = '';
		}
		$user = User::newFromId( $this->params['user_id'] );

		self::createOrModifyPage( $wikiPage, $pageText, $editSummary, $user );

		return true;
	}

	public static function createOrModifyPage( $wikiPage, $pageText, $editSummary, $user ) {
		$newContent = new WikitextContent( $pageText );

		// It's strange that doEditContent() doesn't automatically
		// attach the 'bot' flag when the user is a bot...
		// @TODO - is all this code still necessary?
		$flags = 0;
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( $permissionManager->userHasRight( $user, 'bot' ) ) {
			$flags = EDIT_FORCE_BOT;
		}

		$updater = $wikiPage->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $newContent );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( $editSummary ), $flags );
	}

}
