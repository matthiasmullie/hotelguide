var holidays =
{
	map: null,
	containers: [],
	markerClusterer: null,
	locationMarker: null,
	lastMinuteTheme: new nokia.maps.clustering.MarkerTheme(),
	minPrice: 50,
	maxPrice: 300,

	init: function()
	{
		holidays.initMap();
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

	initMap: function()
	{
		var loadingMessage = $('#loadingMap');
		loadingMessage.show();

		nokia.Settings.set('appId', 'nHMOm3zz6pYH0I2tGItI');
		nokia.Settings.set('authenticationToken', 'wUnag7B1LPoj9DZCL_7hbg');

		// init map
		holidays.map = new nokia.maps.map.Display(
			$('#map').get(0),
			{
				zoomLevel: 3,
				center: [40, 0],
				baseMapType: nokia.maps.map.Display.SMARTMAP,
				components:
				[
//					new nokia.maps.map.component.ZoomBar(),
//					new nokia.maps.map.component.TypeSelector().
					new nokia.maps.map.component.Behavior()
				]
			}
		);

		// init clusterer
		holidays.markerClusterer = new nokia.maps.clustering.ClusterProvider(
			holidays.map,
			{
				eps: 50,
				dataPoints: [],
				theme: holidays.lastMinuteTheme // see theme.js
			}
		);

		// seriously alter clusterer's behavior & icons
		holidays.lastMinuteTheme.getClusterPresentation = function(data)
		{
			var coordinate = this.getClusterCoordinate(data.getPoints());
			var count = data.getSize();
			var bounds = data.getBounds();

			return holidays.drawCluster(coordinate, count, bounds);
		};
		holidays.lastMinuteTheme.getNoisePresentation = function(dataPoint)
		{
			return holidays.drawNoise(dataPoint, dataPoint.id, dataPoint.price);
		};

		// as soon as map view changes, fetch new markers
		holidays.map.addListener('mapviewchangeend', holidays.drawMarkers);

		loadingMessage.hide();
	},

	drawMarkers: function()
	{
		var loadingMessage = $('#loadingMarkers');

		// don't fire new request while previous one is still running
		if(loadingMessage.is(':visible')) return;
		loadingMessage.show();

		// get viewport
		var bounds = holidays.map.getViewBounds();

		$.ajax(
		{
			url: 'ajax/markers.php',
			data:
			{
				min: holidays.minPrice,
				max: holidays.maxPrice,
				seLat: bounds.bottomRight.latitude < bounds.topLeft.latitude ? bounds.bottomRight.latitude : bounds.topLeft.latitude,
				seLng: bounds.bottomRight.longitude > bounds.topLeft.longitude ? bounds.bottomRight.longitude : bounds.topLeft.longitude,
				nwLat: bounds.bottomRight.latitude > bounds.topLeft.latitude ? bounds.bottomRight.latitude : bounds.topLeft.latitude,
				nwLng: bounds.bottomRight.longitude < bounds.topLeft.longitude ? bounds.bottomRight.longitude : bounds.topLeft.longitude,
				minPts: holidays.map.zoomLevel > 13 ? 99999999 : 2
			},
			type: 'GET',
			dataType: 'json',
			success: function(json)
			{
				// clear existing markers
				for(var i in holidays.containers) holidays.map.objects.remove(holidays.containers[i]);
				holidays.markerClusterer.clean();

				for(var i = 0; i < json.locations.length; i++) holidays.drawMarker(json.locations[i].extra);
				for(var i = 0; i < json.clusters.length; i++)
				{
					var seLat = Math.round(json.clusters[i]['bounds']['seLat'] * 10000) / 10000;
					var seLng = Math.round(json.clusters[i]['bounds']['seLng'] * 10000) / 10000;
					var nwLat = Math.round(json.clusters[i]['bounds']['nwLat'] * 10000) / 10000;
					var nwLng = Math.round(json.clusters[i]['bounds']['nwLng'] * 10000) / 10000;

					var se = new nokia.maps.geo.Coordinate(seLat, seLng);
					var nw = new nokia.maps.geo.Coordinate(nwLat, nwLng);

					var count = json.clusters[i]['total'];
					var bounds = new nokia.maps.geo.BoundingBox(nw, se);
					var coordinate = bounds.getCenter();

					var container = holidays.drawCluster(coordinate, count, bounds);
					holidays.containers.push(container);
					holidays.map.objects.add(container);
				}

				// only cluster when zoomed in < 13
				holidays.markerClusterer.setMinPts( holidays.map.zoomLevel > 13 ? 99999999 : 2 );
				holidays.markerClusterer.cluster();

				loadingMessage.hide();
			}
		});
	},

	drawMarker: function(details)
	{
		// too precise coordinates are too hard for HERE to handle ;)
		var lat = Math.round(details.lat * 10000) / 10000;
		var lng = Math.round(details.lng * 10000) / 10000;
		var coordinate = new nokia.maps.geo.Coordinate(lat, lng);

		// add additional details
		coordinate.price = details.price;
		coordinate.id = details.id;

		holidays.markerClusterer.add(coordinate);
	},

	drawCluster: function(coordinate, count, bounds)
	{
		// outerradius can range from 40 to 70; the more entries, the larger the radius
		var outerradius = 30;
		var index = 1;
		while(index < count && outerradius < 70)
		{
			outerradius += 3;
			index *= 2;
		}
		outerradius = Math.max(30, Math.min(70, outerradius));

		var marker = new nokia.maps.map.Marker(
			coordinate,
			{
				icon: new nokia.maps.gfx.GraphicsImage(
					new nokia.maps.gfx.SvgParser().parseSvg(
						'<svg width="70" height="70" xmlns="http://www.w3.org/2000/svg">' +
							'<circle cx="35" cy="35" r="'+ outerradius +'" fill="#abddfc" style="opacity: 0.7"  />' +
							'<circle cx="35" cy="35" r="23" stroke="#0097f5" fill="#fdfdfd" stroke-width="5" />' +
							// text
							'<text x="35" y="40" font-size="8pt" font-family="arial" font-weight="bold" text-anchor="middle" fill="#007fce" textContent="'+ count +'">'+ count +'</text>' +
						'</svg>'
					)
				),
				anchor: { x: 35, y: 35 },
				bounds: bounds
			}
		);

		// add click/tap handler to zoom in
		var clickCallback = function(e)
		{
			holidays.map.zoomTo(e.target.bounds); // zoom to bounds
//			holidays.map.setAttributes(undefined, e.target.coordinate, holidays.map.zoomLevel + 1, undefined, undefined); // zoom in 1 level
		};
		marker.addListeners(
			{
				'click': [clickCallback, true],
				'tap': [clickCallback, true]
			});

		var container = new nokia.maps.map.Container();
		container.objects.add(marker);
		return container;
	},

	drawNoise: function(coordinate, id, price) {
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

		var marker = new nokia.maps.map.Marker(
			coordinate,
			{
				id: id,
				icon: new nokia.maps.gfx.GraphicsImage(
					new nokia.maps.gfx.SvgParser().parseSvg(
						'<svg width="46" height="54" xmlns="http://www.w3.org/2000/svg">' +
							// balloon shape
							'<path stroke="'+ color +'" stroke-width="5" fill="#fcfcfc" d=" M 7.81 8.31 C 11.74 4.27 17.35 1.87 23 2 C 28.65 1.88 34.27 4.27 38.19 8.31 C 42.01 12.15 44.21 17.59 44 23.02 C 44.04 28.86 41.15 34.49 36.78 38.28 C 32.15 42.35 27.72 46.65 23 50.62 C 18.28 46.66 13.86 42.34 9.23 38.28 C 4.85 34.49 1.96 28.85 2 23.01 C 1.8 17.59 3.99 12.15 7.81 8.31 Z" />' +
							// bottom balloon shadow
							'<path fill="#7f7f7f" d=" M 18.18 52.59 C 19 52.19 19.83 51.81 20.64 51.41 C 21.36 51.99 22.05 52.79 23.01 52.91 C 23.96 52.79 24.64 51.99 25.36 51.42 C 26.2 51.82 27.05 52.21 27.9 52.6 C 27.79 53.07 27.67 53.53 27.55 54 L 18.33 54 C 18.28 53.53 18.24 53.06 18.18 52.59 Z" />' +
							// text
							'<text x="23" y="27" font-size="8pt" font-family="arial" font-weight="bold" text-anchor="middle" fill="#333" textContent="€'+ price +'">€'+ price +'</text>' +
						'</svg>'
					)
				),
				anchor: { x: 28, y: 54 }
			}
		);

		// add click/tap handler to open infowindow
		var clickCallback = function(e)
		{
			holidays.infowindowOpen('ajax/location.php?id=' + e.target.id);
		};
		marker.addListeners(
			{
				'click': [clickCallback, true],
				'tap': [clickCallback, true]
			});

		var container = new nokia.maps.map.Container();
		container.objects.add(marker);
		return container;
	},

	autocomplete: function()
	{
		// bind autocomplete
		var input = $('#searchField').get(0);

		// soo, here we'll use google because it's POI database is richer ;)
		var autocomplete = new google.maps.places.Autocomplete(input);

		var locate = function()
		{
			var place = autocomplete.getPlace();

			// none found (probably clicked enter with incomplete search query) - ignore!
			if(typeof place.geometry == 'undefined') {}

			// remove existing marker
			holidays.map.objects.remove(holidays.locationMarker);

			var name = place.name;
			var lat = place.geometry.location.lat();
			var lng = place.geometry.location.lng();

			// to HERE coordinate!
			var coordinate = new nokia.maps.geo.Coordinate(lat, lng);

			// set marker for this specific location
			holidays.locationMarker = new nokia.maps.map.Marker(
				coordinate,
				{
					icon: new nokia.maps.gfx.GraphicsImage(
						new nokia.maps.gfx.SvgParser().parseSvg(
							'<svg width="46" height="54" xmlns="http://www.w3.org/2000/svg">' +
								// balloon shape
								'<path fill="#0097f5" d=" M 7.81 8.31 C 11.74 4.27 17.35 1.87 23 2 C 28.65 1.88 34.27 4.27 38.19 8.31 C 42.01 12.15 44.21 17.59 44 23.02 C 44.04 28.86 41.15 34.49 36.78 38.28 C 32.15 42.35 27.72 46.65 23 50.62 C 18.28 46.66 13.86 42.34 9.23 38.28 C 4.85 34.49 1.96 28.85 2 23.01 C 1.8 17.59 3.99 12.15 7.81 8.31 Z" />' +
								// circle inside balloon
								'<circle cx="23" cy="23" r="13" fill="#0078c2" />' +
								// bottom balloon shadow
								'<path fill="#7f7f7f" d=" M 18.18 52.59 C 19 52.19 19.83 51.81 20.64 51.41 C 21.36 51.99 22.05 52.79 23.01 52.91 C 23.96 52.79 24.64 51.99 25.36 51.42 C 26.2 51.82 27.05 52.21 27.9 52.6 C 27.79 53.07 27.67 53.53 27.55 54 L 18.33 54 C 18.28 53.53 18.24 53.06 18.18 52.59 Z" />' +
							'</svg>'
						)
					),
					anchor: { x: 28, y: 54 }
				}
			);
			holidays.map.objects.add(holidays.locationMarker);

			// zoom to specified location
			holidays.map.setAttributes(undefined, coordinate, 14, undefined, undefined);
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
			}
		);
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
			// click left or right from infowindow
			e.pageX < offsetWindow.left || e.pageX > offsetWindow.left + $infowindow.outerWidth() ||
			// click above or beneath infowindow
			e.pageY < offsetWindow.top || e.pageY > offsetWindow.top + $infowindow.outerHeight() ||
			// inside, close button
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
