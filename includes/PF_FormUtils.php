<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use OOUI\ButtonInputWidget;

/**
 * Utilities for the display and retrieval of forms.
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Eugene Mednikov
 * @file
 * @ingroup PF
 */

class PFFormUtils {

	/**
	 * Add a hidden input for each field in the template call that's
	 * not handled by the form itself
	 * @param PFTemplateInForm|null $template_in_form
	 * @return string
	 */
	public static function unhandledFieldsHTML( $template_in_form ) {
		// This shouldn't happen, but sometimes this value is null.
		// @TODO - fix the code that calls this function so the
		// value is never null.
		if ( $template_in_form === null ) {
			return '';
		}

		// HTML element names shouldn't contain spaces
		$templateName = str_replace( ' ', '_', $template_in_form->getTemplateName() );
		$text = "";
		foreach ( $template_in_form->getValuesFromPage() as $key => $value ) {
			if ( $key !== null && !is_numeric( $key ) ) {
				$key = urlencode( $key );
				$text .= Html::hidden( '_unhandled_' . $templateName . '_' . $key, $value );
			}
		}
		return $text;
	}

	public static function summaryInputHTML( $is_disabled, $label = null, $attr = [], $value = '' ) {
		global $wgPageFormsTabIndex;

		if ( $label == null ) {
			$label = wfMessage( 'summary' )->text();
		}

		$wgPageFormsTabIndex++;
		$attr += [
			'tabIndex' => $wgPageFormsTabIndex,
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

		$text = new OOUI\FieldLayout(
			new OOUI\TextInputWidget( $attr ),
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
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
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
			'tabIndex' => $wgPageFormsTabIndex,
		];
		if ( $is_checked ) {
			$attrs['selected'] = true;
		}
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}
		// @phan-suppress-next-line PhanImpossibleTypeComparison
		if ( array_key_exists( 'class', $attrs ) ) {
			$attrs['classes'] = [ $attrs['class'] ];
		}

		// We can't use OOUI\FieldLayout here, because it will make the display too wide.
		$labelWidget = new OOUI\LabelWidget( [
			'label' => new OOUI\HtmlSnippet( $label )
		] );
		$text = Html::rawElement(
			'label',
			[ 'title' => wfMessage( 'tooltip-minoredit' )->parse() ],
			new OOUI\CheckboxInputWidget( $attrs ) . $labelWidget
		);
		$text = Html::rawElement( 'div', [ 'style' => 'display: inline-block; padding: 12px 16px 12px 0;' ], $text );

		return $text;
	}

	public static function watchInputHTML(
		$form_submitted, $is_disabled, $is_checked = false, $label = null, $attrs = []
	) {
		global $wgPageFormsTabIndex;
		$titleGlobal = RequestContext::getMain()->getTitle();

		$wgPageFormsTabIndex++;
		// figure out if the checkbox should be checked -
		// this code borrowed from /includes/EditPage.php
		if ( !$form_submitted ) {
			$user = RequestContext::getMain()->getUser();
			$services = MediaWikiServices::getInstance();
			$userOptionsLookup = $services->getUserOptionsLookup();
			$watchlistManager = $services->getWatchlistManager();
			if ( $userOptionsLookup->getOption( $user, 'watchdefault' ) ) {
				# Watch all edits
				$is_checked = true;
			} elseif ( $userOptionsLookup->getOption( $user, 'watchcreations' ) &&
				!$titleGlobal->exists() ) {
				# Watch creations
				$is_checked = true;
			} elseif ( $watchlistManager->isWatched( $user, $titleGlobal ) ) {
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
			'tabIndex' => $wgPageFormsTabIndex,
		];
		if ( $is_checked ) {
			$attrs['selected'] = true;
		}
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}
		// @phan-suppress-next-line PhanImpossibleTypeComparison
		if ( array_key_exists( 'class', $attrs ) ) {
			$attrs['classes'] = [ $attrs['class'] ];
		}

		// We can't use OOUI\FieldLayout here, because it will make the display too wide.
		$labelWidget = new OOUI\LabelWidget( [
			'label' => new OOUI\HtmlSnippet( $label )
		] );
		$text = Html::rawElement(
			'label',
			[ 'title' => wfMessage( 'tooltip-watch' )->parse() ],
			new OOUI\CheckboxInputWidget( $attrs ) . $labelWidget
		);
		$text = Html::rawElement( 'div', [ 'style' => 'display: inline-block; padding: 12px 16px 12px 0;' ], $text );

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
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'savearticle' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpSave',
			'tabIndex'  => $wgPageFormsTabIndex,
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
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;

		if ( $label == null ) {
			$label = wfMessage( 'pf_formedit_saveandcontinueediting' )->text();
		}

		$temp = $attr + [
			'id'        => 'wpSaveAndContinue',
			'tabIndex'  => $wgPageFormsTabIndex,
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
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showpreview' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpPreview',
			'tabIndex'  => $wgPageFormsTabIndex,
			'accessKey' => wfMessage( 'accesskey-preview' )->text(),
			'title'     => wfMessage( 'tooltip-preview' )->text(),
		];
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpPreview', $label, 'submit', $temp );
	}

