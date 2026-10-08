<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use CommentStore;
use Html;
use MediaWiki\MediaWikiServices;
use OOUI\ButtonInputWidget;
use RequestContext;
use Title;

/**
 * Buttons and inputs of the bottom of a form: summary, minor edit, watch, save, preview, changes, cancel and run query.
 *
 * @ingroup PF
 */
class FormButtons {

	public static function summaryInputHTML( $is_disabled, $label = null, $attr = [], $value = '' ) {
		$counters = FormCounters::current();

		if ( $label == null ) {
			$label = wfMessage( 'summary' )->text();
		}

		$counters->tabIndex++;
		$attr += [
			'tabIndex' => $counters->tabIndex,
			'value' => $value,
			'name' => 'wpSummary',
			'id' => 'wpSummary',
			'maxlength' => CommentStore::COMMENT_CHARACTER_LIMIT,
			'title' => wfMessage( 'tooltip-summary' )->text(),
			'accessKey' => wfMessage( 'accesskey-summary' )->text()
		];
		if ( $is_disabled ) {
			$attr['disabled'] = true;
		}
		if ( array_key_exists( 'class', $attr ) ) {
			$attr['classes'] = [ $attr['class'] ];
		}

		$text = new \OOUI\FieldLayout(
			new \OOUI\TextInputWidget( $attr ),
			[
				'align' => 'top',
				'label' => $label
			]
		);

		return $text;
	}

	public static function minorEditInputHTML(
		$form_submitted, $is_disabled, $is_checked, $label = null, $attrs = []
	) {
		$counters = FormCounters::current();

		$counters->tabIndex++;
		if ( !$form_submitted ) {
			$user = RequestContext::getMain()->getUser();
			$is_checked = MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $user, 'minordefault' );
		}

		if ( $label == null ) {
			$label = wfMessage( 'minoredit' )->parse();
		}

