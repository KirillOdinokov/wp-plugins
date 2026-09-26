<?php
/**
 * Plugin Name: Odinokov High-Load VPS
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Защита high-load VPS от ботов и парсеров: rate limiting по IP, блокировка глубокой пагинации (/wp-json/wc/store), длинных filter-строк, вредоносных User-Agent. Geo-блокировка по странам (опционально). Googlebot/Bingbot/YandexBot в белом списке.
 * Version:     1.0.1
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0+
 * Text Domain: odinokov-high-load-vps
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ODHLV_VERSION', '1.0.1' );
define( 'ODHLV_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODHLV_URL', plugin_dir_url( __FILE__ ) );
define( 'ODHLV_DATA_DIR', ODHLV_DIR . 'data' );
define( 'ODHLV_CONFIG_FILE', ODHLV_DATA_DIR . '/config.json' );
define( 'ODHLV_RATELIMIT_FILE', ODHLV_DATA_DIR . '/ratelimit.json' );
define( 'ODHLV_BLOCK_LOG_FILE', ODHLV_DATA_DIR . '/block-log.json' );
define( 'ODHLV_TRAFFIC_FILE', ODHLV_DATA_DIR . '/traffic.json' );
define( 'ODHLV_DB_FILE', ODHLV_DATA_DIR . '/GeoLite2-Country.mmdb' );

require_once ODHLV_DIR . 'includes/class-odhlv-updater.php';

new ODHLV_Plugin_Updater(
	__FILE__,
	'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-high-load-vps.json',
	ODHLV_VERSION,
	array(
		'name'        => 'Odinokov High-Load VPS',
		'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
		'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
		'description' => 'Защита от ботов: rate limiting, блокировка пагинации/фильтров, User-Agent, geo.',
	)
);

require_once ODHLV_DIR . 'includes/class-odhlv-core.php';
require_once ODHLV_DIR . 'includes/class-odhlv-admin.php';

add_action( 'plugins_loaded', function () {
	ODHLV_Admin::init();
} );

register_activation_hook( __FILE__, 'odhlv_activate' );
register_deactivation_hook( __FILE__, 'odhlv_deactivate' );

function odhlv_activate() {
	$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
	if ( ! is_dir( $mu_dir ) ) wp_mkdir_p( $mu_dir );
	$source = ODHLV_DIR . 'odinokov-high-load-vps-mu.php';
	$target = $mu_dir . '/odinokov-high-load-vps-mu.php';
	if ( file_exists( $source ) && ! file_exists( $target ) ) copy( $source, $target );
	ODHLV_Core::save_default_config();
}

function odhlv_deactivate() {
	$mu_file = WP_CONTENT_DIR . '/mu-plugins/odinokov-high-load-vps-mu.php';
	if ( file_exists( $mu_file ) ) @unlink( $mu_file );
}
