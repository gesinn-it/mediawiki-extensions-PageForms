<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Title;

/**
 * State of a single FormPrinter::render() call.
 *
 * FormPrinter is a long-lived shared object, so anything that belongs to one render
 * lives here instead: a nested or re-entrant render gets its own context and cannot
 * overwrite the state of the render that is still in progress.
 */
class FormRenderContext {

	/** Tab index and field number of this render. */
	public FormCounters $counters;

	/** The page being edited, or a placeholder title used for permission checks. */
	public ?Title $pageTitle = null;

	/** Whether the form definition has its own "standard input" tag (save, watch, ...). */
	public bool $standardInputsIncluded = false;

	public function __construct() {
		$this->counters = new FormCounters();
	}
}
