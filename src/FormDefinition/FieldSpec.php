<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * One {{{field|...}}} tag of a form definition, or the "free text" standard input,
 * without any value and without anything that depends on the current user or request.
 *
 * Whether the current user may edit a restricted field, what a "default=" evaluates to
 * or which values a field offers are all resolved later, from the flags kept here.
 */
class FieldSpec {

	public const FREE_TEXT_NAME = '#freetext#';

	/** @var list<string> Raw tag components, the first one being "field". */
	private array $components;

	/**
	 * @param list<string> $components
	 */
	public function __construct( array $components ) {
		$this->components = array_values( $components );
	}

	/**
	 * @param string $name
	 * @return self
	 */
	public static function freeText( string $name = self::FREE_TEXT_NAME ): self {
		return new self( [ 'field', $name ] );
	}

	public function getName(): string {
		return trim( $this->components[1] ?? '' );
	}

	public function isFreeText(): bool {
		return $this->getName() === self::FREE_TEXT_NAME;
	}

	/**
	 * @return list<string>
	 */
	public function getComponents(): array {
		return $this->components;
	}

	/**
	 * Whether the tag carries the argument $name, with or without a value.
	 */
	public function hasArg( string $name ): bool {
		return $this->getArg( $name ) !== null;
	}

	/**
	 * The value of the argument $name: '' for a bare flag such as "restricted",
	 * null when the tag does not carry it.
	 */
	public function getArg( string $name ): ?string {
		$count = count( $this->components );
		for ( $i = 2; $i < $count; $i++ ) {
			$parts = array_map( 'trim', explode( '=', $this->components[$i], 2 ) );
			if ( $parts[0] === $name ) {
				return $parts[1] ?? '';
			}
		}
		return null;
	}

	/**
	 * Whether the field is marked "restricted". Whether that restricts the current
	 * user is decided by the caller.
	 */
	public function isRestricted(): bool {
		return $this->hasArg( 'restricted' );
	}

	public function holdsTemplate(): bool {
		return $this->hasArg( 'holds template' );
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

	/**
	 * @return array{components: list<string>}
	 */
	public function toArray(): array {
		return [ 'components' => $this->components ];
	}

	/**
	 * @param array{components: list<string>} $data
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		return new self( $data['components'] );
	}
}
