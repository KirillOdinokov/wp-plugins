<?php
/**
 * Plugin Name: Image Filler Odinokov
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Заполняет пустые thumbnails категорий изображениями товаров и пустые картинки товаров — thumbnail родительской категории. Без перегрузки сервера (батчи + AJAX + nonce).
 * Version:     1.0.2
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * Text Domain: image-filler-odinokov
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'IFO_VERSION', '1.0.2' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ifo-updater.php';

new IFO_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/image-filler-odinokov.json',
    IFO_VERSION,
    array(
        'name'        => 'Image Filler Odinokov',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Заполняет пустые thumbnails категорий изображениями товаров и пустые картинки товаров — thumbnail родительской категории.',
    )
);

class Image_Filler_Odinokov {

    const OPTION_KEY      = 'ifo_settings';
    const NONCE_ACTION    = 'ifo_run_action';
    const BATCH_SIZE_CAT  = 5;
    const BATCH_SIZE_PROD = 10;
    const MAX_PRODUCTS_PER_CAT = 50;
    const TRANSIENT_LIST_PREFIX = 'ifo_list_';
    const TRANSIENT_LIST_TTL    = HOUR_IN_SECONDS;

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ifo_run',       [ $this, 'ajax_run' ] );
        add_action( 'admin_post_ifo_force_check', [ $this, 'force_check' ] );
    }

    public function add_menu() {
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
            'Image Filler Odinokov',
            'Image Filler',
            'manage_options',
            'image-filler-odinokov',
            [ $this, 'render_page' ]
        );
    }

    public function dashboard() {
        ?>
        <div class="wrap">
            <h1>Плагины Одиноков</h1>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                    <h3 style="margin-top:0;">Image Filler Odinokov</h3>
                    <p>Заполняет пустые thumbnails категорий изображениями товаров.</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'ifo_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );
    }

    public function sanitize_settings( $input ) {
        $out = [];
        $out['batch_size_cat']  = max( 1, min( 50, (int) ( $input['batch_size_cat']  ?? self::BATCH_SIZE_CAT  ) ) );
        $out['batch_size_prod'] = max( 1, min( 100, (int) ( $input['batch_size_prod'] ?? self::BATCH_SIZE_PROD ) ) );
        $out['max_per_cat']     = max( 1, min( 500, (int) ( $input['max_per_cat']     ?? self::MAX_PRODUCTS_PER_CAT ) ) );
        $out['dry_run']         = ! empty( $input['dry_run'] ) ? 1 : 0;
        $out['exclude_ids']     = preg_replace( '/[^0-9,]/', '', (string) ( $input['exclude_ids'] ?? '' ) );
        return $out;
    }

    public function get_settings() {
        $defaults = [
            'batch_size_cat'  => self::BATCH_SIZE_CAT,
            'batch_size_prod' => self::BATCH_SIZE_PROD,
            'max_per_cat'     => self::MAX_PRODUCTS_PER_CAT,
            'dry_run'         => 0,
            'exclude_ids'     => '',
        ];
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) $saved = [];
        return array_merge( $defaults, $saved );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'image-filler-odinokov' ) ) return;
        wp_enqueue_style( 'ifo-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', [], IFO_VERSION );
        wp_enqueue_script( 'ifo-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', [ 'jquery' ], IFO_VERSION, true );
        wp_localize_script( 'ifo-admin', 'IFO', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
            'i18n'    => [
                'starting' => 'Запуск…', 'done' => 'Готово', 'stopped' => 'Остановлено',
                'error' => 'Ошибка', 'btnStart' => 'Запустить', 'btnStop' => 'Остановить',
                'processedCats' => 'Категории обработаны', 'processedProd' => 'Товары обработаны',
                'noItems' => 'Нечего обрабатывать',
            ],
        ] );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $s = $this->get_settings();
        ?>
        <div class="wrap ifo-wrap">
            <h1>Image Filler Odinokov</h1>
            <p>Заполняет пустые <strong>thumbnails категорий</strong> изображениями товаров и пустые <strong>картинки товаров</strong> — thumbnail родительской категории. Обработка идёт батчами через AJAX.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'ifo_settings_group' ); ?>
                <table class="form-table">
                    <tr><th>Батч категорий за шаг</th><td><input type="number" min="1" max="50" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[batch_size_cat]" value="<?php echo esc_attr( $s['batch_size_cat'] ); ?>"></td></tr>
                    <tr><th>Батч товаров за шаг</th><td><input type="number" min="1" max="100" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[batch_size_prod]" value="<?php echo esc_attr( $s['batch_size_prod'] ); ?>"></td></tr>
                    <tr><th>Товаров в категории</th><td><input type="number" min="1" max="500" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_per_cat]" value="<?php echo esc_attr( $s['max_per_cat'] ); ?>"></td></tr>
                    <tr><th>Dry-run</th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[dry_run]" value="1" <?php checked( $s['dry_run'], 1 ); ?>> Только отчёт</label></td></tr>
                    <tr><th>ID для исключения</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[exclude_ids]" value="<?php echo esc_attr( $s['exclude_ids'] ); ?>"></td></tr>
                </table>
                <?php submit_button( 'Сохранить настройки' ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                <?php wp_nonce_field( 'ifo_force_check', 'ifo_force_check_nonce' ); ?>
                <input type="hidden" name="action" value="ifo_force_check">
                <?php submit_button( __( 'Проверить обновления', 'image-filler-odinokov' ), 'secondary' ); ?>
            </form>
            <hr>
            <h2>Действия</h2>
            <p><button type="button" class="button button-primary" data-action="fill_cats">1. Заполнить thumbnails категорий</button> <span class="description">Берёт первое изображение из товаров категории.</span></p>
            <p><button type="button" class="button button-primary" data-action="fill_products">2. Заполнить картинки товаров</button> <span class="description">Ставит thumbnail родительской категории.</span></p>
            <div class="ifo-progress" hidden>
                <p><strong>Статус:</strong> <span class="ifo-status">—</span></p>
                <div class="ifo-bar"><div class="ifo-bar-fill"></div></div>
                <p class="ifo-log"></p>
                <p><button type="button" class="button ifo-stop">Остановить</button></p>
            </div>
            <h2>Предпросмотр</h2>
            <p>
                <button type="button" class="button" data-action="scan_cats">Категорий без thumbnail</button>
                <button type="button" class="button" data-action="scan_products">Товаров без изображения</button>
            </p>
            <div class="ifo-scan-result"></div>
        </div>
        <?php
    }

    public function force_check() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'ifo_force_check', 'ifo_force_check_nonce' );
        delete_transient( 'ifo_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/image-filler-odinokov.json' ) );
        set_site_transient( 'update_plugins', null );
        wp_safe_redirect( admin_url( 'plugins.php?ifo_force_check_done=1' ) );
        exit;
    }

    public function ajax_run() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'forbidden', 403 );

        $action = isset( $_POST['do'] ) ? sanitize_text_field( wp_unslash( $_POST['do'] ) ) : '';
        $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
        $stop   = ! empty( $_POST['stop'] );

        if ( $stop ) wp_send_json_success( [ 'done' => true, 'stopped' => true, 'processed' => 0 ] );

        $settings = $this->get_settings();
        $dry_run  = ! empty( $settings['dry_run'] );
        $stop_key = 'ifo_stop_' . get_current_user_id();
        if ( get_transient( $stop_key ) ) wp_send_json_success( [ 'done' => true, 'stopped' => true, 'processed' => 0 ] );

        @set_time_limit( 25 );

        switch ( $action ) {
            case 'fill_cats':    $result = $this->process_cats_batch( $offset, $settings, $dry_run ); break;
            case 'fill_products': $result = $this->process_products_batch( $offset, $settings, $dry_run ); break;
            case 'scan_cats':    $result = $this->scan_cats( $settings ); break;
            case 'scan_products': $result = $this->scan_products( $settings ); break;
            default: wp_send_json_error( 'unknown_action' );
        }
        wp_send_json_success( $result );
    }

    /* ========== Категории без thumbnail ========== */

    private function get_empty_cat_ids_full( $settings, $force_refresh = false ) {
        $cache_key = self::TRANSIENT_LIST_PREFIX . 'cats_' . get_current_user_id();
        if ( ! $force_refresh ) { $cached = get_transient( $cache_key ); if ( is_array( $cached ) ) return $cached; }
        global $wpdb;
        $exclude = $this->parse_exclude_ids( $settings['exclude_ids'] );
        $sql = "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'product_cat'";
        $params = [];
        if ( $exclude ) { $ph = implode( ',', array_fill( 0, count( $exclude ), '%d' ) ); $sql .= " AND t.term_id NOT IN ($ph)"; $params = array_merge( $params, $exclude ); }
        $sql .= " ORDER BY t.term_id ASC";
        $prepared = $params ? $wpdb->prepare( $sql, $params ) : $sql;
        $all_ids = $wpdb->get_col( $prepared );
        if ( empty( $all_ids ) ) { set_transient( $cache_key, [], self::TRANSIENT_LIST_TTL ); return []; }
        $all_ids = array_map( 'intval', $all_ids );
        $ph = implode( ',', array_fill( 0, count( $all_ids ), '%d' ) );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'thumbnail_id' AND term_id IN ($ph)", $all_ids ), OBJECT_K );
        $has_thumb = [];
        foreach ( $rows as $row ) { if ( (int) $row->meta_value > 0 ) $has_thumb[ (int) $row->term_id ] = true; }
        $empty = [];
        foreach ( $all_ids as $tid ) { if ( empty( $has_thumb[ $tid ] ) ) $empty[] = $tid; }
        set_transient( $cache_key, $empty, self::TRANSIENT_LIST_TTL );
        return $empty;
    }

    private function clear_cat_cache() { delete_transient( self::TRANSIENT_LIST_PREFIX . 'cats_' . get_current_user_id() ); }

    private function scan_cats( $settings ) { $ids = $this->get_empty_cat_ids_full( $settings, ! empty( $_POST['fresh'] ) ); return [ 'is_scan' => true, 'done' => true, 'total' => count( $ids ), 'message' => 'Категорий без thumbnail: ' . count( $ids ) ]; }

    private function process_cats_batch( $offset, $settings, $dry_run ) {
        $batch = max( 1, (int) $settings['batch_size_cat'] ); $maxp = max( 1, (int) $settings['max_per_cat'] );
        $first = ( 0 === $offset ); $all = $this->get_empty_cat_ids_full( $settings, ! empty( $_POST['fresh'] ) && $first );
        $ids = array_slice( $all, $offset, $batch );
        $processed = 0; $log = [];
        foreach ( $ids as $tid ) {
            $img_id = $this->find_product_image_for_category( $tid, $maxp );
            if ( $img_id ) { if ( ! $dry_run ) update_term_meta( $tid, 'thumbnail_id', (int) $img_id ); $log[] = "Категория #{$tid} → изображение #{$img_id}"; }
            else $log[] = "Категория #{$tid} → не найдено";
            $processed++;
        }
        $total = count( $all ); $next = $offset + $batch; $done = ( 0 === $processed ) || ( $next >= $total );
        if ( $done ) $this->clear_cat_cache();
        return [ 'is_scan' => false, 'done' => $done, 'processed' => $processed, 'next' => $next, 'total' => $total, 'log' => $log, 'dry_run' => $dry_run ];
    }

    private function find_product_image_for_category( $term_id, $max_products ) {
        $q = new WP_Query( [ 'post_type' => 'product', 'post_status' => [ 'publish', 'private' ], 'posts_per_page' => $max_products, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'DESC', 'tax_query' => [ [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term_id, 'include_children' => false ] ] ] );
        if ( ! $q->have_posts() ) { wp_reset_postdata(); return 0; }
        foreach ( $q->posts as $pid ) {
            $img_id = (int) get_post_thumbnail_id( $pid ); if ( $img_id ) break;
            $gallery = get_post_meta( $pid, '_product_image_gallery', true );
            if ( $gallery ) { $ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) ); if ( $ids ) { $img_id = (int) reset( $ids ); break; } }
        }
        wp_reset_postdata(); return $img_id;
    }

    /* ========== Товары без изображения ========== */

    private function scan_products( $settings ) { $total = $this->count_products_without_image( $settings ); return [ 'is_scan' => true, 'done' => true, 'total' => $total, 'message' => 'Товаров без изображения: ' . $total ]; }

    private function count_products_without_image( $settings ) {
        $args = [ 'post_type' => 'product', 'post_status' => [ 'publish', 'private' ], 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'meta_query' => [ [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ] ] ];
        $exclude = $this->parse_exclude_ids( $settings['exclude_ids'] );
        if ( $exclude ) $args['post__not_in'] = $exclude;
        $q = new WP_Query( $args ); wp_reset_postdata(); return (int) $q->found_posts;
    }

    private function get_products_without_image( $settings, $offset, $limit ) {
        $args = [ 'post_type' => 'product', 'post_status' => [ 'publish', 'private' ], 'posts_per_page' => $limit, 'offset' => $offset, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => [ [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ] ] ];
        $exclude = $this->parse_exclude_ids( $settings['exclude_ids'] );
        if ( $exclude ) $args['post__not_in'] = $exclude;
        $q = new WP_Query( $args ); $ids = $q->posts ? $q->posts : []; wp_reset_postdata(); return $ids;
    }

    private function process_products_batch( $offset, $settings, $dry_run ) {
        $batch = max( 1, (int) $settings['batch_size_prod'] );
        $ids = $this->get_products_without_image( $settings, $offset, $batch );
        $processed = 0; $log = [];
        foreach ( $ids as $pid ) {
            $thumb_id = $this->find_category_thumbnail_for_product( $pid );
            if ( $thumb_id ) { if ( ! $dry_run ) set_post_thumbnail( $pid, (int) $thumb_id ); $log[] = "Товар #{$pid} → изображение #{$thumb_id}"; }
            else $log[] = "Товар #{$pid} → не найдено";
            $processed++;
        }
        $total = $this->count_products_without_image( $settings ); $next = $offset + $batch; $done = ( 0 === $processed ) || ( $next >= $total );
        return [ 'is_scan' => false, 'done' => $done, 'processed' => $processed, 'next' => $next, 'total' => $total, 'log' => $log, 'dry_run' => $dry_run ];
    }

    private function find_category_thumbnail_for_product( $product_id ) {
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( empty( $terms ) || is_wp_error( $terms ) ) return 0;
        usort( $terms, function( $a, $b ) { return ( (int) $b->parent ) - ( (int) $a->parent ); } );
        foreach ( $terms as $term ) {
            $current = (int) $term->term_id; $visited = [];
            while ( $current && ! isset( $visited[ $current ] ) ) {
                $visited[ $current ] = true;
                $thumb = (int) get_term_meta( $current, 'thumbnail_id', true );
                if ( $thumb > 0 ) return $thumb;
                $t = get_term( $current, 'product_cat' );
                $current = ( $t && ! is_wp_error( $t ) ) ? (int) $t->parent : 0;
            }
        }
        return 0;
    }

    private function parse_exclude_ids( $str ) {
        if ( ! $str ) return [];
        return array_values( array_filter( array_map( 'absint', explode( ',', $str ) ) ) );
    }
}

new Image_Filler_Odinokov();
