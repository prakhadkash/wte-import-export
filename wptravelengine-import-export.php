<?php
/**
 * Plugin Name:     WP Travel Engine - Import and Export
 * Plugin URI:      https://wptravelengine.com
 * Description:     WP Travel Engine Import and Export
 * Author:          WP Travel Engine
 * Author URI:      https://wptravelengine.com
 * Text Domain:     wptravelengine-import-export
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         wte-import-export
 */

 defined( 'ABSPATH' ) || exit;

define( 'WPTRAVELENGINE_IMPORT_EXPORT_FILE', __FILE__ );
define( 'WPTRAVELENGINE_IMPORT_EXPORT_ABSPATH', __DIR__ . '/' );


require WPTRAVELENGINE_IMPORT_EXPORT_ABSPATH . 'includes/export.php';
require WPTRAVELENGINE_IMPORT_EXPORT_ABSPATH . 'includes/import.php';
