<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;
use Title;

/**
 * A single SMW page-type (_wpg) value: a page referenced by namespace and
 * title, e.g. a category membership. Owns the canonical string
 * representation used when such a value is stored in wikitext (e.g.
 * "Category:Foo"), and namespace-tolerant comparison against another
 * representation of the same page.
 */
class PageValue {

	private int $namespace;

	private string $dbKey;

	private function __construct( int $namespace, string $dbKey ) {
		$this->namespace = $namespace;
		$this->dbKey = $dbKey;
	}

	public static function newFromTitle( Title $title ): self {
		return new self( $title->getNamespace(), $title->getDBkey() );
	}

	/**
	 * @param \SMW\DIWikiPage $diWikiPage
	 */
	public static function newFromDIWikiPage( $diWikiPage ): self {
		return new self( $diWikiPage->getNamespace(), $diWikiPage->getDBKey() );
	}

	/**
	 * Parses a raw stored value (e.g. "Category:Foo"), as found on a page or
	 * typed by the user, into a PageValue. Returns null if $rawValue cannot
	 * be parsed into a valid title.
	 */
	public static function newFromText( string $rawValue ): ?self {
		$title = Title::newFromText( $rawValue );
		return $title ? self::newFromTitle( $title ) : null;
	}

	public function getNamespace(): int {
		return $this->namespace;
	}

	/**
	 * The localized namespace text for this page value's namespace, e.g.
	 * "Kategorie" on a German wiki for NS_CATEGORY.
	 */
	public function getNamespaceText(): string {
		return PFUtils::getNsText( $this->namespace );
	}

	/**
	 * The canonical string form of this page value, as used when serializing
	 * internal links in wikitext: the canonical (English) namespace name,
	 * regardless of content language, followed by the title with underscores
	 * replaced by spaces. This matches how such values are actually stored on
	 * the page, regardless of the wiki's content language.
	 */
	public function getCanonicalString(): string {
		$title = str_replace( '_', ' ', $this->dbKey );
		if ( $this->namespace === 0 ) {
			return $title;
		}
		return PFUtils::getCanonicalName( $this->namespace ) . ":$title";
	}

	/**
	 * Whether $other refers to the same page, tolerating the differences
	 * that can arise between two independently-obtained representations of
	 * the same page value: underscore vs. space, and capitalization.
	 */
	public function equals( PageValue $other ): bool {
		if ( $this->namespace !== $other->namespace ) {
			return false;
		}
		return $this->normalizedDbKey() === $other->normalizedDbKey();
	}

	private function normalizedDbKey(): string {
		$dbKey = PFUtils::isCapitalized( $this->namespace )
			? PFUtils::getContLang()->ucfirst( $this->dbKey )
			: $this->dbKey;
		return str_replace( ' ', '_', $dbKey );
	}
}
