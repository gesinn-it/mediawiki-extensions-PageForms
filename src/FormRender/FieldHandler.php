<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormRender;

use Html;
use MediaWiki\Extension\PageForms\FieldValueResolver;
use MediaWiki\Extension\PageForms\FormDefinition\FieldSpec;
use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\FormFieldHtmlBuilder;
use MediaWiki\Extension\PageForms\FormPlaceholder;
use MediaWiki\Extension\PageForms\FormRenderContext;
use MediaWiki\Extension\PageForms\MappingLabels;
use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateInForm;
use MediaWiki\MediaWikiServices;
use MWException;
use PFTextAreaInput;
use PFUtils;

/**
 * The {{{field}}} tag: finds the current value of the field and adds its input.
 *
 * Two values of a field are kept while it is handled:
 * - $cur_value is what the input shows. It starts as the value from the request, from
 *   #formredlink or from the template call on the page being edited (a string, an array for a
 *   list, '' or null if there is none). For a field with a mapping or display titles it is then
 *   turned into labels, for a default value it is replaced, and in the starter instance of a
 *   multiple-instance template it is null.
 * - $cur_value_in_template is what is written into the template call of the page: the same value
 *   before it is turned into labels, null for a field that holds an embedded template, and what the
 *   PageForms::CreateFormField hook leaves of it when the form was submitted.
 */
class FieldHandler implements ElementHandler {

	private FormFieldHtmlBuilder $formFieldHtmlBuilder;

	private MappingLabels $mappingLabels;

	private FieldValueResolver $fieldValueResolver;

	public function __construct(
		FormFieldHtmlBuilder $formFieldHtmlBuilder,
		MappingLabels $mappingLabels,
		FieldValueResolver $fieldValueResolver
	) {
		$this->formFieldHtmlBuilder = $formFieldHtmlBuilder;
		$this->mappingLabels = $mappingLabels;
		$this->fieldValueResolver = $fieldValueResolver;
	}

