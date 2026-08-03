<?php
/**
 * Plugin Name: Odinokov Virus
 * Plugin URI:  https://github.com/KirillOdinokov/wp-plugins
 * Description: Комплексная защита от взлома: блокировка вредоносных User-Agent, путей шеллов, защита REST API, XML-RPC, wp-login от брутфорса, блокировка сканирования уязвимостей. Яндекс-боты не блокируются. + Автоочистка БД.
 * Version:     1.2.2
 * Author:      Odinokov
 * Author URI:  https://github.com/KirillOdinokov/wp-plugins
 * License:     GPL v2 or later
 * Text Domain: odinokov-virus
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ODINOKOV_VIRUS_VERSION', '1.2.1');
define('ODINOKOV_VIRUS_DIR', plugin_dir_path(__FILE__));
define('ODINOKOV_VIRUS_CRON_HOOK', 'odinokov_virus_weekly_cleanup');

require_once ODINOKOV_VIRUS_DIR . 'includes/class-odv-updater.php';

new ODV_Plugin_Updater(
    __FILE__,
    'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-virus.json',
    ODINOKOV_VIRUS_VERSION,
    array(
        'name'        => 'Odinokov Virus',
        'author'      => '<a href="https://github.com/KirillOdinokov/wp-plugins">Odinokov</a>',
        'author_uri'  => 'https://github.com/KirillOdinokov/wp-plugins',
        'description' => 'Комплексная защита от взлома: блокировка вредоносных User-Agent, путей шеллов, REST API, XML-RPC, wp-login брутфорса, сканирования уязвимостей. Автоочистка БД.',
    )
);

class Odinokov_Virus {

    private $blocked_ua_patterns = [
        'wp2shell', 'python-httpx', 'python-requests/',
        'zgrab', 'masscan', 'nmap', 'sqlmap', 'nikto',
        'acunetix', 'nessus', 'burpsuite', 'gobuster',
        'dirbuster', 'wfuzz', 'hydra', 'gobuster',
        'firefox/1', 'chrome/1.', 'opera/1.', 'msie 1.',
        'morfeus', 'fucking', 'scanner',
    ];

    private $shell_paths = [
        '/ALFA_DATA/', '/alfacgiapi/', '/wp-plain.php', '/mcp',
        '/wp-content/plugins/revslider/',
        '/wp-admin/css/colors/blue/blue.php',
        '/wp-includes/ID3/', '/wp-includes/SimplePie/',
        '/wp-includes/Text/', '/wp-includes/Requests/',
        '/wp-content/upgrade/',
        '/wp-config.php', '/.env', '/.git/', '/.svn/',
        '/vendor/', '/node_modules/', '/.DS_Store',
        '/?author=',
    ];

    private $yandex_ua_patterns = [
        'YandexBot', 'YandexImages', 'YandexMobileBot', 'YandexMetrika',
        'YandexWebmaster', 'YandexTurbo', 'YandexDirect', 'YandexAdNet',
        'YandexBlogs', 'YandexNews', 'YandexCatalog', 'YandexMobileScreenShotBot',
        'YandexSearchShop', 'YandexSitelinks', 'YandexVertis', 'YandexMarket',
        'YandexOntoDB', 'YandexPagechecker', 'YandexMedia', 'YandexVideo',
        'YandexFavicons', 'YandexForDomain', 'YandexVerticals',
    ];

    private $log_file;
    private $cleanup_log_file;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/odinokov-virus.log';
        $this->cleanup_log_file = $upload_dir['basedir'] . '/odinokov-cleanup.log';

        add_action('init', [$this, 'init'], 0);
        add_action('parse_request', [$this, 'check_shell_paths'], 0);
        add_action('rest_api_init', [$this, 'protect_rest_api'], 0);
        add_action('xmlrpc_enabled', '__return_false');
        add_filter('wp_handle_upload_prefilter', [$this, 'check_uploaded_file']);
        add_filter('upgrader_pre_install', [$this, 'log_plugin_install'], 10, 2);
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_odinokov_virus_clear_log', [$this, 'clear_log']);
        add_action('admin_post_odinokov_virus_run_cleanup', [$this, 'handle_manual_cleanup']);
        add_action('admin_post_odv_force_check', [$this, 'force_check']);
        add_action(ODINOKOV_VIRUS_CRON_HOOK, [$this, 'run_cleanup']);
        add_action('wp_login_failed', [$this, 'log_login_failure']);
        add_action('send_headers', [$this, 'add_security_headers']);
    }

    /* ========== Security Headers ========== */

    public function add_security_headers() {
        if ( is_admin() || headers_sent() ) return;
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    }

    /* ========== Blocking ========== */

    public function init() {
        if ($this->is_yandex_bot()) return;
        $ua = $this->get_user_agent();

        if (empty($ua) && !empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->block_and_log('empty_ua_post', 'Empty UA with POST');
        }

        foreach ($this->blocked_ua_patterns as $pattern) {
            if (stripos($ua, $pattern) !== false) {
                $this->block_and_log('blocked_ua', $pattern);
            }
        }
    }

    public function check_shell_paths() {
        if ($this->is_yandex_bot()) return;
        $uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';

        foreach ($this->shell_paths as $path) {
            if (stripos($uri, $path) !== false) {
                $this->block_and_log('shell_path', $path);
            }
        }

        // wp-login brute force throttling
        if (stripos($uri, '/wp-login.php') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip = $this->get_client_ip();
            $key = 'odinokov_login_attempts_' . md5($ip);
            $attempts = (int) get_transient($key);
            if ($attempts > 5) {
                $this->block_and_log('login_bruteforce', "Too many login attempts from $ip");
            }
            set_transient($key, $attempts + 1, 300);
        }
    }

    public function protect_rest_api() {
        if ($this->is_yandex_bot()) return;
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $is_batch = (stripos($uri, '/wp-json/batch/v1') !== false || stripos($uri, 'rest_route=/batch/v1') !== false);

        if (!$is_batch || $_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (is_user_logged_in() && current_user_can('edit_posts')) return;

        $ua = $this->get_user_agent();
        if (stripos($ua, 'wp2shell') !== false) $this->block_and_log('rest_batch', 'wp2shell via batch/v1');

        // Block unauthenticated REST users enumeration (but NOT /users/me)
        if (stripos($uri, '/wp-json/wp/v2/users') !== false && !is_user_logged_in()) {
            // Allow /users/me even for unauthenticated (returns 401 naturally)
            if (stripos($uri, '/wp-json/wp/v2/users/me') === false) {
                $this->block_and_log('rest_users', 'Unauthenticated users enumeration');
            }
        }

        $this->block_and_log('rest_batch', 'Unauthenticated POST to batch/v1');
    }

    /* ========== Upload / Install Logging ========== */

    public function check_uploaded_file($file) {
        if (!is_user_logged_in() || !current_user_can('upload_plugins')) return $file;
        $filename = isset($file['name']) ? $file['name'] : '';
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'zip') {
            $this->log_event('upload', 'ZIP: ' . $filename . ' by ' . wp_get_current_user()->user_login);
        }
        return $file;
    }

    public function log_plugin_install($response, $extra) {
        $user = wp_get_current_user()->user_login;
        $target = isset($extra['plugin']) ? 'Plugin: ' . $extra['plugin'] : (isset($extra['theme']) ? 'Theme: ' . $extra['theme'] : '');
        if ($target) $this->log_event('install', $target . ' by ' . $user);
        return $response;
    }

    public function log_login_failure($username) {
        $this->log_event('login_fail', 'Failed login for: ' . $username);
    }

    /* ========== Admin ========== */

    public function add_admin_page() {
        global $menu;
        $exists = false;
        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && 'odinokov-plugins' === $item[2]) { $exists = true; break; }
            }
        }
        if (!$exists) {
            add_menu_page('Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', [$this, 'dashboard'], 'dashicons-admin-settings', 30);
        }
        add_submenu_page('odinokov-plugins', 'Odinokov Virus', 'Virus', 'manage_options', 'odinokov-virus', [$this, 'render_admin_page']);
    }

    public function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov Virus</h3>
                <p>Комплексная защита от взлома и автоочистка БД.</p>
            </div>
        </div></div>
        <?php
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        $log_lines = [];
        if (file_exists($this->log_file)) {
            $log_content = @file_get_contents($this->log_file);
            if ($log_content) {
                $log_lines = array_reverse(array_filter(explode("\n", $log_content)));
                $log_lines = array_slice($log_lines, 0, 200);
            }
        }

        $last_cleanup = get_option('odinokov_virus_last_cleanup', null);
        $next_cron = wp_next_scheduled(ODINOKOV_VIRUS_CRON_HOOK);
        $next_display = $next_cron ? date_i18n('Y-m-d H:i:s', $next_cron) : 'Не запланировано';
        $cleanup_done = isset($_GET['cleanup_done']);
        ?>
        <div class="wrap">
            <h1>Odinokov Virus — Журнал защиты</h1>

            <h2>Активные защиты</h2>
            <ul style="list-style:disc;padding-left:20px;">
                <li>Блокировка вредоносных User-Agent (wp2shell, python-httpx, сканеры уязвимостей)</li>
                <li>Блокировка известных путей шеллов и сканирования</li>
                <li>Защита REST API batch/v1 и users от неавторизованных запросов</li>
                <li>Защита wp-login от брутфорса (макс 5 попыток за 5 мин)</li>
                <li>Отключение XML-RPC</li>
                <li>Логирование загрузок, установок, неудачных входов</li>
                <li>Белый список Яндекс-ботов</li>
                <li>Автоочистка БД (еженедельно)</li>
                <li>Заголовки безопасности (X-Content-Type-Options, X-Frame-Options, XSS-Protection)</li>
            </ul>

            <h2>Очистка БД</h2>
            <p>Следующая: <strong><?php echo esc_html($next_display); ?></strong></p>
            <?php if ($cleanup_done && $last_cleanup): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Очистка выполнена!</strong> ~<?php echo esc_html($last_cleanup['freed_mb']); ?> MB</p>
                    <ul style="list-style:disc;padding-left:20px;">
                        <?php foreach ($last_cleanup['results'] as $r): ?><li><?php echo esc_html($r); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:15px;">
                <input type="hidden" name="action" value="odinokov_virus_run_cleanup">
                <?php wp_nonce_field('odinokov_virus_run_cleanup', 'odinokov_virus_cleanup_nonce'); ?>
                <?php submit_button('Запустить очистку БД', 'primary', 'submit', false); ?>
            </form>

            <h2>Последние события (до 200)</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px;">
                <input type="hidden" name="action" value="odinokov_virus_clear_log">
                <?php wp_nonce_field('odinokov_virus_clear_log', 'odinokov_virus_nonce'); ?>
                <?php submit_button('Очистить лог', 'delete', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
                <?php wp_nonce_field('odv_force_check', 'odv_force_check_nonce'); ?>
                <input type="hidden" name="action" value="odv_force_check">
                <?php submit_button(__('Проверить обновления', 'odinokov-virus'), 'secondary'); ?>
            </form>
            <textarea readonly style="width:100%;height:400px;font-family:monospace;font-size:12px;"><?php
                echo empty($log_lines) ? 'Лог пуст.' : esc_textarea(implode("\n", $log_lines));
            ?></textarea>
        </div>
        <?php
    }

    public function force_check() {
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        check_admin_referer('odv_force_check', 'odv_force_check_nonce');
        delete_transient('odv_rel_' . md5('https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-virus.json'));
        wp_safe_redirect(add_query_arg('odv_force_check_done', '1', admin_url('admin.php?page=odinokov-virus')));
        exit;
    }

    public function clear_log() {
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        check_admin_referer('odinokov_virus_clear_log', 'odinokov_virus_nonce');
        if (file_exists($this->log_file)) @unlink($this->log_file);
        wp_redirect(admin_url('admin.php?page=odinokov-virus'));
        exit;
    }

    public function handle_manual_cleanup() {
        if (!current_user_can('manage_options')) wp_die('Access denied.');
        check_admin_referer('odinokov_virus_run_cleanup', 'odinokov_virus_cleanup_nonce');
        $this->run_cleanup();
        wp_redirect(add_query_arg(['page' => 'odinokov-virus', 'cleanup_done' => '1'], admin_url('admin.php')));
        exit;
    }

    /* ========== Helpers ========== */

    private function is_yandex_bot() {
        $ua = $this->get_user_agent();
        foreach ($this->yandex_ua_patterns as $p) { if (stripos($ua, $p) !== false) return true; }
        return false;
    }

    private function block_and_log($reason, $detail) {
        $this->log_event($reason, $detail . ' | IP: ' . $this->get_client_ip() . ' | UA: ' . $this->get_user_agent() . ' | URL: ' . ($_SERVER['REQUEST_URI'] ?? ''));
        status_header(403);
        header('Content-Type: text/plain; charset=utf-8');
        die('Access denied.');
    }

    private function log_event($reason, $detail) {
        $line = sprintf("[%s] [%s] [IP: %s] %s", date('Y-m-d H:i:s'), $reason, $this->get_client_ip(), $detail);
        @file_put_contents($this->log_file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    private function get_user_agent() { return isset($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : ''; }

    private function get_client_ip() {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /* ========== DB Cleanup ========== */

    public function run_cleanup() {
        global $wpdb;
        $start = microtime(true); $results = []; $freed = 0;
        $this->cleanup_log('=== Cleanup started ===');

        // Expired transients
        $d = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", '%_transient_timeout_%', time()));
        $results[] = "Expired transient timeouts: $d"; $this->cleanup_log("Expired transient timeouts: $d");

        $d = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s", '%_transient_%', '%_transient_timeout_%'));
        $results[] = "Orphaned transients: $d"; $this->cleanup_log("Orphaned transients: $d");

        // Orphaned meta
        foreach (['postmeta' => 'post_id', 'term_relationships' => 'object_id', 'commentmeta' => 'comment_id', 'usermeta' => 'user_id'] as $table => $col) {
            $pk = $table === 'commentmeta' ? 'comment_ID' : 'ID';
            $main = $table === 'term_relationships' ? $wpdb->posts : $wpdb->prefix . str_replace(['meta', '_relationships'], '', $table);
            if ($table === 'term_relationships') $main = $wpdb->posts;
            elseif ($table === 'postmeta') $main = $wpdb->posts;
            elseif ($table === 'commentmeta') $main = $wpdb->comments;
            elseif ($table === 'usermeta') $main = $wpdb->users;
            $d = $wpdb->query("DELETE pm FROM {$wpdb->$table} pm LEFT JOIN $main p ON pm.$col = p.$pk WHERE p.$pk IS NULL");
            $results[] = "Orphaned $table: $d"; $this->cleanup_log("Orphaned $table: $d");
        }

        // Revisions
        $d = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
        $results[] = "Revisions: $d"; $this->cleanup_log("Revisions: $d");

        // Auto-drafts older than 7 days
        $d = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_modified < %s", date('Y-m-d H:i:s', strtotime('-7 days'))));
        $results[] = "Old auto-drafts: $d"; $this->cleanup_log("Old auto-drafts: $d");

        // Spam/trash comments
        $d = $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')");
        $results[] = "Spam/trash comments: $d"; $this->cleanup_log("Spam/trash comments: $d");

        // WC sessions
        $wc = $wpdb->prefix . 'woocommerce_sessions';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wc)) === $wc) {
            $d = $wpdb->query($wpdb->prepare("DELETE FROM `$wc` WHERE session_expiry < %d", time()));
            $results[] = "Expired WC sessions: $d"; $this->cleanup_log("Expired WC sessions: $d");
        }

        // Action Scheduler
        $as = $wpdb->prefix . 'actionscheduler_actions';
        $al = $wpdb->prefix . 'actionscheduler_logs';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $as)) === $as) {
            $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
            $d = $wpdb->query($wpdb->prepare("DELETE FROM `$al` WHERE action_id IN (SELECT action_id FROM `$as` WHERE status IN ('complete', 'failed', 'canceled') AND scheduled_date_gmt < %s)", $cutoff));
            $results[] = "Old AS logs: $d"; $this->cleanup_log("Old AS logs: $d");
            $d = $wpdb->query($wpdb->prepare("DELETE FROM `$as` WHERE status IN ('complete', 'failed', 'canceled') AND scheduled_date_gmt < %s", $cutoff));
            $results[] = "Old AS actions: $d"; $this->cleanup_log("Old AS actions: $d");
        }

        // OPTIMIZE fragmented tables
        $frags = $wpdb->get_results("SELECT table_name, data_free FROM information_schema.tables WHERE table_schema = DATABASE() AND data_free > 1048576 ORDER BY data_free DESC LIMIT 20", ARRAY_A);
        foreach ($frags as $tbl) {
            $name = esc_sql($tbl['table_name']);
            $mb = round($tbl['data_free'] / 1048576, 2);
            $wpdb->query("OPTIMIZE TABLE `$name`");
            $freed += $mb;
            $results[] = "Rebuild $name: ~$mb MB"; $this->cleanup_log("Rebuild $name: ~$mb MB");
        }

        $elapsed = round(microtime(true) - $start, 2);
        $results[] = "Total: ~$freed MB | {$elapsed}s";
        $this->cleanup_log("=== Cleanup finished: ~$freed MB in {$elapsed}s ===");
        update_option('odinokov_virus_last_cleanup', ['time' => current_time('mysql'), 'results' => $results, 'freed_mb' => $freed]);
        return $results;
    }

    private function cleanup_log($msg) {
        @file_put_contents($this->cleanup_log_file, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND | LOCK_EX);
    }

    public static function activate() {
        if (!wp_next_scheduled(ODINOKOV_VIRUS_CRON_HOOK)) wp_schedule_event(time(), 'weekly', ODINOKOV_VIRUS_CRON_HOOK);
    }

    public static function deactivate() {
        $ts = wp_next_scheduled(ODINOKOV_VIRUS_CRON_HOOK);
        if ($ts) wp_unschedule_event($ts, ODINOKOV_VIRUS_CRON_HOOK);
    }
}

register_activation_hook(__FILE__, ['Odinokov_Virus', 'activate']);
register_deactivation_hook(__FILE__, ['Odinokov_Virus', 'deactivate']);

new Odinokov_Virus();
