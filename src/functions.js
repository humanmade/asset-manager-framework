
/**
 * @param {wp.media.view.Toolbar} toolbar
 * @param {string} selector
 * @return {wp.media.view.Toolbar}
 */
export function extend_toolbar( toolbar, selector ) {
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

export function get_click_handler( item ) {
	let click_handler = item.click;
	const attribute = ( item.requires.library ? 'library' : 'selection' );

	return function( event ) {
		const state = this.controller.state();
		const selection_state = state.get( attribute );
		const new_attachments = selection_state.models.filter(model => ! model.attributes.attachmentExists);

		click_handler = _.bind( click_handler, this );

		if ( ! new_attachments.length ) {
			click_handler();
			return;
		}

		// Get the current provider.
		const provider = state.get( 'library' )?.props.get( 'provider' ) || AMF_DATA.providers[0]?.id;
		if ( ! provider ) {
			alert( 'No provider found!' );
			return;
		}

		// Short circuit for local media provider.
		if ( provider === 'local' ) {
			click_handler();
			return;
		}

		event.target.disabled = true;

		wp.ajax.post(
			'amf-select',
			{
				selection: selection_state.toJSON(),
				post: wp.media.view.settings.post.id,
				provider
			}
		).done( response => {
			Object.keys(response).forEach( key => {
				selection_state.get( key ).set( 'id', response[ key ] );
			});

			event.target.disabled = false;

			click_handler();
		} ).fail( response => {
			const message = response?.[0]?.message || 'An unknown error occurred.';

			alert( message );

			event.target.disabled = false;
		} );
	}
}

export function addProviderFilter() {
	const { providers } = AMF_DATA;

	// Short circuit if we don't have providers
	if ( ! providers.length ) {
		return;
	}

	addInlineStyle( `
		.view-switch { display: none !important; }
		body.upload-php .media-toolbar-secondary { padding: 12px 0; }
		.amf-hidden { display: none !important; }
	` );

	// If we have only 1 provider then it's the default, no need for a filter.
	if ( providers.length === 1 ) {
		toggleUI( providers[0].supports );
		return;
	}

	// Override core styles that allow only two filter inputs
	addInlineStyle( `
		.media-modal-content .media-frame select.attachment-filters { width: 150px }
		.media-modal-content .media-frame #media-attachment-provider-filter + .spinner { float: right; margin: -25px -0px 5px 25px; }
	` );

	// Create a new MediaLibraryProviderFilter we later will instantiate
	var MediaLibraryProviderFilter = wp.media.view.AttachmentFilters.extend({
		id: 'media-attachment-provider-filter',

		createFilters: function() {
			this.filters = providers.reduce( ( filters, { id, name } ) => {
				filters[ id ] = {
					text: name,
					props: {
						provider: id,
					},
				};
				return filters;
			}, {} );
		},

		select: function() {
			const props = this.model.toJSON();
			let value = providers?.[0]?.id;

			_.find( this.filters, function( filter, id ) {
				const equal = _.all( filter.props, function( prop, key ) {
					return prop === ( props?.[ key ] || null );
				});

				if ( equal ) {
					value = id;
					return value;
				}
			});

			this.$el.val( value );

			// Show / hide components based on provider capabilities.
			if ( props.provider ) {
				const provider = providers.find( ( { id } ) => id === props.provider );
				toggleUI( provider.supports );
			}
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

export function toggleUI( supports ) {
	jQuery( 'a[href*="media-new.php"],.uploader-inline .upload-ui,.uploader-inline .post-upload-ui' ).toggleClass( 'amf-hidden', ! supports.create );
	jQuery( '.media-button.delete-selected-button' ).toggleClass( 'amf-hidden', ! supports.delete );
	jQuery( '#media-attachment-date-filters' ).toggleClass( 'amf-hidden', ! supports.filterDate );
	jQuery( '#media-attachment-filters' ).toggleClass( 'amf-hidden', ! supports.filterType );
}
