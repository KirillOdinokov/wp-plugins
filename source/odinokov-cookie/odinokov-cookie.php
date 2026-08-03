<?php
/**
 * Plugin Name: Odinokov Cookie
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Простое и безопасное уведомление об использовании Cookies для функционирования сайта и Яндекс Метрики.
 * Version:     1.0.5
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: odinokov-cookie
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ODCK_VERSION', '1.0.5' );
define( 'ODCK_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODCK_URL', plugin_dir_url( __FILE__ ) );

require_once ODCK_DIR . 'includes/class-odck-updater.php';

new ODCK_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-cookie.json',
    ODCK_VERSION,
    array(
        'name'        => 'Odinokov Cookie',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Простое и безопасное уведомление об использовании Cookies.',
    )
);

class Odinokov_Cookie_Notice {

    const OPTION_KEY    = 'odck_settings';
    const COOKIE_NAME   = 'odck_cookie_accepted';
    const COOKIE_EXPIRY = 365;

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue' ] );
        add_action( 'wp_footer', [ $this, 'render_notice' ] );
        add_action( 'wp_ajax_odck_accept', [ $this, 'ajax_accept' ] );
        add_action( 'wp_ajax_nopriv_odck_accept', [ $this, 'ajax_accept' ] );
        add_action( 'admin_post_odck_force_check', [ $this, 'force_check' ] );
    }

    public function get_defaults() {
        return [ 'font_family' => 'inherit', 'border_radius' => 0, 'bg_color' => '#ffffff', 'button_border_color' => '#0073aa', 'button_style' => 'flat', 'privacy_url' => '' ];
    }

    public function get_settings() {
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) $saved = [];
        return array_merge( $this->get_defaults(), $saved );
    }

    public function get_google_fonts() {
        return [
            'inherit' => 'Inherit (тема сайта)', 'Roboto' => 'Roboto', 'Open Sans' => 'Open Sans',
            'Lato' => 'Lato', 'Montserrat' => 'Montserrat', 'Raleway' => 'Raleway',
            'PT Sans' => 'PT Sans', 'Inter' => 'Inter', 'Rubik' => 'Rubik',
        ];
    }

    /* ========== Admin ========== */

    public function add_admin_menu() {
        global $menu; $e = false;
        if ( is_array( $menu ) ) { foreach ( $menu as $i ) { if ( isset( $i[2] ) && 'odinokov-plugins' === $i[2] ) { $e = true; break; } } }
        if ( ! $e ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [ $this, 'dashboard' ], 'dashicons-admin-settings', 30 );
        add_submenu_page( 'odinokov-plugins', 'Cookie Notice', 'Cookie Notice', 'manage_options', 'odinokov-cookie', [ $this, 'render_admin_page' ] );
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov Cookie</h3><p>Уведомление об использовании Cookies.</p>
            </div>
        </div></div>
        <?php
    }

    public function register_settings() {
        register_setting( 'odck_settings_group', self::OPTION_KEY, [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ] );
    }

    public function sanitize_settings( $input ) {
        $d = $this->get_defaults(); $o = [];
        $af = array_keys( $this->get_google_fonts() );
        $o['font_family'] = isset( $input['font_family'] ) && in_array( $input['font_family'], $af, true ) ? $input['font_family'] : $d['font_family'];
        $o['border_radius'] = isset( $input['border_radius'] ) ? max( 0, min( 50, (int) $input['border_radius'] ) ) : $d['border_radius'];
        $o['bg_color'] = isset( $input['bg_color'] ) && $this->is_valid_hex( $input['bg_color'] ) ? $input['bg_color'] : $d['bg_color'];
        $o['button_border_color'] = isset( $input['button_border_color'] ) && $this->is_valid_hex( $input['button_border_color'] ) ? $input['button_border_color'] : $d['button_border_color'];
        $o['button_style'] = isset( $input['button_style'] ) && in_array( $input['button_style'], [ 'flat', 'shadow' ], true ) ? $input['button_style'] : $d['button_style'];
        $o['privacy_url'] = isset( $input['privacy_url'] ) ? esc_url_raw( trim( $input['privacy_url'] ) ) : $d['privacy_url'];
        return $o;
    }

    private function is_valid_hex( $c ) { return (bool) preg_match( '/^#[a-fA-F0-9]{3,6}$/', $c ); }

    public function admin_enqueue( $hook ) {
        if ( false === strpos( $hook, 'odinokov-cookie' ) ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'odck-admin', ODCK_URL . 'assets/admin.css', [ 'wp-color-picker' ], ODCK_VERSION );
        wp_enqueue_script( 'odck-admin', ODCK_URL . 'assets/admin.js', [ 'jquery', 'wp-color-picker' ], ODCK_VERSION, true );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $s = $this->get_settings(); $fonts = $this->get_google_fonts();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Cookie Notice — Настройки', 'odinokov-cookie' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'odck_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="odck_font_family"><?php esc_html_e( 'Шрифт', 'odinokov-cookie' ); ?></label></th>
                        <td>
                            <select id="odck_font_family" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family]">
                                <?php foreach ( $fonts as $v => $l ) : ?><option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['font_family'], $v ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="odck_border_radius"><?php esc_html_e( 'Закругление (px)', 'odinokov-cookie' ); ?></label></th>
                        <td><input type="number" id="odck_border_radius" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[border_radius]" value="<?php echo esc_attr( $s['border_radius'] ); ?>" min="0" max="50"></td>
                    </tr>
                    <tr>
                        <th><label for="odck_bg_color"><?php esc_html_e( 'Фон окна', 'odinokov-cookie' ); ?></label></th>
                        <td><input type="text" id="odck_bg_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" class="odck-color-picker" data-default-color="#ffffff"></td>
                    </tr>
                    <tr>
                        <th><label for="odck_button_border_color"><?php esc_html_e( 'Цвет кнопки', 'odinokov-cookie' ); ?></label></th>
                        <td><input type="text" id="odck_button_border_color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_border_color]" value="<?php echo esc_attr( $s['button_border_color'] ); ?>" class="odck-color-picker" data-default-color="#0073aa"></td>
                    </tr>
                    <tr>
                        <th><label for="odck_button_style"><?php esc_html_e( 'Стиль кнопки', 'odinokov-cookie' ); ?></label></th>
                        <td>
                            <select id="odck_button_style" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_style]">
                                <option value="flat" <?php selected( $s['button_style'], 'flat' ); ?>>Flat</option>
                                <option value="shadow" <?php selected( $s['button_style'], 'shadow' ); ?>>Shadow</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="odck_privacy_url"><?php esc_html_e( 'Ссылка на Политику', 'odinokov-cookie' ); ?></label></th>
                        <td><input type="url" id="odck_privacy_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[privacy_url]" value="<?php echo esc_attr( $s['privacy_url'] ); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                <?php wp_nonce_field( 'odck_force_check', 'odck_force_check_nonce' ); ?>
                <input type="hidden" name="action" value="odck_force_check">
                <?php submit_button( __( 'Проверить обновления', 'odinokov-cookie' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public function force_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied.' );
        }
        check_admin_referer( 'odck_force_check', 'odck_force_check_nonce' );
        delete_transient( 'odck_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-cookie.json' ) );
        wp_safe_redirect( add_query_arg( 'odck_force_check_done', '1', admin_url( 'admin.php?page=odinokov-cookie' ) ) );
        exit;
    }

    /* ========== Frontend ========== */

    public function frontend_enqueue() {
        $s = $this->get_settings();
        wp_enqueue_style( 'odck-frontend', ODCK_URL . 'assets/frontend.css', [], ODCK_VERSION );
        if ( 'inherit' !== $s['font_family'] ) {
            wp_enqueue_style( 'odck-google-font', 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $s['font_family'] ) . ':wght@400;500;700&display=swap', [], null );
        }
        wp_enqueue_script( 'odck-frontend', ODCK_URL . 'assets/frontend.js', [], ODCK_VERSION, true );
        wp_localize_script( 'odck-frontend', 'ODCK', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'odck_accept_nonce' ),
        ] );
    }

    public function render_notice() {
        $s = $this->get_settings();
        $fs = ( 'inherit' !== $s['font_family'] ) ? 'font-family:"' . esc_attr( $s['font_family'] ) . '", sans-serif;' : '';
        $bc = 'odck-accept-btn ' . ( 'shadow' === $s['button_style'] ? 'odck-btn-shadow' : 'odck-btn-flat' );
        $bs = 'shadow' === $s['button_style']
            ? sprintf( 'background:%1$s;box-shadow:0 4px 12px %2$s', esc_attr( $s['button_border_color'] ), esc_attr( $this->hex_to_rgba( $s['button_border_color'], 0.35 ) ) )
            : sprintf( 'background:%1$s;border-color:%1$s', esc_attr( $s['button_border_color'] ) );

        $pp = ! empty( $s['privacy_url'] )
            ? '<a href="' . esc_url( $s['privacy_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Политика Конфиденциальности', 'odinokov-cookie' ) . '</a>'
            : esc_html__( 'С политикой конфиденциальности Вы можете ознакомиться на нашем сайте.', 'odinokov-cookie' );
        ?>
        <div id="odck-cookie-notice" class="odck-cookie-notice" style="border-radius:<?php echo (int) $s['border_radius']; ?>px;background:<?php echo esc_attr( $s['bg_color'] ); ?>;<?php echo $fs; ?>" role="dialog" aria-label="<?php esc_attr_e( 'Уведомление о Cookies', 'odinokov-cookie' ); ?>">
            <p><?php esc_html_e( 'Мы используем Cookies для функционирования сайта и Яндекс Метрики.', 'odinokov-cookie' ); ?></p>
            <div class="odck-actions">
                <button type="button" class="<?php echo esc_attr( $bc ); ?>" style="<?php echo $bs; ?>"><?php esc_html_e( 'Принять', 'odinokov-cookie' ); ?></button>
                <span class="odck-privacy-link"><?php echo $pp; ?></span>
            </div>
        </div>
        <?php
    }

    private function hex_to_rgba( $hex, $a ) {
        $hex = ltrim( $hex, '#' ); if ( strlen( $hex ) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return sprintf( 'rgba(%d,%d,%d,%.2f)', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), $a );
    }

    /* ========== AJAX ========== */

    public function ajax_accept() {
        check_ajax_referer( 'odck_accept_nonce', 'nonce' );
        wp_send_json_success();
    }
}

Odinokov_Cookie_Notice::get_instance();
