<?php
/**
 * Generic media item
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

// wp_prepare_attachment_for_js

class Media {

	public $attachmentExists = false;
	public $alt = '';
	public $author = 0;
	public $caption = '';
	public $date = null;
	public $dateFormatted = null;
	public $description = '';
	public $editLink = false;
	public $filename = '';
	public $filesizeHumanReadable = null;
	public $filesizeInBytes = null;
	public $height = null;
	public $icon = '';
	public $id = '';
	public $link = '';
	public $menuOrder = 0;
	public $meta = false;
	public $mime = '';
	public $modified = null;
	public $name = '';
	public $nonces = [
		'update' => false,
		'delete' => false,
		'edit' => false,
	];
	public $sizes = [];
	public $status = 'inherit';
	public $title = '';
	public $uploadedTo = 0;
	public $url = '';
	public $width = null;

	public function __construct( string $id, string $mime ) {
		$this->id = sprintf(
			'amf-%s',
			$id
		);
		$this->mime = $mime;
		$this->icon = wp_mime_type_icon( $mime );

		list( $this->type, $this->subtype ) = explode( '/', $mime );
	}

	final public function set_url( string $url ) : void {
		$this->url = esc_url_raw( $url );
	}

	final public function set_title( string $title ) : void {
		$this->title = $title;
	}

	final public function set_filename( string $filename ) : void {
		$this->filename = $filename;
	}

	final public function set_link( string $link ) : void {
		$this->link = esc_url_raw( $link );
	}

	final public function set_alt( string $alt ) : void {
		$this->alt = $alt;
	}

	final public function set_description( string $description ) : void {
		$this->description = $description;
	}

	final public function set_caption( string $caption ) : void {
		$this->caption = $caption;
	}

	final public function set_name( string $name ) : void {
		$this->name = $name;
	}

	final public function set_date( int $date ) : void {
		$this->date = $date;
		$this->dateFormatted = gmdate( __( 'F j, Y', 'asset-manager-framework' ), $date );
	}

	final public function set_modified( int $modified ) : void {
		$this->modified = $modified;
	}

	final public function set_file_size( int $file_size ) : void {
		$this->filesizeInBytes = $file_size;
		$this->filesizeHumanReadable = size_format( $file_size );
	}

	final public function set_width( int $width ) : void {
		$this->width = $width;
	}

	final public function set_height( int $height ) : void {
		$this->height = $height;
	}

	final public function set_sizes( array $sizes ) : void {
		$this->sizes = $sizes;
	}

}
