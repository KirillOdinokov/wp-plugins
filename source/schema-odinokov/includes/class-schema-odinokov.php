<?php
/**
 * Основной класс плагина Schema Odinokov.
 *
 * @package Schema_Odinokov
 */

namespace Odinokov\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    /** @var Plugin|null */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {}

    public function boot() {
        add_action( 'wp_head', [ $this, 'output_jsonld' ], 5 );
        add_action( 'wp_head', [ $this, 'output_og_image' ], 6 );
        add_action( 'wp', [ $this, 'maybe_disable_yoast_jsonld' ] );
    }

    /**
     * Отключает JSON-LD Yoast SEO на страницах товаров,
     * чтобы schema-odinokov мог выводить свою Product-разметку.
     */
    public function maybe_disable_yoast_jsonld() {
        if ( ! is_singular( 'product' ) || ! $this->is_woocommerce_active() ) {
            return;
        }
        add_filter( 'wpseo_json_ld_output', '__return_empty_array' );
    }

    /**
     * Совместимость с WooCommerce: проверка активности по константе/функции,
     * не требует подключения плагина в момент загрузки этого файла.
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
    }

    /**
     * Главный диспетчер JSON-LD.
     */
    public function output_jsonld() {
        $this->render_website_schema();
        $this->render_organization_schema();
        $this->render_local_business_schema();

        if ( is_singular( 'product' ) && $this->is_woocommerce_active() ) {
            $this->render_product_schema();
            return;
        }

        if ( is_singular( [ 'post', 'page' ] ) ) {
            $this->render_article_schema();
        }

        $this->render_breadcrumb_schema();
    }

    /**
     * JSON-LD LocalBusiness — отдельным блоком.
     * Выводится, только если заполнен хотя бы один из адресных полей.
     */
    private function render_local_business_schema() {
        $opt = get_option( 'schema_odinokov_organization', [] );

        $street   = isset( $opt['street'] )      ? trim( (string) $opt['street'] )      : '';
        $locality = isset( $opt['locality'] )    ? trim( (string) $opt['locality'] )    : '';
        $region   = isset( $opt['region'] )      ? trim( (string) $opt['region'] )      : '';
        $postal   = isset( $opt['postal_code'] ) ? trim( (string) $opt['postal_code'] ) : '';
        $country  = isset( $opt['country'] )     ? trim( (string) $opt['country'] )     : '';

        // Без адреса не выводим — Google ругается на пустой PostalAddress.
        if ( '' === $street && '' === $locality && '' === $region && '' === $postal && '' === $country ) {
            return;
        }

        $org = $this->get_organization_data();

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            '@id'         => $org['url'] . '#localbusiness',
            'name'        => $org['name'],
            'url'         => $org['url'],
            'description' => $org['description'] ?? '',
        ];

        if ( $org['logo'] ) {
            $schema['logo'] = $org['logo'];
        }
        if ( '' !== $org['telephone'] ) {
            $schema['telephone'] = $org['telephone'];
        }
        if ( '' !== $org['email'] ) {
            $schema['email'] = $org['email'];
        }

        $price_range = isset( $opt['price_range'] ) ? trim( (string) $opt['price_range'] ) : '';
        if ( '' !== $price_range ) {
            $schema['priceRange'] = $price_range;
        }

        $address = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $street,
            'addressLocality' => $locality,
            'addressRegion'   => $region,
            'postalCode'      => $postal,
            'addressCountry'  => $country,
        ];
        $schema['address'] = $address;

        $opening = $this->build_opening_hours_specification( isset( $opt['opening'] ) ? (array) $opt['opening'] : [] );
        if ( ! empty( $opening ) ) {
            $schema['openingHoursSpecification'] = $opening;
        }

        /**
         * Фильтр позволяет дополнить LocalBusiness-схему.
         *
         * @param array $schema
         * @param array $opt Сырые значения опций.
         */
        $schema = (array) apply_filters( 'schema_odinokov_local_business_schema', $schema, $opt );

        $this->emit_jsonld( $schema );
    }

    /**
     * Преобразует сохранённые часы работы в массив OpeningHoursSpecification.
     *
     * @param array $input Ассоциативный массив по кодам дней (Mo..Su) с ключами opens/closes.
     * @return array
     */
    private function build_opening_hours_specification( $input ) {
        $days_map = [
            'Mo' => [ 'Monday',    'https://schema.org/Monday' ],
            'Tu' => [ 'Tuesday',   'https://schema.org/Tuesday' ],
            'We' => [ 'Wednesday', 'https://schema.org/Wednesday' ],
            'Th' => [ 'Thursday',  'https://schema.org/Thursday' ],
            'Fr' => [ 'Friday',    'https://schema.org/Friday' ],
            'Sa' => [ 'Saturday',  'https://schema.org/Saturday' ],
            'Su' => [ 'Sunday',    'https://schema.org/Sunday' ],
        ];

        $specs = [];
        foreach ( $days_map as $code => $names ) {
            if ( empty( $input[ $code ]['opens'] ) || empty( $input[ $code ]['closes'] ) ) {
                continue;
            }
            $specs[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => [ $names[1] ],
                'opens'     => $input[ $code ]['opens'],
                'closes'    => $input[ $code ]['closes'],
            ];
        }

        return $specs;
    }

    /**
     * JSON-LD Organization — выводится на всех страницах сайта
     * на основе настроек из админки.
     */
    private function render_organization_schema() {
        $opt = get_option( 'schema_odinokov_organization', [] );

        $name        = ! empty( $opt['name'] ) ? $opt['name'] : get_bloginfo( 'name' );
        $url         = ! empty( $opt['url'] ) ? $opt['url'] : home_url( '/' );
        $description = ! empty( $opt['description'] ) ? $opt['description'] : get_bloginfo( 'description' );
        $telephone   = isset( $opt['telephone'] ) ? (string) $opt['telephone'] : '';
        $email       = isset( $opt['email'] ) ? (string) $opt['email'] : '';

        $logo_url = '';
        if ( ! empty( $opt['logo_id'] ) ) {
            $src = wp_get_attachment_image_src( (int) $opt['logo_id'], 'full' );
            if ( ! empty( $src[0] ) ) {
                $logo_url = $src[0];
            }
        }

        $contact = [];
        if ( '' !== $telephone ) {
            $contact['contactType'] = 'customer support';
            $contact['telephone']   = $telephone;
        }
        if ( '' !== $email ) {
            $contact['email'] = $email;
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            'name'        => $name,
            'url'         => $url,
            'description' => $description,
        ];

        if ( $logo_url ) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logo_url,
            ];
        }

        if ( ! empty( $contact ) ) {
            $schema['contactPoint'] = array_merge( [ '@type' => 'ContactPoint' ], $contact );
        }

        /**
         * Фильтр позволяет дополнить Organization-схему, не редактируя плагин.
         *
         * @param array $schema
         * @param array $opt     Сырые значения опций.
         */
        $schema = (array) apply_filters( 'schema_odinokov_organization_schema', $schema, $opt );

        $this->emit_jsonld( $schema );
    }

    /**
     * JSON-LD Product для карточки товара WooCommerce.
     * Поля: name, image, description, sku, price, priceCurrency, availability.
     */
    private function render_product_schema() {
        global $post;

        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
        if ( ! $product ) {
            return;
        }

        $name        = $product->get_name();
        $description = wp_strip_all_tags( $product->get_description() );
        if ( '' === $description ) {
            $description = wp_strip_all_tags( $product->get_short_description() );
        }
        if ( '' === $description ) {
            $description = $name;
        }

        $image = $this->get_product_image_url( $product );
        $sku   = (string) $product->get_sku();

        $price         = (string) $product->get_price();
        $regular_price = (string) $product->get_regular_price();
        $currency      = (string) get_woocommerce_currency();

        // availability по статусу наличия
        $stock_status = (string) $product->get_stock_status();
        $availability = ( 'outofstock' === $stock_status )
            ? 'https://schema.org/OutOfStock'
            : 'https://schema.org/InStock';

        $org_name = $this->get_organization_data()['name'];

        $schema = [
            '@context'      => 'https://schema.org/',
            '@type'         => 'Product',
            '@id'           => get_permalink( $product->get_id() ) . '#product',
            'name'          => $name,
            'description'   => $description,
            'image'         => $image,
            'sku'           => $sku,
            'brand'         => [ '@type' => 'Brand', 'name' => $org_name ],
            'offers'        => [
                '@type'         => 'Offer',
                'url'           => get_permalink( $product->get_id() ),
                'priceCurrency' => $currency,
                'price'         => ( '' === $price ) ? '0' : $price,
                'availability'  => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'priceValidUntil' => gmdate( 'Y-12-31' ),
            ],
        ];

        // AggregateRating — то, чего нет в Yoast Free
        if ( $product->get_review_count() > 0 ) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) round( (float) $product->get_average_rating(), 1 ),
                'reviewCount' => (int) $product->get_review_count(),
                'bestRating'  => '5',
                'worstRating' => '1',
            ];
        }

        // GTIN / MPN — если есть (ACF/мета-поля)
        $gtin = get_post_meta( $product->get_id(), '_gtin', true ) ?: get_post_meta( $product->get_id(), 'gtin', true );
        if ( $gtin ) $schema['gtin13'] = $gtin;
        $mpn = get_post_meta( $product->get_id(), '_mpn', true ) ?: get_post_meta( $product->get_id(), 'mpn', true );
        if ( $mpn ) $schema['mpn'] = $mpn;

        /**
         * Фильтр позволяет дополнить схему, не редактируя плагин.
         *
         * @param array   $schema   Сформированный массив схемы.
         * @param \WC_Product $product Объект товара.
         */
        $schema = (array) apply_filters( 'schema_odinokov_product_schema', $schema, $product );

        $this->emit_jsonld( $schema );
    }

    /**
     * JSON-LD Article для обычных записей/страниц.
     */
    private function render_article_schema() {
        if ( ! is_singular( [ 'post', 'page' ] ) ) {
            return;
        }

        $post = get_queried_object();
        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        $image    = $this->get_post_image_url( $post );
        $org_data = $this->get_organization_data();

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => get_the_title( $post ),
            'description'      => wp_strip_all_tags( wp_kses_post( $post->post_excerpt ?: wp_trim_words( $post->post_content, 40, '…' ) ) ),
            'datePublished'    => mysql2date( 'c', $post->post_date_gmt, false ),
            'dateModified'     => mysql2date( 'c', $post->post_modified_gmt, false ),
            'author'           => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', $post->post_author ),
            ],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => $org_data['name'],
                'logo'  => $org_data['logo']
                    ? [ '@type' => 'ImageObject', 'url' => $org_data['logo'] ]
                    : null,
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post ),
            ],
            'image'            => $image,
        ];

        /**
         * Фильтр для кастомизации Article-схемы.
         *
         * @param array    $schema
         * @param \WP_Post $post
         */
        $schema = (array) apply_filters( 'schema_odinokov_article_schema', $schema, $post );

        $this->emit_jsonld( $schema );
    }

    /**
     * og:image для head.
     */
    public function output_og_image() {
        $url = $this->resolve_og_image_url();
        if ( ! $url ) {
            return;
        }
        echo '<meta property="og:image" content="' . esc_url( $url ) . '" />' . "\n";
        echo '<meta property="og:image:width" content="1200" />' . "\n";
        echo '<meta property="og:image:height" content="630" />' . "\n";
    }

    /**
     * Возвращает URL изображения для og:image с приоритетами:
     * 1) Карточка товара WC.
     * 2) Миниатюра записи (post thumbnail).
     * 3) Логотип сайта (Custom Logo).
     */
    private function resolve_og_image_url() {
        if ( is_singular( 'product' ) && $this->is_woocommerce_active() ) {
            global $post;
            if ( $post instanceof \WP_Post ) {
                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
                if ( $product ) {
                    $url = $this->get_product_image_url( $product );
                    if ( $url ) {
                        return $url;
                    }
                }
            }
        }

        if ( is_singular() ) {
            $post = get_queried_object();
            if ( $post instanceof \WP_Post ) {
                $url = $this->get_post_image_url( $post );
                if ( $url ) {
                    return $url;
                }
            }
        }

        return $this->get_site_logo_url();
    }

    /**
     * Главное изображение товара WC.
     */
    private function get_product_image_url( $product ) {
        $image_id = $product->get_image_id();
        if ( ! $image_id ) {
            $gallery = $product->get_gallery_image_ids();
            $image_id = ! empty( $gallery ) ? (int) $gallery[0] : 0;
        }
        if ( $image_id ) {
            $src = wp_get_attachment_image_src( $image_id, 'full' );
            if ( ! empty( $src[0] ) ) {
                return $src[0];
            }
        }
        return '';
    }

    /**
     * Миниатюра/первое изображение в посте.
     */
    private function get_post_image_url( \WP_Post $post ) {
        if ( has_post_thumbnail( $post ) ) {
            $src = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
            if ( ! empty( $src[0] ) ) {
                return $src[0];
            }
        }
        return '';
    }

    /**
     * Логотип сайта через Customizer.
     */
    private function get_site_logo_url() {
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $src = wp_get_attachment_image_src( $custom_logo_id, 'full' );
            if ( ! empty( $src[0] ) ) {
                return $src[0];
            }
        }
        return '';
    }

    /**
     * Данные организации из настроек (с fallback на значения WP).
     *
     * @return array{ name: string, url: string, logo: string, telephone: string, email: string }
     */
    private function get_organization_data() {
        $opt = get_option( 'schema_odinokov_organization', [] );

        $logo = '';
        if ( ! empty( $opt['logo_id'] ) ) {
            $src = wp_get_attachment_image_src( (int) $opt['logo_id'], 'full' );
            if ( ! empty( $src[0] ) ) {
                $logo = $src[0];
            }
        }

        return [
            'name'      => ! empty( $opt['name'] ) ? $opt['name'] : get_bloginfo( 'name' ),
            'url'       => ! empty( $opt['url'] ) ? $opt['url'] : home_url( '/' ),
            'logo'      => $logo,
            'telephone' => isset( $opt['telephone'] ) ? (string) $opt['telephone'] : '',
            'email'     => isset( $opt['email'] ) ? (string) $opt['email'] : '',
        ];
    }

    /**
     * JSON-LD WebSite + SearchAction (Sitelinks Search Box для Яндекса и Google).
     */
    private function render_website_schema() {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'WebSite',
            'name'        => get_bloginfo( 'name' ),
            'url'         => home_url( '/' ),
            'description' => get_bloginfo( 'description' ),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ],
        ];
        $this->emit_jsonld( $schema );
    }

    /**
     * JSON-LD BreadcrumbList — на всех страницах.
     * Яндексу критически важны хлебные крошки.
     */
    private function render_breadcrumb_schema() {
        $items = []; $pos = 1;

        $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => get_bloginfo( 'name' ), 'item' => home_url( '/' ) ];

        if ( is_singular( 'product' ) && $this->is_woocommerce_active() ) {
            $terms = wp_get_post_terms( get_the_ID(), 'product_cat', [ 'orderby' => 'term_id' ] );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $main = $terms[0];
                $ancestors = array_reverse( get_ancestors( $main->term_id, 'product_cat', 'taxonomy' ) );
                foreach ( $ancestors as $aid ) {
                    $a = get_term( $aid, 'product_cat' );
                    if ( $a && ! is_wp_error( $a ) ) $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $a->name, 'item' => get_term_link( $a ) ];
                }
                $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $main->name, 'item' => get_term_link( $main ) ];
            }
            $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title() ];
        } elseif ( is_singular( 'post' ) ) {
            $cats = get_the_category();
            if ( ! empty( $cats ) ) {
                $main = $cats[0];
                $ancestors = array_reverse( get_ancestors( $main->term_id, 'category', 'taxonomy' ) );
                foreach ( $ancestors as $aid ) {
                    $a = get_term( $aid, 'category' );
                    if ( $a && ! is_wp_error( $a ) ) $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $a->name, 'item' => get_term_link( $a ) ];
                }
                $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $main->name, 'item' => get_term_link( $main ) ];
            }
            $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title() ];
        } elseif ( is_category() || is_product_category() ) {
            $term = get_queried_object();
            if ( $term instanceof \WP_Term ) {
                $ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
                foreach ( $ancestors as $aid ) {
                    $a = get_term( $aid, $term->taxonomy );
                    if ( $a && ! is_wp_error( $a ) ) $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $a->name, 'item' => get_term_link( $a ) ];
                }
                $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $term->name, 'item' => get_term_link( $term ) ];
            }
        } elseif ( is_page() ) {
            $ancestors = get_post_ancestors( get_queried_object() );
            foreach ( array_reverse( $ancestors ) as $aid ) {
                $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title( $aid ), 'item' => get_permalink( $aid ) ];
            }
            $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title() ];
        }

        if ( count( $items ) < 2 ) return;
        $schema = [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ];
        $this->emit_jsonld( $schema );
    }

    /**
     * Безопасный вывод JSON-LD блока.
     */
    private function emit_jsonld( array $schema ) {
        // Удаляем пустые значения, чтобы не мусорить в выдаче.
        $schema = $this->array_filter_recursive( $schema );
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }

    private function array_filter_recursive( $value ) {
        if ( is_array( $value ) ) {
            $is_assoc = array_keys( $value ) !== range( 0, count( $value ) - 1 );
            $value = array_map( [ $this, 'array_filter_recursive' ], $value );
            if ( $is_assoc ) {
                $value = array_filter( $value, static function ( $v ) {
                    return '' !== $v && null !== $v && [] !== $v;
                } );
            } else {
                $value = array_values( array_filter( $value, static function ( $v ) {
                    return '' !== $v && null !== $v && [] !== $v;
                } ) );
            }
        }
        return $value;
    }
}
