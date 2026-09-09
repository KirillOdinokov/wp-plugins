<?php
/**
 * Plugin Name:       Order Share Odinokov Extended
 * Plugin URI:        https://github.com/KirillOdinokov/wp-plugins
 * Description:       Расширенная версия Order Share Odinokov: дополнительный блок «Сопровождение проекта» с кнопками «Заказать образец», «Провести испытания», «Заказать выезд на объект» и PopUp-формами.
 * Version:           1.0.4
 * Author:            Odinokov
 * Author URI:        https://github.com/KirillOdinokov/wp-plugins
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       order-share-extended
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OSOE_VERSION', '1.0.4' );
define( 'OSOE_FILE', __FILE__ );
define( 'OSOE_DIR', plugin_dir_path( __FILE__ ) );
define( 'OSOE_URL', plugin_dir_url( __FILE__ ) );

require_once OSOE_DIR . 'includes/class-osoe-updater.php';
require_once OSOE_DIR . 'includes/class-osoe.php';

new OSOE_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-order-share-extended.json',
    OSOE_VERSION,
    array(
        'name'        => 'Order Share Odinokov Extended',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Блок «Сопровождение проекта»: заказ образца, испытания, выезд на объект.',
    )
);

add_action( 'plugins_loaded', 'osoe_init', 20 );
function osoe_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'osoe_wc_missing_notice' );
        return;
    }
    new OSOE_Main();
}

function osoe_wc_missing_notice() {
    echo '<div class="error notice"><p>' . esc_html__( 'Для плагина Order Share Odinokov Extended необходимо установить и активировать WooCommerce.', 'order-share-extended' ) . '</p></div>';
}

function osoe_defaults() {
    return array(
        'block_title'          => 'Сопровождение проекта',
        'block_border_width'   => 1,
        'block_border_color'   => '#e5e5e5',
        'block_border_radius'  => 8,
        'block_bg'             => '#f9f9f9',
        'block_padding'        => 20,
        'title_font_ratio'     => 130,
        'btn_font_ratio'       => 80,
        'caption_font_ratio'   => 70,
        'layout'               => 'column',
        'show_icons'           => 0,

        'btn1_enabled'         => 1,
        'btn1_label'           => 'Заказать образец',
        'btn1_caption'         => 'Вышлем образец СДЕК/КСЭ, если Вы уже наш клиент - за наш счет, если ещё ничего не купили - за Ваш',
        'btn1_icon'            => 'fa-solid fa-box',

        'btn2_enabled'         => 1,
        'btn2_label'           => 'Провести испытания',
        'btn2_caption'         => 'Для материалов типа фасадного крепежа, где это необходимо, можем провести испытания с предоставлением протокола испытаний',
        'btn2_icon'            => 'fa-solid fa-flask',

        'btn3_enabled'         => 1,
        'btn3_label'           => 'Заказать выезд на объект',
        'btn3_caption'         => 'Выедем на объект, проведем шэф монтаж или правильно подберем решение',
        'btn3_icon'            => 'fa-solid fa-truck',
    );
}

function osoe_get_settings() {
    $defaults = osoe_defaults();
    $opts     = get_option( 'osoe_settings', array() );
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }
    $merged = array_merge( $defaults, $opts );

    foreach ( array( 'btn1_enabled', 'btn2_enabled', 'btn3_enabled' ) as $k ) {
        $merged[ $k ] = ! empty( $merged[ $k ] ) ? 1 : 0;
    }
    $merged['show_icons'] = ! empty( $merged['show_icons'] ) ? 1 : 0;
    $merged['layout'] = in_array( $merged['layout'], array( 'column', 'row' ), true ) ? $merged['layout'] : 'column';
    $merged['block_border_width'] = max( 0, min( 20, (int) $merged['block_border_width'] ) );
    $merged['block_border_radius'] = max( 0, min( 100, (int) $merged['block_border_radius'] ) );
    $merged['block_padding'] = max( 0, min( 60, (int) $merged['block_padding'] ) );
    $merged['title_font_ratio'] = max( 50, min( 300, (int) $merged['title_font_ratio'] ) );
    $merged['btn_font_ratio'] = max( 20, min( 200, (int) $merged['btn_font_ratio'] ) );
    $merged['caption_font_ratio'] = max( 20, min( 200, (int) $merged['caption_font_ratio'] ) );

    $merged['block_title'] = sanitize_text_field( wp_unslash( $merged['block_title'] ) );
    $merged['btn1_label'] = sanitize_text_field( wp_unslash( $merged['btn1_label'] ) );
    $merged['btn1_caption'] = sanitize_text_field( wp_unslash( $merged['btn1_caption'] ) );
    $merged['btn1_icon'] = osoe_sanitize_icon_class( $merged['btn1_icon'] );
    $merged['btn2_label'] = sanitize_text_field( wp_unslash( $merged['btn2_label'] ) );
    $merged['btn2_caption'] = sanitize_text_field( wp_unslash( $merged['btn2_caption'] ) );
    $merged['btn2_icon'] = osoe_sanitize_icon_class( $merged['btn2_icon'] );
    $merged['btn3_label'] = sanitize_text_field( wp_unslash( $merged['btn3_label'] ) );
    $merged['btn3_caption'] = sanitize_text_field( wp_unslash( $merged['btn3_caption'] ) );
    $merged['btn3_icon'] = osoe_sanitize_icon_class( $merged['btn3_icon'] );
    $merged['block_border_color'] = osoe_sanitize_color( $merged['block_border_color'] );
    $merged['block_bg'] = osoe_sanitize_color( $merged['block_bg'] );

    return $merged;
}

function osoe_sanitize_icon_class( $v ) {
    $v = is_string( $v ) ? trim( $v ) : '';
    $v = wp_strip_all_tags( $v );
    $v = preg_replace( '/[^A-Za-z0-9_\- ]/', '', $v );
    return substr( trim( $v ), 0, 100 );
}

function osoe_sanitize_color( $c ) {
    $c = is_string( $c ) ? trim( $c ) : '';
    if ( '' === $c ) {
        return '';
    }
    if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $c ) ) {
        return $c;
    }
    if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $c ) ) {
        return $c;
    }
    return '';
}

/**
 * Базовый стиль кнопок — читается из тех же опций БД, что и основной плагин
 * (option `oso_settings`), поэтому настройки сохраняются даже если основной
 * плагин деактивирован или удалён.
 */
