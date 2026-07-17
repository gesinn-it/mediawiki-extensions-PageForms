'use strict';

/**
 * Shared fake implementations of the `google.maps.*`, `L.*` (Leaflet), and
 * `OpenLayers.*` globals that `libs/PF_maps.js` and `libs/PF_maps.offline.js`
 * touch as soon as `setupMapFormInput()` runs. Covers only the API surface
 * those two files actually call — see the grep-derived call list in
 * https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/110.
 *
 * install() assigns `global.google`/`global.L`/`global.OpenLayers` and
 * returns a teardown function that deletes them again.
 */

function makeGoogleMaps() {
	// Test helpers (not part of the real google.maps API): every Map/Marker/
	// Geocoder created by production code is pushed here, so a test can
	// inspect the instance setupMapFormInput() built instead of guessing.
	const instances = { maps: [], markers: [], geocoders: [] };

	function LatLng( lat, lng ) {
		this._lat = Number( lat );
		this._lng = Number( lng );
	}
	LatLng.prototype.lat = function () {
		return this._lat;
	};
	LatLng.prototype.lng = function () {
		return this._lng;
	};

	function LatLngBounds() {
		this.points = [];
	}
	LatLngBounds.prototype.extend = function ( point ) {
		this.points.push( point );
		return this;
	};

	function Map( canvas, options ) {
		this.canvas = canvas;
		this.options = options;
		this.center = options && options.center;
		this.zoomLevel = options && options.zoom;
		instances.maps.push( this );
	}
	Map.prototype.setCenter = function ( latLng ) {
		this.center = latLng;
	};
	Map.prototype.setZoom = function ( zoom ) {
		this.zoomLevel = zoom;
	};
	Map.prototype.fitBounds = function ( bounds ) {
		this.bounds = bounds;
	};

	function Marker( opts ) {
		this.position = opts.position;
		this.map = opts.map;
		this.draggable = opts.draggable;
		instances.markers.push( this );
	}
	Marker.prototype.setPosition = function ( latLng ) {
		this.position = latLng;
	};

	function Geocoder() {
		instances.geocoders.push( this );
	}
	Geocoder.prototype.geocode = function ( request, callback ) {
		this.lastRequest = request;
		this.lastCallback = callback;
	};

	const event = {
		listeners: [],
		addListener: function ( target, eventName, handler ) {
			const entry = { target, eventName, handler };
			event.listeners.push( entry );
			return entry;
		},
		// Test helper (not part of the real google.maps API): invoke the
		// most recently registered handler for `target`/`eventName`.
		trigger: function ( target, eventName, arg ) {
			const matches = event.listeners.filter(
				( l ) => l.target === target && l.eventName === eventName
			);
			const last = matches[ matches.length - 1 ];
			if ( !last ) {
				throw new Error( 'No listener registered for ' + eventName );
			}
			return last.handler( arg );
		}
	};

	return {
		LatLng,
		LatLngBounds,
		Map,
		Marker,
		Geocoder,
		GeocoderStatus: { OK: 'OK' },
		event,
		instances
	};
}

