holidays.translate = {
	default: 'nl',

	/**
	 * As soon as the content of an infowindow changes, see if there is content
	 * in a foreign language ([data-language=XY]) and translate it
	 */
	init: function() {
		$( '#infowindow' ).on( 'DOMSubtreeModified', function() {
			$( this ).find( '[data-language]' ).each( function() {
				var from = $( this ).data( 'language' );

				/*
				 * change language flag (to prevent follow-up requests, since
				 * DOMSubtreeModified will be fired again when we replace the
				 * text with the translated content.
				 */
				$( this ).data( 'language', holidays.translate.default );

				holidays.translate.translate( $( this ), from, holidays.translate.default );
			} );
		} );
	},

	/**
	 * Translate the text of a jQuery node.
	 *
	 * @param jQuery $element
	 * @param string from
	 * @param string to
	 */
	translate: function( $element, from, to ) {
		if ( from == to ) {
			return;
		}

		$.ajax( {
			url: 'http://mymemory.translated.net/api/get',
			data: {
				q: $element.html(),
				langpair: from + '|' + to,
				of: 'json'
			},
			type: 'GET',
			dataType: 'json',
			success: function( json ) {
				$element.html( json.responseData.translatedText );
			}
		} );
	}
};

$( holidays.translate.init );
