/**
 * Overrides for the wp.media library.
 */

import { extend_toolbar, addProviderFilter } from './functions';

(function(){
	wp.media.view.Toolbar = extend_toolbar( wp.media.view.Toolbar, 'insert' );
	wp.media.view.Toolbar.Select = extend_toolbar( wp.media.view.Toolbar.Select, 'select' );

	// Support for Smart Media.
	wp.media.view.Toolbar = extend_toolbar( wp.media.view.Toolbar, 'apply' );

	addProviderFilter();
})();
