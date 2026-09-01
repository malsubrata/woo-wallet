/* global wallet_param */
import '../scss/frontend.scss';

jQuery( function ( $ ) {
	$( '.woo-wallet-select2' ).selectWoo( {
		language: {
			inputTooShort() {
				if ( wallet_param.search_by_user_email ) {
					return wallet_param.i18n.non_valid_email_text;
				}
				return wallet_param.i18n.inputTooShort;
			},
			noResults() {
				if ( wallet_param.search_by_user_email ) {
					return wallet_param.i18n.non_valid_email_text;
				}
				return wallet_param.i18n.no_resualt;
			},
			searching() {
				return wallet_param.i18n.searching;
			},
		},
		minimumInputLength: 3,
		ajax: {
			url: wallet_param.ajax_url,
			dataType: 'json',
			type: 'POST',
			delay: 250,
			data( term ) {
				return {
					action: 'woo-wallet-user-search',
					security: wallet_param.search_user_nonce,
					autocomplete_field: 'ID',
					term: term.term,
				};
			},
			processResults( data ) {
				return {
					results: $.map( data, function ( item ) {
						return {
							id: item.value,
							text: item.label,
						};
					} ),
				};
			},
		},
	} );

	$( '#woo_wallet_transfer_form' ).submit( function () {
		$( this ).submit( function () {
			return false;
		} );
		return true;
	} );

	// Submenu Toggle
	$( '.woo-wallet-nav-item-wrapper.has-submenu > a' ).on(
		'click',
		function ( e ) {
			e.preventDefault();
			const $submenu = $( this ).siblings( '.woo-wallet-submenu' );
			const $icon = $( this ).find( '.woo-wallet-submenu-toggle' );

			$( '.woo-wallet-submenu' ).not( $submenu ).slideUp();
			$( '.woo-wallet-submenu-toggle' )
				.not( $icon )
				.removeClass( 'rotate' );

			$submenu.slideToggle();
			$icon.toggleClass( 'rotate' );
		}
	);

	$( document ).on( 'click', function ( e ) {
		if (
			! $( e.target ).closest(
				'.woo-wallet-nav-item-wrapper.has-submenu'
			).length
		) {
			$( '.woo-wallet-submenu' ).slideUp();
			$( '.woo-wallet-submenu-toggle' ).removeClass( 'rotate' );
		}
	} );
} );
