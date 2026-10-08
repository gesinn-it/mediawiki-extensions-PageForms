<?php

namespace MediaWiki\Extension\PageForms;

/**
 * Hands out the form printer of the request.
 *
 * The printer is kept in the global variable $wgPageFormsFormPrinter, which is set when the
 * extension is initialised and which other extensions use to add their input types. Code of
 * this extension gets the printer here instead of reading the global; tests and custom code that
 * replace the global are still honoured.
 *
 * @ingroup PF
 */
class FormPrinterFactory {

	/**
	 * Build a new form printer with all its default collaborators and store it in the global
	 * variable for custom code. The setup hook runs on the finished printer.
	 *
	 * @return FormPrinter
	 */
	public static function initialize(): FormPrinter {
		$printer = new FormPrinter();
		$GLOBALS['wgPageFormsFormPrinter'] = $printer;
		return $printer;
	}

	/**
	 * @return FormPrinter The printer in $wgPageFormsFormPrinter; it is created if there is none yet
	 */
	public static function get(): FormPrinter {
		return $GLOBALS['wgPageFormsFormPrinter'] ?? self::initialize();
	}
}
