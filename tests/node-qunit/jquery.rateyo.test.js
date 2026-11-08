'use strict';

// libs/jquery.rateyo.js is a compatible re-implementation of rateYo 2.3.2. These tests run it side by
// side with the original (the "rateyo" dev dependency) and require the same markup, the same events
// with the same data, the same return values and the same errors for the same calls.

const path = require( 'path' );

const previous = $.fn.rateYo;
window.jQuery = $;

require( path.resolve( __dirname, '../../node_modules/rateyo/src/jquery.rateyo.js' ) );
const original = $.fn.rateYo;

require( '../../libs/jquery.rateyo.js' );
const mine = $.fn.rateYo;

// Other tests replace $.fn.rateYo with a stub; leave it as it was
if ( previous ) {
	$.fn.rateYo = previous;
} else {
	delete $.fn.rateYo;
}

const GROUP_LEFT = 100;
const GROUP_WIDTH = 160;

QUnit.module( 'jquery.rateyo compared with the original', {
	beforeEach() {
		// jsdom has no layout: give the stars a position and a width
		const offset = $.fn.offset;
		const width = $.fn.width;
		this.restore = () => {
			$.fn.offset = offset;
			$.fn.width = width;
		};
		$.fn.offset = function () {
			return this.hasClass( 'jq-ry-normal-group' ) ? { top: 0, left: GROUP_LEFT } :
				offset.apply( this, arguments );
		};
		$.fn.width = function ( value ) {
			return value === undefined && this.hasClass( 'jq-ry-normal-group' ) ?
				GROUP_WIDTH : width.apply( this, arguments );
		};
	},
	afterEach() {
		this.restore();
	}
} );

/**
 * @param {Function} impl
 * @param {Object|undefined} options
 * @param {Object} [attrs] attributes of the element
 * @return {{ $el: jQuery, events: Array }}
 */
function create( impl, options, attrs ) {
	const $el = $( '<div>' ).attr( attrs || {} ).appendTo( document.body );
	const events = [];
	[ 'init', 'change', 'set' ].forEach( ( name ) => {
		$el.on( 'rateyo.' + name, ( e, data ) => events.push( [ name, data.rating ] ) );
	} );
	if ( options === undefined ) {
		impl.call( $el );
	} else {
		impl.call( $el, options );
	}
	return { $el, events };
}

function attempt( fn ) {
	try {
		return { value: fn() };
	} catch ( e ) {
		return { error: e.message };
	}
}

/**
 * @param {*} value what a call returned
 * @param {jQuery} $el the element of the widget
 * @return {*} the value, or a marker for a jQuery object of the element
 */
function describe( value, $el ) {
	return value && value.jquery ? { jquery: value.length, sameElement: value[ 0 ] === $el[ 0 ] } : value;
}

/**
 * Triggers a mouse event; an error of a handler (the original throws when a click on the very end of
 * the stars is rounded up beyond the maximum) is recorded like an event.
 *
 * @param {{ $el: jQuery, events: Array }} made
 * @param {string} type
 * @param {number} pageX
 */
function mouse( made, type, pageX ) {
	const result = attempt( () => made.$el.trigger( $.Event( type, { pageX } ) ) );
	if ( result.error ) {
		made.events.push( [ 'error', type, result.error ] );
	}
}

function snapshot( made ) {
	return { html: made.$el[ 0 ].outerHTML, events: made.events.slice() };
}

const OPTION_SETS = {
	'defaults': undefined,
	'empty options': {},
	'as used by Page Forms (strings from attributes)': {
		normalFill: '#ddd', starWidth: '20px', numStars: '5', maxValue: '5', rating: '3', fullStar: true
	},
	'rating 0 with half stars': {
		normalFill: '#ddd', starWidth: '24px', numStars: 4, maxValue: 4, rating: 0, halfStar: true
	},
	'half star value': { rating: 2.5, halfStar: true },
	'read only': { rating: 3, readOnly: true },
	'spacing': { spacing: '8px', rating: 2.5, numStars: 4, maxValue: 4 },
	'rtl': { rtl: true, rating: 2, fullStar: true },
	'max value differs from the number of stars': { numStars: 4, maxValue: 10, rating: 5 },
	'percentage rating': { rating: '60%' },
	'precision': { precision: 2, rating: 2.345 },
	'multicolor default': { multiColor: {}, rating: 3 },
	'multicolor custom': { multiColor: { startColor: '#000000', endColor: '#ffffff' }, rating: 2 },
	'custom star svg': {
		starSvg: '<svg viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>',
		numStars: 3,
		maxValue: 3,
		rating: 1
	},
	'one star': { numStars: 1, maxValue: 1, rating: 1 }
};

