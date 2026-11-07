<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Parser;
use ParserOptions;
use ParserOutput;
use ReflectionProperty;
use User;

/**
 * Access to the parser, and the preparation a parser needs before it is used for parsing or reading output.
 *
 * @ingroup PF
 */
class ParserAccess {

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

}
