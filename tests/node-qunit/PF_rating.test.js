'use strict';

// Minimal rateYo stub.
jQuery.fn.rateYo = function () {
	return this;
};

QUnit.module( 'PF_rating' );

// Regression test: PF_rating.js must load without a global `pf`.
QUnit.test( 'module loads without pf global', ( assert ) => {
	const saved = global.pf;
	delete global.pf;
	delete require.cache[ require.resolve( '../../libs/PF_rating.js' ) ];
	let threw = false;
	try {
		require( '../../libs/PF_rating.js' );
	} catch ( e ) {
		threw = true;
	} finally {
		global.pf = saved;
	}
	assert.false( threw, 'module loaded without ReferenceError' );
} );
