<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['baseline_path'] = __DIR__ . '/baseline.php';

// Analyse extension source code; vendor + node_modules are excluded by default
$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'src',
		'includes',
		'specials',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'vendor/',
	]
);

// Make dependency extensions visible to Phan's type-checker: those activated
// in the Makefile / ci.yml matrix, those installed via extensions.local.json
// for extensions DCI doesn't bundle, and those already shipped in the base
// MediaWiki image but never wfLoadExtension()'d for this test setup (e.g.
// ConfirmEdit — PFFormEdit::showCaptcha() calls into it conditionally, but
// it isn't activated at runtime here). mediawiki-phan-config only adds MW
// core and MW vendor to directory_list by default, never extensions/.
// Without this, every call into a dependency's classes surfaces as
// PhanUndeclaredClass / PhanUndeclaredClassMethod noise instead of being
// checked against the dependency's actual API.
$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: '../..';

$dependencyExtensions = [
	'SemanticMediaWiki',
	'DisplayTitle',
	'AdminLinks',
	'ExternalData',
	'ConfirmEdit',
];

foreach ( $dependencyExtensions as $ext ) {
	$cfg['directory_list'][] = $IP . '/extensions/' . $ext;
	$cfg['exclude_analysis_directory_list'][] = $IP . '/extensions/' . $ext;
}

return $cfg;
