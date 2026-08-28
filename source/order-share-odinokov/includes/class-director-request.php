<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSO_Director_Request {

    const PAGE_SLUG = 'napisat-directoru';
    const DIRECTOR_EMAIL = 'odinokov.k@yandex.ru';

    public function __construct() {
        add_shortcode( 'director-request', array( $this, 'render_shortcode' ) );
        add_action( 'wp_ajax_oso_director_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_nopriv_oso_director_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_oso_director_captcha', array( $this, 'ajax_refresh_captcha' ) );
        add_action( 'wp_ajax_nopriv_oso_director_captcha', array( $this, 'ajax_refresh_captcha' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public static function ensure_page() {
        $page = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
        if ( $page ) {
            return $page->ID;
        }

        $page_id = wp_insert_post( array(
            'post_title'     => 'Написать директору',
            'post_name'      => self::PAGE_SLUG,
            'post_content'   => '[director-request]',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ) );

        return $page_id;
    }

    public function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'director-request' ) ) {
            return;
        }
        wp_enqueue_style(
            'oso-director-request',
            OSO_URL . 'public/css/director-request.css',
            array(),
            OSO_VERSION
        );
        wp_enqueue_script(
            'oso-director-request',
            OSO_URL . 'public/js/director-request.js',
            array(),
            OSO_VERSION,
            true
        );
        wp_localize_script( 'oso-director-request', 'oso_director', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'oso_director_nonce' ),
            'strings'  => array(
                'required' => __( 'Это поле обязательно', 'order-share-odinokov' ),
                'email'    => __( 'Введите корректный email', 'order-share-odinokov' ),
                'success'  => __( 'Сообщение отправлено!', 'order-share-odinokov' ),
                'error'    => __( 'Ошибка отправки. Попробуйте позже.', 'order-share-odinokov' ),
            ),
        ) );
    }

    public function render_shortcode() {
        ob_start();
        include OSO_DIR . 'public/partials/director-request-form.php';
        return ob_get_clean();
    }

    public function ajax_refresh_captcha() {
        check_ajax_referer( 'oso_director_nonce', 'nonce' );
        $captcha = $this->generate_captcha();
        wp_send_json_success( $captcha );
    }

    public function handle_submit() {
        check_ajax_referer( 'oso_director_nonce', 'nonce' );

        $errors = array();

        $fio           = isset( $_POST['fio'] ) ? sanitize_text_field( wp_unslash( $_POST['fio'] ) ) : '';
        $email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $message_text  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $captcha_answer = isset( $_POST['captcha_answer'] ) ? intval( $_POST['captcha_answer'] ) : 0;
        $captcha_key    = isset( $_POST['captcha_key'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_key'] ) ) : '';

        if ( empty( $message_text ) ) {
            $errors['message'] = __( 'Опишите проблему', 'order-share-odinokov' );
        }
        if ( ! empty( $email ) && ! is_email( $email ) ) {
            $errors['email'] = __( 'Введите корректный email', 'order-share-odinokov' );
        }

        $captcha_data = get_transient( 'oso_director_captcha_' . $captcha_key );
        if ( empty( $captcha_data ) || ! isset( $captcha_data['answer'] ) || $captcha_answer !== intval( $captcha_data['answer'] ) ) {
            $errors['captcha'] = __( 'Неверный ответ. Попробуйте снова.', 'order-share-odinokov' );
        }
        delete_transient( 'oso_director_captcha_' . $captcha_key );

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'errors' => $errors ) );
        }

        $to = self::DIRECTOR_EMAIL;

        $subject = __( 'Сообщение директору с сайта', 'order-share-odinokov' );

        $message  = __( 'Новое сообщение директору', 'order-share-odinokov' ) . "\r\n\r\n";
        if ( $fio ) {
            $message .= __( 'ФИО:', 'order-share-odinokov' ) . ' ' . $fio . "\r\n";
        }
        if ( $email ) {
            $message .= __( 'Email:', 'order-share-odinokov' ) . ' ' . $email . "\r\n";
        }
        if ( $phone ) {
            $message .= __( 'Телефон:', 'order-share-odinokov' ) . ' ' . $phone . "\r\n";
        }
        $message .= "\r\n" . __( 'Сообщение:', 'order-share-odinokov' ) . "\r\n" . $message_text . "\r\n";
        $message .= "\r\n" . __( '--- Отправлено с сайта ---', 'order-share-odinokov' );

        $from_email = get_option( 'oso_email_from', '' );
        if ( empty( $from_email ) || ! is_email( $from_email ) ) {
            $from_email = 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST );
        }
        $from_name = get_bloginfo( 'name' );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );
        if ( $email ) {
            $headers[] = 'Reply-To: ' . $email;
        }

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( ! $sent ) {
            wp_send_json_error( array( 'errors' => array( 'general' => __( 'Ошибка при отправке письма. Попробуйте позже.', 'order-share-odinokov' ) ) ) );
        }

        if ( $email ) {
            $this->send_reply_email( $email, $fio );
        }

        wp_send_json_success( array( 'message' => __( 'Спасибо! Ваше сообщение отправлено директору.', 'order-share-odinokov' ) ) );
    }

    private function send_reply_email( $email, $fio ) {
        $from_email = get_option( 'oso_email_from', '' );
        if ( empty( $from_email ) || ! is_email( $from_email ) ) {
            $from_email = 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST );
        }
        $from_name = get_bloginfo( 'name' );

        $reply_to = get_option( 'oso_email_to', get_option( 'admin_email' ) );
        if ( empty( $reply_to ) || ! is_email( $reply_to ) ) {
            $reply_to = get_option( 'admin_email' );
        }

        $subject = __( 'Ваше обращение получено', 'order-share-odinokov' );

        $page_url = home_url( '/' . self::PAGE_SLUG . '/' );

        $body  = '<html><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.6;">';
        $body .= '<p>' . esc_html__( 'Добрый день!', 'order-share-odinokov' ) . '</p>';
        $body .= '<p>' . esc_html__( 'Мы получили Вашу заявку, спасибо! Мы ценим Ваше доверие и сделаем всё чтобы его оправдать!', 'order-share-odinokov' ) . '</p>';
        $body .= '<p>' . esc_html__( 'Мы работаем с понедельника по пятницу с 9:00 до 18:00 в МСК. Заявки, полученные в выходные дни или нерабочее время будут обработаны в первые рабочие часы следующего рабочего дня!', 'order-share-odinokov' ) . '</p>';
        $body .= '<p>' . esc_html__( 'Отвечаем на заявки в рабочее время в течении часа.', 'order-share-odinokov' ) . '</p>';
        $body .= '<p style="font-size:16px;"><b>' . esc_html__( 'Не получили ответ? Слишком долго ждать? Не понравилось обслуживание? Есть предложение по работе компании?', 'order-share-odinokov' ) . ' <a href="' . esc_url( $page_url ) . '">' . esc_html__( 'Напишите директору', 'order-share-odinokov' ) . '</a></b></p>';
        $body .= '</body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $reply_to,
        );

        wp_mail( $email, $subject, $body, $headers );
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
        set_transient( 'oso_director_captcha_' . $key, $data, 5 * MINUTE_IN_SECONDS );
        return array(
            'question' => sprintf( __( '%1$d + %2$d = ?', 'order-share-odinokov' ), $num1, $num2 ),
            'key'      => $key,
        );
    }
}
