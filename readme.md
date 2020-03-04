# Asset Manager Framework

This WordPress plugin provides a framework for replacing the contents of the standard WordPress media library with assets from an external provider, such as a DAM.

This framework handles all the necessary integration with WordPress (Ajax endpoints, REST API endpoints, and Backbone components), leaving you to only focus on implementing a connection to your DAM.

The intention is that the media manager, the block editor, the classic editor, media endpoints of the REST API, and anything that calls `wp.media()` should "just work" and not need to implement any changes in order to support a media library that is powered by an external provider.

## Status

Current status: **Proof of concept.** This is far from ready for production use.

The following user-facing features work as expected:

* [X] Image block (including its derivatives such as Cover and Media & Text)
* [X] Video block
* [X] Audio block
* [X] File block
* [X] Featured image
* [X] Featured image in the classic editor
* [X] Media screen list mode
* [X] Media screen grid mode (see note below)
* [X] Media screen grid attachment details
* [X] Attachment editing screen

The following user-facing features are not yet supported:

* [ ] Gallery block
* [ ] Images in the classic editor
* [ ] Videos in the classic editor
* [ ] Audio in the classic editor
* [ ] Galleries in the classic editor
* [ ] Other file types in the classic editor
* [ ] Deep linking to the media screen grid attachment details
* [ ] Setting the featured image for a non-image media item automatically, when one is available (eg. video poster)
* [ ] Responsive image srcsets on images within post content

The following new features are planned but not yet implemented:

* [ ] Read-only mode (to prevent local uploading, editing, cropping, or deletion)

## Implementation

There are two main parts to the way this plugin works.

1. Allowing the media manager grid to display external items which are not existing attachments.
2. Subsequently creating a local attachment for an external item when it's selected for use.

The design decision behind this is that allowing for external items to be browsed in the media manager is quite straight forward, but unless each item is associated with a local attachment then most of the rest of WordPress breaks. Previous attempts to do this have involved lying about attachment IDs, or switching to another site on a Multisite network to provide a media item. Neither approach is desirable because such lies need to be maintained and eventually you run into a situation where your lie comes unravelled.

Asset Manager Framework instead allows external media items to be browsed in the media library grid, but as soon as an item is selected for use (eg. to be inserted into a post or used as a featured image), an attachment is created for the media item, and this gets returned by the media manager.

Importantly, the actual media item does not get sideloaded into WordPress - it remains as an external item at its external URL. The attachment object refers to the correct external URL as necessary, while maintaining a local attachment that can be referenced, queried, etc.

## Integration

There are two steps to integrating a media provider using the Asset Manager Framework:

1. Create a provider which extends the `AssetManagerFramework\Provider` class and implements its `request()` method to perform a request to your external media provider to fetch results based on query arguments from the media manager.
2. Hook into the `amf/provider_class` filter to register your provider for use.

Full documentation is coming soon, but for now here's an example of a provider which supplies images from placekitten.com:

```php
<?php

use AssetManagerFramework\{
	Provider,
	MediaList,
	Image,
};

class KittenProvider extends Provider {

	protected function request( array $args ) : MediaList {
		$kittens = [
			500 => 'Boop',
			600 => 'Fuzzy',
			700 => 'Paws',
		];
		$items = [];

		foreach ( $kittens as $id => $title ) {
			$item = new Image( $id, 'image/jpeg' );
			$item->set_url( sprintf(
				'https://placekitten.com/%1$d/%1$d',
				$id
			) );
			$item->set_title( $title );
			$item->set_width( $id );
			$item->set_height( $id );

			$items[] = $item;
		}

		return new MediaList( ...$items );
	}

}

add_filter( 'amf/provider_class', function() {
	return 'KittenProvider';
} );
```

Try it and your media library will be much improved:

![Admin Toolbar Menu](assets/KittenProvider.png)
