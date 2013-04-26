var holidays = {
	// detect if running in app
	app: ( typeof cordova == 'undefined' && document.location.host ) ? false : true,
	// on apps, perform calls to specified remote url
	host: ( typeof cordova == 'undefined' && document.location.host ) ? '/' : 'http://www.last-minute-vakanties.be/',
	// on mobile, we'll serve mobile destination urls
	mobile: ( typeof cordova != 'undefined' && document.location.host ) || jQuery.browser.mobile,
	currency: '€',

	init: function() {
		var init = function() {
			holidays.hideAddressBar();
			holidays.priceRange();
			holidays.css();

			holidays.history.init();
			holidays.infowindow.init();
			holidays.translate.init();
			holidays.map.init();

			if ( !holidays.isOnline() ) {
				holidays.infowindow.error();
			}
		};

		// if in-app, wait for phonegap to complete
		if ( holidays.app && typeof cordova != 'undefined' ) {
			document.addEventListener( 'deviceready', init, false );
		} else {
			init();
		}
	},

	/**
	 * Verify that we have an internet connection
	 */
	isOnline: function() {
		if ( !holidays.app ) {
			return typeof navigator.onLine == 'undefined' || navigator.onLine;
		}

		return navigator.network.connection.type != Connection.NONE;
	},

	/**
	 * On iPhone, the address bar will be hidden when scrolling up.
	 * For scrolling to be possible, the document height will have to be higher
	 * than the viewport though, but we only want to have exact viewport height.
	 *
	 * Let's first set an absurdly high height, scroll (to hide the address bar),
	 * and then calculate the viewport height difference. After that, we can
	 * reset the absurdely high height to the viewport height and increase the
	 * map hight with the viewport height difference.
	 */
	hideAddressBar: function() {
		var $body = $( 'body' );
		var height = window.innerHeight;

		var hide = function() {
			$body.scrollTop( 1 );
		};

		$body.height( 9999 );
		hide();
		$body.height( window.innerHeight );

		var difference = window.innerHeight - height;

		// fix elements that were positioned absolutely against a now incorrect body height
		$( '#bottomWrapper' ).css( 'bottom', -difference );
		$( '#infowindow' ).css( 'bottom', -difference );

		// bind to events
		$( window ).on( 'load', hide );
		$( window ).on( 'orientationchange', hide );
	},

	/**
	 * Bind noUiSlider to allow the manipulation of the minimum and maximum price
	 * to find locations for.
	 * noUiSlider has been picked over jQuery UI Slider, because the latter is not
	 * terribly compatible with touch devices.
	 */
	priceRange: function() {
		var updatePrices = function() {
			var prices = $( '.noUiSlider' ).val();

			// update display
			$( '#price' ).val( holidays.currency + prices[0] + ' - ' + holidays.currency + prices[1] );
		};

		$( '.noUiSlider' ).noUiSlider( {
			range: [50, 300],
			start: [50, 300],
			handles: 2,
			step: 1,
			slide: updatePrices
		} ).change( function() {
			// re-fetch markers, based on new price
			holidays.map.reload();
		} );

		updatePrices();
	},

	/**
	 * Apply some additional styles that depend on some kind of interaction.
	 */
	css: function() {
		$( '#search input.inputText' ).focus( function() {
			$( '#search' ).addClass( 'inputHolderActive' );
		} );
		$( '#search input.inputText' ).blur( function() {
			$( '#search' ).removeClass( 'inputHolderActive' );
		} );
	}
};

$( holidays.init );
