#!/usr/bin/env node
'use strict';

/**
 * Copies the bundled third-party libraries from node_modules into libs/ and applies
 * the Page Forms specific patches from libs-patches/.
 *
 *   node scripts/update-libs.js            write the result into libs/
 *   node scripts/update-libs.js --check    build into a temporary directory and fail if
 *                                          libs/ differs (used by CI)
 *
 * Update procedure: see docs/developer/third-party-libraries.adoc
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

const root = path.resolve( __dirname, '..' );

/**
 * One entry per bundled file: the file inside the npm package, the target below the
 * repository root and the patches applied (in this order) after copying.
 */
const LIBS = [
	{
		pkg: 'select2',
		from: 'dist/js/select2.full.js',
		to: 'libs/select2.js',
		patches: [ 'select2-no-commonjs', 'select2-rtl-dropdown' ]
	},
	{
		pkg: 'sortablejs',
		from: 'Sortable.js',
		to: 'libs/Sortable.js',
		patches: [ 'sortable-no-commonjs' ]
	},
	{
		pkg: 'jstree',
		from: 'dist/jstree.js',
		to: 'libs/jstree.js',
		patches: [ 'jstree-no-commonjs' ]
	},
	{
		pkg: 'fullcalendar',
		from: 'dist/fullcalendar.js',
		to: 'libs/FullCalendar/fullcalendar.js',
		patches: []
	},
	{
		pkg: 'fullcalendar',
		from: 'dist/locale-all.js',
		to: 'libs/FullCalendar/locale-all.js',
		patches: []
	},
	{
		pkg: 'jspreadsheet-ce',
		from: 'dist/jexcel.js',
		to: 'libs/jspreadsheet.js',
		patches: [
			'jspreadsheet-no-require',
			'jspreadsheet-header-title',
			'jspreadsheet-null-guards',
			'jspreadsheet-pf-autocomplete'
		]
	}
];

function fail( message ) {
	console.error( message );
	process.exit( 1 );
}

function build( outDir ) {
	for ( const lib of LIBS ) {
		const source = path.join( root, 'node_modules', lib.pkg, lib.from );
		if ( !fs.existsSync( source ) ) {
			fail( `${ source } not found; run "npm install" first.` );
		}
		const target = path.join( outDir, lib.to );
		fs.mkdirSync( path.dirname( target ), { recursive: true } );
		// Some packages ship CRLF line endings; the repository uses LF.
		fs.writeFileSync( target, fs.readFileSync( source, 'utf8' ).replace( /\r\n/g, '\n' ) );

		for ( const patch of lib.patches ) {
			const patchFile = path.join( root, 'libs-patches', patch + '.patch' );
			const result = spawnSync(
				'patch',
				[ '-s', '--no-backup-if-mismatch', '-p1', '-d', outDir, '-i', patchFile ],
				{ encoding: 'utf8' }
			);
			if ( result.error || result.status !== 0 ) {
				const output = ( result.stdout || '' ) + ( result.stderr || '' ) + ( result.error || '' );
				fail( `Patch ${ patch } does not apply to ${ lib.to }:\n${ output }` );
			}
		}
	}
}

if ( process.argv.includes( '--check' ) ) {
	const tmp = fs.mkdtempSync( path.join( os.tmpdir(), 'pf-libs-' ) );
	try {
		build( tmp );
		const stale = LIBS.map( ( lib ) => lib.to ).filter( ( to ) =>
			!fs.existsSync( path.join( root, to ) ) ||
			fs.readFileSync( path.join( root, to ), 'utf8' ) !== fs.readFileSync( path.join( tmp, to ), 'utf8' )
		);
		if ( stale.length ) {
			fail( 'libs/ is out of date, run "npm run libs:update":\n  ' + stale.join( '\n  ' ) );
		}
		console.log( `libs/ matches node_modules plus patches (${ LIBS.length } files).` );
	} finally {
		fs.rmSync( tmp, { recursive: true, force: true } );
	}
} else {
	build( root );
	console.log( `Updated ${ LIBS.length } files in libs/.` );
}