function osoe_get_base_style() {
    $base = array(
        'font_family'  => 'inherit',
        'font_size'    => 14,
        'border_color' => '#222222',
        'border_width' => 1,
        'border_radius'=> 6,
        'text_color'   => '#222222',
        'bg_color'     => '#ffffff',
        'padding_v'    => 10,
        'padding_h'    => 18,
        'font_weight'  => 600,
        'uppercase'    => 0,
    );

    $s = get_option( 'oso_settings', array() );
    if ( is_array( $s ) ) {
        foreach ( $base as $k => $v ) {
            if ( isset( $s[ $k ] ) && '' !== $s[ $k ] ) {
                $base[ $k ] = $s[ $k ];
            }
        }
    }

    return $base;
}

function osoe_get_email_to() {
    $to = get_option( 'oso_email_to', '' );
    if ( empty( $to ) ) {
        $to = get_option( 'admin_email' );
    }
    return $to;
}

add_action( 'phpmailer_init', 'osoe_phpmailer_init' );
function osoe_phpmailer_init( $phpmailer ) {
    if ( function_exists( 'oso_phpmailer_init' ) ) {
        return;
    }
    $smtp = get_option( 'oso_smtp', array() );
    if ( ! is_array( $smtp ) || empty( $smtp['enabled'] ) || empty( $smtp['host'] ) ) {
        return;
    }
    $phpmailer->isSMTP();
    $phpmailer->Host       = $smtp['host'];
    $phpmailer->Port       = (int) $smtp['port'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = $smtp['username'];
    $phpmailer->Password   = $smtp['password'];
    $phpmailer->SMTPSecure = ( 'none' === $smtp['secure'] ) ? '' : $smtp['secure'];
    $phpmailer->CharSet    = 'UTF-8';

    if ( ! empty( $smtp['from_email'] ) && is_email( $smtp['from_email'] ) ) {
        $phpmailer->setFrom( $smtp['from_email'], $smtp['from_name'] ? $smtp['from_name'] : get_bloginfo( 'name' ) );
    }
}

add_action( 'admin_init', 'osoe_register_settings' );
function osoe_register_settings() {
    register_setting(
        'osoe_settings_group',
        'osoe_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'osoe_sanitize_settings',
            'default'           => osoe_defaults(),
        )
    );
}

