<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

use MediaWiki\Extension\PageForms\TemplateField;

/**
 * The fields TemplateFieldParser has found in the text of a template so far.
 *
 * A field is recorded only the first time its name is found, because some fields are found more
 * than once, especially if they are part of an "#if" call. The names are compared loosely, as
 * they always were, so that "1" and "01" are one field.
 */
class FoundTemplateFields {

	/** @var TemplateField[] */
	private $fields = [];

	/** @var string[] */
	private $names = [];

	public function has( string $fieldName ): bool {
		return in_array( $fieldName, $this->names );
	}

	/**
	 * @param string $fieldName
	 * @param int|string $key The position of the field in the text of the template, or its name
	 * @param TemplateField $field
	 */
	public function add( string $fieldName, $key, TemplateField $field ): void {
		$this->fields[$key] = $field;
		$this->names[] = $fieldName;
	}

	/**
	 * @return TemplateField[] In the order in which the fields were found
	 */
	public function fields(): array {
		return $this->fields;
	}
}
