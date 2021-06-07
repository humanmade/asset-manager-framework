<?php
/**
 * Provider registry
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

final class ProviderRegistry {

	private static $instance;

	private $providers = [];

	private function __construct() {}

	public static function instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register( Provider $provider ) : ProviderRegistry {
		$provider = apply_filters( 'amf/provider', $provider );
		$this->providers[ $provider->get_id() ] = $provider;
		return $this;
	}

	public function get( string $id = '' ) : ?Provider {
		if ( $id ) {
			if ( empty( $this->providers[ $id ] ) ) {
				throw ProviderException::not_found( $id );
			}

			return $this->providers[ $id ];
		}

		if ( ! $this->providers ) {
			throw ProviderException::empty();
		}

		return reset( $this->providers );
	}

	public function get_script_data() : array {
		return array_map(
			function( Provider $provider ) : array {
				return [
					'name' => $provider->get_name(),
					'supports' => [
						'create' => $provider->supports_asset_create(),
						'update' => $provider->supports_asset_update(),
						'delete' => $provider->supports_asset_delete(),
						'dynamicResizing' => $provider->supports_dynamic_image_resizing(),
						'filterDate' => $provider->supports_filter_date(),
						'filterSearch' => $provider->supports_filter_search(),
						'filterType' => $provider->supports_filter_type(),
						'filterUser' => $provider->supports_filter_user(),
					],
				];
			},
			$this->providers
		);
	}

}
