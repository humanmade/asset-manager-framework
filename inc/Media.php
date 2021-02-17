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

	public $alt = '';
	public $amfMeta = [];
	public $attachmentExists = false;
	public $author = 0;
	public $authorLink = '';
	public $authorName = '';
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

	final public function set_url( string $url ) : self {
		$this->url = esc_url_raw( $url );

		return $this;
	}

	final public function set_title( string $title ) : self {
		$this->title = $title;

		return $this;
	}

	final public function set_filename( string $filename ) : self {
		$this->filename = $filename;

		return $this;
	}

	final public function set_link( string $link ) : self {
		$this->link = esc_url_raw( $link );

		return $this;
	}

	final public function set_alt( string $alt ) : self {
		$this->alt = $alt;

		return $this;
	}

	final public function set_description( string $description ) : self {
		$this->description = $description;

		return $this;
	}

	final public function set_caption( string $caption ) : self {
		$this->caption = $caption;

		return $this;
	}

	final public function set_name( string $name ) : self {
		$this->name = $name;

		return $this;
	}

	final public function set_date( int $date ) : self {
		$this->date = $date;
		$this->dateFormatted = gmdate( __( 'F j, Y', 'asset-manager-framework' ), $date );

		return $this;
	}

	final public function set_modified( int $modified ) : self {
		$this->modified = $modified;

		return $this;
	}

	final public function set_file_size( int $file_size ) : self {
		$this->filesizeInBytes = $file_size;
		$this->filesizeHumanReadable = size_format( $file_size );

		return $this;
	}

	final public function set_width( int $width ) : self {
		$this->width = $width;

		return $this;
	}

	final public function set_height( int $height ) : self {
		$this->height = $height;

		return $this;
	}

	final public function set_sizes( array $sizes ) : self {
		$this->sizes = $sizes;

		return $this;
	}

	final public function set_author( string $author_name, string $author_link = '' ) : self {
		$this->authorName = $author_name;
		$this->authorLink = $author_link;

		return $this;
	}

	final public function add_amf_meta( string $key, $value ) : self {
		$this->amfMeta[ $key ] = $value;

		return $this;
	}

	final public function set_amf_meta( array $meta ) : self {
		$this->amfMeta = $meta;

		return $this;
	}

}
