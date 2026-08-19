<?php
/**
 * Plugin Name: Odinokov Table View
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Автоматический табличный вид для категорий WooCommerce с однотипными товарами. Управление выводом подкатегорий/товаров. Совместим с Porto.
 * Version:     1.0.57
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-table-view
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OTV_VERSION', '1.0.57' );
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
    private $show_hover_desc = false;
    private $subcats_rendered = false;
    private $order_buttons = [];

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_post_otv_force_check', [ $this, 'force_check' ] );
        add_action( 'wp', [ $this, 'check_category' ], 0 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'body_class', [ $this, 'body_class' ] );
        add_action( 'woocommerce_before_shop_loop', [ $this, 'render_subcategories' ], 85 );
        add_action( 'woocommerce_product_query', [ $this, 'hide_products' ], 99 );
        add_action( 'woocommerce_after_shop_loop_item', [ $this, 'output_product_short_desc' ], 20 );
        add_filter( 'woocommerce_get_price_html', [ $this, 'extract_order_button' ], 30, 2 );
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
                    <li><?php esc_html_e( 'Не менее 60% товаров имеют одинаковое изображение', 'odinokov-table-view' ); ?></li>
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
        if ( ! $this->is_table_view && null === $this->override_display && ! $this->show_hover_desc ) return;

        wp_enqueue_style( 'otv-table', OTV_URL . 'assets/css/table.css', [], OTV_VERSION );
        wp_enqueue_script( 'otv-table', OTV_URL . 'assets/js/table.js', [], OTV_VERSION, true );

        $subcat_descs = [];

        if ( null !== $this->override_display ) {
            $term = get_queried_object();
            if ( $term && isset( $term->term_id ) ) {
                $subcats = get_terms( [
                    'taxonomy'   => 'product_cat',
                    'parent'     => $term->term_id,
                    'hide_empty' => false,
                ] );
                if ( ! empty( $subcats ) && ! is_wp_error( $subcats ) ) {
                    foreach ( $subcats as $sc ) {
                        $desc = term_description( $sc->term_id );
                        $desc = wp_strip_all_tags( $desc, true );
                        $desc = trim( $desc );
                        if ( ! empty( $desc ) ) {
                            $subcat_descs[ $sc->slug ] = mb_substr( $desc, 0, 200 );
                        }
                    }
                }
            }
        }

        wp_localize_script( 'otv-table', 'otvData', [
            'subcatDescs' => $subcat_descs,
            'isTable'     => $this->is_table_view,
        ] );
    }

    public function body_class( $classes ) {
        if ( $this->is_table_view ) {
            $classes[] = 'otv-table-view';
        }
        if ( null !== $this->override_display || $this->show_hover_desc ) {
            $classes[] = 'otv-active';
        }
        return $classes;
    }

    public function check_category() {
        if ( is_product_tag() ) {
            $this->show_hover_desc = true;
            return;
        }

        if ( ! is_product_category() ) return;

        $term = get_queried_object();
        if ( ! $term || ! isset( $term->term_id ) ) return;

        $term_id = $term->term_id;
        $cache_key = 'otv_check_' . $term_id;

        if ( isset( $_GET['otv_clear'] ) && current_user_can( 'manage_options' ) ) {
            delete_transient( $cache_key );
        }

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            $this->is_table_view = ! empty( $cached['table'] );
            $this->override_display = $cached['display'] ?? null;
            $this->show_hover_desc = ! $this->is_table_view;
            return;
        }

        $result = $this->do_check( $term_id );
        set_transient( $cache_key, $result, HOUR_IN_SECONDS );

        $this->is_table_view = $result['table'];
        $this->override_display = $result['display'];
        $this->show_hover_desc = ! $this->is_table_view;
    }

    public function render_subcategories() {
        if ( null === $this->override_display ) return;
        if ( ! is_product_category() ) return;
        if ( wp_doing_ajax() ) return;
        if ( $this->subcats_rendered ) return;
        $this->subcats_rendered = true;

        $term = get_queried_object();
        if ( ! $term || ! isset( $term->term_id ) ) return;

        // Если Porto уже рендерит подкатегории нативно (display_type = subcategories/both),
        // пропускаем ручной вывод, чтобы не дублировать.
        $native_display = get_term_meta( $term->term_id, 'display_type', true );
        if ( 'subcategories' === $native_display || 'both' === $native_display ) {
            return;
        }

        $subcats = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $term->term_id,
            'hide_empty' => false,
        ] );

        if ( empty( $subcats ) || is_wp_error( $subcats ) ) return;

        echo '<div class="otv-subcategories-wrapper">';

        global $woocommerce_loop;
        $woocommerce_loop['category-view'] = 'grid';
        wc_set_loop_prop( 'columns', 4 );

        woocommerce_product_loop_start();

        foreach ( $subcats as $subcat ) {
            wc_get_template( 'content-product_cat.php', [ 'category' => $subcat ] );
        }

        woocommerce_product_loop_end();

        echo '</div>';
    }

    public function hide_products( $q ) {
        if ( null === $this->override_display ) return;
        if ( 'subcategories' !== $this->override_display ) return;

        $q->set( 'post__in', [ 0 ] );
    }

    public function extract_order_button( $price_html, $product ) {
        if ( $this->is_table_view ) return $price_html;
        if ( null === $this->override_display && ! $this->show_hover_desc ) return $price_html;
        if ( ! $product instanceof WC_Product ) return $price_html;

        if ( preg_match( '/<div class="oso-order-btn-wrap">.*?<\/div>/s', $price_html, $m ) ) {
            $this->order_buttons[ $product->get_id() ] = $m[0];
            $price_html = str_replace( $m[0], '', $price_html );
        }

        return $price_html;
    }

    public function output_product_short_desc() {
        if ( $this->is_table_view ) return;
        if ( null === $this->override_display && ! $this->show_hover_desc ) return;

        global $product;
        if ( ! is_a( $product, 'WC_Product' ) ) return;

        $desc = $product->get_short_description();
        $desc = wp_strip_all_tags( $desc, true );
        $desc = trim( $desc );

        $button_html = $this->order_buttons[ $product->get_id() ] ?? '';

        if ( empty( $desc ) ) {
            if ( $button_html ) {
                echo $button_html;
            }
            return;
        }

        $desc = mb_substr( $desc, 0, 200 );
        $link = get_permalink( $product->get_id() );
        echo '<div class="otv-hover-desc">' . esc_html( $desc ) . ' <a href="' . esc_url( $link ) . '" class="otv-read-more">читать далее &gt;</a>';
        if ( $button_html ) {
            echo '<div class="otv-hover-actions">' . $button_html . '</div>';
        }
        echo '</div>';
    }

    private function do_check( $term_id ) {
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

        return $result;
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
            if ( ! $thumb_id ) continue;
            $key = (int) $thumb_id;
            $image_counts[ $key ] = ( $image_counts[ $key ] ?? 0 ) + 1;
        }

        if ( empty( $image_counts ) ) return false;

        $max_count = max( $image_counts );
        $total_with_images = array_sum( $image_counts );
        $ratio = $max_count / $total_with_images;

        return $ratio >= 0.6;
    }
}

add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        Odinokov_Table_View::get_instance();
    }
} );
