holidays.localize = {
	language: ( navigator.language || navigator.userLanguage ).substr( 0, 2 ),
	currency: 'EUR',
	priceRange: {
		'EUR': [50, 300],
		'USD': [50, 400]
	},

	init: function() {
		holidays.localize.l20n( document );
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
		 * After replacing l20n'ed context, remove the data-l10n-id attribute
		 * so we can go recursive (otherwise, it could l20n the parent again)
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
					node.removeAttribute( 'data-l10n-id' );
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

		if ( typeof document.l10n != 'undefined' ) {
			var ctx = document.l10n;

			do {
				var html = element.innerHTML;
				localizeNode( element );
			} while ( html != element.innerHTML );
		}
	}
};
