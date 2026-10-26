<?php

/**
 * Makes the namespaced names of core classes resolvable on every supported MediaWiki version.
 *
 * Older cores only know these classes under their global names, while newer cores (1.46+) have dropped
 * the global names. The code imports the namespaced names ("use MediaWiki\Html\Html;"), so where one is
 * missing it is aliased to the global class. On a core that has the namespaced class this does nothing.
 * Remove the entries once the oldest supported MediaWiki has the namespaced class.
 */

foreach ( [
	'Html' => 'MediaWiki\\Html\\Html',
	'Linker' => 'MediaWiki\\Linker\\Linker',
	'Title' => 'MediaWiki\\Title\\Title',
] as $legacyName => $namespacedName ) {
	if ( !class_exists( $namespacedName ) && class_exists( $legacyName ) ) {
		class_alias( $legacyName, $namespacedName );
	}
}
