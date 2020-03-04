<?php
/**
 * Media item collection
 *
 * @package asset-manager-framework
 */

declare( strict_types=1 );

namespace AssetManagerFramework;

use ArrayIterator;
use IteratorAggregate;

final class MediaList implements IteratorAggregate {

	private $items = [];

	public function __construct( Media ...$items ) {
		$this->items = $items;
	}

	public function toArray() : array {
		return $this->items;
	}

	public function getIterator() : ArrayIterator {
		return new ArrayIterator( $this->items );
	}

}
