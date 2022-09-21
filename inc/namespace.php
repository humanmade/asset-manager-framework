<?php
/**
 * Asset Manager Framework plugin for WordPress
 *
 * @package   asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use AssetManagerFramework\Interfaces\{
	Resize
};
use Exception;
use WP_Http;
use WP_Post;
use WP_REST_Response;
use WP_Query;

function bootstrap() : void {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
	add_action( 'wp_enqueue_media', __NAMESPACE__ . '\\enqueue_scripts', 1000 );
	add_action( 'wp_print_scripts', __NAMESPACE__ . '\\enqueue_scripts', 1000 );

	// Replace the default wp_ajax_query_attachments handler with our own.
	remove_action( 'wp_ajax_query-attachments', 'wp_ajax_query_attachments', 1 );
	add_action( 'wp_ajax_query-attachments', __NAMESPACE__ . '\\ajax_query_attachments', 1 );

	// Handle the 'select' event Ajax call in the media manager.
	add_action( 'wp_ajax_amf-select', __NAMESPACE__ . '\\ajax_select' );

	// Specify the attached file for our placeholder attachment objects.
	add_filter( 'get_attached_file', __NAMESPACE__ . '\\maybe_replace_attached_file', 10, 2 );

	// Filter attachment URL.
	add_filter( 'wp_get_attachment_url', __NAMESPACE__ . '\\maybe_replace_attachment_url', 1, 2 );

	// Ensure image URLs are output correctly - WP will prepend the base URL again in some cases.
	add_filter( 'wp_prepare_attachment_for_js', __NAMESPACE__ . '\\fix_media_size_urls', 1000, 2 );
	add_filter( 'wp_calculate_image_srcset', __NAMESPACE__ . '\\fix_srcset_urls', 1000, 5 );
	add_filter( 'rest_prepare_attachment', __NAMESPACE__ . '\\fix_rest_attachment_urls', 1000, 3 );
	add_filter( 'image_get_intermediate_size', __NAMESPACE__ . '\\fix_intermediate_size_url', 1000, 2 );
	add_filter( 'wp_get_attachment_image_src', __NAMESPACE__ . '\\fix_attachment_image_src', 1000, 2 );

	// Ensure URLs available for missing image sizes.
	add_filter( 'wp_get_attachment_metadata', __NAMESPACE__ . '\\add_fallback_sizes', 1, 2 );

	// Dynamic image support.
	add_filter( 'image_downsize', __NAMESPACE__ . '\\dynamic_downsize', 9, 3 );
	add_filter( 'wp_calculate_image_srcset', __NAMESPACE__ . '\\dynamic_srcset', 9, 5 );
}

function init() : void {
	// Load the integration layer for MLP if it's active
	if ( class_exists( 'Inpsyde\MultilingualPress\MultilingualPress', false ) ) {
		require_once __DIR__ . '/integrations/multilingualpress/namespace.php';
		Integrations\MultilingualPress\bootstrap();
	}

	$provider_registry = ProviderRegistry::instance();

	// Load the Local Media provider.
	if ( allow_local_media() ) {
		$provider_registry->register( new LocalProvider() );
	}

	do_action( 'amf/register_providers', $provider_registry );

	do_action( 'amf/loaded' );
}

function enqueue_scripts() : void {
	if ( ! wp_script_is( 'media-views', 'enqueued' ) ) {
		return;
	}

	$asset_file = require plugin_dir_path( __DIR__ ) . 'build/index.asset.php';

	wp_enqueue_script(
		'asset-manager-framework',
		plugin_dir_url( __DIR__ ) . 'build/index.js',
		array_merge(
			[
				'media-views',
				'wp-hooks',
			],
			$asset_file['dependencies']
		),
		$asset_file['version'],
		true
	);

	$data = apply_filters( 'amf/script/data', [
		'providers' => ProviderRegistry::instance()->get_script_data(),
	] );

	wp_add_inline_script(
		'asset-manager-framework',
		sprintf( 'var AMF_DATA = %s;', wp_json_encode( $data ) ),
		'before'
	);
}

function ajax_select() : void {

	try {
		$provider = ProviderRegistry::instance()->get( $_REQUEST['provider'] ?? '' );
	} catch ( Exception $e ) {
		wp_send_json_error(
			[
				[
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
				],
			],
			WP_Http::INTERNAL_SERVER_ERROR
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected = isset( $_REQUEST['selection'] ) ? wp_unslash( (array) $_REQUEST['selection'] ) : [];

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = intval( $_REQUEST['post'] ?? 0 );

	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error();
	}

	if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error();
	}

	$supports_dynamic_image_resizing = $provider instanceof Resize;

	$attachments = [];

	foreach ( $selected as $selection ) {
		$attachment = get_attachment_by_id( $selection['id'] );

		if ( $attachment ) {
			$attachments[ $selection['id'] ] = $attachment->ID;
			continue;
		}

		$mime_type = $selection['mime'];

		$args = [
			'post_title' => $selection['title'],
			'post_parent' => $post_id,
			'post_name' => $selection['id'],
			'post_content' => $selection['description'],
			'post_excerpt' => $selection['caption'],
			'post_mime_type' => $mime_type,
			'guid' => $selection['url'],
			'meta_input' => $selection['meta'],
		];

		$attachment_id = wp_insert_attachment( $args, false, 0, true );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id );
		}

		add_post_meta( $attachment_id, '_amf_source_url', wp_slash( $selection['url'] ), true );
		add_post_meta( $attachment_id, '_amf_provider', wp_slash( $provider->get_id() ), true );

		if ( ! empty( $selection['alt'] ) ) {
			add_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $selection['alt'] ) );
		}

		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		if ( ! is_array( $metadata ) ) {
			$metadata = [];
		}

		$metadata['file'] = wp_slash( $selection['filename'] );

		if ( ! empty( $selection['width'] ) ) {
			$metadata['width'] = intval( $selection['width'] );
		}

		if ( ! empty( $selection['height'] ) ) {
			$metadata['height'] = intval( $selection['height'] );
		}

		if ( ! empty( $selection['sizes'] ) && ! $supports_dynamic_image_resizing ) {
			$metadata['sizes'] = array_map( function ( array $size ) use ( $mime_type ): array {
				return [
					'file' => $size['url'],
					'width' => $size['width'],
					'height' => $size['height'],
					'mime-type' => $mime_type,
				];
			}, $selection['sizes'] );
		}

		wp_update_attachment_metadata( $attachment_id, $metadata );

		$attachment = get_post( $attachment_id );
		$meta = $selection['amfMeta'] ?? [];

		unset( $selection['amfMeta'] );

		do_action( 'amf/inserted_attachment', $attachment, $selection, $meta );

		$attachments[ $selection['id'] ] = $attachment->ID;
	}

	wp_send_json_success( $attachments );
}

function maybe_replace_attached_file( $file, int $attachment_id ) {
	$attachment = get_post( $attachment_id );
	if ( ! is_amf_asset( $attachment ) ) {
		return $file;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id, true );

	return $metadata['file'] ?? '';
}

/**
 * Replaces the URL for the attachment on the `wp_get_attachment_url` filter
 *
 * @param string|mixed $url The current URL being filtered
 * @param integer $attachment_id the ID of the attachment
 * @return string the new URL
 */
