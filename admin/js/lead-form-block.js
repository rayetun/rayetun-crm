/**
 * RayEtun CRM — native lead-form block (editor script).
 *
 * A dynamic block: the front-end output is rendered in PHP (Forms::render_block),
 * so save() returns null. The editor shows a live server-rendered preview via
 * ServerSideRender, and the Inspector lets the user customise the title, button,
 * tags, source and which optional fields appear. Written against the global `wp`
 * runtime with createElement (no JSX/build step).
 */
( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'rayetun-crm/lead-form', {
		apiVersion: 2,
		title: __( 'RayEtun CRM Lead Form', 'rayetun-crm' ),
		description: __( 'A lead-capture form that creates a contact in RayEtun CRM on submit.', 'rayetun-crm' ),
		icon: 'feedback',
		category: 'widgets',
		keywords: [ 'crm', 'lead', 'contact', 'form' ],
		attributes: {
			title: { type: 'string', default: 'Get in touch' },
			button: { type: 'string', default: 'Send' },
			tags: { type: 'string', default: '' },
			source: { type: 'string', default: '' },
			show_last_name: { type: 'boolean', default: true },
			show_phone: { type: 'boolean', default: true },
			show_message: { type: 'boolean', default: true }
		},
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Form settings', 'rayetun-crm' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Title', 'rayetun-crm' ),
							value: a.title,
							onChange: function ( v ) { set( { title: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Button label', 'rayetun-crm' ),
							value: a.button,
							onChange: function ( v ) { set( { button: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Tags (comma separated)', 'rayetun-crm' ),
							help: __( 'Applied to every contact captured by this form.', 'rayetun-crm' ),
							value: a.tags,
							onChange: function ( v ) { set( { tags: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Source label', 'rayetun-crm' ),
							value: a.source,
							onChange: function ( v ) { set( { source: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Fields', 'rayetun-crm' ), initialOpen: true },
						el( 'p', { style: { color: '#757575', fontSize: '12px', marginTop: 0 } }, __( 'First name and email are always shown. Email is required.', 'rayetun-crm' ) ),
						el( ToggleControl, {
							label: __( 'Show last name', 'rayetun-crm' ),
							checked: a.show_last_name,
							onChange: function ( v ) { set( { show_last_name: v } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Show phone', 'rayetun-crm' ),
							checked: a.show_phone,
							onChange: function ( v ) { set( { show_phone: v } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Show message', 'rayetun-crm' ),
							checked: a.show_message,
							onChange: function ( v ) { set( { show_message: v } ); }
						} )
					)
				),
				el( ServerSideRender, {
					block: 'rayetun-crm/lead-form',
					attributes: a
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.serverSideRender );
