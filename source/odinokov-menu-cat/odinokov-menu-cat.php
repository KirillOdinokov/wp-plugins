<?php
/**
 * Plugin Name: Odinokov Menu Cat
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Автоматическое добавление категорий товаров WooCommerce в меню сайта с сохранением иерархии.
 * Version:     1.0.0
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-menu-cat
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OMC_VERSION', '1.0.0' );
define( 'OMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'OMC_URL', plugin_dir_url( __FILE__ ) );

require_once OMC_DIR . 'includes/class-omc-updater.php';

new OMC_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-menu-cat.json',
    OMC_VERSION,
    array(
        'name'        => 'Odinokov Menu Cat',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Автоматическое добавление категорий WooCommerce в меню сайта.',
    )
);

class Odinokov_Menu_Cat {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue' ] );
        add_action( 'wp_ajax_omc_load_subcats', [ $this, 'ajax_load_subcats' ] );
        add_action( 'admin_post_omc_add_selected', [ $this, 'handle_add_selected' ] );
        add_action( 'admin_post_omc_add_all', [ $this, 'handle_add_all' ] );
        add_action( 'admin_post_omc_force_check', [ $this, 'force_check' ] );
    }

    public function add_admin_menu() {
        global $menu; $exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) { $exists = true; break; }
            }
        }
        if ( ! $exists ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [ $this, 'dashboard' ], 'dashicons-admin-settings', 30 );
        add_submenu_page( 'odinokov-plugins', 'Menu Cat', 'Menu Cat', 'manage_options', 'odinokov-menu-cat', [ $this, 'render_admin_page' ] );
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov Menu Cat</h3><p>Автоматическое добавление категорий WooCommerce в меню.</p>
            </div>
        </div></div>
        <?php
    }

    public function admin_enqueue( $hook ) {
        if ( false === strpos( $hook, 'odinokov-menu-cat' ) ) return;
        wp_enqueue_style( 'omc-admin', OMC_URL . 'assets/css/admin.css', [], OMC_VERSION );
        wp_enqueue_script( 'omc-admin', OMC_URL . 'assets/js/admin.js', [ 'jquery' ], OMC_VERSION, true );
        wp_localize_script( 'omc-admin', 'OMC', [
            'nonce'  => wp_create_nonce( 'omc_ajax' ),
            'loading' => __( 'Загрузка…', 'odinokov-menu-cat' ),
            'empty'   => __( 'Нет подкатегорий', 'odinokov-menu-cat' ),
            'error'   => __( 'Ошибка загрузки', 'odinokov-menu-cat' ),
        ] );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $menus = wp_get_nav_menus();
        $total_cats = wp_count_terms( 'product_cat', [ 'hide_empty' => false ] );
        if ( is_wp_error( $total_cats ) ) $total_cats = 0;

        $added = isset( $_GET['omc_added'] ) ? absint( $_GET['omc_added'] ) : 0;
        ?>
        <div class="wrap">
            <h1>Odinokov Menu Cat</h1>

            <?php if ( $added > 0 ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php printf( esc_html( _n( 'Добавлена %d категория в меню.', 'Добавлено %d категорий в меню.', $added, 'odinokov-menu-cat' ) ), $added ); ?>
                    <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Перейти к меню', 'odinokov-menu-cat' ); ?></a>
                </p></div>
            <?php endif; ?>

            <?php if ( empty( $menus ) ) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e( 'Нет ни одного меню. Создайте меню в Внешний вид → Меню.', 'odinokov-menu-cat' ); ?></p></div>
                <?php return; endif; ?>

            <?php if ( $total_cats === 0 ) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e( 'Нет категорий товаров. Создайте категории в Товары → Категории.', 'odinokov-menu-cat' ); ?></p></div>
                <?php return; endif; ?>

            <div class="omc-form-wrap" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;max-width:700px;margin-top:16px;">

                <div class="omc-row" style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Меню', 'odinokov-menu-cat' ); ?></label>
                    <select name="omc_menu_id" id="omc-menu-id" style="min-width:250px;">
                        <?php foreach ( $menus as $menu ) : ?>
                            <option value="<?php echo esc_attr( $menu->term_id ); ?>"><?php echo esc_html( $menu->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="omc-row" style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Родительская категория (1 уровень)', 'odinokov-menu-cat' ); ?></label>
                    <?php
                    wp_dropdown_categories( [
                        'taxonomy'          => 'product_cat',
                        'hierarchical'      => true,
                        'depth'             => 1,
                        'hide_empty'        => false,
                        'show_option_none'  => '— ' . __( 'Выберите категорию', 'odinokov-menu-cat' ) . ' —',
                        'option_none_value' => '',
                        'name'              => 'omc_parent_cat',
                        'id'                => 'omc-parent-cat',
                        'class'             => '',
                        'value_field'       => 'term_id',
                    ] );
                    ?>
                </div>

                <div class="omc-row" style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e( 'Подкатегории для добавления', 'odinokov-menu-cat' ); ?></label>
                    <div id="omc-subcats" style="max-height:400px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:10px;background:#fafafa;min-height:60px;">
                        <p style="color:#888;margin:0;"><?php esc_html_e( 'Выберите категорию выше', 'odinokov-menu-cat' ); ?></p>
                    </div>
                    <p style="margin:6px 0 0;">
                        <a href="#" id="omc-select-all" style="display:none;font-size:12px;"><?php esc_html_e( 'Выбрать все', 'odinokov-menu-cat' ); ?></a>
                        <span style="display:none;color:#ccc;margin:0 6px;" id="omc-select-sep">|</span>
                        <a href="#" id="omc-deselect-all" style="display:none;font-size:12px;"><?php esc_html_e( 'Снять все', 'odinokov-menu-cat' ); ?></a>
                    </p>
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                        <?php wp_nonce_field( 'omc_add_selected', 'omc_nonce' ); ?>
                        <input type="hidden" name="action" value="omc_add_selected">
                        <input type="hidden" name="menu_id" id="omc-menu-id-selected">
                        <input type="hidden" name="parent_cat" id="omc-parent-cat-selected">
                        <div id="omc-checked-container"></div>
                        <?php submit_button( __( 'Добавить выбранные в меню', 'odinokov-menu-cat' ), 'primary', 'submit', false ); ?>
                    </form>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                        <?php wp_nonce_field( 'omc_add_all', 'omc_nonce' ); ?>
                        <input type="hidden" name="action" value="omc_add_all">
                        <input type="hidden" name="menu_id" id="omc-menu-id-all">
                        <?php
                        $all_label = sprintf( __( 'Добавить ВСЕ категории в меню (%d)', 'odinokov-menu-cat' ), $total_cats );
                        submit_button( $all_label, 'secondary', 'submit', false, [ 'onclick' => "return confirm('" . esc_js( __( 'Добавить ВСЕ категории товаров в выбранное меню?', 'odinokov-menu-cat' ) ) . "');" ] );
                        ?>
                    </form>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                <?php wp_nonce_field( 'omc_force_check', 'omc_force_check_nonce' ); ?>
                <input type="hidden" name="action" value="omc_force_check">
                <?php submit_button( __( 'Проверить обновления', 'odinokov-menu-cat' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public function ajax_load_subcats() {
        check_ajax_referer( 'omc_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $term_id = absint( $_POST['term_id'] ?? 0 );
        if ( ! $term_id ) wp_die( -1 );

        ob_start();
        $this->render_checkbox_tree( $term_id );
        $html = ob_get_clean();

        if ( '' === trim( $html ) ) {
            $html = '<p style="color:#888;margin:0;">' . esc_html__( 'Нет подкатегорий', 'odinokov-menu-cat' ) . '</p>';
        }

        wp_send_json_success( [ 'html' => $html ] );
    }

    private function render_checkbox_tree( $parent_id, $depth = 0 ) {
        $children = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $parent_id,
            'hide_empty' => false,
        ] );

        if ( empty( $children ) || is_wp_error( $children ) ) return;

        foreach ( $children as $child ) {
            $indent = $depth * 20;
            echo '<div class="omc-cb-row" style="padding-left:' . (int) $indent . 'px;margin:3px 0;">';
            echo '<label>';
            echo '<input type="checkbox" name="omc_cats[]" value="' . esc_attr( $child->term_id ) . '"> ';
            echo esc_html( $child->name );
            echo ' <span style="color:#999;font-size:11px;">(' . (int) $child->count . ')</span>';
            echo '</label>';
            echo '</div>';
            $this->render_checkbox_tree( $child->term_id, $depth + 1 );
        }
    }

    public function handle_add_selected() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'omc_add_selected', 'omc_nonce' );

        $menu_id    = absint( $_POST['menu_id'] ?? 0 );
        $parent_cat = absint( $_POST['parent_cat'] ?? 0 );
        $checked    = array_map( 'absint', $_POST['omc_cats'] ?? [] );

        if ( ! $menu_id || ! $parent_cat ) {
            wp_safe_redirect( add_query_arg( 'omc_added', 0, admin_url( 'admin.php?page=odinokov-menu-cat' ) ) );
            exit;
        }

        $term_map = [];
        $count    = 0;

        $this->ensure_term_in_menu( $menu_id, $parent_cat, $term_map, $count );

        foreach ( $checked as $cat_id ) {
            $this->ensure_term_in_menu( $menu_id, $cat_id, $term_map, $count );
        }

        wp_safe_redirect( add_query_arg( 'omc_added', $count, admin_url( 'admin.php?page=odinokov-menu-cat' ) ) );
        exit;
    }

    public function handle_add_all() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'omc_add_all', 'omc_nonce' );

        $menu_id = absint( $_POST['menu_id'] ?? 0 );
        if ( ! $menu_id ) {
            wp_safe_redirect( add_query_arg( 'omc_added', 0, admin_url( 'admin.php?page=odinokov-menu-cat' ) ) );
            exit;
        }

        $top_cats = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => false,
        ] );

        if ( empty( $top_cats ) || is_wp_error( $top_cats ) ) {
            wp_safe_redirect( add_query_arg( 'omc_added', 0, admin_url( 'admin.php?page=odinokov-menu-cat' ) ) );
            exit;
        }

        $term_map = [];
        $count    = 0;

        foreach ( $top_cats as $cat ) {
            $this->add_term_tree_to_menu( $menu_id, $cat->term_id, 0, $term_map, $count );
        }

        wp_safe_redirect( add_query_arg( 'omc_added', $count, admin_url( 'admin.php?page=odinokov-menu-cat' ) ) );
        exit;
    }

    private function ensure_term_in_menu( $menu_id, $term_id, &$term_map, &$count ) {
        if ( isset( $term_map[ $term_id ] ) ) return;

        $term = get_term( $term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) return;

        if ( $term->parent > 0 ) {
            $this->ensure_term_in_menu( $menu_id, $term->parent, $term_map, $count );
        }

        $parent_menu_id = $term->parent > 0 ? ( $term_map[ $term->parent ] ?? 0 ) : 0;

        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) return;

        $item_id = wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'     => $term->name,
            'menu-item-url'       => $url,
            'menu-item-status'    => 'publish',
            'menu-item-type'      => 'taxonomy',
            'menu-item-object'    => 'product_cat',
            'menu-item-object-id' => $term->term_id,
            'menu-item-parent-id' => $parent_menu_id,
        ] );

        if ( ! is_wp_error( $item_id ) && $item_id > 0 ) {
            $term_map[ $term->term_id ] = $item_id;
            $count++;
        }
    }

    private function add_term_tree_to_menu( $menu_id, $term_id, $parent_menu_id, &$term_map, &$count ) {
        $term = get_term( $term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) return;

        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) return;

        $item_id = wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'     => $term->name,
            'menu-item-url'       => $url,
            'menu-item-status'    => 'publish',
            'menu-item-type'      => 'taxonomy',
            'menu-item-object'    => 'product_cat',
            'menu-item-object-id' => $term->term_id,
            'menu-item-parent-id' => $parent_menu_id,
        ] );

        if ( is_wp_error( $item_id ) || ! $item_id ) return;

        $term_map[ $term->term_id ] = $item_id;
        $count++;

        $children = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $term_id,
            'hide_empty' => false,
        ] );

        if ( empty( $children ) || is_wp_error( $children ) ) return;

        foreach ( $children as $child ) {
            $this->add_term_tree_to_menu( $menu_id, $child->term_id, $item_id, $term_map, $count );
        }
    }

    public function force_check() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'omc_force_check', 'omc_force_check_nonce' );
        delete_transient( 'omc_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-menu-cat.json' ) );
        set_site_transient( 'update_plugins', null );
        wp_safe_redirect( admin_url( 'plugins.php?omc_force_check_done=1' ) );
        exit;
    }
}

add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        Odinokov_Menu_Cat::get_instance();
    }
} );
