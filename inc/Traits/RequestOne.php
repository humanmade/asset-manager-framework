<?php
/**
 * RequestOne trait.
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework\Traits;

use AssetManagerFramework\Media;
use WP_Query;

trait RequestOne {

	/**
	 * Perform a request to a media provider and return results for a single item.
	 *
	 * Typically an implementation of this method will perform a remote request to a media provider service,
	 * process the results, and return a single Media item.
	 *
	 * @param string|int $id The external provider item ID.
	 * @throws Exception Thrown if an unrecoverable error occurs.
	 * @return Media A single Media object response.
	 */
	abstract protected function request_one( $id ) : Media;

	/**
	 * Fetches a single item from the provider.
	 *
	 * @param string|int $id The external media ID.
	 * @return Media
	 */
	final public function request_item( $id ) : Media {

		$item = $this->request_one( str_replace( 'amf-', '', (string) $id ) );

		$query = [
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_name__in' => [ $id ],
		];

		$posts = ( new WP_Query( $query ) )->posts;
		$ids = array_column( $posts, 'ID', 'post_name' );

		if ( isset( $ids[ $item->id ] ) ) {
			$item->id = $ids[ $item->id ];
			$item->attachmentExists = true;
		}

		$item->provider = $this->get_id();

		return $item;
	}
}
