holidays.translate = {
	default: navigator.language || navigator.userLanguage,

	init: function() {
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
		 * @param node
		 * @param l10n
		 */
		function retranslate(node, l10n) {
			var nodes = node.querySelectorAll('[data-l10n-id]');
			var entity;
			for (var i = 0; i < nodes.length; i++) {
				var id = nodes[i].getAttribute('data-l10n-id');
				var entity = l10n.entities[id];
				var node = nodes[i];
				if (entity.value) {
					node.textContent = entity.value;
				}
				for (var key in entity.attributes) {
					node.setAttribute(key, entity.attributes[key]);
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
		function localizeNode(node) {
			var nodes = node.querySelectorAll('[data-l10n-id]');
			var ids = [];
			for (var i = 0; i < nodes.length; i++) {
				if (nodes[i].hasAttribute('data-l10n-args')) {
					ids.push([nodes[i].getAttribute('data-l10n-id'),
						JSON.parse(nodes[i].getAttribute('data-l10n-args'))]);
				} else {
					ids.push(nodes[i].getAttribute('data-l10n-id'));
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
		if ( from == to || !to ) {
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
