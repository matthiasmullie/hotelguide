var holidays =
{
	map: null,
	markers: [],
	locationMarker: null,
	mapStyle: [
	{
		featureType: 'all',
		stylers: [
			{ lightness: 50 }
		]
	}],
	minPrice: 50,
	maxPrice: 300,
	zoomTimer: null,
	messageTimer: null,
	bounds: [],

	init: function()
	{
		holidays.drawMap();
		holidays.autocomplete();
		holidays.priceRange();
		holidays.bindInfoWindow();

		// input field class
		$('#search input.inputText').focus(function()
		{
			$('#search').addClass('inputHolderActive');
		});
		$('#search input.inputText').blur(function()
		{
			$('#search').removeClass('inputHolderActive');
		});
	},

	drawMap: function()
	{
		$('#loadingMap').show();

		var myOptions =
		{
			center: new google.maps.LatLng(40, 0),
			zoom: 3,
			mapTypeId: google.maps.MapTypeId.ROADMAP,
			styles: holidays.mapStyle
		};
		holidays.map = new google.maps.Map($('#map').get(0), myOptions);
		google.maps.event.addListener(holidays.map, 'bounds_changed', function()
		{
			if(holidays.zoomTimer) clearTimeout(holidays.zoomTimer);
			holidays.zoomTimer = setTimeout(holidays.reload, 250);
		});

		$('#loadingMap').hide();
	},

	reload: function()
	{
		// close any open windows (sometimes, on touch devices, they're touched accidentally during pinch-zoom)
		holidays.infowindowClose();

		// don't fire new request while previous one is still running
		var loadingMarkers = $('#loadingMarkers');
		if(loadingMarkers.is(':visible')) return;

		// get viewport
		var bounds = holidays.map.getBounds();

		// check if the request crosses lat/lng bounds before rounding the numbers
		var crossBoundsLat = bounds.getNorthEast().lat() < bounds.getSouthWest().lat();
		var crossBoundsLng = bounds.getNorthEast().lng() < bounds.getSouthWest().lng();

		bounds = holidays.roundBounds(bounds);

		var redraw = true;

		// don't redraw if bounds have not changed
		redraw &= typeof(JSON) == 'undefined' || JSON.stringify(holidays.bounds) != JSON.stringify(bounds);

		// don't redraw if we're zooming into an area where we no longer had clustering (= all locations are drawn already)
		redraw &=
			holidays.numClusters != 0 ||
			// most common
			( !crossBoundsLat && bounds.neLat > holidays.bounds.neLat ) ||
			( !crossBoundsLng && bounds.neLng > holidays.bounds.neLng ) ||
			( !crossBoundsLat && bounds.swLat < holidays.bounds.swLat ) ||
			( !crossBoundsLng && bounds.swLat < holidays.bounds.swLat ) ||
			// north-south or east-west overlap, without center displaying
			( crossBoundsLat && bounds.neLat < holidays.bounds.neLat ) ||
			( crossBoundsLng && bounds.neLng < holidays.bounds.neLng ) ||
			( crossBoundsLat && bounds.swLat > holidays.bounds.swLat ) ||
			( crossBoundsLng && bounds.swLat > holidays.bounds.swLat );

		if (!redraw) return;

		// things changed; change new bounds!
		holidays.bounds = bounds;

		// don't display right away; if everything's done really fast, user won't even have noticed the new request
		holidays.messageTimer = setTimeout("$('#loadingMarkers').show()", 350);

		$.ajax(
		{
			url: 'ajax/markers.php',
			data:
			{
				min: holidays.minPrice,
				max: holidays.maxPrice,
				bounds:
				{
					neLat: bounds.neLat,
					swLat: bounds.swLat,
					neLng: bounds.neLng,
					swLng: bounds.swLng
				},
				crossBounds:
				{
					lat: crossBoundsLat ? 1 : 0,
					lng: crossBoundsLng ? 1 : 0
				},
				minPts: holidays.map.getZoom() > 13 ? 99999999 : 10, // zoomed in much = don't cluster
				nbrClusters: Math.round(($('#map').width() + $('#map').height()) / 25) // smaller screen = less clusters
			},
			type: 'GET',
			dataType: 'json',
			success: function(json) {
				holidays.drawMarkers(json);

				clearTimeout(holidays.messageTimer);
				loadingMarkers.hide();
			}
		});
	},

	drawMarkers: function(json)
	{
		// clear existing markers
		for(var i in holidays.markers) holidays.markers[i].setMap(null);
		holidays.markers = [];

		for(var i = 0; i < json.locations.length; i++)
		{
			var marker = holidays.drawMarker(
				new google.maps.LatLng(json.locations[i].lat, json.locations[i].lng),
				json.locations[i].id,
				json.locations[i].price
			);
			holidays.markers.push(marker);
		}

		holidays.numClusters = 0;
		for(var i = 0; i < json.clusters.length; i++)
		{
			var coordinate = new google.maps.LatLng(json.clusters[i].center.lat, json.clusters[i].center.lng); // weighed center
			var count = json.clusters[i]['total'];

			var marker = holidays.drawCluster(coordinate, count);
			holidays.markers.push(marker);

			holidays.numClusters++;
		}
	},

	drawMarker: function(coordinate, id, price)
	{
		/*
		 * PNG images with text overlay won't work here, so I traced the images
		 * to SVG (where I can add text) and will just add the border colors here
		 */
		var colors = {
			0: '#a9be42', // 'images/pin_green.png', // €0 - 99
			1: '#fe7921', // 'images/pin_orange.png', // €100 - 199
			'+': '#ea3755' // 'images/pin_red.png', // €200 - 299
		}
		var color = colors[Math.floor(price / 100)];
		if(typeof color == 'undefined') color = colors['+'];

		var svg =
			'<svg width="46" height="54" xmlns="http://www.w3.org/2000/svg">' +
				// balloon shape
				'<path stroke="'+ color +'" stroke-width="2" fill="#fcfcfc" style="opacity: 0.9" d=" M 7.81 8.31 C 11.74 4.27 17.35 1.87 23 2 C 28.65 1.88 34.27 4.27 38.19 8.31 C 42.01 12.15 44.21 17.59 44 23.02 C 44.04 28.86 41.15 34.49 36.78 38.28 C 32.15 42.35 27.72 46.65 23 50.62 C 18.28 46.66 13.86 42.34 9.23 38.28 C 4.85 34.49 1.96 28.85 2 23.01 C 1.8 17.59 3.99 12.15 7.81 8.31 Z" />' +
				// bottom balloon shadow
				'<path fill="#7f7f7f" d=" M 18.18 52.59 C 19 52.19 19.83 51.81 20.64 51.41 C 21.36 51.99 22.05 52.79 23.01 52.91 C 23.96 52.79 24.64 51.99 25.36 51.42 C 26.2 51.82 27.05 52.21 27.9 52.6 C 27.79 53.07 27.67 53.53 27.55 54 L 18.33 54 C 18.28 53.53 18.24 53.06 18.18 52.59 Z" />' +
				// text
				'<text x="23" y="27" font-size="8pt" font-family="arial" font-weight="bold" text-anchor="middle" fill="#333" textContent="€'+ price +'">€'+ price +'</text>' +
			'</svg>';
		var image = 'data:image/svg+xml;base64,' + Base64.encode(svg);

		var marker = new google.maps.Marker(
		{
			map: holidays.map,
			position: coordinate,
			icon:
			{
				url: image,
				anchor: new google.maps.Point(28, 54)
			},
			zIndex: 2,
			flat: true,
			title: '€' + price,
			id: id
		});

		// add click listener
		google.maps.event.addListener(marker, 'click', function(e)
		{
			holidays.infowindowOpen('ajax/location.php?id=' + this.id);
		});

		return marker;
	},

	drawCluster: function(coordinate, count)
	{
		// outerradius can range from 25 to 100; the more entries, the larger the radius
		var outerradius = 25;
		var index = 1;
		while(index < count && outerradius < 100)
		{
			outerradius += 4;
			index *= 2;
		}
		outerradius = Math.max(25, Math.min(100, outerradius));

		var svg =
			'<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">' +
				// transparent outer circle
				'<circle cx="100" cy="100" r="'+ outerradius +'" fill="#0097f5" style="opacity: 0.2"  />' +
				// inner circle
				'<circle cx="100" cy="100" r="23" stroke="#0097f5" fill="#fcfcfc" stroke-width="5" style="opacity: 0.9" />' +
				// text
				'<text x="100" y="105" font-size="8pt" font-family="arial" font-weight="bold" text-anchor="middle" fill="#007fce" textContent="'+ count +'">'+ count +'</text>' +
			'</svg>';
		var image = 'data:image/svg+xml;base64,' + Base64.encode(svg);

		var marker = new google.maps.Marker(
		{
			map: holidays.map,
			position: coordinate,
			icon:
			{
				url: image,
				anchor: new google.maps.Point(100, 100)
			},
			zIndex: 1,
			flat: true
		});

		// add click listener
		google.maps.event.addListener(marker, 'click', function(e)
		{
			holidays.map.setZoom(holidays.map.getZoom() + 1);
//			holidays.map.setCenter(marker.getPosition()); // cluster center
			holidays.map.setCenter(e.latLng); // clicked position
		});

		return marker;
	},

	drawReferencePoint: function(coordinate, text)
	{
		var svg =
			'<svg width="46" height="54" xmlns="http://www.w3.org/2000/svg">' +
				// balloon shape
				'<path fill="#0097f5" d=" M 7.81 8.31 C 11.74 4.27 17.35 1.87 23 2 C 28.65 1.88 34.27 4.27 38.19 8.31 C 42.01 12.15 44.21 17.59 44 23.02 C 44.04 28.86 41.15 34.49 36.78 38.28 C 32.15 42.35 27.72 46.65 23 50.62 C 18.28 46.66 13.86 42.34 9.23 38.28 C 4.85 34.49 1.96 28.85 2 23.01 C 1.8 17.59 3.99 12.15 7.81 8.31 Z" />' +
				// circle inside balloon
				'<circle cx="23" cy="23" r="13" fill="#0078c2" />' +
				// bottom balloon shadow
				'<path fill="#7f7f7f" d=" M 18.18 52.59 C 19 52.19 19.83 51.81 20.64 51.41 C 21.36 51.99 22.05 52.79 23.01 52.91 C 23.96 52.79 24.64 51.99 25.36 51.42 C 26.2 51.82 27.05 52.21 27.9 52.6 C 27.79 53.07 27.67 53.53 27.55 54 L 18.33 54 C 18.28 53.53 18.24 53.06 18.18 52.59 Z" />' +
			'</svg>'
		var image = 'data:image/svg+xml;base64,' + Base64.encode(svg);

		var marker = new google.maps.Marker(
		{
			map: holidays.map,
			position: coordinate,
			icon:
			{
				url: image,
				anchor: new google.maps.Point(28, 54)
			},
			zIndex: 3,
			clickable: false,
			flat: true,
			text: '<strong>' + text + '</strong>'
		});
		return marker;
	},

	autocomplete: function()
	{
		// bind autocomplete
		var searchField = $('#searchField');
		var searchbox = new google.maps.places.SearchBox(searchField.get(0));

		var setMarker = function(places)
		{
			if(places.length == 0 || typeof places[0].geometry == 'undefined') return;
			var place = places[0];

			// remove existing marker
			if(holidays.locationMarker) holidays.locationMarker.setMap(null);
			holidays.locationMarker = null;

			// really draw marker
			holidays.locationMarker = holidays.drawReferencePoint(place.geometry.location, place.name);

			// zoom to specified location
			holidays.map.setZoom(14);
			holidays.map.setCenter(place.geometry.location);
		}

		// selecting a specific place from the autocomplete drowndown
		google.maps.event.addListener(searchbox, 'places_changed', function() { setMarker(searchbox.getPlaces()); });

		// form submission (either automated in code or pressing enter) = launch search and assume first result
		searchField.parents('form').submit(function(e)
		{
			e.preventDefault();

			var service = new google.maps.places.PlacesService(holidays.map);
			service.textSearch({ query: searchField.val() }, setMarker);
		});

		// non-existing uri = launch search for that location!
		var location = decodeURIComponent(document.location.pathname.replace(/(^\/|\/$)/, '').replace(/-/g, ' '));
		if(location)
		{
			searchField.val(location);
			searchField.parents('form').submit();
		}
	},

	priceRange: function()
	{
		var updatePrices = function()
		{
			var values = $('.noUiSlider').val();
			holidays.minPrice = values[0];
			holidays.maxPrice = values[1];

			// update display
			$('#price').val('€' + holidays.minPrice + ' - €' + holidays.maxPrice);
		};

		$('.noUiSlider').noUiSlider(
		{
			range: [50, 300],
			start: [50, 300],
			handles: 2,
			step: 1,
			slide: updatePrices
		}).change(function()
		{
			// re-fetch markers, based on new price
			holidays.drawMarkers();
		});

		updatePrices();
	},

	bindInfoWindow: function()
	{
		// bind events to handle infowindow
		$('.submenu').click(function(e)
		{
			e.preventDefault();
			holidays.infowindowOpen($(this).attr('href'));
		});

		// mouseclick close
		$(document, '#infowindowClose').click(function(e)
		{
			$infowindow = $('#infowindow');
			$closeButton = $('#infowindowClose');

			// check clicked position: click outside window or on close button = close
			var offsetWindow = $infowindow.offset();
			var offsetButton = $closeButton.offset();
			if(
				// left or right from infowindow
				e.pageX < offsetWindow.left || e.pageX > offsetWindow.left + $infowindow.outerWidth() ||
				// above or beneath infowindow
				e.pageY < offsetWindow.top || e.pageY > offsetWindow.top + $infowindow.outerHeight() ||

				// clicked close button
				(e.pageX > offsetButton.left && e.pageX < offsetButton.left + $closeButton.outerWidth() &&
				e.pageY > offsetButton.top && e.pageY < offsetButton.top + $closeButton.outerHeight())
			)
			{
				e.preventDefault();
				holidays.infowindowClose();
			}
		});

		// escape key close
		$(document).keyup(function(e)
		{
			if(e.keyCode == 27)
			{
				e.preventDefault();
				holidays.infowindowClose();
			}
		});
	},

	infowindowOpen: function(url)
	{
		$.ajax(
		{
			url: url,
			dataType: 'html',
			success: function(html)
			{
				$('#infowindow')
					.show()
					.find('#infowindowContainer').html(html);
			}
		});
	},

	infowindowClose: function()
	{
		$('#infowindow').hide();
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
	 * @return object
	 */
	roundBounds: function(bounds)
	{
		var totalLat = bounds.getNorthEast().lat() - bounds.getSouthWest().lat();
		var totalLng = bounds.getNorthEast().lng() - bounds.getSouthWest().lng();

		var multiplierLat = Math.pow( 2, Math.ceil( Math.log( Math.abs( totalLat / 2 ) ) / Math.log( 2 ) ) );
		var multiplierLng = Math.pow( 2, Math.ceil( Math.log( Math.abs( totalLng / 2 ) ) / Math.log( 2 ) ) );

		// round coordinates (we don't want calls for every minor change)
		var bounds =
		{
			neLat: Math.max(-180, Math.min(180, Math.ceil( bounds.getNorthEast().lat() / multiplierLat ) * multiplierLat)),
			swLat: Math.max(-180, Math.min(180, Math.floor( bounds.getSouthWest().lat() / multiplierLat ) * multiplierLat)),
			neLng: Math.max(-360, Math.min(360, Math.ceil( bounds.getNorthEast().lng() / multiplierLng ) * multiplierLng)),
			swLng: Math.max(-360, Math.min(360, Math.floor( bounds.getSouthWest().lng() / multiplierLng ) * multiplierLng))
		}

		return bounds;
	}
}

$(holidays.init);
