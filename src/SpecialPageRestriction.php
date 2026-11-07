<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * How a special page that needs a permission hands it to MediaWiki.
 *
 * MediaWiki 1.46 deprecated the $restriction parameter of the SpecialPage constructors and reads the permission
 * from getRestriction(), which the special page overrides. Before 1.46 only the constructor sets the permission
 * that the checks use (isRestricted(), userCanExecute() and the error page read the property directly), so the
 * override alone would leave the page open to everyone there.
 *
 * Remove this class when the oldest MediaWiki that Page Forms supports is 1.46: the special pages then override
 * getRestriction() only.
 *
 * @ingroup PF
 */
final class SpecialPageRestriction {

	/** The first MediaWiki version that takes the permission from getRestriction(). */
	private const OVERRIDE_SINCE = '1.46.0-alpha';

	/**
	 * @return bool Whether the special page passes the permission to its parent constructor (before MediaWiki 1.46)
	 *  instead of only overriding getRestriction()
	 */
	public static function isSetByConstructor(): bool {
		return version_compare( MW_VERSION, self::OVERRIDE_SINCE, '<' );
	}

}
