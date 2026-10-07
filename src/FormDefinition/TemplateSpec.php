<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * A {{{for template|...}}} tag. The block it opens runs up to the matching {{{end template}}}
 * and holds the fields defined in between, in form order.
 */
class TemplateSpec extends TagSpec {

	public const TYPE = 'for template';

	/** @var list<FieldSpec> */
	private array $fields = [];

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
	 * Names of the fields the form defines for this template, in form order.
	 *
	 * @return list<string>
	 */
	public function getFieldNames(): array {
		return array_map( static fn ( FieldSpec $field ): string => $field->getName(), $this->fields );
	}

	public function isMultiple(): bool {
		return in_array( 'multiple', array_map( 'trim', $this->getComponents() ), true );
	}

	public function isStrict(): bool {
		return in_array( 'strict', array_map( 'trim', $this->getComponents() ), true );
	}

	public function getLabel(): ?string {
		return $this->getArg( 'label' );
	}

	public function getIntro(): ?string {
		return $this->getArg( 'intro' );
	}

	public function getMinimumInstances(): ?string {
		return $this->getArg( 'minimum instances' );
	}

	public function getMaximumInstances(): ?string {
		return $this->getArg( 'maximum instances' );
	}

	public function getAddButtonText(): ?string {
		return $this->getArg( 'add button text' );
	}

	public function getDisplay(): ?string {
		return $this->getArg( 'display' );
	}

	/**
	 * The "embed in field=Template[field]" argument.
	 *
	 * @return array{0: string, 1: string}|null Template and field name, null if the argument is
	 *   missing or not of the form Template[field]
	 */
	public function getEmbedInField(): ?array {
		$value = $this->getArg( 'embed in field' );
		if ( $value !== null && preg_match( '/\s*(.*)\[(.*)\]\s*/', $value, $matches ) ) {
			return [ $matches[1], $matches[2] ];
		}
		return null;
	}

	public function getHeight(): ?string {
		return $this->getArg( 'height' );
	}

	public function getDisplayedFieldsWhenMinimized(): ?string {
		return $this->getArg( 'displayed fields when minimized' );
	}

	public function getEventTitleField(): ?string {
		return $this->getArg( 'event title field' );
	}

	public function getEventDateField(): ?string {
		return $this->getArg( 'event date field' );
	}

	public function getEventStartDateField(): ?string {
		return $this->getArg( 'event start date field' );
	}

	public function getEventEndDateField(): ?string {
		return $this->getArg( 'event end date field' );
	}
}
