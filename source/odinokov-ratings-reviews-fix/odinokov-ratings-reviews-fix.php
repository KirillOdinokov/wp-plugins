<?php
/**
 * Plugin Name: Odinokov Ratings & Reviews Fix
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Математическая капча для формы отзыва WooCommerce + пересчёт рейтингов товаров по существующим отзывам.
 * Version:     1.0.0
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL v2 or later
 * Text Domain: odinokov-ratings-reviews-fix
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ORRF_VERSION', '1.0.0' );
define( 'ORRF_DIR', plugin_dir_path( __FILE__ ) );

require_once ORRF_DIR . 'includes/class-orrf-updater.php';

new ORRF_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-ratings-reviews-fix.json',
    ORRF_VERSION,
    array(
        'name'        => 'Odinokov Ratings & Reviews Fix',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Математическая капча для формы отзыва WooCommerce + пересчёт рейтингов товаров.',
    )
);

class Odinokov_Ratings_Reviews_Fix {

    public function __construct() {
        add_action( 'init', [ $this, 'start_session' ] );
        add_action( 'comment_form_logged_in_after', [ $this, 'captcha_field' ] );
        add_action( 'comment_form_after_fields', [ $this, 'captcha_field' ] );
        add_filter( 'preprocess_comment', [ $this, 'validate_captcha' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'inline_styles' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_post_orf_recalculate', [ $this, 'handle_recalculate' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
    }

    /* ========== Captcha ========== */

    public function start_session() {
        if ( ! session_id() && ! headers_sent() && ! is_admin() ) {
            @session_start();
        }
    }

    private function generate_captcha() {
        $a = rand( 1, 15 ); $b = rand( 1, 15 ); $op = rand( 0, 1 ) ? '+' : '-';
        if ( '-' === $op ) { $max = max( $a, $b ); $min = min( $a, $b ); $a = $max; $b = $min; }
        $answer = '+' === $op ? $a + $b : $a - $b;
        $_SESSION['orf_captcha'] = $answer;
        return "$a $op $b = ?";
    }

    public function captcha_field() {
        if ( ! is_product() ) return;
        $q = $this->generate_captcha();
        ?>
        <p class="comment-form-orf-captcha">
            <label for="orf_captcha"><?php esc_html_e( 'Решите пример:', 'odinokov-ratings-reviews-fix' ); ?> <span class="required">*</span></label>
            <span class="orf-captcha-q"><?php echo esc_html( $q ); ?></span>
            <input id="orf_captcha" name="orf_captcha" type="number" size="30" required />
        </p>
        <?php
    }

    public function validate_captcha( $commentdata ) {
        if ( ! is_product() || ( is_admin() && current_user_can( 'moderate_comments' ) ) ) return $commentdata;
        if ( ! isset( $_SESSION['orf_captcha'] ) ) wp_die( esc_html__( 'Ошибка капчи. Обновите страницу.', 'odinokov-ratings-reviews-fix' ) );
        $answer = isset( $_POST['orf_captcha'] ) ? (int) $_POST['orf_captcha'] : -1;
        $correct = (int) $_SESSION['orf_captcha'];
        unset( $_SESSION['orf_captcha'] );
        if ( $answer !== $correct ) wp_die( esc_html__( 'Неверный ответ. Вернитесь и попробуйте снова.', 'odinokov-ratings-reviews-fix' ) );
        return $commentdata;
    }

    public function inline_styles() {
        if ( ! is_product() ) return;
        echo '<style>.orf-captcha-q{display:inline-block;margin-right:8px;font-weight:700;font-size:16px;background:#f5f5f5;padding:4px 12px;border-radius:4px}#orf_captcha{width:80px!important;display:inline-block!important}</style>';
    }

    /* ========== Admin ========== */

    public function add_admin_menu() {
        global $menu;
        $e = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $i ) { if ( isset( $i[2] ) && 'odinokov-plugins' === $i[2] ) { $e = true; break; } }
        }
        if ( ! $e ) add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [ $this, 'dashboard' ], 'dashicons-admin-settings', 30 );
        add_submenu_page( 'odinokov-plugins', 'Ratings & Reviews', 'Ratings Fix', 'manage_options', 'odinokov-ratings-fix', [ $this, 'render_page' ] );
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Ratings & Reviews Fix</h3>
                <p>Капча для отзывов + пересчёт рейтингов WooCommerce.</p>
            </div>
        </div></div>
        <?php
    }

    public function admin_assets( $hook ) {
        if ( false === strpos( $hook, 'odinokov-ratings-fix' ) ) return;
        wp_enqueue_style( 'orf-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', [], ORRF_VERSION );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $done = isset( $_GET['orf_done'] );
        $count = get_transient( 'orf_recalc_count' );
        ?>
        <div class="wrap">
            <h1>Odinokov Ratings & Reviews Fix</h1>
            <?php if ( $done && $count ) : ?>
                <div class="notice notice-success"><p>Рейтинги пересчитаны для <?php echo (int) $count; ?> товаров.</p></div>
                <?php delete_transient( 'orf_recalc_count' ); ?>
            <?php endif; ?>

            <div class="card" style="max-width:600px;padding:20px;margin-top:20px;">
                <h2>Пересчёт рейтингов</h2>
                <p>Пересчитывает рейтинг, количество отзывов и распределение оценок для всех товаров на основе существующих отзывов WooCommerce.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'orf_recalculate', 'orf_nonce' ); ?>
                    <input type="hidden" name="action" value="orf_recalculate">
                    <button type="submit" class="button button-primary">Пересчитать рейтинги</button>
                </form>
            </div>

            <div class="card" style="max-width:600px;padding:20px;margin-top:20px;">
                <h2>Капча для отзывов</h2>
                <p>Математическая капча добавляется в форму отзыва на странице товара. Работает автоматически.</p>
                <p style="color:green;">Активно</p>
            </div>
        </div>
        <?php
    }

    public function handle_recalculate() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'orf_recalculate', 'orf_nonce' );
        $count = $this->recalculate_ratings();
        set_transient( 'orf_recalc_count', $count, 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=odinokov-ratings-fix&orf_done=1' ) ); exit;
    }

    private function recalculate_ratings() {
        global $wpdb;
        $reviews = $wpdb->get_results( "SELECT comment_post_ID AS product_id, meta_value AS rating FROM {$wpdb->comments} c JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id WHERE c.comment_type = 'review' AND c.comment_approved = '1' AND cm.meta_key = 'rating'" );
        if ( empty( $reviews ) ) return 0;

        $by = [];
        foreach ( $reviews as $r ) {
            $pid = (int) $r->product_id; $rating = (int) $r->rating;
            if ( ! isset( $by[ $pid ] ) ) $by[ $pid ] = [ 'count' => 0, 'sum' => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
            $by[ $pid ]['count']++; $by[ $pid ]['sum'] += $rating; $by[ $pid ][ $rating ]++;
        }

        foreach ( $by as $pid => $d ) {
            update_post_meta( $pid, '_wc_review_count', $d['count'] );
            update_post_meta( $pid, '_wc_average_rating', round( $d['sum'] / $d['count'], 2 ) );
            update_post_meta( $pid, '_wc_rating_count', [ 1 => $d[1], 2 => $d[2], 3 => $d[3], 4 => $d[4], 5 => $d[5] ] );
            wp_update_comment_count_now( $pid );
            if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients( $pid );
        }
        return count( $by );
    }
}

new Odinokov_Ratings_Reviews_Fix();