function osoe_sanitize_settings( $input ) {
    $defaults = osoe_defaults();
    $input    = is_array( $input ) ? $input : array();
    $out      = array();

    foreach ( array( 'btn1_enabled', 'btn2_enabled', 'btn3_enabled' ) as $b ) {
        $out[ $b ] = ! empty( $input[ $b ] ) ? 1 : 0;
    }
    $out['show_icons'] = ! empty( $input['show_icons'] ) ? 1 : 0;
    $out['layout'] = ( isset( $input['layout'] ) && 'row' === $input['layout'] ) ? 'row' : 'column';

    $texts = array(
        'block_title',
        'btn1_label', 'btn1_caption',
        'btn2_label', 'btn2_caption',
        'btn3_label', 'btn3_caption',
    );
    foreach ( $texts as $t ) {
        $out[ $t ] = isset( $input[ $t ] ) ? sanitize_text_field( wp_unslash( $input[ $t ] ) ) : $defaults[ $t ];
        if ( '' === $out[ $t ] && isset( $defaults[ $t ] ) ) {
            $out[ $t ] = $defaults[ $t ];
        }
    }

    foreach ( array( 'btn1_icon', 'btn2_icon', 'btn3_icon' ) as $i ) {
        $out[ $i ] = isset( $input[ $i ] ) ? osoe_sanitize_icon_class( $input[ $i ] ) : $defaults[ $i ];
    }

    $nums = array(
        'block_border_width'  => array( 0, 20 ),
        'block_border_radius' => array( 0, 100 ),
        'block_padding'       => array( 0, 60 ),
        'title_font_ratio'    => array( 50, 300 ),
        'btn_font_ratio'      => array( 20, 200 ),
        'caption_font_ratio'  => array( 20, 200 ),
    );
    foreach ( $nums as $k => $r ) {
        $val = isset( $input[ $k ] ) ? (int) $input[ $k ] : (int) $defaults[ $k ];
        $out[ $k ] = max( $r[0], min( $r[1], $val ) );
    }

    $out['block_border_color'] = osoe_sanitize_color( $input['block_border_color'] ?? $defaults['block_border_color'] );
    $out['block_bg']           = osoe_sanitize_color( $input['block_bg'] ?? $defaults['block_bg'] );

    return $out;
}

add_action( 'admin_menu', 'osoe_register_menu' );
function osoe_register_menu() {
    global $menu;
    $exists = false;
    if ( is_array( $menu ) ) {
        foreach ( $menu as $item ) {
            if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) { $exists = true; break; }
        }
    }
    if ( ! $exists ) {
        add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', 'osoe_dashboard', 'dashicons-admin-settings', 30 );
    }
    add_submenu_page(
        'odinokov-plugins',
        'Order Share Extended',
        'Order Share Extended',
        'manage_options',
        'order-share-extended',
        'osoe_render_settings_page'
    );
}

function osoe_dashboard() {
    ?>
    <div class="wrap"><h1>Плагины Одиноков</h1>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <h3 style="margin-top:0;">Order Share Odinokov Extended</h3>
            <p>Блок «Сопровождение проекта»: образцы, испытания, выезд на объект.</p>
        </div>
    </div></div>
    <?php
}

