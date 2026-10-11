<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Title;

/**
 * What a request to the pfautoedit API asks for: the options of the request with the aliases
 * resolved, the form and target names normalized, and the action to perform.
 *
 * @ingroup PF
 */
final class AutoeditRequest {

	public const ACTION_FORMEDIT = 0;
	public const ACTION_SAVE = 1;
	public const ACTION_PREVIEW = 2;
	public const ACTION_DIFF = 3;

	/**
	 * @param array $options The options of the request; "form" and "target" are always set
	 * @param int $action One of the ACTION_* constants
	 * @param bool $isAutoEdit Whether the request comes from #autoedit
	 * @param string|null $hookQuery The form (and target) the request names, as the PageForms::SetTargetName
	 *  hook gets it; null if the request names no form
	 */
	public function __construct(
		public readonly array $options,
		public readonly int $action,
		public readonly bool $isAutoEdit,
		public readonly ?string $hookQuery
	) {
	}

	/**
	 * @param array $options The values of the request, merged with the options set explicitly
	 */
	public static function fromOptions( array $options ): self {
		// MW uses the parameter 'title' instead of 'target' when submitting
		// data for formedit action => use that
		if ( !array_key_exists( 'target', $options ) && array_key_exists( 'title', $options ) ) {
			$options['target'] = $options['title'];
			unset( $options['title'] );
		}

		// if the 'query' parameter was used, unpack the param string
		if ( array_key_exists( 'query', $options ) ) {
			$options = self::parseQueryString( $options, $options['query'] );
			unset( $options['query'] );
		}

		[ $action, $isAutoEdit ] = self::takeAction( $options );
		$hookQuery = self::ensureFormAndTarget( $options );

		return new self( self::normalizeFormAndTarget( $options ), $action, $isAutoEdit, $hookQuery );
	}

	/**
	 * Adds the values of a query string ("Template[field]=value&...") to $data.
	 *
	 * @param array $data
	 * @param string $queryString
	 * @return array $data with the values of the query string
	 */
	public static function parseQueryString( array $data, string $queryString ): array {
		// Pre-encode literal '+' as '%2B' before urldecode() so that plus signs
		// in field values survive decoding as '+' rather than being collapsed to
		// spaces (the standard URL-encoded-form meaning of '+').
		$queryString = str_replace( '+', '%2B', $queryString );

		foreach ( explode( '&', $queryString ) as $param ) {
			// A trailing "&" or an empty query is no parameter; it would add a null value as option "0".
			if ( $param === '' ) {
				continue;
			}

			$elements = explode( '=', $param, 2 );

			$key = trim( urldecode( $elements[0] ) );
			$value = count( $elements ) > 1 ? urldecode( $elements[1] ) : null;

			if ( $key == "query" || $key == "query string" ) {
				$data = self::parseQueryString( $data, $value ?? '' );
			} else {
				NestedInputValues::addToArray( $data, $key, $value );
			}
		}

		return $data;
	}

	/**
	 * Finds the action the request asks for and removes the option that asked for it.
	 *
	 * @return array{0: int, 1: bool} The action and whether the request comes from #autoedit
	 */
	private static function takeAction( array &$options ): array {
		// if an action is explicitly set in the form data, use that
		if ( array_key_exists( 'wpSave', $options ) ) {
			unset( $options['wpSave'] );
			return [ self::ACTION_SAVE, false ];
		}
		if ( array_key_exists( 'wpPreview', $options ) ) {
			unset( $options['wpPreview'] );
			return [ self::ACTION_PREVIEW, false ];
		}
		if ( array_key_exists( 'wpDiff', $options ) ) {
			unset( $options['wpDiff'] );
			return [ self::ACTION_DIFF, false ];
		}

		switch ( $options['action'] ?? null ) {
			case 'pfautoedit':
				// #autoedit embeds the target's wpEdittime and editRevId into its form when the
				// page containing it is rendered. That can be long before the click (or be
				// served from the parser cache), so it is not the revision this save is based
				// on: the new page text is computed from the target as it is now. EditPage
				// would report an edit conflict for every change made to the target since
				// the rendering (AS_CONFLICT_DETECTED, "Modifying ... failed"), although
				// nothing is overwritten. The values are therefore dropped here, and the base
				// of the save is determined when the page text is read (see
				// AutoeditApi::rememberEditBaseRevision()).
				// This only applies to #autoedit requests; saves from a form (wpSave) keep the
				// base the user was shown.
				unset( $options['wpEdittime'], $options['editRevId'] );
				return [ self::ACTION_SAVE, true ];
			case 'preview':
				return [ self::ACTION_PREVIEW, false ];
			default:
				return [ self::ACTION_FORMEDIT, false ];
		}
	}

	/**
	 * Makes sure that "form" and "target" are set.
	 *
	 * @return string|null The form (and target) the request names, null if it names no form
	 */
	private static function ensureFormAndTarget( array &$options ): ?string {
		$hookQuery = null;

		if ( array_key_exists( 'form', $options ) ) {
			$hookQuery = $options['form'];
		} else {
			$options['form'] = '';
		}

		if ( array_key_exists( 'target', $options ) ) {
			if ( $hookQuery !== null ) {
				$hookQuery .= '/' . $options['target'];
			}
		} else {
			$options['target'] = '';
		}

		return $hookQuery;
	}

	/**
	 * Writes the form and target names the way MediaWiki does ("test page" is "Test page").
	 */
	private static function normalizeFormAndTarget( array $options ): array {
		$form = Title::newFromText( $options['form'] );
		if ( $form !== null ) {
			$options['form'] = $form->getPrefixedText();
		}

		$target = Title::newFromText( $options['target'] );
		if ( $target !== null ) {
			$options['target'] = $target->getPrefixedText();
		}

		return $options;
	}
}
