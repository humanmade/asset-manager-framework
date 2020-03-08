/**
 * Overrides for the wp.media library.
 */

import AMFToolbarSelect from './views/toolbar-select';
import AMFToolbar from './views/toolbar';

wp.media.view.Toolbar = AMFToolbar;
wp.media.view.Toolbar.Select = AMFToolbarSelect;
