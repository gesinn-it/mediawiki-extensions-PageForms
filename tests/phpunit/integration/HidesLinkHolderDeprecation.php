<?php

/**
 * For the tests that render a form.
 *
 * FormPrinter replaces the link placeholders in the form it builds with Parser::replaceLinkHolders().
 * MediaWiki deprecated that method in 1.34 and reports it since 1.46 ("should not be used outside parser
 * class"), but offers no replacement: the placeholders are private to the parser, and the form is sent to the
 * browser without going through the parse that would replace them.
 *
 * Remove this trait, and the call in FormPrinter, when MediaWiki gives extensions a way to replace link
 * placeholders in text they parsed with Parser::recursiveTagParse(), or removes the method.
 */
trait HidesLinkHolderDeprecation {

	/**
	 * @before
	 */
	public function hideLinkHolderDeprecationSetUp(): void {
		// The name is the one the deprecation message carries: with the namespace since MediaWiki 1.42.
		$this->hideDeprecated( 'MediaWiki\\Parser\\Parser::replaceLinkHolders' );
		$this->hideDeprecated( 'Parser::replaceLinkHolders' );
	}

}
