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

	final public function set_length( string $duration ) : self {
		$this->fileLength = $duration;
		$this->fileLengthHumanReadable = human_readable_duration( $duration );

		return $this;
	}

	final public function set_thumb( string $thumb ) : self {
		$this->thumb = [
			'src' => $thumb,
			'width' => 400,
			'height' => 400,
		];

		return $this;
	}

	final public function set_bitrate( int $bitrate, string $bitrate_mode = '' ) : self {
		$this->add_meta( 'bitrate', $bitrate );
		$this->add_meta( 'bitrate_mode', $bitrate_mode );

		return $this;
	}

}
