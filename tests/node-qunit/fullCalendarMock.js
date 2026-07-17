'use strict';

/**
 * Fake `$.fn.fullCalendar` covering the callback-registration and command
 * surface PF_FullCalendar.js actually uses:
 *
 * - init call: reads the `events`, `select`, `eventClick`, `eventResize`,
 *   `eventDrop` callbacks off the options object and stores them so a test
 *   can fire them synthetically.
 * - command calls: `'renderEvent'`, `'updateEvent'`, `'removeEvents'`,
 *   `'clientEvents'` - a minimal in-memory event store good enough to
 *   round-trip what PF_FullCalendar.js pushes through them.
 *
 * install() assigns `$.fn.fullCalendar` and returns a teardown function.
 */

function makeCalendarInstance() {
	const events = [];
	let options = null;

	return {
		// Test helper: not part of the real FullCalendar API. Records the
		// options object passed at init time and immediately invokes the
		// `events` callback the way FullCalendar itself does on first render.
		init( opts ) {
			options = opts;
			if ( typeof opts.events === 'function' ) {
				opts.events( null, null, null, () => {} );
			}
			return this;
		},
		options() {
			return options;
		},
		command( name, ...args ) {
			switch ( name ) {
				case 'renderEvent':
					events.push( args[ 0 ] );
					return undefined;
				case 'updateEvent': {
					const idx = events.findIndex( ( e ) => e.id === args[ 0 ].id );
					if ( idx !== -1 ) {
						events[ idx ] = args[ 0 ];
					} else {
						events.push( args[ 0 ] );
					}
					return undefined;
				}
				case 'removeEvents': {
					const idx = events.findIndex( ( e ) => e.id === args[ 0 ] );
					if ( idx !== -1 ) {
						events.splice( idx, 1 );
					}
					return undefined;
				}
				case 'clientEvents':
					// PF_FullCalendar.js calls this both with an id filter
					// (eventClick/eventDrop) and with no argument at all
					// (the '#pfForm' submit handler, to read back everything).
					return args[ 0 ] === undefined ?
						events.slice() :
						events.filter( ( e ) => e.id === args[ 0 ] );
				default:
					throw new Error( 'fullCalendarMock: unhandled command "' + name + '"' );
			}
		},
		// Test helper: read back everything currently rendered, e.g. for the
		// "#pfForm submit" hidden-input generation, which reads 'clientEvents'
		// with no id filter.
		allEvents() {
			return events.slice();
		}
	};
}

/**
 * Build a fake calendar-day `moment`-like object good enough for the
 * `select`/`eventResize`/`eventDrop` callbacks: `.format( token )` returns a
 * fixed field per token, and arithmetic helpers used on it just return
 * another fake with the same shape.
 *
 * @param {Object} fields DD/MM/YYYY/hh/mm/ss/t overrides.
 * @return {Object} fake moment
 */
function fakeMoment( fields ) {
	const defaults = { DD: '15', MM: '06', YYYY: '2026', hh: '10', mm: '30', ss: '00', t: 'a' };
	const values = Object.assign( {}, defaults, fields );
	const self = {
		format( token ) {
			if ( token === undefined ) {
				return values.YYYY + '-' + values.MM + '-' + values.DD;
			}
			return values[ token ];
		},
		_i: undefined
	};
	return self;
}

function install() {
	global.$.fn.fullCalendar = function ( optionsOrCommand, ...args ) {
		if ( typeof optionsOrCommand === 'object' ) {
			const newInstance = makeCalendarInstance();
			// The 'events' callback calls back into 'renderEvent' on this
			// same element (via calendarIdSelector), so the instance has to
			// be registered before init() invokes it.
			this.data( 'fullCalendarMockInstance', newInstance );
			newInstance.init( optionsOrCommand );
			return this;
		}

		const instance = this.data( 'fullCalendarMockInstance' );
		if ( !instance ) {
			throw new Error( 'fullCalendarMock: fullCalendar() command called before init' );
		}
		return instance.command( optionsOrCommand, ...args );
	};

	return function teardown() {
		delete global.$.fn.fullCalendar;
	};
}

module.exports = { install, fakeMoment };
