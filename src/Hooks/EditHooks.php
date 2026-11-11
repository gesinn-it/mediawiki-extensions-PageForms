<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Hooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\PageForms\FormAssets;
use MediaWiki\Extension\PageForms\FormLinker;
use MediaWiki\Extension\PageForms\FormPrinter;
use MediaWiki\Extension\PageForms\FormPrinterFactory;
use MediaWiki\Hook\EditPage__importFormDataHook;
use MediaWiki\Html\Html;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use StringUtils;
use WikiPage;

/**
 * Hook handlers that take part in editing and saving pages.
 *
 * @ingroup PF
 */
class EditHooks implements EditPage__importFormDataHook, PageSaveCompleteHook {

	/**
	 * Disable TinyMCE if this is a form definition page, or a form-editable page.
	 *
	 * @param Title $title The page Title object
	 * @return bool Whether or not to disable TinyMCE
	 */
	public function onTinyMCEDisable( $title ) {
		if ( $title->getNamespace() == PF_NS_FORM ) {
			return false;
		}

		$defaultForms = FormLinker::getDefaultFormsForPage( $title );
		if ( count( $defaultForms ) > 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Show a preview of a form definition below the edit box.
	 *
	 * @param EditPage $editpage
	 * @param WebRequest $request
	 * @return bool Always true
	 */
	// phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName -- MediaWiki maps "EditPage::importFormData" to this name
	public function onEditPage__importFormData( $editpage, $request ) {
		global $wgOut;

		wfDebug( __METHOD__ . ": enter.\n" );

		// Exit if we're not in preview mode.
		if ( !$editpage->preview ) {
			return true;
		}
		// Exit if we aren't in the "Form" namespace.
		if ( $editpage->getArticle()->getTitle()->getNamespace() != PF_NS_FORM ) {
			return true;
		}

		// Needed in case there are any OOUI-based input types in the form.
		$wgOut->enableOOUI();

		$previewNote = $wgOut->parseAsInterface( wfMessage( 'pf-preview-note' )->text() );
		// The "pfForm" ID is there so the form JS will be activated.
		$editpage->previewTextAfterContent .=
			Html::element( 'h2', [], wfMessage( 'pf-preview-header' )->text() ) . "\n" .
			'<div id="pfForm" class="previewnote" style="font-weight: bold">' . $previewNote . "</div>\n<hr />\n";

		$form_definition = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $editpage->textbox1 );
		$result = FormPrinterFactory::get()->render(
			$form_definition, false, false, null, null, "Page Forms form preview dummy title", null,
			false, false, false, [], null, $request
		);

		$wgOut->addParserOutputMetadata( $result->getParserOutput() );

		FormAssets::addFormRLModules();
		$editpage->previewTextAfterContent .=
			'<div style="margin-top: 15px">' . $result->getFormText() . "</div>";

		return true;
	}

	/**
	 * Called by the PageSaveComplete hook.
	 *
	 * Set a cookie after the page save so that a "Your edit was saved"
	 * popup will appear after form-based saves, just as it does after
	 * standard saves. This code will be called after all saves, which
	 * means that it will lead to redundant cookie-setting after normal
	 * saves. However, there doesn't appear to be a way to to set the
	 * cookie correctly only after form-based saves, unfortunately.
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 * @return bool
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		$request = RequestContext::getMain()->getRequest();
		// Bots don't need this cookie
		if ( $request->getVal( 'bot' ) === 'true' ) {
			return true;
		}
		// Have this take effect only if the save came from a form -
		// we need to use a global variable to determine that.
		global $wgPageFormsFormPrinter;
		if ( !$wgPageFormsFormPrinter instanceof FormPrinter ) {
			return true;
		}

		// Code based loosely on EditPage::setPostEditCookie().
		$postEditKey = EditPage::POST_EDIT_COOKIE_KEY_PREFIX . $revisionRecord->getID();
		$response = $request->response();
		$response->setCookie( $postEditKey, 'saved', time() + EditPage::POST_EDIT_COOKIE_DURATION );
		return true;
	}

}
