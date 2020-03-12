<?php
/**
 * Base provider
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use Exception;
use WP_Query;

abstract class Provider {

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

	final public function request_items( array $args ) : MediaList {
		if ( ! empty( $args['post_parent'] ) ) {
			// @TODO
			// special case, only return attachments that exist.
			// run a WP_Query for attachments with post parent and
			// set the attachment status on all of the items
			// and return
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

		$names = array_column( $array, 'id' );
		$query = [
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_name__in' => $names,
		];

		$posts = ( new WP_Query( $query ) )->posts;
		$ids = array_column( $posts, 'ID', 'post_name' );

		foreach ( $items as $item ) {
			if ( isset( $ids[ $item->id ] ) ) {
				$item->id = $ids[ $item->id ];
				$item->attachmentExists = true;
			}
		}

		return $items;
	}

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
