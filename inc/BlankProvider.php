<?php
/**
 * Blank provider
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

class BlankProvider extends Provider {
	public function get_id() : string {
		return '';
	}

	public function get_name() : string {
		return '';
	}

	protected function request( array $args ) : MediaList {
		return new MediaList();
	}
}
