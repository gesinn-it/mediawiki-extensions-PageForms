<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Mutable counter pair for field number and tab index within a single form render.
 *
 * FormPrinter::render() makes the counters of the render it runs the current ones
 * (see begin() and end()); code that builds markup and has no counters passed in, such
 * as the input classes, reads and advances current(). Nested renders are safe, each
 * works on its own counters.
 *
 * Route for the input classes: they keep reading current() instead of getting the counters
 * passed in. The PFFormInput constructor and getHTML() are public API implemented by
 * third-party input classes, so adding a parameter would break them; current() is a thin
 * accessor that leaves those signatures alone. Everything inside FormPrinter that can take
 * the counters as a parameter does so and only falls back to current().
 *
 * The remaining static state is the stack of active renders and the standalone counters.
 * The standalone counters are used only when current() is called outside of any render,
 * e.g. when an input is built on its own (unit tests, parser functions that build an input).
 * They live for the rest of the process unless resetStandalone() is called, which tests that
 * depend on them must do.
 *
 * The legacy globals $wgPageFormsTabIndex and $wgPageFormsFieldNum only mirror these
 * values for external code that reads them (see mirrorToGlobals()); nothing reads them back.
 * While a render runs they hold the values of the field being built; after renders have
 * finished they hold the final values of the render that finished last, which for nested
 * renders is the outermost one, because it overwrites what the inner render left behind.
 */
class FormCounters {

	public int $fieldNum;
	public int $tabIndex;

	/** @var list<FormCounters> Counters of the renders in progress, innermost last. */
	private static array $active = [];

	/** Used outside of any render, e.g. when an input is built on its own. */
	private static ?FormCounters $standalone = null;

	public function __construct( int $fieldNum = 0, int $tabIndex = 0 ) {
		$this->fieldNum = $fieldNum;
		$this->tabIndex = $tabIndex;
	}

	/**
	 * The counters of the innermost render in progress, or standalone ones if there is none.
	 */
	public static function current(): self {
		if ( self::$active !== [] ) {
			return self::$active[array_key_last( self::$active )];
		}
		self::$standalone ??= new self();
		return self::$standalone;
	}

	/**
	 * Make $counters the current ones until the matching end().
	 */
	public static function begin( self $counters ): void {
		self::$active[] = $counters;
	}

	public static function end(): void {
		array_pop( self::$active );
	}

	/**
	 * Start the standalone counters (used outside of any render) at the given values.
	 */
	public static function resetStandalone( int $fieldNum = 0, int $tabIndex = 0 ): void {
		self::$standalone = new self( $fieldNum, $tabIndex );
	}

	/**
	 * Copy the values into the legacy globals, which are kept read-only for external code
	 * and deprecated; the globals will be removed.
	 */
	public function mirrorToGlobals(): void {
		$GLOBALS['wgPageFormsTabIndex'] = $this->tabIndex;
		$GLOBALS['wgPageFormsFieldNum'] = $this->fieldNum;
	}
}
