<?php
/**
 * Helper functions for the Page Forms extension.
 *
 * @author Yaron Koren
 * @ingroup PF
 */

use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

class PFUtils {

	/**
	 * Get a content language object.
	 *
	 * @return Language
	 */
	public static function getContLang() {
		return MediaWikiServices::getInstance()->getContentLanguage();
	}

	/**
	 * Get a parser object.
	 *
	 * @return Parser
	 */
	public static function getParser() {
		return MediaWikiServices::getInstance()->getParser();
	}

	/**
	 * Ensures a Parser is safe to use for parsing/output-reading calls that
	 * touch its typed properties ($mOutput, $mStripState, $mOutputType, ...),
	 * initializing it via clearState() (and setOutputType(), for calls that
	 * go through braceSubstitution(), e.g. recursiveTagParse()) if it hasn't
	 * been already.
	 *
	 * The global Parser singleton returned by getParser() is not guaranteed
	 * to have gone through clearState() yet at any given point in a request -
	 * whether it has depends on what other code happened to touch it earlier,
	 * which varies by call ordering (e.g. by SMW query result format). Before
	 * MW 1.42, accessing an uninitialized typed property like $mOutput just
	 * returned null; since MW 1.42 it throws a fatal error instead. A freshly
	 * constructed Parser (e.g. via ParserFactory::create()) is in the same
	 * uninitialized state and needs the same treatment. clearState() itself
	 * does not set $mOutputType/$ot - only setOutputType() does - so a call
	 * path reaching braceSubstitution() (template expansion) needs both.
	 *
	 * Idempotent: if the parser is already initialized (its output is set),
	 * calling this again does not reset its accumulated state.
	 *
	 * @param Parser $parser
	 * @param User $user
	 * @return Parser The same $parser instance, now safe to use
	 */
	public static function ensureParserInitialized( Parser $parser, User $user ): Parser {
		if ( !$parser->getOptions() ) {
			$parser->setOptions( ParserOptions::newFromUser( $user ) );
		}
		if ( !self::hasParserOutput( $parser ) ) {
			$parser->clearState();
			$parser->setOutputType( Parser::OT_HTML );
		}
		return $parser;
	}

	/**
	 * Whether the parser has its output yet, which it gets from clearState().
	 *
	 * Parser::getOutput() cannot tell: since MW 1.42 it reports a deprecation if it is called before
	 * the initialization, and there is no other public way to ask that works with MW 1.39 as well.
	 * So the property is read; it is unset in MW 1.42 and later and null before that.
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	private static function hasParserOutput( Parser $parser ): bool {
		$property = new ReflectionProperty( Parser::class, 'mOutput' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		return $property->isInitialized( $parser ) && $property->getValue( $parser ) !== null;
	}

	/**
	 * The HTML of a parse result, wrapped in the "mw-parser-output" div like ParserOutput::getText()
	 * returned it. getText() does not exist any more in MediaWiki 1.46, and its replacement
	 * getRawText() does not add the wrapper.
	 *
	 * @param ParserOutput $output
	 * @param ParserOptions $options
	 * @return string
	 */
	public static function getParserOutputHtml( ParserOutput $output, ParserOptions $options ): string {
		if ( method_exists( $output, 'runOutputPipeline' ) ) {
			return $output->runOutputPipeline( $options, [] )->getContentHolderText();
		}
		return $output->getText();
	}

