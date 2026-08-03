<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAR_Admin {

    private static $instance = null;
    const CAP  = 'manage_options';
    const SLUG = 'odinokov-auto-refresh';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_oar_run_now', array( $this, 'handle_run_now' ) );
        add_action( 'admin_post_oar_reset_state', array( $this, 'handle_reset_state' ) );
        add_action( 'admin_post_oar_force_check', array( $this, 'force_check' ) );
    }

    public function menu() {
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
                self::CAP,
                'odinokov-plugins',
                array( $this, 'dashboard' ),
                'dashicons-admin-settings',
                30
            );
        }

        add_submenu_page(
            'odinokov-plugins',
            __( 'Odinokov Auto Refresh', 'odinokov-auto-refresh' ),
            __( 'Auto Refresh', 'odinokov-auto-refresh' ),
            self::CAP,
            self::SLUG,
            array( $this, 'render' )
        );
    }

    public function dashboard() {
        ?>
        <div class="wrap">
            <h1>Плагины Одиноков</h1>
            <p>Список установленных плагинов от Одиноков для управления.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px;">
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
                    <h3 style="margin-top:0;">Odinokov Auto Refresh</h3>
                    <p>Автоматически обновляет дату last modified у записей, страниц, товаров WooCommerce.</p>
                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>" class="button">Настроить</a></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'oar_group', OAR_OPTION_KEY, array(
            'sanitize_callback' => array( $this, 'sanitize' ),
        ) );
    }

    public function sanitize( $input ) {
        $out = OAR_Processor::get_settings();
        $out['enabled']          = ! empty( $input['enabled'] ) ? 1 : 0;
        $out['interval_days']    = max( 1, (int) ( $input['interval_days'] ?? 14 ) );
        $out['batch_size']       = max( 1, min( 500, (int) ( $input['batch_size'] ?? 30 ) ) );
        $out['skip_recent_days'] = max( 0, (int) ( $input['skip_recent_days'] ?? 0 ) );
        $out['jitter_minutes']   = max( 0, (int) ( $input['jitter_minutes'] ?? 0 ) );
        $out['min_gap_hours']    = max( 0, (int) ( $input['min_gap_hours'] ?? 12 ) );

        $allowed_pts = array( 'post', 'page', 'product', 'attachment' );
        $out['post_types'] = array_values( array_intersect(
            (array) ( $input['post_types'] ?? array() ),
            $allowed_pts
        ) );

        $allowed_tx = array( 'category', 'post_tag', 'product_cat', 'product_tag' );
        $out['taxonomies'] = array_values( array_intersect(
            (array) ( $input['taxonomies'] ?? array() ),
            $allowed_tx
        ) );

        OAR_Processor::reset_state();
        return $out;
    }

    public function handle_run_now() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( 'forbidden' );
        }
        check_admin_referer( 'oar_run_now' );
        OAR_Processor::reset_state();
        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            @wp_raise_memory_limit( 'admin' );
        }
        @ignore_user_abort( true );
        @set_time_limit( 0 );
        OAR_Processor::run();
        wp_safe_redirect( add_query_arg( 'oar_msg', 'ran', admin_url( 'admin.php?page=' . self::SLUG ) ) );
        exit;
    }

    public function force_check() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( 'Access denied.' );
        }
        check_admin_referer( 'oar_force_check', 'oar_force_check_nonce' );
        delete_transient( 'oar_release_' . md5( 'https://raw.githubusercontent.com/KirillOdinokov/wp-plugins/main/updates/odinokov-auto-refresh.json' ) );
        set_site_transient( 'update_plugins', null );
        wp_safe_redirect( admin_url( 'plugins.php?oar_force_check_done=1' ) );
        exit;
    }

    public function handle_reset_state() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( 'forbidden' );
        }
        check_admin_referer( 'oar_reset_state' );
        OAR_Processor::reset_state();
        wp_safe_redirect( add_query_arg( 'oar_msg', 'reset', admin_url( 'admin.php?page=' . self::SLUG ) ) );
        exit;
    }

    public function render() {
        if ( ! current_user_can( self::CAP ) ) {
            return;
        }
        $settings = OAR_Processor::get_settings();
        $state    = OAR_Processor::get_state();

        $available_pts = array();
        if ( post_type_exists( 'post' ) )       $available_pts[] = 'post';
        if ( post_type_exists( 'page' ) )       $available_pts[] = 'page';
        if ( post_type_exists( 'product' ) )    $available_pts[] = 'product';
        if ( post_type_exists( 'attachment' ) ) $available_pts[] = 'attachment';

        $available_tx = array();
        if ( taxonomy_exists( 'category' ) )    $available_tx[] = 'category';
        if ( taxonomy_exists( 'post_tag' ) )    $available_tx[] = 'post_tag';
        if ( taxonomy_exists( 'product_cat' ) ) $available_tx[] = 'product_cat';
        if ( taxonomy_exists( 'product_tag' ) ) $available_tx[] = 'product_tag';

        $msg = isset( $_GET['oar_msg'] ) ? sanitize_key( $_GET['oar_msg'] ) : '';
        ?>
        <div class="wrap">
            <h1>Odinokov Auto Refresh</h1>
            <?php if ( 'ran' === $msg ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Прогон выполнен. Курсор сохранён.', 'odinokov-auto-refresh' ); ?></p></div>
            <?php elseif ( 'reset' === $msg ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Состояние сброшено.', 'odinokov-auto-refresh' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'oar_group' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Включить', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
                                <?php esc_html_e( 'Автоматически обновлять дату modified', 'odinokov-auto-refresh' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Интервал (дней)', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <input type="number" min="1" max="365" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[interval_days]" value="<?php echo esc_attr( $settings['interval_days'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Обновлять записи, не трогавшиеся дольше этого срока.', 'odinokov-auto-refresh' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Размер батча', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <input type="number" min="1" max="500" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Сколько объектов обрабатывать за один запуск.', 'odinokov-auto-refresh' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Пропускать недавние (дней)', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <input type="number" min="0" max="365" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[skip_recent_days]" value="<?php echo esc_attr( $settings['skip_recent_days'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Не трогать записи, изменённые за последние N дней.', 'odinokov-auto-refresh' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Типы записей', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <?php foreach ( $available_pts as $pt ) : ?>
                                <label style="margin-right:14px;">
                                    <input type="checkbox" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[post_types][]" value="<?php echo esc_attr( $pt ); ?>" <?php checked( in_array( $pt, (array) $settings['post_types'], true ), true ); ?>>
                                    <?php echo esc_html( $pt ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Таксономии', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <?php foreach ( $available_tx as $tx ) : ?>
                                <label style="margin-right:14px;">
                                    <input type="checkbox" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[taxonomies][]" value="<?php echo esc_attr( $tx ); ?>" <?php checked( in_array( $tx, (array) $settings['taxonomies'], true ), true ); ?>>
                                    <?php echo esc_html( $tx ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Джиттер (минут)', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <input type="number" min="0" max="1440" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[jitter_minutes]" value="<?php echo esc_attr( $settings['jitter_minutes'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Случайный разброс новой даты в пределах ±N минут.', 'odinokov-auto-refresh' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Минимальный интервал (часов)', 'odinokov-auto-refresh' ); ?></th>
                        <td>
                            <input type="number" min="0" max="168" name="<?php echo esc_attr( OAR_OPTION_KEY ); ?>[min_gap_hours]" value="<?php echo esc_attr( $settings['min_gap_hours'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Между прогонами должно пройти не меньше N часов.', 'odinokov-auto-refresh' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Состояние', 'odinokov-auto-refresh' ); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: %s datetime */
                    esc_html__( 'Последний запуск: %s', 'odinokov-auto-refresh' ),
                    $state['last_run'] ? esc_html( gmdate( 'Y-m-d H:i:s', $state['last_run'] ) . ' UTC' ) : '—'
                );
                ?><br>
                <?php
                printf(
                    /* translators: %s datetime */
                    esc_html__( 'Последнее завершение полного круга: %s', 'odinokov-auto-refresh' ),
                    $state['last_finish'] ? esc_html( gmdate( 'Y-m-d H:i:s', $state['last_finish'] ) . ' UTC' ) : '—'
                );
                ?>
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
                <input type="hidden" name="action" value="oar_run_now">
                <?php wp_nonce_field( 'oar_run_now' ); ?>
                <?php submit_button( __( 'Запустить прогон сейчас', 'odinokov-auto-refresh' ), 'secondary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                <input type="hidden" name="action" value="oar_reset_state">
                <?php wp_nonce_field( 'oar_reset_state' ); ?>
                <?php submit_button( __( 'Сбросить курсор', 'odinokov-auto-refresh' ), 'delete', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
                <?php wp_nonce_field( 'oar_force_check', 'oar_force_check_nonce' ); ?>
                <input type="hidden" name="action" value="oar_force_check">
                <?php submit_button( __( 'Проверить обновления', 'odinokov-auto-refresh' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }
}
