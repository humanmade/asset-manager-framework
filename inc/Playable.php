<?php
/**
 * Playable media item
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

abstract class Playable extends Media {

	public $fileLength = '';
	public $fileLengthHumanReadable = '';
	public $meta = [];

	final public function set_length( string $duration ) : void {
		$this->fileLength = $duration;
		$this->fileLengthHumanReadable = human_readable_duration( $duration );
	}

	final public function set_meta( array $meta ) : void {
		$this->meta = $meta;
	}

	final public function set_image( string $image ) : void {
		$this->image = [
			'src' => $image,
			'width' => 400,
			'height' => 400,
		];
	}

	final public function set_thumb( string $thumb ) : void {
		$this->thumb = [
			'src' => $thumb,
			'width' => 400,
			'height' => 400,
		];
	}

}
