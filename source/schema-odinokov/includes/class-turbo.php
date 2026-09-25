<?php
/**
 * Yandex Turbo pages — отдельный RSS-фид.
 *
 * Фид доступен по адресу /turbo.xml. Содержимое генерируется динамически,
 * с кэшированием. Интеграция с Yoast SEO: ссылка на фид добавляется в
 * sitemap_index.xml.
 *
 * @package Schema_Odinokov
 */

namespace Odinokov\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Turbo {

	const OPTION_KEY   = 'schema_odinokov_turbo';
	const QUERY_VAR    = 'sod_turbo';
	const CACHE_KEY    = 'sod_turbo_feed';
	const CACHE_TTL    = 6 * HOUR_IN_SECONDS;

	/** @var Turbo|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register() {
		add_action( 'init', [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_feed' ] );

		// Yoast SEO: добавить ссылку на турбо-фид в sitemap_index.xml.
		add_filter( 'wpseo_sitemap_index', [ $this, 'add_to_yoast_sitemap_index' ] );
	}

	/**
	 * Настройки турбо-фида.
	 *
	 * @return array{enabled: int, post_types: array, items: int}
	 */
	public function get_settings() {
		$defaults = [
			'enabled'    => 1,
			'post_types' => [ 'post', 'page', 'product' ],
			'items'      => 100,
		];

		$opt = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $opt ) ) {
			$opt = [];
		}

		$settings = wp_parse_args( $opt, $defaults );
		$settings['enabled'] = ! empty( $settings['enabled'] ) ? 1 : 0;
		$settings['items']   = max( 1, min( 500, (int) $settings['items'] ) );
		$settings['post_types'] = array_values( array_filter( (array) $settings['post_types'], function ( $pt ) {
			return in_array( $pt, [ 'post', 'page', 'product' ], true );
		} ) );

		if ( empty( $settings['post_types'] ) ) {
			$settings['post_types'] = [ 'post', 'page', 'product' ];
		}

		return $settings;
	}

	public function get_feed_url() {
		return home_url( '/turbo.xml' );
	}

	public function add_rewrite_rule() {
		add_rewrite_rule( '^turbo\.xml$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * При запросе /turbo.xml отдаём фид.
	 */
	public function maybe_serve_feed() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			status_header( 404 );
			exit;
		}

		$feed = get_transient( self::CACHE_KEY );
		if ( false === $feed ) {
			$feed = $this->build_feed( $settings );
			set_transient( self::CACHE_KEY, $feed, self::CACHE_TTL );
		}

		status_header( 200 );
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		echo $feed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — валидный XML-фид.
		exit;
	}

	/**
	 * Строит XML-фид Яндекс.Турбо.
	 *
	 * @param array $settings
	 * @return string
	 */
	public function build_feed( $settings ) {
		$items = $this->get_feed_items( $settings );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss version="2.0" xmlns:yandex="http://news.yandex.ru" xmlns:media="http://search.yahoo.com/mrss/" xmlns:turbo="http://turbo.yandex.ru">' . "\n";
		$xml .= '<channel>' . "\n";
		$xml .= '<title>' . esc_xml( get_bloginfo( 'name' ) ) . '</title>' . "\n";
		$xml .= '<link>' . esc_xml( home_url( '/' ) ) . '</link>' . "\n";
		$xml .= '<description>' . esc_xml( get_bloginfo( 'description' ) ) . '</description>' . "\n";
		$xml .= '<language>ru</language>' . "\n";
		$xml .= '<generator>Schema Odinokov</generator>' . "\n";

		foreach ( $items as $post ) {
			$xml .= $this->build_item( $post );
		}

		$xml .= '</channel>' . "\n";
		$xml .= '</rss>' . "\n";

		return $xml;
	}

	/**
	 * Получает записи для фида.
	 *
	 * @param array $settings
	 * @return \WP_Post[]
	 */
	private function get_feed_items( $settings ) {
		$post_types = $settings['post_types'];
		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $settings['items'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'ignore_sticky_posts' => true,
		];

		$query = new \WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Строит элемент <item> для турбо-фида.
	 *
	 * @param \WP_Post $post
	 * @return string
	 */
	private function build_item( \WP_Post $post ) {
		$title    = get_the_title( $post );
		$url      = get_permalink( $post );
		$content  = $this->get_turbo_content( $post );

		$image = '';
		$image_id = get_post_thumbnail_id( $post );
		if ( $image_id ) {
			$src = wp_get_attachment_image_src( $image_id, 'full' );
			if ( ! empty( $src[0] ) ) {
				$image = $src[0];
			}
		}

		$xml  = '<item turbo="true">' . "\n";
		$xml .= '<title>' . esc_xml( $title ) . '</title>' . "\n";
		$xml .= '<link>' . esc_xml( $url ) . '</link>' . "\n";
		$xml .= '<turbo:source>' . esc_xml( $url ) . '</turbo:source>' . "\n";
		$xml .= '<turbo:topic>' . esc_xml( $title ) . '</turbo:topic>' . "\n";
		$xml .= '<pubDate>' . esc_xml( mysql2date( 'r', $post->post_date_gmt, false ) ) . '</pubDate>' . "\n";

		$body = '';
		$body .= '<header><h1>' . esc_html( $title ) . '</h1></header>' . "\n";
		if ( $image ) {
			$body .= '<figure><img src="' . esc_url( $image ) . '" /></figure>' . "\n";
		}
		$body .= $content;

		$xml .= '<turbo:content><![CDATA[' . "\n" . $body . "\n" . ']]></turbo:content>' . "\n";
		$xml .= '</item>' . "\n";

		return $xml;
	}

	/**
	 * Приводит контент записи к допустимому для Турбо HTML.
	 *
	 * @param \WP_Post $post
	 * @return string
	 */
	private function get_turbo_content( \WP_Post $post ) {
		$content = apply_filters( 'the_content', $post->post_content );
		$content = $this->sanitize_turbo_html( $content );
		return $content;
	}

	/**
	 * Убирает из HTML всё, что не поддерживается Яндекс.Турбо.
	 *
	 * @param string $html
	 * @return string
	 */
	private function sanitize_turbo_html( $html ) {
		// Убираем скрипты, стили, формы, iframe.
		$html = preg_replace( '#<script[^>]*>.*?</script>#is', '', $html );
		$html = preg_replace( '#<style[^>]*>.*?</style>#is', '', $html );
		$html = preg_replace( '#<form[^>]*>.*?</form>#is', '', $html );
		$html = preg_replace( '#<iframe[^>]*>.*?</iframe>#is', '', $html );
		$html = preg_replace( '#<object[^>]*>.*?</object>#is', '', $html );
		$html = preg_replace( '#<embed[^>]*>#is', '', $html );

		// Оставляем только допустимые теги и атрибуты.
		$allowed = [
			'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
			'p'  => [], 'br' => [], 'hr' => [],
			'a'  => [ 'href' => true ],
			'ul' => [], 'ol' => [], 'li' => [],
			'blockquote' => [], 'figure' => [], 'figcaption' => [],
			'img' => [ 'src' => true ],
			'video' => [ 'src' => true ],
			'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [],
			'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
			'header' => [], 'menu' => [], 'button' => [ 'formaction' => true ],
		];

		return wp_kses( $html, $allowed );
	}

	/**
	 * Добавляет ссылку на турбо-фид в sitemap_index.xml (Yoast SEO).
	 *
	 * @param string $sitemap_custom_items
	 * @return string
	 */
	public function add_to_yoast_sitemap_index( $sitemap_custom_items ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return $sitemap_custom_items;
		}

		$url = $this->get_feed_url();

		$sitemap_custom_items .= '<sitemap>' . "\n";
		$sitemap_custom_items .= '<loc>' . esc_url( $url ) . '</loc>' . "\n";
		$sitemap_custom_items .= '<lastmod>' . esc_xml( gmdate( 'c' ) ) . '</lastmod>' . "\n";
		$sitemap_custom_items .= '</sitemap>' . "\n";

		return $sitemap_custom_items;
	}
}
