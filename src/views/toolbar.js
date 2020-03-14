import { get_click_handler } from '../functions';

const { Toolbar } = wp.media.view;

const AMFToolbar = Toolbar.extend( {
	set: function( id, view, options ) {
		if ( 'insert' === id ) {
			view.click = get_click_handler( view );
		}

		return Toolbar.prototype.set.apply( this, [ id, view, options ] );
	}
} );

export default AMFToolbar;
