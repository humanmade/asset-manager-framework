<?php
/**
 * Blank provider
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

class BlankProvider extends Provider {

	protected function request( array $args ) : MediaList {
		return new MediaList();
	}
}
