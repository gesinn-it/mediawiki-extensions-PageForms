<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\EndTemplateSpec;
use MediaWiki\Extension\PageForms\FormDefinition\FieldSpec;
use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;
use MediaWiki\Extension\PageForms\FormDefinition\InfoSpec;
use MediaWiki\Extension\PageForms\FormDefinition\SectionSpec;
use MediaWiki\Extension\PageForms\FormDefinition\StandardInputSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TemplateSpec;
use MediaWiki\Extension\PageForms\FormDefinition\TextSpec;
use MediaWiki\Extension\PageForms\FormDefinition\UnknownTagSpec;
use MediaWiki\Extension\PageForms\FormRender\ElementHandler;
use MediaWiki\Extension\PageForms\FormRender\EndTemplateHandler;
use MediaWiki\Extension\PageForms\FormRender\FieldHandler;
use MediaWiki\Extension\PageForms\FormRender\InfoHandler;
use MediaWiki\Extension\PageForms\FormRender\PageTextAssembler;
use MediaWiki\Extension\PageForms\FormRender\SectionHandler;
use MediaWiki\Extension\PageForms\FormRender\SectionLayout;
use MediaWiki\Extension\PageForms\FormRender\StandardInputHandler;
use MediaWiki\Extension\PageForms\FormRender\TemplateHandler;
use MediaWiki\Extension\PageForms\FormRender\TextHandler;
use MediaWiki\Extension\PageForms\FormRender\UnknownTagHandler;

/**
 * Builds the form printer and hands it out.
 *
 * The factory owns the wiring: the default object graph of the printer is built here, and the
 * PageForms::FormPrinterSetup hook runs here, on the finished printer.
 *
 * The printer is kept in the global variable $wgPageFormsFormPrinter, which is set when the
 * extension is initialised and which other extensions use to add their input types. Code of
 * this extension gets the printer here instead of reading the global; tests and custom code that
 * replace the global are still honoured.
 *
 * @ingroup PF
 */
class FormPrinterFactory {

	/**
	 * Build a new form printer with all its default collaborators and store it in the global
	 * variable for custom code. The setup hook runs on the finished printer.
	 *
	 * @return FormPrinter
	 */
	public static function initialize(): FormPrinter {
		$printer = self::create();
		$GLOBALS['wgPageFormsFormPrinter'] = $printer;
		return $printer;
	}

	/**
	 * @return FormPrinter The printer in $wgPageFormsFormPrinter; it is created if there is none yet
	 */
	public static function get(): FormPrinter {
		return $GLOBALS['wgPageFormsFormPrinter'] ?? self::initialize();
	}

	/**
	 * Build a form printer, run the setup hook on it and return it. The global variable is left
	 * alone. A collaborator that is not passed in is created with its default, so a test fakes
	 * exactly the ones it needs.
	 *
	 * @param InputTypeRegistry|null $inputTypeRegistry
	 * @param FormDefParser|null $formDefParser
	 * @param array<class-string, ElementHandler>|null $elementHandlers
	 * @param RenderServices|null $services
	 * @return FormPrinter
	 */
	public static function create(
		?InputTypeRegistry $inputTypeRegistry = null,
		?FormDefParser $formDefParser = null,
		?array $elementHandlers = null,
		?RenderServices $services = null
	): FormPrinter {
		$parts = self::newParts( $inputTypeRegistry, $formDefParser, $elementHandlers, $services );
		$printer = new FormPrinter( $parts );
		self::runSetupHook( $printer, $parts->services );
		return $printer;
	}

	/**
	 * Run the hook that lets custom code set up a printer that is completely built. It is
	 * all-purpose, and runs last for that reason.
	 *
	 * @param FormPrinter $printer
	 * @param RenderServices $services
	 */
	public static function runSetupHook( FormPrinter $printer, RenderServices $services ): void {
		// Avoid PHP 7.1 warning from passing $this by reference.
		$printerRef = $printer;
		$services->hookContainer()->run( 'PageForms::FormPrinterSetup', [ &$printerRef ] );
	}

	/**
	 * The default object graph of a form printer. Pass in what should differ.
	 *
	 * @param InputTypeRegistry|null $inputTypeRegistry
	 * @param FormDefParser|null $formDefParser
	 * @param array<class-string, ElementHandler>|null $elementHandlers
	 * @param RenderServices|null $services
	 * @return FormPrinterParts
	 */
	public static function newParts(
		?InputTypeRegistry $inputTypeRegistry = null,
		?FormDefParser $formDefParser = null,
		?array $elementHandlers = null,
		?RenderServices $services = null
	): FormPrinterParts {
		$services ??= new RenderServices();
		$inputTypeRegistry ??= InputTypeRegistry::newWithBuiltInTypes();
		$calendarHtmlBuilder = new CalendarHtmlBuilder();
		$multipleTemplateHtmlBuilder = new MultipleTemplateHtmlBuilder();
		$spreadsheetHtmlBuilder = new SpreadsheetHtmlBuilder();
		$formFieldHtmlBuilder = new FormFieldHtmlBuilder( $inputTypeRegistry );
		$formDefParser ??= new FormDefParser( $services->parserFactory(), new FormDefinitionReader() );

		return new FormPrinterParts(
			$inputTypeRegistry,
			$calendarHtmlBuilder,
			$spreadsheetHtmlBuilder,
			$multipleTemplateHtmlBuilder,
			$formFieldHtmlBuilder,
			$formDefParser,
			new SectionLayout(
				$multipleTemplateHtmlBuilder, $spreadsheetHtmlBuilder, $calendarHtmlBuilder, $formFieldHtmlBuilder
			),
			new PageTextAssembler( $services->hookContainer() ),
			$elementHandlers ?? self::newElementHandlers( $formFieldHtmlBuilder, $services ),
			$services
		);
	}

	/**
	 * @param FormFieldHtmlBuilder $formFieldHtmlBuilder
	 * @param RenderServices $services
	 * @return array<class-string, ElementHandler>
	 */
	private static function newElementHandlers(
		FormFieldHtmlBuilder $formFieldHtmlBuilder, RenderServices $services
	): array {
		return [
			FieldSpec::class => new FieldHandler(
				$formFieldHtmlBuilder, new MappingLabels(), new FieldValueResolver(),
				new FormFieldExtraHtmlBuilder(), $services
			),
			TextSpec::class => new TextHandler(),
			UnknownTagSpec::class => new UnknownTagHandler(),
			TemplateSpec::class => new TemplateHandler(),
			EndTemplateSpec::class => new EndTemplateHandler(),
			InfoSpec::class => new InfoHandler(),
			StandardInputSpec::class => new StandardInputHandler(),
			SectionSpec::class => new SectionHandler(),
		];
	}
}
