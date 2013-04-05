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

	init: function()
	{
		holidays.drawMap();
		holidays.autocomplete();
		holidays.priceRange();

		// bind events to handle infowindow
		$('.submenu').click(function(e)
		{
			e.preventDefault();
			holidays.infowindowOpen($(this).attr('href'));
		});
		$(document, '#infowindowClose').click(holidays.infowindowClose);
		$(document).keyup(holidays.infowindowClose);

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
		google.maps.event.addListener(holidays.map, 'bounds_changed', holidays.drawMarkers);

		$('#loadingMap').hide();
	},

	drawMarkers: function()
	{
		var loadingMessage = $('#loadingMarkers');

		// don't fire new request while previous one is still running
		if(loadingMessage.is(':visible')) return;
		loadingMessage.show();

		// get viewport
		var bounds = holidays.map.getBounds();

		$.ajax(
		{
			url: 'ajax/markers.php',
			data:
			{
				min: holidays.minPrice,
				max: holidays.maxPrice,
				neLat: bounds.getNorthEast().lat(),
				neLng: bounds.getNorthEast().lng(),
				swLat: bounds.getSouthWest().lat(),
				swLng: bounds.getSouthWest().lng(),
				minPts: holidays.map.getZoom() > 13 ? 99999999 : 10, // zoomed in much = don't cluster
				nbrClusters: Math.round(Math.sqrt($('#map').width() * $('#map').height()) / 15) // smaller screen = less clusters
			},
			type: 'GET',
			dataType: 'json',
			success: function(json)
			{
				// clear existing markers
				for(var i in holidays.markers) holidays.markers[i].setMap(null);
				holidays.markers = [];

				for(var i = 0; i < json.locations.length; i++)
				{
					holidays.drawMarker(
						new google.maps.LatLng(json.locations[i].extra.lat, json.locations[i].extra.lng),
						json.locations[i].extra.id,
						json.locations[i].extra.price
					);
				}

				for(var i = 0; i < json.clusters.length; i++)
				{
					var ne = new google.maps.LatLng(json.clusters[i].bounds.neLat, json.clusters[i].bounds.neLng);
					var sw = new google.maps.LatLng(json.clusters[i].bounds.swLat, json.clusters[i].bounds.swLng);

					var count = json.clusters[i]['total'];
					var bounds = new google.maps.LatLngBounds(sw, ne);
					var coordinate = bounds.getCenter();

					holidays.drawCluster(coordinate, count, bounds);
				}

				loadingMessage.hide();
			}
		});
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
			icon: image,
			anchorPoint: new google.maps.Point(28, 54),
			title: '€' + price,
			id: id
		});

		// add click listener
		google.maps.event.addListener(marker, 'click', function(e)
		{
			holidays.infowindowOpen('ajax/location.php?id=' + this.id);
		});

		holidays.markers.push(marker);
	},

	drawCluster: function(coordinate, count, bounds)
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
			icon: image,
			anchorPoint: new google.maps.Point(100, 100),
			bounds: bounds
		});

		// add click listener
		google.maps.event.addListener(marker, 'click', function(e)
		{
			holidays.map.setZoom(holidays.map.getZoom() + 1);
			holidays.map.setCenter(e.latLng);

			// alternative: zoom to boundaries of clicked marker
//			holidays.map.fitBounds(marker.bounds);
		});

		holidays.markers.push(marker);
	},

	autocomplete: function()
	{
		// bind autocomplete
		var input = $('#searchField').get(0);
		var autocomplete = new google.maps.places.Autocomplete(input);

		locate = function()
		{
			var place = autocomplete.getPlace();

			// none found (probably clicked enter with incomplete search query) - ignore!
			if(typeof place.geometry == 'undefined') {}

			// remove existing marker
			if(holidays.locationMarker) holidays.locationMarker.setMap(null);
			holidays.locationMarker = null;

			// add marker to requested location
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

			holidays.locationMarker = new google.maps.Marker(
			{
				map: holidays.map,
				position: place.geometry.location,
				icon: image,
				anchorPoint: new google.maps.Point(28, 54),
				text: '<strong>' + place.name + '</strong>'
			});

			// zoom to specified location
			holidays.map.setZoom(14);
			holidays.map.setCenter(place.geometry.location);
		}

		google.maps.event.addListener(autocomplete, 'place_changed', locate);
	},

	priceRange: function()
	{
		var updatePrices = function() {
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

	infowindowOpen: function(url)
	{
		$.ajax(
		{
			url: url,
			dataType: 'html',
			success: function(html)
			{
				$('#infowindow').show().find('#infowindowContainer').html(html);
			}
		});
	},

	infowindowClose: function(e)
	{
		$infowindow = $('#infowindow');
		$closeButton = $('#infowindowClose');

		// check clicked position: click outside window or on close button = close
		var offsetWindow = $infowindow.offset();
		var offsetButton = $closeButton.offset();
		if(
			// escape key
			e.keyCode == 27 ||
			// left or right from infowindow
			e.pageX < offsetWindow.left || e.pageX > offsetWindow.left + $infowindow.outerWidth() ||
			// above or beneath infowindow
			e.pageY < offsetWindow.top || e.pageY > offsetWindow.top + $infowindow.outerHeight() ||
			// inside close button
			(e.pageX > offsetButton.left && e.pageX < offsetButton.left + $closeButton.outerWidth() &&
			 e.pageY > offsetButton.top && e.pageY < offsetButton.top + $closeButton.outerHeight())
		)
		{
			e.preventDefault();
			$infowindow.hide();
		}
	}
}

$(holidays.init);
