<?php
/**
 * Base provider
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use Exception;
use RangeException;
use WP_Query;

abstract class Provider {

	/**
	 * Get the ID for the provider.
	 *
	 * @return string
	 */
	abstract public function get_id() : string;

	/**
	 * Get the human readable name for this provider.
	 *
	 * @return string
	 */
	abstract public function get_name() : string;

	/**
	 * Perform a request to a media provider and return results according to the arguments.
	 *
	 * Typically an implementation of this method will perform a remote request to a media provider service,
	 * process the results, and return a Media collection.
	 *
	 * @param array $args {
	 *     Arguments for the request for media items, typically coming directly from the media manager filters.
	 *
	 *     @type int      $paged          The page number of the results.
	 *     @type int      $posts_per_page Optional. Maximum number of results to return. Usually 40.
	 *     @type string   $s              Optional. The search query.
	 *     @type string[] $post_mime_type Optional. Array of primary mime types or subtypes.
	 *     @type string   $orderby        Optional. Order by. Typically it's safe to assume 'date', although 'menu_order ID' is possible.
	 *     @type string   $order          Optional. Order. 'DESC' (for 'date') or 'ASC' (for 'menu_order ID').
	 *     @type int      $author         Optional. User ID if results are filtered by author.
	 *     @type int      $year           Optional. Four digit year number if results are filtered by date.
	 *     @type int      $monthnum       Optional. One or two digit month number if results are filtered by date.
	 * }
	 * @throws Exception Thrown if an unrecoverable error occurs.
	 * @return MediaList The collection of Media items. Can be an empty collection if there are no matching results.
	 */
	abstract protected function request( array $args ) : MediaList;

	public function supports_asset_create() : bool {
		return false;
	}

	public function supports_asset_update() : bool {
		return false;
	}

	public function supports_asset_delete() : bool {
		return false;
	}

	public function supports_asset_crop() : bool {
		return false;
	}

	public function supports_dynamic_image_resizing() : bool {
		return false;
	}

	public function supports_filter_search() : bool {
		return true;
	}

	public function supports_filter_date() : bool {
		return true;
	}

	public function supports_filter_type() : bool {
		return true;
	}

	public function supports_filter_user() : bool {
		return true;
	}

	/**
	 * Fetches a list of media items that are ultimately used directly by the media managaer.
	 *
	 * @param array $args Raw query arguments from the POST request in the media manager.
	 * @throws RangeException Thrown if the provider returns too many media items.
	 * @return MediaList Media items for use in the media manager.
	 */
	final public function request_items( array $args ) : MediaList {
		if ( isset( $args['post_parent'] ) ) {
			// @TODO
			// special case, only return attachments that exist.
			// run a WP_Query for attachments with post parent and
			// set the attachment status on all of the items
			// and return
			// note that post_parent can be 0
		}

		if ( isset( $args['posts_per_page'] ) ) {
			$args['posts_per_page'] = intval( $args['posts_per_page'] );
		}

		if ( isset( $args['year'] ) ) {
			$args['year'] = intval( $args['year'] );
		}

		if ( isset( $args['monthnum'] ) ) {
			$args['monthnum'] = intval( $args['monthnum'] );
		}

		if ( isset( $args['author'] ) ) {
			$args['author'] = intval( $args['author'] );
		}

		if ( isset( $args['post_mime_type'] ) ) {
			// The post_mime_type arg takes various formats, so this normalises its value to make it easier
			// for implementations to deal with.

			// The post_mime_type arg can be a string of comma separate types or subtypes, or an array of types or subtypes.
			// Examples:
			// - a string containing one primary type: image
			// - an array containing primary types: [ image, video ]
			// - a comma separated string of subtypes: application/msword,application/wordperfect,application/octet-stream

			if ( ! is_array( $args['post_mime_type'] ) ) {
				$args['post_mime_type'] = explode( ',', $args['post_mime_type'] );
			}
		}

		$args['paged'] = intval( $args['paged'] ?? 1 );

		$items = $this->request( $args );
		$array = $items->toArray();

		if ( ! $array ) {
			return $items;
		}

		if ( isset( $args['posts_per_page'] ) && ( $args['posts_per_page'] > 0 ) && count( $array ) > $args['posts_per_page'] ) {
			throw new RangeException(
				sprintf(
					/* translators: %s: Argument name */
					__( 'Too many media items were returned by the provider. The "%s" argument must be respected.', 'asset-manager-framework' ),
					'posts_per_page'
				)
			);
		}

		$names = array_column( $array, 'id' );
		$query = [
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_name__in' => $names,
		];

		$posts = ( new WP_Query( $query ) )->posts;
		$ids = array_column( $posts, 'ID', 'post_name' );

		$provider_id = $this->get_id();

		foreach ( $items as $item ) {
			if ( isset( $ids[ $item->id ] ) ) {
				$item->id = $ids[ $item->id ];
				$item->attachmentExists = true;
			}
			$item->provider = $provider_id;
		}

		return $items;
	}

	/**
	 * Performs an HTTP API request and returns the response. Abstracts away the HTTP error handling so
	 * an implementation only needs to concern itself with the happy path.
	 *
	 * @param string $url The URL for the request.
	 * @param array  $args The arguments to pass to `wp_remote_request()`.
	 * @throws Exception Thrown if there is an error with the request or its response code is not 200.
	 * @return string The response body.
	 */
	final public function remote_request( string $url, array $args ) : string {
		$response = wp_remote_request(
			$url,
			$args
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: Error message */
					__( 'Error fetching media: %s', 'asset-manager-framework' ),
					$response->get_error_message()
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$message = sprintf(
				'%1$s: %2$s',
				$response_code,
				$response_message
			);

			throw new Exception(
				sprintf(
					/* translators: %s: Error message */
					__( 'Error fetching media: %s', 'asset-manager-framework' ),
					$message
				)
			);
		}

		return $response_body;
	}

}
