<?php
/**
 * Asset Manager Framework plugin for WordPress
 *
 * @package   asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use Exception;
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

	$supports_dynamic_image_resizing = get_provider()->supports_dynamic_image_resizing();

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
	$metadata = wp_get_attachment_metadata( $attachment_id );

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
			]
		);
	}

	wp_send_json_success( $items->toArray() );
}
