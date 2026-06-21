<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;
use PFFormField;
use PFRegExpInput;
use PFUtils;

/**
 * Assembles the HTML fragment for a single form field within a PageForms form.
 */
class FormFieldHtmlBuilder {

	/** @var array */
	private array $inputTypeHooks;

	/** @var array */
	private array $semanticTypeHooks;

	public function __construct( array $inputTypeHooks, array $semanticTypeHooks ) {
		$this->inputTypeHooks = $inputTypeHooks;
		$this->semanticTypeHooks = $semanticTypeHooks;
	}

	/**
	 * Returns the HTML for a single form field.
	 *
	 * @param PFFormField $form_field
	 * @param string|null $cur_value
	 * @param FormCounters|null $counters
	 * @return string
	 */
	public function formFieldHTML(
		PFFormField $form_field, ?string $cur_value, ?FormCounters $counters = null
	): string {
		global $wgPageFormsFieldNum;
		$fieldNum = $counters !== null ? $counters->fieldNum : $wgPageFormsFieldNum;

		// Also get the actual field, with all the semantic information
		// (type is PFTemplateField, instead of PFFormField)
		$template_field = $form_field->getTemplateField();
		$class_name = null;
		$text = '';

		if ( $form_field->isHidden() ) {
			$attribs = [];
			if ( $form_field->hasFieldArg( 'class' ) ) {
				$attribs['class'] = $form_field->getFieldArg( 'class' );
			}
			$text = Html::hidden( $form_field->getInputName(), $cur_value, $attribs );
			$other_args = [];
		} elseif ( $form_field->getInputType() !== '' &&
				array_key_exists( $form_field->getInputType(), $this->inputTypeHooks ) &&
				$this->inputTypeHooks[$form_field->getInputType()] != null ) {
			// Last argument to constructor should be a hash,
			// merging the default values for this input type with
			// all other properties set in the form definition, plus
			// some semantic-related arguments.
			$hook_values = $this->inputTypeHooks[$form_field->getInputType()];
			$class_name = $hook_values[0];
			$other_args = $form_field->getArgumentsForInputCall( $hook_values[1] );
		} else {
			// The input type is not defined in the form.
			$property_type = $template_field->getPropertyType();
			$is_list = ( $form_field->isList() || $template_field->isList() );
			if ( $property_type !== '' &&
				array_key_exists( $property_type, $this->semanticTypeHooks ) &&
				isset( $this->semanticTypeHooks[$property_type][$is_list] ) ) {
				$hook_values = $this->semanticTypeHooks[$property_type][$is_list];
				$class_name = $hook_values[0];
				$other_args = $form_field->getArgumentsForInputCall( $hook_values[1] );
			} else {
				// Anything else.
				$class_name = 'PFTextInput';
				$other_args = $form_field->getArgumentsForInputCall();
				// Set default size for list inputs.
				if ( $form_field->isList() ) {
					if ( !array_key_exists( 'size', $other_args ) ) {
						$other_args['size'] = 100;
					}
				}
			}
		}

		if ( $class_name !== null ) {
			$form_input = new $class_name(
				$fieldNum, $cur_value, $form_field->getInputName(),
				$form_field->isDisabled(), $other_args
			);

			// If a regex was defined, make this a "regexp" input that wraps
			// around the real one.
			if ( $template_field->getRegex() !== null ) {
				$other_args['regexp'] = $template_field->getRegex();
				$form_input = PFRegExpInput::newFromInput( $form_input );
			}
			$form_input->addJavaScript();
			$text = $form_input->getHtmlText();
		}

		$this->addTranslatableInput( $form_field, $text );
		return $text;
	}

	/**
	 * For translatable fields, strips the translate tags from $cur_value and
	 * stashes the translate-number tag as a field arg so that
	 * addTranslatableInput() can emit the hidden input later.
	 *
	 * @param mixed &$template
	 * @param mixed &$tif
	 * @param PFFormField &$form_field
	 * @param string|null &$cur_value
	 */
	public function createFormFieldTranslateTag(
		&$template,
		&$tif,
		PFFormField &$form_field,
		?string &$cur_value
	): void {
		if ( $cur_value === null || PFUtils::isTranslateEnabled()
			|| !$form_field->hasFieldArg( 'translatable' )
			|| !$form_field->getFieldArg( 'translatable' ) ) {
			return;
		}

		// If translatable, add translatable tags when saving, or remove them for displaying form.
		if ( preg_match( '#^<translate>(.*)</translate>$#', $cur_value, $matches ) ) {
			$cur_value = $matches[1];
		} elseif ( substr( $cur_value, 0, strlen( '<translate>' ) ) == '<translate>'
				&& substr( $cur_value, -1 * strlen( '</translate>' ) ) == '</translate>' ) {
			// For unknown reasons, the pregmatch regex does not work every time !! :(
			$cur_value = substr( $cur_value, strlen( '<translate>' ), -1 * strlen( '</translate>' ) );
		}

		if ( substr( $cur_value, 0, 6 ) == '<!--T:' ) {
			// hide the tag <!-- T:X --> in another input
			// if field does not use VisualEditor?

			if ( preg_match( "/<!-- *T:([a-zA-Z0-9]+) *-->( |\n)/", $cur_value, $matches ) ) {
				// Remove the tag from this input.
				$cur_value = str_replace( $matches[0], '', $cur_value );
				// Add a field arg, to add a hidden input in form with the tag.
				$form_field->setFieldArg( 'translate_number_tag', $matches[0] );
			}
		}
	}

	/**
	 * For translatable fields, appends a hidden input containing the translate tags.
	 *
	 * @param PFFormField $form_field
	 * @param string &$text
	 */
	private function addTranslatableInput( PFFormField $form_field, string &$text ): void {
		if ( PFUtils::isTranslateEnabled() || !$form_field->hasFieldArg( 'translatable' )
			|| !$form_field->getFieldArg( 'translatable' ) ) {
			return;
		}

		if ( $form_field->hasFieldArg( 'translate_number_tag' ) ) {
			$inputName = $form_field->getInputName();
			$pattern = '/\[([^\\]\\]]+)\]$/';
			if ( preg_match( $pattern, $inputName, $matches ) ) {
				$inputName = preg_replace( $pattern, '[${1}_translate_number_tag]', $inputName );
			} else {
				$inputName .= '_translate_number_tag';
			}
			$translateTag = $form_field->getFieldArg( 'translate_number_tag' );
			$text .= "<input type='hidden' name='$inputName' value='$translateTag'/>";
		}
	}
}
