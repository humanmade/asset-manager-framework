const { Select } = wp.media.view.Toolbar;

const AMFToolbarSelect = Select.extend( {
	initialize: function () {
		Select.prototype.initialize.apply( this, arguments );

		if ( this.options.items && this.options.items.select ) {
			const click_handler = this.options.items.select.click;
			const attribute = ( this.options.items.select.requires.library ? 'library' : 'selection' );

			this.options.items.select.click = () => {
				const selection_state = this.controller.state().get( attribute );
				const selection = selection_state.toJSON();
				const new_attachments = selection_state.models.filter(model => ! model.attributes.attachmentExists);

				click_handler = _.bind( click_handler, this );

				if ( ! new_attachments.length ) {
					click_handler();
					return;
				}

				let request = wp.ajax.post(
					'amf-select',
					{
						'selection' : selection,
						'post' : wp.media.view.settings.post.id,
					}
				);

				request.done((response)=>{
					Object.keys(response).forEach( key => {
						selection_state.get( key ).set( 'id', response[ key ].ID );
					});

					click_handler();
				});

				request.fail(()=>{
					console.log('=== failed ===');
					// @TODO call click_handler
				});

			};
		}
	}
} );

export default AMFToolbarSelect;
