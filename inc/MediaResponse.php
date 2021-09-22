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

	/**
	 * Total available items.
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Items requested per page.
	 *
	 * @var int
	 */
	private $per_page;

	/**
	 * Sets up the MediaResponse object.
	 *
	 * @param ?MediaList $media_list The list of returned items.
	 * @param int $total The total number of results available.
	 * @param int $per_page The number of items requested per page, defaults to 40 in media
	 *                      modal requests.
	 */
	public function __construct( ?MediaList $media_list = null, int $total = 0, int $per_page = 40 ) {
		$this->media_list = $media_list ?? new MediaList();
		$this->total = $total;
		$this->per_page = $per_page;
	}

	public function get_items() : MediaList {
		return $this->media_list;
	}

	public function get_total() : int {
		return $this->total;
	}

	public function get_total_pages() : int {
		return (int) ceil( $this->total / $this->per_page );
	}

}
