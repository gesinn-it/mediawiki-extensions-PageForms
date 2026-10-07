<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionReader;
use MediaWiki\MediaWikiServices;
use Parser;
use ParserFactory;
use ParserOptions;
use PFUtils;
use RequestContext;
use User;

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
		return $this->readPageValues( $form_def, $existing_page_content, $form_id )->toOptions();
	}

	/**
	 * Read the values the form's fields have on an existing page.
	 *
	 * @param string $form_def Form definition wikitext.
	 * @param string $existing_page_content Wikitext of the page being edited.
	 * @param ?int $form_id Optional form page ID (used by FormCache).
	 * @return FormValues
	 */
	public function readPageValues(
		string $form_def, string $existing_page_content, ?int $form_id = null
	): FormValues {
		$parser = $this->createParser( $this->parserFactory );

		$form_def = FormCache::getFormDefinition( $parser, $form_def, $form_id );
		$definition = $this->reader->read( $form_def );

		// Walk the templates and collect the values on the page.
		$result = new FormValues();

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
					$result->addInstance( $template_key, $i . 'a' );
				}
			} elseif ( $tif->pageCallsThisTemplate() ) {
				$tif->setFieldValuesFromPage( $existing_page_content );
				$existing_template_text = $tif->getFullTextInPage();
				$existing_page_content = PFUtils::strReplaceFirst(
					$existing_template_text, '', $existing_page_content
				);
			}

			$mappedFields = [];
			foreach ( $templateSpec->getFields() as $field ) {
				$field_name = $field->getName();
				if ( $field->getMappingType() !== null ) {
					$mappedFields[] = $field_name;
				}
				if ( $instances !== null ) {
					// Multiple-instance template: instances are keyed "0a", "1a", ... exactly
					// as HtmlFormDataExtractor::addToArray() names them.
					foreach ( $instances as $i => $values ) {
						if ( array_key_exists( $field_name, $values ) ) {
							$result->setFieldValue( $template_key, $i . 'a', $field_name, $values[$field_name] );
						}
					}
				} elseif ( $tif->getFullTextInPage() !== ''
					&& $tif->hasValueFromPageForField( $field_name )
				) {
					$value = $tif->getAndRemoveValueFromPageForField( $field_name );
					$result->setFieldValue( $template_key, null, $field_name, $value );
					if ( $field->holdsTemplate() ) {
						// The value holds the calls of the template embedded in this field.
						// Like formHTML(), put them back at the end of the page text, where the
						// form section of the embedded template finds them.
						$existing_page_content .= $value;
					}
				}
			}
			$result->setMappedFields( $template_key, $mappedFields );

			// Whatever else the page's template call carries is "unhandled" (see
			// FormUtils::unhandledFieldsHTML()). Positional parameters are not carried over.
			$handledFields = $templateSpec->getFieldNames();
			foreach ( $instances ?? [ $tif->getValuesFromPage() ] as $i => $values ) {
				foreach ( $values as $name => $value ) {
					if ( is_numeric( $name ) || in_array( $name, $handledFields, true ) ) {
						continue;
					}
					if ( $instances !== null ) {
						// Each instance keeps its own parameters.
						$result->setUnhandled( $template_key, (string)$name, $value, $i . 'a' );
					} elseif ( !$result->hasUnhandled( $template_key, (string)$name ) ) {
						$result->setUnhandled( $template_key, (string)$name, $value );
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
			$result->setFreeText( $freeText );
		}

		return $result;
	}

	/**
	 * The inputs of a form that $user may not edit because they are marked "restricted".
	 *
	 * @param string $form_def Form definition wikitext.
	 * @param ?int $form_id Optional form page ID (used by FormCache).
	 * @param User $user
	 * @return RestrictedInputs
	 */
	public function getRestrictedInputs( string $form_def, ?int $form_id, User $user ): RestrictedInputs {
		// The current services' parser factory, not the one this (long-lived) instance was built with:
		// this runs on every autoedit request, including after the services have been replaced.
		$parser = $this->createParser( MediaWikiServices::getInstance()->getParserFactory() );
		$definition = $this->reader->read( FormCache::getFormDefinition( $parser, $form_def, $form_id ) );
		$restricted = new RestrictedInputs();

		foreach ( $definition->getTemplates() as $templateSpec ) {
			$template_name = str_replace( '_', ' ', $parser->recursiveTagParse( $templateSpec->getRawName() ) );
			$template_key = str_replace( ' ', '_', $template_name );
			foreach ( $templateSpec->getFields() as $field ) {
				if ( $this->isRestrictedFor( $field->getRestriction(), $user ) ) {
					$restricted->addField( $template_key, $field->getName() );
				}
			}
		}
		foreach ( $definition->getSections() as $section ) {
			if ( $this->isRestrictedFor( $section->getRestriction(), $user ) ) {
				$restricted->addSection( $section->getName() );
			}
		}
		foreach ( $definition->getStandardInputs() as $input ) {
			if ( $input->isFreeText() && $this->isRestrictedFor( $input->getRestriction(), $user ) ) {
				$restricted->restrictFreeText();
			}
		}
		return $restricted;
	}

	/**
	 * @param list<string>|null $restriction As returned by TagSpec::getRestriction()
	 * @param User $user
	 * @return bool Whether the restriction keeps $user from editing
	 */
	private function isRestrictedFor( ?array $restriction, User $user ): bool {
		if ( $restriction === null ) {
			return false;
		}
		if ( $restriction === [] ) {
			return !$user->isAllowed( 'editrestrictedfields' );
		}
		$groups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $user );
		return !array_intersect( $groups, $restriction );
	}

	/**
	 * A fresh parser, set up like the one formHTML() uses. It is titled from the current
	 * request so wikitext parsed with it (e.g. a template-name tag containing {{PAGENAME}})
	 * doesn't resolve against a "Badtitle" placeholder (see issue #189).
	 */
	private function createParser( ParserFactory $parserFactory ): Parser {
		$parser = $parserFactory->create();
		$parser->setOptions( ParserOptions::newFromUser( RequestContext::getMain()->getUser() ) );
		$contextTitle = RequestContext::getMain()->getTitle();
		if ( $contextTitle !== null ) {
			$parser->setTitle( $contextTitle );
		}
		$parser->clearState();
		return $parser;
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
		$section_start = 0;
		$offset = 0;
		$tag = $this->reader->findNextTag( $form_def, $offset );
		while ( $tag !== null ) {
			if ( count( $tag['components'] ) > 0 ) {
				$tag_title = trim( $tag['components'][0] );
				if ( $tag_title === 'for template' || $tag_title === 'end template' ) {
					$form_def_sections[] = substr( $form_def, $section_start, $tag['start'] - $section_start );
					$section_start = $tag['start'];
				}
			}
			$offset = $tag['start'] + 1;
			$tag = $this->reader->findNextTag( $form_def, $offset );
		}
		$form_def_sections[] = trim( substr( $form_def, $section_start ) );
		return $form_def_sections;
	}

}
