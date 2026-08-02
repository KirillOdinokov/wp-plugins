<?php
/**
 * Plugin Name: Odinokov Geo Blocker
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Блокирует доступ к сайту с неразрешённых стран на уровне must-use плагина (до загрузки WordPress). Разрешённые страны: Россия, Беларусь, США, Украина, Казахстан, Узбекистан.
 * Version:     1.0.1
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ODGK_VERSION', '1.0.1' );
define( 'ODGK_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODGK_URL', plugin_dir_url( __FILE__ ) );
define( 'ODGK_DATA_DIR', ODGK_DIR . 'data' );
define( 'ODGK_DB_FILE', ODGK_DATA_DIR . '/GeoLite2-Country.mmdb' );

// Auto-updater
require_once ODGK_DIR . 'includes/class-odgk-updater.php';
new ODGK_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-geo-blocker.json',
    ODGK_VERSION,
    array(
        'name'        => 'Odinokov Geo Blocker',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Блокирует доступ к сайту с неразрешённых стран на уровне must-use плагина.',
    )
);

require_once ODGK_DIR . 'lib/class-maxmind-db.php';
require_once ODGK_DIR . 'includes/class-logger.php';
require_once ODGK_DIR . 'includes/class-core.php';
require_once ODGK_DIR . 'includes/class-ajax.php';
require_once ODGK_DIR . 'includes/class-admin.php';

WP_Geo_Blocker::instance();
WP_Geo_Blocker_Ajax::init();
WP_Geo_Blocker_Admin::init();

register_activation_hook( __FILE__, 'odgk_activate' );
register_deactivation_hook( __FILE__, 'odgk_deactivate' );

function odgk_activate() {
    $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
    if ( ! is_dir( $mu_dir ) ) wp_mkdir_p( $mu_dir );
    $source = ODGK_DIR . 'odinokov-geo-blocker-mu.php';
    $target = $mu_dir . '/odinokov-geo-blocker-mu.php';
    if ( file_exists( $source ) && ! file_exists( $target ) ) copy( $source, $target );
}

function odgk_deactivate() {
    $mu_file = WP_CONTENT_DIR . '/mu-plugins/odinokov-geo-blocker-mu.php';
    if ( file_exists( $mu_file ) ) unlink( $mu_file );
    // Clean up old mu file if exists
    $old_mu = WP_CONTENT_DIR . '/mu-plugins/wp-geo-blocker-mu.php';
    if ( file_exists( $old_mu ) ) unlink( $old_mu );
}