	/**
	 * Ensures a Parser is safe to use for a recursiveTagParse()/replaceVariables()-style
	 * call that must expand "{{...}}" constructs (templates, parser functions, magic
	 * words) into HTML - e.g. resolving a 'mapping template=' or 'function=' field value.
	 *
	 * ensureParserInitialized() alone is not enough for these calls: it only acts when
	 * the parser has never been touched this request. But the global Parser singleton
	 * can also have already been initialized *and left in the wrong mode* by other code
	 * that used it earlier in the same request for its own purposes without resetting
	 * it afterward - e.g. AutoeditApi::prepareAction() calls
	 * startExternalParse( null, ..., Parser::OT_WIKI ) on this same singleton while
	 * processing a form submission/preload that precedes rendering the fields below it.
	 * In OT_WIKI mode, braceSubstitution() deliberately leaves "{{TemplateName|value}}"
	 * as literal wikitext instead of expanding it - recursiveTagParse() then silently
	 * returns the raw markup unchanged, with no error, instead of the expected output.
	 * The same call also leaves the singleton untitled (resolving to the "Badtitle"
	 * placeholder), which matters for any title-sensitive content being expanded (e.g.
	 * "{{PAGENAME}}" inside the wikitext passed in).
	 *
	 * Unlike ensureParserInitialized(), this always resets the output type - and the
	 * title, if it's currently the "Badtitle" placeholder - regardless of whether the
	 * parser was already "initialized" by some earlier, unrelated caller.
	 *
	 * @param Parser $parser
	 * @param User $user
	 * @param Title|null $title Title to resolve title-sensitive content against, if the
	 *  parser's current title is the "Badtitle" placeholder. Pass the page being
	 *  edited/viewed - e.g. RequestContext::getMain()->getTitle().
	 * @return Parser The same $parser instance, now safe to use for tag-parse calls
	 */
	public static function ensureParserReadyForTagParse( Parser $parser, User $user, ?Title $title = null ): Parser {
		self::ensureParserInitialized( $parser, $user );
		$parser->setOutputType( Parser::OT_HTML );
		if ( $title !== null && $parser->getTitle()->isSpecial( 'Badtitle' ) ) {
			$parser->setTitle( $title );
		}
		return $parser;
	}

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
		if ( \MediaWiki\Extension\PageForms\FormNamespaces::isCapitalized( $title->getNamespace() ) ) {
			return $namespace . self::getContLang()->ucfirst( $title->getPartialURL() );
		} else {
			return $namespace . $title->getPartialURL();
		}
	}

	/**
	 * Gets the text contents of a page with the passed-in Title object.
	 * @param Title $title
	 * @param int $audience
	 * @return string|null
	 */
	public static function getPageText( $title, $audience = RevisionRecord::FOR_PUBLIC ) {
		$wikiPage = self::newWikiPageFromTitle( $title );
		$content = $wikiPage->getContent( $audience );
		if ( $content instanceof TextContent ) {
			return $content->getText();
		} else {
			return null;
		}
	}

	/**
	 * Creates a WikiPage for the given Title, using the correct API for the current MW version.
	 *
	 * @param Title $title
	 * @return WikiPage
	 */
	public static function newWikiPageFromTitle( Title $title ): WikiPage {
		return MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
	}

	public static function getSpecialPage( $pageName ) {
		return MediaWikiServices::getInstance()
			->getSpecialPageFactory()
			->getPage( $pageName );
	}

	/**
	 * Helper function to get the SMW data store, if SMW is installed.
	 * @return \SMW\Store|null
	 */
	public static function getSMWStore() {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			return \SMW\StoreFactory::getStore();
		} else {
			return null;
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

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormAssets::addFormRLModules()}
	 */
	public static function addFormRLModules( $parser = null ) {
		return \MediaWiki\Extension\PageForms\FormAssets::addFormRLModules( $parser );
	}

	/**
	 * Returns a read-only database connection, using the correct API for the current MW version.
	 *
	 * @return \Wikimedia\Rdbms\IReadableDatabase|\Wikimedia\Rdbms\IDatabase
	 */
	public static function getReplicaDB() {
		if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
			return MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		}
		// MW < 1.42: getConnectionProvider() did not exist; getDBLoadBalancer() was the only option
		return MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
	}

	/**
	 * Returns an array of all form names on this wiki.
	 * @return string[]
	 */
	public static function getAllForms() {
		$dbr = self::getReplicaDB();
		$res = $dbr->select( 'page',
			'page_title',
			[ 'page_namespace' => PF_NS_FORM,
				'page_is_redirect' => false ],
			__METHOD__,
			[ 'ORDER BY' => 'page_title' ] );
		$form_names = [];
		for ( $row = $res->fetchRow(); $row; $row = $res->fetchRow() ) {
			$form_names[] = str_replace( '_', ' ', $row[0] );
		}
		$res->free();
		if ( count( $form_names ) == 0 ) {
			// This case requires special handling in the UI.
			throw new MWException( wfMessage( 'pf-noforms-error' )->parse() );
		}
		return $form_names;
	}

	public static function getFormDropdownLabel() {
		$namespaceStrings = self::getContLang()->getNamespaces();
		$formNSString = $namespaceStrings[PF_NS_FORM];
		return $formNSString . wfMessage( 'colon-separator' )->escaped();
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormTagParser::convertBackToPipes()}
	 */
	public static function convertBackToPipes( $s ) {
		return \MediaWiki\Extension\PageForms\FormTagParser::convertBackToPipes( $s );
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormTagParser::smartSplitFormTag()}
	 */
	public static function smartSplitFormTag( $string ) {
		return \MediaWiki\Extension\PageForms\FormTagParser::smartSplitFormTag( $string );
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormTagParser::getFormTagComponents()}
	 */
	public static function getFormTagComponents( $str ) {
		return \MediaWiki\Extension\PageForms\FormTagParser::getFormTagComponents( $str );
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormTagParser::getWordForYesOrNo()}
	 */
	public static function getWordForYesOrNo( $isYes ) {
		return \MediaWiki\Extension\PageForms\FormTagParser::getWordForYesOrNo( $isYes );
	}

	/**
	 * array_merge_recursive merges arrays, but it converts values with duplicate
	 * keys to arrays rather than overwriting the value in the first array with the duplicate
	 * value in the second array, as array_merge does.
	 *
	 * arrayMergeRecursiveDistinct() does not change the datatypes of the values in the arrays.
	 * Matching keys' values in the second array overwrite those in the first array.
	 *
	 * Parameters are passed by reference, though only for performance reasons. They're not
	 * altered by this function.
	 *
	 * See http://www.php.net/manual/en/function.array-merge-recursive.php#92195
	 *
	 * @param array &$array1
	 * @param array &$array2
	 * @return array
	 * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
	 * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
	 */
	public static function arrayMergeRecursiveDistinct( array &$array1, array &$array2 ) {
		$merged = $array1;

		foreach ( $array2 as $key => &$value ) {
			if ( is_array( $value ) && isset( $merged[$key] ) && is_array( $merged[$key] ) ) {
				$merged[$key] = self::arrayMergeRecursiveDistinct( $merged[$key], $value );
			} else {
				$merged[$key] = $value;
			}
		}

		return $merged;
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormNamespaces::ignoreFormName()}
	 */
	public static function ignoreFormName( $formName ) {
		return \MediaWiki\Extension\PageForms\FormNamespaces::ignoreFormName( $formName );
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormNamespaces::isCapitalized()}
	 */
	public static function isCapitalized( $index ) {
		return \MediaWiki\Extension\PageForms\FormNamespaces::isCapitalized( $index );
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormNamespaces::getCanonicalName()}
	 */
	public static function getCanonicalName( $index ) {
		return \MediaWiki\Extension\PageForms\FormNamespaces::getCanonicalName( $index );
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormNamespaces::getNsText()}
	 */
	public static function getNsText( $index ) {
		return \MediaWiki\Extension\PageForms\FormNamespaces::getNsText( $index );
	}

	public static function isTranslateEnabled() {
		return ExtensionRegistry::getInstance()->isLoaded( 'Translate' );
	}

	/**
	 * @deprecated Use {@see \MediaWiki\Extension\PageForms\FormTagParser::strReplaceFirst()}
	 */
	public static function strReplaceFirst( string $search, string $replace, string $subject ): string {
		return \MediaWiki\Extension\PageForms\FormTagParser::strReplaceFirst( $search, $replace, $subject );
	}
}
