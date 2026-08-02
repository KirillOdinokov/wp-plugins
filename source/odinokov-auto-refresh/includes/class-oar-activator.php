<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAR_Activator {

    public static function activate() {
        $defaults = array(
            'interval_days'    => 14,
            'batch_size'       => 30,
            'post_types'       => array( 'post', 'page', 'product' ),
            'taxonomies'       => array( 'category', 'post_tag', 'product_cat', 'product_tag' ),
            'skip_recent_days' => 0,
            'enabled'          => 1,
            'jitter_minutes'   => 0,
            'min_gap_hours'    => 12,
        );

        $existing = get_option( OAR_OPTION_KEY );
        if ( ! is_array( $existing ) ) {
            add_option( OAR_OPTION_KEY, $defaults, '', 'yes' );
        } else {
            $merged = array_merge( $defaults, $existing );
            update_option( OAR_OPTION_KEY, $merged, 'yes' );
        }

        $state_existing = get_option( OAR_STATE_KEY );
        if ( ! is_array( $state_existing ) ) {
            add_option( OAR_STATE_KEY, array(
                'offset'      => 0,
                'last_run'    => 0,
                'last_finish' => 0,
                'cursor'      => array(),
            ), '', 'no' );
        }

        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'oar_cron_event' ) ) {
            wp_schedule_event( time() + 60, 'hourly', 'oar_cron_event' );
        }
    }

    public static function deactivate() {
        if ( function_exists( 'wp_next_scheduled' ) ) {
            $timestamp = wp_next_scheduled( 'oar_cron_event' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'oar_cron_event' );
            }
            wp_clear_scheduled_hook( 'oar_cron_event' );
        }
    }
}
