/*
 * A star rating widget with the API of rateYo 2.3.2.
 *
 * This is a compatible re-implementation of rateYo (http://prrashi.github.io/rateyo/,
 * Copyright (c) 2014 Prashanth Pamidi, MIT license), which has not been maintained since 2018.
 * It creates the same markup (so that skins/jquery.rateyo.css and site CSS keep working), takes
 * the same options, offers the same methods and triggers the same events:
 *
 *   $( el ).rateYo( options )                  create (options also from data-rateyo-* attributes)
 *   $( el ).rateYo( 'rating' [, value] )       get or set the rating
 *   $( el ).rateYo( 'option', name [, value] ) get or set an option
 *   $( el ).rateYo( 'destroy' )                remove the widget
 *   events: rateyo.init, rateyo.change, rateyo.set  (data: { rating: number })
 *
 * tests/node-qunit/jquery.rateyo.test.js runs this file and the original side by side.
 * Known difference: the mobile browser detection of the original (a long list of device names)
 * is replaced by a short check for common mobile user agents.
 */
( function ( $ ) {
	'use strict';

	const BASICSTAR = '<?xml version="1.0" encoding="utf-8"?>' +
		'<svg version="1.1"' +
		'xmlns="http://www.w3.org/2000/svg"' +
		'viewBox="0 12.705 512 486.59"' +
		'x="0px" y="0px"' +
		'xml:space="preserve">' +
		'<polygon ' +
		'points="256.814,12.705 317.205,198.566' +
		' 512.631,198.566 354.529,313.435 ' +
		'414.918,499.295 256.814,384.427 ' +
		'98.713,499.295 159.102,313.435 ' +
		'1,198.566 196.426,198.566 "/>' +
		'</svg>';

	const DEFAULTS = {
		starWidth: '32px',
		normalFill: 'gray',
		ratedFill: '#f39c12',
		numStars: 5,
		maxValue: 5,
		precision: 1,
		rating: 0,
		fullStar: false,
		halfStar: false,
		readOnly: false,
		spacing: '0px',
		rtl: false,
		multiColor: null,
		onInit: null,
		onChange: null,
		onSet: null,
		starSvg: null
	};

	const MULTICOLOR_OPTIONS = {
		startColor: '#c0392b',
		endColor: '#f1c40f'
	};

	function isMobileBrowser() {
		return /android|iphone|ipad|ipod|mobile|silk|kindle|blackberry|opera mini/i
			.test( navigator.userAgent || navigator.vendor || window.opera || '' );
	}

	function isDefined( value ) {
		return typeof value !== 'undefined';
	}

	function checkBounds( value, minValue, maxValue ) {
		if ( !( value >= minValue && value <= maxValue ) ) {
			throw new Error( 'Invalid Rating, expected value between ' + minValue + ' and ' + maxValue );
		}
		return value;
	}

	const HEX_REGEX = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i;

	function hexToRGB( hex ) {
		const values = HEX_REGEX.exec( hex );
		if ( !values ) {
			return null;
		}
		return { r: parseInt( values[ 1 ], 16 ), g: parseInt( values[ 2 ], 16 ), b: parseInt( values[ 3 ], 16 ) };
	}

	function getChannelValue( startVal, endVal, percent ) {
		const value = Math.round( startVal + ( endVal - startVal ) * ( percent / 100 ) ).toString( 16 );
		return value.length === 1 ? '0' + value : value;
	}

	function getColor( startColor, endColor, percent ) {
		if ( !startColor || !endColor ) {
			return null;
		}
		percent = isDefined( percent ) ? percent : 0;
		const start = hexToRGB( startColor );
		const end = hexToRGB( endColor );
		return '#' + getChannelValue( start.r, end.r, percent ) +
			getChannelValue( start.g, end.g, percent ) +
			getChannelValue( start.b, end.b, percent );
	}

	function RateYo( $node, options ) {
		this.node = $node.get( 0 );

		const that = this;
		const minValue = 0;

		$node.empty().addClass( 'jq-ry-container' );

		const $groupWrapper = $( '<div/>' ).addClass( 'jq-ry-group-wrapper' ).appendTo( $node );
		const $normalGroup = $( '<div/>' ).addClass( 'jq-ry-normal-group' ).addClass( 'jq-ry-group' )
			.appendTo( $groupWrapper );
		const $ratedGroup = $( '<div/>' ).addClass( 'jq-ry-rated-group' ).addClass( 'jq-ry-group' )
			.appendTo( $groupWrapper );

		let step, starWidth, percentOfStar, spacing, percentOfSpacing, containerWidth;
		let currentRating = options.rating;
		let isInitialized = false;
		let ratedFill = options.ratedFill;

		function showRating( ratingVal ) {
			if ( !isDefined( ratingVal ) ) {
				ratingVal = options.rating;
			}
			currentRating = ratingVal;

			const numStarsToShow = ratingVal / step;
			let percent = numStarsToShow * percentOfStar;
			if ( numStarsToShow > 1 ) {
				percent += ( Math.ceil( numStarsToShow ) - 1 ) * percentOfSpacing;
			}

			setRatedFill( options.ratedFill );

			percent = options.rtl ? 100 - percent : percent;
			if ( percent < 0 ) {
				percent = 0;
			} else if ( percent > 100 ) {
				percent = 100;
			}
			$ratedGroup.css( 'width', percent + '%' );
		}

		function setContainerWidth() {
			containerWidth = starWidth * options.numStars + spacing * ( options.numStars - 1 );
			percentOfStar = ( starWidth / containerWidth ) * 100;
			percentOfSpacing = ( spacing / containerWidth ) * 100;
			$node.width( containerWidth );
			showRating();
		}

		function setStarWidth( newWidth ) {
			const starHeight = options.starWidth = newWidth;
			starWidth = window.parseFloat( options.starWidth.replace( 'px', '' ) );
			$normalGroup.find( 'svg' ).attr( { width: options.starWidth, height: starHeight } );
			$ratedGroup.find( 'svg' ).attr( { width: options.starWidth, height: starHeight } );
			setContainerWidth();
			return $node;
		}

		function setSpacing( newSpacing ) {
			options.spacing = newSpacing;
			spacing = parseFloat( options.spacing.replace( 'px', '' ) );
			$normalGroup.find( 'svg:not(:first-child)' ).css( { 'margin-left': newSpacing } );
			$ratedGroup.find( 'svg:not(:first-child)' ).css( { 'margin-left': newSpacing } );
			setContainerWidth();
			return $node;
		}

		function setNormalFill( newFill ) {
			options.normalFill = newFill;
			( options.rtl ? $ratedGroup : $normalGroup ).find( 'svg' ).attr( { fill: options.normalFill } );
			return $node;
		}

		function setRatedFill( newFill ) {
			if ( options.multiColor ) {
				const percentCovered = ( ( currentRating - minValue ) / options.maxValue ) * 100;
				const colorOpts = options.multiColor || {};
				newFill = getColor(
					colorOpts.startColor || MULTICOLOR_OPTIONS.startColor,
					colorOpts.endColor || MULTICOLOR_OPTIONS.endColor,
					percentCovered
				);
			} else {
				ratedFill = newFill;
			}
			options.ratedFill = newFill;
			( options.rtl ? $normalGroup : $ratedGroup ).find( 'svg' ).attr( { fill: options.ratedFill } );
			return $node;
		}

		function setRtl( newValue ) {
			options.rtl = !!newValue;
			setNormalFill( options.normalFill );
			showRating();
		}

		function setMultiColor( colorOptions ) {
			options.multiColor = colorOptions;
			setRatedFill( colorOptions ? colorOptions : ratedFill );
		}

		function setNumStars( newValue ) {
			options.numStars = newValue;
			step = options.maxValue / options.numStars;
			$normalGroup.empty();
			$ratedGroup.empty();
			for ( let i = 0; i < options.numStars; i++ ) {
				$normalGroup.append( $( options.starSvg || BASICSTAR ) );
				$ratedGroup.append( $( options.starSvg || BASICSTAR ) );
			}
			setStarWidth( options.starWidth );
			setNormalFill( options.normalFill );
			setSpacing( options.spacing );
			showRating();
			return $node;
		}

		function setMaxValue( newValue ) {
			options.maxValue = newValue;
			step = options.maxValue / options.numStars;
			if ( options.rating > newValue ) {
				setRating( newValue );
			}
			showRating();
			return $node;
		}

		function setPrecision( newValue ) {
			options.precision = newValue;
			setRating( options.rating );
			return $node;
		}

		function setHalfStar( newValue ) {
			options.halfStar = newValue;
			return $node;
		}

		function setFullStar( newValue ) {
			options.fullStar = newValue;
			return $node;
		}

		/**
		 * Round a value to the next whole (fullStar) or half (halfStar) star
		 *
		 * @param {number} value
		 * @return {number}
		 */
		function round( value ) {
			const remainder = value % step;
			const halfStep = step / 2;

			if ( !options.fullStar && !options.halfStar ) {
				return value;
			}
			if ( options.fullStar || ( options.halfStar && remainder > halfStep ) ) {
				value += step - remainder;
			} else {
				value = value - remainder;
				if ( remainder > 0 ) {
					value += halfStep;
				}
			}
			return value;
		}

		function calculateRating( e ) {
			const nodeStartX = $normalGroup.offset().left;
			const nodeEndX = nodeStartX + $normalGroup.width();
			const maxValue = options.maxValue;
			const pageX = e.pageX;
			let calculatedRating = 0;

			if ( pageX < nodeStartX ) {
				calculatedRating = minValue;
			} else if ( pageX > nodeEndX ) {
				calculatedRating = maxValue;
			} else {
				let calcPrcnt = ( pageX - nodeStartX ) / ( nodeEndX - nodeStartX );

				if ( spacing > 0 ) {
					calcPrcnt *= 100;
					let remPrcnt = calcPrcnt;
					while ( remPrcnt > 0 ) {
						if ( remPrcnt > percentOfStar ) {
							calculatedRating += step;
							remPrcnt -= ( percentOfStar + percentOfSpacing );
						} else {
							calculatedRating += remPrcnt / percentOfStar * step;
							remPrcnt = 0;
						}
					}
				} else {
					calculatedRating = calcPrcnt * options.maxValue;
				}
				calculatedRating = round( calculatedRating );
			}

			if ( options.rtl ) {
				calculatedRating = maxValue - calculatedRating;
			}
			return parseFloat( calculatedRating );
		}

		function onMouseEnter( e ) {
			const rating = parseFloat( calculateRating( e ).toFixed( options.precision ) );
			showRating( rating );
			$node.trigger( 'rateyo.change', { rating: rating } );
		}

		function onMouseLeave() {
			if ( isMobileBrowser() ) {
				return;
			}
			showRating();
			$node.trigger( 'rateyo.change', { rating: options.rating } );
		}

		function onMouseClick( e ) {
			that.rating( parseFloat( calculateRating( e ).toFixed( options.precision ) ) );
		}

		function callback( name ) {
			return function ( e, data ) {
				if ( options[ name ] && typeof options[ name ] === 'function' ) {
					options[ name ].apply( this, [ data.rating, that ] );
				}
			};
		}

		const onInit = callback( 'onInit' );
		const onChange = callback( 'onChange' );
		const onSet = callback( 'onSet' );

		function bindEvents() {
			$node.on( 'mousemove', onMouseEnter )
				.on( 'mouseenter', onMouseEnter )
				.on( 'mouseleave', onMouseLeave )
				.on( 'click', onMouseClick )
				.on( 'rateyo.init', onInit )
				.on( 'rateyo.change', onChange )
				.on( 'rateyo.set', onSet );
		}

		function unbindEvents() {
			$node.off( 'mousemove', onMouseEnter )
				.off( 'mouseenter', onMouseEnter )
				.off( 'mouseleave', onMouseLeave )
				.off( 'click', onMouseClick )
				.off( 'rateyo.init', onInit )
				.off( 'rateyo.change', onChange )
				.off( 'rateyo.set', onSet );
		}

		function setReadOnly( newValue ) {
			options.readOnly = newValue;
			$node.attr( 'readonly', true );
			unbindEvents();
			if ( !newValue ) {
				$node.removeAttr( 'readonly' );
				bindEvents();
			}
			return $node;
		}

		function setRating( newValue ) {
			let rating = newValue;
			let maxValue = options.maxValue;

			if ( typeof rating === 'string' ) {
				if ( rating[ rating.length - 1 ] === '%' ) {
					rating = rating.slice( 0, -1 );
					maxValue = 100;
					setMaxValue( maxValue );
				}
				rating = parseFloat( rating );
			}

			checkBounds( rating, minValue, maxValue );
			rating = parseFloat( rating.toFixed( options.precision ) );

			options.rating = rating;
			showRating();

			if ( isInitialized ) {
				$node.trigger( 'rateyo.set', { rating: rating } );
			}
			return $node;
		}

		function setCallback( name ) {
			return function ( method ) {
				options[ name ] = method;
				return $node;
			};
		}

		this.rating = function ( newValue ) {
			if ( !isDefined( newValue ) ) {
				return options.rating;
			}
			setRating( newValue );
			return $node;
		};

		this.destroy = function () {
			if ( !options.readOnly ) {
				unbindEvents();
			}
			RateYo.prototype.collection = deleteInstance( $node.get( 0 ), this.collection );
			$node.removeClass( 'jq-ry-container' ).children().remove();
			return $node;
		};

		this.method = function ( methodName ) {
			if ( !methodName ) {
				throw new Error( 'Method name not specified!' );
			}
			if ( !isDefined( this[ methodName ] ) ) {
				throw new Error( 'Method ' + methodName + " doesn't exist!" );
			}
			return this[ methodName ].apply( this, Array.prototype.slice.call( arguments, 1 ) );
		};

		const optionSetters = {
			starWidth: setStarWidth,
			numStars: setNumStars,
			normalFill: setNormalFill,
			ratedFill: setRatedFill,
			multiColor: setMultiColor,
			maxValue: setMaxValue,
			precision: setPrecision,
			rating: setRating,
			halfStar: setHalfStar,
			fullStar: setFullStar,
			readOnly: setReadOnly,
			spacing: setSpacing,
			rtl: setRtl,
			onInit: setCallback( 'onInit' ),
			onSet: setCallback( 'onSet' ),
			onChange: setCallback( 'onChange' )
		};

		this.option = function ( optionName, param ) {
			if ( !isDefined( optionName ) ) {
				return options;
			}
			if ( !Object.prototype.hasOwnProperty.call( optionSetters, optionName ) ) {
				throw new Error( 'No such option as ' + optionName );
			}
			return isDefined( param ) ? optionSetters[ optionName ]( param ) : options[ optionName ];
		};

		setNumStars( options.numStars );
		setReadOnly( options.readOnly );
		if ( options.rtl ) {
			setRtl( options.rtl );
		}

		this.collection.push( this );
		this.rating( options.rating, true );

		isInitialized = true;
		$node.trigger( 'rateyo.init', { rating: options.rating } );
	}

	RateYo.prototype.collection = [];

	function getInstance( node, collection ) {
		return collection.find( ( candidate ) => candidate.node === node );
	}

	function deleteInstance( node, collection ) {
		const index = collection.findIndex( ( candidate ) => candidate.node === node );
		return index === -1 ? collection : collection.slice( 0, index ).concat( collection.slice( index + 1 ) );
	}

	function rateYo( options ) {
		const instances = RateYo.prototype.collection;
		const $nodes = $( this );

		if ( $nodes.length === 0 ) {
			return $nodes;
		}

		const args = Array.prototype.slice.call( arguments );

		if ( args.length === 0 ) {
			options = args[ 0 ] = {};
		} else if ( args.length === 1 && typeof args[ 0 ] === 'object' ) {
			options = args[ 0 ];
		} else if ( args.length >= 1 && typeof args[ 0 ] === 'string' ) {
			const methodName = args[ 0 ];
			const params = args.slice( 1 );
			const result = [];

			$nodes.get().forEach( ( node ) => {
				const existingInstance = getInstance( node, instances );
				if ( !existingInstance ) {
					throw new Error( 'Trying to set options before even initialization' );
				}
				const method = existingInstance[ methodName ];
				if ( !method ) {
					throw new Error( 'Method ' + methodName + ' does not exist!' );
				}
				result.push( method.apply( existingInstance, params ) );
			} );

			return result.length === 1 ? result[ 0 ] : result;
		} else {
			throw new Error( 'Invalid Arguments' );
		}

		// $.extend (unlike Object.assign) skips undefined values, as the original does
		// eslint-disable-next-line no-jquery/no-extend
		options = $.extend( {}, DEFAULTS, options );

		$nodes.get().forEach( ( node ) => {
			if ( getInstance( node, instances ) ) {
				return;
			}

			const dataAttrs = {};
			// eslint-disable-next-line no-jquery/no-extend
			const optionsCopy = $.extend( {}, options );

			const data = $( node ).data();
			Object.keys( data ).filter( ( key ) => key.indexOf( 'rateyo' ) === 0 ).forEach( ( key ) => {
				let optionName = key.replace( /^rateyo/, '' );
				optionName = optionName[ 0 ].toLowerCase() + optionName.slice( 1 );
				dataAttrs[ optionName ] = data[ key ];
				delete optionsCopy[ optionName ];
			} );

			// The constructor registers the instance
			// eslint-disable-next-line no-new, no-jquery/no-extend
			new RateYo( $( node ), $.extend( {}, dataAttrs, optionsCopy ) );
		} );

		return $nodes;
	}

	window.RateYo = RateYo;
	$.fn.rateYo = rateYo;

}( window.jQuery ) );
