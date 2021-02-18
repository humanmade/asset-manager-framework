<?php
/**
 * Audio media item
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

class Audio extends Playable {

	public $album = '';
	public $artist = '';

	final public function set_album( string $album ) : self {
		$this->album = $album;
		$this->meta['album'] = $album;

		return $this;
	}

	final public function set_artist( string $artist ) : self {
		$this->artist = $artist;
		$this->meta['artist'] = $artist;

		return $this;
	}

	final public function set_bitrate( int $bitrate, string $bitrate_mode = '' ) : self {
		$this->meta['bitrate'] = $bitrate;
		$this->meta['bitrate_mode'] = $bitrate_mode;

		return $this;
	}
}