		$attrs += [
			'id' => 'wpMinoredit',
			'name' => 'wpMinoredit',
			'accessKey' => wfMessage( 'accesskey-minoredit' )->text(),
			'tabIndex' => $counters->tabIndex,
		];
		if ( $is_checked ) {
			$attrs['selected'] = true;
		}
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}
		if ( array_key_exists( 'class', $attrs ) ) {
			$attrs['classes'] = [ $attrs['class'] ];
		}

		// We can't use OOUI\FieldLayout here, because it will make the display too wide.
		$labelWidget = new \OOUI\LabelWidget( [
			'label' => new \OOUI\HtmlSnippet( $label )
		] );
		$text = Html::rawElement(
			'label',
			[ 'title' => wfMessage( 'tooltip-minoredit' )->parse() ],
			new \OOUI\CheckboxInputWidget( $attrs ) . $labelWidget
		);
		// Inline element so it is valid inside both <div> and <p> containers
		$text = Html::rawElement( 'span', [ 'class' => 'pf-submit-option' ], $text );

		return $text;
	}

	public static function watchInputHTML(
		$form_submitted, $is_disabled, $is_checked = false, $label = null, $attrs = []
	) {
		$counters = FormCounters::current();
		$titleGlobal = RequestContext::getMain()->getTitle();

		$counters->tabIndex++;
		// figure out if the checkbox should be checked -
		// this code borrowed from /includes/EditPage.php
		if ( !$form_submitted ) {
			$user = RequestContext::getMain()->getUser();
			$services = MediaWikiServices::getInstance();
			$userOptionsLookup = $services->getUserOptionsLookup();
			if ( $userOptionsLookup->getOption( $user, 'watchdefault' ) ) {
				# Watch all edits
				$is_checked = true;
			} elseif ( $userOptionsLookup->getOption( $user, 'watchcreations' ) &&
				!$titleGlobal->exists() ) {
				# Watch creations
				$is_checked = true;
			} elseif ( $services->getWatchlistManager()->isWatched( $user, $titleGlobal ) ) {
				# Already watched
				$is_checked = true;
			}
		}
		if ( $label == null ) {
			$label = wfMessage( 'watchthis' )->parse();
		}
		$attrs += [
			'id' => 'wpWatchthis',
			'name' => 'wpWatchthis',
			'accessKey' => wfMessage( 'accesskey-watch' )->text(),
			'tabIndex' => $counters->tabIndex,
		];
		if ( $is_checked ) {
			$attrs['selected'] = true;
		}
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}
		if ( array_key_exists( 'class', $attrs ) ) {
			$attrs['classes'] = [ $attrs['class'] ];
		}

		// We can't use OOUI\FieldLayout here, because it will make the display too wide.
		$labelWidget = new \OOUI\LabelWidget( [
			'label' => new \OOUI\HtmlSnippet( $label )
		] );
		$text = Html::rawElement(
			'label',
			[ 'title' => wfMessage( 'tooltip-watch' )->parse() ],
			new \OOUI\CheckboxInputWidget( $attrs ) . $labelWidget
		);
		// Inline element so it is valid inside both <div> and <p> containers
		$text = Html::rawElement( 'span', [ 'class' => 'pf-submit-option' ], $text );

		return $text;
	}

	/**
	 * Helper function to display a simple button
	 * @param string $name
	 * @param string $value
	 * @param string $type
	 * @param array $attrs
	 * @return ButtonInputWidget
	 */
	private static function buttonHTML( $name, $value, $type, $attrs ) {
		$attrs += [
			'type' => $type,
			'name' => $name,
			'label' => $value
		];
		$button = new ButtonInputWidget( $attrs );
		// Special handling for 'class'.
		if ( isset( $attrs['class'] ) ) {
			// Make sure it's an array.
			if ( is_string( $attrs['class'] ) ) {
				$attrs['class'] = [ $attrs['class'] ];
			}
			$button->addClasses( $attrs['class'] );
		}
		return $button;
	}

	public static function saveButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		$counters = FormCounters::current();

		$counters->tabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'savearticle' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpSave',
			'tabIndex'  => $counters->tabIndex,
			'accessKey' => wfMessage( 'accesskey-save' )->text(),
			'title'     => wfMessage( 'tooltip-save' )->text(),
			'flags'     => [ 'primary', 'progressive' ]
		];
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpSave', $label, 'submit', $temp );
	}

	public static function saveAndContinueButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		$counters = FormCounters::current();

		$counters->tabIndex++;

		if ( $label == null ) {
			$label = wfMessage( 'pf_formedit_saveandcontinueediting' )->text();
		}

		$temp = $attr + [
			'id'        => 'wpSaveAndContinue',
			'tabIndex'  => $counters->tabIndex,
			'disabled'  => true,
			'accessKey' => wfMessage( 'pf_formedit_accesskey_saveandcontinueediting' )->text(),
			'title'     => wfMessage( 'pf_formedit_tooltip_saveandcontinueediting' )->text(),
		];

		if ( $is_disabled ) {
			$temp['class'] = 'pf-save_and_continue disabled';
		} else {
			$temp['class'] = 'pf-save_and_continue';
		}

		return self::buttonHTML( 'wpSaveAndContinue', $label, 'button', $temp );
	}

	public static function showPreviewButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		$counters = FormCounters::current();

		$counters->tabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showpreview' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpPreview',
			'tabIndex'  => $counters->tabIndex,
			'accessKey' => wfMessage( 'accesskey-preview' )->text(),
			'title'     => wfMessage( 'tooltip-preview' )->text(),
		];
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpPreview', $label, 'submit', $temp );
	}

	public static function showChangesButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		$counters = FormCounters::current();

		$counters->tabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showdiff' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpDiff',
			'tabIndex'  => $counters->tabIndex,
			'accessKey' => wfMessage( 'accesskey-diff' )->text(),
			'title'     => wfMessage( 'tooltip-diff' )->text(),
		];
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpDiff', $label, 'submit', $temp );
	}

	public static function cancelLinkHTML( $is_disabled, $label = null, $attr = [] ) {
		$titleGlobal = RequestContext::getMain()->getTitle();

		if ( $label == null ) {
			$label = wfMessage( 'cancel' )->parse();
		}
		$attr['classes'] = [];
		if ( $titleGlobal == null || $titleGlobal->isSpecial( 'FormEdit' ) ) {
			$req = RequestContext::getMain()->getRequest();
			$returnto = $req->getVal( 'returnto' );
			$returntoTitle = $returnto !== null ? Title::newFromText( $returnto ) : null;
			if ( $returntoTitle !== null ) {
				$attr['href'] = $returntoTitle->getLocalURL();
			} else {
				$attr['classes'][] = 'pfSendBack';
			}
		} else {
			$attr['href'] = $titleGlobal->getFullURL();
		}
		$attr['framed'] = false;
		$attr['label'] = $label;
		$attr['flags'] = [ 'destructive' ];
		if ( array_key_exists( 'class', $attr ) ) {
			$attr['classes'][] = $attr['class'];
		}

		return "\t\t" . new \OOUI\ButtonWidget( $attr ) . "\n";
	}

	public static function runQueryButtonHTML( $is_disabled = false, $label = null, $attr = [] ) {
		// is_disabled is currently ignored
		$counters = FormCounters::current();

		$counters->tabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'runquery' )->text();
		}
		$buttonHTML = self::buttonHTML( 'wpRunQuery', $label, 'submit',
			$attr + [
			'id' => 'wpRunQuery',
			'tabIndex' => $counters->tabIndex,
			'title' => $label,
			'flags' => [ 'primary', 'progressive' ],
			'icon' => 'search'
		] );
		return new \OOUI\FieldLayout( $buttonHTML );
	}

	/**
	 * Much of this function is based on MediaWiki's EditPage::showEditForm().
	 * @param bool $form_submitted
	 * @param bool $is_disabled
	 * @return string
	 */
	public static function formBottom( $form_submitted, $is_disabled ) {
		$req = RequestContext::getMain()->getRequest();
		$summary = $req->getVal( 'wpSummary' );
		$user = RequestContext::getMain()->getUser();

		$optionsContent = self::summaryInputHTML( $is_disabled, null, [], $summary );
		if ( $user->isAllowed( 'minoredit' ) ) {
			$optionsContent .= self::minorEditInputHTML( $form_submitted, $is_disabled, false );
		}
		if ( $user->isRegistered() ) {
			$optionsContent .= self::watchInputHTML( $form_submitted, $is_disabled );
		}

		$buttonsContent = self::saveButtonHTML( $is_disabled );
		$buttonsContent .= self::showPreviewButtonHTML( $is_disabled );
		$buttonsContent .= self::showChangesButtonHTML( $is_disabled );
		$buttonsContent .= self::cancelLinkHTML( $is_disabled );

		return Html::rawElement( 'div', [ 'class' => 'editOptions' ],
			$optionsContent .
			Html::rawElement( 'div', [ 'class' => 'editButtons' ], $buttonsContent )
		);
	}

	/**
	 * Used by 'RunQuery' page
	 * @return \OOUI\FieldLayout
	 */
	public static function queryFormBottom() {
		return self::runQueryButtonHTML( false );
	}

}
