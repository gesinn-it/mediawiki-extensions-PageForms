<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;

/**
 * The value that a field of a form has when the form is shown: what the request says, with the
 * labels of a mapped field turned back into values, or else the default or the preloaded value.
 *
 * @ingroup PF
 */
class FormFieldCurrentValue {

	private FormField $field;

	public function __construct( FormField $field ) {
		$this->field = $field;
	}

	/**
	 * @param array|null $template_instance_query_values The request values of the template instance
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param bool $all_instances_printed
	 * @param string|null &$val_modifier Set to "+" or "-" if the value is to be added to or removed
	 *   from the existing ones
	 * @return string|array|null
	 */
	public function get(
		$template_instance_query_values,
		$form_submitted,
		$source_is_page,
		$all_instances_printed,
		&$val_modifier = null
	) {
		// Get the value from the request, if
		// it's there, and if it's not an array.
		$delimiter = $this->field->getFieldArg( 'delimiter' );

		if ( PFUtils::isTranslateEnabled() &&
			$this->field->hasFieldArg( 'translatable' ) && $this->field->getFieldArg( 'translatable' ) ) {
			$this->addTranslateTagToQueryValue( $template_instance_query_values );
		}

		if ( is_array( $template_instance_query_values ) ) {
			$field_query_val = $this->findQueryValue( $template_instance_query_values, $val_modifier );

			if ( $form_submitted && $field_query_val != '' ) {
				return $this->valueFromSubmittedForm( $field_query_val, $template_instance_query_values );
			}
			if ( !$form_submitted && $field_query_val != '' ) {
				if ( is_array( $field_query_val ) ) {
					$str = FormInputValues::getStringFromPassedInArray( $field_query_val, $delimiter );
				} else {
					$str = $field_query_val;
				}
				return str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $str );

			}
		}

