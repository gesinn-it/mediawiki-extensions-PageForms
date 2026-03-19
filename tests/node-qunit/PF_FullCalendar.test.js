// PF_FullCalendar.js passes `pf` as an IIFE argument at the call site:
//   }( jQuery, mediaWiki, pf ) );
// If `pf` is not defined as a global, this throws ReferenceError on load.
// Regression test for: "Exception in module-execute in module ext.pageforms.rating:
//   ReferenceError: pf is not defined"

// Stub FullCalendar and rateYo.
global.$.fn.fullCalendar = () => {};
global.$.fn.rateYo = () => ( { on: () => {} } );

const MODULE_PATH = require.resolve( '../../libs/PF_FullCalendar.js' );

QUnit.module( 'PF_FullCalendar' );

QUnit.test( 'throws ReferenceError when pf global is missing', ( assert ) => {
	const saved = global.pf;
	delete global.pf;
	delete require.cache[ MODULE_PATH ];
	assert.throws(
		() => {
			require( MODULE_PATH );
		},
		ReferenceError,
		'loading without pf global throws ReferenceError'
	);
	global.pf = saved;
	delete require.cache[ MODULE_PATH ];
} );

QUnit.test( 'loads without error when pf global is present', ( assert ) => {
	assert.expect( 0 );
	global.pf = global.pf || {};
	delete require.cache[ MODULE_PATH ];
	require( MODULE_PATH );
} );
