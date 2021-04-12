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
	 * Undocumented variable
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Undocumented variable
	 *
	 * @var int
	 */
	public $size;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	public $tmp_name;

	/**
	 * Undocumented variable
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