		return $this->defaultValue( $form_submitted, $source_is_page, $all_instances_printed );
	}

	/**
	 * Remove the translate tags ("<!--T:X-->") that are left without content.
	 *
	 * @param string &$value
	 */
	public function cleanupTranslateTags( &$value ): void {
		$i = 0;
		// If there are two tags ("<!--T:X-->") with no content between them, remove the first one.
		while ( preg_match( '/(<!--T:[0-9]+-->\s*)(<!--T:[0-9]+-->)/', $value, $matches ) ) {
			$value = str_replace( $matches[1], '', $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}

		$i = 0;
		// If there is a tag ("<!--T:X-->") at the end, with nothing after, remove it.
		while ( preg_match( '#(<!--T:[0-9]+-->\s*)(</translate>)#', $value, $matches ) ) {
			$value = str_replace( $matches[1], '', $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}

		$i = 0;
		// If there is a tag ("<!--T:X-->") not separated from a template call ("{{ ..."),
		// add a new line between them.
		while ( preg_match( '/(<!--T:[0-9]+-->)({{[^}]+}}\s*)/', $value, $matches ) ) {
			$value = str_replace( $matches[1], $matches[1] . "\n", $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}
	}

	/**
	 * If this is a translatable field, and both it and its corresponding translate ID tag are
	 * passed in, add the tag to the value.
	 *
	 * @param array|null &$template_instance_query_values
	 */
	private function addTranslateTagToQueryValue( &$template_instance_query_values ): void {
		$fieldName = $this->field->getTemplateField()->getFieldName();
		$fieldNameTag = $fieldName . '_translate_number_tag';
		if ( isset( $template_instance_query_values[$fieldName] ) &&
			isset( $template_instance_query_values[$fieldNameTag] ) ) {
			$tag = $template_instance_query_values[$fieldNameTag];
			if ( !preg_match( '/( |\n)$/', $tag ) ) {
				$tag .= "\n";
			}
			if ( trim( $template_instance_query_values[$fieldName] ) ) {
				// Don't add the tag if field content has been removed.
				$template_instance_query_values[$fieldName] = $tag . $template_instance_query_values[$fieldName];
			}
		}
		// If user has deleted some content, and there is some translate tag
		// ("<!--T:X-->") with no content, remove the tag.
		if ( isset( $template_instance_query_values[$fieldName] ) ) {
			$this->cleanupTranslateTags( $template_instance_query_values[$fieldName] );
		}
	}

	/**
	 * The value the request has for the field.
	 *
	 * @param array $template_instance_query_values
	 * @param string|null &$val_modifier Set to "+" or "-" if the value is to be added to or removed
	 *   from the existing ones
	 * @return string|array|null
	 */
	private function findQueryValue( array $template_instance_query_values, &$val_modifier ) {
		$field_name = $this->field->getTemplateField()->getFieldName();
		$escaped_field_name = str_replace( "'", "\'", $field_name );

		// If the field name contains an apostrophe, the array
		// sometimes has the apostrophe escaped, and sometimes
		// not. For now, just check for both versions.
		// @TODO - figure this out.
		$field_query_val = null;
		if ( array_key_exists( $escaped_field_name, $template_instance_query_values ) ) {
			$field_query_val = $template_instance_query_values[$escaped_field_name];
		} elseif ( array_key_exists( $field_name, $template_instance_query_values ) ) {
			$field_query_val = $template_instance_query_values[$field_name];
		} else {
			// The next checks are to allow for support for adding a value to ("+") and removing
			// one from ("-") the existing values with autoedit.
			if ( array_key_exists( "$field_name+", $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values["$field_name+"];
				$val_modifier = '+';
			} elseif ( array_key_exists( "$field_name-", $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values["$field_name-"];
				$val_modifier = '-';
			}
		}
		return $field_query_val;
	}

	/**
	 * The value of a field of a submitted form, with the labels turned back into values if the
	 * field maps them.
	 *
	 * @param string|array $field_query_val
	 * @param array $template_instance_query_values
	 * @return string
	 */
	private function valueFromSubmittedForm( $field_query_val, array $template_instance_query_values ) {
		$field_name = $this->field->getTemplateField()->getFieldName();
		$delimiter = $this->field->getFieldArg( 'delimiter' );

		$map_field = false;
		if ( array_key_exists( 'map_field', $template_instance_query_values ) &&
			array_key_exists( $field_name, $template_instance_query_values['map_field'] ) ) {
			$map_field = true;
		}
		if ( is_array( $field_query_val ) ) {
			$cur_values = [];
			if ( $map_field && $this->field->hasOwnPossibleValues() ) {
				foreach ( $field_query_val as $key => $val ) {
					$val = $this->field->autocapitalize( trim( $val ) );
					if ( $key === 'is_list' ) {
						$cur_values[$key] = $val;
					} else {
						$cur_values[] = $this->field->labelToValue( $val );
					}
				}
			} else {
				foreach ( $field_query_val as $key => $val ) {
					$cur_values[$key] = $this->field->autocapitalize( $val );
				}
			}
			return FormInputValues::getStringFromPassedInArray( $cur_values, $delimiter );
		}

		$field_query_val = $this->field->autocapitalize( trim( $field_query_val ) );
		if ( $map_field && $this->field->hasOwnPossibleValues() ) {
			// this should be replaced with an input type neutral way of
			// figuring out if this scalar input type is a list
			if ( $this->field->getInputType() == "tokens" ) {
				$this->field->setIsList( true );
			}
			if ( $this->field->isList() ) {
				$cur_values = array_map( 'trim', explode( $delimiter, $field_query_val ) );
				foreach ( $cur_values as $key => $val ) {
					$cur_values[$key] = $this->field->labelToValue( $val );
				}
				return implode( $delimiter, $cur_values );
			}
			return $this->field->labelToValue( $field_query_val );
		}
		return $field_query_val;
	}

	/**
	 * The default or preloaded value of the field, if the form is not submitted and the page has
	 * no value for it.
	 *
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param bool $all_instances_printed
	 * @return string|null
	 */
	private function defaultValue( $form_submitted, $source_is_page, $all_instances_printed ) {
		// Default values in new instances of multiple-instance
		// templates should always be set, even for existing pages.
		$part_of_multiple = $this->field->hasFieldArg( 'part_of_multiple' );
		$printing_starter_instance = $part_of_multiple && $all_instances_printed;
		if ( ( !$source_is_page || $printing_starter_instance ) && !$form_submitted ) {
			if ( $this->field->getDefaultValue() !== null ) {
				// Set to the default value specified in the form, if it's there.
				return $this->field->getDefaultValue();
			} elseif ( $this->field->getPreloadPage() ) {
				return FormCache::getPreloadedText( $this->field->getPreloadPage() );
			}
		}

		// We're still here...
		return null;
	}
}
