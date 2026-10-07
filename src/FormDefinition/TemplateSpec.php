<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * One {{{for template|...}}} ... {{{end template}}} block of a form definition: the
 * tag that opens it and the fields it defines, in form order.
 */
class TemplateSpec {

	/** @var list<string> Raw tag components, the first one being "for template". */
	private array $components;

	/** @var list<FieldSpec> */
	private array $fields = [];

	/**
	 * @param list<string> $components
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

	/**
	 * The template name as written in the tag. It may still contain wikitext such as
	 * {{PAGENAME}}, which only the parser can resolve.
	 */
	public function getRawName(): string {
		return $this->components[1] ?? '';
	}

	public function addField( FieldSpec $field ): void {
		$this->fields[] = $field;
	}

	/**
	 * @return list<FieldSpec>
	 */
	public function getFields(): array {
		return $this->fields;
	}

	/**
	 * Names of the fields the form defines for this template, in form order,
	 * without the free text input.
	 *
	 * @return list<string>
	 */
	public function getFieldNames(): array {
		$names = [];
		foreach ( $this->fields as $field ) {
			if ( !$field->isFreeText() ) {
				$names[] = $field->getName();
			}
		}
		return $names;
	}

	/**
	 * @return array{components: list<string>, fields: list<array>}
	 */
	public function toArray(): array {
		return [
			'components' => $this->components,
			'fields' => array_map( static fn ( FieldSpec $f ): array => $f->toArray(), $this->fields ),
		];
	}

	/**
	 * @param array{components: list<string>, fields: list<array>} $data
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$spec = new self( $data['components'] );
		foreach ( $data['fields'] as $field ) {
			$spec->addField( FieldSpec::fromArray( $field ) );
		}
		return $spec;
	}
}
