( function ( $ ) {
	'use strict';

	var CONTENT_ELEMENTS = [ 'code', 'symbol', 'name' ];

	var PRESETS = [ 'default', 'minimal', 'pill', 'compact', 'borderless', 'floating' ];

	var OVERRIDE_PROPERTIES = {
		surface: '--umc-switcher-surface',
		text: '--umc-switcher-text',
		border: '--umc-switcher-border',
		hover: '--umc-switcher-hover',
		selected_bg: '--umc-switcher-selected-bg',
		focus_ring: '--umc-switcher-focus-ring',
		radius: '--umc-switcher-radius',
		control_height: '--umc-switcher-control-height',
		spacing: '--umc-switcher-spacing',
		font_weight: '--umc-switcher-font-weight',
	};

	var OVERRIDE_DIMENSIONS = [ 'radius', 'control_height', 'spacing' ];

	var MOTION_DURATIONS = {
		none: '0ms',
		subtle: '150ms',
	};

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

	function displaySubnavModule() {
		var $root = $( '.umc-display-settings' );
		var $nav = $root.find( '[data-umc-display-subnav]' );
		var $pills = $nav.find( '[data-umc-display-tab]' );
		var $panels = $root.find( '[data-umc-display-panel]' );

		if ( ! $pills.length || ! $panels.length ) {
			return;
		}

		function activate( key ) {
			$pills.each( function () {
				var $pill = $( this );
				var active = String( $pill.data( 'umc-display-tab' ) ) === key;

				$pill.toggleClass( 'is-active', active ).attr( 'aria-pressed', active ? 'true' : 'false' );
			} );

			$panels.each( function () {
				var $panel = $( this );

				$panel.toggleClass(
					'umc-display-panel--hidden',
					String( $panel.data( 'umc-display-panel' ) ) !== key
				);
			} );
		}

		$nav.prop( 'hidden', false );

		$nav.on( 'click', '[data-umc-display-tab]', function ( event ) {
			event.preventDefault();
			activate( String( $( this ).data( 'umc-display-tab' ) ) );
		} );

		activate( String( $pills.first().data( 'umc-display-tab' ) ) );
	}

	function displayPreviewModule() {
		var $root = $( '.umc-display-settings' );

		if ( ! $root.length ) {
			return;
		}

		var config = window.umcDisplayPreview || {};
		var samples = config.samples || [];
		var elements = ( config.elements && config.elements.length ) ? config.elements : CONTENT_ELEMENTS;
		var presets = ( config.presets && config.presets.length ) ? config.presets : PRESETS;
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

		function elementOrder( context ) {
			var order = String( fieldValue( context + '_order' ) || '' )
				.split( ',' )
				.map( function ( part ) {
					return part.replace( /^\s+|\s+$/g, '' );
				} )
				.filter( function ( part ) {
					return elements.indexOf( part ) !== -1;
				} );

			elements.forEach( function ( element ) {
				if ( order.indexOf( element ) === -1 ) {
					order.push( element );
				}
			} );

			return order;
		}

		function visibleElements( context ) {
			var visible = elementOrder( context ).filter( function ( element ) {
				return !! fieldValue( context + '_show_' + element );
			} );

			return visible.length ? visible : [ 'code' ];
		}

		function renderElements( $host, sample, context ) {
			var rendered = 0;

			$host.empty();

			visibleElements( context ).forEach( function ( element ) {
				if ( ! sample[ element ] ) {
					return;
				}

				$( '<span/>' )
					.addClass( 'umc-switcher__' + element )
					.text( sample[ element ] )
					.appendTo( $host );

				rendered++;
			} );

			if ( ! rendered ) {
				$( '<span/>' ).addClass( 'umc-switcher__code' ).text( sample.code ).appendTo( $host );
			}
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

		function updateChevron( $trigger ) {
			if ( ! $trigger.length ) {
				return;
			}

			var enabled = !! fieldValue( 'show_chevron' );
			var $chevron = $trigger.find( '.umc-switcher__chevron' );

			if ( ! enabled ) {
				$chevron.remove();
				return;
			}

			if ( ! $chevron.length ) {
				$( '<span/>' )
					.addClass( 'umc-switcher__chevron' )
					.attr( 'aria-hidden', 'true' )
					.appendTo( $trigger );
			}
		}

		function updateLabels() {
			var $trigger = $switcher.find( '.umc-switcher__trigger' ).first();
			var $triggerContent = $trigger.find( '.umc-switcher__trigger-content' ).first();
			var $menu = $switcher.find( '.umc-switcher__menu' ).first();
			var $links = $switcher.find( '.umc-switcher__link' );
			var isDropdown = 'dropdown' === styleValue();

			if ( $triggerContent.length && samples.length ) {
				renderElements( $triggerContent, samples[ 0 ], 'trigger' );
			}

			samples.forEach( function ( sample, index ) {
				var $link = $links.eq( index );

				if ( $link.length ) {
					renderElements( $link, sample, 'menu' );
				}
			} );

			updateChevron( $trigger );

			// The preview keeps the dropdown open so menu composition stays visible.
			$switcher.toggleClass( 'umc-switcher--preview-show-names', isDropdown );

			if ( $menu.length ) {
				$menu.prop( 'hidden', ! isDropdown );
			}

			if ( $trigger.length ) {
				$trigger.attr( 'aria-expanded', isDropdown ? 'true' : 'false' );
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

		function updateResponsiveClasses() {
			$switcher.toggleClass( 'umc-switcher--hide-name-on-mobile', !! fieldValue( 'hide_name_on_mobile' ) );
			$switcher.toggleClass( 'umc-switcher--compact-on-mobile', !! fieldValue( 'compact_on_mobile' ) );
		}

		function updateOverrides() {
			Object.keys( OVERRIDE_PROPERTIES ).forEach( function ( token ) {
				var property = OVERRIDE_PROPERTIES[ token ];
				var raw = fieldValue( 'override_' + token );
				var value = ( null === raw || undefined === raw ) ? '' : String( raw ).replace( /^\s+|\s+$/g, '' );

				if ( '' === value ) {
					$switcher.css( property, '' );
					return;
				}

				if ( OVERRIDE_DIMENSIONS.indexOf( token ) !== -1 ) {
					if ( ! /^\d+$/.test( value ) ) {
						$switcher.css( property, '' );
						return;
					}

					value += 'px';
				}

				$switcher.css( property, value );
			} );
		}

		function updateMotion() {
			var motion = fieldValue( 'motion' ) || 'subtle';

			$switcher.css(
				'--umc-switcher-transition-duration',
				MOTION_DURATIONS[ motion ] || MOTION_DURATIONS.subtle
			);
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

			var preset = fieldValue( 'preset' ) || 'default';

			setExclusiveClass(
				presets.map( function ( token ) {
					return 'umc-switcher--preset-' + token;
				} ),
				'umc-switcher--preset-' + ( presets.indexOf( preset ) === -1 ? 'default' : preset )
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
			updateOverrides();
			updateMotion();
			updateVisibilityClasses();
			updateResponsiveClasses();
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

			window.onbeforeunload = '';
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

		displaySubnavModule();
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
