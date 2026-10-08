<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * A {{{...}}} tag of a form definition: its name and its arguments, as written.
 *
 * Nothing here depends on the current user, the request or the page. Whether the current
 * user is restricted by a "restricted" argument, what "default=" evaluates to or which values
 * a field offers are all resolved later, from the arguments kept here.
 */
abstract class TagSpec implements FormElement {

	/** The type name of a kind of tag, set by each subclass */
	public const TYPE = '';

	/** @var list<string> */
	private array $components;

	/**
	 * @param list<string> $components The tag split at its top-level pipes; the first one is the tag name
	 */
	public function __construct( array $components ) {
		$this->components = array_values( $components );
	}

	/**
	 * @return list<string>
	 */
	public function getComponents(): array {
		return $this->components;
	}

	public function getTagName(): string {
		return trim( $this->components[0] );
	}

	/**
	 * The second component: the template, field or section name, or the standard input
	 * type. It may still contain wikitext such as {{PAGENAME}}, which only the parser can resolve.
	 */
	public function getRawName(): string {
		return $this->components[1] ?? '';
	}

	/**
	 * The arguments after the name, by argument name. A bare flag such as "restricted" has
	 * the value ''. If an argument is given twice, the last one wins, as it does when the
	 * form is rendered.
	 *
	 * @return array<string, string>
	 */
	public function getArgs(): array {
		$args = [];
		$count = count( $this->components );
		for ( $i = 2; $i < $count; $i++ ) {
			$parts = array_map( 'trim', explode( '=', $this->components[$i], 2 ) );
			$args[$this->normalizeArgName( $parts[0] )] = $parts[1] ?? '';
		}
		return $args;
	}

	/**
	 * The value of the argument $name: '' for a bare flag, null if the tag does not carry it.
	 */
	public function getArg( string $name ): ?string {
		return $this->getArgs()[$this->normalizeArgName( $name )] ?? null;
	}

	/**
	 * Whether the tag carries the argument $name, with or without a value.
	 */
	public function hasArg( string $name ): bool {
		return $this->getArg( $name ) !== null;
	}

	/**
	 * Whether the tag carries the argument $name as a bare flag, without an "=".
	 */
	public function isFlag( string $name ): bool {
		$name = $this->normalizeArgName( $name );
		$count = count( $this->components );
		for ( $i = 2; $i < $count; $i++ ) {
			$parts = array_map( 'trim', explode( '=', $this->components[$i], 2 ) );
			if ( count( $parts ) === 1 && $this->normalizeArgName( $parts[0] ) === $name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Who may edit what this tag defines, as far as the tag restricts it: null if the tag is not
	 * "restricted", [] for a bare "restricted" (the right to edit restricted fields) and the list
	 * of group names for "restricted=group1,group2".
	 *
	 * @return list<string>|null
	 */
	public function getRestriction(): ?array {
		$value = $this->getArg( 'restricted' );
		if ( $value === null ) {
			return null;
		}
		return $value === '' ? [] : array_map( 'trim', explode( ',', $value ) );
	}

	protected function normalizeArgName( string $name ): string {
		return $name;
	}

	public function toWikitext(): string {
		return '{{{' . implode( '|', $this->components ) . '}}}';
	}

	public function toArray(): array {
		return [ 'type' => static::TYPE, 'components' => $this->components ];
	}

	/**
	 * Rebuild a tag from what toArray() returned. Every concrete kind of tag implements this, so
	 * that no code instantiates the abstract class.
	 *
	 * @param array<string, mixed> $data
	 * @return static
	 */
	abstract public static function fromArray( array $data ): static;
}
