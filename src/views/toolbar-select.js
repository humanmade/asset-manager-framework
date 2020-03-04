const { Select } = wp.media.view.Toolbar;

const AMFToolbarSelect = Select.extend( {
	initialize: function () {
		Select.prototype.initialize.apply( this, arguments );

		if ( this.options.items && this.options.items.select ) {
			const click_handler = this.options.items.select.click;

			this.options.items.select.click = () => {
				const selection_state = this.controller.state().get('selection');
				const selection = selection_state.first().toJSON();
				const cid = selection_state.single().cid;

				if ( selection.attachmentExists ) {
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
					let model = wp.media.model.Attachments.all._byId[ cid ];

					model.set( 'id', response.ID );

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
