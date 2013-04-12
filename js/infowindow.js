holidays.infowindow = {
	init: function() {
		holidays.infowindow.bind();
	},

	/**
	 * Clicking several elements (class="infowindow") should result in the requests'
	 * output displayed in an infowindow.
	 */
	bind: function() {
		// every link with class="infowindow" will see its content opened in an infowindow
		$( document ).on( 'click', 'a.infowindow', function( e ) {
			e.preventDefault();
			holidays.infowindow.open( $( this ).attr( 'href' ) );
		});

		// every form with class="infowindow" will submit over ajax and display the results in the infowindow
		$( document ).on( 'submit', 'form.infowindow', function( e ) {
			e.preventDefault();

			// submit form via ajax
			var action = $( this ).attr( 'action' );
			var method = $( this ).attr( 'method' );
			var data = $( this ).serialize();

			$.ajax( {
				url: holidays.host + action,
				data: data,
				type: method,
				dataType: 'html',
				success: holidays.infowindow.output,
				error: holidays.infowindow.error
			} );
		});
	},

	events: {
		/*
		 * Mouseclick close, either clicked:
		 * * outside of the infowindow's boundaries
		 * * on the infowindow's close button
		 */
		click: function( e ) {
			var $infowindow = $( '#infowindowContainer' );
			var $closeButton = $( '#infowindowClose' );

			// check clicked position: click outside window or on close button = close
			var offsetWindow = $infowindow.offset();
			var offsetButton = $closeButton.offset();

			if (
				// left or right from infowindow
				e.pageX < offsetWindow.left || e.pageX > offsetWindow.left + $infowindow.outerWidth() ||
				// above or beneath infowindow
				e.pageY < offsetWindow.top || e.pageY > offsetWindow.top + $infowindow.outerHeight() ||

				// close button
				( e.pageX > offsetButton.left && e.pageX < offsetButton.left + $closeButton.outerWidth() &&
				e.pageY > offsetButton.top && e.pageY < offsetButton.top + $closeButton.outerHeight() )
			) {
				e.preventDefault();
				holidays.infowindow.close();
			}
		},

		/*
		 * Escape key close
		 */
		escape: function( e ) {
			if ( e.keyCode == 27 ) {
				e.preventDefault();
				holidays.infowindow.close();
			}
		}
	},

	/**
	 * Outputs a certain content into an infowindow.
	 *
	 * @param string content
	 */
	output: function( content ) {
		$( '#infowindow' )
			.show()
			.find( '#infowindowContainer' ).html( content );

		$( document ).on( 'click', 'body, #infowindowClose', holidays.infowindow.events.click );
		$( document ).on( 'keyup', null, holidays.infowindow.events.escape );
	},

	/**
	 * Display the content into an infowindow.
	 *
	 * @param string content
	 */
	display: function( content ) {
		holidays.infowindow.output( content );

		// save details in history, allowing people to use browser next/previous to return to the infowindow
		holidays.history.push( [content], 'infowindow', '/', holidays.infowindow.display );
	},

	/**
	 * Open the contents of <url> into an infowindow.
	 *
	 * @param string url
	 */
	open: function( url ) {
		$.ajax( {
			url: holidays.host + url,
			dataType: 'html',
			success: holidays.infowindow.display,
			error: holidays.infowindow.error
		} );
	},

	/**
	 * Close the currently open infowindow.
	 */
	close: function() {
		// unbind events listeners to close infowindow
		$( document ).off( 'click', 'body, #infowindowClose', holidays.infowindow.events.click );
		$( document ).off( 'keyup', null, holidays.infowindow.events.escape );

		$( '#infowindow' ).hide();
	},

	/**
	 * Display an generic error message (error.html)
	 */
	error: function() {
		$.ajax( {
			url: '/error.html',
			dataType: 'html',
			success: holidays.infowindow.display
		} );
	}
};

$( holidays.infowindow.init );
