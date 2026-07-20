<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Mutable counter pair for field number and tab index within a single form render.
 *
 * Passed by reference through FormPrinter::formHTML() and all builder classes so
 * that each builder can read and increment the counters without accessing PHP globals
 * directly. FormPrinter writes the values back into the legacy globals after each
 * increment for backward compatibility with external code that reads them.
 */
class FormCounters {

	public int $fieldNum;
	public int $tabIndex;

	public function __construct( int $fieldNum = 0, int $tabIndex = 0 ) {
		$this->fieldNum = $fieldNum;
		$this->tabIndex = $tabIndex;
	}
}
