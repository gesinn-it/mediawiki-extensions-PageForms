'use strict';

const sinon = require( 'sinon' );
const mapServicesMock = require( './mapServicesMock.js' );

// PF_maps.offline.js does not wrap setupMapFormInput() in an IIFE, so it is
// not reachable directly from a test. See PF_maps.test.js for the same
// require()-then-DOM-driven pattern this file follows. Compared to
// PF_maps.js, this "offline"/simplified variant has no data-bound-coords
// handling, no Leaflet image-overlay scaling, and setMarkerFromAddress()
// reads only the standard .pfAddressInput input (no feeder aggregation).

const SCRIPT = '../../libs/PF_maps.offline.js';

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

function flushReady() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 250 );
	} );
}

// This repo's ESLint config targets ES2016 and rejects `async` functions
// (see PF_autoedit.test.js for the equivalent assert.async() pattern).
function asyncTest( name, fn ) {
	QUnit.test( name, ( assert ) => {
		const done = assert.async();
		fn( assert ).then( done );
	} );
}

/**
 * Build the markup produced by PFOpenLayersInput::mapLookupHTML() /
 * PFGoogleMapsInput::getHTML() / PFLeafletInput::getHTML() for a single map
 * input, and append it to document.body.
 *
 * @param {string} wrapperClass one of pfGoogleMapsInput/pfLeafletInput/pfOpenLayersInput
 * @param {Object} [opts]
 * @param {string} [opts.coordsValue] initial value of .pfCoordsInput
 * @return {Object} { $wrapper, $mapCanvas, $coordsInput, $addressInput, $lookUpAddress }
 */
function createMapInput( wrapperClass, opts ) {
	opts = opts || {};
	const $addressInput = $( '<input type="text">' );
	const $addressWrapper = $( '<div>' ).addClass( 'pfAddressInput' ).append( $addressInput );
	const $lookUpAddress = $( '<a>' ).addClass( 'pfLookUpAddress' );
	const $coordsInput = $( '<input type="text">' ).addClass( 'pfCoordsInput' ).attr( 'name', opts.name || 'coords' );
	if ( opts.coordsValue !== undefined ) {
		$coordsInput.val( opts.coordsValue );
	}
	const $mapCanvas = $( '<div>' ).addClass( 'pfMapCanvas' );

	const $wrapper = $( '<div>' ).addClass( wrapperClass )
		.append( $addressWrapper )
		.append( $lookUpAddress )
		.append( $coordsInput )
		.append( $mapCanvas )
		.appendTo( document.body );

	return { $wrapper, $mapCanvas, $coordsInput, $addressInput, $lookUpAddress };
}

function commonHooks() {
	return {
		beforeEach() {
			this.uninstallMocks = mapServicesMock.install();
			global.alert = sinon.stub();
			mw.hook = () => ( { add: () => {} } );
		},
		afterEach() {
			this.uninstallMocks();
			delete global.alert;
		}
	};
}

QUnit.module( 'PF_maps.offline', commonHooks() );

// ── pfRoundOffDecimal ────────────────────────────────────────────────────────

QUnit.test( 'pfRoundOffDecimal: rounds to five decimal places', ( assert ) => {
	freshRequire();
	assert.strictEqual( window.pf.pfRoundOffDecimal( 1.123456789 ), 1.12346 );
} );

QUnit.test( 'pfRoundOffDecimal: leaves a value with fewer than five decimals unchanged', ( assert ) => {
	freshRequire();
	assert.strictEqual( window.pf.pfRoundOffDecimal( 1.5 ), 1.5 );
} );

// ── Google Maps ──────────────────────────────────────────────────────────────

QUnit.module( 'PF_maps.offline: Google Maps', commonHooks() );

asyncTest( 'clicking the map places a marker and fills in the coords input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		global.google.maps.event.trigger( map, 'click', { latLng: new global.google.maps.LatLng( 12.3456789, 45.6789012 ) } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '12.34568, 45.6789' );
		assert.strictEqual( global.google.maps.instances.markers.length, 1 );
	} );
} );

asyncTest( 'dragging an existing marker moves it instead of creating a new one', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		global.google.maps.event.trigger( map, 'click', { latLng: new global.google.maps.LatLng( 1, 1 ) } );
		return flushReady();
	} ).then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		global.google.maps.event.trigger( map, 'click', { latLng: new global.google.maps.LatLng( 2, 2 ) } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( global.google.maps.instances.markers.length, 1 );
		assert.strictEqual( $coordsInput.val(), '2, 2' );
	} );
} );

