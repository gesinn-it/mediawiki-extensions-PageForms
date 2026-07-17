'use strict';

const sinon = require( 'sinon' );
const mapServicesMock = require( './mapServicesMock.js' );

// PF_maps.js does not wrap setupMapFormInput() in an IIFE, so it is not
// reachable directly from a test (it stays local to the CommonJS module
// wrapper). setupMapFormInput() is instead invoked the same way production
// code invokes it: via the jQuery(document).ready() handler that scans for
// .pfGoogleMapsInput/.pfLeafletInput/.pfOpenLayersInput elements already in
// the DOM at require() time. Only pfRoundOffDecimal() — extracted onto the
// `pf` namespace in a prior commit — is unit tested directly; everything
// else is exercised end-to-end through the DOM against the shared
// google.maps/L/OpenLayers mock in mapServicesMock.js.

const SCRIPT = '../../libs/PF_maps.js';

function freshRequire() {
	delete require.cache[ require.resolve( SCRIPT ) ];
	require( SCRIPT );
}

// jQuery(document).ready() defers to a macrotask even when the document is
// already "complete" (see PF_sfselect.test.js for the same pattern), and the
// Google Maps click handler additionally delays marker placement by 200ms
// (see PF_maps.js: "Let a click set the marker ... keeping the default
// behavior for double clicks"), so the flush has to clear both.
function flushReady() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 250 );
	} );
}

// This repo's ESLint config targets ES2016 and rejects `async` functions
// (see PF_autoedit.test.js for the equivalent assert.async() pattern). Every
// test here is a Promise chain built from flushReady(), so wrap it in
// QUnit's async helper once instead of repeating the boilerplate per test.
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
 * @param {string} [opts.boundCoords] "lat1, lon1;lat2, lon2" for data-bound-coords
 * @param {string} [opts.imagePath] data-image-path (Leaflet image overlay)
 * @param {number} [opts.imageHeight] data-height
 * @param {number} [opts.imageWidth] data-width
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
	if ( opts.boundCoords !== undefined ) {
		$coordsInput.attr( 'data-bound-coords', opts.boundCoords );
	}
	const $mapCanvas = $( '<div>' ).addClass( 'pfMapCanvas' );

	const $wrapper = $( '<div>' ).addClass( wrapperClass )
		.append( $addressWrapper )
		.append( $lookUpAddress )
		.append( $coordsInput )
		.append( $mapCanvas )
		.appendTo( document.body );

	if ( opts.imagePath !== undefined ) {
		$wrapper.attr( 'data-image-path', opts.imagePath );
		$wrapper.attr( 'data-height', opts.imageHeight );
		$wrapper.attr( 'data-width', opts.imageWidth );
	}

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

QUnit.module( 'PF_maps', commonHooks() );

// ── pfRoundOffDecimal ────────────────────────────────────────────────────────

QUnit.test( 'pfRoundOffDecimal: rounds to five decimal places', ( assert ) => {
	freshRequire();
	assert.strictEqual( window.pf.pfRoundOffDecimal( 1.123456789 ), 1.12346 );
} );

QUnit.test( 'pfRoundOffDecimal: leaves a value with fewer than five decimals unchanged', ( assert ) => {
	freshRequire();
	assert.strictEqual( window.pf.pfRoundOffDecimal( 1.5 ), 1.5 );
} );

QUnit.test( 'pfRoundOffDecimal: handles negative numbers', ( assert ) => {
	freshRequire();
	assert.strictEqual( window.pf.pfRoundOffDecimal( -45.123456 ), -45.12346 );
} );

// ── Google Maps ──────────────────────────────────────────────────────────────

QUnit.module( 'PF_maps: Google Maps', commonHooks() );

asyncTest( 'clicking the map places a marker and fills in the coords input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		global.google.maps.event.trigger( map, 'click', { latLng: new global.google.maps.LatLng( 12.3456789, 45.6789012 ) } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '12.34568, 45.6789' );
		assert.strictEqual( global.google.maps.instances.markers.length, 1, 'exactly one marker was created' );
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
		assert.strictEqual( global.google.maps.instances.markers.length, 1, 'the same marker instance is reused' );
		assert.strictEqual( $coordsInput.val(), '2, 2' );
	} );
} );

