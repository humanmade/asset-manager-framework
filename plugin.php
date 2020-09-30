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
 * Version:      0.8.1
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

require_once __DIR__ . '/inc/namespace.php';

bootstrap();
