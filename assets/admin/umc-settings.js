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

	function displayPreviewModule() {
		var $root = $( '.umc-display-settings' );

		if ( ! $root.length ) {
			return;
		}

		var config = window.umcDisplayPreview || {};
		var samples = config.samples || [];
		var $switcher = $root.find( '.umc-switcher' ).first();
		var $frame = $root.find( '[data-umc-preview-frame]' );

		if ( ! $switcher.length ) {
			return;
		}

		function fieldValue( name ) {
			var $fields = $root.find( '[data-umc-display-field="' + name + '"]' );

			if ( ! $fields.length ) {
				return null;
			}

			var $field = $fields.filter( ':enabled' ).first();

			if ( ! $field.length ) {
				$field = $fields.first();
			}

			if ( 'checkbox' === $field.attr( 'type' ) ) {
				return $field.is( ':checked' );
			}

			if ( 'radio' === $field.attr( 'type' ) ) {
				var $checked = $root.find( '[data-umc-display-field="' + name + '"]:enabled:checked' );

				if ( $checked.length ) {
					return $checked.val();
				}

				return $root.find( '[data-umc-display-field="' + name + '"]:checked' ).val();
			}

			return $field.val();
		}

		function placement() {
			return $root.find( 'input[name="umc_display[placement]"]:checked' ).val() || 'manual';
		}

		function styleValue() {
			var value = $root.find( 'input[name="umc_display[style]"]:checked' ).val() || 'dropdown';

			if ( 'manual' !== placement() ) {
				return 'dropdown';
			}

			return value;
		}

		function formatLabel( sample, compact ) {
			var showCode = !! fieldValue( 'content_show_code' );
			var showSymbol = !! fieldValue( 'content_show_symbol' );
			var showName = !! fieldValue( 'content_show_name' );
			var parts = [];

			if ( showCode ) {
				parts.push( sample.code );
			}

			if ( showSymbol && sample.symbol ) {
				parts.push( sample.symbol );
			}

			if ( ! parts.length ) {
				if ( ! compact && showName && sample.name ) {
					return sample.name;
				}

				parts.push( sample.code );
			}

			var primary = parts.join( ' ' );

			if ( ! compact && showName && sample.name ) {
				return primary + ' \u2014 ' + sample.name;
			}

			return primary;
		}

		function setExclusiveClass( group, className ) {
			var classes = ( $switcher.attr( 'class' ) || '' ).split( /\s+/ ).filter( Boolean );
			var next = classes.filter( function ( cls ) {
				return group.indexOf( cls ) === -1;
			} );

			if ( className ) {
				next.push( className );
			}

			$switcher.attr( 'class', next.join( ' ' ) );
		}

		function updatePreviewLayout() {
			var current = placement();
			var $canvas = $root.find( '[data-umc-preview-canvas]' );

			$canvas.toggleClass( 'umc-display-preview-frame__canvas--manual', 'manual' === current );
			$canvas.toggleClass( 'umc-display-preview-frame__canvas--floating-side', 'floating_side' === current );
			$canvas.toggleClass( 'umc-display-preview-frame__canvas--floating-bottom', 'sticky_footer' === current );
		}

		function panelKeyForPlacement( current ) {
			if ( 'floating_side' === current ) {
				return 'floating_side';
			}

			if ( 'sticky_footer' === current ) {
				return 'sticky_footer';
			}

			return null;
		}

		function setPanelControls( $panel, enabled ) {
			$panel.find( 'input, select, textarea, button' ).each( function () {
				$( this ).prop( 'disabled', ! enabled );
			} );
		}

		function updatePositionPanels() {
			var $card = $root.find( '[data-umc-position-card]' );
			var current = placement();
			var activeKey = panelKeyForPlacement( current );
			var isManual = 'manual' === current;

			$card.toggleClass( 'umc-display-card--hidden', isManual );

			$root.find( '[data-umc-position-panel]' ).each( function () {
				var $panel = $( this );
				var isActive = ! isManual && $panel.data( 'umc-position-panel' ) === activeKey;

				$panel.toggleClass( 'umc-display-panel--hidden', ! isActive );
				setPanelControls( $panel, isActive );
			} );
		}

		function updateManualPanel() {
			var current = placement();

			$root.find( '[data-umc-manual-panel]' ).toggleClass( 'umc-display-panel--hidden', 'manual' !== current );
		}

		function updateEnableStatus() {
			var enabled = !! fieldValue( 'enabled' );
			var $status = $root.find( '[data-umc-display-status]' );

			$root.toggleClass( 'umc-display-configurator--switcher-off', ! enabled );
			$status.toggleClass( 'is-on', enabled ).toggleClass( 'is-off', ! enabled );
			$status.text( enabled ? ( config.statusOn || 'On' ) : ( config.statusOff || 'Off' ) );
		}

		function updateDisabledPreviewOverlay() {
			var enabled = !! fieldValue( 'enabled' );
			var $overlay = $root.find( '[data-umc-preview-disabled-overlay]' );

			if ( $overlay.length ) {
				$overlay.prop( 'hidden', enabled );
			}
		}

		function updateStyleControls() {
			var auto = 'manual' !== placement();
			var $horizontal = $root.find( 'input[name="umc_display[style]"][value="horizontal_list"]' );
			var $horizontalCard = $horizontal.closest( '.umc-display-choice-card' );

			$horizontal.prop( 'disabled', auto );
			$horizontalCard.toggleClass( 'umc-display-choice-card--disabled', auto );

			if ( auto && $horizontal.is( ':checked' ) ) {
				$root.find( 'input[name="umc_display[style]"][value="dropdown"]' ).prop( 'checked', true );
			}
		}

		function updateLabels() {
			var $trigger = $switcher.find( '.umc-switcher__trigger' ).first();
			var $menu = $switcher.find( '.umc-switcher__menu' ).first();
			var $links = $switcher.find( '.umc-switcher__link' );
			var showName = !! fieldValue( 'content_show_name' );
			var isDropdown = 'dropdown' === styleValue();

			samples.forEach( function ( sample, index ) {
				var label = formatLabel( sample, false );
				var compact = formatLabel( sample, true );

				if ( 0 === index && $trigger.length ) {
					$trigger.text( compact );
				}

				if ( $links.eq( index ).length ) {
					$links.eq( index ).text( label );
				}
			} );

			$switcher.toggleClass( 'umc-switcher--preview-show-names', showName && isDropdown );

			if ( $menu.length ) {
				$menu.prop( 'hidden', ! ( showName && isDropdown ) );
			}

			if ( $trigger.length ) {
				$trigger.attr( 'aria-expanded', showName && isDropdown ? 'true' : 'false' );
			}
		}

		function updateOrder() {
			if ( ! fieldValue( 'active_first' ) ) {
				return;
			}

			var $menu = $switcher.find( '.umc-switcher__menu, .umc-switcher__list' ).first();
			var $active = $menu.find( '.is-active, [aria-current="true"]' ).closest( 'li' );

			if ( $active.length ) {
				$active.prependTo( $menu );
			}
		}

		function updateVisibilityClasses() {
			$switcher.toggleClass( 'umc-switcher--hide-desktop', ! fieldValue( 'visibility_desktop' ) );
			$switcher.toggleClass( 'umc-switcher--hide-mobile', ! fieldValue( 'visibility_mobile' ) );
		}

		function updateCssVariables() {
			var edge = fieldValue( 'edge_offset' );
			var vertical = fieldValue( 'vertical_offset' );
			var bottom = fieldValue( 'bottom_offset' );

			if ( edge !== undefined && edge !== '' ) {
				$switcher.css( '--umc-edge-offset', edge + 'px' );
			}

			if ( vertical !== undefined && vertical !== '' ) {
				$switcher.css( '--umc-vertical-offset', vertical + 'px' );
			}

			if ( bottom !== undefined && bottom !== '' ) {
				$switcher.css( '--umc-bottom-offset', bottom + 'px' );
			}
		}

		function updatePresentationClasses() {
			var style = styleValue();
			var currentPlacement = placement();

			setExclusiveClass(
				[ 'umc-switcher--dropdown', 'umc-switcher--horizontal-list' ],
				'horizontal_list' === style ? 'umc-switcher--horizontal-list' : 'umc-switcher--dropdown'
			);

			setExclusiveClass(
				[ 'umc-switcher--manual', 'umc-switcher--floating-side', 'umc-switcher--floating-bottom' ],
				{
					manual: 'umc-switcher--manual',
					floating_side: 'umc-switcher--floating-side',
					sticky_footer: 'umc-switcher--floating-bottom',
				}[ currentPlacement ] || 'umc-switcher--manual'
			);

			setExclusiveClass(
				[ 'umc-switcher--side-left', 'umc-switcher--side-right' ],
				'left' === ( fieldValue( 'side' ) || 'right' )
					? 'umc-switcher--side-left'
					: 'umc-switcher--side-right'
			);

			setExclusiveClass(
				[ 'umc-switcher--align-top', 'umc-switcher--align-middle', 'umc-switcher--align-bottom' ],
				{
					top: 'umc-switcher--align-top',
					middle: 'umc-switcher--align-middle',
					bottom: 'umc-switcher--align-bottom',
				}[ fieldValue( 'vertical_alignment' ) || 'middle' ] || 'umc-switcher--align-middle'
			);

			setExclusiveClass(
				[ 'umc-switcher--theme-automatic', 'umc-switcher--theme-light', 'umc-switcher--theme-dark' ],
				{
					automatic: 'umc-switcher--theme-automatic',
					light: 'umc-switcher--theme-light',
					dark: 'umc-switcher--theme-dark',
				}[ fieldValue( 'theme' ) || 'automatic' ] || 'umc-switcher--theme-automatic'
			);

			setExclusiveClass(
				[ 'umc-switcher--size-compact', 'umc-switcher--size-standard', 'umc-switcher--size-large' ],
				{
					compact: 'umc-switcher--size-compact',
					standard: 'umc-switcher--size-standard',
					large: 'umc-switcher--size-large',
				}[ fieldValue( 'size' ) || 'standard' ] || 'umc-switcher--size-standard'
			);

			setExclusiveClass(
				[ 'umc-switcher--shape-slight', 'umc-switcher--shape-rounded', 'umc-switcher--shape-pill' ],
				{
					slight: 'umc-switcher--shape-slight',
					rounded: 'umc-switcher--shape-rounded',
					pill: 'umc-switcher--shape-pill',
				}[ fieldValue( 'shape' ) || 'rounded' ] || 'umc-switcher--shape-rounded'
			);

			$switcher.toggleClass( 'umc-switcher--expanded', 'horizontal_list' === style );
		}

		function refreshPreview() {
			updateStyleControls();
			updatePositionPanels();
			updateManualPanel();
			updateEnableStatus();
			updatePresentationClasses();
			updatePreviewLayout();
			updateCssVariables();
			updateVisibilityClasses();
			updateDisabledPreviewOverlay();
			updateLabels();
			updateOrder();
			checkDirty();
		}

		function serializeDisplayState() {
			var state = {};
			var $fields = $root.find( '.umc-display-configurator' ).find( 'input, select, textarea' );

			$fields.each( function () {
				var $el = $( this );
				var name = $el.attr( 'name' );

				if ( ! name ) {
					return;
				}

				var type = ( $el.attr( 'type' ) || '' ).toLowerCase();

				if ( 'checkbox' === type ) {
					state[ name ] = $el.is( ':checked' ) ? '1' : '0';
					return;
				}

				if ( 'radio' === type ) {
					if ( $el.is( ':checked' ) ) {
						state[ name ] = String( $el.val() );
					} else if ( ! Object.prototype.hasOwnProperty.call( state, name ) ) {
						state[ name ] = '';
					}
					return;
				}

				if ( 'hidden' === type && $root.find( '[name="' + name.replace( /"/g, '\\"' ) + '"][type="checkbox"]' ).length ) {
					return;
				}

				state[ name ] = String( $el.val() );
			} );

			return JSON.stringify( state, Object.keys( state ).sort() );
		}

		var initialSnapshot = serializeDisplayState();

			function checkDirty() {
				var $indicator = $( '[data-umc-unsaved-indicator]' );
				var $bar = $( '[data-umc-display-actions]' );

				if ( ! $indicator.length ) {
					return;
				}

				var dirty = serializeDisplayState() !== initialSnapshot;
				$indicator.prop( 'hidden', ! dirty );

				if ( $bar.length ) {
					$bar.prop( 'hidden', ! dirty );
				}
			}

		function copyShortcode() {
			var text = $root.find( '[data-umc-shortcode-text]' ).text();

			if ( ! text ) {
				return;
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( function () {
					window.alert( config.copySuccess || 'Shortcode copied.' );
				} ).catch( function () {
					window.alert( config.copyFailed || 'Could not copy shortcode.' );
				} );
				return;
			}

			window.prompt( config.copyPrompt || 'Copy shortcode:', text );
		}

		$root.on( 'change input', '[data-umc-display-field], input[name="umc_display[placement]"], input[name="umc_display[style]"]', refreshPreview );

		$root.on( 'click', '.umc-display-preview__viewport-btn', function ( event ) {
			event.preventDefault();

			var mode = $( this ).data( 'umc-preview-viewport' );

			$root.find( '.umc-display-preview__viewport-btn' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
			$frame.toggleClass( 'umc-display-preview-frame--mobile', 'mobile' === mode );
		} );

		$root.on( 'click', '.umc-switcher__link', function ( event ) {
			event.preventDefault();
		} );

		$root.on( 'click', '[data-umc-copy-shortcode]', function ( event ) {
			event.preventDefault();
			copyShortcode();
		} );

		$root.closest( 'form' ).on( 'submit', function () {
			window.onbeforeunload = '';
		} );

		$( '[data-umc-display-actions]' ).on( 'click', 'button[type="submit"]', function () {
			window.onbeforeunload = '';
		} );

		refreshPreview();
		checkDirty();
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

		displayPreviewModule();
		stickySaveModule();
	} );

	function stickySaveModule() {
		$( '[data-umc-sticky-root]' ).each( function () {
			var $scope = $( this );
			var scopeId = $scope.data( 'umc-sticky-root' );
			var $bar = $( '[data-umc-sticky-save][data-umc-sticky-scope="' + scopeId + '"]' );

			if ( ! $bar.length ) {
				$bar = $scope.find( '[data-umc-sticky-save]' ).first();
			}

			if ( ! $bar.length ) {
				return;
			}

			function serializeScope() {
				var state = {};

				$scope.find( 'input, select, textarea' ).each( function () {
					var $el = $( this );
					var name = $el.attr( 'name' );

					if ( ! name || $el.is( '[data-umc-display-field]' ) ) {
						return;
					}

					var type = ( $el.attr( 'type' ) || '' ).toLowerCase();

					if ( 'checkbox' === type ) {
						state[ name ] = $el.is( ':checked' ) ? '1' : '0';
						return;
					}

					if ( 'radio' === type ) {
						if ( $el.is( ':checked' ) ) {
							state[ name ] = String( $el.val() );
						} else if ( ! Object.prototype.hasOwnProperty.call( state, name ) ) {
							state[ name ] = '';
						}
						return;
					}

					if ( 'hidden' === type && $scope.find( '[name="' + name.replace( /"/g, '\\"' ) + '"][type="checkbox"]' ).length ) {
						return;
					}

					state[ name ] = String( $el.val() );
				} );

				return JSON.stringify( state, Object.keys( state ).sort() );
			}

			var initialSnapshot = serializeScope();

			function setDirtyState( dirty ) {
				$bar.prop( 'hidden', false );
				$bar.find( '[data-umc-unsaved-indicator]' ).prop( 'hidden', ! dirty );
				$bar.find( '[data-umc-sticky-discard]' ).prop( 'hidden', ! dirty );
				$bar.find( '[data-umc-sticky-saved]' ).prop( 'hidden', true );
			}

			function checkDirty() {
				setDirtyState( serializeScope() !== initialSnapshot );
			}

			setDirtyState( false );

			$scope.on( 'change input', 'input, select, textarea', checkDirty );

			$bar.find( '[data-umc-sticky-discard]' ).on( 'click', function ( event ) {
				event.preventDefault();
				window.location.reload();
			} );

			$bar.on( 'click', 'button[type="submit"]', function () {
				window.onbeforeunload = '';
				$bar.find( '[data-umc-sticky-saved]' ).prop( 'hidden', false );
			} );
		} );
	}
}( jQuery ) );
