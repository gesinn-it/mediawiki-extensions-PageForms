<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * The structure of a form: its templates and their fields, free of any value.
 *
 * Built by FormDefinitionReader. Values (from the page text, a request or the API)
 * live in a separate layer and are applied on top of this model.
 */
class FormDefinition {

	/** @var list<TemplateSpec> */
	private array $templates = [];

	private bool $hasFreeText = false;

	public function addTemplate( TemplateSpec $template ): void {
		$this->templates[] = $template;
	}

	/**
	 * @return list<TemplateSpec>
	 */
	public function getTemplates(): array {
		return $this->templates;
	}

	public function setHasFreeText( bool $hasFreeText ): void {
		$this->hasFreeText = $hasFreeText;
	}

	/**
	 * Whether the form has a "standard input|free text" input.
	 */
	public function hasFreeText(): bool {
		return $this->hasFreeText;
	}

	/**
	 * @return array{templates: list<array>, hasFreeText: bool}
	 */
	public function toArray(): array {
		return [
			'templates' => array_map( static fn ( TemplateSpec $t ): array => $t->toArray(), $this->templates ),
			'hasFreeText' => $this->hasFreeText,
		];
	}

	/**
	 * @param array{templates: list<array>, hasFreeText: bool} $data
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$definition = new self();
		foreach ( $data['templates'] as $template ) {
			$definition->addTemplate( TemplateSpec::fromArray( $template ) );
		}
		$definition->setHasFreeText( $data['hasFreeText'] );
		return $definition;
	}
}
