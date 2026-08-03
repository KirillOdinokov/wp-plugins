<?php
/**
 * Plugin Name: Schema Odinokov
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Полная JSON-LD разметка: Organization, LocalBusiness, Product (WooCommerce), Article, BreadcrumbList, WebSite+SearchAction, FAQ, AggregateRating, og:image. Всё что Yoast Premium не даёт бесплатно.
 * Version:     1.3.1
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0-or-later
 * Text Domain: schema-odinokov
 * Requires at least: 5.6
 * Requires PHP: 7.2
 *
 * @package Schema_Odinokov
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SCHEMA_ODINOKOV_VERSION', '1.3.1' );
define( 'SCHEMA_ODINOKOV_FILE', __FILE__ );
define( 'SCHEMA_ODINOKOV_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCHEMA_ODINOKOV_URL', plugin_dir_url( __FILE__ ) );

require_once SCHEMA_ODINOKOV_DIR . 'includes/class-schema-odinokov.php';
require_once SCHEMA_ODINOKOV_DIR . 'includes/class-admin.php';
require_once SCHEMA_ODINOKOV_DIR . 'includes/class-sod-updater.php';

new SOD_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/schema-odinokov.json',
    SCHEMA_ODINOKOV_VERSION,
    array(
        'name'        => 'Schema Odinokov',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Полная JSON-LD разметка: Organization, LocalBusiness, Product, Article, BreadcrumbList, WebSite, FAQ, AggregateRating.',
    )
);

add_action( 'plugins_loaded', function () {
    \Odinokov\Schema\Plugin::instance()->boot();
    \Odinokov\Schema\Admin::instance()->register();
} );
