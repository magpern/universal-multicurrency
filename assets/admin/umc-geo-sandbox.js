( function ( $ ) {
	'use strict';

	function countryInput() {
		return $( '#umc_geo_sandbox_country' );
	}

	function browseSelect() {
		return $( '[data-umc-geo-browse-select]' );
	}

	function setCountry( code ) {
		countryInput().val( code );
		browseSelect().val( code );
	}

	$( document ).on( 'click', '[data-umc-geo-preset-country]', function ( event ) {
		event.preventDefault();
		setCountry( $( this ).data( 'umc-geo-preset-country' ) );
	} );

	$( document ).on( 'click', '[data-umc-geo-toggle-browse]', function ( event ) {
		event.preventDefault();
		var $browse = $( '.umc-geo-sandbox-browse' );
		$browse.prop( 'hidden', ! $browse.prop( 'hidden' ) );
	} );

	$( document ).on( 'change', '[data-umc-geo-browse-select]', function () {
		setCountry( $( this ).val() );
	} );

	$( function () {
		var initial = countryInput().val();

		if ( initial ) {
			browseSelect().val( initial );
		}
	} );
}( jQuery ) );
