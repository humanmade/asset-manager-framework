<?php
/**
 * Local media provider
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

class LocalProvider extends Provider {
	public function get_id() : string {
		return 'local';
	}

	public function get_name() : string {
		return __( 'Local Media', 'asset-manager-framework' );
	}

	protected function request( array $args ) : MediaList {
		wp_ajax_query_attachments();
		return new MediaList();
	}

	public function supports_asset_create() : bool {
		return true;
	}

	public function supports_asset_update() : bool {
		return true;
	}

	public function supports_asset_delete() : bool {
		return true;
	}
}
