<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The "#set" call that stores the hidden fields of a template.
 */
class SetCall {

	/** @var string */
	private $text = '';

	/**
	 * @param string $property
	 * @param string $fieldString The call of the field parameter, with the namespace if any
	 * @param bool $isList
	 */
	public function addField( string $property, string $fieldString, bool $isList ): void {
		$this->text .= $property . ( $isList ? '#list=' : '=' ) . $fieldString . '|';
	}

	/**
	 * @return string The call, or an empty string if no field was added
	 */
	public function render(): string {
		return $this->text === '' ? '' : '{{#set:' . $this->text . "}}\n";
	}
}
