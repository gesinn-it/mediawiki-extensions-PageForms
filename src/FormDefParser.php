<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use ParserFactory;
use ParserOptions;
use PFFormCache;
use PFTemplateInForm;
use PFUtils;
use RequestContext;
use Title;

/**
 * Parses a PageForms form definition and extracts preloaded field values
 * from existing page content.
 */
class FormDefParser {

	private ParserFactory $parserFactory;

	public function __construct( ParserFactory $parserFactory ) {
		$this->parserFactory = $parserFactory;
	}

	/**
	 * Given a form definition and the wikitext of an existing page, return
	 * an array of preloaded field values keyed by template name then field name,
	 * plus an optional 'pf_free_text' entry for any remaining page content.
	 *
	 * @param string $form_def Form definition wikitext.
	 * @param string $existing_page_content Wikitext of the page being edited.
	 * @param ?int $form_id Optional form page ID (used by PFFormCache).
	 * @return array<string, mixed>
	 */
	public function preparePreloadData( string $form_def, string $existing_page_content, ?int $form_id = null ): array {
		$user = RequestContext::getMain()->getUser();

		// Set up a fresh parser — same approach as formHTML().
		$globalParser = PFUtils::getParser();
		if ( method_exists( $globalParser, 'getFreshParser' ) ) {
			$parser = $globalParser->getFreshParser();
			if ( !$parser->getOptions() ) {
				$parser->setOptions( ParserOptions::newFromUser( $user ) );
			}
		} else {
			$parser = $this->parserFactory->create();
			$parser->setOptions( ParserOptions::newFromUser( $user ) );
		}
		$parser->clearState();
		// MW 1.35 requires a non-null Title for Parser::parse(). Provide a
		// fallback when no title has been set on the parser yet.
		if ( !is_object( $parser->getTitle() ) ) {
			$parser->setTitle( Title::newMainPage() );
		}

		$form_def = PFFormCache::getFormDefinition( $parser, $form_def, $form_id );

		// Neutralise the 'free text' standard input so it doesn't confuse the scan.
		$form_def = str_replace( 'standard input|free text', 'field|#freetext#', $form_def );
		$form_def_sections = $this->splitFormDefIntoSections( $form_def );

		// Walk sections and collect preloaded field values.
		$result = [];
		$tif = null;
		$template_key = null;

		foreach ( $form_def_sections as $section ) {
			$section = ' ' . $section;
			$start_position = 0;

			while ( true ) {
				$brackets_loc = strpos( $section, '{{{', $start_position );
				if ( $brackets_loc === false ) {
					break;
				}
				$brackets_end_loc = strpos( $section, '}}}', $brackets_loc );
				$bracketed_string = substr(
					$section, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 )
				);
				$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
				if ( count( $tag_components ) === 0 ) {
					break;
				}
				$tag_title = trim( $tag_components[0] );

				if ( $tag_title === 'for template' ) {
					$template_name = str_replace( '_', ' ', $parser->recursiveTagParse( $tag_components[1] ) );
					// Top-level array key: spaces → underscores, matching HtmlFormDataExtractor output.
					$template_key = str_replace( ' ', '_', $template_name );
					$tif = PFTemplateInForm::newFromFormTag( $tag_components, $parser );
					$tif->setPageRelatedInfo( $existing_page_content );
					if ( $tif->pageCallsThisTemplate() ) {
						$tif->setFieldValuesFromPage( $existing_page_content );
						$existing_template_text = $tif->getFullTextInPage();
						$existing_page_content = $this->strReplaceFirst(
							$existing_template_text, '', $existing_page_content
						);
					}
				} elseif ( $tag_title === 'end template' ) {
					$tif = null;
					$template_key = null;
				} elseif ( $tag_title === 'field' && $tif !== null && $template_key !== null ) {
					$field_name = trim( $tag_components[1] );
					if ( $field_name !== '#freetext#'
						&& $tif->getFullTextInPage() !== ''
						&& $tif->hasValueFromPageForField( $field_name )
					) {
						$result[$template_key][$field_name] = $tif->getAndRemoveValueFromPageForField( $field_name );
					}
				}

				$start_position = $brackets_loc + 1;
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
	private function splitFormDefIntoSections( string $form_def ): array {
		$form_def_sections = [];
		$start_position = 0;
		$section_start = 0;
		$brackets_loc = strpos( $form_def, '{{{', $start_position );
		while ( $brackets_loc !== false ) {
			$brackets_end_loc = strpos( $form_def, '}}}', $brackets_loc );
			$bracketed_string = substr( $form_def, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 ) );
			$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
			$tag_title = trim( $tag_components[0] );
			if ( $tag_title === 'for template' || $tag_title === 'end template' ) {
				$form_def_sections[] = substr( $form_def, $section_start, $brackets_loc - $section_start );
				$section_start = $brackets_loc;
			}
			$start_position = $brackets_loc + 1;
			$brackets_loc = strpos( $form_def, '{{{', $start_position );
		}
		$form_def_sections[] = trim( substr( $form_def, $section_start ) );
		return $form_def_sections;
	}

	private function strReplaceFirst( string $search, string $replace, string $subject ): string {
		$firstChar = strpos( $subject, $search );
		if ( $firstChar !== false ) {
			return substr( $subject, 0, $firstChar ) . $replace . substr( $subject, $firstChar + strlen( $search ) );
		}
		return $subject;
	}
}
