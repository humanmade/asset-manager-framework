<?php
/**
 * Asset Manager Framework plugin for WordPress
 *
 * @package   asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use Exception;
use WP_Http;
use WP_Post;
use WP_Query;

function bootstrap() : void {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
	add_action( 'wp_enqueue_media', __NAMESPACE__ . '\\enqueue_scripts', -10 );
	add_action( 'wp_print_scripts', __NAMESPACE__ . '\\enqueue_scripts', -10 );

	// Replace the default wp_ajax_query_attachments handler with our own.
	remove_action( 'wp_ajax_query-attachments', 'wp_ajax_query_attachments', 1 );
	add_action( 'wp_ajax_query-attachments', __NAMESPACE__ . '\\ajax_query_attachments', 1 );

	// Handle the 'select' event Ajax call in the media manager.
	add_action( 'wp_ajax_amf-select', __NAMESPACE__ . '\\ajax_select' );

	// Specify the attached file for our placeholder attachment objects.
	add_filter( 'get_attached_file', __NAMESPACE__ . '\\replace_attached_file', 10, 2 );

	// Ensure thumbnail sizes are set correctly - WP will prepend the base URL again.
	add_filter( 'wp_prepare_attachment_for_js', __NAMESPACE__ . '\\fix_media_size_urls', 1000, 2 );
}

function init() : void {
	// Load the integration layer for MLP if it's active
	if ( class_exists( 'Inpsyde\MultilingualPress\MultilingualPress', false ) ) {
		require_once __DIR__ . '/integrations/multilingualpress/namespace.php';
		Integrations\MultilingualPress\bootstrap();
	}

	// Load the Local Media provider.
	if ( allow_local_media() ) {
		register_provider( new LocalProvider() );
	}

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
			],
			$asset_file['dependencies']
		),
		$asset_file['version'],
		false
	);

	wp_add_inline_script(
		'asset-manager-framework',
		sprintf( 'var AMF_DATA = %s;', wp_json_encode( get_script_data() ) ),
		'before'
	);
}

function get_script_data() : array {
	$providers = array_reduce( get_providers(), function( array $carry, Provider $provider ) : array {
		$carry[ $provider->get_id() ] = [
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
		return $carry;
	}, [] );

	return apply_filters( 'amf/script/data', [
		'providers' => $providers,
	] );
}

function ajax_select() : void {

	try {
		$provider = get_provider( $_REQUEST['provider'] ?? null );
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

	$supports_dynamic_image_resizing = $provider->supports_dynamic_image_resizing();

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

		if ( ! empty( $selection['alt'] ) ) {
			add_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $selection['alt'] ) );
		}

		add_post_meta( $attachment_id, 'amf_provider', $provider->get_id(), true );

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

function replace_attached_file( $file, int $attachment_id ) : string {
	$metadata = wp_get_attachment_metadata( $attachment_id, true );

	return $metadata['file'] ?? '';
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

function register_provider( Provider $provider ) {
	global $amf_providers;

	if ( empty( $amf_providers ) ) {
		$amf_providers = [];
	}

	$amf_providers[ $provider->get_id() ] = $provider;
}

function get_providers() : array {
	global $amf_providers;

	$providers = $amf_providers ?? [];

	return apply_filters( 'amf/providers', $providers );
}

function get_provider( ?string $id = null ) : Provider {
	$providers = get_providers();
	$provider = null;

	// If no ID is passed use the first available as default.
	if ( empty( $id ) ) {
		$provider = array_shift( $providers );
	} else {
		$provider = $providers[ $id ];
	}

	// If no provider matched then error out.
	if ( empty( $provider ) ) {
		throw new Exception(
			sprintf(
				/* translators: %s: Provider class ID */
				__( 'Media Provider with ID "%s" not found.', 'asset-manager-framework' ),
				$id
			)
		);
	}

	return apply_filters( 'amf/provider', $provider, $id );
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
		$provider = get_provider( $args['provider'] ?? null );
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

function fix_media_size_urls( array $response, WP_Post $attachment ) : array {
	if ( strpos( $attachment->post_name, 'amf-' ) !== 0 ) {
		return $response;
	}

	// Correct the image sizes array to remove the duplicated base URL when a
	// full URL is provided as the size file name.
	if ( ! empty( $response['sizes'] ) ) {
		$base_url = str_replace( wp_basename( $attachment->guid ), '', $attachment->guid );
		foreach ( $response['sizes'] as $name => $size ) {
			if ( mb_substr_count( $size['url'], $base_url ) < 2 ) {
				continue;
			}
			$response['sizes'][ $name ]['url'] = $base_url . str_replace( $base_url, '', $size['url'] );
		}
	}

	return $response;
}
