
export function extend_toolbar( toolbar, selector ) {
	return toolbar.extend( {
		set: function( id, view, options ) {
			if ( selector === id ) {
				view.click = get_click_handler( view );
			}

			return toolbar.prototype.set.apply( this, [ id, view, options ] );
		}
	} );
}

export function get_click_handler( item ) {
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
			alert( response[0].message ?? __( 'An unknown error occurred.', 'asset-manager-framework' ) );

			event.target.disabled = false;
		} );
	}
}

export function addProviderFilter() {
	// Short circuit if we don't have providers
	if ( ! AMF_DATA || ! AMF_DATA.hasOwnProperty( 'providers' ) ) {
		return;
	}

	// Override core styles that allow only two filter inputs
	addInlineStyle( '.media-modal-content .media-frame select.attachment-filters { width: 150px }' );

	// Create a new MediaLibraryProviderFilter we later will instantiate
	var MediaLibraryProviderFilter = wp.media.view.AttachmentFilters.extend({
		id: 'media-attachment-provider-filter',

		createFilters: function() {
			var filters = {};
			// Formats the 'providers' we've included via wp_localize_script()
			_.each( AMF_DATA.providers || {}, function( value, index ) {
				filters[ index ] = {
					text: value,
					props: {
						provider: index,
					}
				};
			});
			this.filters = filters;
		}
	});

	// Extend and override wp.media.view.AttachmentsBrowser to include our new filter
	var AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
		createToolbar: function() {
			// Make sure to load the original toolbar
			AttachmentsBrowser.prototype.createToolbar.call( this );
			this.toolbar.set( 'MediaLibraryProviderFilter', new MediaLibraryProviderFilter({
				controller: this.controller,
				model:      this.collection.props,
				priority: -75
			}).render() );
		}
	});
}

export function addInlineStyle( styles ) {
	var css = document.createElement('style');
	css.type = 'text/css';

	if ( css.styleSheet ) {
		css.styleSheet.cssText = styles;
	} else {
		css.appendChild( document.createTextNode( styles ) );
	}

	document.getElementsByTagName( 'head' )[0].appendChild( css );
}
