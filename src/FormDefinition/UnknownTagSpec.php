<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * A {{{...}}} tag Page Forms does not know. The form shows such a tag as escaped text,
 * which is why the original text is kept.
 */
class UnknownTagSpec extends TagSpec {

	public const TYPE = 'unknown';

	private string $raw;

	/**
	 * @param list<string> $components
	 * @param string $raw The tag as written, including the braces
	 */
	public function __construct( array $components, string $raw = '' ) {
		parent::__construct( $components );
		$this->raw = $raw;
	}

	public function getRaw(): string {
		return $this->raw;
	}

	public function toWikitext(): string {
		return $this->raw !== '' ? $this->raw : parent::toWikitext();
	}

	public function toArray(): array {
		return parent::toArray() + [ 'raw' => $this->raw ];
	}

	public static function fromArray( array $data ): static {
		return new static( $data['components'], $data['raw'] );
	}
}
