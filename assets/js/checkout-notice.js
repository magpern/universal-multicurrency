( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.data ) {
		return;
	}

	var select = window.wp.data.select;
	var dispatch = window.wp.data.dispatch;
	var lastRenderedSignature = '';
	var scheduled = false;

	function getNoticePayload() {
		var cartStore = select( 'wc/store/cart' );

		if ( ! cartStore || typeof cartStore.getCartData !== 'function' ) {
			return null;
		}

		var cartData = cartStore.getCartData();

		if ( ! cartData || ! cartData.extensions || ! cartData.extensions.umc ) {
			return null;
		}

		return cartData.extensions.umc.checkout_notice || null;
	}

	function removeRenderedNotice() {
		if ( ! lastRenderedSignature ) {
			return;
		}

		dispatch( 'core/notices' ).removeNotice( 'umc-checkout-' + lastRenderedSignature );
	}

	function syncNotice() {
		scheduled = false;
		var payload = getNoticePayload();

		if ( ! payload || ! payload.show ) {
			removeRenderedNotice();
			lastRenderedSignature = '';
			return;
		}

		if ( ! payload.signature || ! payload.message ) {
			return;
		}

		if ( payload.signature === lastRenderedSignature ) {
			return;
		}

		removeRenderedNotice();

		dispatch( 'core/notices' ).createNotice(
			payload.status || 'info',
			payload.message,
			{
				id: 'umc-checkout-' + payload.signature,
				context: 'wc/checkout',
				isDismissible: true
			}
		);

		lastRenderedSignature = payload.signature;
	}

	function scheduleSync() {
		if ( scheduled ) {
			return;
		}

		scheduled = true;
		window.queueMicrotask( syncNotice );
	}

	var unsubscribe = window.wp.data.subscribe( scheduleSync );

	if ( typeof unsubscribe !== 'function' ) {
		scheduleSync();
	} else {
		scheduleSync();
	}
}() );
