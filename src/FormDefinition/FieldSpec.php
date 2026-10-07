<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * A {{{field|...}}} tag.
 */
class FieldSpec extends TagSpec {

	public const TYPE = 'field';

	public function getName(): string {
		return trim( $this->getRawName() );
	}

	public function isMandatory(): bool {
		return $this->hasArg( 'mandatory' );
	}

	public function isHidden(): bool {
		return $this->hasArg( 'hidden' );
	}

	/**
	 * Whether the field is marked "restricted". Whether that restricts the current
	 * user is decided by the caller.
	 */
	public function isRestricted(): bool {
		return $this->hasArg( 'restricted' );
	}

	public function isList(): bool {
		return $this->hasArg( 'list' );
	}

	public function isUnique(): bool {
		return $this->hasArg( 'unique' );
	}

	public function holdsTemplate(): bool {
		return $this->hasArg( 'holds template' );
	}

	public function getInputType(): ?string {
		return $this->getArg( 'input type' );
	}

	/**
	 * The "default=" value as written, not evaluated.
	 */
	public function getDefault(): ?string {
		return $this->getArg( 'default' );
	}

	/**
	 * @return string|null 'template' or 'property' for a mapped field, null otherwise
	 */
	public function getMappingType(): ?string {
		if ( $this->hasArg( 'mapping template' ) ) {
			return 'template';
		}
		if ( $this->hasArg( 'mapping property' ) ) {
			return 'property';
		}
		return null;
	}
}
