<?php
/**
 * Provider exception
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use Exception;

final class ProviderException extends Exception {

	public static function empty(): self {
		return new self(
			__( 'No provider found.', 'asset-manager-framework' )
		);
	}

	public static function not_found( string $id ): self {
		return new self(
			sprintf(
				/* translators: %s: Provider class ID */
				__( 'Provider with ID "%s" not found.', 'asset-manager-framework' ),
				$id
			)
		);
	}

}
