<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSO_Order {

    private static $is_order_screen = null;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 5 );
        add_action( 'wp_ajax_oso_order_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_nopriv_oso_order_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_oso_refresh_captcha', array( $this, 'ajax_refresh_captcha' ) );
        add_action( 'wp_ajax_nopriv_oso_refresh_captcha', array( $this, 'ajax_refresh_captcha' ) );

        add_action( 'wp', array( $this, 'maybe_disable_add_to_cart' ), 99 );
        add_action( 'template_redirect', array( $this, 'cache_screen' ), 1 );

        add_filter( 'woocommerce_get_price_html', array( $this, 'append_button_to_price' ), 20, 2 );
        add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_button_fallback' ), 20 );
        add_action( 'wp_footer', array( $this, 'render_popup' ), 1 );
    }

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'oso_orders';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_name VARCHAR(255) NOT NULL DEFAULT '',
            inn VARCHAR(64) NOT NULL DEFAULT '',
            name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            accessories TEXT NULL,
            delivery VARCHAR(8) NOT NULL DEFAULT 'no',
            delivery_address TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY created_at (created_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private function save_order( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oso_orders';
        $wpdb->insert(
            $table,
            array(
                'product_name'     => $data['product_name'],
                'inn'              => $data['inn'],
                'name'             => $data['name'],
                'email'            => $data['email'],
                'accessories'      => $data['accessories'],
                'delivery'         => $data['delivery'],
                'delivery_address' => $data['delivery_address'],
                'created_at'       => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    public function cache_screen() {
        self::$is_order_screen = is_shop() || is_product_taxonomy() || ( function_exists( 'is_product' ) && is_product() );
    }

    private function is_order_screen() {
        if ( null !== self::$is_order_screen ) {
            return self::$is_order_screen;
        }
        return is_shop() || is_product_taxonomy() || ( function_exists( 'is_product' ) && is_product() );
    }

    public function enqueue_assets() {
        if ( ! $this->is_order_screen() ) {
            return;
        }
        $s = oso_get_settings();

        wp_enqueue_style(
            'oso-public',
            OSO_URL . 'public/css/order-share-public.css',
            array(),
            OSO_VERSION
        );

        if ( ! empty( $s['custom_fonts'] ) ) {
            $families = array();
            foreach ( explode( '|', $s['custom_fonts'] ) as $f ) {
                $f = trim( $f );
                if ( '' !== $f ) {
                    $families[] = str_replace( '+', ' ', $f ) . ':wght@300;400;500;600;700;800';
                }
            }
            if ( ! empty( $families ) ) {
                $url = add_query_arg(
                    array(
                        'family'  => rawurlencode( implode( '&family=', $families ) ),
                        'display' => 'swap',
                    ),
                    'https://fonts.googleapis.com/css2'
                );
                wp_enqueue_style( 'oso-google-fonts', esc_url( $url ), array(), null );
            }
        }

        wp_enqueue_script(
            'oso-public',
            OSO_URL . 'public/js/order-share-public.js',
            array(),
            OSO_VERSION,
            true
        );

        wp_localize_script( 'oso-public', 'oso_order', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'oso_order_nonce' ),
            'strings'  => array(
                'required' => __( 'Это поле обязательно', 'order-share-odinokov' ),
                'email'    => __( 'Введите корректный email', 'order-share-odinokov' ),
                'filesize' => __( 'Файл слишком большой. Максимум 20 МБ.', 'order-share-odinokov' ),
                'filetype' => __( 'Недопустимый формат файла.', 'order-share-odinokov' ),
                'maxfiles' => __( 'Максимум 3 файла.', 'order-share-odinokov' ),
                'success'  => __( 'Заявка отправлена!', 'order-share-odinokov' ),
                'error'    => __( 'Ошибка отправки. Попробуйте позже.', 'order-share-odinokov' ),
            ),
        ) );
    }

    public function maybe_disable_add_to_cart() {
        $s = oso_get_settings();
        if ( empty( $s['disable_add_to_cart'] ) ) {
            return;
        }
        if ( isset( $_GET['add-to-cart'] ) ) {
            wp_safe_redirect( remove_query_arg( 'add-to-cart' ) );
            exit;
        }
        add_action( 'wp_loaded', array( $this, 'block_wc_add_to_cart_ajax' ), 1 );
        add_filter( 'woocommerce_is_purchasable', '__return_false', 999 );
        add_filter( 'woocommerce_variation_is_purchasable', '__return_false', 999 );
        add_action( 'wp_head', array( $this, 'hide_add_to_cart_css' ) );
        add_action( 'woocommerce_after_shop_loop_item', array( $this, 'remove_loop_add_to_cart' ), 1 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'remove_single_add_to_cart' ), 1 );
    }

    public function block_wc_add_to_cart_ajax() {
        remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );
        remove_action( 'wc_ajax_add_to_cart', 'wc_ajax_add_to_cart' );
        add_action( 'wc_ajax_add_to_cart', array( $this, 'ajax_block_add_to_cart' ) );
    }

    public function ajax_block_add_to_cart() {
        wp_send_json_error( array( 'error' => __( 'Добавление в корзину отключено.', 'order-share-odinokov' ) ) );
    }

    public function hide_add_to_cart_css() {
        echo '<style>.add_to_cart_button, .single_add_to_cart_button, .product_type_simple.add_to_cart_button { display:none !important; }</style>' . "\n";
    }

    public function remove_loop_add_to_cart() {
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
    }

    public function remove_single_add_to_cart() {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
    }

    public function append_button_to_price( $price_html, $product ) {
        if ( ! $product instanceof WC_Product ) {
            return $price_html;
        }
        if ( is_product() ) {
            return $price_html;
        }
        $s = oso_get_settings();
        if ( empty( $s['enable_order_category'] ) ) {
            return $price_html;
        }
        if ( ! $this->is_order_screen() ) {
            return $price_html;
        }
        $this->mark_button_rendered( $product->get_id() );
        ob_start();
        $this->print_button( $product->get_id(), $product->get_name() );
        $button = ob_get_clean();
        return $price_html . '<div class="oso-order-btn-wrap">' . $button . '</div>';
    }

    public function render_button_fallback() {
        global $product;
        if ( ! $product instanceof WC_Product ) {
            return;
        }
        if ( $this->is_button_rendered( $product->get_id() ) ) {
            return;
        }
        $s = oso_get_settings();
        if ( empty( $s['enable_order_category'] ) ) {
            return;
        }
        if ( ! $this->is_order_screen() ) {
            return;
        }
        $this->print_button( $product->get_id(), $product->get_name() );
    }

    private static $rendered_ids = array();

    private function mark_button_rendered( $id ) {
        self::$rendered_ids[ $id ] = true;
    }

    private function is_button_rendered( $id ) {
        return isset( self::$rendered_ids[ $id ] );
    }

    public function render_button() {
        $s = oso_get_settings();
        $is_single = ( function_exists( 'is_product' ) && is_product() );
        if ( $is_single ) {
            if ( empty( $s['enable_order_product'] ) ) {
                return;
            }
        } else {
            if ( empty( $s['enable_order_category'] ) ) {
                return;
            }
        }
        global $product;
        if ( ! $product instanceof WC_Product ) {
            return;
        }
        $this->print_button( $product->get_id(), $product->get_name() );
    }

    private function print_button( $product_id, $product_name ) {
        $s         = oso_get_settings();
        $style     = oso_build_button_style( $s );
        $btn_text  = $s['order_text'];
        $is_single = ( function_exists( 'is_product' ) && is_product() );
        $icon_size = $is_single ? (int) $s['icon_size_product'] : (int) $s['icon_size_category'];
        $icon_html = oso_render_icon( $s, 'order', $icon_size );

        echo '<a href="javascript:void(0)" class="oso-order-btn oso-btn" data-product-id="' . esc_attr( $product_id ) . '" data-product-name="' . esc_attr( $product_name ) . '" style="' . esc_attr( $style ) . '">';
        echo '<span class="oso-btn-ico" aria-hidden="true" style="width:' . $icon_size . 'px;height:' . $icon_size . 'px;max-width:' . $icon_size . 'px;max-height:' . $icon_size . 'px;">' . $icon_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="oso-btn-txt">' . esc_html( $btn_text ) . '</span>';
        echo '</a>';
    }

    public function render_popup() {
        if ( ! $this->is_order_screen() ) {
            return;
        }
        $s = oso_get_settings();
        if ( ! $s['enable_order_product'] && ! $s['enable_order_category'] ) {
            return;
        }
        include OSO_DIR . 'public/partials/popup-form.php';
    }

    public function generate_captcha() {
        $num1 = wp_rand( 1, 10 );
        $num2 = wp_rand( 1, 10 );
        $key  = wp_rand( 100000, 999999 ) . '_' . time();
        $data = array(
            'num1'   => $num1,
            'num2'   => $num2,
            'answer' => $num1 + $num2,
            'time'   => time(),
        );
        set_transient( 'oso_captcha_' . $key, $data, 5 * MINUTE_IN_SECONDS );
        return array(
            'question' => sprintf( __( '%1$d + %2$d = ?', 'order-share-odinokov' ), $num1, $num2 ),
            'key'      => $key,
        );
    }

    public function ajax_refresh_captcha() {
        check_ajax_referer( 'oso_order_nonce', 'nonce' );
        $captcha = $this->generate_captcha();
        wp_send_json_success( $captcha );
    }

    public function handle_submit() {
        check_ajax_referer( 'oso_order_nonce', 'nonce' );

        $errors = array();

        $product_name   = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
        $inn            = isset( $_POST['inn'] ) ? sanitize_text_field( wp_unslash( $_POST['inn'] ) ) : '';
        $name           = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $accessories    = isset( $_POST['accessories'] ) ? sanitize_textarea_field( wp_unslash( $_POST['accessories'] ) ) : '';
        $delivery       = isset( $_POST['delivery'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery'] ) ) : 'no';
        $delivery_addr  = isset( $_POST['delivery_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['delivery_address'] ) ) : '';
        $captcha_answer = isset( $_POST['captcha_answer'] ) ? intval( $_POST['captcha_answer'] ) : 0;
        $captcha_key    = isset( $_POST['captcha_key'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_key'] ) ) : '';

        $s = oso_get_settings();

        if ( ! empty( $s['field_name'] ) && empty( $name ) ) {
            $errors['name'] = __( 'Укажите, как к Вам обращаться', 'order-share-odinokov' );
        }
        if ( ! empty( $s['field_email'] ) && ( empty( $email ) || ! is_email( $email ) ) ) {
            $errors['email'] = __( 'Введите корректный email', 'order-share-odinokov' );
        }
        if ( ! empty( $s['field_captcha'] ) ) {
            $captcha_data = get_transient( 'oso_captcha_' . $captcha_key );
            if ( empty( $captcha_data ) || ! isset( $captcha_data['answer'] ) || $captcha_answer !== intval( $captcha_data['answer'] ) ) {
                $errors['captcha'] = __( 'Неверный ответ. Попробуйте снова.', 'order-share-odinokov' );
            }
            delete_transient( 'oso_captcha_' . $captcha_key );
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'errors' => $errors ) );
        }

        $to = get_option( 'oso_email_to', get_option( 'admin_email' ) );
        if ( empty( $to ) ) {
            $to = get_option( 'admin_email' );
        }

        $from_email = get_option( 'oso_email_from', '' );
        if ( empty( $from_email ) || ! is_email( $from_email ) ) {
            $from_email = 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST );
        }
        $from_name = get_bloginfo( 'name' );

        $subject = sprintf( __( 'Новая заявка: %s', 'order-share-odinokov' ), $product_name );

        $message  = __( 'Новая заявка на сайте', 'order-share-odinokov' ) . "\r\n\r\n";
        $message .= __( 'Материал:', 'order-share-odinokov' ) . ' ' . $product_name . "\r\n";
        if ( ! empty( $s['field_inn'] ) ) {
            $message .= __( 'ИНН:', 'order-share-odinokov' ) . ' ' . ( $inn ?: __( 'Физическое лицо', 'order-share-odinokov' ) ) . "\r\n";
        }
        $message .= __( 'Имя:', 'order-share-odinokov' ) . ' ' . $name . "\r\n";
        $message .= __( 'Email:', 'order-share-odinokov' ) . ' ' . $email . "\r\n";
        if ( ! empty( $s['field_accessories'] ) && $accessories ) {
            $message .= __( 'Комплектующие:', 'order-share-odinokov' ) . "\r\n" . $accessories . "\r\n";
        }
        if ( ! empty( $s['field_delivery'] ) ) {
            $message .= __( 'Доставка:', 'order-share-odinokov' ) . ' ' . ( 'yes' === $delivery ? __( 'Да', 'order-share-odinokov' ) : __( 'Нет', 'order-share-odinokov' ) ) . "\r\n";
            if ( 'yes' === $delivery && $delivery_addr ) {
                $message .= __( 'Адрес доставки:', 'order-share-odinokov' ) . "\r\n" . $delivery_addr . "\r\n";
            }
        }
        $message .= "\r\n" . __( '--- Отправлено с сайта ---', 'order-share-odinokov' );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $email,
            'From: ' . $from_name . ' <' . $from_email . '>',
        );

        $attachments = array();
        if ( ! empty( $s['field_files'] ) && ! empty( $_FILES['files'] ) ) {
            $files       = $_FILES['files'];
            $allowed_ext = array( 'jpg', 'jpeg', 'pdf', 'dwg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv' );
            $max_size    = 20 * 1024 * 1024;

            $upload_dir = wp_upload_dir();
            $tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'oso-order-tmp/';
            if ( ! file_exists( $tmp_dir ) ) {
                wp_mkdir_p( $tmp_dir );
                file_put_contents( $tmp_dir . 'index.php', '<?php // Silence is golden.' );
                file_put_contents( $tmp_dir . '.htaccess', 'Deny from all' );
            }

            if ( is_array( $files['name'] ) ) {
                $count = count( $files['name'] );
                for ( $i = 0; $i < $count; $i++ ) {
                    if ( $i >= 3 ) {
                        break;
                    }
                    if ( empty( $files['name'][ $i ] ) ) {
                        continue;
                    }
                    if ( $files['size'][ $i ] > $max_size ) {
                        continue;
                    }
                    $ext = strtolower( pathinfo( $files['name'][ $i ], PATHINFO_EXTENSION ) );
                    if ( ! in_array( $ext, $allowed_ext, true ) ) {
                        continue;
                    }
                    $tmp = $files['tmp_name'][ $i ];
                    if ( is_uploaded_file( $tmp ) ) {
                        $safe_name = sanitize_file_name( $files['name'][ $i ] );
                        $dest      = $tmp_dir . time() . '_' . $i . '_' . $safe_name;
                        if ( move_uploaded_file( $tmp, $dest ) ) {
                            $attachments[] = $dest;
                        }
                    }
                }
            }
        }

        $sent = wp_mail( $to, $subject, $message, $headers, $attachments );

        foreach ( $attachments as $file ) {
            if ( file_exists( $file ) ) {
                @unlink( $file );
            }
        }

        $this->save_order( array(
            'product_name'     => $product_name,
            'inn'              => $inn,
            'name'             => $name,
            'email'            => $email,
            'accessories'      => $accessories,
            'delivery'         => $delivery,
            'delivery_address' => $delivery_addr,
        ) );

        $client_sent = $this->send_client_email( $email, $name, $product_name );

        oso_log_mail( $to, $subject, $sent, $product_name );

        if ( ! $sent ) {
            wp_send_json_error( array( 'errors' => array( 'general' => __( 'Ошибка при отправке письма. Попробуйте позже.', 'order-share-odinokov' ) ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Заявка отправлена! Мы свяжемся с Вами в ближайшее время.', 'order-share-odinokov' ) ) );
    }

    private function send_client_email( $email, $name, $product_name ) {
        $s = oso_get_settings();
        if ( empty( $s['client_email_enabled'] ) ) {
            return false;
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            return false;
        }

        $from_email = get_option( 'oso_email_from', '' );
        if ( empty( $from_email ) || ! is_email( $from_email ) ) {
            $from_email = 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST );
        }
        $from_name = get_bloginfo( 'name' );

        $reply_to = get_option( 'oso_email_to', get_option( 'admin_email' ) );
        if ( empty( $reply_to ) || ! is_email( $reply_to ) ) {
            $reply_to = get_option( 'admin_email' );
        }

        $subject = $s['client_email_subject'];

        $message_text = $s['client_email_message'];
        $message_text = str_replace( '{name}', esc_html( $name ), $message_text );
        $message_text = str_replace( '{product}', esc_html( $product_name ), $message_text );
        $message_text = str_replace( '{director_url}', esc_url( home_url( '/napisat-directoru/' ) ), $message_text );

        $signature = $s['client_email_signature'];

        $body  = '<html><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.6;">';
        $body .= $message_text;
        if ( '' !== $signature ) {
            $body .= '<div style="margin-top:20px;padding-top:15px;border-top:1px solid #eee;">' . $signature . '</div>';
        }
        $body .= '</body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $reply_to,
        );

        return wp_mail( $email, $subject, $body, $headers );
    }
}
