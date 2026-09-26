<?php
/**
 * Ядро защиты: rate limiting, блокировка пагинации/фильтров, User-Agent, geo.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ODHLV_Core {

	private static $config = null;
	private static $blocked = false;

	public static function default_config() {
		return array(
			'enabled'                  => 1,
			'rate_limit_enabled'       => 1,
			'rate_limit_max_requests'  => 60,
			'rate_limit_window'        => 60,
			'block_pagination_enabled' => 1,
			'max_page'                 => 5,
			'max_per_page'             => 50,
			'block_long_query_enabled' => 1,
			'max_query_length'         => 400,
			'max_query_params'         => 8,
			'block_bad_ua_enabled'     => 1,
			'geo_enabled'              => 0,
			'allowed_countries'        => array( 'RU', 'BY', 'KZ', 'UZ' ),
			'blocked_ua_patterns'      => array(
				'python-requests', 'python-httpx', 'python-urllib',
				'Go-http-client', 'zgrab', 'masscan', 'nmap', 'sqlmap',
				'nikto', 'acunetix', 'nessus', 'curl/', 'wget/', 'scrapy',
				'axios', 'node-fetch', 'okhttp', 'libwww-perl', 'fasthttp',
			),
		);
	}

	public static function get_config() {
		if ( null !== self::$config ) {
			return self::$config;
		}
		$defaults = self::default_config();
		$config = $defaults;
		if ( file_exists( ODHLV_CONFIG_FILE ) ) {
			$raw = @file_get_contents( ODHLV_CONFIG_FILE );
			if ( $raw ) {
				$data = json_decode( $raw, true );
				if ( is_array( $data ) ) {
					$config = array_merge( $defaults, $data );
				}
			}
		}
		self::$config = $config;
		return $config;
	}

	public static function save_default_config() {
		if ( ! file_exists( ODHLV_CONFIG_FILE ) ) {
			self::save_config( self::default_config() );
		}
	}

	public static function save_config( $config ) {
		if ( ! is_dir( ODHLV_DATA_DIR ) ) {
			wp_mkdir_p( ODHLV_DATA_DIR );
		}
		@file_put_contents( ODHLV_CONFIG_FILE, json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		self::$config = $config;
	}

	/* ================== MU entry ================== */

	public static function run_mu() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) return;
		if ( php_sapi_name() === 'cli' ) return;
		if ( ! isset( $_SERVER['HTTP_HOST'] ) ) return;

		$config = self::get_config();
		if ( empty( $config['enabled'] ) ) return;

		$ip = self::get_client_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? trim( $_SERVER['HTTP_USER_AGENT'] ) : '';

		if ( self::is_search_engine( $ua ) ) return;

		if ( ! empty( $config['block_bad_ua_enabled'] ) && self::is_bad_ua( $ua, $config ) ) {
			self::block( $ip, 'bad_ua', $ua );
		}

		if ( ! empty( $config['block_pagination_enabled'] ) && self::is_bad_pagination( $config ) ) {
			self::block( $ip, 'pagination', self::get_request_uri() );
		}

		if ( ! empty( $config['block_long_query_enabled'] ) && self::is_bad_query( $config ) ) {
			self::block( $ip, 'long_query', self::get_request_uri() );
		}

		if ( ! empty( $config['geo_enabled'] ) && self::is_disallowed_country( $ip, $config ) ) {
			self::block( $ip, 'geo', self::get_country( $ip ) );
		}

		if ( ! empty( $config['rate_limit_enabled'] ) && self::is_rate_limited( $ip, $config ) ) {
			self::block( $ip, 'rate_limit', '' );
		}
	}

	/* ================== Detection ================== */

	private static function get_client_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ) as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return $ip;
			}
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
	}

	private static function get_request_uri() {
		return isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	}

	private static function is_search_engine( $ua ) {
		$patterns = array(
			'Googlebot', 'Google-InspectionTool', 'APIs-Google', 'AdsBot-Google',
			'Bingbot', 'msnbot', 'DuckDuckBot',
			'YandexBot', 'YandexImages', 'YandexMobileBot', 'YandexMetrika', 'YandexWebmaster', 'YandexTurbo',
			'Baiduspider', 'Sogou', 'Exabot', 'facebookexternalhit', 'Twitterbot',
		);
		foreach ( $patterns as $p ) {
			if ( stripos( $ua, $p ) !== false ) return true;
		}
		return false;
	}

	private static function is_bad_ua( $ua, $config ) {
		foreach ( $config['blocked_ua_patterns'] as $pattern ) {
			if ( '' === $pattern ) continue;
			if ( stripos( $ua, $pattern ) !== false ) return true;
		}
		return false;
	}

	private static function is_bad_pagination( $config ) {
		$uri = self::get_request_uri();
		$parsed = parse_url( $uri );
		$path = isset( $parsed['path'] ) ? $parsed['path'] : '';

		// Проверяем только REST API /wc/store и каталог.
		$is_store_api = ( false !== strpos( $path, '/wp-json/wc/store' ) );
		$is_catalog   = ( false !== strpos( $uri, '?' ) );
		if ( ! $is_store_api && ! $is_catalog ) return false;

		$query = isset( $parsed['query'] ) ? $parsed['query'] : '';
		if ( '' === $query ) return false;

		parse_str( $query, $params );

		$max_page     = (int) $config['max_page'];
		$max_per_page = (int) $config['max_per_page'];

		// page / paged — глубокая пагинация.
		foreach ( array( 'page', 'paged' ) as $k ) {
			if ( isset( $params[ $k ] ) && (int) $params[ $k ] > $max_page ) {
				return true;
			}
		}

		// per_page — запрос слишком большого объёма.
		if ( isset( $params['per_page'] ) && (int) $params['per_page'] > $max_per_page ) {
			return true;
		}

		return false;
	}

	private static function is_bad_query( $config ) {
		$uri = self::get_request_uri();
		$parsed = parse_url( $uri );
		$query = isset( $parsed['query'] ) ? $parsed['query'] : '';
		if ( '' === $query ) return false;

		if ( strlen( $query ) > (int) $config['max_query_length'] ) {
			return true;
		}

		parse_str( $query, $params );
		if ( count( $params ) > (int) $config['max_query_params'] ) {
			return true;
		}

		// Длинные значения filter_* (типа filter_shirina-rulona-m=0-0115%2C0-0175...).
		foreach ( $params as $k => $v ) {
			if ( 0 === strpos( $k, 'filter_' ) || 0 === strpos( $k, 'query_type_' ) ) {
				if ( is_string( $v ) && strlen( $v ) > 120 ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function is_rate_limited( $ip, $config ) {
		$now = time();
		$window = (int) $config['rate_limit_window'];
		$max = (int) $config['rate_limit_max_requests'];
		$key = md5( $ip );

		$data = array();
		if ( file_exists( ODHLV_RATELIMIT_FILE ) ) {
			$raw = @file_get_contents( ODHLV_RATELIMIT_FILE );
			if ( $raw ) {
				$tmp = json_decode( $raw, true );
				if ( is_array( $tmp ) ) $data = $tmp;
			}
		}

		// Периодическая очистка устаревших записей (1 из 100 запросов).
		if ( ! empty( $data ) && wp_rand( 1, 100 ) === 1 ) {
			$pruned = false;
			foreach ( $data as $k => $hits ) {
				$hits = array_values( array_filter( $hits, function ( $t ) use ( $now, $window ) {
					return $t > ( $now - $window );
				} ) );
				if ( empty( $hits ) ) { unset( $data[ $k ] ); $pruned = true; }
				else { $data[ $k ] = $hits; }
			}
			if ( $pruned ) @file_put_contents( ODHLV_RATELIMIT_FILE, json_encode( $data ), LOCK_EX );
		}

		$hits = isset( $data[ $key ] ) ? $data[ $key ] : array();
		$hits = array_values( array_filter( $hits, function ( $t ) use ( $now, $window ) {
			return $t > ( $now - $window );
		} ) );

		if ( count( $hits ) >= $max ) {
			$data[ $key ] = $hits;
			@file_put_contents( ODHLV_RATELIMIT_FILE, json_encode( $data ), LOCK_EX );
			return true;
		}

		$hits[] = $now;
		$data[ $key ] = $hits;
		@file_put_contents( ODHLV_RATELIMIT_FILE, json_encode( $data ), LOCK_EX );
		return false;
	}

	private static function is_disallowed_country( $ip, $config ) {
		$country = self::get_country( $ip );
		if ( null === $country || '' === $country ) return false;
		$allowed = (array) $config['allowed_countries'];
		return ! in_array( strtoupper( $country ), array_map( 'strtoupper', $allowed ), true );
	}

	private static function get_country( $ip ) {
		// Server vars (Cloudflare и т.п.)
		foreach ( array( 'GEOIP_COUNTRY_CODE', 'HTTP_CF_IPCOUNTRY', 'HTTP_GEOIP_COUNTRY_CODE' ) as $v ) {
			if ( ! empty( $_SERVER[ $v ] ) ) {
				$c = strtoupper( trim( $_SERVER[ $v ] ) );
				if ( strlen( $c ) === 2 && ctype_alpha( $c ) ) return $c;
			}
		}

		// MaxMind DB (если есть файл).
		if ( file_exists( ODHLV_DB_FILE ) && file_exists( ODHLV_DIR . 'lib/class-maxmind-db.php' ) ) {
			if ( ! class_exists( 'ODHLV_MaxMind_DB_Reader' ) ) {
				require_once ODHLV_DIR . 'lib/class-maxmind-db.php';
			}
			try {
				$reader = new ODHLV_MaxMind_DB_Reader( ODHLV_DB_FILE );
				return $reader->get_country_code( $ip );
			} catch ( Exception $e ) {}
		}

		return null;
	}

	/* ================== Blocking ================== */

	private static function block( $ip, $reason, $detail ) {
		if ( self::$blocked ) return;
		self::$blocked = true;

		self::log_block( $ip, $reason, $detail );

		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Retry-After: 3600' );
		echo 'Access denied.';
		exit;
	}

	private static function log_block( $ip, $reason, $detail ) {
		$entries = array();
		if ( file_exists( ODHLV_BLOCK_LOG_FILE ) ) {
			$raw = @file_get_contents( ODHLV_BLOCK_LOG_FILE );
			if ( $raw ) {
				$tmp = json_decode( $raw, true );
				if ( is_array( $tmp ) ) $entries = $tmp;
			}
		}
		$entries[] = array(
			'ip'     => $ip,
			'reason' => $reason,
			'detail' => mb_substr( $detail, 0, 300 ),
			'ua'     => mb_substr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 200 ),
			'time'   => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( count( $entries ) > 2000 ) $entries = array_slice( $entries, -2000 );
		@file_put_contents( ODHLV_BLOCK_LOG_FILE, json_encode( $entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/* ================== Log helpers for admin ================== */

	public static function get_block_log() {
		if ( ! file_exists( ODHLV_BLOCK_LOG_FILE ) ) return array();
		$raw = @file_get_contents( ODHLV_BLOCK_LOG_FILE );
		if ( ! $raw ) return array();
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	public static function clear_block_log() {
		if ( file_exists( ODHLV_BLOCK_LOG_FILE ) ) @unlink( ODHLV_BLOCK_LOG_FILE );
	}

	public static function get_db_info() {
		$file = ODHLV_DB_FILE;
		$info = array(
			'file'       => $file,
			'exists'     => file_exists( $file ),
			'build_date' => '',
			'size'       => '',
		);
		if ( $info['exists'] ) {
			$info['size'] = size_format( filesize( $file ) );
			if ( file_exists( ODHLV_DIR . 'lib/class-maxmind-db.php' ) ) {
				if ( ! class_exists( 'ODHLV_MaxMind_DB_Reader' ) ) {
					require_once ODHLV_DIR . 'lib/class-maxmind-db.php';
				}
				try {
					$reader = new ODHLV_MaxMind_DB_Reader( $file );
					$epoch = method_exists( $reader, 'get_build_epoch' ) ? $reader->get_build_epoch() : 0;
					if ( $epoch > 0 ) {
						$info['build_date'] = function_exists( 'wp_date' ) ? wp_date( 'd.m.Y H:i:s', $epoch ) : gmdate( 'd.m.Y H:i:s', $epoch );
					}
					unset( $reader );
				} catch ( Exception $e ) {}
			}
		} else {
			$info['size'] = '—';
		}
		return $info;
	}
}
