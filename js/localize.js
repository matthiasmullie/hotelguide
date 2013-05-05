holidays.localize = {
	language: $.cookie( 'language' ) || ( navigator.language || navigator.userLanguage ).substr( 0, 2 ),
	currency: $.cookie( 'currency' ) || 'EUR',
	priceRange: {
		'EUR': [50, 300],
		'USD': [50, 400]
	},

	init: function() {
		holidays.localize.l20n( document );
		holidays.localize.bind();

		// set default values
		$( 'input[name=language][value='+ holidays.localize.language +']' ).prop( 'checked', true ).trigger( 'change' );
		$( 'input[name=currency][value='+ holidays.localize.currency +']' ).prop( 'checked', true ).trigger( 'change' );
	},

	bind: function() {
		// click cog = open/close settings pane
		$( '#settings a' ).on( 'click', function( e ) {
			e.preventDefault();

			var $settingsPane = $( '#settingsPane' );
			if ( $settingsPane.is( ':visible' ) ) {
				$settingsPane.hide();
				$( this ).removeClass( 'active' );
			} else {
				$settingsPane.show();
				$( this ).addClass( 'active' );
			}
		} );

		// update settings when new one is selected
		$( 'input[name=language], input[name=currency]' ).on( 'change', function() {
			$( this )
				.parents( 'li' ).addClass( 'selected' )
					.siblings().removeClass( 'selected' );

			// get newly selected values
			var language = $( 'input[name=language]:checked' ).val() || holidays.localize.language;
			var currency = $( 'input[name=currency]:checked' ).val() || holidays.localize.currency;

			// verify data
			if ( !( currency in holidays.localize.priceRange ) ) {
				currency = holidays.localize.currency;
			}

			if ( language !== holidays.localize.language || currency !== holidays.localize.currency ) {
				holidays.localize.language = language;
				holidays.localize.currency = currency;

				// fire new request for markers
				holidays.map.reload();

				// update slider ranges
				$( '.noUiSlider' ).empty().off();
				holidays.priceRangeSlider();

				// translate interface
				if ( typeof document.l10n != 'undefined' ) {
					var ctx = document.l10n;

					// hack to translate to current language
					ctx.registerLocales( holidays.localize.language );
				}
				holidays.localize.l20n( document );

				// save to cookie
				$.cookie( 'language', holidays.localize.language );
				$.cookie( 'currency', holidays.localize.currency );
			}
		} );
	},

	/**
	 * Localize (using l20n provided in document) a certain HTML element.
	 *
	 * @param HTMLElement element
	 */
	l20n: function( element ) {
		var translatedIds = [];

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
					if ( $.inArray( id, translatedIds ) < 0 ) {
						node.innerHTML = entity.value;
						translatedIds.push( id );
					}
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
