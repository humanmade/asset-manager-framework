import { get_click_handler } from '../functions';

const { Toolbar } = wp.media.view;

const AMFToolbar = Toolbar.extend( {
	initialize: function () {
		Toolbar.prototype.initialize.apply( this, arguments );

		if ( this.options.items && this.options.items.insert ) {
			let handler = get_click_handler( this.options.items.insert );
			this.options.items.insert.click = handler;
		}
	}
} );

export default AMFToolbar;
