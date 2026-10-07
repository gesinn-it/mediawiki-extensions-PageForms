<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * One piece of a form definition, in the order it appears: a tag or the text between tags.
 */
interface FormElement {

	/**
	 * @return string The element as it is written in a form definition
	 */
	public function toWikitext(): string;

	/**
	 * @return array<string, mixed> Plain data (arrays, strings) from which fromArray() rebuilds the element
	 */
	public function toArray(): array;
}
