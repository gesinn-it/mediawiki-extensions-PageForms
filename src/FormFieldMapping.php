<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;

/**
 * Replaces the possible values of a field with their labels, for the "mapping template" and
 * "mapping property" arguments of the field.
 *
 * @ingroup PF
 */
class FormFieldMapping {

	private FormField $field;

	public function __construct( FormField $field ) {
		$this->field = $field;
	}

	/**
	 * @param string $mappingType 'template' or 'property'
	 */
	public function apply( string $mappingType ): void {
		if ( $mappingType == 'template' ) {
			$this->withTemplate();
		} elseif ( $mappingType == 'property' ) {
			$this->withProperty();
		}

		$this->field->setPossibleValues( MappingLabels::disambiguate( $this->field->getOwnPossibleValues() ) );
	}

	/**
	 * Get an array of labels from an array of values given a mapping template.
	 */
	public function withTemplate(): void {
		$this->field->setPossibleValues( $this->field->getMappingLabels()->forTemplate(
			$this->field->getFieldArg( 'mapping template' ), $this->keys()
		) );
	}

	/**
	 * Get an array of labels from an array of values given a mapping property.
	 *
	 * @param \SMW\Store|null $store Injectable for testing; defaults to the live SMW store.
	 */
	public function withProperty( $store = null ): void {
		$store ??= PFUtils::getSMWStore();
		if ( $store == null ) {
			return;
		}

		$this->field->setPossibleValues( $this->field->getMappingLabels()->forProperty(
			$store, $this->field->getFieldArg( 'mapping property' ), $this->keys()
		) );
	}

	/**
	 * The values to look the labels up for: the page names, which are the keys of the possible
	 * values when those are display titles.
	 *
	 * @return array<int, string|int>
	 */
	private function keys(): array {
		$keys = [];
		foreach ( $this->field->getOwnPossibleValues() as $index => $value ) {
			$keys[] = $this->field->getUseDisplayTitle() ? $index : $value;
		}
		return $keys;
	}
}
