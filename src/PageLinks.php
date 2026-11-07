<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use HtmlArmor;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use PFUtils;
use RequestContext;
use User;

/**
 * Builds links to pages and special pages, and the redirect form for a page.
 *
 * @ingroup PF
 */
class PageLinks {

	/**
	 * Creates a link to a special page, using that page's top-level description as the link text.
	 * @param LinkRenderer $linkRenderer
	 * @param string $specialPageName
	 * @return string
	 */
	public static function linkForSpecialPage( $linkRenderer, $specialPageName ) {
		$specialPage = self::getSpecialPage( $specialPageName );
		// Don't use SpecialPage::getDescription() directly: its return value's
		// escaping is not consistent across MediaWiki versions (MW <= 1.42
		// returns plain text via Message::text(), MW >= 1.43 returns the bare
		// Message object, whose __toString() calls Message::parse() and is
		// therefore already HTML). Resolve the message ourselves with
		// ->text() and escape it exactly once.
		$description = htmlspecialchars( $specialPage->msg( strtolower( $specialPage->getName() ) )->text() );
		return $linkRenderer->makeKnownLink( $specialPage->getPageTitle(), new HtmlArmor( $description ) );
	}

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param LinkTarget|Title $title
	 * @param string|null $msg Must already be HTML escaped
	 * @param array $attrs link attributes
	 * @param array $params query parameters
	 *
	 * @return string|null HTML link
	 */
	public static function makeLink( $linkRenderer, $title, $msg = null, $attrs = [], $params = [] ) {
		$titleGlobal = RequestContext::getMain()->getTitle();

		if ( $title === null ) {
			return null;
		} elseif ( $titleGlobal !== null && $title->equals( $titleGlobal ) ) {
			// Display bolded text instead of a link.
			return Linker::makeSelfLinkObj( $title, $msg );
		} else {
			$html = ( $msg == null ) ? null : new HtmlArmor( $msg );
			return $linkRenderer->makeLink( $title, $html, $attrs, $params );
		}
	}

	/**
	 * Creates the name of the page that appears in the URL;
	 * this method is necessary because Title::getPartialURL(), for
	 * some reason, doesn't include the namespace
	 * @param Title $title
	 * @return string
	 */
	public static function titleURLString( $title ) {
		$namespace = $title->getNsText();
		if ( $namespace !== '' ) {
			$namespace .= ':';
		}
		if ( FormNamespaces::isCapitalized( $title->getNamespace() ) ) {
			return $namespace . PFUtils::getContLang()->ucfirst( $title->getPartialURL() );
		} else {
			return $namespace . $title->getPartialURL();
		}
	}

	/**
	 * Creates wiki-text for a link to a wiki page
	 * @param int $namespace
	 * @param string $name
	 * @param string|null $text
	 * @return string
	 */
	public static function linkText( $namespace, $name, $text = null ) {
		$title = Title::makeTitleSafe( $namespace, $name );
		if ( $title === null ) {
			// TODO maybe report an error here?
			return $name;
		}
		if ( $text === null ) {
			return '[[:' . $title->getPrefixedText() . '|' . $name . ']]';
		} else {
			return '[[:' . $title->getPrefixedText() . '|' . $text . ']]';
		}
	}

	public static function getSpecialPage( $pageName ) {
		return MediaWikiServices::getInstance()
			->getSpecialPageFactory()
			->getPage( $pageName );
	}

	/**
	 * Returns a hidden mini-form to be printed at the bottom of various helper
	 * forms, like Special:CreateForm, so that the main form can either save or
	 * preview the resulting page.
	 *
	 * @param string $title
	 * @param string $page_contents
	 * @param string $edit_summary
	 * @param bool $is_save
	 * @param User $user
	 * @return string
	 */
	public static function printRedirectForm(
		$title,
		$page_contents,
		$edit_summary,
		$is_save,
		$user
	) {
		global $wgPageFormsScriptPath;

		if ( $is_save ) {
			$action = "wpSave";
		} else {
			$action = "wpPreview";
		}

		$text = <<<END
	<p style="position: absolute; left: 45%; top: 45%;"><img src="$wgPageFormsScriptPath/skins/loading.gif" /></p>

END;
		$form_body = Html::hidden( 'wpTextbox1', $page_contents );
		$form_body .= Html::hidden( 'wpUnicodeCheck', 'ℳ𝒲♥𝓊𝓃𝒾𝒸ℴ𝒹ℯ' );
		$form_body .= Html::hidden( 'wpSummary', $edit_summary );
		// @TODO - add this in at some point.
		//$form_body .= Html::hidden( 'editRevId', $edit_rev_id );

		$userIsRegistered = $user->isRegistered();
		if ( $userIsRegistered ) {
			$edit_token = $user->getEditToken();
		} else {
			$edit_token = \MediaWiki\Session\Token::SUFFIX;
		}
		$form_body .= Html::hidden( 'wpEditToken', $edit_token );
		$form_body .= Html::hidden( $action, null );

		$form_body .= Html::hidden( 'wpUltimateParam', true );

		$text .= Html::rawElement(
			'form',
			[
				'id' => 'editform',
				'name' => 'editform',
				'method' => 'post',
				'action' => $title instanceof Title ? $title->getLocalURL( 'action=submit' ) : $title
			],
			$form_body
		);

		$text .= <<<END
	<script type="text/javascript">
	window.onload = function() {
		document.editform.submit();
	}
	</script>

END;
		// @TODO - remove this hook? It seems useless.
		MediaWikiServices::getInstance()->getHookContainer()->run(
			'PageForms::PrintRedirectForm', [ $is_save, !$is_save, false, &$text ]
		);
		return $text;
	}

}
