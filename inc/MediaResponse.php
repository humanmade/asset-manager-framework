<?php
/**
 * Media Response object.
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

class MediaResponse {

	/**
	 * The reponse media list.
	 *
	 * @var MediaList
	 */
	private $media_list;

	public function __construct( MediaList $media_list, int $total, int $per_page ) {
		$this->media_list = $media_list;

		// Set pagination headers.
		if ( ! headers_sent() ) {
			header( sprintf( 'X-WP-Total: %d', $total ) );
			header( sprintf( 'X-WP-TotalPages: %d', ceil( $total / $per_page ) ) );
		}
	}

	public function get_items() : MediaList {
		return $this->media_list;
	}

}
