<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

/**
 * An {{{end template}}} tag.
 */
class EndTemplateSpec extends TagSpec {

	public const TYPE = 'end template';

	public static function fromArray( array $data ): static {
		return new static( $data['components'] );
	}
}
