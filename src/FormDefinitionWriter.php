<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFPageSection;
use PFUtils;
use Title;

/**
 * Writes the wikitext of a form definition from the model of a form: the form itself, its
 * templates, sections and fields. It is what creates a form from a template, for example.
 *
 * @ingroup PF
 */
class FormDefinitionWriter {

	/**
	 * @param Form $form
	 * @param bool $includeFreeText
	 * @param string|null $freeTextLabel Defaults to the message in the content language
	 * @return string
	 */
	public function form( Form $form, bool $includeFreeText = true, ?string $freeTextLabel = null ): string {
		$text = $this->formHeader( $form ) . $this->formInfoTag( $form );
		$text .= '<div id="wikiPreview" style="display: none; padding-bottom: 25px;' .
			' margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>' . "\n\n";
		foreach ( $form->getItems() as $item ) {
			if ( $item['type'] == 'template' ) {
				$text .= $this->template( $item['item'] ) . "\n";
			} elseif ( $item['type'] == 'section' ) {
				$text .= $this->section( $item['item'] ) . "\n";
			}
		}

		if ( $includeFreeText ) {
			$freeTextLabel ??= wfMessage( 'pf_form_freetextlabel' )->inContentLanguage()->text();
			$text .= <<<END
'''$freeTextLabel:'''

{{{standard input|free text|rows=10}}}

END;
		}
		return $text . "</includeonly>\n";
	}

	/**
	 * What is shown on the form page itself, and the start of what is included in other pages.
	 */
	private function formHeader( Form $form ): string {
		$title = Title::makeTitle( PF_NS_FORM, $form->getFormName() );
		$fs = PFUtils::getSpecialPage( 'FormStart' );
		$form_start_url = PFUtils::titleURLString( $fs->getPageTitle() ) . "/" . $title->getPartialURL();
		$form_description = wfMessage( 'pf_form_docu', $form->getFormName(), $form_start_url )
			->inContentLanguage()->text();
		$form_input = "{{#forminput:form=" . str_replace( ',', '\,', $form->getFormName() );
		if ( $form->getAssociatedCategory() !== null ) {
			$form_input .= "|autocomplete on category=" . $form->getAssociatedCategory();
		}
		$form_input .= "}}\n";
		return <<<END
<noinclude>
$form_description

$form_input
</noinclude><includeonly>

END;
	}

	private function formInfoTag( Form $form ): string {
		$info = '';
		if ( $form->getPageNameFormula() !== '' ) {
			$info .= "|page name=" . $form->getPageNameFormula();
		}
		if ( $form->getCreateTitle() !== '' ) {
			$info .= "|create title=" . $form->getCreateTitle();
		}
		if ( $form->getEditTitle() !== '' ) {
			$info .= "|edit title=" . $form->getEditTitle();
		}
		return $info ? "{{{info" . $info . "}}}\n" : '';
	}

	public function template( TemplateInForm $template ): string {
		$allowMultiple = $template->allowsMultiple();
		$text = "{{{for template|" . $template->getTemplateName();
		if ( $allowMultiple ) {
			$text .= "|multiple";
		}
		if ( $template->getLabel() != '' ) {
			$text .= "|label=" . $template->getLabel();
		}
		$text .= "}}}\n";
		// For now, HTML for templates differs for multiple-instance
		// templates; this may change if handling of form definitions
		// gets more sophisticated.
		if ( !$allowMultiple ) {
			$text .= "{| class=\"formtable\"\n";
		}
		$fields = $template->getFields();
		foreach ( $fields as $i => $field ) {
			$text .= $this->field( $field, $allowMultiple, $i == count( $fields ) - 1 );
		}
		if ( !$allowMultiple ) {
			$text .= "|}\n";
		}
		return $text . "{{{end template}}}\n";
	}

	public function section( PFPageSection $section ): string {
		$section_name = $section->getSectionName();
		$section_level = $section->getSectionLevel();
		// Set default section level to 2
		if ( $section_level == '' ) {
			$section_level = 2;
		}
		// display the section headers in wikitext
		$header_string = str_repeat( "=", $section_level );
		$text = $header_string . $section_name . $header_string . "\n";

		$text .= "{{{section|" . $section_name . "|level=" . $section_level;

		if ( $section->isMandatory() ) {
			$text .= "|mandatory";
		} elseif ( $section->isRestricted() ) {
			$text .= "|restricted";
		} elseif ( $section->isHidden() ) {
			$text .= "|hidden";
		}
		foreach ( $section->getSectionArgs() as $arg => $value ) {
			if ( $value === true ) {
				$text .= "|$arg";
			} else {
				$text .= "|$arg=$value";
			}
		}
		return $text . "}}}\n";
	}

