import { get_click_handler } from '../functions';

const { Select } = wp.media.view.Toolbar;

const AMFToolbarSelect = Select.extend( {
	initialize: function () {
		Select.prototype.initialize.apply( this, arguments );

		if ( this.options.items && this.options.items.select ) {
			let handler = get_click_handler( this.options.items.select );
			this.options.items.select.click = handler;
		}
	}
} );

export default AMFToolbarSelect;