function maybe_replace_attachment_url( $url, int $attachment_id ) : string {
	$source_url = get_amf_source_url( $attachment_id );
	if ( ! empty( $source_url ) ) {
		return $source_url;
	}

	return $url;
}

/**
 * Returns the AMF source URL of AMF assets, or an empty string for non-AMF assets
 *
 * @param integer $attachment_id the ID of the attachment to fetch source URL for
 * @return string|null the source URL or null
 */
function get_amf_source_url( int $attachment_id ) :? string {
	$attachment = get_post( $attachment_id );
	if ( ! is_amf_asset( $attachment ) ) {
		return null;
	}

	$meta_url = get_post_meta( $attachment_id, '_amf_source_url', true );
	if ( ! empty( $meta_url ) ) {
		return wp_unslash(  $meta_url );
	}

	return null;
}

function get_attachment_by_id( string $id ) :? WP_Post {
	$query = new WP_Query(
		[
			'post_type' => 'attachment',
			'name' => $id,
			'posts_per_page' => 1,
			'no_found_rows' => true,
		]
	);

	if ( ! $query->have_posts() ) {
		return null;
	}

	return $query->next_post();
}

function allow_local_media() : bool {
	$allow = defined( 'AMF_ALLOW_LOCAL_MEDIA' ) ? AMF_ALLOW_LOCAL_MEDIA : true;
	return apply_filters( 'amf/allow_local_media', $allow );
}

