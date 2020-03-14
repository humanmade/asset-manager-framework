
function extend_toolbar( toolbar, selector ) {
	return toolbar.extend( {
		set: function( id, view, options ) {
			if ( selector === id ) {
				view.click = get_click_handler( view );
			}

			return toolbar.prototype.set.apply( this, [ id, view, options ] );
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
			console.log('=== failed ===');
			console.log(response);

			event.target.disabled = false;

			// @TODO call click_handler
		} );
	}
}

export {
	get_click_handler,
	extend_toolbar,
}
