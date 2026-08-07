<?php
/**
 * Plugin Name: Odinokov Breadcrumbs
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Добавляет мега-меню при наведении на пункты хлебных крошек. Выводит дочерние категории/товары/записи в формате wide menu. Подгрузка через AJAX.
 * Version:     1.1.3
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: odinokov-breadcrumbs
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'OBM_VERSION' ) ) {
    define( 'OBM_VERSION', '1.1.3' );
}
if ( ! defined( 'OBM_FILE' ) ) {
    define( 'OBM_FILE', __FILE__ );
}
if ( ! defined( 'OBM_DIR' ) ) {
    define( 'OBM_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'OBM_URL' ) ) {
    define( 'OBM_URL', plugin_dir_url( __FILE__ ) );
}

require_once OBM_DIR . 'includes/class-obm-updater.php';

new OBM_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-breadcrumbs.json',
    OBM_VERSION,
    array(
        'name'        => 'Odinokov Breadcrumbs',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Добавляет мега-меню при наведении на пункты хлебных крошек. Выводит дочерние категории/товары/записи.',
    )
);

class Odinokov_Breadcrumbs {

    const OPTION_KEY   = 'obm_settings';
    const AJAX_ACTION  = 'obm_load_menu';
    const MAX_ITEMS    = 50;

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue' ] );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_load_menu' ] );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'ajax_load_menu' ] );
        add_action( 'admin_post_obm_force_check', [ $this, 'force_check' ] );
    }

    /* ====================================================================== */
    /*  Настройки                                                             */
    /* ====================================================================== */

    public function get_defaults() {
        return [
            'font_family' => 'inherit',
            'font_weight' => '400',
            'text_color'  => '#333333',
            'bg_color'    => '#ffffff',
        ];
    }

    public function get_settings() {
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        return array_merge( $this->get_defaults(), $saved );
    }

    public function get_font_choices() {
        return [
            'inherit' => 'Inherit (тема сайта)',
            'Roboto' => 'Roboto', 'Open Sans' => 'Open Sans', 'Lato' => 'Lato',
            'Montserrat' => 'Montserrat', 'Raleway' => 'Raleway', 'PT Sans' => 'PT Sans',
            'Inter' => 'Inter', 'Arial' => 'Arial', 'Helvetica' => 'Helvetica',
        ];
    }

    public function get_font_weight_choices() {
        return [ '300' => '300 (Light)', '400' => '400 (Normal)', '500' => '500 (Medium)', '600' => '600 (Semi Bold)', '700' => '700 (Bold)' ];
    }

    /* ====================================================================== */
    /*  Админка                                                               */
    /* ====================================================================== */

    public function add_admin_menu() {
        global $menu;
        $exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) {
                    $exists = true;
                    break;
                }
            }
        }
        if ( ! $exists ) {
            add_menu_page(
                'Одиноков', 'Одиноков', 'manage_options',
                'odinokov-plugins', [ $this, 'dashboard' ],
                'dashicons-admin-settings', 30
            );
        }

        add_submenu_page(
            'odinokov-plugins',
            __( 'Odinokov Breadcrumbs', 'odinokov-breadcrumbs' ),
            __( 'Breadcrumbs', 'odinokov-breadcrumbs' ),
            'manage_options',
            'odinokov-breadcrumbs',
            [ $this, 'render_admin_page' ]
        );
    }

    public function dashboard() {
        ?>
        <div class="wrap">
            <h1>Плагины Одиноков</h1>
            <p>Список установленных плагинов от Одиноков для управления.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                    <h3 style="margin-top:0;">Odinokov Breadcrumbs</h3>
                    <p>Мега-меню при наведении на пункты хлебных крошек.</p>
                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=odinokov-breadcrumbs' ) ); ?>" class="button">Настроить</a></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'obm_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );
    }

    public function sanitize_settings( $input ) {
        $defaults = $this->get_defaults();
        $out = [];
        $allowed_fonts = array_keys( $this->get_font_choices() );
        $out['font_family'] = isset( $input['font_family'] ) && in_array( $input['font_family'], $allowed_fonts, true ) ? $input['font_family'] : $defaults['font_family'];
        $allowed_weights = array_keys( $this->get_font_weight_choices() );
        $out['font_weight'] = isset( $input['font_weight'] ) && in_array( $input['font_weight'], $allowed_weights, true ) ? $input['font_weight'] : $defaults['font_weight'];
        $out['text_color'] = isset( $input['text_color'] ) && $this->is_valid_color( $input['text_color'] ) ? $input['text_color'] : $defaults['text_color'];
        $out['bg_color'] = isset( $input['bg_color'] ) && $this->is_valid_color( $input['bg_color'] ) ? $input['bg_color'] : $defaults['bg_color'];
        return $out;
    }

    private function is_valid_color( $color ) {
        if ( ! is_string( $color ) ) return false;
        $color = trim( $color );
        if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $color ) ) return true;
        if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $color ) ) return true;
        return false;
    }

    public function admin_enqueue( $hook ) {
        if ( false === strpos( $hook, 'odinokov-breadcrumbs' ) ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'obm-admin', OBM_URL . 'assets/admin.css', [ 'wp-color-picker' ], OBM_VERSION );
        wp_enqueue_script( 'obm-admin', OBM_URL . 'assets/admin.js', [ 'jquery', 'wp-color-picker' ], OBM_VERSION, true );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $s = $this->get_settings();
        $fonts = $this->get_font_choices();
        $weights = $this->get_font_weight_choices();
        ?>
        <div class="wrap obm-wrap">
            <h1><?php echo esc_html__( 'Odinokov Breadcrumbs — Настройки', 'odinokov-breadcrumbs' ); ?></h1>
            <p class="description"><?php echo esc_html__( 'Настройки внешнего вида мега-меню хлебных крошек.', 'odinokov-breadcrumbs' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'obm_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="obm_font_family"><?php echo esc_html__( 'Шрифт', 'odinokov-breadcrumbs' ); ?></label></th>
                        <td>
                            <select id="obm_font_family" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family]">
                                <?php foreach ( $fonts as $v => $l ) : ?>
                                    <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['font_family'], $v ); ?>><?php echo esc_html( $l ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="obm_font_weight"><?php echo esc_html__( 'Жирность', 'odinokov-breadcrumbs' ); ?></label></th>
                        <td>
                            <select id="obm_font_weight" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_weight]">
                                <?php foreach ( $weights as $v => $l ) : ?>
                                    <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['font_weight'], $v ); ?>><?php echo esc_html( $l ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="obm_text_color"><?php echo esc_html__( 'Цвет текста', 'odinokov-breadcrumbs' ); ?></label></th>
                        <td><input type="text" id="obm_text_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[text_color]" value="<?php echo esc_attr( $s['text_color'] ); ?>" class="obm-color-picker" data-default-color="#333333"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="obm_bg_color"><?php echo esc_html__( 'Цвет фона', 'odinokov-breadcrumbs' ); ?></label></th>
                        <td><input type="text" id="obm_bg_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" class="obm-color-picker" data-default-color="#ffffff"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                <?php wp_nonce_field( 'obm_force_check', 'obm_force_check_nonce' ); ?>
                <input type="hidden" name="action" value="obm_force_check">
                <?php submit_button( __( 'Проверить обновления', 'odinokov-breadcrumbs' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public function force_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied.' );
        }
        check_admin_referer( 'obm_force_check', 'obm_force_check_nonce' );
        delete_transient( 'obm_release_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-breadcrumbs.json' ) );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
        wp_safe_redirect( admin_url( 'plugins.php?obm_force_check_done=1' ) );
        exit;
    }

    /* ====================================================================== */
    /*  Фронтенд                                                              */
    /* ====================================================================== */

    public function frontend_enqueue() {
        $s = $this->get_settings();
        if ( 'inherit' !== $s['font_family'] ) {
            wp_enqueue_style( 'obm-google-font', 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $s['font_family'] ) . ':wght@300;400;500;600;700&display=swap', [], null );
        }
        wp_enqueue_style( 'obm-frontend', OBM_URL . 'assets/frontend.css', [], OBM_VERSION );
        wp_enqueue_script( 'obm-frontend', OBM_URL . 'assets/frontend.js', [], OBM_VERSION, true );
        wp_localize_script( 'obm-frontend', 'OBM', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'obm_menu_nonce' ),
            'i18n'    => [
                'goToCategory' => __( 'Смотреть все', 'odinokov-breadcrumbs' ),
                'loading'      => __( 'Загрузка...', 'odinokov-breadcrumbs' ),
            ],
        ] );
        add_action( 'wp_head', [ $this, 'inline_styles' ], 99 );
    }

    public function inline_styles() {
        $s = $this->get_settings();
        $font_css = ( 'inherit' !== $s['font_family'] ) ? 'font-family:"' . esc_attr( $s['font_family'] ) . '", sans-serif;' : '';
        $css = '.obm-mega-menu{color:' . esc_attr( $s['text_color'] ) . ';background:' . esc_attr( $s['bg_color'] ) . ';font-weight:' . esc_attr( $s['font_weight'] ) . ';' . $font_css . '}';
        echo '<style id="obm-inline">' . $css . '</style>';
    }

    /* ====================================================================== */
    /*  AJAX — загрузка меню (универсальный: товары / рубрики / страницы)     */
    /* ====================================================================== */

    public function ajax_load_menu() {
        check_ajax_referer( 'obm_menu_nonce', 'nonce' );

        $term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
        $url     = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        $term = null;
        if ( $term_id ) {
            $term = get_term( $term_id );
        } elseif ( $url ) {
            $term = $this->resolve_term_from_url( $url );
        }

        if ( $term && ! is_wp_error( $term ) ) {
            $tax = $term->taxonomy;
            $children = get_terms( [ 'taxonomy' => $tax, 'parent' => $term->term_id, 'hide_empty' => true, 'number' => 1, 'fields' => 'ids' ] );
            $has_children = ! empty( $children ) && ! is_wp_error( $children );

            if ( $has_children ) {
                $data = $this->get_child_terms( $term->term_id, $tax );
            } elseif ( 'product_cat' === $tax ) {
                $data = $this->get_term_products( $term->term_id );
            } elseif ( 'category' === $tax ) {
                $data = $this->get_term_posts( $term->term_id, 'post' );
            } else {
                $data = $this->get_term_posts( $term->term_id );
            }
        } elseif ( $url && ! $term ) {
            $data = $this->resolve_page_from_url( $url );
        } else {
            wp_send_json_error( [ 'message' => 'Not found' ] );
        }

        wp_send_json_success( $data );
    }

    private function resolve_term_from_url( $url ) {
        $path = trim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
        if ( ! $path ) return null;

        $segments = array_filter( explode( '/', $path ), function( $s ) {
            return '' !== $s && false === strpos( $s, '.' );
        } );
        if ( empty( $segments ) ) return null;

        $skip = [ 'product-category', 'product', 'shop', 'page', 'catalog', 'products', 'katalog', 'tovary', 'category', 'blog', 'news' ];

        for ( $i = count( $segments ) - 1; $i >= 0; $i-- ) {
            $slug = $segments[ $i ];
            if ( in_array( strtolower( $slug ), $skip, true ) ) continue;

            foreach ( [ 'product_cat', 'category', 'post_tag' ] as $tax ) {
                $term = get_term_by( 'slug', $slug, $tax );
                if ( $term && ! is_wp_error( $term ) ) return $term;
            }
        }
        return null;
    }

    private function get_child_terms( $parent_id, $taxonomy ) {
        $children = get_terms( [
            'taxonomy'   => $taxonomy,
            'parent'     => $parent_id,
            'hide_empty' => true,
            'number'     => self::MAX_ITEMS + 1,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $children ) || empty( $children ) ) {
            return $this->empty_data( $taxonomy, $parent_id );
        }

        $has_more = count( $children ) > self::MAX_ITEMS;
        if ( $has_more ) array_pop( $children );

        $items = [];
        foreach ( $children as $child ) {
            $items[] = [
                'id'    => $child->term_id,
                'name'  => $child->name,
                'url'   => get_term_link( $child ),
                'count' => $child->count,
            ];
        }
        return $this->build_data( $items, $has_more, $taxonomy, $parent_id );
    }

    private function get_term_products( $term_id ) {
        $query = new WP_Query( [
            'post_type' => 'product', 'post_status' => 'publish',
            'posts_per_page' => self::MAX_ITEMS + 1, 'orderby' => 'title', 'order' => 'ASC',
            'tax_query' => [ [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term_id ] ],
        ] );
        $posts = $query->posts;
        if ( empty( $posts ) ) return $this->empty_data( 'product_cat', $term_id );

        $has_more = count( $posts ) > self::MAX_ITEMS;
        if ( $has_more ) array_pop( $posts );

        $items = [];
        foreach ( $posts as $p ) {
            $prod = function_exists( 'wc_get_product' ) ? wc_get_product( $p ) : null;
            $items[] = [
                'id'    => $p->ID,
                'name'  => $p->post_title,
                'url'   => get_permalink( $p->ID ),
                'price' => $prod ? $prod->get_price_html() : '',
            ];
        }
        return $this->build_data( $items, $has_more, 'product_cat', $term_id );
    }

    private function get_term_posts( $term_id, $post_type = 'post' ) {
        $query = new WP_Query( [
            'post_type' => $post_type, 'post_status' => 'publish',
            'posts_per_page' => self::MAX_ITEMS + 1, 'orderby' => 'date', 'order' => 'DESC',
            'tax_query' => [ [ 'taxonomy' => get_term( $term_id ) ? get_term( $term_id )->taxonomy : 'category', 'field' => 'term_id', 'terms' => $term_id ] ],
        ] );
        $posts = $query->posts;
        if ( empty( $posts ) ) return $this->empty_data( 'category', $term_id );

        $has_more = count( $posts ) > self::MAX_ITEMS;
        if ( $has_more ) array_pop( $posts );

        $items = [];
        foreach ( $posts as $p ) {
            $items[] = [ 'id' => $p->ID, 'name' => $p->post_title, 'url' => get_permalink( $p->ID ) ];
        }
        return $this->build_data( $items, $has_more, 'category', $term_id );
    }

    private function resolve_page_from_url( $url ) {
        $post_id = url_to_postid( $url );
        if ( ! $post_id ) return $this->empty_data();

        $children = get_pages( [ 'child_of' => $post_id, 'sort_column' => 'menu_order', 'number' => self::MAX_ITEMS + 1 ] );
        if ( empty( $children ) ) return $this->empty_data();

        $has_more = count( $children ) > self::MAX_ITEMS;
        if ( $has_more ) array_pop( $children );

        $items = [];
        foreach ( $children as $child ) {
            $items[] = [ 'id' => $child->ID, 'name' => $child->post_title, 'url' => get_permalink( $child->ID ) ];
        }
        return $this->build_data( $items, $has_more, '', 0, get_permalink( $post_id ), get_the_title( $post_id ) );
    }

    private function empty_data( $tax = '', $parent_id = 0 ) {
        $term_url = $parent_id && $tax ? get_term_link( $parent_id, $tax ) : '';
        $term_name = $parent_id && $tax ? ( get_term( $parent_id, $tax ) ? get_term( $parent_id, $tax )->name : '' ) : '';
        return $this->build_data( [], false, $tax, $parent_id, $term_url, $term_name );
    }

    private function build_data( $items, $has_more, $tax = '', $parent_id = 0, $term_url = null, $term_name = null ) {
        if ( null === $term_url && $parent_id && $tax ) {
            $term_url = get_term_link( $parent_id, $tax );
        }
        if ( null === $term_name && $parent_id && $tax ) {
            $t = get_term( $parent_id, $tax );
            $term_name = $t && ! is_wp_error( $t ) ? $t->name : '';
        }
        return [
            'items'     => $items,
            'has_more'  => $has_more,
            'total'     => count( $items ),
            'term_url'  => is_wp_error( $term_url ) ? '' : $term_url,
            'term_name' => $term_name ?: '',
        ];
    }

}

Odinokov_Breadcrumbs::get_instance();
