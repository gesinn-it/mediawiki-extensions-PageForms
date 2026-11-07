<?php
/**
 * Helper functions for the Page Forms extension.
 *
 * @author Yaron Koren
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\FormAssets;
use MediaWiki\Extension\PageForms\FormNamespaces;
use MediaWiki\Extension\PageForms\FormTagParser;
use MediaWiki\Extension\PageForms\PageLinks;
use MediaWiki\Extension\PageForms\ParserAccess;
use MediaWiki\Extension\PageForms\QueryArrayMerger;
use MediaWiki\Extension\PageForms\WikiAccess;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

class PFUtils {

	/**
	 * @deprecated Use {@see ParserAccess::getContLang()}
	 */
	public static function getContLang() {
		return ParserAccess::getContLang();
	}

	/**
	 * @deprecated Use {@see ParserAccess::getParser()}
	 */
	public static function getParser() {
		return ParserAccess::getParser();
	}

	/**
	 * @deprecated Use {@see ParserAccess::ensureParserInitialized()}
	 */
	public static function ensureParserInitialized( Parser $parser, User $user ): Parser {
		return ParserAccess::ensureParserInitialized( $parser, $user );
	}

	/**
	 * @deprecated Use {@see ParserAccess::getParserOutputHtml()}
	 */
	public static function getParserOutputHtml( ParserOutput $output, ParserOptions $options ): string {
		return ParserAccess::getParserOutputHtml( $output, $options );
	}

	/**
	 * @deprecated Use {@see ParserAccess::ensureParserReadyForTagParse()}
	 */
	public static function ensureParserReadyForTagParse( Parser $parser, User $user, ?Title $title = null ): Parser {
		return ParserAccess::ensureParserReadyForTagParse( $parser, $user, $title );
	}

	/**
	 * @deprecated Use {@see PageLinks::linkForSpecialPage()}
	 */
	public static function linkForSpecialPage( $linkRenderer, $specialPageName ) {
		return PageLinks::linkForSpecialPage( $linkRenderer, $specialPageName );
	}

	/**
	 * @deprecated Use {@see PageLinks::makeLink()}
	 */
	public static function makeLink( $linkRenderer, $title, $msg = null, $attrs = [], $params = [] ) {
		return PageLinks::makeLink( $linkRenderer, $title, $msg, $attrs, $params );
	}

	/**
	 * @deprecated Use {@see PageLinks::titleURLString()}
	 */
	public static function titleURLString( $title ) {
		return PageLinks::titleURLString( $title );
	}

	/**
	 * @deprecated Use {@see WikiAccess::getPageText()}
	 */
	public static function getPageText( $title, $audience = RevisionRecord::FOR_PUBLIC ) {
		return WikiAccess::getPageText( $title, $audience );
	}

	/**
	 * @deprecated Use {@see WikiAccess::newWikiPageFromTitle()}
	 */
	public static function newWikiPageFromTitle( Title $title ): WikiPage {
		return WikiAccess::newWikiPageFromTitle( $title );
	}

	/**
	 * @deprecated Use {@see PageLinks::getSpecialPage()}
	 */
	public static function getSpecialPage( $pageName ) {
		return PageLinks::getSpecialPage( $pageName );
	}

	/**
	 * @deprecated Use {@see WikiAccess::getSMWStore()}
	 */
	public static function getSMWStore() {
		return WikiAccess::getSMWStore();
	}

	/**
	 * @deprecated Use {@see PageLinks::linkText()}
	 */
	public static function linkText( $namespace, $name, $text = null ) {
		return PageLinks::linkText( $namespace, $name, $text );
	}

	/**
	 * @deprecated Use {@see PageLinks::printRedirectForm()}
	 */
	public static function printRedirectForm(
		$title,
		$page_contents,
		$edit_summary,
		$is_save,
		$user
	) {
		return PageLinks::printRedirectForm( $title, $page_contents, $edit_summary, $is_save, $user );
	}

	/**
	 * @deprecated Use {@see FormAssets::addFormRLModules()}
	 */
	public static function addFormRLModules( $parser = null ) {
		return FormAssets::addFormRLModules( $parser );
	}

	/**
	 * @deprecated Use {@see WikiAccess::getReplicaDB()}
	 */
	public static function getReplicaDB() {
		return WikiAccess::getReplicaDB();
	}

	/**
	 * @deprecated Use {@see WikiAccess::getAllForms()}
	 */
	public static function getAllForms() {
		return WikiAccess::getAllForms();
	}

	/**
	 * @deprecated Use {@see WikiAccess::getFormDropdownLabel()}
	 */
	public static function getFormDropdownLabel() {
		return WikiAccess::getFormDropdownLabel();
	}

	/**
	 * @deprecated Use {@see FormTagParser::convertBackToPipes()}
	 */
	public static function convertBackToPipes( $s ) {
		return FormTagParser::convertBackToPipes( $s );
	}

	/**
	 * @deprecated Use {@see FormTagParser::smartSplitFormTag()}
	 */
	public static function smartSplitFormTag( $string ) {
		return FormTagParser::smartSplitFormTag( $string );
	}

	/**
	 * @deprecated Use {@see FormTagParser::getFormTagComponents()}
	 */
	public static function getFormTagComponents( $str ) {
		return FormTagParser::getFormTagComponents( $str );
	}

	/**
	 * @deprecated Use {@see FormTagParser::getWordForYesOrNo()}
	 */
	public static function getWordForYesOrNo( $isYes ) {
		return FormTagParser::getWordForYesOrNo( $isYes );
	}

	/**
	 * @deprecated Use {@see QueryArrayMerger::arrayMergeRecursiveDistinct()}
	 */
	public static function arrayMergeRecursiveDistinct( array &$array1, array &$array2 ) {
		return QueryArrayMerger::arrayMergeRecursiveDistinct( $array1, $array2 );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::ignoreFormName()}
	 */
	public static function ignoreFormName( $formName ) {
		return FormNamespaces::ignoreFormName( $formName );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::isCapitalized()}
	 */
	public static function isCapitalized( $index ) {
		return FormNamespaces::isCapitalized( $index );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::getCanonicalName()}
	 */
	public static function getCanonicalName( $index ) {
		return FormNamespaces::getCanonicalName( $index );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::getNsText()}
	 */
	public static function getNsText( $index ) {
		return FormNamespaces::getNsText( $index );
	}

	/**
	 * @deprecated Use {@see WikiAccess::isTranslateEnabled()}
	 */
	public static function isTranslateEnabled() {
		return WikiAccess::isTranslateEnabled();
	}

	/**
	 * @deprecated Use {@see FormTagParser::strReplaceFirst()}
	 */
	public static function strReplaceFirst( string $search, string $replace, string $subject ): string {
		return FormTagParser::strReplaceFirst( $search, $replace, $subject );
	}
}
