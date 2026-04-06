( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.editPost.PluginSidebar ) {
		return;
	}
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var SelectControl = wp.components.SelectControl;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var apiFetch = wp.apiFetch;

	function optionsFromPresets( presets ) {
		var out = [];
		Object.keys( presets || {} ).forEach( function ( k ) {
			out.push( { label: presets[ k ], value: k } );
		} );
		return out;
	}

	function DesignPresetPanel() {
		var data = window.lfDesignPresetData || {};
		var presets = data.presets || {};
		var initial = data.current || '';
		var stateVal = useState( initial );
		var value = stateVal[ 0 ];
		var setValue = stateVal[ 1 ];
		var stateMsg = useState( null );
		var message = stateMsg[ 0 ];
		var setMessage = stateMsg[ 1 ];
		var stateErr = useState( null );
		var error = stateErr[ 0 ];
		var setError = stateErr[ 1 ];
		var stateBusy = useState( false );
		var busy = stateBusy[ 0 ];
		var setBusy = stateBusy[ 1 ];

		var options = optionsFromPresets( presets );

		function save() {
			setBusy( true );
			setMessage( null );
			setError( null );
			apiFetch( {
				path: '/leadsforward/v1/design-preset',
				method: 'POST',
				data: { preset: value },
			} )
				.then( function ( res ) {
					setMessage( ( res && res.message ) || '' );
				} )
				.catch( function ( err ) {
					var m =
						err && err.message
							? err.message
							: 'Save failed.';
					setError( m );
				} )
				.finally( function () {
					setBusy( false );
				} );
		}

		return el(
			Fragment,
			null,
			error
				? el( Notice, { status: 'error', isDismissible: false }, error )
				: null,
			message
				? el( Notice, { status: 'success', isDismissible: false }, message )
				: null,
			el( SelectControl, {
				label: data.strings && data.strings.label,
				help: data.strings && data.strings.help,
				value: value,
				options: options,
				onChange: setValue,
			} ),
			el(
				Button,
				{
					isPrimary: true,
					onClick: save,
					disabled: busy || options.length === 0,
				},
				busy
					? ( data.strings && data.strings.saving ) || '…'
					: ( data.strings && data.strings.apply ) || 'Apply'
			)
		);
	}

	registerPlugin( 'lf-design-preset', {
		icon: 'admin-appearance',
		render: function () {
			var data = window.lfDesignPresetData || {};
			var sidebarTitle =
				( data.strings && data.strings.sidebarTitle ) || 'LeadsForward';
			var menuLabel =
				( data.strings && data.strings.menuLabel ) || 'LeadsForward design';

			return el(
				Fragment,
				null,
				el( PluginSidebarMoreMenuItem, {
					target: 'lf-design-preset-sidebar',
				}, menuLabel ),
				el(
					PluginSidebar,
					{
						name: 'lf-design-preset-sidebar',
						title: sidebarTitle,
						icon: 'admin-appearance',
					},
					el( DesignPresetPanel, null )
				)
			);
		},
	} );
} )( window.wp );
