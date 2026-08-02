<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSO_Sert_Request {

    public function __construct() {
        add_shortcode( 'sert-request', array( $this, 'render_shortcode' ) );
        add_action( 'wp_ajax_oso_sert_products', array( $this, 'ajax_load_products' ) );
        add_action( 'wp_ajax_nopriv_oso_sert_products', array( $this, 'ajax_load_products' ) );
        add_action( 'wp_ajax_oso_sert_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_nopriv_oso_sert_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_oso_sert_captcha', array( $this, 'ajax_refresh_captcha' ) );
        add_action( 'wp_ajax_nopriv_oso_sert_captcha', array( $this, 'ajax_refresh_captcha' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'sert-request' ) ) {
            return;
        }
        wp_enqueue_style(
            'oso-sert-request',
            OSO_URL . 'public/css/sert-request.css',
            array(),
            OSO_VERSION
        );
        wp_enqueue_script(
            'oso-sert-request',
            OSO_URL . 'public/js/sert-request.js',
            array(),
            OSO_VERSION,
            true
        );
        wp_localize_script( 'oso-sert-request', 'oso_sert', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'oso_sert_nonce' ),
            'strings'  => array(
                'select_category' => __( '— Выберите категорию —', 'order-share-odinokov' ),
                'select_product'  => __( '— Выберите товар —', 'order-share-odinokov' ),
                'loading'         => __( 'Загрузка...', 'order-share-odinokov' ),
                'required'        => __( 'Это поле обязательно', 'order-share-odinokov' ),
                'email'           => __( 'Введите корректный email', 'order-share-odinokov' ),
                'select_docs'     => __( 'Выберите хотя бы один тип документации', 'order-share-odinokov' ),
                'success'         => __( 'Заявка отправлена!', 'order-share-odinokov' ),
                'error'           => __( 'Ошибка отправки. Попробуйте позже.', 'order-share-odinokov' ),
            ),
        ) );
    }

    public function render_shortcode() {
        ob_start();
        include OSO_DIR . 'public/partials/sert-request-form.php';
        return ob_get_clean();
    }

    public function ajax_load_products() {
        check_ajax_referer( 'oso_sert_nonce', 'nonce' );

        $category_slug = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $search        = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $page          = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
        $per_page      = 100;

        if ( empty( $category_slug ) ) {
            wp_send_json_error( array( 'message' => 'No category' ) );
        }

        $term = get_term_by( 'slug', $category_slug, 'product_cat' );
        if ( ! $term ) {
            wp_send_json_error( array( 'message' => 'Category not found' ) );
        }

        $args = array(
            'status'         => 'publish',
            'category'       => array( $term->slug ),
            'limit'          => $per_page,
            'page'           => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'return'         => 'ids',
        );

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        $product_ids = wc_get_products( $args );
        $total_args  = $args;
        $total_args['limit']  = -1;
        $total_args['page']   = 1;
        $total_args['return'] = 'ids';
        $all_ids = wc_get_products( $total_args );
        $total = count( $all_ids );

        $products = array();
        foreach ( $product_ids as $pid ) {
            $p = wc_get_product( $pid );
            if ( $p ) {
                $products[] = array(
                    'id'   => $pid,
                    'name' => $p->get_name(),
                );
            }
        }

        wp_send_json_success( array(
            'products'   => $products,
            'total'      => $total,
            'has_more'   => ( $page * $per_page ) < $total,
            'page'       => $page,
        ) );
    }

    public function ajax_refresh_captcha() {
        check_ajax_referer( 'oso_sert_nonce', 'nonce' );
        $captcha = $this->generate_captcha();
        wp_send_json_success( $captcha );
    }

    public function handle_submit() {
        check_ajax_referer( 'oso_sert_nonce', 'nonce' );

        $errors = array();

        $category_slug = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $product_id    = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $inn           = isset( $_POST['inn'] ) ? sanitize_text_field( wp_unslash( $_POST['inn'] ) ) : '';
        $email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $other_text    = isset( $_POST['other_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['other_text'] ) ) : '';

        $doc_types = isset( $_POST['doc_types'] ) ? (array) $_POST['doc_types'] : array();
        $doc_types = array_map( 'sanitize_text_field', $doc_types );

        $captcha_answer = isset( $_POST['captcha_answer'] ) ? intval( $_POST['captcha_answer'] ) : 0;
        $captcha_key    = isset( $_POST['captcha_key'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_key'] ) ) : '';

        if ( empty( $name ) ) {
            $errors['name'] = __( 'Укажите, как к Вам обращаться', 'order-share-odinokov' );
        }
        if ( empty( $inn ) ) {
            $errors['inn'] = __( 'Укажите ИНН компании', 'order-share-odinokov' );
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            $errors['email'] = __( 'Введите корректный email', 'order-share-odinokov' );
        }
        if ( empty( $doc_types ) ) {
            $errors['docs'] = __( 'Выберите хотя бы один тип документации', 'order-share-odinokov' );
        }

        $captcha_data = get_transient( 'oso_sert_captcha_' . $captcha_key );
        if ( empty( $captcha_data ) || ! isset( $captcha_data['answer'] ) || $captcha_answer !== intval( $captcha_data['answer'] ) ) {
            $errors['captcha'] = __( 'Неверный ответ. Попробуйте снова.', 'order-share-odinokov' );
        }
        delete_transient( 'oso_sert_captcha_' . $captcha_key );

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'errors' => $errors ) );
        }

        $category_name = '';
        $term = get_term_by( 'slug', $category_slug, 'product_cat' );
        if ( $term ) {
            $category_name = $term->name;
        }

        $product_name = '';
        if ( $product_id ) {
            $p = wc_get_product( $product_id );
            if ( $p ) {
                $product_name = $p->get_name();
            }
        }

        $doc_labels = array(
            'bim'        => 'BIM-модель',
            'sert'       => 'Сертификаты',
            'passport'   => 'Паспорт',
            'datasheet'  => 'Технический лист (Data Sheet)',
            'catalog'    => 'Каталог продукции',
            'album_dwg'  => 'Альбом типовых решений DWG',
            'other'      => 'Другое',
        );

        $docs_list = array();
        foreach ( $doc_types as $dt ) {
            if ( isset( $doc_labels[ $dt ] ) ) {
                $docs_list[] = $doc_labels[ $dt ];
            }
        }

        $to = get_option( 'oso_email_to', get_option( 'admin_email' ) );
        if ( empty( $to ) ) {
            $to = get_option( 'admin_email' );
        }

        $subject = __( 'Запрос документации', 'order-share-odinokov' );
        if ( $product_name ) {
            $subject .= ': ' . $product_name;
        }

        $message  = __( 'Новый запрос документации с сайта', 'order-share-odinokov' ) . "\r\n\r\n";
        $message .= __( 'Категория:', 'order-share-odinokov' ) . ' ' . $category_name . "\r\n";
        $message .= __( 'Товар:', 'order-share-odinokov' ) . ' ' . $product_name . "\r\n";
        $message .= __( 'Запрошенные документы:', 'order-share-odinokov' ) . "\r\n";
        foreach ( $docs_list as $dl ) {
            $message .= '  - ' . $dl . "\r\n";
        }
        if ( in_array( 'other', $doc_types ) && $other_text ) {
            $message .= '  (' . __( 'Другое', 'order-share-odinokov' ) . ': ' . $other_text . ")\r\n";
        }
        $message .= "\r\n";
        $message .= __( 'Имя:', 'order-share-odinokov' ) . ' ' . $name . "\r\n";
        $message .= __( 'ИНН:', 'order-share-odinokov' ) . ' ' . $inn . "\r\n";
        $message .= __( 'Email:', 'order-share-odinokov' ) . ' ' . $email . "\r\n";
        if ( $phone ) {
            $message .= __( 'Телефон:', 'order-share-odinokov' ) . ' ' . $phone . "\r\n";
        }
        $message .= "\r\n" . __( '--- Отправлено с сайта ---', 'order-share-odinokov' );

        $headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( ! $sent ) {
            wp_send_json_error( array( 'errors' => array( 'general' => __( 'Ошибка при отправке письма. Попробуйте позже.', 'order-share-odinokov' ) ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Спасибо за Ваше обращение! Мы пришлем запрашиваемые Вами документы или свяжемся с Вами в ближайшее время.', 'order-share-odinokov' ) ) );
    }

    private function generate_captcha() {
        $num1 = wp_rand( 1, 10 );
        $num2 = wp_rand( 1, 10 );
        $key  = wp_rand( 100000, 999999 ) . '_' . time();
        $data = array(
            'num1'   => $num1,
            'num2'   => $num2,
            'answer' => $num1 + $num2,
            'time'   => time(),
        );
        set_transient( 'oso_sert_captcha_' . $key, $data, 5 * MINUTE_IN_SECONDS );
        return array(
            'question' => sprintf( __( '%1$d + %2$d = ?', 'order-share-odinokov' ), $num1, $num2 ),
            'key'      => $key,
        );
    }
}
