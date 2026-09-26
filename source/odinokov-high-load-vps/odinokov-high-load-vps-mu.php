<?php
/**
 * Odinokov High-Load VPS - Must-Use Plugin Loader
 *
 * This file is installed in mu-plugins. It includes the actual protection
 * from the plugin directory, so updates are picked up automatically.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ODHLV_MU_LOADED', true );

$odhlv_plugin_dir = WP_CONTENT_DIR . '/plugins/odinokov-high-load-vps/';

if ( ! defined( 'ODHLV_DIR' ) ) define( 'ODHLV_DIR', $odhlv_plugin_dir );
if ( ! defined( 'ODHLV_URL' ) ) define( 'ODHLV_URL', WP_CONTENT_URL . '/plugins/odinokov-high-load-vps/' );
if ( ! defined( 'ODHLV_DATA_DIR' ) ) define( 'ODHLV_DATA_DIR', ODHLV_DIR . 'data' );
if ( ! defined( 'ODHLV_CONFIG_FILE' ) ) define( 'ODHLV_CONFIG_FILE', ODHLV_DATA_DIR . '/config.json' );
if ( ! defined( 'ODHLV_RATELIMIT_FILE' ) ) define( 'ODHLV_RATELIMIT_FILE', ODHLV_DATA_DIR . '/ratelimit.json' );
if ( ! defined( 'ODHLV_BLOCK_LOG_FILE' ) ) define( 'ODHLV_BLOCK_LOG_FILE', ODHLV_DATA_DIR . '/block-log.json' );
if ( ! defined( 'ODHLV_TRAFFIC_FILE' ) ) define( 'ODHLV_TRAFFIC_FILE', ODHLV_DATA_DIR . '/traffic.json' );
if ( ! defined( 'ODHLV_DB_FILE' ) ) define( 'ODHLV_DB_FILE', ODHLV_DATA_DIR . '/GeoLite2-Country.mmdb' );

if ( file_exists( ODHLV_DIR . 'includes/class-odhlv-core.php' ) ) {
	require_once ODHLV_DIR . 'includes/class-odhlv-core.php';
	ODHLV_Core::run_mu();
}
