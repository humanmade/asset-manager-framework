<?php
/**
 * Representation of a single element from the `$_FILES` superglobal in PHP.
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

class FileUpload {

	/**
	 * Uploaded file name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * File mime type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * File size in bytes.
	 *
	 * @var int
	 */
	public $size;

	/**
	 * Temporary file upload path.
	 *
	 * @var string
	 */
	public $tmp_name;

	/**
	 * File upload error code.
	 *
	 * @var int
	 */
	public $error;

	public function __construct( array $file ) {
		$this->name = $file['name'];
		$this->type = $file['type'];
		$this->size = $file['size'];
		$this->tmp_name = $file['tmp_name'];
		$this->error = $file['error'];
	}
}