Object.keys( OPTION_SETS ).forEach( ( name ) => {
	QUnit.test( 'creates the same markup and the init event: ' + name, ( assert ) => {
		assert.deepEqual(
			snapshot( create( mine, OPTION_SETS[ name ] ) ),
			snapshot( create( original, OPTION_SETS[ name ] ) )
		);
	} );

	QUnit.test( 'reacts the same to the mouse: ' + name, ( assert ) => {
		const run = ( impl ) => {
			const made = create( impl, OPTION_SETS[ name ] );
			const states = [];
			const positions = [ GROUP_LEFT - 10, GROUP_LEFT, GROUP_LEFT + 1, GROUP_LEFT + 17, GROUP_LEFT + 40,
				GROUP_LEFT + 79, GROUP_LEFT + 80, GROUP_LEFT + 123, GROUP_LEFT + GROUP_WIDTH, GROUP_LEFT + GROUP_WIDTH + 30 ];
			positions.forEach( ( x ) => {
				mouse( made, 'mouseenter', x );
				mouse( made, 'mousemove', x );
				states.push( snapshot( made ) );
				mouse( made, 'click', x );
				states.push( snapshot( made ) );
				mouse( made, 'mouseleave', x );
				states.push( snapshot( made ) );
			} );
			return states;
		};

		assert.deepEqual( run( mine ), run( original ) );
	} );
} );

QUnit.test( 'reads the options from data-rateyo-* attributes', ( assert ) => {
	const attrs = { 'data-rateyo-rating': '3', 'data-rateyo-num-stars': '3', 'data-rateyo-max-value': '3',
		'data-rateyo-star-width': '12px', 'data-rateyo-half-star': 'true' };

	assert.deepEqual(
		snapshot( create( mine, { rating: 1 }, attrs ) ),
		snapshot( create( original, { rating: 1 }, attrs ) )
	);
} );

QUnit.test( 'returns the element set and ignores an empty one', ( assert ) => {
	const run = ( impl ) => {
		const $el = $( '<div>' ).appendTo( document.body );
		return [ impl.call( $el ) === $el || impl.call( $el ).length, impl.call( $() ).length ];
	};

	assert.deepEqual( run( mine ), run( original ) );
} );

QUnit.test( 'does not initialise an element twice', ( assert ) => {
	const run = ( impl ) => {
		const made = create( impl, { rating: 2 } );
		impl.call( made.$el, { rating: 4 } );
		return [ snapshot( made ), impl.call( made.$el, 'rating' ) ];
	};

	assert.deepEqual( run( mine ), run( original ) );
} );

const METHOD_CALLS = [
	[ 'rating' ],
	[ 'rating', 4 ],
	[ 'rating', '3' ],
	[ 'rating', '50%' ],
	[ 'rating', 0 ],
	[ 'rating', 6 ],
	[ 'rating', -1 ],
	[ 'option', 'rating' ],
	[ 'option', 'rating', 2 ],
	[ 'option', 'starWidth' ],
	[ 'option', 'starWidth', '40px' ],
	[ 'option', 'numStars', 7 ],
	[ 'option', 'numStars', 2 ],
	[ 'option', 'normalFill', '#eee' ],
	[ 'option', 'ratedFill', '#00f' ],
	[ 'option', 'multiColor', {} ],
	[ 'option', 'multiColor', { startColor: '#112233', endColor: '#aabbcc' } ],
	[ 'option', 'maxValue', 10 ],
	[ 'option', 'maxValue', 2 ],
	[ 'option', 'precision', 0 ],
	[ 'option', 'precision', 3 ],
	[ 'option', 'halfStar', true ],
	[ 'option', 'fullStar', true ],
	[ 'option', 'readOnly', true ],
	[ 'option', 'readOnly', false ],
	[ 'option', 'spacing', '6px' ],
	[ 'option', 'rtl', true ],
	[ 'option', 'rtl', false ],
	[ 'option', 'noSuchOption', 1 ],
	[ 'noSuchMethod' ],
	[ 'destroy' ]
];

