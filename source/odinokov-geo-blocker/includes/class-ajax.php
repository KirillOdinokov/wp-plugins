<?php
class WP_Geo_Blocker_Ajax {

    public static function init() {
        add_action( 'wp_ajax_odgk_request_access', array( __CLASS__, 'handle_request_access' ) );
        add_action( 'wp_ajax_nopriv_odgk_request_access', array( __CLASS__, 'handle_request_access' ) );
    }

    public static function handle_request_access() {
        $has_nonce = isset( $_POST['nonce'] ) && $_POST['nonce'];
        if ( $has_nonce ) {
            if ( ! wp_verify_nonce( $_POST['nonce'], 'odgk_request_access' ) ) {
                wp_send_json_error( array( 'message' => 'Security error' ) );
            }
        } else {
            $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
            $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
            if ( ! $referer || strpos( $referer, $host ) === false ) {
                wp_send_json_error( array( 'message' => 'Security error' ) );
            }
        }

        $ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) wp_send_json_error( array( 'message' => 'Invalid IP' ) );

        $to = get_option( 'odinokov_geo_notify_email', 'odinokov.k@yandex.ru' );
        $admin_url = admin_url( 'admin.php?page=odgk-exceptions&action=add&ip=' . urlencode( $ip ) );
        $subject = sprintf( '[%s] Access Request: %s', get_bloginfo( 'name' ), $ip );
        $message = "IP: $ip\nSite: " . home_url() . "\nDate: " . current_time( 'mysql' ) . "\n\nAdd IP: $admin_url\n\nOr manually: Odinokov → Geo Blocker";

        $sent = wp_mail( $to, $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );
        if ( $sent ) {
            wp_send_json_success( array( 'message' => 'Request sent. Your IP will be reviewed.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Send error. Please try again later.' ) );
        }
    }
}
