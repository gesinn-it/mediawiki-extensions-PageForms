<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;
use ParserFactory;
use ParserOptions;
use PFUtils;
use RequestContext;

/**
 * Parses a PageForms form definition and extracts preloaded field values
 * from existing page content.
 */
class FormDefParser {

	private ParserFactory $parserFactory;

	private FormDefinitionReader $reader;

	public function __construct( ParserFactory $parserFactory, ?FormDefinitionReader $reader = null ) {
		$this->parserFactory = $parserFactory;
		$this->reader = $reader ?? new FormDefinitionReader();
	}

	/**
	 * Given a form definition and the wikitext of an existing page, return
	 * an array of preloaded field values keyed by template name then field name,
	 * plus an optional 'pf_free_text' entry for any remaining page content.
	 *
	 * @param string $form_def Form definition wikitext.
	 * @param string $existing_page_content Wikitext of the page being edited.
	 * @param ?int $form_id Optional form page ID (used by FormCache).
	 * @return array<string, mixed>
	 */
	public function preparePreloadData( string $form_def, string $existing_page_content, ?int $form_id = null ): array {
		$user = RequestContext::getMain()->getUser();

		// Set up a fresh parser — same approach as formHTML(). Title it from the
		// current request so wikitext parsed below (e.g. a template-name tag
		// containing {{PAGENAME}}) doesn't resolve against a "Badtitle" placeholder
		// (see issue #189).
		$parser = $this->parserFactory->create();
		$parser->setOptions( ParserOptions::newFromUser( $user ) );
		$contextTitle = RequestContext::getMain()->getTitle();
		if ( $contextTitle !== null ) {
			$parser->setTitle( $contextTitle );
		}
		$parser->clearState();

		$form_def = FormCache::getFormDefinition( $parser, $form_def, $form_id );

		$definition = $this->reader->read( $form_def );

		// Walk the templates and collect preloaded field values.
		$result = [];

		foreach ( $definition->getTemplates() as $templateSpec ) {
			$tag_components = $templateSpec->getComponents();
			$template_name = str_replace( '_', ' ', $parser->recursiveTagParse( $tag_components[1] ) );
			// Top-level array key: spaces → underscores, matching HtmlFormDataExtractor output.
			$template_key = str_replace( ' ', '_', $template_name );
			$tif = TemplateInForm::newFromFormTag( $tag_components, $parser );
			$tif->setPageRelatedInfo( $existing_page_content );
			// Field values of every instance of a multiple-instance template, in page
			// order. Stays null for single-instance templates.
			$instances = null;
			if ( $tif->allowsMultiple() ) {
				// Read every call of this template on the page, like formHTML() does
				// by repeating the section once per instance.
				$instances = [];
				while ( $tif->pageCallsThisTemplate() ) {
					$tif->setFieldValuesFromPage( $existing_page_content );
					$existing_template_text = $tif->getFullTextInPage();
					if ( $existing_template_text === '' ) {
						break;
					}
					$instances[] = $tif->getValuesFromPage();
					$existing_page_content = PFUtils::strReplaceFirst(
						$existing_template_text, '', $existing_page_content
					);
					$tif->setPageRelatedInfo( $existing_page_content );
				}
				foreach ( $instances as $i => $unused ) {
					// An instance without any field still counts as an instance.
					$result[$template_key][$i . 'a'] = [];
				}
			} elseif ( $tif->pageCallsThisTemplate() ) {
				$tif->setFieldValuesFromPage( $existing_page_content );
				$existing_template_text = $tif->getFullTextInPage();
				$existing_page_content = PFUtils::strReplaceFirst(
					$existing_template_text, '', $existing_page_content
				);
			}

			foreach ( $templateSpec->getFields() as $field ) {
				$field_name = $field->getName();
				if ( $instances !== null ) {
					// Multiple-instance template: instances are keyed "0a", "1a", ... exactly
					// as HtmlFormDataExtractor::addToArray() names them.
					foreach ( $instances as $i => $values ) {
						if ( array_key_exists( $field_name, $values ) ) {
							$result[$template_key][$i . 'a'][$field_name] = $values[$field_name];
						}
					}
				} elseif ( $tif->getFullTextInPage() !== ''
					&& $tif->hasValueFromPageForField( $field_name )
				) {
					$result[$template_key][$field_name] = $tif->getAndRemoveValueFromPageForField( $field_name );
				}
			}

			// Whatever else the page's template call carries is "unhandled" (see
			// FormUtils::unhandledFieldsHTML()).
			$handledFields = $templateSpec->getFieldNames();
			foreach ( $instances ?? [ $tif->getValuesFromPage() ] as $values ) {
				foreach ( $values as $name => $value ) {
					$unhandledKey = '_unhandled_' . $template_key . '_' . urlencode( (string)$name );
					// Positional parameters are not carried over, and neither
					// formHTML() nor the later page assembly distinguishes the
					// instances of a multiple-instance template here: the first
					// instance that has the parameter provides its value.
					if ( !is_numeric( $name ) && !in_array( $name, $handledFields, true )
						&& !array_key_exists( $unhandledKey, $result )
					) {
						$result[$unhandledKey] = $value;
					}
				}
			}
		}

		// Whatever remains in $existing_page_content after all template text has been
		// stripped is the free text section — mirror what formHTML() does when
		// $source_is_page=true (line: $free_text = trim( $existing_page_content )).
		// Without this, an autoedit SAVE would silently delete any free text on the page.
		$freeText = trim( $existing_page_content );
		if ( $freeText !== '' ) {
			$result['pf_free_text'] = $freeText;
		}

		return $result;
	}

	/**
	 * Split a form definition string into sections on {{{for template}}} / {{{end template}}}
	 * boundaries.
	 *
	 * The first element of the returned array is any text before the first template tag;
	 * subsequent elements each start with a {{{for template}}} or {{{end template}}} tag.
	 *
	 * @param string $form_def Form definition wikitext (with 'standard input|free text' already
	 *   replaced by 'field|#freetext#' when needed).
	 * @return list<string>
	 */
	public function splitFormDefIntoSections( string $form_def ): array {
		$form_def_sections = [];
		$start_position = 0;
		$section_start = 0;
		$brackets_loc = strpos( $form_def, '{{{', $start_position );
		while ( $brackets_loc !== false ) {
			$brackets_end_loc = strpos( $form_def, '}}}', $brackets_loc );
			$bracketed_string = substr( $form_def, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 ) );
			$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
			if ( count( $tag_components ) > 0 ) {
				$tag_title = trim( $tag_components[0] );
				if ( $tag_title === 'for template' || $tag_title === 'end template' ) {
					$form_def_sections[] = substr( $form_def, $section_start, $brackets_loc - $section_start );
					$section_start = $brackets_loc;
				}
			}
			$start_position = $brackets_loc + 1;
			$brackets_loc = strpos( $form_def, '{{{', $start_position );
		}
		$form_def_sections[] = trim( substr( $form_def, $section_start ) );
		return $form_def_sections;
	}

}
