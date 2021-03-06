holidays.map = {
	map: null,
	markers: [],
	locationMarker: null,
	zoomTimer: null,
	messageTimer: null,
	lastRequest: null,
	bounds: [],
	prices: [],
	allowHistory: true,

	init: function() {
		var init = function() {
			// only load if Maps is initialized (app may have no internet connection)
			if ( typeof google != 'undefined' && typeof google.maps != 'undefined' ) {
				holidays.map.draw();
				holidays.map.bind();
				holidays.map.locate();
				holidays.map.autocomplete();

				clearInterval( initInterval );
			}
		};
		var initInterval = setInterval( init, 250 );
	},

	/**
	 * Add Google maps to the page.
	 */
	draw: function() {
		$( '#loadingMap' ).show();

		var options = {
			center: new google.maps.LatLng( 40, 0 ),
			zoom: 3,
			mapTypeId: google.maps.MapTypeId.ROADMAP,
			styles: [{
				featureType: 'all',
				stylers: [{ lightness: 50 }]
			}]
		};
		holidays.map.map = new google.maps.Map( $( '#map' ).get( 0 ), options );
	},

	/**
	 * If bounds have changed, we'll want to re-fetch & -draw all markers.
	 * Let's not fire the request right away though, wait a little longer
	 * to make sure that users have actually terminated zooming/panning.
	 */
	bind: function() {
		google.maps.event.addListener( holidays.map.map, 'idle', function( e ) {
			$( '#loadingMap' ).hide();

			if ( holidays.map.zoomTimer ) {
				clearTimeout( holidays.map.zoomTimer );
			}

			holidays.map.zoomTimer = setTimeout( holidays.map.reload, 250 );
		});
	},

	/**
	 * After zooming or moving the map around (= changing the viewport), we may need to
	 * refresh the markers on the map.
	 */
	reload: function() {
		// if map not yet loaded, abort
		if ( holidays.map.map === null ) {
			return;
		}

		// if there's an ongoing ajax request, abort it
		if ( holidays.map.lastRequest ) {
			holidays.map.lastRequest.abort();
		}

		var center = holidays.map.map.getCenter();
		var zoom = holidays.map.map.getZoom();
		var bounds = holidays.map.map.getBounds();

		/*
		 * We don't always want this to be added to history, only when user-initiated.
		 * When "the code" changes the zoom/center/bounds/..., the event leading up to
		 * this code is also being executed, but in some cases that one will have added
		 * its dedicated history entry already.
		 */
		if ( holidays.map.allowHistory ) {
			// save coordinates in history, allowing people to use browser next/previous to return to certain map views.
			var state = [center.lat(), center.lng(), zoom];
			holidays.history.push( state, 'reload', '/', holidays.map.setView );
		}
		holidays.map.allowHistory = true;

		// check if the request crosses lat/lng bounds & round the bounds
		var spanBoundsLat = bounds.getNorthEast().lat() < bounds.getSouthWest().lat();
		var spanBoundsLng = bounds.getNorthEast().lng() < bounds.getSouthWest().lng();

		bounds = holidays.map.roundBounds( bounds, spanBoundsLat, spanBoundsLng );
		var prices = $( '.noUiSlider' ).val();

		holidays.map.fetchData( bounds, prices, spanBoundsLat, spanBoundsLng );
	},

	/**
	 * Request new locations/clusters for the specified parameters
	 *
	 * @param array bounds
	 * @param array prices
	 * @param bool spanBoundsLat
	 * @param bool spanBoundsLng
	 */
	fetchData: function( bounds, prices, spanBoundsLat, spanBoundsLng ) {
		// check if enough has changed to fire a new ajax request (e.g. not if viewport still fits in same previous bounds)
		if ( !holidays.map.isOutdated( bounds, prices, spanBoundsLat, spanBoundsLng ) ) {
			return;
		}

		// don't display right away; if everything's done really fast, user won't even have noticed the new request
		var loadingMessage = $( '#loadingMarkers' );
		holidays.map.messageTimer = setTimeout( function() { loadingMessage.show(); }, 350 );

		holidays.map.lastRequest = $.ajax( {
			url: holidays.host + 'ajax/markers.php',
			data:
			{
				prices: {
					min: prices[0],
					max: prices[1]
				},
				bounds: {
					neLat: bounds.neLat,
					swLat: bounds.swLat,
					neLng: bounds.neLng,
					swLng: bounds.swLng
				},
				spanBounds: {
					lat: spanBoundsLat ? 1 : 0,
					lng: spanBoundsLng ? 1 : 0
				},
				minPts: holidays.map.map.getZoom() > 13 ? 999999 : 15, // zoomed in much = don't cluster
				nbrClusters: Math.round( $( '#map' ).width() * $( '#map' ).height() / 15000 ), // smaller screen = less clusters
				app: holidays.app ? 1 : 0,
				host: holidays.host,
				mobile: holidays.mobile ? 1 : 0,
				language: holidays.localize.language,
				currency: holidays.localize.currency
			},
			type: 'GET',
			dataType: 'json',
			timeout: 30000,
			success: function( json ) {
				holidays.map.drawMarkers( json );

				// things changed; change new data - will be used to check if we need to fetch new data for next change
				holidays.map.bounds = bounds;
				holidays.map.prices = prices;

				clearTimeout( holidays.map.messageTimer );
				loadingMessage.hide();
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				// don't display error message if aborted
				if ( textStatus == 'abort' ) {
					return;
				}

				holidays.infowindow.error();
			}
		} );
	},

	/**
	 * Draw all markers.
	 *
	 * @param object json
	 */
	drawMarkers: function( json ) {
		// clear existing markers
		for ( var i in holidays.map.markers ) {
			holidays.map.markers[i].setMap( null );
		}
		holidays.map.markers = [];

		// draw regular locations' markers
		for ( var i = 0; i < json.locations.length; i++ ) {
			var coordinate = new google.maps.LatLng( json.locations[i].lat, json.locations[i].lng );

			var marker = holidays.map.marker.location( coordinate, json.locations[i].feed_id, json.locations[i].product_id, json.locations[i].text );
			holidays.map.markers.push( marker );
		}

		// draw clusters' markers
		for ( var i = 0; i < json.clusters.length; i++ ) {
			var coordinate = new google.maps.LatLng( json.clusters[i].center.lat, json.clusters[i].center.lng );
			var count = json.clusters[i]['total'];

			var marker = holidays.map.marker.cluster( coordinate, count );
			holidays.map.markers.push( marker );
		}

		// save amount of clusters - will be used to check if we need to fetch new data for next change
		holidays.map.numClusters = json.clusters.length;
	},

	/*
	 * PNG images with text overlay won't work here, so I traced the images
	 * to SVG (where I can add text) and will just add the border colors here
	 */
	marker: {
		/**
		 * Draw a marker on the map for a single location.
		 *
		 * @param google.maps.LatLng coordinate
		 * @param int id
		 * @param string text
		 * @returns google.maps.Marker
		 */
		location: function( coordinate, feedId, productId, text ) {
			// prices color range
			var price = parseInt( text.replace( /[^0-9]/g, '' ) ); // hacky...
			var priceRange = holidays.localize.priceRange[holidays.localize.currency];
			var colors = ['#a9be42', '#fe7921', '#ea3755'];
			var range = priceRange[1] - priceRange[0];
			var index = Math.floor( ( price - priceRange[0] ) / ( range / colors.length ) );
			index = Math.max( 0, Math.min( colors.length, index ) );
			var color = colors[index];

			var svg =
				'<svg width="46" height="54" xmlns="http://www.w3.org/2000/svg">' +
					// balloon shape
					'<path stroke="'+ color +'" stroke-width="2" fill="#fcfcfc" style="stroke-opacity: 1; opacity: 0.9" d=" M 7.81 8.31 C 11.74 4.27 17.35 1.87 23 2 C 28.65 1.88 34.27 4.27 38.19 8.31 C 42.01 12.15 44.21 17.59 44 23.02 C 44.04 28.86 41.15 34.49 36.78 38.28 C 32.15 42.35 27.72 46.65 23 50.62 C 18.28 46.66 13.86 42.34 9.23 38.28 C 4.85 34.49 1.96 28.85 2 23.01 C 1.8 17.59 3.99 12.15 7.81 8.31 Z" />' +
					// bottom balloon shadow
					'<path fill="#7f7f7f" d=" M 18.18 52.59 C 19 52.19 19.83 51.81 20.64 51.41 C 21.36 51.99 22.05 52.79 23.01 52.91 C 23.96 52.79 24.64 51.99 25.36 51.42 C 26.2 51.82 27.05 52.21 27.9 52.6 C 27.79 53.07 27.67 53.53 27.55 54 L 18.33 54 C 18.28 53.53 18.24 53.06 18.18 52.59 Z" />' +
					// text
					'<text x="23" y="28" font-size="13" font-family="Arial,sans-serif" font-weight="bold" text-anchor="middle" fill="#333" textContent="'+ text +'">'+ text +'</text>' +
				'</svg>';

			var marker = new google.maps.Marker( {
				map: holidays.map.map,
				position: coordinate,
				icon: {
					url: 'data:image/svg+xml;base64,' + Base64.encode( svg ),
					anchor: new google.maps.Point( 28, 54 )
				},
				zIndex: 1000 - price, // surface cheaper hotels
				flat: true,
				title: text,
				feedId: feedId,
				productId: productId
			} );

			// add click listener
			google.maps.event.addListener( marker, 'click', function( e ) {
				var loadingMessage = $( '#loadingDetails' ).show();
				$( '#infowindow' ).on( 'DOMSubtreeModified', function() {
					loadingMessage.hide();
				} );

				var params = {
					feedId: this.feedId,
					productId: this.productId,
					app: holidays.app ? 1 : 0,
					host: holidays.host,
					mobile: holidays.mobile ? 1 : 0,
					language: holidays.localize.language,
					currency: holidays.localize.currency
				};

				holidays.infowindow.open( 'ajax/details.php?' + $.param( params ) );
			} );

			return marker;
		},

		/**
		 * Draw a marker on the map for a cluster.
		 *
		 * @param google.maps.LatLng coordinate
		 * @param int count
		 * @returns google.maps.Marker
		 */
		cluster: function( coordinate, count ) {
			// outerradius can range from 25 to 100; the more entries, the larger the radius
			var outerradius = 25;
			var index = 1;
			while ( index < count && outerradius < 100 ) {
				outerradius += 4;
				index *= 2;
			}
			outerradius = Math.max( 25, Math.min( 100, outerradius ) );

			var svg =
				'<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">' +
					// transparent outer circle
					'<circle cx="100" cy="100" r="'+ outerradius +'" fill="#0097f5" style="opacity: 0.2"  />' +
					// inner circle
					'<circle cx="100" cy="100" r="23" stroke="#0097f5" fill="#fcfcfc" stroke-width="5" style="opacity: 0.9" />' +
					// text
					'<text x="100" y="105" font-size="11" font-family="Arial,sans-serif" font-weight="bold" text-anchor="middle" fill="#007fce" textContent="'+ count +'">'+ count +'</text>' +
				'</svg>';

			var marker = new google.maps.Marker( {
				map: holidays.map.map,
				position: coordinate,
				icon: {
					url: 'data:image/svg+xml;base64,' + Base64.encode( svg ),
					anchor: new google.maps.Point( 100, 100 )
				},
				zIndex: 1,
				flat: true
			} );

			// add click listener
			google.maps.event.addListener( marker, 'click', function( e ) {
				holidays.map.setView( e.latLng.lat(), e.latLng.lng(), holidays.map.map.getZoom() + 1 );
			} );

			return marker;
		},

		/**
		 * Draw a marker on the map for a reference point.
		 *
		 * @param google.maps.LatLng coordinate
		 * @param string text
		 * @returns google.maps.Marker
		 */
		reference: function( coordinate, text ) {
			var svg =
				'<svg width="46" height="54" xmlns="http://www.w3.org/2000/svg">' +
					// balloon shape
					'<path fill="#0097f5" d=" M 7.81 8.31 C 11.74 4.27 17.35 1.87 23 2 C 28.65 1.88 34.27 4.27 38.19 8.31 C 42.01 12.15 44.21 17.59 44 23.02 C 44.04 28.86 41.15 34.49 36.78 38.28 C 32.15 42.35 27.72 46.65 23 50.62 C 18.28 46.66 13.86 42.34 9.23 38.28 C 4.85 34.49 1.96 28.85 2 23.01 C 1.8 17.59 3.99 12.15 7.81 8.31 Z" />' +
					// circle inside balloon
					'<circle cx="23" cy="23" r="13" fill="#0078c2" />' +
					// bottom balloon shadow
					'<path fill="#7f7f7f" d=" M 18.18 52.59 C 19 52.19 19.83 51.81 20.64 51.41 C 21.36 51.99 22.05 52.79 23.01 52.91 C 23.96 52.79 24.64 51.99 25.36 51.42 C 26.2 51.82 27.05 52.21 27.9 52.6 C 27.79 53.07 27.67 53.53 27.55 54 L 18.33 54 C 18.28 53.53 18.24 53.06 18.18 52.59 Z" />' +
				'</svg>'

			var marker = new google.maps.Marker( {
				map: holidays.map.map,
				position: coordinate,
				icon: {
					url: 'data:image/svg+xml;base64,' + Base64.encode( svg ),
					anchor: new google.maps.Point( 28, 54 )
				},
				zIndex: 3,
				clickable: false,
				flat: true,
				text: '<strong>' + text + '</strong>'
			} );

			return marker;
		}
	},

	/*
	 * When clicking on the button, the user's current location will be requested
	 * and a marker will be added.
	 */
	locate: function() {
		// check if functionality is supported
		if ( !navigator.geolocation ) {
			return;
		}

		/**
		 * Add a marker to the user's current location.
		 *
		 * @param nsIDOMGeoPosition position
		 */
		var setMarker = function( position ) {
			var coordinates = new google.maps.LatLng( position.coords.latitude, position.coords.longitude );

			// remove existing marker
			if ( holidays.map.referenceMarker ) {
				holidays.map.referenceMarker.setMap( null );
			}
			holidays.map.referenceMarker = null;

			// draw new marker
			holidays.map.referenceMarker = holidays.map.marker.reference( coordinates, '' );

			// save action in history, allowing people to use browser next/previous to return to user's current position
			holidays.history.push( [], 'locate', '/', holidays.map.locate );
			holidays.map.allowHistory = false;

			// zoom to specified location
			holidays.map.setView( coordinates.lat(), coordinates.lng(), 14 );
		}

		$( '#searchLocate' )
			.show() // is not displayed by default since we don't know if it's supported by the browser
			.on( 'click', function( e ) {
				e.preventDefault();
				navigator.geolocation.getCurrentPosition( setMarker );
			} );
	},

	/**
	 * Bind Google's autocomplete the the search for, allowing users to search for
	 * locations near a certain place.
	 */
	autocomplete: function() {
		var searchField = $( '#searchField' );
		var searchbox = new google.maps.places.Autocomplete( searchField.get( 0 ) );

		/**
		 * Add a marker for the selected location.
		 *
		 * @param array places
		 */
		var setMarker = function( places ) {
			if ( places.length == 0 || typeof places[0].geometry == 'undefined' ) {
				return;
			}
			var place = places[0];

			// remove existing marker
			if ( holidays.map.referenceMarker ) {
				holidays.map.referenceMarker.setMap( null );
			}
			holidays.map.referenceMarker = null;

			// draw new marker
			holidays.map.referenceMarker = holidays.map.marker.reference( place.geometry.location, place.name );

			// save action in history, allowing people to use browser next/previous to return to requested location
			var slug = encodeURIComponent( place.name.toLowerCase().replace( / /g, '-' ) );
			holidays.history.push( [place.name], 'autocomplete', '/' + slug, holidays.map.findLocation );
			holidays.map.allowHistory = false;

			// zoom to specified location
			holidays.map.setView( place.geometry.location.lat(), place.geometry.location.lng(), 14 );
		};

		// selecting a specific place from the autocomplete dropdown
		google.maps.event.addListener( searchbox, 'place_changed', function() {
			setMarker( [searchbox.getPlace()] );
		} );

		// form submission (either automated in code or pressing enter) = launch search and assume first result
		searchField.parents( 'form' ).submit( function( e ) {
			e.preventDefault();

			var service = new google.maps.places.PlacesService( holidays.map.map );
			service.textSearch( { query: searchField.val() }, setMarker );
		});

		if ( !holidays.app ) {
			/*
			 * If URI has a non-existing slug, start a search for that location.
			 * e.g. http://www.last-minute-vakanties.be/new-york will zoom in and
			 * add a marker on New York.
			 */
			var location = decodeURIComponent( document.location.pathname.replace( /(^\/|\/$)/, '' ).replace( /-/g, ' ' ) );
			if ( location ) {
				holidays.map.findLocation( location );
			}
		}
	},

	/*
	 * Will search for and add a marker to the given location.
	 *
	 * @param string location
	 */
	findLocation: function( location ) {
		var searchField = $( '#searchField' );
		searchField.val( location );

		searchField.parents( 'form' ).submit();
	},

	/**
	 * Display a certain position on the map.
	 *
	 * @param float centerLat
	 * @param float centerLng
	 * @param int zoom
	 */
	setView: function( centerLat, centerLng, zoom ) {
		var coordinate = new google.maps.LatLng( centerLat, centerLng );
		holidays.map.map.setCenter( coordinate );
		holidays.map.map.setZoom( zoom );
	},

	/**
	 * Before firing a new ajax request to fetch new markers for the current viewport,
	 * this function will be called. This will check if it is actually necessary to
	 * fetch new data. If for example the viewport nearly hasn't changed (and is still
	 * withing the bounds that were fetch on the previous request), it makes no sense
	 * to fire a new request, since the data will still be the same.
	 *
	 * @param array bounds
	 * @param array prices
	 * @param bool spanBoundsLat
	 * @param bool spanBoundsLng
	 * @return bool
	 */
	isOutdated: function( bounds, prices, spanBoundsLat, spanBoundsLng ) {
		var redraw = true;

		// don't redraw if bounds have not changed
		redraw &= typeof( JSON ) == 'undefined' || JSON.stringify( holidays.map.bounds ) != JSON.stringify( bounds );

		// don't redraw if we're zooming into an area where we no longer had clustering (= all locations are drawn already)
		redraw &=
			holidays.map.numClusters != 0 ||
			// most common
			( !spanBoundsLat && bounds.neLat > holidays.map.bounds.neLat ) ||
			( !spanBoundsLng && bounds.neLng > holidays.map.bounds.neLng ) ||
			( !spanBoundsLat && bounds.swLat < holidays.map.bounds.swLat ) ||
			( !spanBoundsLng && bounds.swLat < holidays.map.bounds.swLat ) ||
			// north-south or east-west overlap, without center displaying
			( spanBoundsLat && bounds.neLat < holidays.map.bounds.neLat ) ||
			( spanBoundsLng && bounds.neLng < holidays.map.bounds.neLng ) ||
			( spanBoundsLat && bounds.swLat > holidays.map.bounds.swLat ) ||
			( spanBoundsLng && bounds.swLat > holidays.map.bounds.swLat );

		// don't redraw if price range hasn't changed
		redraw |= typeof( JSON ) == 'undefined' || JSON.stringify( holidays.map.prices ) != JSON.stringify( prices );

		// check language & currency
		redraw |= $.cookie( 'language' ) != holidays.localize.language;
		redraw |= $.cookie( 'currency' ) != holidays.localize.currency;

		return redraw;
	},

	/**
	 * Extend bounds a little bit to a rounder number, that way similar
	 * requests can use the same cache. Round to a multiple of X only when
	 * caching (that way, there are less different caches and odds that
	 * that region is cached already are larger).
	 *
	 * Rounding should be different depending on how much of the map is displayed.
	 * If most of the map is showing, rounding can be more rough. This is important
	 * because at high zoom levels (= zoomed in), we don't want the clusters to be
	 * calculated on really rough bounds: if e.g. we only see lat 54.657 to 54.723 in
	 * our viewport, we don't want the clusters to be calculated on lat 50 to 60.
	 * Always round to the nearest power of 2.
	 *
	 * @param google.maps.LatLngBounds bounds
	 * @param bool spanBoundsLat
	 * @param bool spanBoundsLng
	 * @return object
	 */
	roundBounds: function( bounds, spanBoundsLat, spanBoundsLng ) {
		var totalLat = bounds.getNorthEast().lat() - bounds.getSouthWest().lat();
		var totalLng = bounds.getNorthEast().lng() - bounds.getSouthWest().lng();

		totalLat += spanBoundsLat ? 180 : 0;
		totalLng += spanBoundsLng ? 360 : 0;

		var multiplierLat = Math.pow( 2, Math.ceil( Math.log( Math.abs( totalLat / 2 ) ) / Math.log( 2 ) ) );
		var multiplierLng = Math.pow( 2, Math.ceil( Math.log( Math.abs( totalLng / 2 ) ) / Math.log( 2 ) ) );

		var round = {
			neLat: { coordinate: bounds.getNorthEast().lat(), multiplier: multiplierLat, func: Math.ceil, bounds: [-90, 90] },
			swLat: { coordinate: bounds.getSouthWest().lat(), multiplier: multiplierLat, func: Math.floor, bounds: [-90, 90] },
			neLng: { coordinate: bounds.getNorthEast().lng(), multiplier: multiplierLng, func: Math.ceil, bounds: [-180, 180] },
			swLng: { coordinate: bounds.getSouthWest().lng(), multiplier: multiplierLng, func: Math.floor, bounds: [-180, 180] }
		};

		for ( var i in round ) {
			round[i] = Math.max( round[i].bounds[0], Math.min( round[i].bounds[1], round[i].func( round[i].coordinate / round[i].multiplier ) * round[i].multiplier ) );
		}

		return round;
	}
};
