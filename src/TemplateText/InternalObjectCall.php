<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * The call that stores the fields of a template with a connecting property as an object of its
 * own: "#set_internal" if Semantic Internal Objects is installed, "#subobject" if not. Both have
 * a similar syntax, so that the difference stays in this class.
 */
class InternalObjectCall {

	/** @var string */
	private $text;

	/** @var bool */
	private $useSubobject;

	/**
	 * @param string $connectingProperty
	 * @param bool $hasSio Whether Semantic Internal Objects is installed
	 */
	public function __construct( string $connectingProperty, bool $hasSio ) {
		$this->useSubobject = !$hasSio;
		$this->text = $hasSio
			? '{{#set_internal:' . $connectingProperty
			: '{{#subobject:-|' . $connectingProperty . '={{PAGENAME}}';
	}

	/**
	 * @param string $property
	 * @param string $fieldString The call of the field parameter, with the namespace if any
	 * @param bool $isList
	 */
	public function addField( string $property, string $fieldString, bool $isList ): void {
		if ( !$isList ) {
			$this->text .= '|' . $property . '=' . $fieldString;
		} elseif ( $this->useSubobject ) {
			$this->text .= '|' . $property . '=' . $fieldString . '|+sep=,';
		} else {
			$this->text .= '|' . $property . '#list=' . $fieldString;
		}
	}

	public function render(): string {
		return $this->text . '}}';
	}
}
