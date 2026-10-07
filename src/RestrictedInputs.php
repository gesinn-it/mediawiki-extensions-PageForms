<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * The inputs of a form that the current user may not edit, and the means to take their
 * values out of a request so that a request to the API cannot change what the form
 * would have shown as disabled.
 */
class RestrictedInputs {

	/** @var array<string, list<string>> Field names by template key */
	private array $fields = [];

	/** @var list<string> */
	private array $sections = [];

	private bool $freeText = false;

	public function addField( string $templateKey, string $field ): void {
		$this->fields[$templateKey][] = $field;
	}

	public function addSection( string $name ): void {
		$this->sections[] = $name;
	}

	public function restrictFreeText(): void {
		$this->freeText = true;
	}

	public function isEmpty(): bool {
		return $this->fields === [] && $this->sections === [] && !$this->freeText;
	}

	/**
	 * The request without the values of the restricted inputs: a field's value, its "+" and "-"
	 * modifier forms and its mapping marker, in a single-instance template as well as in every
	 * instance of a multiple-instance one.
	 *
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public function removeFrom( array $options ): array {
		foreach ( $this->fields as $templateKey => $fields ) {
			if ( !is_array( $options[$templateKey] ?? null ) ) {
				continue;
			}
			$options[$templateKey] = $this->removeFieldsFrom( $options[$templateKey], $fields );
			foreach ( $options[$templateKey] as $key => $instance ) {
				if ( is_array( $instance ) && $key !== 'map_field' ) {
					$options[$templateKey][$key] = $this->removeFieldsFrom( $instance, $fields );
				}
			}
		}
		foreach ( $this->sections as $name ) {
			unset( $options['_section'][$name] );
		}
		if ( $this->freeText ) {
			unset( $options['pf_free_text'] );
		}
		return $options;
	}

	/**
	 * @param array<string, mixed> $values
	 * @param list<string> $fields
	 * @return array<string, mixed>
	 */
	private function removeFieldsFrom( array $values, array $fields ): array {
		foreach ( $fields as $field ) {
			unset( $values[$field], $values[$field . '+'], $values[$field . '-'], $values['map_field'][$field] );
		}
		return $values;
	}
}
