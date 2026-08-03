<?php
/**
 * Plugin Name: Odinokov Excerpt
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Показывает короткое описание категорий и товаров при наведении в каталоге WooCommerce. Совместим с Porto.
 * Version:     1.0.0
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-excerpt
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ODEX_VERSION', '1.0.0' );
define( 'ODEX_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODEX_URL', plugin_dir_url( __FILE__ ) );

require_once ODEX_DIR . 'includes/class-odex-updater.php';

new ODEX_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-excerpt.json',
    ODEX_VERSION,
    array(
        'name'        => 'Odinokov Excerpt',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Короткое описание категорий и товаров при наведении в каталоге WooCommerce.',
    )
);

class Odinokov_Excerpt {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_after_shop_loop_item', [ $this, 'product_excerpt' ] );
        add_action( 'woocommerce_after_subcategory', [ $this, 'category_excerpt' ] );
    }

    public function add_admin_menu() {
        global $menu; $exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) { $exists = true; break; }
            }
        }
        if ( ! $exists ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [ $this, 'dashboard' ], 'dashicons-admin-settings', 30 );
        add_submenu_page( 'odinokov-plugins', 'Excerpt', 'Excerpt', 'manage_options', 'odinokov-excerpt', [ $this, 'render_admin_page' ] );
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov Excerpt</h3><p>Описание категорий и товаров при наведении.</p>
            </div>
        </div></div>
        <?php
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>Odinokov Excerpt</h1>
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;max-width:600px;margin-top:16px;">
                <p><?php esc_html_e( 'Плагин добавляет всплывающее описание при наведении на товары и подкатегории в каталоге WooCommerce.', 'odinokov-excerpt' ); ?></p>
                <ul style="list-style:disc;padding-left:20px;">
                    <li><?php esc_html_e( 'Подкатегории — первые 150 символов описания', 'odinokov-excerpt' ); ?></li>
                    <li><?php esc_html_e( 'Товары — первые 100 символов краткого описания', 'odinokov-excerpt' ); ?></li>
                </ul>
                <p style="color:#666;"><?php esc_html_e( 'Настроек не требуется. Плагин работает автоматически.', 'odinokov-excerpt' ); ?></p>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets() {
        if ( ! function_exists( 'is_woocommerce' ) ) return;
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_tax( 'product_cat' ) ) return;

        wp_enqueue_style( 'odex-tooltip', ODEX_URL . 'assets/css/tooltip.css', [], ODEX_VERSION );
        wp_enqueue_script( 'odex-tooltip', ODEX_URL . 'assets/js/tooltip.js', [], ODEX_VERSION, true );
        wp_localize_script( 'odex-tooltip', 'ODEX', [
            'i18n_goto_cat'    => __( 'Перейти в категорию', 'odinokov-excerpt' ),
            'i18n_goto_prod'   => __( 'Перейти к товару', 'odinokov-excerpt' ),
        ] );
    }

    public function product_excerpt() {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return;

        $desc = $product->get_short_description();
        if ( empty( $desc ) ) {
            $desc = $product->get_description();
        }
        $desc = wp_strip_all_tags( $desc );

        if ( empty( trim( $desc ) ) ) return;

        $desc = mb_substr( $desc, 0, 100 );
        if ( 100 === mb_strlen( wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ) ) ) {
            $desc .= '…';
        }

        $url = get_permalink( $product->get_id() );

        printf(
            '<div class="odex-tooltip-data" data-odex-excerpt="%s" data-odex-url="%s" data-odex-type="product" style="display:none;"></div>',
            esc_attr( $desc ),
            esc_url( $url )
        );
    }

    public function category_excerpt( $category ) {
        if ( ! $category || empty( $category->term_id ) ) return;

        $desc = term_description( $category->term_id, 'product_cat' );
        $desc = wp_strip_all_tags( $desc );

        if ( empty( trim( $desc ) ) ) return;

        $original_len = mb_strlen( $desc );
        $desc = mb_substr( $desc, 0, 150 );
        if ( $original_len > 150 ) {
            $desc .= '…';
        }

        $url = get_term_link( $category );

        printf(
            '<div class="odex-tooltip-data" data-odex-excerpt="%s" data-odex-url="%s" data-odex-type="category" style="display:none;"></div>',
            esc_attr( $desc ),
            esc_url( $url )
        );
    }
}

add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        Odinokov_Excerpt::get_instance();
    }
} );