	/**
	 * For now, the definition of an individual field depends on whether or not it's part of a
	 * multiple-instance template; this may change if handling of such templates in form
	 * definitions gets more sophisticated.
	 *
	 * @param FormField $field
	 * @param bool $partOfMultiple
	 * @param bool $isLastFieldInTemplate
	 * @return string
	 */
	public function field( FormField $field, bool $partOfMultiple, bool $isLastFieldInTemplate ): string {
		$descPlaceholder = $this->fieldDescription( $field );
		$fieldLabel = $this->fieldLabel( $field );

		if ( $partOfMultiple ) {
			$text = "'''$fieldLabel:''' $descPlaceholder";
		} else {
			$text = "! $fieldLabel: $descPlaceholder\n| ";
		}
		$text .= $this->fieldTag( $field );
		if ( $partOfMultiple ) {
			$text .= "\n";
		} elseif ( !$isLastFieldInTemplate ) {
			$text .= "|-\n";
		}
		return $text;
	}

	/**
	 * The description as a tooltip, if the form asks for one and an extension can show it, and as
	 * a paragraph below the label otherwise.
	 */
	private function fieldDescription( FormField $field ): string {
		$descriptionArgs = $field->getDescriptionArgs();
		if ( !array_key_exists( "Description", $descriptionArgs ) || $descriptionArgs['Description'] == '' ) {
			return '';
		}
		$fieldDesc = $descriptionArgs['Description'];
		$paragraph = '<br><p class="pfFieldDescription"' .
			' style="font-size:0.7em; color:gray;">' . $fieldDesc . '</p>';
		if ( !isset( $descriptionArgs['DescriptionTooltipMode'] ) ) {
			return $paragraph;
		}
		// The wikitext we use for tooltips depends on which other extensions are installed.
		if ( class_exists( 'RegularTooltipsParser' ) ) {
			// RegularTooltips
			return " {{#info-tooltip:$fieldDesc}}";
		} elseif ( defined( 'SMW_VERSION' ) ) {
			// Semantic MediaWiki
			return " {{#info:$fieldDesc}}";
		} elseif ( class_exists( 'SimpleTooltipParserFunction' ) ) {
			// SimpleTooltip
			return " {{#tip-info:$fieldDesc}}";
		}
		// Don't make it a tooltip.
		return $paragraph;
	}

	private function fieldLabel( FormField $field ): string {
		$fieldLabel = $field->getTemplateField()->getLabel();
		if ( $fieldLabel == '' ) {
			$fieldLabel = $field->getTemplateField()->getFieldName();
		}
		$textBeforeField = $field->getDescriptionArgs()['TextBeforeField'] ?? '';
		if ( $textBeforeField != '' ) {
			$fieldLabel = $textBeforeField . ' ' . $fieldLabel;
		}
		return $fieldLabel;
	}

	/**
	 * The {{{field}}} tag.
	 */
	private function fieldTag( FormField $field ): string {
		$text = "{{{field|" . $field->getTemplateField()->getFieldName();
		if ( $field->isHidden() ) {
			$text .= "|hidden";
		} else {
			$inputType = $field->getInputType();
			if ( $inputType !== null && $inputType !== '' ) {
				$text .= "|input type=" . $inputType;
			}
		}
		foreach ( $field->getFieldArgs() as $arg => $value ) {
			if ( $value === true ) {
				$text .= "|$arg";
			} elseif ( $arg === 'uploadable' ) {
				// Are there similar value-less arguments
				// that need to be handled here?
				$text .= "|$arg";
			} else {
				$text .= "|$arg=$value";
			}
		}
		$text .= $this->valuesOfTemplateField( $field );

		if ( $field->isMandatory() ) {
			$text .= "|mandatory";
		} elseif ( $field->isRestricted() ) {
			$text .= "|restricted";
		}
		return $text . "}}}\n";
	}

	/**
	 * Special handling if SMW is not installed - the form has to handle stuff that otherwise
	 * would go in the template.
	 */
	private function valuesOfTemplateField( FormField $field ): string {
		$templateField = $field->getTemplateField();
		$possibleValues = $templateField->getPossibleValues();
		if (
			defined( 'SMW_VERSION' ) ||
			array_key_exists( 'values', $field->getFieldArgs() ) ||
			!is_array( $possibleValues ) ||
			count( $possibleValues ) == 0
		) {
			return '';
		}
		$text = '';
		if ( $field->getInputType() == null ) {
			$text .= $templateField->isList() ? '|input type=checkboxes' : '|input type=dropdown';
		}
		$delimiter = ',';
		if ( $templateField->isList() ) {
			$delimiter = $templateField->getDelimiter();
			if ( $delimiter == '' ) {
				$delimiter = ',';
			}
			// @todo - we need to add a "|delimiter=" param
			// here too, if #template_params is not being
			// called in the template.
		}
		return $text . '|values=' . implode( $delimiter, $possibleValues );
	}
}
