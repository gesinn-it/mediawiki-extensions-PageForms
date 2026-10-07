<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

use InvalidArgumentException;

/**
 * The structure of a form: its tags and the text between them, in order, free of any value.
 *
 * Built by FormDefinitionReader. Values (from the page text, a request or the API)
 * live in a separate layer and are applied on top of this model.
 */
class FormDefinition {

	private const TAG_CLASSES = [
		FieldSpec::TYPE => FieldSpec::class,
		TemplateSpec::TYPE => TemplateSpec::class,
		EndTemplateSpec::TYPE => EndTemplateSpec::class,
		SectionSpec::TYPE => SectionSpec::class,
		StandardInputSpec::TYPE => StandardInputSpec::class,
		InfoSpec::TYPE => InfoSpec::class,
		UnknownTagSpec::TYPE => UnknownTagSpec::class,
	];

	/** @var list<FormElement> */
	private array $elements = [];

	/** @var list<TemplateSpec> */
	private array $templates = [];

	private ?TemplateSpec $openTemplate = null;

	/**
	 * Append an element. A field joins the template opened last, as long as that one is not ended;
	 * a field outside a template belongs to no template.
	 */
	public function addElement( FormElement $element ): void {
		$this->elements[] = $element;
		if ( $element instanceof TemplateSpec ) {
			// A new "for template" closes a block that was never ended.
			$this->templates[] = $element;
			$this->openTemplate = $element;
		} elseif ( $element instanceof EndTemplateSpec ) {
			$this->openTemplate = null;
		} elseif ( $element instanceof FieldSpec ) {
			$this->openTemplate?->addField( $element );
		}
	}

	/**
	 * @return list<FormElement> Tags and text, in the order of the form definition
	 */
	public function getElements(): array {
		return $this->elements;
	}

	/**
	 * @return list<TemplateSpec>
	 */
	public function getTemplates(): array {
		return $this->templates;
	}

	/**
	 * @return list<StandardInputSpec>
	 */
	public function getStandardInputs(): array {
		return $this->elementsOfType( StandardInputSpec::class );
	}

	/**
	 * @return list<SectionSpec>
	 */
	public function getSections(): array {
		return $this->elementsOfType( SectionSpec::class );
	}

	/**
	 * The {{{info}}} tag, if the form has one.
	 */
	public function getInfo(): ?InfoSpec {
		return $this->elementsOfType( InfoSpec::class )[0] ?? null;
	}

	/**
	 * Whether the form has a "standard input|free text" input.
	 */
	public function hasFreeText(): bool {
		foreach ( $this->getStandardInputs() as $input ) {
			if ( $input->isFreeText() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @template T of FormElement
	 * @param class-string<T> $class
	 * @return list<T>
	 */
	private function elementsOfType( string $class ): array {
		$matching = [];
		foreach ( $this->elements as $element ) {
			if ( $element instanceof $class ) {
				$matching[] = $element;
			}
		}
		return $matching;
	}

	/**
	 * @return array{elements: list<array>}
	 */
	public function toArray(): array {
		return [
			'elements' => array_map( static fn ( FormElement $e ): array => $e->toArray(), $this->elements ),
		];
	}

	/**
	 * @param array{elements: list<array>} $data
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$definition = new self();
		foreach ( $data['elements'] as $element ) {
			$type = $element['type'] ?? '';
			if ( $type === TextSpec::TYPE ) {
				$definition->addElement( new TextSpec( $element['text'] ) );
			} elseif ( isset( self::TAG_CLASSES[$type] ) ) {
				$definition->addElement( self::TAG_CLASSES[$type]::fromArray( $element ) );
			} else {
				throw new InvalidArgumentException( "Unknown form element type '$type'" );
			}
		}
		return $definition;
	}
}
