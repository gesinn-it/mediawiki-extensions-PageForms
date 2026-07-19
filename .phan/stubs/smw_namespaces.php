<?php
/**
 * SemanticMediaWiki's SMW_NS_CONCEPT is registered through extension.json's
 * "namespaces" block and resolved into a real constant by MediaWiki core's
 * ExtensionRegistry at runtime. Phan only sees literal define() calls, so
 * versions of SMW that don't also ship their own .phan/stubs/namespaces.php
 * (e.g. 6.0.1, unlike 7.x) leave the constant looking undeclared to Phan.
 */
if ( !defined( 'SMW_NS_CONCEPT' ) ) {
	define( 'SMW_NS_CONCEPT', 108 );
}