	public static function showChangesButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showdiff' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpDiff',
			'tabIndex'  => $wgPageFormsTabIndex,
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
			$attr['classes'][] = 'pfSendBack';
		} else {
			$attr['href'] = $titleGlobal->getFullURL();
		}
		$attr['framed'] = false;
		$attr['label'] = $label;
		$attr['flags'] = [ 'destructive' ];
		if ( array_key_exists( 'class', $attr ) ) {
			$attr['classes'][] = $attr['class'];
		}

		return "\t\t" . new OOUI\ButtonWidget( $attr ) . "\n";
	}

	public static function runQueryButtonHTML( $is_disabled = false, $label = null, $attr = [] ) {
		// is_disabled is currently ignored
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'runquery' )->text();
		}
		$buttonHTML = self::buttonHTML( 'wpRunQuery', $label, 'submit',
			$attr + [
			'id' => 'wpRunQuery',
			'tabIndex' => $wgPageFormsTabIndex,
			'title' => $label,
			'flags' => [ 'primary', 'progressive' ],
			'icon' => 'search'
		] );
		return new OOUI\FieldLayout( $buttonHTML );
	}

	/**
	 * Much of this function is based on MediaWiki's EditPage::showEditForm().
	 * @param bool $form_submitted
	 * @param bool $is_disabled
	 * @return string
	 */
	public static function formBottom( $form_submitted, $is_disabled ) {
		$text = <<<END
	<br />
	<div class='editOptions'>

END;
		$req = RequestContext::getMain()->getRequest();
		$summary = $req->getVal( 'wpSummary' );
		$text .= self::summaryInputHTML( $is_disabled, null, [], $summary );
		$user = RequestContext::getMain()->getUser();
		if ( $user->isAllowed( 'minoredit' ) ) {
			$text .= self::minorEditInputHTML( $form_submitted, $is_disabled, false );
		}

		$userIsRegistered = $user->isRegistered();
		if ( $userIsRegistered ) {
			$text .= self::watchInputHTML( $form_submitted, $is_disabled );
		}

		$text .= <<<END
	<br />
	<div class='editButtons'>

END;
		$text .= self::saveButtonHTML( $is_disabled );
		$text .= self::showPreviewButtonHTML( $is_disabled );
		$text .= self::showChangesButtonHTML( $is_disabled );
		$text .= self::cancelLinkHTML( $is_disabled );
		$text .= <<<END
	</div><!-- editButtons -->
	</div><!-- editOptions -->

END;
		return $text;
	}

	/** @deprecated since PageForms 6.x — use PFFormCache::getPreloadedText() instead. */
	public static function getPreloadedText( $preload ) {
		return PFFormCache::getPreloadedText( $preload );
	}

	/**
	 * Used by 'RunQuery' page
	 * @return string
	 */
	public static function queryFormBottom() {
		return self::runQueryButtonHTML( false );
	}

	public static function getMonthNames() {
		return [
			wfMessage( 'january' )->inContentLanguage()->text(),
			wfMessage( 'february' )->inContentLanguage()->text(),
			wfMessage( 'march' )->inContentLanguage()->text(),
			wfMessage( 'april' )->inContentLanguage()->text(),
			// Needed to avoid using 3-letter abbreviation
			wfMessage( 'may_long' )->inContentLanguage()->text(),
			wfMessage( 'june' )->inContentLanguage()->text(),
			wfMessage( 'july' )->inContentLanguage()->text(),
			wfMessage( 'august' )->inContentLanguage()->text(),
			wfMessage( 'september' )->inContentLanguage()->text(),
			wfMessage( 'october' )->inContentLanguage()->text(),
			wfMessage( 'november' )->inContentLanguage()->text(),
			wfMessage( 'december' )->inContentLanguage()->text()
		];
	}

	public static function setGlobalVarsForSpreadsheet() {
		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;

		// JS variables that hold boolean and date values in the wiki's
		// (as opposed to the user's) language.
		$wgPageFormsContLangYes = wfMessage( 'htmlform-yes' )->inContentLanguage()->text();
		$wgPageFormsContLangNo = wfMessage( 'htmlform-no' )->inContentLanguage()->text();
		$monthMessages = [
			"january", "february", "march", "april", "may_long", "june",
			"july", "august", "september", "october", "november", "december"
		];
		$wgPageFormsContLangMonths = [ '' ];
		foreach ( $monthMessages as $monthMsg ) {
			$wgPageFormsContLangMonths[] = wfMessage( $monthMsg )->inContentLanguage()->text();
		}
	}

	/** @deprecated since PageForms 6.x — use PFFormCache::getFormDefinition() instead. */
	public static function getFormDefinition( Parser $parser, $form_def = null, $form_id = null ) {
		return PFFormCache::getFormDefinition( $parser, $form_def, $form_id );
	}

	/** @deprecated since PageForms 6.x — use PFFormCache::purgeCache() instead. */
	public static function purgeCache( WikiPage $wikipage ) {
		return PFFormCache::purgeCache( $wikipage );
	}

	/** @deprecated since PageForms 6.x — use PFFormCache::purgeCacheOnSave() instead. */
	public static function purgeCacheOnSave( RenderedRevision $renderedRevision ) {
		return PFFormCache::purgeCacheOnSave( $renderedRevision );
	}

	/** @deprecated since PageForms 6.x — use PFFormCache::getFormCache() instead. */
	public static function getFormCache() {
		return PFFormCache::getFormCache();
	}

	/** @deprecated since PageForms 6.x — use PFFormCache::getCacheKey() instead. */
	public static function getCacheKey( $formId, $parser = null ) {
		return PFFormCache::getCacheKey( $formId, $parser );
	}

	/**
	 * Get section header HTML
	 * @param string $header_name
	 * @param int $header_level
	 * @return string
	 */
	public static function headerHTML( $header_name, $header_level = 2 ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		$text = "";

		if ( !is_numeric( $header_level ) ) {
			// The default header level is set to 2
			$header_level = 2;
		}

		$header_level = min( $header_level, 6 );
		$elementName = 'h' . $header_level;
		$text = Html::rawElement( $elementName, [], $header_name );
		return $text;
	}

	/**
	 * Get the changed index if a new template or section was
	 * inserted before the end, or one was deleted in the form
	 * @param int $i
	 * @param int|null $new_item_loc
	 * @param int|null $deleted_item_loc
	 * @return int
	 */
	public static function getChangedIndex( $i, $new_item_loc, $deleted_item_loc ) {
		$old_i = $i;
		if ( $new_item_loc != null ) {
			if ( $i > $new_item_loc ) {
				$old_i = $i - 1;
			} elseif ( $i == $new_item_loc ) {
				// it's the new template; it shouldn't
				// get any query-string data
				$old_i = -1;
			}
		} elseif ( $deleted_item_loc != null ) {
			if ( $i >= $deleted_item_loc ) {
				$old_i = $i + 1;
			}
		}
		return $old_i;
	}

	public static function setShowOnSelect( $showOnSelectVals, $inputID, $isCheckbox = false ) {
		global $wgPageFormsShowOnSelect;

		foreach ( $showOnSelectVals as $divID => $options ) {
			// A checkbox will just have div ID(s).
			$data = $isCheckbox ? $divID : [ $options, $divID ];
			if ( array_key_exists( $inputID, $wgPageFormsShowOnSelect ) ) {
				$wgPageFormsShowOnSelect[$inputID][] = $data;
			} else {
				$wgPageFormsShowOnSelect[$inputID] = [ $data ];
			}
		}
	}

	/**
	 * If the value passed in for a certain field, when a form is submitted,
	 * is an array, then it might be from a checkbox or date input — in that
	 * case, convert it into a string.
	 *
	 * Extracted from PFFormPrinter, where a forwarding alias is kept for
	 * backward compatibility with external callers.
	 *
	 * @param array $value
	 * @param string $delimiter
	 * @return string
	 */
	public static function getStringFromPassedInArray( $value, $delimiter ) {
		// If it's just a regular list, concatenate it.
		// This is needed due to some strange behavior
		// in PF, where, if a preload page is passed in
		// in the query string, the form ends up being
		// parsed twice.
		if ( array_key_exists( 'is_list', $value ) ) {
			unset( $value['is_list'] );
			return str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], implode( "$delimiter ", $value ) );
		}

		// if it has 1 or 2 elements, assume it's a checkbox; if it has
		// 3 elements, assume it's a date
		// - this handling will have to get more complex if other
		// possibilities get added
		if ( count( $value ) == 1 ) {
			return PFUtils::getWordForYesOrNo( false );
		} elseif ( count( $value ) == 2 ) {
			return PFUtils::getWordForYesOrNo( true );
		// if it's 3 or greater, assume it's a date or datetime
		} elseif ( count( $value ) >= 3 ) {
			$month = $value['month'];
			$day = $value['day'];
			if ( $day !== '' ) {
				global $wgAmericanDates;
				if ( $wgAmericanDates == false ) {
					// pad out day to always be two digits
					$day = str_pad( $day, 2, "0", STR_PAD_LEFT );
				}
			}
			$year = $value['year'];
			$hour = $minute = $second = $ampm24h = $timezone = null;
			if ( isset( $value['hour'] ) ) {
				$hour = $value['hour'];
			}
			if ( isset( $value['minute'] ) ) {
				$minute = $value['minute'];
			}
			if ( isset( $value['second'] ) ) {
				$second = $value['second'];
			}
			if ( isset( $value['ampm24h'] ) ) {
				$ampm24h = $value['ampm24h'];
			}
			if ( isset( $value['timezone'] ) ) {
				$timezone = $value['timezone'];
			}
			if ( $year !== '' ) {
				global $wgAmericanDates;

				if ( $month == '' ) {
					return $year;
				} elseif ( $day == '' ) {
					if ( !$wgAmericanDates ) {
						// The month is a number - we need it to be a string,
						// so that the date will be parsed correctly if
						// strtotime() is used.
						$monthNames = self::getMonthNames();
						$month = $monthNames[$month - 1];
					}
					return "$month $year";
				} else {
					if ( $wgAmericanDates == true ) {
						$new_value = "$month $day, $year";
					} else {
						$new_value = "$year/$month/$day";
					}
					// If there's a day, include whatever time information we have.
					if ( $hour !== null ) {
						$new_value .= " "
						. str_pad( (string)intval( substr( $hour, 0, 2 ) ), 2, '0', STR_PAD_LEFT )
						. ":"
						. str_pad( (string)intval( substr( $minute, 0, 2 ) ), 2, '0', STR_PAD_LEFT );
					}
					if ( $second !== null ) {
						$new_value .= ":" . str_pad( (string)intval( substr( $second, 0, 2 ) ), 2, '0', STR_PAD_LEFT );
					}
					if ( $ampm24h !== null ) {
						$new_value .= " $ampm24h";
					}
					if ( $timezone !== null ) {
						$new_value .= " $timezone";
					}
					return $new_value;
				}
			}
		}
		return '';
	}

	/**
	 * Returns HTML for a loading overlay (spinner + background mask).
	 *
	 * Extracted from PFFormPrinter, where a forwarding alias is kept for
	 * backward compatibility with external callers.
	 *
	 * @return string
	 */
	public static function displayLoadingImage() {
		global $wgPageFormsScriptPath;

		$text = '<div id="loadingMask"></div>';
		$loadingBGImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loadingbg.png" ] );
		$text .= '<div style="position: fixed; left: 50%; top: 50%;">' . $loadingBGImage . '</div>';
		$loadingImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ] );
		$text .= '<div style="position: fixed; left: 50%; top: 50%; padding: 48px;">' . $loadingImage . '</div>';

		return Html::rawElement( 'span', [ 'class' => 'loadingImage' ], $text );
	}

	/**
	 * Generates a random UUID v4 string.
	 *
	 * Extracted from PFFormPrinter (was private), now public so it can be
	 * tested and reused outside the form rendering pipeline.
	 *
	 * @return string
	 */
	public static function generateUUID() {
		// Copied from https://www.php.net/manual/en/function.uniqid.php#94959
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,
			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

}
