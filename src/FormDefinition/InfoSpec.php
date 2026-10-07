<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * An {{{info|...}}} tag. Unlike in other tags, the first component is already an argument
 * and argument names are case-insensitive.
 */
class InfoSpec extends TagSpec {

	public const TYPE = 'info';

	public function getArgs(): array {
		$args = [];
		foreach ( array_slice( $this->getComponents(), 1 ) as $component ) {
			$parts = array_map( 'trim', explode( '=', $component, 2 ) );
			$args[$this->normalizeArgName( $parts[0] )] = $parts[1] ?? '';
		}
		return $args;
	}

	protected function normalizeArgName( string $name ): string {
		return strtolower( $name );
	}

	public function getCreateTitle(): ?string {
		return $this->getArg( 'create title' ) ?? $this->getArg( 'add title' );
	}

	public function getEditTitle(): ?string {
		return $this->getArg( 'edit title' );
	}

	public function getQueryTitle(): ?string {
		return $this->getArg( 'query title' );
	}

	public function isFreeTextOnlyInclude(): bool {
		return $this->hasArg( 'includeonly free text' ) || $this->hasArg( 'onlyinclude free text' );
	}

	public function hasQueryFormAtTop(): bool {
		return $this->hasArg( 'query form at top' );
	}
}