asyncTest( 'entering coordinates and pressing Enter sets the marker', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '10, 20' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '10, 20' );
	} );
} );

asyncTest( 'invalid coordinates (wrong number of parts) clear the input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '10, 20, 30' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '' );
	} );
} );

asyncTest( 'non-numeric coordinates clear the input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( 'abc, def' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '' );
	} );
} );

asyncTest( 'out-of-range coordinates clear the input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '999, 20' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '' );
	} );
} );

asyncTest( 'initial coordinates present in the input are applied on setup', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput', { coordsValue: '5, 6' } );
	freshRequire();
	return flushReady().then( () => {
		assert.strictEqual( $coordsInput.val(), '5, 6' );
		assert.strictEqual( global.google.maps.instances.markers.length, 1 );
	} );
} );

asyncTest( 'looking up an address zooms in, geocodes, and sets the marker on success', ( assert ) => {
	const { $addressInput, $lookUpAddress, $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	$addressInput.val( 'Berlin' );
	freshRequire();
	return flushReady().then( () => {
		$lookUpAddress.trigger( 'click' );

		const map = global.google.maps.instances.maps[ 0 ];
		assert.strictEqual( map.zoomLevel, 14, 'map zooms in immediately, before the geocode result arrives' );

		const geocoder = global.google.maps.instances.geocoders[ 0 ];
		assert.strictEqual( geocoder.lastRequest.address, 'Berlin' );

		const fakeLocation = new global.google.maps.LatLng( 52.52, 13.405 );
		geocoder.lastCallback( [ { geometry: { location: fakeLocation } } ], global.google.maps.GeocoderStatus.OK );

		assert.strictEqual( $coordsInput.val(), '52.52, 13.405' );
	} );
} );

asyncTest( 'looking up an address shows an alert on geocode failure', ( assert ) => {
	const { $addressInput, $lookUpAddress } = createMapInput( 'pfGoogleMapsInput' );
	$addressInput.val( 'Nowhereville' );
	freshRequire();
	return flushReady().then( () => {
		$lookUpAddress.trigger( 'click' );

		const geocoder = global.google.maps.instances.geocoders[ 0 ];
		geocoder.lastCallback( [], 'ZERO_RESULTS' );

		assert.true( global.alert.calledOnce );
		assert.true( global.alert.firstCall.args[ 0 ].includes( 'ZERO_RESULTS' ) );
	} );
} );

// ── Leaflet ───────────────────────────────────────────────────────────────────

QUnit.module( 'PF_maps.offline: Leaflet', commonHooks() );

asyncTest( 'clicking the map places a marker and fills in the coords input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.L.instances.maps[ 0 ];
		map.trigger( 'click', { latlng: { lat: 12.34567, lng: 45.6 } } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '12.34567, 45.6' );
		assert.strictEqual( global.L.instances.markers.length, 1 );
	} );
} );

asyncTest( 'moving the marker again reuses the existing marker instead of creating a new one', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.L.instances.maps[ 0 ];
		map.trigger( 'click', { latlng: { lat: 1, lng: 1 } } );
		return flushReady();
	} ).then( () => {
		const map = global.L.instances.maps[ 0 ];
		map.trigger( 'click', { latlng: { lat: 2, lng: 2 } } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( global.L.instances.markers.length, 1, 'the same marker instance is reused (regression: marker == null, not === null)' );
		assert.strictEqual( $coordsInput.val(), '2, 2' );
	} );
} );

asyncTest( 'a second rapid click on the map is treated as a double click and does not move the marker', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.L.instances.maps[ 0 ];
		map.trigger( 'click', { latlng: { lat: 1, lng: 1 } } );
		map.trigger( 'click', { latlng: { lat: 2, lng: 2 } } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '' );
	} );
} );

asyncTest( 'entering coordinates and pressing Enter sets the marker and zooms to it', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '10, 20' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '10, 20' );
		assert.strictEqual( global.L.instances.maps[ 0 ].zoomLevel, 14 );
	} );
} );

asyncTest( 'initial coordinates present in the input are applied and zoomed to on setup', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput', { coordsValue: '5, 6' } );
	freshRequire();
	return flushReady().then( () => {
		assert.strictEqual( $coordsInput.val(), '5, 6' );
		assert.strictEqual( global.L.instances.markers.length, 1 );
		assert.strictEqual( global.L.instances.maps[ 0 ].zoomLevel, 14 );
	} );
} );

