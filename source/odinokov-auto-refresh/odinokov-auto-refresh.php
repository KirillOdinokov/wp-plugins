<?php
/**
 * Plugin Name: Odinokov Auto Refresh
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Автоматически обновляет дату last modified у записей, страниц, товаров WooCommerce, категорий и меток, чтобы сайт не выглядел заброшенным.
 * Version:     1.0.1
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0+
 * Text Domain: odinokov-auto-refresh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OAR_VERSION', '1.0.1' );
define( 'OAR_PLUGIN_FILE', __FILE__ );
define( 'OAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OAR_OPTION_KEY', 'oar_settings' );
define( 'OAR_STATE_KEY', 'oar_state' );

require_once OAR_PLUGIN_DIR . 'includes/class-oar-activator.php';
require_once OAR_PLUGIN_DIR . 'includes/class-oar-plugin-updater.php';

new OAR_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-auto-refresh.json',
    OAR_VERSION,
    array(
        'name'        => 'Odinokov Auto Refresh',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Автоматически обновляет дату last modified у записей, страниц, товаров WooCommerce, категорий и меток, чтобы сайт не выглядел заброшенным.',
    )
);

register_activation_hook( __FILE__, array( 'OAR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OAR_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', function() {
    require_once OAR_PLUGIN_DIR . 'includes/class-oar-processor.php';
    require_once OAR_PLUGIN_DIR . 'includes/class-oar-cron.php';
    require_once OAR_PLUGIN_DIR . 'includes/class-oar-admin.php';

    OAR_Cron::instance();
    if ( is_admin() ) {
        OAR_Admin::instance();
    }
} );
