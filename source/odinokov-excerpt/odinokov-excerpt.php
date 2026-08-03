<?php
/**
 * Plugin Name: Odinokov Excerpt
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Показывает короткое описание категорий и товаров при наведении в каталоге WooCommerce. Совместим с Porto.
 * Version:     1.0.1
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-excerpt
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ODEX_VERSION', '1.0.1' );
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

    const OPTION_KEY = 'odex_settings';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_after_shop_loop_item', [ $this, 'product_excerpt' ] );
        add_action( 'woocommerce_after_subcategory', [ $this, 'category_excerpt' ] );
    }

    public function get_defaults() {
        return [
            'product_enabled'  => 1,
            'category_enabled' => 1,
            'product_chars'    => 100,
            'category_chars'   => 150,
        ];
    }

    public function get_settings() {
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) $saved = [];
        return array_merge( $this->get_defaults(), $saved );
    }

    public function register_settings() {
        register_setting( 'odex_settings_group', self::OPTION_KEY, [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ] );
    }

    public function sanitize_settings( $input ) {
        $d = $this->get_defaults();
        $o = [];
        $o['product_enabled']  = ! empty( $input['product_enabled'] ) ? 1 : 0;
        $o['category_enabled'] = ! empty( $input['category_enabled'] ) ? 1 : 0;
        $o['product_chars']    = isset( $input['product_chars'] ) ? max( 10, min( 1000, (int) $input['product_chars'] ) ) : $d['product_chars'];
        $o['category_chars']   = isset( $input['category_chars'] ) ? max( 10, min( 1000, (int) $input['category_chars'] ) ) : $d['category_chars'];
        return $o;
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
        $s = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>Odinokov Excerpt — Настройки</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'odex_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Товары', 'odinokov-excerpt' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[product_enabled]" value="1" <?php checked( $s['product_enabled'], 1 ); ?>>
                                <?php esc_html_e( 'Показывать описание при наведении на товар', 'odinokov-excerpt' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="odex_product_chars"><?php esc_html_e( 'Символов в описании товара', 'odinokov-excerpt' ); ?></label></th>
                        <td>
                            <input type="number" id="odex_product_chars" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[product_chars]" value="<?php echo esc_attr( $s['product_chars'] ); ?>" min="10" max="1000" step="5" class="small-text">
                            <p class="description"><?php esc_html_e( 'По умолчанию: 100', 'odinokov-excerpt' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Подкатегории', 'odinokov-excerpt' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[category_enabled]" value="1" <?php checked( $s['category_enabled'], 1 ); ?>>
                                <?php esc_html_e( 'Показывать описание при наведении на подкатегорию', 'odinokov-excerpt' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="odex_category_chars"><?php esc_html_e( 'Символов в описании подкатегории', 'odinokov-excerpt' ); ?></label></th>
                        <td>
                            <input type="number" id="odex_category_chars" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[category_chars]" value="<?php echo esc_attr( $s['category_chars'] ); ?>" min="10" max="1000" step="5" class="small-text">
                            <p class="description"><?php esc_html_e( 'По умолчанию: 150', 'odinokov-excerpt' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
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
        $s = $this->get_settings();
        if ( ! $s['product_enabled'] ) return;

        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return;

        $desc = $product->get_short_description();
        if ( empty( $desc ) ) {
            $desc = $product->get_description();
        }
        $desc = wp_strip_all_tags( $desc );

        if ( empty( trim( $desc ) ) ) return;

        $limit = (int) $s['product_chars'];
        $original = mb_strlen( $desc );
        $desc = mb_substr( $desc, 0, $limit );
        if ( $original > $limit ) {
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
        $s = $this->get_settings();
        if ( ! $s['category_enabled'] ) return;

        if ( ! $category || empty( $category->term_id ) ) return;

        $desc = term_description( $category->term_id, 'product_cat' );
        $desc = wp_strip_all_tags( $desc );

        if ( empty( trim( $desc ) ) ) return;

        $limit = (int) $s['category_chars'];
        $original_len = mb_strlen( $desc );
        $desc = mb_substr( $desc, 0, $limit );
        if ( $original_len > $limit ) {
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
