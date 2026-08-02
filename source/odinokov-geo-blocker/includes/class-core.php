<?php
class WP_Geo_Blocker {

    private static $instance = null;
    private $db_reader = null;
    private $allowed_countries = array( 'RU', 'BY', 'US', 'UA', 'KZ', 'UZ' );
    private $country_names = array(
        'RU' => 'Россия / Russia', 'BY' => 'Беларусь / Belarus', 'US' => 'США / USA',
        'UA' => 'Украина / Ukraine', 'KZ' => 'Казахстан / Kazakhstan', 'UZ' => 'Узбекистан / Uzbekistan',
    );
    private $exceptions_file;
    private $exceptions = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        if ( defined( 'ODGK_MU_LOADED' ) ) return;
        $this->exceptions_file = ODGK_DATA_DIR . '/exceptions.json';
        if ( $this->is_cli() || $this->is_cron() ) return;
        $this->load_exceptions();
        $ip = $this->get_client_ip();
        if ( $this->is_ip_excepted( $ip ) ) return;
        $country = $this->get_country_by_ip( $ip );
        if ( null === $country ) return;

        if ( ! in_array( $country, $this->allowed_countries, true ) ) {
            WP_Geo_Blocker_Logger::log( $ip, $country );
            if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['odgk_submit'] ) ) {
                $this->handle_submit( $ip, $country );
            } else {
                $this->show_block_page( $ip, $country );
            }
        }
    }

    private function handle_submit( $ip, $country ) {
        $answer = isset( $_POST['odgk_captcha'] ) ? (int) $_POST['odgk_captcha'] : 0;
        $expected = isset( $_POST['odgk_answer'] ) ? (int) $_POST['odgk_answer'] : -1;

        if ( $answer !== $expected ) {
            $this->show_block_page( $ip, $country, 'Неверный ответ капчи / Wrong captcha answer.' );
            return;
        }

        $to = get_option( 'odinokov_geo_notify_email', 'odinokov.k@yandex.ru' );
        $sent = wp_mail(
            $to,
            '[Odinokov Geo Blocker] Access Request: ' . $ip,
            "IP: $ip\nCountry: $country\nDate: " . current_time('mysql') . "\nSite: " . home_url() . "\n\nAdd this IP to Odinokov → Geo Blocker → Exceptions",
            array( 'Content-Type: text/plain; charset=UTF-8' )
        );

        status_header( 403 ); nocache_headers();
        $msg_ru = $sent ? 'Запрос отправлен. Ваш IP будет рассмотрен для разблокировки.' : 'Ошибка отправки. Попробуйте позже.';
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Доступ ограничен</title><style>body{font-family:sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}.box{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:600px;padding:40px;text-align:center}h1{color:#d32f2f;margin-bottom:20px}.msg{background:#e8f5e9;color:#2e7d32;border-radius:6px;padding:16px;margin:20px 0}.msg.err{background:#ffebee;color:#c62828}</style></head><body><div class="box"><h1>Доступ ограничен</h1><div class="msg' . ($sent ? '' : ' err') . '"><p>' . esc_html($msg_ru) . '</p></div></div></body></html>';
        die();
    }

    private function is_cli() { return defined('WP_CLI') && WP_CLI || php_sapi_name() === 'cli' || ! isset($_SERVER['HTTP_HOST']); }
    private function is_cron() { return defined('DOING_CRON') && DOING_CRON; }

    private function get_client_ip() {
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ) as $h ) {
            if ( ! empty( $_SERVER[ $h ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return $ip;
            }
        }
        return '127.0.0.1';
    }

    private function get_country_by_ip( $ip ) {
        $c = $this->get_country_from_server_vars(); if ( $c ) return $c;
        $c = $this->get_country_from_pecl( $ip ); if ( $c ) return $c;
        if ( ! $this->db_reader ) {
            if ( file_exists( ODGK_DB_FILE ) ) {
                try { $this->db_reader = new MaxMind_DB_Reader( ODGK_DB_FILE ); }
                catch ( Exception $e ) { $this->db_reader = false; }
            } else { $this->db_reader = false; }
        }
        if ( $this->db_reader && $this->db_reader !== false ) {
            try { return $this->db_reader->get_country_code( $ip ); } catch ( Exception $e ) {}
        }
        return $this->get_country_by_api( $ip );
    }

    private function get_country_from_server_vars() {
        foreach ( array( 'GEOIP_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE_CF', 'HTTP_GEOIP_COUNTRY_CODE', 'HTTP_CF_IPCOUNTRY' ) as $v ) {
            if ( ! empty( $_SERVER[ $v ] ) ) { $c = strtoupper( trim( $_SERVER[ $v ] ) ); if ( strlen( $c ) === 2 && ctype_alpha( $c ) ) return $c; }
        }
        return null;
    }

    private function get_country_from_pecl( $ip ) {
        if ( function_exists( 'geoip_country_code_by_name' ) ) { $c = @geoip_country_code_by_name( $ip ); if ( $c ) return strtoupper( $c ); }
        return null;
    }

    private function get_country_by_api( $ip ) {
        $key = 'odgk_country_' . md5( $ip ); $cached = get_transient( $key ); if ( false !== $cached ) return $cached ?: null;
        $r = wp_remote_get( 'http://ip-api.com/json/' . $ip . '?fields=countryCode', array( 'timeout' => 5 ) );
        if ( is_wp_error( $r ) ) { set_transient( $key, '', HOUR_IN_SECONDS ); return null; }
        $d = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( isset( $d['countryCode'] ) && $d['countryCode'] ) { set_transient( $key, $d['countryCode'], DAY_IN_SECONDS ); return $d['countryCode']; }
        set_transient( $key, '', HOUR_IN_SECONDS ); return null;
    }

    private function load_exceptions() {
        if ( file_exists( $this->exceptions_file ) ) {
            $c = @file_get_contents( $this->exceptions_file );
            $this->exceptions = ( $c && ( $d = json_decode( $c, true ) ) && is_array( $d ) ) ? $d : array();
        } else { $this->exceptions = array(); }
    }

    private function is_ip_excepted( $ip ) {
        foreach ( $this->exceptions as $e ) { if ( isset( $e['ip'] ) && $e['ip'] === $ip ) return true; }
        return false;
    }

    private function show_block_page( $ip, $country, $error_msg = '' ) {
        $list = implode( ', ', array_values( $this->country_names ) );
        $ca = wp_rand( 1, 20 ); $cb = wp_rand( 1, 20 ); $cr = $ca + $cb;
        $err = $error_msg ? '<div class="msg"><p>' . esc_html( $error_msg ) . '</p></div>' : '';
        $html = '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Доступ ограничен</title><style>body{font-family:sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;color:#333}.box{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:600px;padding:40px}h1{color:#d32f2f;margin-bottom:20px}p{line-height:1.6;margin-bottom:12px}.en{color:#666;font-style:italic}.cb{background:#f0f4ff;border:1px solid #c5d3f0;border-radius:8px;padding:20px;margin:20px 0}.cb label{display:block;font-weight:600;margin-bottom:8px}.cb input{width:100%;padding:10px 14px;border:1px solid #ccc;border-radius:6px;font-size:16px}.fg{margin-bottom:16px}.fg label{display:block;font-weight:600;margin-bottom:6px}.fg input{width:100%;padding:10px 14px;border:1px solid #ccc;border-radius:6px;font-size:16px}.btn{background:#1976d2;color:#fff;border:none;padding:12px 28px;border-radius:6px;font-size:16px;cursor:pointer}.btn:hover{background:#1565c0}.msg{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:6px;padding:12px;margin-bottom:16px}</style></head><body><div class="box"><h1>Доступ ограничен</h1><p>Сайт доступен только с территорий: ' . esc_html( $list ) . '.</p><p class="en">This website is only accessible from: ' . esc_html( $list ) . '.</p>' . $err . '<form method="post"><div class="fg"><label>Ваш IP:</label><input value="' . esc_attr( $ip ) . '" readonly style="background:#f9f9f9;"></div><div class="cb"><label>Решите пример: ' . esc_html( "$ca + $cb = ?" ) . '</label><input type="text" name="odgk_captcha" placeholder="Ответ" autocomplete="off" required></div><input type="hidden" name="odgk_answer" value="' . $cr . '"><button type="submit" name="odgk_submit" value="1" class="btn">Отправить запрос</button></form></div></body></html>';
        status_header( 403 ); nocache_headers(); die( $html );
    }
}
