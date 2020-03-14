/**
 * Overrides for the wp.media library.
 */

import { extend_toolbar } from './functions';

wp.media.view.Toolbar = extend_toolbar( wp.media.view.Toolbar, 'insert' );
wp.media.view.Toolbar.Select = extend_toolbar( wp.media.view.Toolbar.Select, 'select' );