asyncTest( 'a double click clears the pending single-click marker timer', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		global.google.maps.event.trigger( map, 'click', { latLng: new global.google.maps.LatLng( 9, 9 ) } );
		global.google.maps.event.trigger( map, 'dblclick', {} );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '', 'no marker was placed because the click timer was cancelled' );
		assert.strictEqual( global.google.maps.instances.markers.length, 0 );
	} );
} );

asyncTest( 'entering coordinates and pressing Enter sets the marker', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '10, 20' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '10, 20' );
		assert.strictEqual( global.google.maps.instances.markers.length, 1 );
	} );
} );

asyncTest( 'invalid coordinates (wrong number of parts) clear the input', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '10, 20, 30' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '' );
		assert.strictEqual( global.google.maps.instances.markers.length, 0, 'no marker created for invalid input' );
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
		assert.strictEqual( global.google.maps.instances.markers.length, 1, 'a marker is placed for the pre-filled value' );
		assert.strictEqual( global.google.maps.instances.maps[ 0 ].zoomLevel, 14, 'map zooms in to the pre-filled coordinates' );
	} );
} );

asyncTest( 'data-bound-coords fits the map to the given bounds when no coords value is set', ( assert ) => {
	createMapInput( 'pfGoogleMapsInput', { boundCoords: '10, 20;30, 40' } );
	freshRequire();
	return flushReady().then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		assert.ok( map.bounds instanceof global.google.maps.LatLngBounds, 'map.fitBounds() was called with a LatLngBounds' );
		assert.strictEqual( map.bounds.points.length, 2, 'both corner points were extended into the bounds' );
	} );
} );

asyncTest( 'invalid data-bound-coords are ignored', ( assert ) => {
	createMapInput( 'pfGoogleMapsInput', { boundCoords: 'abc, def;30, 40' } );
	freshRequire();
	return flushReady().then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		assert.strictEqual( map.bounds, undefined, 'fitBounds() is never called for a non-numeric bound' );
	} );
} );

asyncTest( 'out-of-range data-bound-coords are ignored', ( assert ) => {
	createMapInput( 'pfGoogleMapsInput', { boundCoords: '999, 20;30, 40' } );
	freshRequire();
	return flushReady().then( () => {
		const map = global.google.maps.instances.maps[ 0 ];
		assert.strictEqual( map.bounds, undefined, 'fitBounds() is never called for an out-of-range bound' );
	} );
} );

asyncTest( 'looking up an address geocodes and sets the marker on success', ( assert ) => {
	const { $addressInput, $lookUpAddress, $coordsInput } = createMapInput( 'pfGoogleMapsInput' );
	$addressInput.val( 'Berlin' );
	freshRequire();
	return flushReady().then( () => {
		$lookUpAddress.trigger( 'click' );

		const geocoder = global.google.maps.instances.geocoders[ 0 ];
		assert.strictEqual( geocoder.lastRequest.address, 'Berlin', 'the address input value was passed to geocode()' );

		const fakeLocation = new global.google.maps.LatLng( 52.52, 13.405 );
		geocoder.lastCallback( [ { geometry: { location: fakeLocation } } ], global.google.maps.GeocoderStatus.OK );

		assert.strictEqual( $coordsInput.val(), '52.52, 13.405', 'coords input is filled from the geocode result' );
		assert.strictEqual( global.google.maps.instances.maps[ 0 ].zoomLevel, 14 );
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

		assert.true( global.alert.calledOnce, 'alert() is shown when geocoding fails' );
		assert.true( global.alert.firstCall.args[ 0 ].includes( 'ZERO_RESULTS' ) );
	} );
} );

asyncTest( 'pressing Enter in the address field looks up the address instead of submitting the form', ( assert ) => {
	const { $addressInput } = createMapInput( 'pfGoogleMapsInput' );
	$addressInput.val( 'Berlin' );
	freshRequire();
	return flushReady().then( () => {
		$addressInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( global.google.maps.instances.geocoders[ 0 ].lastRequest.address, 'Berlin' );
	} );
} );

// ── Leaflet ───────────────────────────────────────────────────────────────────

QUnit.module( 'PF_maps: Leaflet', commonHooks() );

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

asyncTest( 'a second rapid click on the map is treated as a double click and does not move the marker', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.L.instances.maps[ 0 ];
		map.trigger( 'click', { latlng: { lat: 1, lng: 1 } } );
		map.trigger( 'click', { latlng: { lat: 2, lng: 2 } } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '', 'the double-click was treated as a zoom, not a marker placement' );
	} );
} );

