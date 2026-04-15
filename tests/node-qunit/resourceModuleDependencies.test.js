'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '../..' );
const extensionJson = require( '../../extension.json' );

/**
 * Maps a mw.* usage pattern to the ResourceLoader module that must be
 * declared as a dependency for it to be available at runtime.
 *
 * Add new entries here whenever a new mw.* API is introduced in a lib file.
 * All registered scripts in extension.json are scanned automatically — no
 * further test changes are required.
 */
const DEPENDENCY_RULES = [
	{
		pattern: /new\s+mw\.Api\b/,
		dependency: 'mediawiki.api',
		description: 'new mw.Api()'
	},
	{
		pattern: /\bmw\.util\b/,
		dependency: 'mediawiki.util',
		description: 'mw.util'
	}
];

/**
 * Returns a flat list of { moduleName, scriptPath, dependencies } records
 * derived from the ResourceModules section of extension.json.
 *
 * @return {Array<{moduleName: string, scriptPath: string, dependencies: string[]}>}
 */
function buildModuleScriptRecords() {
	const records = [];
	const modules = extensionJson.ResourceModules || {};

	const moduleNames = Object.keys( modules );
	for ( let i = 0; i < moduleNames.length; i++ ) {
		const moduleName = moduleNames[ i ];
		const def = modules[ moduleName ];
		const scripts = Array.isArray( def.scripts )
			? def.scripts
			: ( typeof def.scripts === 'string' ? [ def.scripts ] : [] );
		const dependencies = def.dependencies || [];

		for ( const scriptPath of scripts ) {
			records.push( { moduleName, scriptPath, dependencies } );
		}
	}

	return records;
}

QUnit.module( 'ResourceModule dependency completeness', () => {
	const records = buildModuleScriptRecords();

	for ( const { moduleName, scriptPath, dependencies } of records ) {
		const fullPath = path.join( ROOT, scriptPath );

		if ( !fs.existsSync( fullPath ) ) {
			continue;
		}

		const source = fs.readFileSync( fullPath, 'utf8' );

		for ( const { pattern, dependency, description } of DEPENDENCY_RULES ) {
			if ( !pattern.test( source ) ) {
				continue;
			}

			QUnit.test(
				`${ moduleName } declares "${ dependency }" (${ scriptPath } uses ${ description })`,
				( assert ) => {
					assert.true(
						dependencies.includes( dependency ),
						`${ moduleName } uses ${ description } but is missing "${ dependency }" in its dependencies in extension.json`
					);
				}
			);
		}
	}
} );
