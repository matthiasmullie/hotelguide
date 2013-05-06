var holidays = {
	// detect if running in app
	app: ( typeof cordova == 'undefined' && document.location.host ) ? false : true,
	// on apps, perform calls to specified remote url
	host: ( typeof cordova == 'undefined' && document.location.host ) ? '/' : 'http://www.last-minute-vakanties.be/',
	// on mobile, we'll serve mobile destination urls
	mobile: ( typeof cordova != 'undefined' && document.location.host ) || jQuery.browser.mobile,

	init: function() {
		var init = function() {
			holidays.hideAddressBar();
			holidays.priceRangeSlider();
			holidays.css();
			holidays.openExternal();

			holidays.history.init();
			holidays.infowindow.init();
			holidays.localize.init();
			holidays.map.init();

			if ( !holidays.isOnline() ) {
				holidays.infowindow.error();

				// if not inline, poll for network status and reload app once online
				var reload = function() {
					if ( holidays.isOnline() ) {
						location.reload( true );
					}
				};
				setInterval( reload, 1000 );
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
	 * Open target=_blank links in external browser, not in-app
	 */
	openExternal: function() {
		if ( !holidays.app ) {
			return;
		}

		$( document ).on( 'click', '[target=_blank]', function( e ) {
			e.preventDefault();

			window.open( $( this ).attr( 'href' ), '_system' );
		} );
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
			$body.height( window.innerHeight );
		};

		$body.height( 9999 );
		hide();

		var difference = window.innerHeight - height;

		// fix elements that were positioned absolutely against what is now an incorrect body height
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
	priceRangeSlider: function() {
		var updatePrices = function() {
			var prices = $( '.noUiSlider' ).val();

			// update display
			var data = {
				'curr': holidays.localize.currency,
				'from': prices[0],
				'to': prices[1]
			};
			$( '#price' ).data( 'l10n-args', JSON.stringify( data ) );
			$( '#price' ).attr( 'data-l10n-args', JSON.stringify( data ) );

			// save range to cookie
			$.cookie( 'priceRange-' + holidays.localize.currency, JSON.stringify( prices ) );

			// re-localize
			holidays.localize.l20n( $( '#filter' ).get( 0 ) );
		};

		// get existing range from cookie (if any)
		var cookieRange = $.cookie( 'priceRange-' + holidays.localize.currency );
		cookieRange = cookieRange ? JSON.parse( cookieRange ) : null;

		$( '.noUiSlider' ).noUiSlider( {
			range: holidays.localize.priceRange[holidays.localize.currency],
			start: cookieRange || holidays.localize.priceRange[holidays.localize.currency],
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