function osoe_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $s = osoe_get_settings();
    ?>
    <div class="wrap osoe-admin">
        <h1>Order Share Odinokov Extended — настройки</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'osoe_settings_group' ); ?>

            <h2>Блок «Сопровождение проекта»</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Заголовок блока</th>
                    <td><input type="text" class="regular-text" name="osoe_settings[block_title]" value="<?php echo esc_attr( $s['block_title'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Фон блока</th>
                    <td><input type="text" class="osoe-color" name="osoe_settings[block_bg]" value="<?php echo esc_attr( $s['block_bg'] ); ?>" placeholder="#f9f9f9"></td>
                </tr>
                <tr>
                    <th scope="row">Цвет рамки блока</th>
                    <td><input type="text" class="osoe-color" name="osoe_settings[block_border_color]" value="<?php echo esc_attr( $s['block_border_color'] ); ?>" placeholder="#e5e5e5"></td>
                </tr>
                <tr>
                    <th scope="row">Толщина рамки блока (px)</th>
                    <td><input type="number" min="0" max="20" name="osoe_settings[block_border_width]" value="<?php echo esc_attr( $s['block_border_width'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Закругление рамки блока (px)</th>
                    <td><input type="number" min="0" max="100" name="osoe_settings[block_border_radius]" value="<?php echo esc_attr( $s['block_border_radius'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Внутренний отступ блока (px)</th>
                    <td><input type="number" min="0" max="60" name="osoe_settings[block_padding]" value="<?php echo esc_attr( $s['block_padding'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Расположение кнопок</th>
                    <td>
                        <select name="osoe_settings[layout]">
                            <option value="column" <?php selected( $s['layout'], 'column' ); ?>>В столбик</option>
                            <option value="row" <?php selected( $s['layout'], 'row' ); ?>>В строку</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Показывать иконки</th>
                    <td><label><input type="checkbox" name="osoe_settings[show_icons]" value="1" <?php checked( $s['show_icons'], 1 ); ?>> показывать иконки на кнопках</label></td>
                </tr>
            </table>

            <h2>Размеры шрифтов (в % от базового)</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Заголовок блока (%)</th>
                    <td><input type="number" min="50" max="300" name="osoe_settings[title_font_ratio]" value="<?php echo esc_attr( $s['title_font_ratio'] ); ?>"> <span class="description">по умолчанию 130%</span></td>
                </tr>
                <tr>
                    <th scope="row">Кнопки (%)</th>
                    <td><input type="number" min="20" max="200" name="osoe_settings[btn_font_ratio]" value="<?php echo esc_attr( $s['btn_font_ratio'] ); ?>"> <span class="description">по умолчанию 80%</span></td>
                </tr>
                <tr>
                    <th scope="row">Подписи под кнопками (%)</th>
                    <td><input type="number" min="20" max="200" name="osoe_settings[caption_font_ratio]" value="<?php echo esc_attr( $s['caption_font_ratio'] ); ?>"> <span class="description">по умолчанию 70% от размера кнопки</span></td>
                </tr>
            </table>

            <h2>Кнопка 1 — «Заказать образец»</h2>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Включить</th><td><label><input type="checkbox" name="osoe_settings[btn1_enabled]" value="1" <?php checked( $s['btn1_enabled'], 1 ); ?>> показывать кнопку</label></td></tr>
                <tr><th scope="row">Текст кнопки</th><td><input type="text" class="regular-text" name="osoe_settings[btn1_label]" value="<?php echo esc_attr( $s['btn1_label'] ); ?>"></td></tr>
                <tr><th scope="row">Иконка (Font Awesome)</th><td><input type="text" class="regular-text" name="osoe_settings[btn1_icon]" value="<?php echo esc_attr( $s['btn1_icon'] ); ?>" placeholder="fa-solid fa-box"></td></tr>
                <tr><th scope="row">Подпись под кнопкой</th><td><textarea name="osoe_settings[btn1_caption]" rows="3" class="large-text"><?php echo esc_textarea( $s['btn1_caption'] ); ?></textarea></td></tr>
            </table>

            <h2>Кнопка 2 — «Провести испытания»</h2>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Включить</th><td><label><input type="checkbox" name="osoe_settings[btn2_enabled]" value="1" <?php checked( $s['btn2_enabled'], 1 ); ?>> показывать кнопку</label></td></tr>
                <tr><th scope="row">Текст кнопки</th><td><input type="text" class="regular-text" name="osoe_settings[btn2_label]" value="<?php echo esc_attr( $s['btn2_label'] ); ?>"></td></tr>
                <tr><th scope="row">Иконка (Font Awesome)</th><td><input type="text" class="regular-text" name="osoe_settings[btn2_icon]" value="<?php echo esc_attr( $s['btn2_icon'] ); ?>" placeholder="fa-solid fa-flask"></td></tr>
                <tr><th scope="row">Подпись под кнопкой</th><td><textarea name="osoe_settings[btn2_caption]" rows="3" class="large-text"><?php echo esc_textarea( $s['btn2_caption'] ); ?></textarea></td></tr>
            </table>

            <h2>Кнопка 3 — «Заказать выезд на объект»</h2>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Включить</th><td><label><input type="checkbox" name="osoe_settings[btn3_enabled]" value="1" <?php checked( $s['btn3_enabled'], 1 ); ?>> показывать кнопку</label></td></tr>
                <tr><th scope="row">Текст кнопки</th><td><input type="text" class="regular-text" name="osoe_settings[btn3_label]" value="<?php echo esc_attr( $s['btn3_label'] ); ?>"></td></tr>
                <tr><th scope="row">Иконка (Font Awesome)</th><td><input type="text" class="regular-text" name="osoe_settings[btn3_icon]" value="<?php echo esc_attr( $s['btn3_icon'] ); ?>" placeholder="fa-solid fa-truck"></td></tr>
                <tr><th scope="row">Подпись под кнопкой</th><td><textarea name="osoe_settings[btn3_caption]" rows="3" class="large-text"><?php echo esc_textarea( $s['btn3_caption'] ); ?></textarea></td></tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <style>
        .osoe-admin .form-table th{width:300px;}
    </style>
    <?php
}

add_action( 'admin_enqueue_scripts', 'osoe_admin_enqueue' );
function osoe_admin_enqueue( $hook ) {
    if ( false === strpos( $hook, 'order-share-extended' ) ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script( 'wp-color-picker', '(function($){$(function(){$(".osoe-color").wpColorPicker();});})(jQuery);' );
}
