<?php
/**
 * Plugin Name:       Order Share Odinokov
 * Plugin URI:        https://github.com/KirillOdinokov/wp-plugins
 * Description:       Объединённый плагин: кнопки «Отправить» (Web Share API), «Сохранить PDF» (Print) и «Оставить заявку» (PopUp форма) на страницах товара WooCommerce. Полная настройка стиля всех трёх кнопок из админки. Защита от ботов, капча, отключение add-to-cart. Шорткод [sert-request] — форма запроса документации.
 * Version:           1.0.9
 * Author:            Odinokov
 * Author URI:        https://github.com/KirillOdinokov/wp-plugins
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       order-share-odinokov
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'OSO_VERSION' ) ) {
    define( 'OSO_VERSION', '1.0.9' );
}
if ( ! defined( 'OSO_FILE' ) ) {
    define( 'OSO_FILE', __FILE__ );
}
if ( ! defined( 'OSO_DIR' ) ) {
    define( 'OSO_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'OSO_URL' ) ) {
    define( 'OSO_URL', plugin_dir_url( __FILE__ ) );
}

require_once OSO_DIR . 'includes/helpers.php';
require_once OSO_DIR . 'includes/class-order.php';
require_once OSO_DIR . 'includes/class-share.php';
require_once OSO_DIR . 'includes/class-sert-request.php';
require_once OSO_DIR . 'includes/class-oso-updater.php';

new OSO_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/order-share-odinokov.json',
    OSO_VERSION,
    array(
        'name'        => 'Order Share Odinokov',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Кнопки «Отправить», «Сохранить PDF», «Оставить заявку» на страницах WooCommerce.',
    )
);

add_action( 'plugins_loaded', 'oso_init', 20 );
function oso_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'oso_wc_missing_notice' );
        return;
    }
    load_plugin_textdomain( 'order-share-odinokov', false, dirname( plugin_basename( OSO_FILE ) ) . '/languages' );
    new OSO_Order();
    new OSO_Share();
    new OSO_Sert_Request();
}

function oso_wc_missing_notice() {
    echo '<div class="error notice"><p>' . esc_html__( 'Для плагина Order Share Odinokov необходимо установить и активировать WooCommerce.', 'order-share-odinokov' ) . '</p></div>';
}

register_activation_hook( __FILE__, 'oso_activate' );
function oso_activate() {
    $opts = get_option( 'oso_settings', array() );
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }
    if ( empty( $opts ) ) {
        $defaults = oso_defaults();
        update_option( 'oso_settings', $defaults );
    }
    if ( ! get_option( 'oso_email_to' ) ) {
        update_option( 'oso_email_to', get_option( 'admin_email' ) );
    }
}

register_uninstall_hook( __FILE__, 'oso_uninstall' );
function oso_uninstall() {
    delete_option( 'oso_settings' );
    delete_option( 'oso_email_to' );
}

function oso_defaults() {
    return array(
        'enable_share_product'    => 1,
        'enable_share_post'       => 1,
        'enable_order_product'    => 1,
        'enable_order_category'   => 1,
        'disable_add_to_cart'     => 1,

        'share_text'              => 'Отправить',
        'pdf_text'                => 'Сохранить PDF',
        'order_text'              => 'Оставить заявку',
        'share_icon'              => 'fa-solid fa-share-nodes',
        'pdf_icon'                => 'fa-solid fa-file-pdf',
        'order_icon'              => 'fa-regular fa-paper-plane',
        'custom_share_icon'       => '',
        'custom_pdf_icon'         => '',
        'custom_order_icon'       => '',
        'icon_set'                => 'black and bold',

        'font_family'             => 'inherit',
        'custom_fonts'            => '',
        'font_size'               => 14,
        'icon_size_product'       => 18,
        'icon_size_category'      => 18,
        'bg_color'                => '#ffffff',
        'text_color'              => '#222222',
        'border_color'            => '#222222',
        'border_width'            => 1,
        'border_radius'           => 6,
        'padding_v'               => 10,
        'padding_h'               => 18,
        'gap'                     => 10,
        'uppercase'               => 0,
        'font_weight'             => 600,

        'print_button'            => 1,
        'pdf_filename'            => 'page',

        'field_inn'               => 1,
        'field_name'              => 1,
        'field_email'             => 1,
        'field_accessories'       => 1,
        'field_files'             => 1,
        'field_delivery'          => 1,
        'field_captcha'           => 1,
    );
}

function oso_get_settings() {
    $defaults = oso_defaults();
    $opts     = get_option( 'oso_settings', array() );
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }
    $merged = array_merge( $defaults, $opts );

    foreach ( array( 'enable_share_product', 'enable_share_post', 'enable_order_product', 'enable_order_category', 'disable_add_to_cart', 'print_button', 'uppercase' ) as $k ) {
        $merged[ $k ] = ! empty( $merged[ $k ] ) ? 1 : 0;
    }
    foreach ( array( 'field_inn', 'field_name', 'field_email', 'field_accessories', 'field_files', 'field_delivery', 'field_captcha' ) as $k ) {
        $merged[ $k ] = ! empty( $merged[ $k ] ) ? 1 : 0;
    }

    $merged['font_size']       = max( 8, min( 60, (int) $merged['font_size'] ) );
    $merged['icon_size_product']  = max( 12, min( 80, (int) $merged['icon_size_product'] ) );
    $merged['icon_size_category'] = max( 12, min( 80, (int) $merged['icon_size_category'] ) );
    $merged['border_width']    = max( 0, min( 20, (int) $merged['border_width'] ) );
    $merged['border_radius']   = max( 0, min( 100, (int) $merged['border_radius'] ) );
    $merged['padding_v']       = max( 0, min( 60, (int) $merged['padding_v'] ) );
    $merged['padding_h']       = max( 0, min( 60, (int) $merged['padding_h'] ) );
    $merged['gap']             = max( 0, min( 60, (int) $merged['gap'] ) );
    $merged['font_weight']     = max( 100, min( 900, (int) $merged['font_weight'] ) );

    $merged['share_text']      = oso_sanitize_text( $merged['share_text'] );
    $merged['pdf_text']        = oso_sanitize_text( $merged['pdf_text'] );
    $merged['order_text']      = oso_sanitize_text( $merged['order_text'] );
    $merged['share_icon']      = oso_sanitize_icon_class( $merged['share_icon'] );
    $merged['pdf_icon']        = oso_sanitize_icon_class( $merged['pdf_icon'] );
    $merged['order_icon']      = oso_sanitize_icon_class( $merged['order_icon'] );
    $merged['custom_share_icon']= oso_sanitize_url( $merged['custom_share_icon'] );
    $merged['custom_pdf_icon'] = oso_sanitize_url( $merged['custom_pdf_icon'] );
    $merged['custom_order_icon']= oso_sanitize_url( $merged['custom_order_icon'] );
    $merged['pdf_filename']    = oso_sanitize_filename( $merged['pdf_filename'] );
    $merged['font_family']     = oso_sanitize_font_family( $merged['font_family'] );
    $merged['custom_fonts']    = oso_sanitize_font_list( $merged['custom_fonts'] );
    $merged['bg_color']        = oso_sanitize_color( $merged['bg_color'] );
    $merged['text_color']      = oso_sanitize_color( $merged['text_color'] );
    $merged['border_color']    = oso_sanitize_color( $merged['border_color'] );

    return $merged;
}

function oso_sanitize_text( $v ) {
    $v = is_string( $v ) ? trim( $v ) : '';
    $v = sanitize_text_field( wp_unslash( $v ) );
    return substr( $v, 0, 200 );
}
function oso_sanitize_icon_class( $v ) {
    $v = is_string( $v ) ? trim( $v ) : '';
    $v = wp_strip_all_tags( $v );
    $v = preg_replace( '/[^A-Za-z0-9_\-\s]/', '', $v );
    $v = preg_replace( '/\s+/', ' ', $v );
    return substr( trim( $v ), 0, 100 );
}
function oso_sanitize_url( $v ) {
    $v = is_string( $v ) ? trim( $v ) : '';
    if ( '' === $v ) {
        return '';
    }
    return esc_url_raw( $v, array( 'http', 'https' ) );
}
function oso_sanitize_filename( $v ) {
    $v = is_string( $v ) ? trim( $v ) : '';
    $v = sanitize_file_name( $v );
    $v = preg_replace( '/[^A-Za-z0-9_\-\.]/', '', $v );
    return substr( $v, 0, 80 );
}
function oso_sanitize_color( $c ) {
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
function oso_sanitize_font_family( $v ) {
    $v = is_string( $v ) ? trim( $v ) : '';
    if ( '' === $v || 'inherit' === strtolower( $v ) ) {
        return 'inherit';
    }
    $v = wp_strip_all_tags( $v );
    $v = preg_replace( '/[^A-Za-z0-9_\-,\s]/', '', $v );
    return substr( $v, 0, 200 );
}
function oso_sanitize_font_list( $v ) {
    if ( ! is_string( $v ) ) {
        return '';
    }
    $parts = array_filter( array_map( 'trim', explode( '|', $v ) ) );
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

function oso_get_icon_sets() {
    $dir = OSO_DIR . 'icons';
    if ( ! is_dir( $dir ) ) {
        return array();
    }
    $sets = array();
    $items = scandir( $dir );
    if ( ! is_array( $items ) ) {
        return array();
    }
    foreach ( $items as $item ) {
        if ( '.' === $item || '..' === $item ) {
            continue;
        }
        $path = $dir . '/' . $item;
        if ( is_dir( $path ) ) {
            $sets[] = $item;
        }
    }
    sort( $sets );
    return $sets;
}

function oso_sanitize_icon_set( $v ) {
    $v      = is_string( $v ) ? trim( $v ) : '';
    $valid  = oso_get_icon_sets();
    if ( in_array( $v, $valid, true ) ) {
        return $v;
    }
    return 'black and bold';
}

add_action( 'admin_init', 'oso_register_settings' );
add_action( 'admin_post_oso_force_check', 'oso_force_check' );
function oso_register_settings() {
    register_setting(
        'oso_settings_group',
        'oso_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'oso_sanitize_settings',
            'default'           => oso_defaults(),
        )
    );
    register_setting( 'oso_settings_group', 'oso_email_to', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_email',
    ) );
}

function oso_force_check() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
    check_admin_referer( 'oso_force_check', 'oso_force_check_nonce' );
    delete_transient( 'oso_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/order-share-odinokov.json' ) );
    set_site_transient( 'update_plugins', null );
    wp_safe_redirect( admin_url( 'plugins.php?oso_checked=1' ) );
    exit;
}

function oso_sanitize_settings( $input ) {
    $defaults = oso_defaults();
    $input    = is_array( $input ) ? $input : array();
    $out      = array();

    foreach ( array(
        'enable_share_product', 'enable_share_post', 'enable_order_product', 'enable_order_category',
        'disable_add_to_cart', 'print_button', 'uppercase',
        'field_inn', 'field_name', 'field_email', 'field_accessories', 'field_files', 'field_delivery', 'field_captcha',
    ) as $b ) {
        $out[ $b ] = ! empty( $input[ $b ] ) ? 1 : 0;
    }

    $out['share_text']       = oso_sanitize_text( $input['share_text'] ?? $defaults['share_text'] );
    if ( '' === $out['share_text'] ) {
        $out['share_text'] = $defaults['share_text'];
    }
    $out['pdf_text']         = oso_sanitize_text( $input['pdf_text'] ?? $defaults['pdf_text'] );
    if ( '' === $out['pdf_text'] ) {
        $out['pdf_text'] = $defaults['pdf_text'];
    }
    $out['order_text']       = oso_sanitize_text( $input['order_text'] ?? $defaults['order_text'] );
    if ( '' === $out['order_text'] ) {
        $out['order_text'] = $defaults['order_text'];
    }

    $out['share_icon']       = oso_sanitize_icon_class( $input['share_icon'] ?? $defaults['share_icon'] );
    $out['pdf_icon']         = oso_sanitize_icon_class( $input['pdf_icon'] ?? $defaults['pdf_icon'] );
    $out['order_icon']       = oso_sanitize_icon_class( $input['order_icon'] ?? $defaults['order_icon'] );
    $out['custom_share_icon']= oso_sanitize_url( $input['custom_share_icon'] ?? '' );
    $out['custom_pdf_icon']  = oso_sanitize_url( $input['custom_pdf_icon'] ?? '' );
    $out['custom_order_icon']= oso_sanitize_url( $input['custom_order_icon'] ?? '' );
    $out['icon_set']         = oso_sanitize_icon_set( $input['icon_set'] ?? $defaults['icon_set'] );
    $out['pdf_filename']     = oso_sanitize_filename( $input['pdf_filename'] ?? $defaults['pdf_filename'] );
    if ( '' === $out['pdf_filename'] ) {
        $out['pdf_filename'] = $defaults['pdf_filename'];
    }

    $out['font_family']      = oso_sanitize_font_family( $input['font_family'] ?? $defaults['font_family'] );
    $out['custom_fonts']     = oso_sanitize_font_list( $input['custom_fonts'] ?? '' );
    $out['bg_color']         = oso_sanitize_color( $input['bg_color'] ?? $defaults['bg_color'] );
    $out['text_color']       = oso_sanitize_color( $input['text_color'] ?? $defaults['text_color'] );
    $out['border_color']     = oso_sanitize_color( $input['border_color'] ?? $defaults['border_color'] );

    $nums = array(
        'font_size'          => array( 8, 60 ),
        'icon_size_product'  => array( 12, 80 ),
        'icon_size_category' => array( 12, 80 ),
        'border_width'       => array( 0, 20 ),
        'border_radius' => array( 0, 100 ),
        'padding_v'     => array( 0, 60 ),
        'padding_h'     => array( 0, 60 ),
        'gap'           => array( 0, 60 ),
        'font_weight'   => array( 100, 900 ),
    );
    foreach ( $nums as $k => $r ) {
        $val = isset( $input[ $k ] ) ? (int) $input[ $k ] : (int) $defaults[ $k ];
        $out[ $k ] = max( $r[0], min( $r[1], $val ) );
    }

    return $out;
}

add_action( 'admin_menu', 'oso_register_menu' );
function oso_register_menu() {
    global $menu;
    $e = false;
    if ( is_array( $menu ) ) {
        foreach ( $menu as $i ) { if ( isset( $i[2] ) && 'odinokov-plugins' === $i[2] ) { $e = true; break; } }
    }
    if ( ! $e ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', 'oso_dashboard', 'dashicons-admin-settings', 30 );

    add_submenu_page(
        'odinokov-plugins',
        esc_html__( 'Order Share Odinokov', 'order-share-odinokov' ),
        esc_html__( 'Order Share', 'order-share-odinokov' ),
        'manage_options',
        'order-share-odinokov',
        'oso_render_settings_page'
    );
}

function oso_dashboard() {
    ?>
    <div class="wrap"><h1>Плагины Одиноков</h1>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <h3 style="margin-top:0;">Order Share Odinokov</h3>
            <p>Кнопки «Отправить», «Сохранить PDF», «Оставить заявку».</p>
        </div>
    </div></div>
    <?php
}

add_action( 'admin_enqueue_scripts', 'oso_admin_enqueue' );
function oso_admin_enqueue( $hook ) {
    if ( false === strpos( $hook, 'order-share-odinokov' ) ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_media();
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script( 'wp-color-picker', '(function($){$(function(){$(".oso-color").wpColorPicker();});})(jQuery);' );
    wp_add_inline_script( 'jquery-core', '(function($){$(function(){$("body").on("click",".oso-upload-icon",function(e){e.preventDefault();var b=$(this);var f=b.siblings("input.oso-icon-url");var p=b.siblings("img.oso-icon-preview");var frame=wp.media({title:"Выберите иконку",button:{text:"Использовать"},library:{type:["image","image/svg+xml"]},multiple:false});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();f.val(a.url).trigger("change");if(p.length){p.attr("src",a.url).show();}else{p=$("<img>").attr("src",a.url).addClass("oso-icon-preview").css({maxWidth:"40px",maxHeight:"40px",display:"inline-block",marginLeft:"8px",verticalAlign:"middle"}).insertAfter(b);}});frame.open();});$("body").on("click",".oso-clear-icon",function(e){e.preventDefault();var b=$(this);b.siblings("input.oso-icon-url").val("").trigger("change");var p=b.siblings("img.oso-icon-preview");if(p.length){p.hide().attr("src","");}});});})(jQuery);' );
}

function oso_get_font_choices() {
    $s = oso_get_settings();
    $choices = array(
        'inherit' => '— inherit (наследовать) —',
        'initial' => '— initial (по умолчанию браузера) —',
        'unset'   => '— unset (сброс) —',
    );
    if ( ! empty( $s['custom_fonts'] ) ) {
        foreach ( explode( '|', $s['custom_fonts'] ) as $f ) {
            $f = trim( $f );
            if ( '' !== $f ) {
                $choices[ $f ] = $f;
            }
        }
    }
    return $choices;
}

function oso_icon_upload_field( $name, $value ) {
    ?>
    <div class="oso-icon-upload">
        <input type="text" class="regular-text oso-icon-url" name="oso_settings[<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="https://...">
        <button type="button" class="button oso-upload-icon"><?php esc_html_e( 'Загрузить / Выбрать', 'order-share-odinokov' ); ?></button>
        <button type="button" class="button oso-clear-icon"><?php esc_html_e( 'Очистить', 'order-share-odinokov' ); ?></button>
        <?php if ( $value ) : ?>
            <img class="oso-icon-preview" src="<?php echo esc_url( $value ); ?>" alt="" style="max-width:40px;max-height:40px;display:inline-block;margin-left:8px;vertical-align:middle;">
        <?php endif; ?>
    </div>
    <?php
}

function oso_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $s     = oso_get_settings();
    $email = get_option( 'oso_email_to', get_option( 'admin_email' ) );
    ?>
    <div class="wrap oso-admin">
        <h1><?php echo esc_html__( 'Order Share Odinokov — настройки', 'order-share-odinokov' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'oso_settings_group' ); ?>
            <h2><?php esc_html_e( 'Где показывать', 'order-share-odinokov' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Показывать кнопки «Отправить» и «PDF» на товаре', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[enable_share_product]" value="1" <?php checked( $s['enable_share_product'], 1 ); ?>> <?php esc_html_e( 'Включить на Single Product Page', 'order-share-odinokov' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Показывать кнопки «Отправить» и «PDF» на записях', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[enable_share_post]" value="1" <?php checked( $s['enable_share_post'], 1 ); ?>> <?php esc_html_e( 'Включить на страницах Post (single)', 'order-share-odinokov' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Показывать кнопку «Оставить заявку» на товаре', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[enable_order_product]" value="1" <?php checked( $s['enable_order_product'], 1 ); ?>> <?php esc_html_e( 'Кнопка в карточке товара', 'order-share-odinokov' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Показывать кнопку «Оставить заявку» в категориях', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[enable_order_category]" value="1" <?php checked( $s['enable_order_category'], 1 ); ?>> <?php esc_html_e( 'Кнопка в списке товаров', 'order-share-odinokov' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Показывать блок кнопок на страницах записей (post)', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[enable_share_post]" value="1" <?php checked( $s['enable_share_post'], 1 ); ?>> <?php esc_html_e( 'Три кнопки под контентом записи', 'order-share-odinokov' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Показывать кнопку «Сохранить PDF»', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[print_button]" value="1" <?php checked( $s['print_button'], 1 ); ?>> <?php esc_html_e( 'Если выключено — кнопка PDF не выводится', 'order-share-odinokov' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Отключить Add to Cart', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[disable_add_to_cart]" value="1" <?php checked( $s['disable_add_to_cart'], 1 ); ?>> <?php esc_html_e( 'Отключает кнопки WC «В корзину» и блокирует соответствующий AJAX', 'order-share-odinokov' ); ?></label></td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Иконки и подписи', 'order-share-odinokov' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Комплект иконок', 'order-share-odinokov' ); ?></th>
                    <td>
                        <?php
                        $icon_sets = oso_get_icon_sets();
                        if ( empty( $icon_sets ) ) {
                            echo '<p>' . esc_html__( 'Комплекты иконок не найдены в папке icons.', 'order-share-odinokov' ) . '</p>';
                        } else {
                            foreach ( $icon_sets as $set ) {
                                $set_id   = 'oso-icon-set-' . sanitize_title( $set );
                                $preview  = OSO_URL . 'icons/' . rawurlencode( $set ) . '/share.png';
                                ?>
                                <label style="display:inline-block;margin-right:20px;margin-bottom:12px;text-align:center;vertical-align:top;">
                                    <div style="width:60px;height:60px;border:2px solid <?php echo ( $s['icon_set'] === $set ) ? '#2271b1' : '#ddd'; ?>;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:4px;background:#f9f9f9;">
                                        <img src="<?php echo esc_url( $preview ); ?>" alt="<?php echo esc_attr( $set ); ?>" style="max-width:48px;max-height:48px;object-fit:contain;">
                                    </div>
                                    <input type="radio" name="oso_settings[icon_set]" value="<?php echo esc_attr( $set ); ?>" id="<?php echo esc_attr( $set_id ); ?>" <?php checked( $s['icon_set'], $set ); ?>>
                                    <br>
                                    <label for="<?php echo esc_attr( $set_id ); ?>" style="font-size:12px;cursor:pointer;"><?php echo esc_html( $set ); ?></label>
                                </label>
                                <?php
                            }
                        }
                        ?>
                        <p class="description"><?php esc_html_e( 'Выберите комплект иконок для кнопок. PNG-иконки из выбранного комплекта будут использоваться, если не задана своя иконка через загрузку.', 'order-share-odinokov' ); ?></p>
                    </td>
                </tr>
            </table>
            <table class="form-table" role="presentation">
                <tr><th colspan="2"><strong><?php esc_html_e( 'Кнопка «Отправить»', 'order-share-odinokov' ); ?></strong></th></tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Текст', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text" name="oso_settings[share_text]" value="<?php echo esc_attr( $s['share_text'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Иконка (Font Awesome)', 'order-share-odinokov' ); ?></th>
                    <td>
                        <input type="text" class="regular-text" name="oso_settings[share_icon]" value="<?php echo esc_attr( $s['share_icon'] ); ?>">
                        <p class="description"><?php esc_html_e( 'Например: fa-solid fa-share-nodes (FA 6) или fa fa-share-alt (FA 4). По умолчанию используется Font Awesome из Porto.', 'order-share-odinokov' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Своя иконка (загрузить из медиа-библиотеки)', 'order-share-odinokov' ); ?></th>
                    <td>
                        <?php oso_icon_upload_field( 'custom_share_icon', $s['custom_share_icon'] ); ?>
                        <p class="description"><?php esc_html_e( 'Будет автоматически уменьшена до 18×18 px. Если указано — используется вместо Font Awesome.', 'order-share-odinokov' ); ?></p>
                    </td>
                </tr>

                <tr><th colspan="2"><strong><?php esc_html_e( 'Кнопка «Сохранить PDF»', 'order-share-odinokov' ); ?></strong></th></tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Текст', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text" name="oso_settings[pdf_text]" value="<?php echo esc_attr( $s['pdf_text'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Иконка (Font Awesome)', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text" name="oso_settings[pdf_icon]" value="<?php echo esc_attr( $s['pdf_icon'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Своя иконка (загрузить из медиа-библиотеки)', 'order-share-odinokov' ); ?></th>
                    <td>
                        <?php oso_icon_upload_field( 'custom_pdf_icon', $s['custom_pdf_icon'] ); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Имя PDF-файла', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text" name="oso_settings[pdf_filename]" value="<?php echo esc_attr( $s['pdf_filename'] ); ?>"></td>
                </tr>

                <tr><th colspan="2"><strong><?php esc_html_e( 'Кнопка «Оставить заявку»', 'order-share-odinokov' ); ?></strong></th></tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Текст', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text" name="oso_settings[order_text]" value="<?php echo esc_attr( $s['order_text'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Иконка (Font Awesome)', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text" name="oso_settings[order_icon]" value="<?php echo esc_attr( $s['order_icon'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Своя иконка (загрузить из медиа-библиотеки)', 'order-share-odinokov' ); ?></th>
                    <td>
                        <?php oso_icon_upload_field( 'custom_order_icon', $s['custom_order_icon'] ); ?>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Стиль (применяется ко всем трём кнопкам)', 'order-share-odinokov' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Шрифт', 'order-share-odinokov' ); ?></th>
                    <td>
                        <select name="oso_settings[font_family]">
                            <?php foreach ( oso_get_font_choices() as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['font_family'], $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Дополнительные Google-шрифты (через |)', 'order-share-odinokov' ); ?></th>
                    <td>
                        <input type="text" class="regular-text" name="oso_settings[custom_fonts]" value="<?php echo esc_attr( $s['custom_fonts'] ); ?>">
                        <p class="description"><?php esc_html_e( 'Например: Roboto|Montserrat|Open+Sans. Пусто — без подключения.', 'order-share-odinokov' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Размер шрифта (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="8" max="60" name="oso_settings[font_size]" value="<?php echo esc_attr( $s['font_size'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Размер иконки на товаре (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="12" max="80" name="oso_settings[icon_size_product]" value="<?php echo esc_attr( $s['icon_size_product'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Размер иконки в категории (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="12" max="80" name="oso_settings[icon_size_category]" value="<?php echo esc_attr( $s['icon_size_category'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Цвет фона', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text oso-color" name="oso_settings[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" placeholder="#ffffff"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Цвет текста', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text oso-color" name="oso_settings[text_color]" value="<?php echo esc_attr( $s['text_color'] ); ?>" placeholder="#222222"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Цвет границы', 'order-share-odinokov' ); ?></th>
                    <td><input type="text" class="regular-text oso-color" name="oso_settings[border_color]" value="<?php echo esc_attr( $s['border_color'] ); ?>" placeholder="#222222"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Толщина границы (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="0" max="20" name="oso_settings[border_width]" value="<?php echo esc_attr( $s['border_width'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Закругление углов (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="0" max="100" name="oso_settings[border_radius]" value="<?php echo esc_attr( $s['border_radius'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Отступ сверху/снизу (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="0" max="60" name="oso_settings[padding_v]" value="<?php echo esc_attr( $s['padding_v'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Отступ слева/справа (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="0" max="60" name="oso_settings[padding_h]" value="<?php echo esc_attr( $s['padding_h'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Расстояние между кнопками (px)', 'order-share-odinokov' ); ?></th>
                    <td><input type="number" min="0" max="60" name="oso_settings[gap]" value="<?php echo esc_attr( $s['gap'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Верхний регистр (UPPERCASE)', 'order-share-odinokov' ); ?></th>
                    <td><label><input type="checkbox" name="oso_settings[uppercase]" value="1" <?php checked( $s['uppercase'], 1 ); ?>> <?php esc_html_e( 'Текст кнопок в верхнем регистре', 'order-share-odinokov' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Жирность шрифта', 'order-share-odinokov' ); ?></th>
                    <td>
                        <select name="oso_settings[font_weight]">
                            <?php foreach ( array( 300 => '300 — Light', 400 => '400 — Regular', 500 => '500 — Medium', 600 => '600 — Semi Bold', 700 => '700 — Bold', 800 => '800 — Extra Bold' ) as $w => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $w ); ?>" <?php selected( $s['font_weight'], $w ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Поля PopUp-формы', 'order-share-odinokov' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Показывать поля', 'order-share-odinokov' ); ?></th>
                    <td>
                        <?php
                        $fields = array(
                            'field_inn'         => __( 'ИНН', 'order-share-odinokov' ),
                            'field_name'        => __( 'Имя / Обращение', 'order-share-odinokov' ),
                            'field_email'       => __( 'Email', 'order-share-odinokov' ),
                            'field_accessories' => __( 'Комплектующие', 'order-share-odinokov' ),
                            'field_files'       => __( 'Вложения (до 3 файлов)', 'order-share-odinokov' ),
                            'field_delivery'    => __( 'Доставка', 'order-share-odinokov' ),
                            'field_captcha'     => __( 'Капча', 'order-share-odinokov' ),
                        );
                        foreach ( $fields as $k => $lbl ) : ?>
                            <label style="display:block;margin-bottom:4px;"><input type="checkbox" name="oso_settings[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( $s[ $k ], 1 ); ?>> <?php echo esc_html( $lbl ); ?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Email для получения заявок', 'order-share-odinokov' ); ?></th>
                    <td>
                        <input type="email" class="regular-text" name="oso_email_to" value="<?php echo esc_attr( $email ); ?>">
                        <p class="description"><?php esc_html_e( 'Сюда будут приходить письма с заявками.', 'order-share-odinokov' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
            <?php wp_nonce_field( 'oso_force_check', 'oso_force_check_nonce' ); ?>
            <input type="hidden" name="action" value="oso_force_check">
            <?php submit_button( __( 'Проверить обновления', 'order-share-odinokov' ), 'secondary' ); ?>
        </form>
    </div>
    <style>
        .oso-admin .form-table th{width:280px;}
        .oso-admin .form-table input.regular-text{max-width:380px;}
    </style>
    <?php
}

add_filter( 'plugin_action_links_' . plugin_basename( OSO_FILE ), 'oso_action_links' );
function oso_action_links( $links ) {
    $url  = admin_url( 'options-general.php?page=order-share-odinokov' );
    $link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Настроить', 'order-share-odinokov' ) . '</a>';
    array_unshift( $links, $link );
    return $links;
}
