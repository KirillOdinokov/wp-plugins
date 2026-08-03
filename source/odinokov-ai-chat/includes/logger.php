<?php
if (!defined('ABSPATH')) {
    exit;
}

function odinokov_ai_create_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'odinokov_ai_logs';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

    if (!$table_exists) {
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_msg TEXT NOT NULL,
            ai_reply TEXT NOT NULL,
            model VARCHAR(50) DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            status VARCHAR(10) DEFAULT 'ok',
            error_msg TEXT DEFAULT NULL,
            INDEX idx_created (created_at)
        ) {$charset}";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    } else {
        $has_status = $wpdb->get_var("SHOW COLUMNS FROM {$table_name} LIKE 'status'");
        if (!$has_status) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN status VARCHAR(10) DEFAULT 'ok' AFTER ip");
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN error_msg TEXT DEFAULT NULL AFTER status");
        }
    }

    if (!wp_next_scheduled('odinokov_ai_cleanup_logs')) {
        wp_schedule_event(time() + 3600, 'daily', 'odinokov_ai_cleanup_logs');
    }
}

function odinokov_ai_log_conversation($user_msg, $ai_reply, $model = '') {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'odinokov_ai_logs',
        array(
            'user_msg'  => mb_substr($user_msg, 0, 5000),
            'ai_reply'  => mb_substr($ai_reply, 0, 10000),
            'model'     => $model,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'status'    => 'ok',
        ),
        array('%s', '%s', '%s', '%s', '%s')
    );
}

function odinokov_ai_log_error($user_msg, $error_msg, $model = '') {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'odinokov_ai_logs',
        array(
            'user_msg'  => mb_substr($user_msg, 0, 5000),
            'ai_reply'  => '',
            'model'     => $model,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'status'    => 'error',
            'error_msg' => mb_substr($error_msg, 0, 2000),
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s')
    );
}

function odinokov_ai_cleanup_old_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'odinokov_ai_logs';
    $days = (int) get_option('odinokov_ai_log_retention_days', 30);
    if ($days < 1) $days = 30;
    $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
}
add_action('odinokov_ai_cleanup_logs', 'odinokov_ai_cleanup_old_logs');
