/**
 * Currency Switcher block editor script.
 *
 * @package UniversalMulticurrency
 */

( function ( blocks, blockEditor, components, serverSideRender, element, i18n ) {
	'use strict';

	if ( ! blocks || ! blockEditor || ! serverSideRender || ! element ) {
		return;
	}

	var registerBlockType = blocks.registerBlockType;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var ExternalLink = components.ExternalLink;
	var ServerSideRender = serverSideRender;
	var createElement = element.createElement;
	var __ = i18n.__;
	var blockName = 'universal-multicurrency/currency-switcher';
	var displaySettingsUrl =
		( window.umcSwitcherBlock && window.umcSwitcherBlock.displaySettingsUrl ) ||
		'';

	registerBlockType( blockName, {
		edit: function Edit( props ) {
			var blockProps = useBlockProps();

			return createElement(
				'div',
				blockProps,
				createElement( ServerSideRender, {
					block: blockName,
					attributes: props.attributes,
				} ),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: __( 'Switcher settings', 'universal-multicurrency' ) },
						createElement(
							'p',
							null,
							__(
								'Switcher appearance is configured globally in WooCommerce → Settings → Multicurrency → Display.',
								'universal-multicurrency'
							)
						),
						displaySettingsUrl
							? createElement(
									ExternalLink,
									{ href: displaySettingsUrl },
									__( 'Open Display settings', 'universal-multicurrency' )
							  )
							: null
					)
				)
			);
		},
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.element,
	window.wp.i18n
);