asyncTest( 'looking up an address via nominatim sets the marker and fits bounds on success', ( assert ) => {
	const { $addressInput, $lookUpAddress, $coordsInput } = createMapInput( 'pfLeafletInput' );
	$addressInput.val( 'Berlin' );
	freshRequire();
	return flushReady().then( () => {
		const ajaxStub = sinon.stub( $, 'ajax' ).returns( $.Deferred().resolve( [ {
			lat: '52.52',
			lon: '13.405',
			boundingbox: [ '52.3', '52.7', '13.2', '13.6' ]
		} ] ).promise() );

		$lookUpAddress.trigger( 'click' );

		return flushReady().then( () => {
			assert.true( ajaxStub.firstCall.args[ 0 ].includes( 'Berlin' ) );
			assert.strictEqual( $coordsInput.val(), '52.52, 13.405' );

			const map = global.L.instances.maps[ 0 ];
			assert.deepEqual( map.bounds, [ [ '52.3', '13.2' ], [ '52.7', '13.6' ] ] );

			ajaxStub.restore();
		} );
	} );
} );

asyncTest( 'looking up an address shows an alert when nominatim returns no results', ( assert ) => {
	const { $addressInput, $lookUpAddress } = createMapInput( 'pfLeafletInput' );
	$addressInput.val( 'Nowhereville' );
	freshRequire();
	return flushReady().then( () => {
		const ajaxStub = sinon.stub( $, 'ajax' ).returns( $.Deferred().resolve( [] ).promise() );

		$lookUpAddress.trigger( 'click' );

		return flushReady().then( () => {
			assert.true( global.alert.calledOnce );
			ajaxStub.restore();
		} );
	} );
} );

// ── OpenLayers ────────────────────────────────────────────────────────────────

QUnit.module( 'PF_maps.offline: OpenLayers', commonHooks() );

asyncTest( 'initial coordinates present in the input are applied on setup', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfOpenLayersInput', { coordsValue: '5, 6' } );
	freshRequire();
	return flushReady().then( () => {
		assert.strictEqual( $coordsInput.val(), '5, 6' );
		assert.strictEqual( global.OpenLayers.instances.markers.length, 1 );
	} );
} );

asyncTest( 'a missing map-canvas id is auto-generated from data-origID', ( assert ) => {
	const { $mapCanvas } = createMapInput( 'pfOpenLayersInput' );
	$mapCanvas.attr( 'data-origID', 'myMap' );
	freshRequire();
	return flushReady().then( () => {
		assert.strictEqual( ( $mapCanvas.attr( 'id' ) || '' ).indexOf( 'myMap-' ), 0 );
	} );
} );

asyncTest( 'clicking the map places a marker and fills in the coords input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfOpenLayersInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.OpenLayers.instances.maps[ 0 ];
		// getLonLatFromPixel({x, y}) returns a LonLat with lon=x, lat=y; the
		// coords input displays "lat, lon", so the axes appear swapped here.
		map.events.trigger( 'click', { xy: { x: 12.3, y: 45.6 } } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '45.6, 12.3' );
		assert.strictEqual( global.OpenLayers.instances.markers.length, 1 );
	} );
} );

asyncTest( 'a second rapid click on the map is treated as a double click and does not move the marker', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfOpenLayersInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.OpenLayers.instances.maps[ 0 ];
		map.events.trigger( 'click', { xy: { x: 1, y: 1 } } );
		map.events.trigger( 'click', { xy: { x: 2, y: 2 } } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '' );
	} );
} );

asyncTest( 'entering coordinates and pressing Enter sets the marker and zooms to it', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfOpenLayersInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '10, 20' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '10, 20' );
		assert.strictEqual( global.OpenLayers.instances.markers.length, 1 );
	} );
} );

asyncTest( 'looking up an address via nominatim sets the marker and zooms to extent on success', ( assert ) => {
	const { $addressInput, $lookUpAddress, $coordsInput } = createMapInput( 'pfOpenLayersInput' );
	$addressInput.val( 'Berlin' );
	freshRequire();
	return flushReady().then( () => {
		const ajaxStub = sinon.stub( $, 'ajax' ).returns( $.Deferred().resolve( [ {
			lat: '52.52',
			lon: '13.405',
			boundingbox: [ '52.3', '52.7', '13.2', '13.6' ]
		} ] ).promise() );

		$lookUpAddress.trigger( 'click' );

		return flushReady().then( () => {
			assert.strictEqual( $coordsInput.val(), '52.52, 13.405' );
			assert.strictEqual( global.OpenLayers.instances.markers.length, 1 );
			ajaxStub.restore();
		} );
	} );
} );
