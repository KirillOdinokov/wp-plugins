<?php
class WP_Geo_Blocker_Logger {

    const MAX_ENTRIES = 2000;
    const LOG_FILE = 'block-log.json';

    public static function log( $ip, $country ) {
        $entries = self::read_log_raw( ODGK_DATA_DIR . '/' . self::LOG_FILE );
        $entries[] = array(
            'ip' => $ip, 'hostname' => self::resolve_hostname( $ip ),
            'country' => $country, 'time' => current_time( 'mysql' ),
        );
        if ( count( $entries ) > self::MAX_ENTRIES ) $entries = array_slice( $entries, -self::MAX_ENTRIES );
        file_put_contents( ODGK_DATA_DIR . '/' . self::LOG_FILE, json_encode( $entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }

    public static function log_mu( $ip, $country ) {
        $entries = self::read_log_raw( ODGK_DATA_DIR . '/' . self::LOG_FILE );
        $entries[] = array(
            'ip' => $ip, 'hostname' => self::resolve_hostname( $ip ),
            'country' => $country, 'time' => gmdate( 'Y-m-d H:i:s' ),
        );
        if ( count( $entries ) > self::MAX_ENTRIES ) $entries = array_slice( $entries, -self::MAX_ENTRIES );
        file_put_contents( ODGK_DATA_DIR . '/' . self::LOG_FILE, json_encode( $entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }

    public static function get_entries() {
        return self::read_log_raw( ODGK_DATA_DIR . '/' . self::LOG_FILE );
    }

    public static function clear() {
        $file = ODGK_DATA_DIR . '/' . self::LOG_FILE;
        if ( file_exists( $file ) ) unlink( $file );
    }

    private static function read_log_raw( $file ) {
        if ( ! file_exists( $file ) ) return array();
        $content = @file_get_contents( $file );
        if ( false === $content ) return array();
        $data = json_decode( $content, true );
        return is_array( $data ) ? $data : array();
    }

    private static function resolve_hostname( $ip ) {
        $hostname = @gethostbyaddr( $ip );
        return ( $hostname && $hostname !== $ip ) ? $hostname : '';
    }
}
