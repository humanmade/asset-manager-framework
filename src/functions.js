
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

	// WP 7.0 turned .media-toolbar-secondary into a fixed 2x2 grid (row 1 labels,
	// row 2 selects) with hard coded grid-areas for the only two filters core knows
	// about. A third filter is auto placed into an implicit third row and clipped by
	// the fixed height toolbar. WP 6.9 and earlier still use the old float layout,
	// where the opposite is true and the width override below is what makes three
	// filters fit. See https://github.com/humanmade/product-dev/issues/2265
	if ( AMF_DATA.gridToolbar ) {
		addInlineStyle( `
			/* Let every filter claim its own column instead of overflowing to a third row. */
			.media-toolbar-secondary {
				grid-template-columns: none;
				grid-auto-flow: column;
				grid-auto-columns: auto;
			}

			/*
			 * Reset core's hard coded 2x2 placements so auto-flow controls the order.
			 * The last two selectors are not redundant: core places the date filter with
			 * "select#media-attachment-filters ~ select#media-attachment-date-filters",
			 * which carries two IDs, so a single ID selector here would lose and the
			 * date filter would stay pinned to column 2 whenever the type filter is hidden.
			 */
			.media-toolbar-secondary > label[for="media-attachment-filters"],
			.media-toolbar-secondary > select#media-attachment-filters,
			.media-toolbar-secondary > label[for="media-attachment-date-filters"],
			.media-toolbar-secondary > select#media-attachment-date-filters,
			.media-toolbar-secondary > label[for="media-attachment-provider-filter"],
			.media-toolbar-secondary > select#media-attachment-provider-filter,
			.media-toolbar-secondary > select#media-attachment-filters ~ label[for="media-attachment-date-filters"],
			.media-toolbar-secondary > select#media-attachment-filters ~ select#media-attachment-date-filters {
				grid-area: auto;
			}

			/* Shrink with the container rather than collide with the search field. */
			.media-toolbar-secondary > select.attachment-filters {
				min-width: 0;
			}

			/*
			 * Any non-filter child (the dragInfo and suggestedDimensions instructions,
			 * buttons) must span both rows, or it lands in the label row, stretches it
			 * and pushes the selects back out of the toolbar.
			 */
			.media-toolbar-secondary > *:not(label):not(select) {
				grid-row: 1 / -1;
				align-self: center;
			}

			/*
			 * Below 901px core stacks the toolbar and hard codes a height for exactly
			 * two filters (7.1: 117px, 7.0: 74px). Let it size itself instead and feed
			 * the matching content offset in from syncToolbarOffset().
			 */
			@media only screen and (max-width: 900px) {
				.attachments-browser .media-toolbar {
					height: auto;
				}

				.attachments-browser:not(.has-load-more) .attachments,
				.attachments-browser.has-load-more .attachments-wrapper,
				.attachments-browser .uploader-inline,
				.media-frame-content .attachments-browser .attachments-wrapper {
					top: var( --amf-toolbar-offset, 131px );
				}
			}
		` );
	} else {
		// Override core styles that allow only two filter inputs
		addInlineStyle( `
			.media-modal-content .media-frame select.attachment-filters { width: 150px }
			.media-modal-content .media-frame #media-attachment-provider-filter + .spinner { float: right; margin: -25px -0px 5px 25px; }
		` );
	}

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

			// The provider filter is not offered while editing a gallery. Both the
			// label and the select are skipped, so no orphaned label is left behind.
			if ( this.controller._state !== 'gallery-edit' ) {
				/*
				 * The filter is a <select>, so a <label> needs to be rendered before it.
				 * wp.media.view.Label defaults to screen-reader-text up to WP 6.9 and
				 * drops that class in 7.0, so this matches whatever core does with its
				 * own filter labels on the version in use.
				 */
				this.toolbar.set( 'MediaLibraryProviderFilterLabel', new wp.media.view.Label({
					value: AMF_DATA.l10n?.providerFilterLabel || 'Media library',
					attributes: {
						'for': 'media-attachment-provider-filter'
					},
					priority: -75
				}).render() );

				this.toolbar.set( 'MediaLibraryProviderFilter', new MediaLibraryProviderFilter({
					controller: this.controller,
					model:      this.collection.props,
					priority: -75
				}).render() );
			}

			// The toolbar is not in the document yet and its height changes with the
			// viewport and with which filters a provider supports, so track it.
			observeToolbarHeight( this.toolbar.$el[0] );
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
	jQuery( '#media-attachment-date-filters, label[for="media-attachment-date-filters"]' ).toggleClass( 'amf-hidden', ! supports.filterDate );
	jQuery( '#media-attachment-filters, label[for="media-attachment-filters"]' ).toggleClass( 'amf-hidden', ! supports.filterType );
}

/**
 * Track the toolbar height so the content below it can be offset to match.
 *
 * Only has an effect below 901px, where core stacks the filters and hard codes a
 * toolbar height that assumes there are exactly two of them. A ResizeObserver
 * covers every case that changes the height: first layout, viewport resizes, and
 * filters being shown or hidden as the provider changes.
 *
 * @param {HTMLElement} toolbarEl
 */
export function observeToolbarHeight( toolbarEl ) {
	if ( ! AMF_DATA.gridToolbar || ! toolbarEl || typeof ResizeObserver === 'undefined' ) {
		return;
	}

	new ResizeObserver( () => syncToolbarOffset( toolbarEl ) ).observe( toolbarEl );
}

/**
 * @param {HTMLElement} toolbarEl
 */
function syncToolbarOffset( toolbarEl ) {
	// A detached or hidden toolbar measures 0. Ignore it so a closing modal does
	// not clobber the offset of one that is still on screen.
	if ( ! toolbarEl.offsetHeight ) {
		return;
	}

	// Core keeps a constant 14px gap between the toolbar height and the top of the
	// attachment list (7.1: 117 -> 131, 7.0: 74 -> 90).
	document.documentElement.style.setProperty(
		'--amf-toolbar-offset',
		( toolbarEl.offsetHeight + 14 ) + 'px'
	);
}
