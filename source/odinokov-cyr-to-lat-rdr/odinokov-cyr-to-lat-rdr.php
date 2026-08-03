<?php
/**
 * Plugin Name: Odinokov Cyr-to-Lat Redirect
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Автоматический 301 редирект с URL, содержащих кириллические символы, на латинские аналоги (после транслитерации плагином Cyr-To-Lat).
 * Version:     1.0.2
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OCLR_VERSION', '1.0.2' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-oclr-updater.php';

new OCLR_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-cyr-to-lat-rdr.json',
    OCLR_VERSION,
    array(
        'name'        => 'Odinokov Cyr-to-Lat Redirect',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Автоматический 301 редирект с URL, содержащих кириллические символы, на латинские аналоги.',
    )
);

add_action( 'admin_menu', 'oclr_admin_menu' );
add_action( 'admin_post_oclr_force_check', 'oclr_force_check' );
function oclr_admin_menu() {
    global $menu; $e = false;
    if ( is_array( $menu ) ) { foreach ( $menu as $i ) { if ( isset( $i[2] ) && 'odinokov-plugins' === $i[2] ) { $e = true; break; } } }
    if ( ! $e ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', 'oclr_dashboard', 'dashicons-admin-settings', 30 );
    add_submenu_page( 'odinokov-plugins', 'Cyr-to-Lat Redirect', 'Cyr-to-Lat RDR', 'manage_options', 'odinokov-cyr-to-lat-rdr', 'oclr_dashboard' );
}

function oclr_dashboard() {
    ?>
    <div class="wrap"><h1>Плагины Одиноков</h1>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <h3 style="margin-top:0;">Odinokov Cyr-to-Lat Redirect</h3>
            <p>Автоматический 301 редирект с кириллических URL на латиницу. Работает автоматически, настроек не требует.</p>
            <p style="color:green;">Активно</p>
        </div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
        <?php wp_nonce_field('oclr_force_check', 'oclr_force_check_nonce'); ?>
        <input type="hidden" name="action" value="oclr_force_check">
        <?php submit_button(__('Проверить обновления', 'odinokov-cyr-to-lat-rdr'), 'secondary'); ?>
    </form>
    </div>
    <?php
}

function oclr_force_check() {
    if (!current_user_can('manage_options')) wp_die('Access denied.');
    check_admin_referer('oclr_force_check', 'oclr_force_check_nonce');
    delete_transient('oclr_rel_' . md5('https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-cyr-to-lat-rdr.json'));
    set_site_transient('update_plugins', null);
    wp_safe_redirect(admin_url('plugins.php?oclr_force_check_done=1'));
    exit;
}

class OCLR_Redirect {

    private static $cyr_to_lat_map = [
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'YO','Ж'=>'ZH','З'=>'Z','И'=>'I','Й'=>'J','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'CZ','Ч'=>'CH','Ш'=>'SH','Щ'=>'SHH','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'YU','Я'=>'YA',
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'cz','ч'=>'ch','ш'=>'sh','щ'=>'shh','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'І'=>'I','і'=>'i','Ѣ'=>'YE','ѣ'=>'ye','Ѳ'=>'FH','ѳ'=>'fh','Ѵ'=>'YH','ѵ'=>'yh',
    ];

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'do_redirect' ], 1 );
    }

    public static function do_redirect() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
        $uri = $_SERVER['REQUEST_URI'];
        $decoded = urldecode( $uri );
        $parsed = wp_parse_url( $decoded );
        $path = isset( $parsed['path'] ) ? $parsed['path'] : '';
        if ( ! self::contains_cyrillic( $path ) ) return;
        $new_path = self::transliterate( $path );
        if ( $new_path === $path ) return;
        $new_url = home_url( $new_path );
        if ( ! empty( $parsed['query'] ) ) $new_url .= '?' . $parsed['query'];
        wp_redirect( $new_url, 301 ); exit;
    }

    private static function contains_cyrillic( $s ) { return (bool) preg_match( '/[\x{0400}-\x{04FF}]/u', $s ); }
    private static function transliterate( $s ) { return strtr( $s, self::$cyr_to_lat_map ); }
}

OCLR_Redirect::init();
