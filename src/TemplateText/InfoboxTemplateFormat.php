<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The "infobox" format: a table like the "standard" one, with a title row and a style that
 * floats it to the right.
 */
class InfoboxTemplateFormat extends TableTemplateFormat {

	public function open(): string {
		// A CSS style can't be used, unfortunately, since most
		// MediaWiki setups don't have an 'infobox' or
		// comparable CSS class.
		return '{| style="width: 30em; font-size: 90%; border: 1px solid #aaaaaa;' .
			' background-color: #f9f9f9; color: black; margin-bottom: 0.5em; margin-left: 1em;' .
			' padding: 0.2em; float: right; clear: right; text-align:left;"' . "\n" .
			'! style="text-align: center; background-color:#ccccff;" colspan="2"' .
			' |<span style="font-size: larger;">{{PAGENAME}}</span>' . "\n" .
			"|-\n\n";
	}
}
