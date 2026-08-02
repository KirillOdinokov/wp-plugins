<?php
/**
 * Odinokov Geo Blocker - Must-Use Plugin Loader
 *
 * This file is installed in mu-plugins. It includes the actual blocker
 * from the plugin directory, so updates to the plugin are picked up automatically.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ODGK_MU_LOADED', true );

$odgk_plugin_dir = WP_CONTENT_DIR . '/plugins/odinokov-geo-blocker/';

if ( ! defined( 'ODGK_DIR' ) ) define( 'ODGK_DIR', $odgk_plugin_dir );
if ( ! defined( 'ODGK_URL' ) ) define( 'ODGK_URL', WP_CONTENT_URL . '/plugins/odinokov-geo-blocker/' );
if ( ! defined( 'ODGK_DATA_DIR' ) ) define( 'ODGK_DATA_DIR', ODGK_DIR . 'data' );
if ( ! defined( 'ODGK_DB_FILE' ) ) define( 'ODGK_DB_FILE', ODGK_DATA_DIR . '/GeoLite2-Country.mmdb' );

if ( file_exists( ODGK_DIR . 'lib/class-maxmind-db.php' ) ) {
    require_once ODGK_DIR . 'lib/class-maxmind-db.php';
}

if ( file_exists( ODGK_DIR . 'includes/class-mu-blocker.php' ) ) {
    require_once ODGK_DIR . 'includes/class-mu-blocker.php';
    WP_Geo_MU_Blocker::check();
}
