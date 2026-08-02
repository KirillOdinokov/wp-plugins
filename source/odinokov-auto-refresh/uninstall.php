<?php
/**
 * Удаление плагина: очистка опций и cron.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'oar_settings' );
delete_option( 'oar_state' );

$timestamp = wp_next_scheduled( 'oar_cron_event' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'oar_cron_event' );
}
wp_clear_scheduled_hook( 'oar_cron_event' );

global $wpdb;
$wpdb->query(
    $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '%_transient_oar_%' )
);
$wpdb->query(
    $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '%_transient_timeout_oar_%' )
);