METHOD_CALLS.forEach( ( call ) => {
	QUnit.test( 'method call ' + JSON.stringify( call ), ( assert ) => {
		const run = ( impl ) => {
			const made = create( impl, { rating: 3, numStars: 5, starWidth: '20px' } );
			const result = attempt( () => impl.apply( made.$el, call ) );
			return {
				result: result.error ? result : { value: describe( result.value, made.$el ) },
				after: snapshot( made ),
				rating: attempt( () => impl.call( made.$el, 'rating' ) )
			};
		};

		assert.deepEqual( run( mine ), run( original ) );
	} );
} );

QUnit.test( 'the options object', ( assert ) => {
	const run = ( impl ) => {
		const made = create( impl, { rating: 2, normalFill: '#ddd' } );
		return JSON.parse( JSON.stringify( impl.call( made.$el, 'option' ) ) );
	};

	assert.deepEqual( run( mine ), run( original ) );
} );

QUnit.test( 'errors for calls without a widget and for invalid arguments', ( assert ) => {
	const run = ( impl ) => {
		const $plain = $( '<div>' ).appendTo( document.body );
		return [
			attempt( () => impl.call( $plain, 'rating' ) ),
			attempt( () => impl.call( $plain, 'option', 'rating', 1 ) ),
			attempt( () => impl.call( $plain, 1 ) ),
			attempt( () => impl.call( $plain, 'rating', 1 ) )
		];
	};

	assert.deepEqual( run( mine ), run( original ) );
} );

QUnit.test( 'the callbacks get the rating and the instance', ( assert ) => {
	const run = ( impl ) => {
		const calls = [];
		const record = ( name ) => function ( rating, instance ) {
			calls.push( [ name, rating, typeof instance.rating, typeof instance.destroy, this.nodeName ] );
		};
		const made = create( impl, {
			rating: 2, onInit: record( 'init' ), onChange: record( 'change' ), onSet: record( 'set' )
		} );
		mouse( made, 'mousemove', GROUP_LEFT + 70 );
		mouse( made, 'click', GROUP_LEFT + 70 );
		mouse( made, 'mouseleave', GROUP_LEFT + 70 );
		impl.call( made.$el, 'option', 'onSet', record( 'set2' ) );
		impl.call( made.$el, 'rating', 1 );
		return calls;
	};

	assert.deepEqual( run( mine ), run( original ) );
} );

QUnit.test( 'a mobile browser keeps the shown rating on mouseleave', ( assert ) => {
	const mobile = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36';
	// Both libraries read the global "navigator"
	const saved = Object.getOwnPropertyDescriptor( global, 'navigator' );
	Object.defineProperty( global, 'navigator', { value: { userAgent: mobile }, configurable: true } );
	try {
		const run = ( impl ) => {
			const made = create( impl, { rating: 1 } );
			mouse( made, 'mousemove', GROUP_LEFT + 100 );
			mouse( made, 'mouseleave', GROUP_LEFT + 100 );
			return snapshot( made );
		};
		const mineResult = run( mine );

		assert.deepEqual( mineResult, run( original ) );
		assert.notStrictEqual( mineResult.events.filter( ( e ) => e[ 0 ] === 'change' ).pop()[ 1 ], 1,
			'the hover rating is still shown after leaving' );
	} finally {
		if ( saved ) {
			Object.defineProperty( global, 'navigator', saved );
		} else {
			delete global.navigator;
		}
	}
} );
