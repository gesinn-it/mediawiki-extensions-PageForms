<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MessageLocalizer;
use MWException;
use PFUtils;
use Title;

/**
 * Finds the form that a request to the pfautoedit API edits the target page with.
 *
 * @ingroup PF
 */
final class AutoeditFormResolver {

	/**
	 * MediaWiki core has no built-in "resolve the whole redirect chain" helper, so a redirect is
	 * followed one hop at a time, same as MediaWiki's own redirect handling. A fixed hop limit
	 * guards against redirect loops.
	 */
	private const MAX_REDIRECT_HOPS = 5;

	public function __construct( private readonly MessageLocalizer $messages ) {
	}

	/**
	 * Finds the name of the form for a request that names none: the one alternative form it
	 * offers, or else the default form of the target page.
	 *
	 * @param string $targetName
	 * @param string[] $alternativeForms The forms the request offers
	 * @throws MWException if there is no form, or more than one
	 */
	public function findDefaultFormName( string $targetName, array $alternativeForms ): string {
		$formNames = array_values( $alternativeForms );

		// if no alternate forms were explicitly set, try finding a default form for the target page
		if ( count( $formNames ) === 0 ) {
			// if no form and and no alt forms and no target page was specified, give up
			if ( $targetName === '' ) {
				throw new MWException( $this->messages->msg( 'pf_autoedit_notargetspecified' )->parse() );
			}

			$targetTitle = Title::newFromText( $targetName );

			// if the specified target title is invalid, give up
			if ( !$targetTitle instanceof Title ) {
				throw new MWException( $this->messages->msg(
					'pf_autoedit_invalidtargetspecified', $targetName
				)->parse() );
			}

			$formNames = FormLinker::getDefaultFormsForPage( $targetTitle );
			if ( count( $formNames ) === 0 ) {
				throw new MWException( $this->messages->msg( 'pf_autoedit_noformfound' )->parse() );
			}
		}

		// if more than one form was found, issue a notice and give up
		// this happens if no default form but several alternate forms are defined
		if ( count( $formNames ) > 1 ) {
			throw new MWException(
				$this->messages->msg( 'pf_autoedit_toomanyformsfound' )->parse(), AutoeditApi::DEBUG
			);
		}

		return $formNames[0];
	}

	/**
	 * Follows the redirects of a form page to the form they lead to.
	 *
	 * @param Title $formTitle A form page that is a redirect
	 * @param string $formName The name of the form as the request gave it
	 * @throws MWException if the chain is too long, or leads to an invalid target
	 */
	public function followRedirects( Title $formTitle, string $formName ): Title {
		for ( $i = 0; $i < self::MAX_REDIRECT_HOPS && $formTitle->isRedirect(); $i++ ) {
			$newTitle = PFUtils::newWikiPageFromTitle( $formTitle )->getRedirectTarget();
			if ( !( $newTitle instanceof Title ) || !$newTitle->isValidRedirectTarget() ) {
				break;
			}
			$formTitle = $newTitle;
		}

		// if we exceeded the hop limit or encountered an invalid redirect target, give up
		if ( $formTitle->isRedirect() ) {
			$newTitle = PFUtils::newWikiPageFromTitle( $formTitle )->getRedirectTarget();

			if ( $newTitle instanceof Title && $newTitle->isValidRedirectTarget() ) {
				throw new MWException( $this->messages->msg( 'pf_autoedit_redirectlimitexeeded', $formName )->parse() );
			}

			throw new MWException( $this->messages->msg(
				'pf_autoedit_invalidredirecttarget', $newTitle->getFullText(), $formName
			)->parse() );
		}

		return $formTitle;
	}
}
