<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * A {{{section|...}}} tag.
 */
class SectionSpec extends TagSpec {

	public const TYPE = 'section';

	public function getName(): string {
		return trim( $this->getRawName() );
	}

	public function getLevel(): ?string {
		return $this->getArg( 'level' );
	}

	public function isMandatory(): bool {
		return $this->hasArg( 'mandatory' );
	}

	public function isHidden(): bool {
		return $this->hasArg( 'hidden' );
	}

	public function isRestricted(): bool {
		return $this->hasArg( 'restricted' );
	}

	public function hidesIfEmpty(): bool {
		return $this->hasArg( 'hide if empty' );
	}
}
