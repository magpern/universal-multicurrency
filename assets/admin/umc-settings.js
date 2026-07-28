( function ( $ ) {
	'use strict';

	function toggleEditor( targetId, open ) {
		var $row = $( '#' + targetId );

		if ( ! $row.length ) {
			return;
		}

		$( '.umc-editor-row' ).removeClass( 'umc-editor-row--open' );

		if ( open ) {
			$row.addClass( 'umc-editor-row--open' );
			$row.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}
	}

	$( function () {
		$( document ).on( 'click', '.umc-editor-toggle', function ( event ) {
			event.preventDefault();
			toggleEditor( $( this ).data( 'target' ), true );
		} );

		$( document ).on( 'click', '.umc-editor-close', function ( event ) {
			event.preventDefault();
			toggleEditor( $( this ).data( 'target' ), false );
		} );

		$( document ).on( 'click', '.umc-remove-currency', function ( event ) {
			var message = $( this ).data( 'confirm' );

			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );

		$( document ).on( 'click', '.umc-add-currency__submit', function ( event ) {
			var code = $( '#umc-add-currency-code' ).val();
			var addUrl = $( this ).data( 'add-url' );

			event.preventDefault();

			if ( ! code || ! addUrl ) {
				return;
			}

			window.location.href =
				addUrl + ( addUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'code=' + encodeURIComponent( code );
		} );

		if ( $.fn.selectWoo ) {
			$( '.umc-add-currency__select' ).selectWoo( {
				width: '360px',
				allowClear: true,
			} );
		} else if ( $.fn.select2 ) {
			$( '.umc-add-currency__select' ).select2( {
				width: '360px',
				allowClear: true,
			} );
		}
	} );
}( jQuery ) );