	/**
	 * @param FormElement $element
	 * @param FormRenderContext $context
	 * @throws MWException if the tag has no field name
	 */
	public function handle( FormElement $element, FormRenderContext $context ): void {
		if ( !$element instanceof FieldSpec ) {
			return;
		}
		$new_text = '';
		// If the template is null, that (hopefully)
		// means we're handling the free text field.
		// Make the template a dummy variable.
		if ( $context->tif == null ) {
			$this->setUpFreeTextTemplate( $context );
		}
		// We get the field name both here
		// and in the FormField constructor,
		// because FormField isn't equipped
		// to deal with the #freetext# hack,
		// among others.
		if ( count( $element->getComponents() ) < 2 ) {
			throw new MWException(
				'<div class="error">Error in form definition:' .
				' \'field\' tag is missing the field name.</div>'
			);
		}
		$field_name = trim( $element->getComponents()[1] );
		$form_field = FormField::newFromFormFieldTag(
			$element,
			$context->template, $context->tif, $context->formIsDisabled, $context->user, $context->parser,
			$this->mappingLabels
		);
		// For special displays, add in the
		// form fields, so we know the data
		// structure.
		if ( ( $context->tif->getDisplay() == 'table'
				&& ( !$context->tif->allowsMultiple() || $context->tif->getInstanceNum() == 0 ) ) ||
			( $context->tif->getDisplay() == 'spreadsheet'
				&& $context->tif->allowsMultiple() && $context->tif->getInstanceNum() == 0 ) ||
			( $context->tif->getDisplay() == 'calendar'
				&& $context->tif->allowsMultiple() && $context->tif->getInstanceNum() == 0 ) ) {
			$context->tif->addField( $form_field );
		}
		$cur_value = $this->currentValue( $form_field, $field_name, $context );

		// Handle the free text field.
		if ( $field_name == '#freetext#' ) {
			// If there was no preloading, this will just be blank.
			$context->preloadedFreeText = $cur_value;
			// Add placeholders for the free text in both the form and
			// the page, using <free_text> tags - once all the free text
			// is known (at the end), it will get substituted in.
			if ( $form_field->isHidden() ) {
				$new_text = Html::hidden( 'pf_free_text', '!free_text!' );
			} else {
				$new_text = $this->freeTextInputHtml( $form_field, $cur_value, $context );
			}
			$context->freeTextWasIncluded = true;
			$context->wikiPage->addFreeTextSection();
		}

		if ( $context->tif->getTemplateName() === '' || $field_name == '#freetext#' ) {
			$context->section .= $new_text;
		} else {
			if ( $form_field->holdsTemplate() ) {
				// If this field holds an embedded template and the value is not
				// an array, there are no instances of the template — set the value
				// to null to avoid carrying over whatever is currently on the page.
				$cur_value_in_template = null;
			} else {
				$cur_value_in_template = $cur_value;
			}

			// If we're creating the page name from a formula based on
			// form values, see if the current input is part of that formula,
			// and if so, substitute in the actual value.
			if ( $context->formSubmitted && $context->generatedPageName !== '' ) {
				// This line appears to be unnecessary.
				// $context->generatedPageName = str_replace('.', '_', $context->generatedPageName);
				$context->generatedPageName = str_replace( ' ', '_', $context->generatedPageName ?? '' );
				$escaped_input_name = str_replace( ' ', '_', $form_field->getInputName() ?? '' );
				$context->generatedPageName = str_ireplace(
					"<$escaped_input_name>", (string)( $cur_value_in_template ?? '' ),
					$context->generatedPageName
				);
				// Once the substitution is done, replace underlines back
				// with spaces.
				$context->generatedPageName = str_replace( '_', ' ', $context->generatedPageName );
			}
			$delimiter = $form_field->getFieldArg( 'delimiter' );
			if ( $cur_value !== '' &&
				( $form_field->hasFieldArg( 'mapping template' ) ||
				$form_field->hasFieldArg( 'mapping property' ) ||
				$form_field->getUseDisplayTitle() ) ) {
				// If the input type is "tokens', the value is not
				// an array, but the delimiter still needs to be set.
				if ( !is_array( $cur_value ) ) {
					if ( $form_field->isList() ) {
						$delimiter = $form_field->getFieldArg( 'delimiter' );
					} else {
						$delimiter = null;
					}
				}
				$cur_value = $form_field->valueStringToLabels( $cur_value, $delimiter );
			}

			// Call hooks - unfortunately this has to be split into two
			// separate calls, because of the different variable names in
			// each case.
			// @TODO - should it be $cur_value for both cases? Or should the
			// hook perhaps modify both variables?
			if ( $context->formSubmitted ) {
				MediaWikiServices::getInstance()->getHookContainer()->run(
					'PageForms::CreateFormField', [ &$form_field, &$cur_value_in_template, true ]
				);
			} else {
				$this->formFieldHtmlBuilder->createFormFieldTranslateTag(
					$context->template, $context->tif, $form_field, $cur_value
				);
				MediaWikiServices::getInstance()->getHookContainer()->run(
					'PageForms::CreateFormField', [ &$form_field, &$cur_value, false ]
				);
			}
			// if this is not part of a 'multiple' template, increment the
			// tab index (used for correct tabbing)
			if ( !$form_field->hasFieldArg( 'part_of_multiple' ) ) {
				$context->counters->tabIndex++;
			}
			// increment the field number regardless
			$context->counters->fieldNum++;
			if ( $context->sourceIsPage && !$context->tif->allInstancesPrinted() ) {
				// If the source is a page, don't use the default
				// values - except for newly-added instances of a
				// multiple-instance template.
			} elseif ( $form_field->getDefaultValue() !== null ) {
				[ $cur_value, $cur_value_in_template ] = $this->fieldValueResolver->resolveDefaultValue(
					$form_field, (string)$cur_value, (string)$cur_value_in_template,
					(bool)$context->tif->allowsMultiple(), $context->formSubmitted, $context->user
				);
			}

			// If all instances have been
			// printed, that means we're
			// now printing a "starter"
			// div - set the current value
			// to null, unless it's the
			// default value.
			// (Ideally it wouldn't get
			// set at all, but that seems a
			// little harder.)
			if ( $context->tif->allInstancesPrinted() && $form_field->getDefaultValue() == null ) {
				$cur_value = null;
			}

			$new_text = $this->formFieldHtmlBuilder->formFieldHTML(
				$form_field, $cur_value, $context->parser, $context->counters
			);
			$new_text .= $form_field->additionalHTMLForInput(
				$cur_value, $field_name, $context->tif->getTemplateName()
			);

			if ( $new_text ) {
				$context->wikiPage->addTemplateParam(
					$context->templateName, $context->tif->getInstanceNum(), $field_name,
					$cur_value_in_template
				);
				$context->section .= $new_text;
			}
		}

		if ( $context->tif->allowsMultiple() && !$context->tif->allInstancesPrinted() ) {
			$wordForYes = PFUtils::getWordForYesOrNo( true );
			if ( $form_field->getInputType() == 'checkbox' ) {
				if ( strtolower( (string)$cur_value ) == strtolower( $wordForYes )
					|| strtolower( (string)$cur_value ) == 'yes' || $cur_value == '1' ) {
					$cur_value = true;
				} else {
					$cur_value = false;
				}
			}
		}

		if ( $context->tif->getDisplay() != null
			&& ( !$context->tif->allowsMultiple() || !$context->tif->allInstancesPrinted() ) ) {
			$context->tif->addGridValue( $field_name, $cur_value );
		}
	}

