<?php
/**
 * Asset Manager Framework plugin for WordPress
 *
 * @package   asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use Exception;
use WP_Error;
use WP_Http;
use WP_Post;
use WP_Query;

function bootstrap() : void {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
	add_action( 'admin_print_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
	add_action( 'wp_print_scripts', __NAMESPACE__ . '\\enqueue_scripts' );

	// Replace the default wp_ajax_query_attachments handler with our own.
	remove_action( 'wp_ajax_query-attachments', 'wp_ajax_query_attachments', 1 );
	add_action( 'wp_ajax_query-attachments', __NAMESPACE__ . '\\ajax_query_attachments', 1 );

	// Handle the 'select' event Ajax call in the media manager.
	add_action( 'wp_ajax_amf-select', __NAMESPACE__ . '\\ajax_select' );

	// Specify the attached file for our placeholder attachment objects.
	add_filter( 'get_attached_file', __NAMESPACE__ . '\\replace_attached_file', 10, 2 );

	// Handle uploads via the provider.
	add_filter( 'pre_move_uploaded_file', __NAMESPACE__ . '\\handle_upload', 10, 2 );
	add_filter( 'wp_handle_upload', __NAMESPACE__ . '\\handle_upload_response', 2 );
	add_filter( 'wp_prepare_attachment_for_js', __NAMESPACE__ . '\\match_attachments_for_js', 1000, 2 );
}

function init() : void {
	// Load the integration layer for MLP if it's active
	if ( class_exists( 'Inpsyde\MultilingualPress\MultilingualPress', false ) ) {
		require_once __DIR__ . '/integrations/multilingualpress/namespace.php';
		Integrations\MultilingualPress\bootstrap();
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
}

function ajax_select() : void {
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

	$attachments = [];
	$provider = get_provider();

	foreach ( $selected as $selection ) {
		$attachment = get_attachment_by_id( $selection['id'] );

		if ( $attachment ) {
			$attachments[ $selection['id'] ] = $attachment->ID;
			continue;
		}

		try {
			$media = get_media_from_selection( $selection );
			$attachment = insert_attachment( $media, $provider, $post_id );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		$attachments[ $selection['id'] ] = $attachment->ID;
	}

	wp_send_json_success( $attachments );
}

function get_media_from_selection( array $selection ) : Media {
	$media = new Media( $selection['id'], $selection['mime'] );

	foreach ( $selection as $key => $value ) {
		$media->{ $key } = $value;
	}

	return $media;
}

function insert_attachment( Media $media, Provider $provider, int $parent = null ) : WP_Post {
	global $amf_inserted;

	if ( empty( $amf_inserted ) ) {
		$amf_inserted = [];
	}

	$args = [
		'post_title' => $media->title,
		'post_parent' => $parent,
		'post_name' => $media->id,
		'post_content' => $media->description,
		'post_excerpt' => $media->caption,
		'post_mime_type' => $media->mime,
		'guid' => $media->url,
		'meta_input' => $media->meta,
	];

	$supports_dynamic_image_resizing = $provider->supports_dynamic_image_resizing();

	$attachment_id = wp_insert_attachment( $args, false, 0, true );

	if ( is_wp_error( $attachment_id ) ) {
		throw new Exception( $attachment_id->get_error_message() );
	}

	if ( ! empty( $media->alt ) ) {
		add_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $media->alt ) );
	}

	$metadata = wp_get_attachment_metadata( $attachment_id, true );
	if ( ! is_array( $metadata ) ) {
		$metadata = [];
	}

	$metadata['file'] = wp_slash( $media->filename );

	if ( ! empty( $media->width ) ) {
		$metadata['width'] = intval( $media->width );
	}

	if ( ! empty( $media->height ) ) {
		$metadata['height'] = intval( $media->height );
	}

	if ( ! empty( $media->sizes ) && ! $supports_dynamic_image_resizing ) {
		$metadata['sizes'] = array_map( function( array $size ) use ( $args ) : array {
			return [
				'file' => $size['url'],
				'width' => $size['width'],
				'height' => $size['height'],
				'mime-type' => $args['post_mime_type'],
			];
		}, $media->sizes );
	}

	wp_update_attachment_metadata( $attachment_id, $metadata );

	$attachment = get_post( $attachment_id );
	$meta = $media->amfMeta ?? [];

	unset( $media->amfMeta );

	do_action( 'amf/inserted_attachment', $attachment, $media, $meta );

	// Track AMF attachments.
	$amf_inserted[ $attachment->guid ] = $attachment;

	return $attachment;
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

function get_provider() : Provider {
	static $provider = null;

	if ( $provider ) {
		return $provider;
	}

	$provider = apply_filters( 'amf/provider', new BlankProvider() );

	return $provider;
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
		$items = get_provider()->request_items( $args );
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

function match_attachments_for_js( array $response, WP_Post $attachment ) : array {
	global $amf_inserted;

	if ( empty( $amf_inserted[ $attachment->guid ] ) ) {
		return new WP_Error( 'amf_error', __( 'Unable to prepare attachment response.', 'asset-manager-framework' ) );
	}

	if ( strpos( $attachment->post_name, 'amf-' ) === 0 ) {
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

	return wp_prepare_attachment_for_js( $amf_inserted[ $attachment->guid ] );
}

function handle_upload( $_, $file ) {
	global $amf_upload;

	$provider = get_provider();

	// Reset result of upload.
	$amf_upload = null;

	try {
		$upload = new FileUpload( $file );
		$media = $provider->handle_upload( $upload );

		$amf_upload = [
			'file' => $upload->tmp_name,
			'url' => $media->url,
			'type' => $media->mime,
		];

		// Hook into the subsequent wp_insert_attachment() call to remove the
		// new unwanted local post on shutdown.
		add_action( 'add_attachment', __NAMESPACE__ . '\\remove_local_attachment' );
	} catch ( Exception $e ) {
		$amf_upload = [
			'error' => $e->getMessage(),
		];

		return false;
	}

	return true;
}

function remove_local_attachment( int $post_id ) : void {
	remove_action( 'add_attachment', __NAMESPACE__ . '\\remove_local_attachment' );
	add_action( 'shutdown', function () use ( $post_id ) {
		wp_delete_attachment( $post_id, true );
	} );
}

function handle_upload_response() : array {
	global $amf_upload;

	if ( $amf_upload ) {
		return $amf_upload;
	}

	return [
		'error' => __( 'Could not upload to specified provider.', 'asset-manager-framework' ),
	];
}
