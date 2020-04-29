<?php
/**
 * Integration with MultilingualPress 3.
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework\MultilingualPress;

use Inpsyde\MultilingualPress\ {
	TranslationUi\Post\RelationshipContext,
	TranslationUi\Post\PostRelationSaveHelper,
	Framework\Http\PhpServerRequest,
};

/**
 * Bootstrap actions and filters.
 */
function bootstrap() : void {
	add_filter( PostRelationSaveHelper::FILTER_SYNC_KEYS, __NAMESPACE__ . '\sync_thumbnail', 10, 3 );
}

/**
 * Synchronises the featured image to the translation site when requested.
 *
 * @param string[]            $keys    Array of post meta key names to sync. Unused.
 * @param RelationshipContext $context Context for the current translation.
 * @param PhpServerRequest    $request The translation UI request.
 * @return string[] The unmodified array of post meta keys to sync.
 */
function sync_thumbnail( array $keys, RelationshipContext $context, PhpServerRequest $request ) : array {
	$translations = $request->bodyValue(
		'multilingualpress',
		INPUT_POST,
		FILTER_DEFAULT,
		FILTER_FORCE_ARRAY
	);

	$remote_site_id = $context->remoteSiteId();
	$remote_post_id = $context->remotePostId();
	$source_post_id = $context->sourcePostId();

	// Bail if there's something wrong with the request
	if ( ! isset( $translations[ "site-{$remote_site_id}" ] ) ) {
		return $keys;
	}

	$translation = $translations[ "site-{$remote_site_id}" ];

	// Bail if the user hasn't requested to copy the featured image
	if ( $translation['remote-thumbnail-copy'] !== '1' ) {
		return $keys;
	}

	$source_attachment_id = get_post_meta( $source_post_id, '_thumbnail_id', true );

	// Bail if there's no featured image
	if ( empty( $source_attachment_id ) ) {
		return $keys;
	}

	$source_attachment = get_post( $source_attachment_id );
	$source_attachment_meta = get_post_meta( $source_attachment->ID );

	// Switch to the target site
	switch_to_blog( $remote_site_id );

	$remote_attachment_id = get_post_meta( $remote_post_id, '_thumbnail_id', true );

	if ( ! $remote_attachment_id ) {
		// No remote attachment, create one copied from source attachment
		$source_attachment->ID = null;
		$remote_attachment_id = wp_insert_attachment(
			$source_attachment,
			false,
			$remote_post_id,
			true
		);

		// There doesn't appear to be an error reporting mechanism in MLP, so we'll just bail if there's a problem
		// creating the attachment on the remote site
		if ( is_wp_error( $remote_attachment_id ) ) {
			return $keys;;
		}

		// Set featured image ID for remote post
		add_post_meta( $remote_post_id, '_thumbnail_id', $remote_attachment_id );
	} else {
		// Update existing remote attachment with source attachment
		$source_attachment->ID = $remote_attachment_id;
		$source_attachment->post_parent = $remote_post_id;
		wp_update_post(
			$source_attachment
		);
	}

	// Iterate all source attachment metadata and apply to remote post
	foreach ( $source_attachment_meta as $key => $values ) {
		foreach ( $values as $value ) {
			update_post_meta( $remote_attachment_id, $key, maybe_unserialize( $value ) );
		}
	}

	// Switch back to the source site
	restore_current_blog();

	return $keys;
}
