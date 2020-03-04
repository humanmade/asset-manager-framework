<?php
/**
 * Asset Manager Framework plugin for WordPress
 *
 * @package   asset-manager-framework
 * @copyright 2020 Human Made
 * @license   GPL v2 or later
 *
 * Plugin Name:  Asset Manager Framework
 * Description:  A framework for overriding the WordPress media library with an external asset provider.
 * Version:      0.1.0
 * Plugin URI:   https://github.com/humanmade/asset-manager-framework
 * Author:       Human Made
 * Author URI:   https://humanmade.com/
 * Text Domain:  asset-manager-framework
 * Requires PHP: 7.2
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use Exception;
use WP_Post;
use WP_Query;

add_action( 'plugins_loaded', function() : void {
	require_once 'inc/Provider.php';
	require_once 'inc/BlankProvider.php';
	require_once 'inc/Media.php';
	require_once 'inc/Playable.php';
	require_once 'inc/Image.php';
	require_once 'inc/Audio.php';
	require_once 'inc/Video.php';
	require_once 'inc/Document.php';
	require_once 'inc/MediaList.php';

	do_action( 'amf/loaded' );
} );

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );

function enqueue_scripts() : void {
	if ( ! wp_script_is( 'media-views', 'enqueued' ) ) {
		return;
	}

	$asset_file = require plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

	wp_enqueue_script(
		'asset-manager-framework',
		plugin_dir_url( __FILE__ ) . 'build/index.js',
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

// Replace the default wp_ajax_query_attachments handler with our own.
remove_action( 'wp_ajax_query-attachments', 'wp_ajax_query_attachments', 1 );
add_action( 'wp_ajax_query-attachments', __NAMESPACE__ . '\\ajax_query_attachments', 1 );

// Handle the 'select' event in the media manager.
add_action( 'wp_ajax_amf-select', __NAMESPACE__ . '\\ajax_select' );

function ajax_select() : void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selection = isset( $_REQUEST['selection'] ) ? wp_unslash( (array) $_REQUEST['selection'] ) : [];

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = intval( $_REQUEST['post'] ?? 0 );

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error();
	}

	$attachment = get_attachment_by_id( $selection['id'] );

	if ( $attachment ) {
		wp_send_json_success( $attachment );
	}

	$args = [
		'post_title' => $selection['title'],
		'post_parent' => $post_id,
		'post_name' => $selection['id'],
		'post_content' => $selection['description'],
		'post_excerpt' => $selection['caption'],
		'post_mime_type' => $selection['mime'],
		'guid' => $selection['url'],
	];

	$attachment_id = wp_insert_attachment( $args, false, 0, true );

	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( $attachment_id );
	}

	if ( ! empty( $selection['alt'] ) ) {
		add_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $selection['alt'] ) );
	}

	$metadata = [
		'file' => $selection['filename'],
	];

	if ( ! empty( $selection['width'] ) ) {
		$metadata['width'] = intval( $selection['width'] );
	}

	if ( ! empty( $selection['height'] ) ) {
		$metadata['height'] = intval( $selection['height'] );
	}

	wp_update_attachment_metadata( $attachment_id, wp_slash( $metadata ) );

	wp_send_json_success( get_post( $attachment_id ) );
}

add_filter( 'get_attached_file', function( $file, int $attachment_id ) : string {
	$metadata = wp_get_attachment_metadata( $attachment_id );

	return $metadata['file'] ?? '';
}, 10, 2 );

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

	$provider_class = apply_filters( 'amf/provider_class', __NAMESPACE__ . '\BlankProvider' );
	$provider = new $provider_class();

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
