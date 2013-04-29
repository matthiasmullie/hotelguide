holidays.translate = {
	default: navigator.language || navigator.userLanguage,

	init: function() {
		holidays.translate.l20n( document );
		holidays.translate.bind();
	},

	/**
	 * Localize (using l20n provided in document) a certain HTML element.
	 *
	 * @param HTMLElement element
	 */
	l20n: function( element ) {
		/**
		 * Code stolen from l20n.js
		 * @see l20n.js
		 *
		 * l20n.js originally replaces node.textContent, but we prefer
		 * node.innerHTML ;)
		 *
		 * @param node
		 * @param l10n
		 */
		function retranslate( node, l10n ) {
			var nodes = node.querySelectorAll( '[data-l10n-id]' );
			var entity;
			for ( var i = 0; i < nodes.length; i++ ) {
				var id = nodes[i].getAttribute( 'data-l10n-id' );
				var entity = l10n.entities[id];
				var node = nodes[i];
				if ( entity.value ) {
					node.innerHTML = entity.value;
				}
				for ( var key in entity.attributes ) {
					node.setAttribute( key, entity.attributes[key] );
				}
			}
			// readd data-l10n-attrs
			// readd data-l10n-overlay
			// secure attribute access
		}

		/**
		 * Code stolen from l20n.js
		 * @see l20n.js
		 *
		 * @param node
		 */
		function localizeNode( node ) {
			var nodes = node.querySelectorAll( '[data-l10n-id]' );
			var ids = [];
			for ( var i = 0; i < nodes.length; i++ ) {
				if ( nodes[i].hasAttribute( 'data-l10n-args' ) ) {
					ids.push( [
						nodes[i].getAttribute( 'data-l10n-id' ),
						JSON.parse( nodes[i].getAttribute( 'data-l10n-args' ) )
					] );
				} else {
					ids.push( nodes[i].getAttribute( 'data-l10n-id' ) );
				}
			}
			ctx.localize( ids, retranslate.bind( this, node ) );
		}

		var ctx = document.l10n;
		localizeNode( element );
	},

	/**
	 * As soon as the content of an infowindow changes, see if there is content
	 * in a foreign language ([data-language=XY]) and translate it
	 */
	bind: function() {
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
		// ISO 639-1
		from = from.substr( 0, 2 );
		to = to.substr( 0, 2 );

		if ( !from || !to || from == to ) {
			return;
		}

		// trim to 500 chars
		var text = $element.text();
		if ( text.length > 500 ) {
			text = text.substr( 0, 500 );
			text = text.substr( 0, text.lastIndexOf( ' ' ) ) + '…';
		}

		$.ajax( {
			url: 'http://mymemory.translated.net/api/get',
			data: {
				q: text,
				langpair: from + '|' + to,
				of: 'json',
				mt: 1, // machine translation
				de: 'mymemory@last-minute-vakanties.be' // point of contact
			},
			type: 'GET',
			dataType: 'json',
			success: function( json ) {
				if ( typeof json.responseStatus != 'undefined' && json.responseStatus == 200 ) {
					$element.html( json.responseData.translatedText );
				}
			}
		} );
	}
};
