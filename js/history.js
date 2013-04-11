holidays.history = {
	callbacks: [],

	init: function() {
		holidays.history.bind();
	},

	/**
	 * Bind to the "inpopstate" event, fired when navigating through a browser's history.
	 */
	bind: function() {
		window.onpopstate = function( e ) {
			if ( e.state && 'callback' in e.state ) {
				e.preventDefault();

				// execute callback with the saved arguments
				var callback = holidays.history.callbacks[e.state.callback];
				callback.apply( this, e.state.state );
			}
		};
	},

	/**
	 * Add a new "state" to the browser's history.
	 *
	 * @param object state
	 * @param string name
	 * @param string slug
	 * @param function callback
	 */
	push: function( state, name, slug, callback ) {
		// don't re-add current state
		if (
			window.history.state &&
			JSON.stringify( window.history.state.state ) == JSON.stringify( state ) &&
			window.history.state.name == name
		) {
			return;
		}

		// save callback function to memory
		var i = holidays.history.callbacks.length;
		holidays.history.callbacks[i] = callback;

		window.history.pushState( { callback: i, state: state, name: name }, name, slug );
	},

	/**
	 * Replace the current "state" in the browser's history by a new one.
	 *
	 * @param object state
	 * @param string name
	 * @param string slug
	 * @param function callback
	 */
	replace: function( state, name, slug, callback ) {
		// save callback function to memory
		var i = holidays.history.callbacks.length;
		holidays.history.callbacks[i] = callback;

		window.history.replaceState( { callback: i, state: state, name: name }, name, slug );
	},

	/**
	 * Replaces the next "state" that is being added. This can be used when the order of
	 * execution is unclear (e.g. waiting for event being triggered)
	 *
	 * @param object state
	 * @param string name
	 * @param string slug
	 * @param function callback
	 */
	replaceNext: function( state, name, slug, callback ) {
		var historyLength = window.history.length;

		var replaceNext = function() {
			if ( window.history.length > historyLength ) {
				clearInterval( interval );

				holidays.history.replace( state, name, slug, callback );
			}
		};

		var interval = setInterval( replaceNext, 50 );
	}
};

$( holidays.history.init );
