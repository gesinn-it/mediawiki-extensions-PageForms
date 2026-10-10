<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWiki\HookContainer\HookContainer;
use PFUtils;

/**
 * Writes the wikitext of a template, for Special:CreateTemplate and Special:CreateClass.
 *
 * What Template::createText() reports as the text of the template is made here, from the data
 * of the Template. What differs by installation (is SMW or Semantic Internal Objects installed)
 * is a parameter and not a look at the constants, so that each case can be written in a test.
 */
class TemplateWikitextWriter {

	/**
	 * @param HookContainer $hookContainer
	 * @param bool $hasSmw Whether Semantic MediaWiki is installed
	 * @param bool $hasSio Whether Semantic Internal Objects is installed
	 */
	public function __construct(
		private readonly HookContainer $hookContainer,
		private readonly bool $hasSmw,
		private readonly bool $hasSio
	) {
	}

	/**
	 * @param Template $template
	 * @return string
	 */
	public function write( Template $template ): string {
		$fields = $template->getTemplateFields();
		$categoryTag = $this->categoryTag( $template->getCategoryName() );

		// Check whether the user needs the full wikitext instead of #template_display
		if ( $template->isFullWikiText() ) {
			$text = $this->documentation( $template->getName(), $fields );
		} else {
			$text = $this->templateParams( $fields );
			if ( !$this->hasSmw ) {
				return $text . $this->templateDisplay( $template->getFormat() ) . $categoryTag . '</includeonly>';
			}
		}

		$format = TemplateFormats::fromName( $template->getFormat() );
		$internalObject = null;
		if ( $template->getConnectingProperty() ) {
			$internalObject = new InternalObjectCall( $template->getConnectingProperty(), $this->hasSio );
		}
		$set = new SetCall();
		$table = $this->table( $template, $format, $internalObject, $set );

		if ( $internalObject === null ) {
			$table .= $format->trailingNewline();
		} else {
			$text .= $internalObject->render();
		}
		return $text . $set->render() . $table . $categoryTag . "</includeonly>\n";
	}

	/**
	 * The text of one field, with what the hooks put before and after it.
	 *
	 * @param TemplateField $field
	 * @return string
	 */
	public function fieldText( TemplateField $field ): string {
		$text = '';
		$fieldStart = null;
		$this->hookContainer->run( 'PageForms::TemplateFieldStart', [ $field, &$fieldStart ] );
		// @phan-suppress-next-line PhanImpossibleValueComparison -- the hook may set $fieldStart
		if ( $fieldStart != '' ) {
			$text .= "$fieldStart ";
		}

		$text .= $field->createText();

		$fieldEnd = null;
		$this->hookContainer->run( 'PageForms::TemplateFieldEnd', [ $field, &$fieldEnd ] );
		// @phan-suppress-next-line PhanImpossibleValueComparison -- the hook may set $fieldEnd
		if ( $fieldEnd != '' ) {
			$text .= " $fieldEnd";
		}

		return $text;
	}

	/**
	 * @param string|null $categoryName
	 * @return string
	 */
	public function categoryTag( ?string $categoryName ): string {
		if ( $categoryName === '' || $categoryName === null ) {
			return '';
		}
		$namespaceLabels = PFUtils::getContLang()->getNamespaces();
		$categoryNamespace = $namespaceLabels[NS_CATEGORY];
		return "\n[[$categoryNamespace:" . $categoryName . "]]\n";
	}

