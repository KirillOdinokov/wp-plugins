<?php
require_once __DIR__ . '/persist.php';

class WP_Geo_MU_Blocker {

    public static function check() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) return;
        if ( php_sapi_name() === 'cli' ) return;
        if ( ! isset( $_SERVER['HTTP_HOST'] ) ) return;

        // Восстанавливаем базу/исключения из резервной копии, если апдейт их стёр.
        if ( function_exists( 'odgk_persist_sync' ) ) {
            odgk_persist_sync();
        }

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
            // Google-сервисы с обычным браузерным UA (PageSpeed и т.п.) приходят с
            // IP Google. Reverse-DNS делаем только если в UA есть «google», иначе
            // под флудом это тысячи DNS-запросов на каждый заблокированный IP.
            if ( false !== stripos( $ua, 'google' ) && self::is_google_ip( $ip ) ) return;

            self::log_block( $ip, $country, $ua );

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

    /**
     * Логирование гео-блокировок в append-only JSONL (быстро и безопасно под флудом,
     * без перезаписи всего массива на каждый блок). Читается в админке (Geo Log).
     */
    private static function log_block( $ip, $country, $ua ) {
        if ( ! defined( 'ODGK_DATA_DIR' ) ) return;
        $file  = ODGK_DATA_DIR . '/geo-blocks.log';
        $entry = array(
            'time'    => gmdate( 'Y-m-d H:i:s' ),
            'ip'      => $ip,
            'country' => $country,
            'ua'      => mb_substr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 200 ),
            'url'     => mb_substr( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', 0, 300 ),
        );
        @file_put_contents( $file, json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND | LOCK_EX );

        // Ротация: 1 раз на ~200 блокировок, если файл распух — оставляем последние 5000 строк.
        if ( mt_rand( 1, 200 ) === 1 && @filesize( $file ) > 8 * 1024 * 1024 ) {
            $lines = @file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            if ( is_array( $lines ) ) {
                $lines = array_slice( $lines, -5000 );
                @file_put_contents( $file, implode( "\n", $lines ) . "\n", LOCK_EX );
            }
        }
    }

    private static function is_verified_googlebot( $ua, $ip ) {
        if ( ! self::is_google_ua( $ua ) ) return false;

        // Краулеров Google (Googlebot и пр.) никогда не блокируем гео-блоком —
        // даже если reverse-DNS не прошёл (например, таймаут DNS). Reverse-DNS
        // оставляем только как пометку verified в кэше для диагностики.
        $cache_file = ODGK_DATA_DIR . '/googlebot_cache.json';
        $cache = array();
        if ( file_exists( $cache_file ) ) {
            $c = @file_get_contents( $cache_file );
            if ( $c ) { $tmp = json_decode( $c, true ); if ( is_array( $tmp ) ) $cache = $tmp; }
        }

        $now = time();
        // Cleanup expired entries (1 in 200 requests)
        if ( ! empty( $cache ) && mt_rand( 1, 200 ) === 1 ) {
            $pruned = false;
            foreach ( $cache as $k => $v ) {
                if ( $v['expires'] <= $now ) { unset( $cache[ $k ] ); $pruned = true; }
            }
            if ( $pruned ) @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );
        }

        $hash = md5( $ip );
        if ( ! isset( $cache[ $hash ] ) || $cache[ $hash ]['expires'] <= $now ) {
            $hostname = @gethostbyaddr( $ip );
            $verified = ( $hostname && $hostname !== $ip
                && ( stripos( $hostname, '.googlebot.com' ) !== false || stripos( $hostname, '.google.com' ) !== false )
                && @gethostbyname( $hostname ) === $ip );
            $cache[ $hash ] = [ 'verified' => $verified ? 1 : 0, 'expires' => $now + 86400 ];
            @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );
        }

        return true;
    }

    private static function is_google_ua( $ua ) {
        $bots = array(
            'Googlebot',                 // основной краулер (включая Image/News/Video)
            'Google-InspectionTool',     // инструмент проверки URL (Search Console)
            'Google-Site-Verification',  // подтверждение владения сайтом
            'GoogleOther',               // прочие задачи Google
            'Google-Extended',           // краулер для AI (BERT/Gemini)
            'Google-Read-Aloud',         // чтение вслух
            'Storebot-Google',           // Google Store
            'AdsBot-Google',             // краулер рекламы
            'Mediapartners-Google',      // AdSense
            'APIs-Google',               // Google API
            'Feedfetcher-Google',        // Google News/Reader
            'GoogleProducer',            // Google News
            'Google-Publisher-Plugin',   // Google Publisher
            'Google Favicon',            // загрузчик favicon
        );
        foreach ( $bots as $b ) {
            if ( stripos( $ua, $b ) !== false ) return true;
        }
        return false;
    }

    private static function is_google_ip( $ip ) {
        $cache_file = ODGK_DATA_DIR . '/google_ip_cache.json';
        $cache = array();
        if ( file_exists( $cache_file ) ) {
            $c = @file_get_contents( $cache_file );
            if ( $c ) { $tmp = json_decode( $c, true ); if ( is_array( $tmp ) ) $cache = $tmp; }
        }

        $now  = time();
        $hash = md5( $ip );

        if ( isset( $cache[ $hash ] ) && $cache[ $hash ]['expires'] > $now ) {
            return ! empty( $cache[ $hash ]['is_google'] );
        }

        // Occasional cleanup of expired entries (1 in 200 requests).
        if ( ! empty( $cache ) && mt_rand( 1, 200 ) === 1 ) {
            $pruned = false;
            foreach ( $cache as $k => $v ) {
                if ( $v['expires'] <= $now ) { unset( $cache[ $k ] ); $pruned = true; }
            }
            if ( $pruned ) @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );
        }

        $hostname  = @gethostbyaddr( $ip );
        $is_google = false;
        if ( $hostname && $hostname !== $ip ) {
            foreach ( array( '.googlebot.com', '.googleusercontent.com', '.google.com' ) as $s ) {
                if ( substr( $hostname, -strlen( $s ) ) === $s ) { $is_google = true; break; }
            }
        }

        $cache[ $hash ] = [ 'is_google' => $is_google ? 1 : 0, 'expires' => $now + 86400 ];
        @file_put_contents( $cache_file, json_encode( $cache ), LOCK_EX );

        return $is_google;
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