function makeLeaflet() {
	// Test helpers (not part of the real Leaflet API): every LMap/Marker
	// created by production code is pushed here.
	const instances = { maps: [], markers: [] };

	function LatLng( lat, lng ) {
		this.lat = Number( lat );
		this.lng = Number( lng );
	}

	function Marker( location ) {
		this.location = location;
		this._map = null;
		this.dragging = {
			enabled: false,
			enable: function () {
				this.enabled = true;
			}
		};
		this._handlers = {};
		instances.markers.push( this );
	}
	Marker.prototype.addTo = function ( map ) {
		this._map = map;
		return this;
	};
	Marker.prototype.setLatLng = function ( location ) {
		this.location = location;
	};
	Marker.prototype.getLatLng = function () {
		return this.location;
	};
	Marker.prototype.off = function () {
		return this;
	};
	Marker.prototype.on = function ( eventName, handler ) {
		this._handlers[ eventName ] = handler;
		return this;
	};
	// Test helper: fire a previously-registered handler (e.g. 'dragend').
	Marker.prototype.trigger = function ( eventName, arg ) {
		return this._handlers[ eventName ] && this._handlers[ eventName ]( arg );
	};

	function TileLayer() {}
	TileLayer.prototype.addTo = function ( map ) {
		this._map = map;
		return this;
	};

	function ImageOverlay( url, bounds ) {
		this.url = url;
		this.bounds = bounds;
	}
	ImageOverlay.prototype.addTo = function ( map ) {
		this._map = map;
		return this;
	};

	function LMap( canvas, options ) {
		this.canvas = canvas;
		this.options = options;
		this._clickHandlers = [];
		instances.maps.push( this );
	}
	LMap.prototype.on = function ( eventName, handler ) {
		if ( eventName === 'click' ) {
			this._clickHandlers.push( handler );
		}
		return this;
	};
	// Test helper: simulate a map click at {lat, lng}.
	LMap.prototype.trigger = function ( eventName, arg ) {
		if ( eventName === 'click' ) {
			this._clickHandlers.forEach( ( h ) => h( arg ) );
		}
	};
	LMap.prototype.setView = function ( latLng, zoom ) {
		this.center = latLng;
		this.zoomLevel = zoom;
	};
	LMap.prototype.setZoom = function ( zoom ) {
		this.zoomLevel = zoom;
	};
	LMap.prototype.fitBounds = function ( bounds ) {
		this.bounds = bounds;
	};

	return {
		CRS: { Simple: 'Simple' },
		latLng: function ( lat, lng ) {
			return new LatLng( lat, lng );
		},
		marker: function ( location ) {
			return new Marker( location );
		},
		map: function ( canvas, options ) {
			return new LMap( canvas, options );
		},
		tileLayer: function () {
			return new TileLayer();
		},
		imageOverlay: function ( url, bounds ) {
			return new ImageOverlay( url, bounds );
		},
		instances
	};
}

function makeOpenLayers() {
	// Test helpers (not part of the real OpenLayers API): every Map/Marker
	// created by production code is pushed here.
	const instances = { maps: [], markers: [] };

	function LonLat( lon, lat ) {
		this.lon = lon;
		this.lat = lat;
	}
	LonLat.prototype.transform = function () {
		// Identity transform for tests: no real projection math needed.
		return this;
	};
	LonLat.prototype.clone = function () {
		return new LonLat( this.lon, this.lat );
	};

	function Bounds() {}
	Bounds.prototype.transform = function () {
		return this;
	};

	function Projection( code ) {
		this.code = code;
	}

	function Marker( location ) {
		this.location = location;
		instances.markers.push( this );
	}

	function MarkersLayer() {
		this.markers = [];
	}
	MarkersLayer.prototype.addMarker = function ( marker ) {
		this.markers.push( marker );
	};
	MarkersLayer.prototype.clearMarkers = function () {
		this.markers = [];
	};

	function OSMLayer() {}

	function OLMap( canvasId ) {
		this.canvasId = canvasId;
		this.layers = [];
		instances.maps.push( this );
		this.events = {
			_handlers: {},
			register: function ( eventName, context, handler ) {
				this._handlers[ eventName ] = handler;
			},
			// Test helper: fire a previously-registered handler.
			trigger: function ( eventName, arg ) {
				return this._handlers[ eventName ] && this._handlers[ eventName ]( arg );
			}
		};
	}
	OLMap.prototype.addLayer = function ( layer ) {
		this.layers.push( layer );
	};
	OLMap.prototype.zoomTo = function ( zoom ) {
		this.zoomLevel = zoom;
	};
	OLMap.prototype.zoomToExtent = function ( bounds ) {
		this.bounds = bounds;
	};
	OLMap.prototype.setCenter = function ( lonLat, zoom ) {
		this.center = lonLat;
		if ( zoom !== undefined ) {
			this.zoomLevel = zoom;
		}
	};
	OLMap.prototype.getProjectionObject = function () {
		return new Projection( 'EPSG:900913' );
	};
	OLMap.prototype.getLonLatFromPixel = function ( pixel ) {
		return new LonLat( pixel.x, pixel.y );
	};

	return {
		Map: function ( canvasId ) {
			return new OLMap( canvasId );
		},
		Layer: {
			OSM: OSMLayer,
			Markers: MarkersLayer
		},
		Marker,
		LonLat,
		Bounds,
		Projection,
		instances
	};
}

/**
 * Install fresh google.maps/L/OpenLayers fakes as globals.
 *
 * @return {Function} teardown() - deletes the globals again.
 */
function install() {
	global.google = { maps: makeGoogleMaps() };
	global.L = makeLeaflet();
	global.OpenLayers = makeOpenLayers();

	return function teardown() {
		delete global.google;
		delete global.L;
		delete global.OpenLayers;
	};
}

module.exports = { install };
