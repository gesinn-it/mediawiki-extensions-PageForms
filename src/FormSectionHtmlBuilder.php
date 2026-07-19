<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;
use PFPageSection;
use PFTextAreaInput;
use PFUtils;
use PFWikiPage;
use User;
use WebRequest;

/**
 * Assembles the HTML fragment for a {{{section|...}}} tag within a PageForms form.
 */
class FormSectionHtmlBuilder {

	/**
	 * Builds the HTML input for a single section tag and updates $existing_page_content in-place.
	 *
	 * @param array $tag_components Parsed components of the section tag
	 * @param string $form_def_section The current form-definition section string (used for look-ahead)
	 * @param int $brackets_end_loc Current '}}}' position in $form_def_section
	 * @param bool $source_is_page Whether the form is being pre-populated from an existing page
	 * @param string|null &$existing_page_content Remaining page wikitext; mutated to remove the extracted section
	 * @param WebRequest $request Current HTTP request
	 * @param PFWikiPage $wiki_page Wiki page object that accumulates section data
	 * @param bool $form_is_disabled Whether all form inputs are disabled
	 * @param User $user Current user
	 * @return string HTML fragment that replaces the {{{section|...}}} tag
	 */
	public function buildHtml(
		array $tag_components,
		string $form_def_section,
		int $brackets_end_loc,
		bool $source_is_page,
		?string &$existing_page_content,
		WebRequest $request,
		PFWikiPage $wiki_page,
		bool $form_is_disabled,
		User $user,
		?FormCounters $counters = null
	): string {
		global $wgPageFormsFieldNum;
		$fieldNum = $counters !== null ? $counters->fieldNum : $wgPageFormsFieldNum;

		$section_name = trim( $tag_components[1] );
		$page_section_in_form = PFPageSection::newFromFormTag( $tag_components, $user );
		$section_text = '';

		if ( $source_is_page && $existing_page_content !== null ) {
			$section_text = $this->extractSectionFromPageContent(
				$section_name,
				$page_section_in_form,
				$form_def_section,
				$brackets_end_loc,
				$existing_page_content,
				$user
			);
		}

		if ( !$source_is_page ) {
			$section_text = $this->extractSectionFromRequest(
				$section_name,
				$page_section_in_form,
				$request,
				$wiki_page
			);
		}

		$section_text = trim( $section_text );

		$input_name = '_section[' . $section_name . ']';
		$other_args = $page_section_in_form->getSectionArgs();
		$other_args['isSection'] = true;
		if ( $page_section_in_form->isMandatory() ) {
			$other_args['mandatory'] = true;
		}

		if ( $page_section_in_form->isHidden() ) {
			return Html::hidden( $input_name, $section_text );
		}

		$sectionInput = new PFTextAreaInput(
			(string)$fieldNum, $section_text, $input_name,
			( $form_is_disabled || $page_section_in_form->isRestricted() ), $other_args
		);
		$sectionInput->addJavaScript();
		return $sectionInput->getHtmlText();
	}

	/**
	 * Extracts section text from existing page content and removes it from $existing_page_content.
	 */
	private function extractSectionFromPageContent(
		string $section_name,
		PFPageSection $page_section_in_form,
		string $form_def_section,
		int $brackets_end_loc,
		string &$existing_page_content,
		User $user
	): string {
		// T72202: ensure trailing newline so section-end detection works for the last section.
		if ( substr( $existing_page_content, -1 ) !== "\n" ) {
			$existing_page_content .= "\n";
		}

		$equalsSigns = str_repeat( '=', (int)$page_section_in_form->getSectionLevel() );
		$searchStr =
			'/^' .
			preg_quote( $equalsSigns, '/' ) .
			'[ ]*?' .
			preg_quote( $section_name, '/' ) .
			'[ ]*?' .
			preg_quote( $equalsSigns, '/' ) .
			'$/m';

		$section_start_loc = 0;
		if ( preg_match( $searchStr, $existing_page_content, $matches, PREG_OFFSET_CAPTURE ) ) {
			$section_start_loc = $matches[0][1];
			$existing_page_content = str_replace( $matches[0][0], '', $existing_page_content );
		}

		$section_end_loc = $this->findSectionEndLoc(
			$form_def_section,
			$brackets_end_loc,
			$existing_page_content,
			$section_start_loc,
			$user
		);

		if ( $section_end_loc === -1 || $section_end_loc === null || $section_end_loc === false ) {
			$section_text = substr( $existing_page_content, $section_start_loc );
			$existing_page_content = substr( $existing_page_content, 0, $section_start_loc );
		} else {
			$section_text = substr(
				$existing_page_content, $section_start_loc, $section_end_loc - $section_start_loc
			);
			$existing_page_content = substr( $existing_page_content, 0, $section_start_loc )
				. substr( $existing_page_content, $section_end_loc );
		}

		return $section_text;
	}

	/**
	 * Looks ahead in the form definition to find where the current section ends in the page content.
	 *
	 * @return int|false Position of section end in $existing_page_content, or -1 if end of content
	 */
	private function findSectionEndLoc(
		string $form_def_section,
		int $brackets_end_loc,
		string $existing_page_content,
		int $section_start_loc,
		User $user
	) {
		$section_end_loc = -1;
		$previous_brackets_end_loc = $brackets_end_loc;
		$next_section_found = false;

		while ( !$next_section_found ) {
			$next_bracket_start_loc = strpos( $form_def_section, '{{{', $previous_brackets_end_loc );
			if ( $next_bracket_start_loc === false ) {
				$section_end_loc = strpos( $existing_page_content, '{{', $section_start_loc );
				$next_section_found = true;
			} else {
				$next_bracket_end_loc = strpos( $form_def_section, '}}}', $next_bracket_start_loc );
				$bracketed_string_next_section = substr(
					$form_def_section, $next_bracket_start_loc + 3,
					$next_bracket_end_loc - ( $next_bracket_start_loc + 3 )
				);
				$tag_components_next_section =
					PFUtils::getFormTagComponents( $bracketed_string_next_section );
				$page_next_section_in_form =
					PFPageSection::newFromFormTag( $tag_components_next_section, $user );
				$tag_title_next_section = trim( $tag_components_next_section[0] );
				if ( $tag_title_next_section == 'section' ) {
					if ( preg_match(
						'/(^={1,6}[ ]*?' . preg_quote( $tag_components_next_section[1], '/' )
							. '[ ]*?={1,6}\s*?$)/m',
						$existing_page_content, $matches, PREG_OFFSET_CAPTURE
					) ) {
						$section_end_loc = $matches[0][1];
						$next_section_found = true;
					} elseif ( $page_next_section_in_form->isHideIfEmpty() ) {
						$previous_brackets_end_loc = $next_bracket_end_loc;
					} else {
						break;
					}
				} else {
					$next_section_found = true;
				}
			}
		}

		return $section_end_loc;
	}

	/**
	 * Extracts section text from the HTTP request (form submission path).
	 */
	private function extractSectionFromRequest(
		string $section_name,
		PFPageSection $page_section_in_form,
		WebRequest $request,
		PFWikiPage $wiki_page
	): string {
		$text_per_section = $request->getArray( '_section' );

		if ( is_array( $text_per_section ) && array_key_exists( $section_name, $text_per_section ) ) {
			$section_text = $text_per_section[$section_name];
		} else {
			$section_text = '';
		}

		$section_options = [ 'hideIfEmpty' => $page_section_in_form->isHideIfEmpty() ];
		$wiki_page->addSection(
			$section_name, $page_section_in_form->getSectionLevel(), $section_text, $section_options
		);

		return $section_text;
	}
}
