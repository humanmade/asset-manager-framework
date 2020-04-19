
/**
 * @param {wp.media.view.Toolbar} toolbar
 * @param {string} selector
 * @return {wp.media.view.Toolbar}
 */
function extend_toolbar( toolbar, selector ) {
	return toolbar.extend( {
		/**
		 * @param {string|Object} id
		 * @param {Backbone.View|Object} view
		 * @param {Object} [options={}]
		 * @return {wp.media.view.Toolbar} Returns itself to allow chaining.
		 */
		set: function( id, view, options ) {
			if ( selector === id ) {
				view.click = get_click_handler( view );
			}

			return toolbar.prototype.set.apply( this, arguments );
		}
	} );
}

function get_click_handler( item ) {
	let click_handler = item.click;
	const attribute = ( item.requires.library ? 'library' : 'selection' );

	return function( event ) {
		const selection_state = this.controller.state().get( attribute );
		const new_attachments = selection_state.models.filter(model => ! model.attributes.attachmentExists);

		click_handler = _.bind( click_handler, this );

		if ( ! new_attachments.length ) {
			click_handler();
			return;
		}

		event.target.disabled = true;

		wp.ajax.post(
			'amf-select',
			{
				'selection' : selection_state.toJSON(),
				'post' : wp.media.view.settings.post.id,
			}
		).done( response => {
			Object.keys(response).forEach( key => {
				selection_state.get( key ).set( 'id', response[ key ] );
			});

			event.target.disabled = false;

			click_handler();
		} ).fail( response => {
			let message = ( 'An unknown error occurred.' );

			if ( response && response[0] && response[0].message ) {
				message = response[0].message;
			}

			alert( message );

			event.target.disabled = false;
		} );
	}
}

export {
	get_click_handler,
	extend_toolbar,
}
