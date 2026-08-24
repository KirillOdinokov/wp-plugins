<?php
/**
 * Plugin Name: Odinokov Tag Cat
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Выводит теги товаров категории после описания категории в WooCommerce. Настраиваемый визуал беджей.
 * Version:     1.0.3
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-tag-cat
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OTC_VERSION', '1.0.3' );
define( 'OTC_DIR', plugin_dir_path( __FILE__ ) );
define( 'OTC_URL', plugin_dir_url( __FILE__ ) );

require_once OTC_DIR . 'includes/class-otc-updater.php';

new OTC_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-tag-cat.json',
    OTC_VERSION,
    array(
        'name'        => 'Odinokov Tag Cat',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Теги товаров категории после описания категории в WooCommerce.',
    )
);

class Odinokov_Tag_Cat {

    const OPTION_KEY = 'otc_settings';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue' ] );
        add_action( 'admin_post_otc_force_check', [ $this, 'force_check' ] );
        add_action( 'woocommerce_before_shop_loop', [ $this, 'render_tags' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function get_defaults() {
        return [
            'tag_gap'           => 8,
            'tag_bg_color'      => '#f0f0f0',
            'tag_border_color'  => '#cccccc',
            'tag_border_width'  => 1,
            'tag_border_style'  => 'solid',
            'tag_text_color'    => '#333333',
            'tag_font_family'   => 'inherit',
            'tag_font_size'     => 13,
            'tag_padding_v'     => 4,
            'tag_padding_h'     => 10,
            'tag_border_radius' => 4,
            'custom_fonts'      => '',
        ];
    }

    public function get_settings() {
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) $saved = [];
        return array_merge( $this->get_defaults(), $saved );
    }

    public function register_settings() {
        register_setting( 'otc_settings_group', self::OPTION_KEY, [ 'sanitize_callback' => [ $this, 'sanitize' ] ] );
    }

    public function sanitize( $input ) {
        $d = $this->get_defaults();
        $o = [];
        $o['tag_gap']           = isset( $input['tag_gap'] ) ? max( 0, min( 60, (int) $input['tag_gap'] ) ) : $d['tag_gap'];
        $o['tag_bg_color']      = $this->sanitize_color( $input['tag_bg_color'] ?? $d['tag_bg_color'] );
        $o['tag_border_color']  = $this->sanitize_color( $input['tag_border_color'] ?? $d['tag_border_color'] );
        $o['tag_border_width']  = isset( $input['tag_border_width'] ) ? max( 0, min( 10, (int) $input['tag_border_width'] ) ) : $d['tag_border_width'];
        $o['tag_border_style']  = in_array( $input['tag_border_style'] ?? '', [ 'solid', 'dashed', 'dotted', 'double', 'none' ], true ) ? $input['tag_border_style'] : $d['tag_border_style'];
        $o['tag_text_color']    = $this->sanitize_color( $input['tag_text_color'] ?? $d['tag_text_color'] );
        $o['tag_font_family']   = sanitize_text_field( $input['tag_font_family'] ?? $d['tag_font_family'] );
        $o['tag_font_size']     = isset( $input['tag_font_size'] ) ? max( 8, min( 40, (int) $input['tag_font_size'] ) ) : $d['tag_font_size'];
        $o['tag_padding_v']     = isset( $input['tag_padding_v'] ) ? max( 0, min( 30, (int) $input['tag_padding_v'] ) ) : $d['tag_padding_v'];
        $o['tag_padding_h']     = isset( $input['tag_padding_h'] ) ? max( 0, min( 40, (int) $input['tag_padding_h'] ) ) : $d['tag_padding_h'];
        $o['tag_border_radius'] = isset( $input['tag_border_radius'] ) ? max( 0, min( 50, (int) $input['tag_border_radius'] ) ) : $d['tag_border_radius'];
        $o['custom_fonts']      = sanitize_text_field( $input['custom_fonts'] ?? '' );
        return $o;
    }

    private function sanitize_color( $c ) {
        return preg_match( '/^#[a-fA-F0-9]{3,6}$/', $c ) ? $c : '#000000';
    }

    public function add_admin_menu() {
        global $menu; $exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) { $exists = true; break; }
            }
        }
        if ( ! $exists ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [ $this, 'dashboard' ], 'dashicons-admin-settings', 30 );
        add_submenu_page( 'odinokov-plugins', 'Tag Cat', 'Tag Cat', 'manage_options', 'odinokov-tag-cat', [ $this, 'render_admin_page' ] );
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov Tag Cat</h3><p>Теги товаров в описании категории.</p>
            </div>
        </div></div>
        <?php
    }

    public function admin_enqueue( $hook ) {
        if ( false === strpos( $hook, 'odinokov-tag-cat' ) ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'otc-admin', OTC_URL . 'assets/admin.js', [ 'jquery', 'wp-color-picker' ], OTC_VERSION, true );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $s = $this->get_settings();
        $fonts = $this->get_font_choices();
        $border_styles = [ 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double', 'none' => 'None' ];
        ?>
        <div class="wrap">
            <h1>Odinokov Tag Cat — Настройки</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'otc_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="otc_tag_gap"><?php esc_html_e( 'Отступ между метками (px)', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="number" id="otc_tag_gap" name="otc_settings[tag_gap]" value="<?php echo esc_attr( $s['tag_gap'] ); ?>" min="0" max="60" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_bg_color"><?php esc_html_e( 'Фон беджа', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="text" id="otc_tag_bg_color" name="otc_settings[tag_bg_color]" value="<?php echo esc_attr( $s['tag_bg_color'] ); ?>" class="otc-color-picker" data-default-color="#f0f0f0"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_border_color"><?php esc_html_e( 'Цвет границы', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="text" id="otc_tag_border_color" name="otc_settings[tag_border_color]" value="<?php echo esc_attr( $s['tag_border_color'] ); ?>" class="otc-color-picker" data-default-color="#cccccc"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_border_width"><?php esc_html_e( 'Толщина границы (px)', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="number" id="otc_tag_border_width" name="otc_settings[tag_border_width]" value="<?php echo esc_attr( $s['tag_border_width'] ); ?>" min="0" max="10" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_border_style"><?php esc_html_e( 'Стиль границы', 'odinokov-tag-cat' ); ?></label></th>
                        <td>
                            <select id="otc_tag_border_style" name="otc_settings[tag_border_style]">
                                <?php foreach ( $border_styles as $v => $l ) : ?>
                                    <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['tag_border_style'], $v ); ?>><?php echo esc_html( $l ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_text_color"><?php esc_html_e( 'Цвет текста', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="text" id="otc_tag_text_color" name="otc_settings[tag_text_color]" value="<?php echo esc_attr( $s['tag_text_color'] ); ?>" class="otc-color-picker" data-default-color="#333333"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_font_family"><?php esc_html_e( 'Шрифт', 'odinokov-tag-cat' ); ?></label></th>
                        <td>
                            <select id="otc_tag_font_family" name="otc_settings[tag_font_family]">
                                <?php foreach ( $fonts as $v => $l ) : ?>
                                    <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['tag_font_family'], $v ); ?>><?php echo esc_html( $l ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Или укажите свой шрифт в поле ниже', 'odinokov-tag-cat' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_custom_fonts"><?php esc_html_e( 'Свои шрифты (через запятую)', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="text" id="otc_custom_fonts" name="otc_settings[custom_fonts]" value="<?php echo esc_attr( $s['custom_fonts'] ); ?>" class="regular-text" placeholder="Roboto, Open Sans, Montserrat"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_font_size"><?php esc_html_e( 'Размер шрифта (px)', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="number" id="otc_tag_font_size" name="otc_settings[tag_font_size]" value="<?php echo esc_attr( $s['tag_font_size'] ); ?>" min="8" max="40" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_padding_v"><?php esc_html_e( 'Отступ сверху/снизу (px)', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="number" id="otc_tag_padding_v" name="otc_settings[tag_padding_v]" value="<?php echo esc_attr( $s['tag_padding_v'] ); ?>" min="0" max="30" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_padding_h"><?php esc_html_e( 'Отступ слева/справа (px)', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="number" id="otc_tag_padding_h" name="otc_settings[tag_padding_h]" value="<?php echo esc_attr( $s['tag_padding_h'] ); ?>" min="0" max="40" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="otc_tag_border_radius"><?php esc_html_e( 'Закругление (px)', 'odinokov-tag-cat' ); ?></label></th>
                        <td><input type="number" id="otc_tag_border_radius" name="otc_settings[tag_border_radius]" value="<?php echo esc_attr( $s['tag_border_radius'] ); ?>" min="0" max="50" class="small-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                <?php wp_nonce_field( 'otc_force_check', 'otc_force_check_nonce' ); ?>
                <input type="hidden" name="action" value="otc_force_check">
                <?php submit_button( __( 'Проверить обновления', 'odinokov-tag-cat' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public function force_check() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'otc_force_check', 'otc_force_check_nonce' );
        delete_transient( 'otc_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-tag-cat.json' ) );
        set_site_transient( 'update_plugins', null );
        wp_safe_redirect( admin_url( 'plugins.php?otc_force_check_done=1' ) );
        exit;
    }

    public function get_font_choices() {
        $s = $this->get_settings();
        $choices = [
            'inherit'        => 'Inherit (тема сайта)',
            'Arial'          => 'Arial',
            'Helvetica'      => 'Helvetica',
            'Georgia'        => 'Georgia',
            'Tahoma'         => 'Tahoma',
            'Verdana'        => 'Verdana',
            'Trebuchet MS'   => 'Trebuchet MS',
            'Roboto'         => 'Roboto',
            'Open Sans'      => 'Open Sans',
            'Lato'           => 'Lato',
            'Montserrat'     => 'Montserrat',
            'Raleway'        => 'Raleway',
            'PT Sans'        => 'PT Sans',
            'Inter'          => 'Inter',
            'Rubik'          => 'Rubik',
        ];
        if ( ! empty( $s['custom_fonts'] ) ) {
            foreach ( explode( ',', $s['custom_fonts'] ) as $f ) {
                $f = trim( $f );
                if ( '' !== $f && ! isset( $choices[ $f ] ) ) {
                    $choices[ $f ] = $f;
                }
            }
        }
        return $choices;
    }

    public function enqueue_assets() {
        if ( ! is_product_category() && ! is_tax( 'product_cat' ) ) return;
        wp_enqueue_style( 'otc-tags', OTC_URL . 'assets/css/tags.css', [], OTC_VERSION );
        wp_enqueue_script( 'otc-tags', OTC_URL . 'assets/js/tags.js', [], OTC_VERSION, true );
    }

    public function render_tags() {
        if ( ! is_product_category() ) return;

        $term = get_queried_object();
        if ( ! $term || ! isset( $term->term_id ) ) return;

        $cache_key = 'otc_tags_' . $term->term_id . '_v2';
        $tags = get_transient( $cache_key );

        if ( false === $tags ) {
            $product_ids = get_posts( [
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'tax_query'      => [ [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $term->term_id,
                    'include_children' => true,
                ] ],
                'fields'         => 'ids',
            ] );

            if ( empty( $product_ids ) ) {
                $tags = [];
            } else {
                $tags = wp_get_object_terms( $product_ids, 'product_tag' );
                if ( is_wp_error( $tags ) ) $tags = [];
            }

            set_transient( $cache_key, $tags, HOUR_IN_SECONDS );
        }

        if ( empty( $tags ) ) return;

        $s = $this->get_settings();
        $total = count( $tags );
        $limit = 25;

        $ff = 'inherit' !== $s['tag_font_family'] ? 'font-family:' . esc_attr( $s['tag_font_family'] ) . ', sans-serif;' : '';

        $style = sprintf(
            'display:inline-block;margin:%1$dpx;padding:%2$dpx %3$dpx;background:%4$s;border:%5$dpx %6$s %7$s;border-radius:%8$dpx;color:%9$s;font-size:%10$dpx;%11$stext-decoration:none;line-height:1.4;',
            (int) ( $s['tag_gap'] / 2 ),
            (int) $s['tag_padding_v'],
            (int) $s['tag_padding_h'],
            esc_attr( $s['tag_bg_color'] ),
            (int) $s['tag_border_width'],
            esc_attr( $s['tag_border_style'] ),
            esc_attr( $s['tag_border_color'] ),
            (int) $s['tag_border_radius'],
            esc_attr( $s['tag_text_color'] ),
            (int) $s['tag_font_size'],
            $ff
        );

        echo '<div class="otc-tags-wrap' . ( $total > $limit ? ' otc-tags-collapsed' : '' ) . '" style="margin-top:16px;line-height:2;">';
        $i = 0;
        foreach ( $tags as $tag ) {
            $url = get_term_link( $tag );
            if ( is_wp_error( $url ) ) continue;
            $i++;
            printf(
                '<a href="%s" class="otc-tag" style="%s">%s</a>',
                esc_url( $url ),
                $style,
                esc_html( $tag->name )
            );
        }
        echo '</div>';
        if ( $total > $limit ) {
            echo '<button type="button" class="otc-toggle-btn" data-otc-show="' . esc_attr__( 'Показать полностью', 'odinokov-tag-cat' ) . ' (' . ( $total - $limit ) . ')" data-otc-hide="' . esc_attr__( 'Скрыть', 'odinokov-tag-cat' ) . '" style="display:inline-block;margin:8px 4px;padding:4px 12px;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;cursor:pointer;font-size:13px;color:#333;">' . esc_html__( 'Показать полностью', 'odinokov-tag-cat' ) . ' (' . ( $total - $limit ) . ')</button>';
        }
    }
}

add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        Odinokov_Tag_Cat::get_instance();
    }
} );
