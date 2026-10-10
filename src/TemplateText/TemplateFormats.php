<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\TemplateText;

/**
 * Finds the TemplateFormat for the name of a format.
 */
class TemplateFormats {

	/**
	 * @param string|null $name "standard", "infobox", "plain" or "sections"; an empty name means
	 *  "standard"
	 * @return TemplateFormat
	 */
	public static function fromName( ?string $name ): TemplateFormat {
		if ( !$name ) {
			return new TableTemplateFormat();
		}
		switch ( $name ) {
			case 'standard':
				return new TableTemplateFormat();
			case 'infobox':
				return new InfoboxTemplateFormat();
			case 'plain':
				return new PlainTemplateFormat();
			case 'sections':
				return new SectionsTemplateFormat();
			default:
				return new UnformattedTemplateFormat();
		}
	}
}
