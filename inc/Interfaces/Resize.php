<?php
/**
 * Resizable interface.
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework\Interfaces;

use WP_Post;

interface Resize {

	/**
	 * Handles resizing of an AMF attachment.
	 *
	 * @param WP_Post $attachment The attachment post.
	 * @param int $width Target width.
	 * @param int $height Target height.
	 * @param bool|array $crop If truthy crop to the given dimensions, can be a non-associative
	 *                         array of x and y positions where x is 'left', 'center' or 'right'
	 *                         and y is 'top', 'center' or 'bottom'.
	 * @return string The resized asset URL.
	 */
	public function resize( WP_Post $attachment, int $width, int $height, $crop = false ) : string;

}