function ajax_query_attachments() : void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$args = isset( $_REQUEST['query'] ) ? wp_unslash( (array) $_REQUEST['query'] ) : [];

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = intval( $_REQUEST['post_id'] ?? 0 );

	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error();
	}

	if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error();
	}

	try {
		$provider = ProviderRegistry::instance()->get( $args['provider'] ?? '' );
		$items = $provider->request_items( $args );
	} catch ( Exception $e ) {
		wp_send_json_error(
			[
				[
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
				],
			],
			WP_Http::INTERNAL_SERVER_ERROR
		);
	}

	wp_send_json_success( $items->toArray() );
}

function is_amf_asset( ?WP_Post $attachment ) : bool {
	return ! empty( $attachment ) && strpos( $attachment->post_name, 'amf-' ) === 0;
}

function get_asset_provider( ?WP_Post $attachment ) : ?Provider {
	if ( empty( $attachment ) || ! is_amf_asset( $attachment ) ) {
		return null;
	}

	$provider = null;

	try {
		$provider_id = wp_unslash( get_post_meta( $attachment->ID, '_amf_provider', true ) );
		$provider = ProviderRegistry::instance()->get( $provider_id );
	} catch ( Exception $e ) {
		trigger_error( $e->getMessage(), E_USER_WARNING );
	}

	return $provider;
}

function fix_media_url( $url, WP_Post $attachment ) : string {
	if ( empty( $url ) || ! is_amf_asset( $attachment ) ) {
		return $url ?: '';
	}

	preg_match( '#https?://(?:.(?!https?://))+$#', (string) $url, $matches );

	return $matches[0] ?? '';
}

function fix_media_size_urls( array $response, WP_Post $attachment ) : array {
	if ( ! empty( $response['sizes'] ) ) {
		foreach ( $response['sizes'] as $name => $size ) {
			$response['sizes'][ $name ]['url'] = fix_media_url( $size['url'], $attachment );
		}
	}

	return $response;
}

function fix_srcset_urls( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ) : array {
	$attachment = get_post( $attachment_id );

	foreach ( $sources as $width => $source ) {
		$sources[ $width ]['url'] = fix_media_url( $source['url'], $attachment );
	}

	return $sources;
}

function fix_rest_attachment_urls( WP_REST_Response $response, WP_Post $attachment ) : WP_REST_Response {

	$data = $response->get_data();

	if ( ! empty( $data['media_details'] ) && is_array( $data['media_details'] ) && ! empty( $data['media_details']['sizes'] ) && is_array( $data['media_details']['sizes'] ) ) {
		foreach ( $data['media_details']['sizes'] as $name => $size ) {
			$data['media_details']['sizes'][ $name ]['source_url'] = fix_media_url( $size['source_url'], $attachment );
		}
	}

	$response->set_data( $data );

	return $response;
}