asyncTest( 'initial coordinates present in the input are applied on setup', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput', { coordsValue: '5, 6' } );
	freshRequire();
	return flushReady().then( () => {
		assert.strictEqual( $coordsInput.val(), '5, 6' );
		assert.strictEqual( global.L.instances.markers.length, 1 );
	} );
} );

asyncTest( 'longitude beyond +-180 is normalized on a normal (non-image) map', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfLeafletInput' );
	freshRequire();
	return flushReady().then( () => {
		const map = global.L.instances.maps[ 0 ];
		map.trigger( 'click', { latlng: { lat: 10, lng: 190 } } );
		return flushReady();
	} ).then( () => {
		assert.strictEqual( $coordsInput.val(), '10, -170', 'longitude 190 normalizes to -170' );
	} );
} );

asyncTest( 'setting coordinates on an image-overlay map scales the marker position by image width', ( assert ) => {
	// setMarkerFromCoordinates() scales the entered lat/lon up by
	// imageWidth/100 before placing the marker, matching the source image's
	// pixel grid rather than a geographic coordinate space; leafletSetMarker()
	// scales the marker's position back down by 100/imageWidth when writing
	// the coords input, so the two operations are inverse and the displayed
	// value round-trips — but the marker's raw stored position (asserted via
	// the mock's instance tracking) must reflect the scaled-up value.
	const { $coordsInput } = createMapInput( 'pfLeafletInput', {
		coordsValue: '10, 20',
		imagePath: 'http://example.com/image.png',
		imageHeight: 200,
		imageWidth: 400
	} );
	freshRequire();
	return flushReady().then( () => {
		const marker = global.L.instances.markers[ 0 ];
		assert.strictEqual( marker.location.lat, 40, 'lat scaled up by imageWidth / 100 = 4' );
		assert.strictEqual( marker.location.lng, 80, 'lon scaled up by imageWidth / 100 = 4' );
		assert.strictEqual( $coordsInput.val(), '10, 20', 'displayed value is scaled back down for the user' );
	} );
} );

asyncTest( 'data-bound-coords fits the map to the given bounds when no coords value is set', ( assert ) => {
	createMapInput( 'pfLeafletInput', { boundCoords: '10, 20;30, 40' } );
	freshRequire();
	return flushReady().then( () => {
		const map = global.L.instances.maps[ 0 ];
		assert.deepEqual( map.bounds, [ [ '10', '20' ], [ '30', '40' ] ] );
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
			assert.true( ajaxStub.calledOnce );
			assert.true( ajaxStub.firstCall.args[ 0 ].includes( 'Berlin' ), 'the address text was URL-encoded into the query' );
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

QUnit.module( 'PF_maps: OpenLayers', commonHooks() );

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

asyncTest( 'an existing map-canvas id is kept as-is', ( assert ) => {
	const { $mapCanvas } = createMapInput( 'pfOpenLayersInput' );
	$mapCanvas.attr( 'id', 'explicit-id' );
	freshRequire();
	return flushReady().then( () => {
		assert.strictEqual( $mapCanvas.attr( 'id' ), 'explicit-id' );
		assert.strictEqual( global.OpenLayers.instances.maps[ 0 ].canvasId, 'explicit-id' );
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

asyncTest( 'entering coordinates and pressing Enter sets the marker', ( assert ) => {
	const { $coordsInput } = createMapInput( 'pfOpenLayersInput' );
	freshRequire();
	return flushReady().then( () => {
		$coordsInput.val( '10, 20' );
		$coordsInput.trigger( $.Event( 'keypress', { keyCode: 13, which: 13 } ) );

		assert.strictEqual( $coordsInput.val(), '10, 20' );
		assert.strictEqual( global.OpenLayers.instances.markers.length, 1 );
	} );
} );

asyncTest( 'data-bound-coords zooms to the given extent when no coords value is set', ( assert ) => {
	createMapInput( 'pfOpenLayersInput', { boundCoords: '10, 20;30, 40' } );
	freshRequire();
	return flushReady().then( () => {
		const map = global.OpenLayers.instances.maps[ 0 ];
		assert.ok( map.bounds instanceof global.OpenLayers.Bounds );
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
