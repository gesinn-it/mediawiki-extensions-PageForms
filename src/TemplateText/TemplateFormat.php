<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The markup that one display format of a created template ("standard", "infobox", "plain",
 * "sections") puts around the fields. TemplateWikitextWriter decides what is written for which
 * field; the format only knows how it looks.
 */
interface TemplateFormat {

	/**
	 * What opens the output of the fields.
	 */
	public function open(): string;

	/**
	 * The label of a field that is always shown.
	 *
	 * @param string $label
	 * @param bool $notFirst Whether the field is not the first of the template
	 */
	public function header( string $label, bool $notFirst ): string;

	/**
	 * What comes before the "#if" call around a field that is shown only if it has a value.
	 */
	public function nonemptyLead(): string;

	/**
	 * The label of a field that is shown only if it has a value; it is part of the "#if" call.
	 *
	 * @param string $label
	 * @param bool $notFirst Whether the field is not the first of the template
	 */
	public function nonemptyHeader( string $label, bool $notFirst ): string;

	/**
	 * What is written in front of the value of a field that is shown only if it has a value, to
	 * keep it apart from the label inside the "#if" call.
	 */
	public function nonemptySeparator(): string;

	/**
	 * What is written in front of the value of a field that is always shown.
	 */
	public function valueCell(): string;

	/**
	 * What is written in front of the value of a field that is shown only if it has a value, when
	 * its label is not part of the "#if" call.
	 */
	public function nonemptyValueCell(): string;

	/**
	 * The label of the query that lists the pages pointing to this one.
	 *
	 * @param string $label
	 * @param bool $hasFields Whether the template has fields
	 */
	public function aggregationHeader( string $label, bool $hasFields ): string;

	/**
	 * What closes the output of the fields.
	 */
	public function close(): string;

	/**
	 * What follows the output of the fields, unless the template has more than one instance
	 * per page.
	 */
	public function trailingNewline(): string;
}
