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
		wfDeprecated( __METHOD__, '2.4.0' );
		return ParserAccess::getContLang();
	}

	/**
	 * @deprecated Use {@see ParserAccess::getParser()}
	 */
	public static function getParser() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return ParserAccess::getParser();
	}

	/**
	 * @deprecated Use {@see ParserAccess::ensureParserInitialized()}
	 */
	public static function ensureParserInitialized( Parser $parser, User $user ): Parser {
		wfDeprecated( __METHOD__, '2.4.0' );
		return ParserAccess::ensureParserInitialized( $parser, $user );
	}

	/**
	 * @deprecated Use {@see ParserAccess::getParserOutputHtml()}
	 */
	public static function getParserOutputHtml( ParserOutput $output, ParserOptions $options ): string {
		wfDeprecated( __METHOD__, '2.4.0' );
		return ParserAccess::getParserOutputHtml( $output, $options );
	}

	/**
	 * @deprecated Use {@see ParserAccess::ensureParserReadyForTagParse()}
	 */
	public static function ensureParserReadyForTagParse( Parser $parser, User $user, ?Title $title = null ): Parser {
		wfDeprecated( __METHOD__, '2.4.0' );
		return ParserAccess::ensureParserReadyForTagParse( $parser, $user, $title );
	}

	/**
	 * @deprecated Use {@see PageLinks::linkForSpecialPage()}
	 */
	public static function linkForSpecialPage( $linkRenderer, $specialPageName ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return PageLinks::linkForSpecialPage( $linkRenderer, $specialPageName );
	}

	/**
	 * @deprecated Use {@see PageLinks::makeLink()}
	 */
	public static function makeLink( $linkRenderer, $title, $msg = null, $attrs = [], $params = [] ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return PageLinks::makeLink( $linkRenderer, $title, $msg, $attrs, $params );
	}

	/**
	 * @deprecated Use {@see PageLinks::titleURLString()}
	 */
	public static function titleURLString( $title ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return PageLinks::titleURLString( $title );
	}

	/**
	 * @deprecated Use {@see WikiAccess::getPageText()}
	 */
	public static function getPageText( $title, $audience = RevisionRecord::FOR_PUBLIC ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return WikiAccess::getPageText( $title, $audience );
	}

	/**
	 * @deprecated Use {@see WikiAccess::newWikiPageFromTitle()}
	 */
	public static function newWikiPageFromTitle( Title $title ): WikiPage {
		wfDeprecated( __METHOD__, '2.4.0' );
		return WikiAccess::newWikiPageFromTitle( $title );
	}

	/**
	 * @deprecated Use {@see PageLinks::getSpecialPage()}
	 */
	public static function getSpecialPage( $pageName ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return PageLinks::getSpecialPage( $pageName );
	}

	/**
	 * @deprecated Use {@see WikiAccess::getSMWStore()}
	 */
	public static function getSMWStore() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return WikiAccess::getSMWStore();
	}

	/**
	 * @deprecated Use {@see PageLinks::linkText()}
	 */
	public static function linkText( $namespace, $name, $text = null ) {
		wfDeprecated( __METHOD__, '2.4.0' );
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
		wfDeprecated( __METHOD__, '2.4.0' );
		return PageLinks::printRedirectForm( $title, $page_contents, $edit_summary, $is_save, $user );
	}

	/**
	 * @deprecated Use {@see FormAssets::addFormRLModules()}
	 */
	public static function addFormRLModules( $parser = null ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormAssets::addFormRLModules( $parser );
	}

	/**
	 * @deprecated Use {@see WikiAccess::getReplicaDB()}
	 */
	public static function getReplicaDB() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return WikiAccess::getReplicaDB();
	}

	/**
	 * @deprecated Use {@see WikiAccess::getAllForms()}
	 */
	public static function getAllForms() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return WikiAccess::getAllForms();
	}

	/**
	 * @deprecated Use {@see WikiAccess::getFormDropdownLabel()}
	 */
	public static function getFormDropdownLabel() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return WikiAccess::getFormDropdownLabel();
	}

	/**
	 * @deprecated Use {@see FormTagParser::convertBackToPipes()}
	 */
	public static function convertBackToPipes( $s ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormTagParser::convertBackToPipes( $s );
	}

	/**
	 * @deprecated Use {@see FormTagParser::smartSplitFormTag()}
	 */
	public static function smartSplitFormTag( $string ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormTagParser::smartSplitFormTag( $string );
	}

	/**
	 * @deprecated Use {@see FormTagParser::getFormTagComponents()}
	 */
	public static function getFormTagComponents( $str ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormTagParser::getFormTagComponents( $str );
	}

	/**
	 * @deprecated Use {@see FormTagParser::getWordForYesOrNo()}
	 */
	public static function getWordForYesOrNo( $isYes ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormTagParser::getWordForYesOrNo( $isYes );
	}

	/**
	 * @deprecated Use {@see QueryArrayMerger::arrayMergeRecursiveDistinct()}
	 */
	public static function arrayMergeRecursiveDistinct( array &$array1, array &$array2 ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return QueryArrayMerger::arrayMergeRecursiveDistinct( $array1, $array2 );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::ignoreFormName()}
	 */
	public static function ignoreFormName( $formName ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormNamespaces::ignoreFormName( $formName );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::isCapitalized()}
	 */
	public static function isCapitalized( $index ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormNamespaces::isCapitalized( $index );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::getCanonicalName()}
	 */
	public static function getCanonicalName( $index ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormNamespaces::getCanonicalName( $index );
	}

	/**
	 * @deprecated Use {@see FormNamespaces::getNsText()}
	 */
	public static function getNsText( $index ) {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormNamespaces::getNsText( $index );
	}

	/**
	 * @deprecated Use {@see WikiAccess::isTranslateEnabled()}
	 */
	public static function isTranslateEnabled() {
		wfDeprecated( __METHOD__, '2.4.0' );
		return WikiAccess::isTranslateEnabled();
	}

	/**
	 * @deprecated Use {@see FormTagParser::strReplaceFirst()}
	 */
	public static function strReplaceFirst( string $search, string $replace, string $subject ): string {
		wfDeprecated( __METHOD__, '2.4.0' );
		return FormTagParser::strReplaceFirst( $search, $replace, $subject );
	}
}
