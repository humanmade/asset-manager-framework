<?php
/**
 * Blank provider
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

class BlankProvider extends Provider {
	public static $id = '';
	public static $name = 'Local media';

	protected function request( array $args ) : MediaList {
		return new MediaList();
	}
}