	/**
	 * The part for the page of the template itself: how to call it, to be copied from there.
	 *
	 * @param string|null $templateName
	 * @param TemplateField[] $fields
	 * @return string
	 */
	private function documentation( ?string $templateName, array $fields ): string {
		$templateHeader = wfMessage( 'pf_template_docu', $templateName )->inContentLanguage()->text();
		$text = <<<END
<noinclude>
$templateHeader
<pre>
END;
		$text .= '{{' . $templateName;
		if ( count( $fields ) > 0 ) {
			$text .= "\n";
		}
		foreach ( $fields as $field ) {
			if ( $field->getFieldName() == '' ) {
				continue;
			}
			$text .= "|" . $field->getFieldName() . "=\n";
		}
		$templateFooter = wfMessage( 'pf_template_docufooter' )->inContentLanguage()->text();
		return $text . <<<END
}}
</pre>
$templateFooter
</noinclude><includeonly>
END;
	}

	/**
	 * The part for the page of the template itself: the description of its fields.
	 *
	 * @param TemplateField[] $fields
	 * @return string
	 */
	private function templateParams( array $fields ): string {
		$text = <<<END
<noinclude>
{{#template_params:
END;
		foreach ( $fields as $i => $field ) {
			if ( $field->getFieldName() == '' ) {
				continue;
			}
			if ( $i > 0 ) {
				$text .= "|";
			}
			$text .= $field->toWikitext();
		}
		return $text . <<<END
}}
</noinclude><includeonly>
END;
	}

	/**
	 * The display of a template without SMW: nothing is stored, so there are no fields to write.
	 *
	 * @param string|null $format
	 * @return string
	 */
	private function templateDisplay( ?string $format ): string {
		$text = "\n{{#template_display:";
		if ( $format != null ) {
			$text .= "_format=" . $format;
		}
		return $text . "}}";
	}

	/**
	 * The fields in the markup of the format, with the aggregating query if there is one.
	 *
	 * The fields that are hidden or that go into an internal object are not part of the table;
	 * they are added to $set and $internalObject.
	 *
	 * @param Template $template
	 * @param TemplateFormat $format
	 * @param InternalObjectCall|null $internalObject
	 * @param SetCall $set
	 * @return string
	 */
	private function table(
		Template $template, TemplateFormat $format, ?InternalObjectCall $internalObject, SetCall $set
	): string {
		$fields = $template->getTemplateFields();
		$table = $format->open();
		foreach ( $fields as $i => $field ) {
			if ( $field->getFieldName() == '' ) {
				continue;
			}
			$table .= $this->fieldRow( $field, $i > 0, $format, $internalObject, $set );
		}

		// Add an inline query to the output text, for
		// aggregation, if a property was specified.
		$aggregatingProperty = $template->getAggregatingProperty();
		if ( $aggregatingProperty !== null && $aggregatingProperty !== '' ) {
			$table .= $format->aggregationHeader( (string)$template->getAggregationLabel(), count( $fields ) > 0 );
			$table .= "{{#ask:[[" . $aggregatingProperty . "::{{SUBJECTPAGENAME}}]]|format=list}}\n";
		}
		return $table . $format->close();
	}

	/**
	 * The label and the value of one field.
	 *
	 * @param TemplateField $field
	 * @param bool $notFirst Whether the field is not the first of the template
	 * @param TemplateFormat $format
	 * @param InternalObjectCall|null $internalObject Gets the field, if the field has a property
	 * @param SetCall $set Gets the field, if it is hidden and has a property
	 * @return string
	 */
	private function fieldRow(
		TemplateField $field, bool $notFirst, TemplateFormat $format, ?InternalObjectCall $internalObject, SetCall $set
	): string {
		$fieldParam = '{{{' . $field->getFieldName() . '|}}}';
		// The same as TemplateField::createText(): the main namespace has no prefix. The id can be
		// a numeric string, as it is where the field is set from a request.
		if ( (int)$field->getNamespace() === NS_MAIN ) {
			$fieldString = $fieldParam;
		} else {
			$fieldString = $field->getNSText() . ':' . $fieldParam;
		}
		$fieldLabel = $field->getLabel();
		if ( $fieldLabel == '' ) {
			$fieldLabel = $field->getFieldName();
		}
		$fieldDisplay = $field->getDisplay();
		$fieldProperty = $field->getSemanticProperty();

		// Header/field label column
		$separator = '';
		$row = '';
		if ( $fieldDisplay === null ) {
			$row .= $format->header( $fieldLabel, $notFirst );
		} elseif ( $fieldDisplay == 'nonempty' ) {
			$row .= $format->nonemptyLead() . '{{#if:' . $fieldParam . '|' .
				$format->nonemptyHeader( $fieldLabel, $notFirst );
			$separator = $format->nonemptySeparator();
		}
		// Value column
		if ( $fieldDisplay != 'hidden' && $fieldDisplay != 'nonempty' ) {
			$row .= $format->valueCell();
		}

		if ( !$fieldProperty || $internalObject !== null ) {
			if ( $separator != '' ) {
				$row .= "$separator ";
			}
			$row .= $this->fieldText( $field );
			if ( $fieldDisplay == 'nonempty' ) {
				$row .= " }}";
			}
			$row .= "\n";
			if ( $fieldProperty && $internalObject !== null ) {
				$internalObject->addField( $fieldProperty, $fieldString, (bool)$field->isList() );
			}
		} elseif ( $fieldDisplay == 'hidden' ) {
			$set->addField( $fieldProperty, $fieldString, (bool)$field->isList() );
		} elseif ( $fieldDisplay == 'nonempty' ) {
			$row .= $format->nonemptyValueCell() . $this->fieldText( $field ) . "\n}}\n";
		} else {
			$row .= $this->fieldText( $field ) . "\n";
		}
		return $row;
	}
}
