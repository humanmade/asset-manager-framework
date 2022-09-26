/**
 * Overrides for the wp.media library.
 */

import { extend_toolbar, addProviderFilter } from './functions';

( function() {
	wp.media.view.Toolbar = extend_toolbar( wp.media.view.Toolbar, 'insert' );
	wp.media.view.Toolbar.Select = extend_toolbar( wp.media.view.Toolbar.Select, 'select' );

	// Add a hook to let other libraries extend the toolbar.
	wp.hooks.doAction( 'amf.extend_toolbar', extend_toolbar );

	addProviderFilter();
} )();
