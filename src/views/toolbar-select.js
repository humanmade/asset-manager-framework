import { get_click_handler } from '../functions';

const { Select } = wp.media.view.Toolbar;

const AMFToolbarSelect = Select.extend( {
	set: function( id, view, options ) {
		if ( 'select' === id ) {
			view.click = get_click_handler( view );
		}

		return Select.prototype.set.apply( this, [ id, view, options ] );
	}
} );

export default AMFToolbarSelect;
