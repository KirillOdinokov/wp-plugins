<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAR_Cron {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'oar_cron_event', array( $this, 'handle' ) );
        add_action( 'oar_run_now', array( $this, 'handle' ) );
        add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );
    }

    public function register_schedules( $schedules ) {
        if ( ! isset( $schedules['oar_weekly'] ) ) {
            $schedules['oar_weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Раз в неделю', 'odinokov-auto-refresh' ),
            );
        }
        return $schedules;
    }

    public function handle() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            @wp_raise_memory_limit( 'admin' );
        }
        @ignore_user_abort( true );
        if ( ! ini_get( 'safe_mode' ) ) {
            @set_time_limit( 0 );
        }
        OAR_Processor::run();
    }
}
