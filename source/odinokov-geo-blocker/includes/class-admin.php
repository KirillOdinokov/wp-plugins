<?php
class WP_Geo_Blocker_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_post_odgk_add_exception', array( __CLASS__, 'handle_add_exception' ) );
        add_action( 'admin_post_odgk_remove_exception', array( __CLASS__, 'handle_remove_exception' ) );
        add_action( 'admin_post_odgk_install_mu', array( __CLASS__, 'handle_install_mu' ) );
        add_action( 'admin_post_odgk_clear_log', array( __CLASS__, 'handle_clear_log' ) );
        add_action( 'admin_post_odgk_force_check', array( __CLASS__, 'force_check' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function register_settings() {
        register_setting( 'odgk_options', 'odinokov_geo_notify_email', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => 'odinokov.k@yandex.ru',
        ));
    }

    public static function add_menu() {
        global $menu;
        $exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'odinokov-plugins' === $item[2] ) { $exists = true; break; }
            }
        }
        if ( ! $exists ) {
            add_menu_page( 'Одиноков', 'Одиноков', 'manage_options', 'odinokov-plugins', array( __CLASS__, 'dashboard' ), 'dashicons-admin-settings', 30 );
        }
        add_submenu_page( 'odinokov-plugins', 'Geo Blocker', 'Geo Blocker', 'manage_options', 'odgk-exceptions', array( __CLASS__, 'render_page' ) );
        add_submenu_page( 'odinokov-plugins', 'Geo Blocker — Log', 'Geo Log', 'manage_options', 'odgk-log', array( __CLASS__, 'render_log_page' ) );
    }

    public static function dashboard() {
        ?>
        <div class="wrap"><h1>Плагины Одиноков</h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                <h3 style="margin-top:0;">Odinokov Geo Blocker</h3>
                <p>Блокирует доступ с неразрешённых стран.</p>
            </div>
        </div></div>
        <?php
    }

    public static function render_page() {
        $exceptions = self::get_exceptions();
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        $ip = isset( $_GET['ip'] ) ? sanitize_text_field( wp_unslash( $_GET['ip'] ) ) : '';
        if ( $action === 'add' && $ip ) { self::add_exception( $ip ); $exceptions = self::get_exceptions(); }

        $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
        $mu_file = $mu_dir . '/odinokov-geo-blocker-mu.php';
        $mu_exists = file_exists( $mu_file );
        ?>
        <div class="wrap">
            <h1>Odinokov Geo Blocker</h1>
            <div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
                <h2>Разрешённые страны</h2>
                <p>Россия (RU), Беларусь (BY), США (US), Украина (UA), Казахстан (KZ), Узбекистан (UZ)</p>
            </div>

            <div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
                <h2>Настройки</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'odgk_options' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="odinokov_geo_notify_email">Email для уведомлений</label></th>
                            <td>
                                <input type="email" id="odinokov_geo_notify_email" name="odinokov_geo_notify_email" value="<?php echo esc_attr( get_option( 'odinokov_geo_notify_email', 'odinokov.k@yandex.ru' ) ); ?>" class="regular-text">
                                <p class="description">Сюда приходят запросы на разблокировку.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                    <?php wp_nonce_field( 'odgk_force_check', 'odgk_force_check_nonce' ); ?>
                    <input type="hidden" name="action" value="odgk_force_check">
                    <?php submit_button( __( 'Проверить обновления', 'odinokov-geo-blocker' ), 'secondary' ); ?>
                </form>
            </div>

            <div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
                <h2>Добавить IP в исключения</h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'odgk_add_exception', 'odgk_nonce' ); ?>
                    <input type="hidden" name="action" value="odgk_add_exception">
                    <table class="form-table">
                        <tr><th><label for="exception_ip">IP-адрес</label></th><td><input type="text" name="exception_ip" id="exception_ip" class="regular-text" placeholder="192.168.1.1" required></td></tr>
                        <tr><th><label for="exception_note">Примечание</label></th><td><input type="text" name="exception_note" id="exception_note" class="regular-text" placeholder="Описание"></td></tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary">Добавить</button></p>
                </form>
            </div>

            <div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
                <h2>Список исключений</h2>
                <?php if ( empty( $exceptions ) ) : ?><p>Нет исключений.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>IP</th><th>Примечание</th><th>Добавлен</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ( $exceptions as $i => $e ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $e['ip'] ); ?></td>
                                    <td><?php echo esc_html( $e['note'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( $e['added'] ?? '' ); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                                            <?php wp_nonce_field( 'odgk_remove_exception', 'odgk_nonce' ); ?>
                                            <input type="hidden" name="action" value="odgk_remove_exception">
                                            <input type="hidden" name="exception_index" value="<?php echo (int) $i; ?>">
                                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Удалить?')">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width:800px;padding:20px;margin-top:20px;">
                <h2>Must-Use плагин</h2>
                <p>Статус: <?php echo $mu_exists ? '<span style="color:green">Установлен</span>' : '<span style="color:red">Не установлен</span>'; ?></p>
                <?php if ( ! $mu_exists ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'odgk_install_mu', 'odgk_nonce' ); ?>
                        <input type="hidden" name="action" value="odgk_install_mu">
                        <button type="submit" class="button button-primary">Установить MU-плагин</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function handle_add_exception() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'odgk_add_exception', 'odgk_nonce' );
        $ip = isset( $_POST['exception_ip'] ) ? sanitize_text_field( wp_unslash( $_POST['exception_ip'] ) ) : '';
        $note = isset( $_POST['exception_note'] ) ? sanitize_text_field( wp_unslash( $_POST['exception_note'] ) ) : '';
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) wp_die( 'Invalid IP' );
        self::add_exception( $ip, $note );
        wp_safe_redirect( admin_url( 'admin.php?page=odgk-exceptions&added=1' ) ); exit;
    }

    public static function handle_remove_exception() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'odgk_remove_exception', 'odgk_nonce' );
        $index = isset( $_POST['exception_index'] ) ? (int) $_POST['exception_index'] : -1;
        $exceptions = self::get_exceptions();
        if ( isset( $exceptions[ $index ] ) ) { array_splice( $exceptions, $index, 1 ); self::save_exceptions( $exceptions ); }
        wp_safe_redirect( admin_url( 'admin.php?page=odgk-exceptions&removed=1' ) ); exit;
    }

    public static function add_exception( $ip, $note = '' ) {
        $exceptions = self::get_exceptions();
        foreach ( $exceptions as $e ) { if ( $e['ip'] === $ip ) return; }
        $exceptions[] = array( 'ip' => $ip, 'note' => $note, 'added' => current_time( 'mysql' ) );
        self::save_exceptions( $exceptions );
    }

    private static function get_exceptions() {
        $file = ODGK_DATA_DIR . '/exceptions.json';
        if ( ! file_exists( $file ) ) return array();
        $c = @file_get_contents( $file ); if ( ! $c ) return array();
        $d = json_decode( $c, true );
        return is_array( $d ) ? $d : array();
    }

    public static function handle_install_mu() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'odgk_install_mu', 'odgk_nonce' );
        $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
        if ( ! is_dir( $mu_dir ) ) wp_mkdir_p( $mu_dir );
        copy( ODGK_DIR . 'odinokov-geo-blocker-mu.php', $mu_dir . '/odinokov-geo-blocker-mu.php' );
        wp_safe_redirect( admin_url( 'admin.php?page=odgk-exceptions&mu_installed=1' ) ); exit;
    }

    public static function force_check() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'odgk_force_check', 'odgk_force_check_nonce' );
        delete_transient( 'odgk_rel_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-geo-blocker.json' ) );
        set_site_transient( 'update_plugins', null );
        wp_safe_redirect( admin_url( 'plugins.php?odgk_force_check_done=1' ) );
        exit;
    }

    public static function handle_clear_log() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'odgk_clear_log', 'odgk_nonce' );
        WP_Geo_Blocker_Logger::clear();
        wp_safe_redirect( admin_url( 'admin.php?page=odgk-log&cleared=1' ) ); exit;
    }

    public static function render_log_page() {
        $entries = array_reverse( WP_Geo_Blocker_Logger::get_entries() );
        $names = array( 'RU' => 'Россия', 'BY' => 'Беларусь', 'US' => 'США', 'UA' => 'Украина', 'KZ' => 'Казахстан', 'UZ' => 'Узбекистан' );
        ?>
        <div class="wrap">
            <h1>Журнал блокировок</h1>
            <?php if ( empty( $entries ) ) : ?>
                <p>Журнал пуст.</p>
            <?php else : ?>
                <div style="margin:16px 0;">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                        <?php wp_nonce_field( 'odgk_clear_log', 'odgk_nonce' ); ?>
                        <input type="hidden" name="action" value="odgk_clear_log">
                        <button type="submit" class="button" onclick="return confirm('Очистить журнал?')">Очистить</button>
                    </form>
                    <span style="margin-left:12px;color:#666;">Записей: <?php echo count( $entries ); ?> / 2000</span>
                </div>
                <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
                    <thead><tr><th>Время</th><th>IP</th><th>Hostname</th><th>Страна</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ( $entries as $e ) : ?>
                            <tr>
                                <td><?php echo esc_html( $e['time'] ?? '' ); ?></td>
                                <td><code><?php echo esc_html( $e['ip'] ); ?></code></td>
                                <td><?php echo esc_html( $e['hostname'] ?? '' ); ?></td>
                                <td><?php $c = $e['country'] ?? ''; echo esc_html( isset( $names[ $c ] ) ? $names[ $c ] . " ($c)" : $c ); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                                        <?php wp_nonce_field( 'odgk_add_exception', 'odgk_nonce' ); ?>
                                        <input type="hidden" name="action" value="odgk_add_exception">
                                        <input type="hidden" name="exception_ip" value="<?php echo esc_attr( $e['ip'] ); ?>">
                                        <input type="hidden" name="exception_note" value="Из лога">
                                        <button type="submit" class="button button-small">В WhiteList</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function save_exceptions( $exceptions ) {
        @file_put_contents( ODGK_DATA_DIR . '/exceptions.json', json_encode( $exceptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }
}
