<?php
/**
 * Plugin Name: Odinokov Table View
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Автоматический табличный вид для категорий WooCommerce с однотипными товарами. Управление выводом подкатегорий/товаров. Совместим с Porto.
 * Version:     1.0.30
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-table-view
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OTV_VERSION', '1.0.30' );
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
    private $subcats_output = false;

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
        add_action( 'woocommerce_archive_description', [ $this, 'output_subcategories' ], 20 );
        add_action( 'woocommerce_product_query', [ $this, 'hide_products' ], 99 );
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
        if ( ! $this->is_table_view ) return;
        wp_enqueue_style( 'otv-table', OTV_URL . 'assets/css/table.css', [], OTV_VERSION );
        wp_enqueue_script( 'otv-table', OTV_URL . 'assets/js/table.js', [], OTV_VERSION, true );
    }

    public function body_class( $classes ) {
        if ( $this->is_table_view ) {
            $classes[] = 'otv-table-view';
        }
        return $classes;
    }

    public function check_category() {
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
            return;
        }

        $result = $this->do_check( $term_id );
        set_transient( $cache_key, $result, HOUR_IN_SECONDS );

        $this->is_table_view = $result['table'];
        $this->override_display = $result['display'];
    }

    public function output_subcategories() {
        if ( null === $this->override_display ) return;
        if ( ! is_product_category() ) return;
        if ( $this->subcats_output ) return;
        $this->subcats_output = true;

        $term = get_queried_object();
        if ( ! $term || ! isset( $term->term_id ) ) return;

        $subcats = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $term->term_id,
            'hide_empty' => false,
        ] );

        if ( empty( $subcats ) || is_wp_error( $subcats ) ) return;

        echo '<ul class="products columns-4 otv-subcat-list">';
        foreach ( $subcats as $subcat ) {
            $thumbnail_id = get_term_meta( $subcat->term_id, 'thumbnail_id', true );
            $image = $thumbnail_id ? wp_get_attachment_image( $thumbnail_id, 'woocommerce_thumbnail' ) : wc_placeholder_img( 'woocommerce_thumbnail' );
            $link = get_term_link( $subcat );
            if ( is_wp_error( $link ) ) continue;
            ?>
            <li class="product-category product">
                <a href="<?php echo esc_url( $link ); ?>" class="woocommerce-loop-category__link">
                    <?php echo $image; ?>
                    <h2 class="woocommerce-loop-category__title"><?php echo esc_html( $subcat->name ); ?></h2>
                </a>
            </li>
            <?php
        }
        echo '</ul>';
    }

    public function hide_products( $q ) {
        if ( null === $this->override_display ) return;
        if ( 'subcategories' !== $this->override_display ) return;

        $q->set( 'post__in', [ 0 ] );
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
