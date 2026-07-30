/* global jQuery, umcGeoRules */
( function ( $ ) {
	'use strict';

	function root() {
		return $( '[data-umc-geo-root]' );
	}

	function list() {
		return root().find( '[data-umc-geo-rules]' );
	}

	function announce( message ) {
		root().find( '[data-umc-geo-live]' ).text( message );
	}

	function renumber() {
		list().children( '[data-umc-geo-rule]' ).each( function ( index ) {
			var $row = $( this );
			$row.find( '.umc-geo-rule__position' ).text( String( index + 1 ) );
			$row.find( '[data-umc-geo-move="up"]' ).prop( 'disabled', index === 0 );
			$row.find( '[data-umc-geo-move="down"]' ).prop(
				'disabled',
				index === list().children().length - 1
			);
		} );
	}

	function moveRow( $row, direction ) {
		if ( 'up' === direction ) {
			$row.prev().before( $row );
		} else {
			$row.next().after( $row );
		}
		renumber();
		var label = $row.find( '.umc-geo-rule__badge' ).text();
		var position = $row.index() + 1;
		if ( umcGeoRules && umcGeoRules.movedTemplate ) {
			announce( umcGeoRules.movedTemplate.replace( '%1$s', label ).replace( '%2$d', String( position ) ) );
		}
		$row.find( '[data-umc-geo-move="' + direction + '"]' ).trigger( 'focus' );
	}

	$( document ).on( 'click', '[data-umc-geo-move]', function ( event ) {
		event.preventDefault();
		moveRow( $( this ).closest( '[data-umc-geo-rule]' ), $( this ).data( 'umc-geo-move' ) );
	} );

	$( document ).on( 'click', '[data-umc-geo-remove]', function ( event ) {
		event.preventDefault();
		var $row = $( this ).closest( '[data-umc-geo-rule]' );
		var label = $row.find( '.umc-geo-rule__badge' ).text();
		$row.remove();
		renumber();
		if ( umcGeoRules && umcGeoRules.removedTemplate ) {
			announce( umcGeoRules.removedTemplate.replace( '%1$s', label ) );
		}
	} );

	$( document ).on( 'click', '[data-umc-geo-add]', function ( event ) {
		event.preventDefault();
		var type = $( this ).data( 'umc-geo-add' );
		var $template = $( '#umc-geo-rule-template' ).contents().clone();
		$template.find( '[data-umc-geo-type]' ).val( type );
		if ( 'other' === type ) {
			$template.find( '[data-umc-geo-type]' ).closest( 'li' ).find( '.umc-geo-rule__match select' ).remove();
		}
		list().append( $template );
		renumber();
	} );

	renumber();
}( jQuery ) );
