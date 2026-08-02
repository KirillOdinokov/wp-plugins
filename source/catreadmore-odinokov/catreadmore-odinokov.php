<?php
/**
 * Plugin Name: CatReadMore Odinokov
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Скрывает часть длинного описания категории WooCommerce белым градиентом и выводит кнопку «Читать подробное описание категории». Полный текст остаётся в DOM (SEO friendly). Настройки в меню Одиноков → CatReadMore.
 * Version:     1.3.1
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: catreadmore-odinokov
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'CROMO_VERSION' ) ) {
    define( 'CROMO_VERSION', '1.3.1' );
}
if ( ! defined( 'CROMO_FILE' ) ) {
    define( 'CROMO_FILE', __FILE__ );
}
if ( ! defined( 'CROMO_DIR' ) ) {
    define( 'CROMO_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CROMO_URL' ) ) {
    define( 'CROMO_URL', plugin_dir_url( __FILE__ ) );
}

// =====================================================================
// АВТООБНОВЛЕНИЕ
// =====================================================================

class CROMO_Updater {

    private $plugin_slug;
    private $plugin_file;
    private $update_url;
    private $current_version;
    private $plugin_name;
    private $plugin_author;
    private $plugin_author_uri;
    private $plugin_description;

    public function __construct( $plugin_file, $update_url, $current_version, $args = array() ) {
        $this->plugin_file     = $plugin_file;
        $this->update_url      = $update_url;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->current_version = $current_version;
        $this->plugin_name        = $args['name'] ?? 'CatReadMore Odinokov';
        $this->plugin_author      = $args['author'] ?? '<a href="https://rufiks.ru">Odinokov</a>';
        $this->plugin_author_uri  = $args['author_uri'] ?? 'https://rufiks.ru';
        $this->plugin_description = $args['description'] ?? 'Скрывает часть описания категории WooCommerce белым градиентом и выводит кнопку «Читать подробное описание категории».';

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_update' ), 10, 3 );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        if ( version_compare( $this->current_version, $release['version'], '>=' ) ) {
            return $transient;
        }

        if ( empty( $release['download_url'] ) ) {
            return $transient;
        }

        $transient->response[ $this->plugin_slug ] = (object) array(
            'slug'        => dirname( $this->plugin_slug ),
            'plugin'      => $this->plugin_slug,
            'new_version' => $release['version'],
            'url'         => $release['homepage'] ?? '',
            'package'     => $release['download_url'],
            'tested'      => $release['tested'] ?? get_bloginfo( 'version' ),
        );

        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        return (object) array(
            'name'           => $this->plugin_name,
            'slug'           => dirname( $this->plugin_slug ),
            'version'        => $release['version'],
            'author'         => $this->plugin_author,
            'homepage'       => $release['homepage'] ?? $this->plugin_author_uri,
            'requires'       => $release['requires'] ?? '5.8',
            'tested'         => $release['tested'] ?? get_bloginfo( 'version' ),
            'last_updated'   => $release['last_updated'] ?? '',
            'download_link'  => $release['download_url'],
            'sections'       => array(
                'description' => $this->plugin_description,
                'changelog'   => $release['changelog'] ?? '',
            ),
        );
    }

    public function after_update( $response, $hook_extra, $result ) {
        return $response;
    }

    private function get_latest_release() {
        $response = wp_remote_get( $this->update_url, array(
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $release || empty( $release['version'] ) || empty( $release['download_url'] ) ) {
            return null;
        }

        return $release;
    }
}

new CROMO_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/catreadmore-odinokov.json',
    CROMO_VERSION,
    array(
        'name'        => 'CatReadMore Odinokov',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Скрывает часть описания категории WooCommerce белым градиентом и выводит кнопку «Читать подробное описание категории».',
    )
);

// =====================================================================
// НАСТРОЙКИ ПО УМОЛЧАНИЮ
// =====================================================================

function cromo_defaults() {
    return array(
        'char_limit'          => 500,
        'button_text'         => 'Читать подробное описание категории',
        'font_family'         => 'inherit',
        'font_weight'         => 'inherit',
        'font_italic'         => 0,
        'font_underline'      => 0,
        'bg_color'            => '#ffffff',
        'text_color'          => '#222222',
        'gradient_color'      => '#ffffff',
        'border_color'        => '#222222',
        'border_width'        => 1,
        'border_radius'       => 6,
        'padding'             => 12,
        'gradient_height'     => 180,
        'custom_fonts'        => '',
        'icon_url'            => '',
        'icon_width'          => 24,
        'margin_closed_top'   => 0,
        'margin_closed_right' => 0,
        'margin_closed_bottom'=> 0,
        'margin_closed_left'  => 0,
        'margin_open_top'     => 0,
        'margin_open_right'   => 0,
        'margin_open_bottom'  => 0,
        'margin_open_left'    => 0,
    );
}

function cromo_get_settings() {
    $defaults = cromo_defaults();
    $opts     = get_option( 'cromo_settings', array() );
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }
    $legacy_map = array(
        'char_limit'      => 'cromo_char_limit',
        'button_text'     => 'cromo_button_text',
        'font_family'     => 'cromo_font_family',
        'bg_color'        => 'cromo_bg_color',
        'border_color'    => 'cromo_border_color',
        'border_width'    => 'cromo_border_width',
        'border_radius'   => 'cromo_border_radius',
        'padding'         => 'cromo_padding',
        'gradient_height' => 'cromo_gradient_height',
        'custom_fonts'    => 'cromo_custom_fonts',
    );
    foreach ( $legacy_map as $key => $old_opt ) {
        if ( ! isset( $opts[ $key ] ) || '' === $opts[ $key ] ) {
            $val = get_option( $old_opt, null );
            if ( null !== $val && '' !== $val ) {
                $opts[ $key ] = $val;
            }
        }
    }
    $merged = array_merge( $defaults, $opts );
    $merged['char_limit']      = max( 50, (int) $merged['char_limit'] );
    $merged['border_width']    = max( 0, (int) $merged['border_width'] );
    $merged['border_radius']   = max( 0, (int) $merged['border_radius'] );
    $merged['padding']         = max( 0, (int) $merged['padding'] );
    $merged['gradient_height'] = max( 40, (int) $merged['gradient_height'] );
    $merged['icon_width']      = max( 8, (int) $merged['icon_width'] );
    foreach ( array( 'margin_closed_top', 'margin_closed_right', 'margin_closed_bottom', 'margin_closed_left', 'margin_open_top', 'margin_open_right', 'margin_open_bottom', 'margin_open_left' ) as $mk ) {
        $merged[ $mk ] = (int) $merged[ $mk ];
    }
    return $merged;
}

// =====================================================================
// САНИТАЙЗЕРЫ
// =====================================================================

function cromo_sanitize_color( $color ) {
    $color = is_string( $color ) ? trim( $color ) : '';
    if ( '' === $color ) {
        return '';
    }
    if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $color ) ) {
        return $color;
    }
    if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $color ) ) {
        return $color;
    }
    return '';
}

function cromo_sanitize_font_family( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';
    if ( '' === $value || 'inherit' === strtolower( $value ) ) {
        return 'inherit';
    }
    $value = wp_strip_all_tags( $value );
    $value = preg_replace( '/[^A-Za-z0-9_\-,\s]/', '', $value );
    return substr( $value, 0, 200 );
}

function cromo_sanitize_font_weight( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';
    $allowed = array( 'inherit', '300', '400', '500', '600', '700', '800' );
    if ( in_array( $value, $allowed, true ) ) {
        return $value;
    }
    return 'inherit';
}

function cromo_sanitize_font_list( $value ) {
    if ( ! is_string( $value ) ) {
        return '';
    }
    $parts = array_filter( array_map( 'trim', explode( '|', $value ) ) );
    $clean = array();
    foreach ( $parts as $p ) {
        $p = wp_strip_all_tags( $p );
        $p = preg_replace( '/[^A-Za-z0-9_\+ ]/', '', $p );
        if ( '' !== $p ) {
            $clean[] = $p;
        }
    }
    return implode( '|', array_slice( array_unique( $clean ), 0, 20 ) );
}

function cromo_sanitize_url( $value ) {
    return is_string( $value ) ? esc_url_raw( trim( $value ) ) : '';
}

// =====================================================================
// АДМИН-СТРАНИЦА
// =====================================================================

add_action( 'admin_menu', 'cromo_admin_menu' );
function cromo_admin_menu() {
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
            'Одиноков',
            'Одиноков',
            'manage_options',
            'odinokov-plugins',
            'cromo_dashboard_page',
            'dashicons-admin-settings',
            30
        );
    }

    add_submenu_page(
        'odinokov-plugins',
        esc_html__( 'CatReadMore Odinokov', 'catreadmore-odinokov' ),
        esc_html__( 'CatReadMore', 'catreadmore-odinokov' ),
        'manage_options',
        'catreadmore-odinokov',
        'cromo_admin_page'
    );
}

function cromo_dashboard_page() {
    ?>
    <div class="wrap">
        <h1>Плагины Одиноков</h1>
        <p>Список установленных плагинов от Одиноков для управления.</p>
        <div class="odinokov-plugins-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div class="odinokov-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">CatReadMore Odinokov</h3>
                <p>Скрывает часть описания категории WooCommerce белым градиентом и выводит кнопку «Читать подробное описание категории».</p>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=catreadmore-odinokov' ) ); ?>" class="button">Настроить</a></p>
            </div>
        </div>
    </div>
    <?php
}

add_action( 'admin_init', 'cromo_admin_init' );
function cromo_admin_init() {
    register_setting(
        'cromo_settings_group',
        'cromo_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'cromo_sanitize_settings',
            'default'           => cromo_defaults(),
        )
    );
}

function cromo_sanitize_settings( $input ) {
    $defaults = cromo_defaults();
    $out      = array();

    $out['char_limit']          = isset( $input['char_limit'] ) ? max( 50, (int) $input['char_limit'] ) : $defaults['char_limit'];
    $out['button_text']         = isset( $input['button_text'] ) ? sanitize_text_field( wp_unslash( $input['button_text'] ) ) : $defaults['button_text'];
    if ( '' === $out['button_text'] ) {
        $out['button_text'] = $defaults['button_text'];
    }
    $out['font_family']         = isset( $input['font_family'] ) ? cromo_sanitize_font_family( $input['font_family'] ) : $defaults['font_family'];
    $out['font_weight']         = isset( $input['font_weight'] ) ? cromo_sanitize_font_weight( $input['font_weight'] ) : $defaults['font_weight'];
    $out['font_italic']         = ! empty( $input['font_italic'] ) ? 1 : 0;
    $out['font_underline']      = ! empty( $input['font_underline'] ) ? 1 : 0;
    $out['bg_color']            = isset( $input['bg_color'] ) ? cromo_sanitize_color( $input['bg_color'] ) : $defaults['bg_color'];
    $out['text_color']          = isset( $input['text_color'] ) ? cromo_sanitize_color( $input['text_color'] ) : $defaults['text_color'];
    $out['gradient_color']      = isset( $input['gradient_color'] ) ? cromo_sanitize_color( $input['gradient_color'] ) : $defaults['gradient_color'];
    $out['border_color']        = isset( $input['border_color'] ) ? cromo_sanitize_color( $input['border_color'] ) : $defaults['border_color'];
    $out['border_width']        = isset( $input['border_width'] ) ? max( 0, (int) $input['border_width'] ) : $defaults['border_width'];
    $out['border_radius']       = isset( $input['border_radius'] ) ? max( 0, (int) $input['border_radius'] ) : $defaults['border_radius'];
    $out['padding']             = isset( $input['padding'] ) ? max( 0, (int) $input['padding'] ) : $defaults['padding'];
    $out['gradient_height']     = isset( $input['gradient_height'] ) ? max( 40, (int) $input['gradient_height'] ) : $defaults['gradient_height'];
    $out['custom_fonts']        = isset( $input['custom_fonts'] ) ? cromo_sanitize_font_list( $input['custom_fonts'] ) : '';
    $out['icon_url']            = isset( $input['icon_url'] ) ? cromo_sanitize_url( $input['icon_url'] ) : '';
    $out['icon_width']          = isset( $input['icon_width'] ) ? max( 8, (int) $input['icon_width'] ) : $defaults['icon_width'];

    foreach ( array( 'margin_closed_top', 'margin_closed_right', 'margin_closed_bottom', 'margin_closed_left', 'margin_open_top', 'margin_open_right', 'margin_open_bottom', 'margin_open_left' ) as $mk ) {
        $out[ $mk ] = isset( $input[ $mk ] ) ? (int) $input[ $mk ] : $defaults[ $mk ];
    }

    return $out;
}

add_action( 'admin_enqueue_scripts', 'cromo_admin_assets' );
function cromo_admin_assets( $hook ) {
    if ( false === strpos( $hook, 'catreadmore-odinokov' ) ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script(
        'cromo-admin',
        CROMO_URL . 'assets/admin.js',
        array( 'jquery', 'wp-color-picker' ),
        CROMO_VERSION,
        true
    );
    wp_enqueue_style(
        'cromo-admin-css',
        CROMO_URL . 'assets/admin.css',
        array(),
        CROMO_VERSION
    );
}

function cromo_admin_page() {
    $s = cromo_get_settings();
    ?>
    <div class="wrap cromo-admin-wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields( 'cromo_settings_group' ); ?>

            <div class="cromo-admin-card">
                <h2><?php esc_html_e( 'Дизайн кнопки', 'catreadmore-odinokov' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cromo_font_family"><?php esc_html_e( 'Шрифт кнопки', 'catreadmore-odinokov' ); ?></label></th>
                        <td>
                            <select id="cromo_font_family" name="cromo_settings[font_family]">
                                <?php foreach ( cromo_get_font_choices() as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['font_family'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'По умолчанию — inherit (наследует шрифт сайта).', 'catreadmore-odinokov' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_font_weight"><?php esc_html_e( 'Жирность шрифта', 'catreadmore-odinokov' ); ?></label></th>
                        <td>
                            <select id="cromo_font_weight" name="cromo_settings[font_weight]">
                                <option value="inherit" <?php selected( $s['font_weight'], 'inherit' ); ?>>inherit (наследовать)</option>
                                <option value="300" <?php selected( $s['font_weight'], '300' ); ?>>300 (Light)</option>
                                <option value="400" <?php selected( $s['font_weight'], '400' ); ?>>400 (Regular)</option>
                                <option value="500" <?php selected( $s['font_weight'], '500' ); ?>>500 (Medium)</option>
                                <option value="600" <?php selected( $s['font_weight'], '600' ); ?>>600 (Semi Bold)</option>
                                <option value="700" <?php selected( $s['font_weight'], '700' ); ?>>700 (Bold)</option>
                                <option value="800" <?php selected( $s['font_weight'], '800' ); ?>>800 (Extra Bold)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Стиль шрифта', 'catreadmore-odinokov' ); ?></th>
                        <td>
                            <label style="margin-right:16px;"><input type="checkbox" name="cromo_settings[font_italic]" value="1" <?php checked( $s['font_italic'], 1 ); ?>> <em><?php esc_html_e( 'Курсив (italic)', 'catreadmore-odinokov' ); ?></em></label>
                            <label><input type="checkbox" name="cromo_settings[font_underline]" value="1" <?php checked( $s['font_underline'], 1 ); ?>> <u><?php esc_html_e( 'Подчёркнутый (underline)', 'catreadmore-odinokov' ); ?></u></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_bg_color"><?php esc_html_e( 'Цвет фона кнопки', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="text" id="cromo_bg_color" name="cromo_settings[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" class="cromo-color-picker" data-default-color="#ffffff"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_text_color"><?php esc_html_e( 'Цвет текста кнопки', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="text" id="cromo_text_color" name="cromo_settings[text_color]" value="<?php echo esc_attr( $s['text_color'] ); ?>" class="cromo-color-picker" data-default-color="#222222"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_border_color"><?php esc_html_e( 'Цвет границы', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="text" id="cromo_border_color" name="cromo_settings[border_color]" value="<?php echo esc_attr( $s['border_color'] ); ?>" class="cromo-color-picker" data-default-color="#222222"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_border_width"><?php esc_html_e( 'Толщина границы (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_border_width" name="cromo_settings[border_width]" value="<?php echo esc_attr( $s['border_width'] ); ?>" min="0" max="20" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_border_radius"><?php esc_html_e( 'Закругление углов (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_border_radius" name="cromo_settings[border_radius]" value="<?php echo esc_attr( $s['border_radius'] ); ?>" min="0" max="100" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_padding"><?php esc_html_e( 'Внутренний отступ (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_padding" name="cromo_settings[padding]" value="<?php echo esc_attr( $s['padding'] ); ?>" min="0" max="60" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_button_text"><?php esc_html_e( 'Текст кнопки', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="text" id="cromo_button_text" name="cromo_settings[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>

            <div class="cromo-admin-card">
                <h2><?php esc_html_e( 'Иконка кнопки', 'catreadmore-odinokov' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Иконка', 'catreadmore-odinokov' ); ?></th>
                        <td>
                            <div class="cromo-icon-upload">
                                <input type="hidden" id="cromo_icon_url" name="cromo_settings[icon_url]" value="<?php echo esc_attr( $s['icon_url'] ); ?>">
                                <button type="button" class="button" id="cromo_upload_icon"><?php esc_html_e( 'Выбрать иконку', 'catreadmore-odinokov' ); ?></button>
                                <button type="button" class="button" id="cromo_remove_icon" style="<?php echo empty( $s['icon_url'] ) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Удалить', 'catreadmore-odinokov' ); ?></button>
                                <div id="cromo_icon_preview" style="margin-top:8px;">
                                    <?php if ( ! empty( $s['icon_url'] ) ) : ?>
                                        <img src="<?php echo esc_url( $s['icon_url'] ); ?>" style="max-width:100px;max-height:100px;display:block;">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_icon_width"><?php esc_html_e( 'Ширина иконки (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_icon_width" name="cromo_settings[icon_width]" value="<?php echo esc_attr( $s['icon_width'] ); ?>" min="8" max="200" style="width:80px;"></td>
                    </tr>
                </table>
            </div>

            <div class="cromo-admin-card">
                <h2><?php esc_html_e( 'Отступы описания — закрытое состояние', 'catreadmore-odinokov' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cromo_margin_closed_top"><?php esc_html_e( 'Сверху (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_closed_top" name="cromo_settings[margin_closed_top]" value="<?php echo esc_attr( $s['margin_closed_top'] ); ?>" style="width:80px;"></td>
                        <th scope="row"><label for="cromo_margin_closed_right"><?php esc_html_e( 'Справа (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_closed_right" name="cromo_settings[margin_closed_right]" value="<?php echo esc_attr( $s['margin_closed_right'] ); ?>" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_margin_closed_bottom"><?php esc_html_e( 'Снизу (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_closed_bottom" name="cromo_settings[margin_closed_bottom]" value="<?php echo esc_attr( $s['margin_closed_bottom'] ); ?>" style="width:80px;"></td>
                        <th scope="row"><label for="cromo_margin_closed_left"><?php esc_html_e( 'Слева (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_closed_left" name="cromo_settings[margin_closed_left]" value="<?php echo esc_attr( $s['margin_closed_left'] ); ?>" style="width:80px;"></td>
                    </tr>
                </table>
            </div>

            <div class="cromo-admin-card">
                <h2><?php esc_html_e( 'Отступы описания — открытое состояние', 'catreadmore-odinokov' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cromo_margin_open_top"><?php esc_html_e( 'Сверху (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_open_top" name="cromo_settings[margin_open_top]" value="<?php echo esc_attr( $s['margin_open_top'] ); ?>" style="width:80px;"></td>
                        <th scope="row"><label for="cromo_margin_open_right"><?php esc_html_e( 'Справа (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_open_right" name="cromo_settings[margin_open_right]" value="<?php echo esc_attr( $s['margin_open_right'] ); ?>" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_margin_open_bottom"><?php esc_html_e( 'Снизу (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_open_bottom" name="cromo_settings[margin_open_bottom]" value="<?php echo esc_attr( $s['margin_open_bottom'] ); ?>" style="width:80px;"></td>
                        <th scope="row"><label for="cromo_margin_open_left"><?php esc_html_e( 'Слева (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_margin_open_left" name="cromo_settings[margin_open_left]" value="<?php echo esc_attr( $s['margin_open_left'] ); ?>" style="width:80px;"></td>
                    </tr>
                </table>
            </div>

            <div class="cromo-admin-card">
                <h2><?php esc_html_e( 'Контент', 'catreadmore-odinokov' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cromo_char_limit"><?php esc_html_e( 'Лимит символов', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_char_limit" name="cromo_settings[char_limit]" value="<?php echo esc_attr( $s['char_limit'] ); ?>" min="50" step="10" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_gradient_height"><?php esc_html_e( 'Высота градиента (px)', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="number" id="cromo_gradient_height" name="cromo_settings[gradient_height]" value="<?php echo esc_attr( $s['gradient_height'] ); ?>" min="40" max="600" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_gradient_color"><?php esc_html_e( 'Цвет градиента', 'catreadmore-odinokov' ); ?></label></th>
                        <td><input type="text" id="cromo_gradient_color" name="cromo_settings[gradient_color]" value="<?php echo esc_attr( $s['gradient_color'] ); ?>" class="cromo-color-picker" data-default-color="#ffffff"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cromo_custom_fonts"><?php esc_html_e( 'Доп. Google-шрифты (через |)', 'catreadmore-odinokov' ); ?></label></th>
                        <td>
                            <input type="text" id="cromo_custom_fonts" name="cromo_settings[custom_fonts]" value="<?php echo esc_attr( $s['custom_fonts'] ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Например: Roboto|Montserrat|Open+Sans. Пусто — без подключения.', 'catreadmore-odinokov' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// =====================================================================
// СПИСОК ШРИФТОВ
// =====================================================================

function cromo_get_font_choices() {
    $settings = cromo_get_settings();
    $choices  = array(
        'inherit' => '— inherit (наследовать) —',
        'initial' => '— initial (по умолчанию браузера) —',
        'unset'   => '— unset (сброс) —',
    );
    if ( ! empty( $settings['custom_fonts'] ) ) {
        foreach ( explode( '|', $settings['custom_fonts'] ) as $f ) {
            $f = trim( $f );
            if ( '' !== $f ) {
                $choices[ $f ] = $f;
            }
        }
    }
    return $choices;
}

// =====================================================================
// GOOGLE FONTS
// =====================================================================

add_action( 'wp_enqueue_scripts', 'cromo_maybe_enqueue_fonts', 20 );
function cromo_maybe_enqueue_fonts() {
    if ( ! is_product_taxonomy() ) {
        return;
    }
    $settings = cromo_get_settings();
    if ( empty( $settings['custom_fonts'] ) ) {
        return;
    }
    $families = array();
    foreach ( explode( '|', $settings['custom_fonts'] ) as $f ) {
        $f = trim( $f );
        if ( '' === $f ) {
            continue;
        }
        $families[] = str_replace( '+', ' ', $f ) . ':wght@400;500;600;700';
    }
    if ( empty( $families ) ) {
        return;
    }
    $url = add_query_arg(
        array(
            'family'  => rawurlencode( implode( '&family=', $families ) ),
            'display' => 'swap',
        ),
        'https://fonts.googleapis.com/css2'
    );
    wp_enqueue_style( 'cromo-google-fonts', esc_url( $url ), array(), null );
}

// =====================================================================
// ИНЛАЙН-СТИЛИ
// =====================================================================

add_action( 'wp_head', 'cromo_inline_styles', 99 );
function cromo_inline_styles() {
    if ( ! is_product_taxonomy() ) {
        return;
    }
    $s = cromo_get_settings();

    $font_family_css = '';
    if ( 'inherit' !== $s['font_family'] && '' !== $s['font_family'] ) {
        $font_family_css = 'font-family:' . esc_attr( $s['font_family'] ) . ',sans-serif;';
    }

    $font_weight_css = '';
    if ( 'inherit' !== $s['font_weight'] ) {
        $font_weight_css = 'font-weight:' . esc_attr( $s['font_weight'] ) . ';';
    }

    $font_style_css = '';
    if ( ! empty( $s['font_italic'] ) )  $font_style_css .= 'font-style:italic;';
    if ( ! empty( $s['font_underline'] ) ) $font_style_css .= 'text-decoration:underline;';

    $bg     = $s['bg_color'] ? $s['bg_color'] : '#ffffff';
    $tc     = $s['text_color'] ? $s['text_color'] : '#222222';
    $gc     = $s['gradient_color'] ? $s['gradient_color'] : '#ffffff';
    $border = $s['border_width'] > 0 ? $s['border_width'] . 'px solid ' . $s['border_color'] : 'none';
    $radius = (int) $s['border_radius'];
    $pad    = (int) $s['padding'];
    $gh     = (int) $s['gradient_height'];

    $margin_closed = sprintf( '%dpx %dpx %dpx %dpx',
        (int) $s['margin_closed_top'],
        (int) $s['margin_closed_right'],
        (int) $s['margin_closed_bottom'],
        (int) $s['margin_closed_left']
    );

    $margin_open = sprintf( '%dpx %dpx %dpx %dpx',
        (int) $s['margin_open_top'],
        (int) $s['margin_open_right'],
        (int) $s['margin_open_bottom'],
        (int) $s['margin_open_left']
    );

    $css = ":root{--cromo-gradient-h:{$gh}px;}
.cromo-term-description{position:relative;}
.cromo-term-description.is-collapsed{margin:{$margin_closed};overflow:hidden;}
.cromo-term-description .cromo-fade{position:absolute;left:0;right:0;bottom:0;height:var(--cromo-gradient-h);pointer-events:none;background:linear-gradient(to bottom,rgba(255,255,255,0) 0%,{$gc} 100%);}
.cromo-term-description .cromo-more-wrap{position:absolute;left:16px;bottom:16px;z-index:2;}
.cromo-term-description .cromo-more-btn{display:inline-flex;align-items:center;gap:6px;background:{$bg};color:{$tc};border:{$border};border-radius:{$radius}px;padding:{$pad}px;text-decoration:none;cursor:pointer;line-height:1.2;{$font_family_css}{$font_weight_css}{$font_style_css}}
.cromo-term-description .cromo-more-btn:hover{opacity:.9;}
.cromo-term-description .cromo-more-btn:focus{outline:2px solid {$s['border_color']};outline-offset:2px;}
.cromo-term-description .cromo-more-icon{display:inline-block;vertical-align:middle;max-width:" . (int) $s['icon_width'] . "px;height:auto;flex-shrink:0;}
.cromo-term-description.cromo-expanded{margin:{$margin_open};overflow:visible;}
.cromo-term-description.cromo-expanded .cromo-fade{display:none;}
.cromo-term-description.cromo-expanded .cromo-more-wrap{display:none;}
@media (prefers-reduced-motion: no-preference){
.cromo-term-description .cromo-fade{transition:opacity .25s ease;}
}";

    echo '<style id="cromo-inline">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// =====================================================================
// ВЫВОД ОПИСАНИЯ
// =====================================================================

add_action( 'woocommerce_archive_description', 'cromo_takeover_archive_description', 1 );
function cromo_takeover_archive_description( $term ) {
    if ( ! ( $term instanceof WP_Term ) ) {
        $queried = get_queried_object();
        if ( $queried instanceof WP_Term ) {
            $term = $queried;
        } else {
            return;
        }
    }

    ob_start();
    if ( is_product_taxonomy() ) {
        if ( has_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description' ) ) {
            remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
        }
    }
    ob_end_clean();

    cromo_render_term_description( $term );
}

function cromo_render_term_description( $term ) {
    if ( ! ( $term instanceof WP_Term ) ) {
        $queried = get_queried_object();
        if ( $queried instanceof WP_Term ) {
            $term = $queried;
        } else {
            return;
        }
    }

    remove_filter( 'term_description', 'cromo_filter_term_description', 20 );
    $desc = term_description( $term );
    add_filter( 'term_description', 'cromo_filter_term_description', 20 );
    if ( empty( $desc ) || '' === trim( wp_strip_all_tags( $desc ) ) ) {
        return;
    }

    $s         = cromo_get_settings();
    $plain_len = mb_strlen( wp_strip_all_tags( $desc ) );
    $limit     = (int) $s['char_limit'];
    $need_fade = $plain_len > $limit;

    $visible_h    = max( 180, (int) round( $limit * 0.45 ) );
    $wrap_classes = 'cromo-term-description' . ( $need_fade ? ' is-collapsed' : '' );
    $style_attr   = $need_fade
        ? sprintf( ' style="max-height:%1$dpx; overflow:hidden;"', $visible_h )
        : '';

    echo '<div class="' . esc_attr( $wrap_classes ) . '"' . $style_attr . ' data-cromo-collapsed="' . ( $need_fade ? '1' : '0' ) . '">';
    echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    if ( $need_fade ) {
        echo '<div class="cromo-fade" aria-hidden="true"></div>';
        echo '<div class="cromo-more-wrap">';
        echo '<button type="button" class="cromo-more-btn" aria-expanded="false" aria-controls="cromo-desc-' . esc_attr( $term->term_id ) . '">';
        if ( ! empty( $s['icon_url'] ) ) {
            echo '<img src="' . esc_url( $s['icon_url'] ) . '" alt="" class="cromo-more-icon">';
        }
        echo '<span>' . esc_html( $s['button_text'] ) . '</span>';
        echo '</button>';
        echo '</div>';
    }
    echo '</div>';
}

// =====================================================================
// FALLBACK ДЛЯ ТЕМ
// =====================================================================

add_filter( 'term_description', 'cromo_filter_term_description', 20 );
function cromo_filter_term_description( $description ) {
    if ( ! is_product_taxonomy() ) {
        return $description;
    }
    if ( strpos( $description, 'cromo-term-description' ) !== false ) {
        return $description;
    }
    $s         = cromo_get_settings();
    $plain_len = mb_strlen( wp_strip_all_tags( $description ) );
    if ( $plain_len <= (int) $s['char_limit'] ) {
        return $description;
    }

    $limit     = (int) $s['char_limit'];
    $visible_h = max( 180, (int) round( $limit * 0.45 ) );
    $term      = get_queried_object();

    $html  = '<div class="cromo-term-description is-collapsed" style="max-height:' . $visible_h . 'px; overflow:hidden;" data-cromo-collapsed="1">';
    $html .= $description;
    $html .= '<div class="cromo-fade" aria-hidden="true"></div>';
    $html .= '<div class="cromo-more-wrap">';
    $html .= '<button type="button" class="cromo-more-btn" aria-expanded="false" aria-controls="cromo-desc-' . ( $term instanceof WP_Term ? esc_attr( $term->term_id ) : '' ) . '">';
    if ( ! empty( $s['icon_url'] ) ) {
        $html .= '<img src="' . esc_url( $s['icon_url'] ) . '" alt="" class="cromo-more-icon">';
    }
    $html .= '<span>' . esc_html( $s['button_text'] ) . '</span>';
    $html .= '</button></div></div>';
    return $html;
}

// =====================================================================
// ФРОНТЕНД-СКРИПТ
// =====================================================================

add_action( 'wp_enqueue_scripts', 'cromo_enqueue_script' );
function cromo_enqueue_script() {
    if ( ! is_product_taxonomy() ) {
        return;
    }
    wp_register_script( 'cromo-front', false, array(), CROMO_VERSION, true );
    wp_enqueue_script( 'cromo-front' );

    $js = "(function(){
        function expand(block, btn){
            block.classList.remove('is-collapsed');
            block.classList.add('cromo-expanded');
            block.style.setProperty('max-height', 'none', 'important');
            block.style.setProperty('overflow',  'visible', 'important');
            block.style.setProperty('height',    'auto', 'important');

            var p = block.parentElement;
            while (p && p !== document.body) {
                p.style.setProperty('max-height',         'none', 'important');
                p.style.setProperty('overflow',          'visible', 'important');
                p.style.setProperty('height',            'auto', 'important');
                p.style.setProperty('-webkit-line-clamp','unset', 'important');
                p = p.parentElement;
            }
            var all = block.querySelectorAll('*');
            for (var i = 0; i < all.length; i++) {
                all[i].style.setProperty('max-height',         'none', 'important');
                all[i].style.setProperty('overflow',          'visible', 'important');
                all[i].style.setProperty('height',            'auto', 'important');
                all[i].style.setProperty('-webkit-line-clamp','unset', 'important');
            }

            if (btn) { btn.setAttribute('aria-expanded', 'true'); }
        }
        document.addEventListener('click', function(e){
            var btn = e.target.closest && e.target.closest('.cromo-more-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var block = btn.closest('.cromo-term-description');
            if (!block) return;
            expand(block, btn);
        });
    })();";
    wp_add_inline_script( 'cromo-front', $js );
}

// =====================================================================
// ССЫЛКА НА СТРАНИЦЕ ПЛАГИНОВ
// =====================================================================

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cromo_action_links' );
function cromo_action_links( $links ) {
    $url  = admin_url( 'admin.php?page=catreadmore-odinokov' );
    $link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Настроить', 'catreadmore-odinokov' ) . '</a>';
    array_unshift( $links, $link );
    return $links;
}