	/**
	 * A field outside of any template is the free text field: use a dummy template for it, and
	 * take the free text from the query string if it was set.
	 *
	 * @param FormRenderContext $context
	 */
	private function setUpFreeTextTemplate( FormRenderContext $context ): void {
		$context->template = new Template( null, [] );
		// Get free text from the query string, if it was set.
		if ( $context->request->getCheck( 'free_text' ) ) {
			$standard_input = $context->request->getArray( 'standard_input', [] );
			$standard_input['#freetext#'] = $context->request->getVal( 'free_text' );
			$context->request->setVal( 'standard_input', $standard_input );
		}
		$context->tif = TemplateInForm::create( 'standard_input', null, null, null, [] );
		$context->tif->setFieldValuesFromSubmit( $context->request );
	}

	/**
	 * The value the field has when the form is shown, from the sources in order of priority:
	 * the values of the request (or of #formredlink) with a val_modifier applied to them,
	 * and, when a page is edited, the value of the field in the template call on that page.
	 * The value is '' or null if there is none, a string, or an array for a list.
	 *
	 * @param FormField $form_field
	 * @param string $field_name
	 * @param FormRenderContext $context
	 * @return string|array|null
	 */
	private function currentValue( FormField $form_field, string $field_name, FormRenderContext $context ) {
		$val_modifier = null;
		if ( $context->isAutocreate ) {
			$values_from_query = $context->autocreateQuery[$context->tif->getTemplateName()] ?? [];
			$cur_value = $form_field->getCurrentValue(
				$values_from_query, $context->formSubmitted, $context->sourceIsPage,
				$context->tif->allInstancesPrinted(), $val_modifier
			);
		} else {
			$cur_value = $form_field->getCurrentValue(
				$context->tif->getValuesFromSubmit(), $context->formSubmitted, $context->sourceIsPage,
				$context->tif->allInstancesPrinted(), $val_modifier
			);
		}
		$delimiter = $form_field->getFieldArg( 'delimiter' );
		if ( $form_field->holdsTemplate() ) {
			$context->placeholderFields[] = FormPlaceholder::format(
				$context->tif->getTemplateName(), $field_name
			);
		}

		if ( $val_modifier !== null ) {
			$page_value = $context->tif->getValuesFromPage()[$field_name] ?? '';
			$cur_value = $this->fieldValueResolver->applyValModifier(
				(string)$cur_value, $val_modifier, (string)$page_value, $delimiter
			);
			$context->tif->changeFieldValues( $field_name, $cur_value, $delimiter );
		}
		// If the user is editing a page, and that page contains a call to
		// the template being processed, get the current field's value
		// from the template call
		if ( $context->sourceIsPage && ( $context->tif->getFullTextInPage() != '' )
			&& !$context->formSubmitted ) {
			if ( $context->tif->hasValueFromPageForField( $field_name ) ) {
				// Get value, and remove it,
				// so that at the end we
				// can have a list of all
				// the fields that weren't
				// handled by the form.
				$cur_value = $context->tif->takeValueFromPage(
					$field_name, $form_field->holdsTemplate(), $context->existingPageContent
				);
			}
		}

		return $cur_value;
	}

	/**
	 * The input of the free text field: a text area that shows the free text once it is known,
	 * which is not the case until all of the form definition is processed.
	 *
	 * @param FormField $form_field
	 * @param string|array|null $cur_value
	 * @param FormRenderContext $context
	 * @return string
	 */
	private function freeTextInputHtml( FormField $form_field, $cur_value, FormRenderContext $context ): string {
		$context->counters->tabIndex++;
		$context->counters->fieldNum++;
		if ( $cur_value === '' || $cur_value === null ) {
			$default_value = '!free_text!';
		} else {
			$default_value = $cur_value;
		}
		$freeTextInput = new PFTextAreaInput(
			$input_number = null, $default_value, 'pf_free_text',
			( $context->formIsDisabled || $form_field->isRestricted() ),
			$form_field->getFieldArgs()
		);
		$freeTextInput->addJavaScript();
		$new_text = $freeTextInput->getHtmlText();
		if ( $form_field->hasFieldArg( 'edittools' ) ) {
			// borrowed from EditPage::showEditTools()
			$edittools_text = $context->parser->recursiveTagParse(
				wfMessage( 'edittools', [ 'content' ] )->text()
			);

			$new_text .= <<<END
<div class="mw-editTools">
$edittools_text
</div>

END;
		}

		return $new_text;
	}
}
