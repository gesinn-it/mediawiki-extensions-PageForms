<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\FormElement;
use MediaWiki\Extension\PageForms\FormRender\ElementHandler;
use MediaWiki\Extension\PageForms\FormRender\PageTextAssembler;
use MediaWiki\Extension\PageForms\FormRender\SectionLayout;

/**
 * The collaborators a FormPrinter is built from.
 *
 * FormPrinterFactory::newParts() creates them with their defaults; a test that needs a fake makes
 * its own.
 *
 * @ingroup PF
 */
final class FormPrinterParts {

	/**
	 * @param InputTypeRegistry $inputTypeRegistry
	 * @param CalendarHtmlBuilder $calendarHtmlBuilder
	 * @param SpreadsheetHtmlBuilder $spreadsheetHtmlBuilder
	 * @param MultipleTemplateHtmlBuilder $multipleTemplateHtmlBuilder
	 * @param FormFieldHtmlBuilder $formFieldHtmlBuilder
	 * @param FormDefParser $formDefParser
	 * @param SectionLayout $sectionLayout
	 * @param PageTextAssembler $pageTextAssembler
	 * @param array<class-string<FormElement>, ElementHandler> $elementHandlers The handler of each type
	 *   of form definition element
	 * @param RenderServices $services
	 */
	public function __construct(
		public readonly InputTypeRegistry $inputTypeRegistry,
		public readonly CalendarHtmlBuilder $calendarHtmlBuilder,
		public readonly SpreadsheetHtmlBuilder $spreadsheetHtmlBuilder,
		public readonly MultipleTemplateHtmlBuilder $multipleTemplateHtmlBuilder,
		public readonly FormFieldHtmlBuilder $formFieldHtmlBuilder,
		public readonly FormDefParser $formDefParser,
		public readonly SectionLayout $sectionLayout,
		public readonly PageTextAssembler $pageTextAssembler,
		public readonly array $elementHandlers,
		public readonly RenderServices $services
	) {
	}
}