function fix_attachment_image_src( $image, $attachment_id ) {
	if ( ! $image ) {
		return $image;
	}

	$attachment = get_post( $attachment_id );
	$image[0] = fix_media_url( $image[0], $attachment );
	return $image;
}


function fix_intermediate_size_url( array $data, int $attachment_id ) : array {
	$attachment = get_post( $attachment_id );
	$data['url'] = fix_media_url( $data['url'], $attachment );
	return $data;
}

function add_fallback_sizes( array $metadata, int $attachment_id ) : array {
	$attachment = get_post( $attachment_id );

	if ( ! is_amf_asset( $attachment ) ) {
		return $metadata;
	}

	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return $metadata;
	}

	if ( ! isset( $metadata['sizes'] ) ) {
		$metadata['sizes'] = [];
	}

	$source_url = get_amf_source_url( $attachment_id ) ?:  $attachment->guid;

	// Use the full size if available or create a fallback from the main file metadata.
	$fallback_size = $metadata['sizes']['full'] ?? [
		'file' => wp_unslash( $source_url ),
		'width' => intval( $metadata['width'] ),
		'height' => intval( $metadata['height'] ),
		'mime-type' => $attachment->post_mime_type,
	];

	$missing_sizes = array_diff(
		get_intermediate_image_sizes(),
		array_keys( $metadata['sizes'] )
	);

	// Populate missing image sizes from original.
	foreach ( $missing_sizes as $size ) {
		$metadata['sizes'][ $size ] = $fallback_size;
	}

	return $metadata;
}

function get_valid_dimensions( $size, int $max_width = 0, int $max_height = 0 ) : array {
	$width = $max_width;
	$height = $max_height;
	$crop = false;

	if ( is_array( $size ) ) {
		$width = $size[0];
		$height = $size[1];
		$crop = $size[2] ?? $crop;
	} else {
		$all_sizes = wp_get_registered_image_subsizes();
		if ( ! isset( $all_sizes[ $size ] ) ) {
			return [ $width, $height, $crop ];
		}

		$width = $all_sizes[ $size ]['width'];
		$height = $all_sizes[ $size ]['height'];
		$crop = $all_sizes[ $size ]['crop'];
	}

	if ( $crop ) {
		return array_merge(
			wp_constrain_dimensions( $width, $height, $max_width, $max_height ),
			[ $crop ]
		);
	}

	$width = min( $width, $max_width );
	$height = min( $height, $max_height );

	return [ $width, $height, $crop ];
}

function dynamic_downsize( $downsize, $attachment_id, $size ) {
	if ( ! $attachment_id ) {
		return $downsize;
	}

	$attachment = get_post( $attachment_id );

	$provider = get_asset_provider( $attachment );
	if ( ! $provider instanceof Resize ) {
		return $downsize;
	}

	$is_intermediate = false;

	$metadata = wp_get_attachment_metadata( $attachment_id, true );
	$max_width = $metadata['width'] ?? 0;
	$max_height = $metadata['height'] ?? 0;

	list( $width, $height, $crop ) = get_valid_dimensions( $size, $max_width, $max_height );

	if ( ( $width < $max_width ) || ( $height < $max_height ) ) {
		$is_intermediate = true;
	}

	$url = $provider->resize( $attachment, $width, $height, $crop );

	return [
		$url,
		$width,
		$height,
		$is_intermediate,
	];
}

function dynamic_srcset( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ) : array {
	$attachment = get_post( $attachment_id );

	$provider = get_asset_provider( $attachment );
	if ( ! $provider instanceof Resize ) {
		return $sources;
	}

	foreach ( $sources as $target_width => $data ) {
		if ( $size_array[0] < $target_width ) {
			unset( $sources[ $target_width ] );
		} else {
			list( $width, $height ) = wp_constrain_dimensions( $size_array[0], $size_array[1], $target_width );
			$sources[ $target_width ]['url'] = $provider->resize( $attachment, $width, $height, true );
		}
	}

	return $sources;
}
