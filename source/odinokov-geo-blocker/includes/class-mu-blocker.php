<?php
class WP_Geo_MU_Blocker {

    public static function check() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) return;
        if ( php_sapi_name() === 'cli' ) return;
        if ( ! isset( $_SERVER['HTTP_HOST'] ) ) return;

        $ip = self::get_client_ip();
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? trim( $_SERVER['HTTP_USER_AGENT'] ) : '';

        // Load exceptions
        $exceptions_file = ODGK_DATA_DIR . '/exceptions.json';
        $exceptions = array();
        if ( file_exists( $exceptions_file ) ) {
            $content = @file_get_contents( $exceptions_file );
            if ( $content ) {
                $data = json_decode( $content, true );
                if ( is_array( $data ) ) $exceptions = $data;
            }
        }

        foreach ( $exceptions as $entry ) {
            if ( isset( $entry['ip'] ) && $entry['ip'] === $ip ) return;
        }

        // Whitelist verified Googlebot (cached reverse DNS)
        if ( self::is_verified_googlebot( $ua, $ip ) ) return;

        // Yandex whitelist
        $yandex_ua = array( 'YandexBot', 'YandexImages', 'YandexMobileBot', 'YandexMetrika', 'YandexWebmaster', 'YandexTurbo' );
        foreach ( $yandex_ua as $p ) { if ( stripos( $ua, $p ) !== false ) return; }

        $country = self::get_country( $ip );
        if ( null === $country ) return;

        $allowed = array( 'RU', 'BY', 'UA', 'KZ', 'UZ' );
        if ( ! in_array( $country, $allowed, true ) ) {
            if ( class_exists( 'WP_Geo_Blocker_Logger' ) ) {
                WP_Geo_Blocker_Logger::log_mu( $ip, $country );
            }
            status_header( 403 );
            header( 'Content-Type: text/html; charset=utf-8' );
            echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Доступ ограничен</title></head><body style="font-family:sans-serif;text-align:center;padding:40px;"><h1>Доступ ограничен / Access Restricted</h1><p>Сайт недоступен из вашей страны.</p></body></html>';
            die();
        }
    }

    private static function get_client_ip() {
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ) as $h ) {
            if ( ! empty( $_SERVER[ $h ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return $ip;
            }
        }
        return '127.0.0.1';
    }

    private static function is_verified_googlebot( $ua, $ip ) {
        if ( stripos( $ua, 'Googlebot' ) === false ) return false;

        $cache_file = ODGK_DATA_DIR . '/googlebot_cache.json';
        $cache = array();
        if ( file_exists( $cache_file ) ) {
            $c = @file_get_contents( $cache_file );
            if ( $c ) { $tmp = json_decode( $c, true ); if ( is_array( $tmp ) ) $cache = $tmp; }
        }

        $now = time();
        // Cleanup expired entries (1 in 200 requests)
        if ( ! empty( $cache ) && wp_rand( 1, 200 ) === 1 ) {
            $pruned = false;
            foreach ( $cache as $k => $v ) {
                if ( $v['expires'] <= $now ) { unset( $cache[ $k ] ); $pruned = true; }
            }
            if ( $pruned ) @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );
        }

        $hash = md5( $ip );
        if ( isset( $cache[ $hash ] ) && $cache[ $hash ]['expires'] > $now ) {
            return ! empty( $cache[ $hash ]['verified'] );
        }

        $hostname = @gethostbyaddr( $ip );
        if ( ! $hostname || $hostname === $ip ) {
            $cache[ $hash ] = [ 'verified' => 0, 'expires' => $now + 86400 ];
            @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );
            return false;
        }
        if ( stripos( $hostname, '.googlebot.com' ) === false && stripos( $hostname, '.google.com' ) === false ) {
            $cache[ $hash ] = [ 'verified' => 0, 'expires' => $now + 86400 ];
            @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );
            return false;
        }
        $resolved = @gethostbyname( $hostname );
        $verified = ( $resolved === $ip );

        $cache[ $hash ] = [ 'verified' => $verified ? 1 : 0, 'expires' => $now + 86400 ];
        @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );
        return $verified;
    }

    private static function get_country( $ip ) {
        // Server vars
        foreach ( array( 'GEOIP_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE_CF', 'HTTP_GEOIP_COUNTRY_CODE', 'HTTP_CF_IPCOUNTRY' ) as $var ) {
            if ( ! empty( $_SERVER[ $var ] ) ) {
                $code = strtoupper( trim( $_SERVER[ $var ] ) );
                if ( strlen( $code ) === 2 && ctype_alpha( $code ) ) return $code;
            }
        }

        // PECL
        if ( function_exists( 'geoip_country_code_by_name' ) ) {
            $code = @geoip_country_code_by_name( $ip );
            if ( $code && strlen( $code ) === 2 ) return strtoupper( $code );
        }

        // MaxMind
        if ( file_exists( ODGK_DB_FILE ) ) {
            try {
                $reader = new MaxMind_DB_Reader( ODGK_DB_FILE );
                return $reader->get_country_code( $ip );
            } catch ( Exception $e ) {}
        }

        return null;
    }
}
