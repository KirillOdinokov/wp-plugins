<?php
/**
 * Plugin Name: Odinokov Table View
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Автоматический табличный вид для категорий WooCommerce с однотипными товарами. Управление выводом подкатегорий/товаров. Совместим с Porto.
 * Version:     1.0.2
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-table-view
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OTV_VERSION', '1.0.2' );
define( 'OTV_DIR', plugin_dir_path( __FILE__ ) );
define( 'OTV_URL', plugin_dir_url( __FILE__ ) );

require_once OTV_DIR . 'includes/class-otv-updater.php';

new OTV_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-table-view.json',
    OTV_VERSION,
    array(
        'name'        => 'Odinokov Table View',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Автоматический табличный вид для категорий с однотипными товарами.',
    )
);

class Odinokov_Table_View {

    private static $instance = null;
    private $is_table_view = false;
    private $override_display = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_post_otv_force_check', [ $this, 'force_check' ] );
        add_action( 'wp', [ $this, 'check_category' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_before_shop_loop', [ $this, 'apply_table_view' ], 1 );
    }

    public function add_admin_menu() {
        global $menu; $exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) { $exists = true; break; }
            }
        }
        if ( ! $exists ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [ $this, 'dashboard' ], 'dashicons-admin-settings', 30 );
        add_submenu_page( 'odinokov-plugins', 'Table View', 'Table View', 'manage_options', 'odinokov-table-view', [ $this, 'render_admin_page' ] );
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov Table View</h3><p>Автоматический табличный вид для категорий.</p>
            </div>
        </div></div>
        <?php
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>Odinokov Table View</h1>
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;max-width:700px;margin-top:16px;">
                <p><?php esc_html_e( 'Плагин автоматически применяет табличный вид к категориям, где:', 'odinokov-table-view' ); ?></p>
                <ul style="list-style:disc;padding-left:20px;">
                    <li><?php esc_html_e( 'Категория конечная или содержит не более 2 дочерних подкатегорий (без внуков)', 'odinokov-table-view' ); ?></li>
                    <li><?php esc_html_e( 'Более 5 товаров в категории', 'odinokov-table-view' ); ?></li>
                    <li><?php esc_html_e( 'Не менее 80% товаров имеют одинаковое изображение', 'odinokov-table-view' ); ?></li>
                </ul>
                <p><?php esc_html_e( 'Также управляет выводом подкатегорий/товаров:', 'odinokov-table-view' ); ?></p>
                <ul style="list-style:disc;padding-left:20px;">
                    <li><?php esc_html_e( '3–5 подкатегорий → Both (товары и подкатегории)', 'odinokov-table-view' ); ?></li>
                    <li><?php esc_html_e( '6+ подкатегорий → только Subcategories', 'odinokov-table-view' ); ?></li>
                </ul>
                <p style="color:#666;"><?php esc_html_e( 'Настроек не требуется. Плагин работает автоматически.', 'odinokov-table-view' ); ?></p>
            </div>
        </div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
            <?php wp_nonce_field( 'otv_force_check', 'otv_force_check_nonce' ); ?>
            <input type="hidden" name="action" value="otv_force_check">
            <?php submit_button( __( 'Проверить обновления', 'odinokov-table-view' ), 'secondary' ); ?>
        </form>
        <?php
    }

    public function force_check() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'otv_force_check', 'otv_force_check_nonce' );
        delete_transient( 'otv_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-table-view.json' ) );
        set_site_transient( 'update_plugins', null );
        wp_safe_redirect( admin_url( 'plugins.php?otv_force_check_done=1' ) );
        exit;
    }

    public function enqueue_assets() {
        if ( ! $this->is_table_view ) return;
        wp_enqueue_style( 'otv-table', OTV_URL . 'assets/css/table.css', [], OTV_VERSION );
    }

    public function check_category() {
        if ( ! is_product_category() ) return;

        $term = get_queried_object();
        if ( ! $term || ! isset( $term->term_id ) ) return;

        $term_id = $term->term_id;
        $cache_key = 'otv_check_' . $term_id;
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            $this->is_table_view = ! empty( $cached['table'] );
            $this->override_display = $cached['display'] ?? null;
            return;
        }

        $result = [ 'table' => false, 'display' => null ];

        $subcats = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $term_id,
            'hide_empty' => false,
        ] );

        $subcat_count = is_array( $subcats ) ? count( $subcats ) : 0;

        if ( $subcat_count >= 6 ) {
            $result['display'] = 'subcategories';
        } elseif ( $subcat_count >= 3 ) {
            $result['display'] = 'both';
        }

        $is_shallow = true;
        if ( $subcat_count > 0 ) {
            if ( $subcat_count > 2 ) {
                $is_shallow = false;
            } else {
                foreach ( $subcats as $sc ) {
                    $grandchildren = get_terms( [
                        'taxonomy'   => 'product_cat',
                        'parent'     => $sc->term_id,
                        'hide_empty' => false,
                        'number'     => 1,
                        'fields'     => 'ids',
                    ] );
                    if ( ! empty( $grandchildren ) ) {
                        $is_shallow = false;
                        break;
                    }
                }
            }
        }

        if ( $is_shallow ) {
            $product_count = $this->get_product_count( $term_id );
            if ( $product_count > 5 ) {
                if ( $this->check_image_similarity( $term_id ) ) {
                    $result['table'] = true;
                }
            }
        }

        set_transient( $cache_key, $result, HOUR_IN_SECONDS );

        $this->is_table_view = $result['table'];
        $this->override_display = $result['display'];
    }

    private function get_product_count( $term_id ) {
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'product' AND p.post_status = 'publish'
             WHERE tr.term_taxonomy_id = %d",
            $term_id
        ) );
        return (int) $count;
    }

    private function check_image_similarity( $term_id ) {
        global $wpdb;

        $product_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'product' AND p.post_status = 'publish'
             WHERE tr.term_taxonomy_id = %d",
            $term_id
        ) );

        if ( count( $product_ids ) < 2 ) return false;

        $image_counts = [];
        foreach ( $product_ids as $pid ) {
            $thumb_id = get_post_thumbnail_id( $pid );
            $key = $thumb_id ? (int) $thumb_id : 'none';
            $image_counts[ $key ] = ( $image_counts[ $key ] ?? 0 ) + 1;
        }

        $max_count = max( $image_counts );
        $total = count( $product_ids );
        $ratio = $max_count / $total;

        return $ratio >= 0.8;
    }

    public function apply_table_view() {
        if ( ! $this->is_table_view ) return;

        global $woocommerce_loop;
        $woocommerce_loop['category-view'] = 'table';

        remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
        remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );

        add_action( 'woocommerce_before_shop_loop_item', [ $this, 'table_row_open' ], 1 );
        add_action( 'woocommerce_before_shop_loop_item', [ $this, 'table_cell_image' ], 5 );
        add_action( 'woocommerce_before_shop_loop_item', [ $this, 'table_cell_name_open' ], 10 );
        add_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
        add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'table_cell_name_close' ], 1 );
        add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'table_cell_price' ], 5 );
        add_action( 'woocommerce_after_shop_loop_item', [ $this, 'table_cell_cart' ], 5 );
        add_action( 'woocommerce_after_shop_loop_item', [ $this, 'table_row_close' ], 15 );

        add_action( 'woocommerce_before_shop_loop', [ $this, 'table_open' ], 0 );
        add_action( 'woocommerce_after_shop_loop', [ $this, 'table_close' ], 999 );
    }

    public function table_open() {
        echo '<table class="otv-products-table"><thead><tr>';
        echo '<th class="otv-th-img">' . esc_html__( 'Фото', 'odinokov-table-view' ) . '</th>';
        echo '<th class="otv-th-name">' . esc_html__( 'Наименование', 'odinokov-table-view' ) . '</th>';
        echo '<th class="otv-th-price">' . esc_html__( 'Цена', 'odinokov-table-view' ) . '</th>';
        echo '<th class="otv-th-cart">' . esc_html__( 'В корзину', 'odinokov-table-view' ) . '</th>';
        echo '</tr></thead><tbody>';
    }

    public function table_close() {
        echo '</tbody></table>';
    }

    public function table_row_open() {
        global $product;
        $classes = $product && is_a( $product, 'WC_Product' ) ? wc_get_product_class( '', $product ) : '';
        echo '<tr class="' . esc_attr( implode( ' ', $classes ) ) . '">';
    }

    public function table_cell_image() {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return;
        echo '<td class="otv-td-img" data-title="' . esc_attr__( 'Фото', 'odinokov-table-view' ) . '">';
        echo '<a href="' . esc_url( $product->get_permalink() ) . '">';
        echo $product->get_image( 'thumbnail', [ 'class' => 'otv-table-img', 'loading' => 'lazy' ] );
        echo '</a>';
        echo '</td>';
    }

    public function table_cell_name_open() {
        global $product;
        echo '<td class="otv-td-name" data-title="' . esc_attr__( 'Наименование', 'odinokov-table-view' ) . '">';
        echo '<a href="' . esc_url( $product->get_permalink() ) . '" class="otv-table-link">';
    }

    public function table_cell_name_close() {
        global $product;
        echo '</a>';
        if ( $product && is_a( $product, 'WC_Product' ) && $product->get_sku() ) {
            echo '<span class="otv-sku">' . esc_html( $product->get_sku() ) . '</span>';
        }
        echo '</td>';
    }

    public function table_cell_price() {
        echo '<td class="otv-td-price" data-title="' . esc_attr__( 'Цена', 'odinokov-table-view' ) . '">';
        woocommerce_template_loop_price();
        echo '</td>';
    }

    public function table_cell_cart() {
        echo '<td class="otv-td-cart">';
        woocommerce_template_loop_add_to_cart();
        echo '</td>';
    }

    public function table_row_close() {
        echo '</tr>';
    }
}

add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        Odinokov_Table_View::get_instance();
    }
} );
