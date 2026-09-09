<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSOE_Main {

    private static $block_done = false;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_block' ), 6 );
        add_action( 'porto_woocommerce_share', array( $this, 'render_block' ), 60 );
        add_action( 'wp_footer', array( $this, 'render_popup' ), 1 );

        add_action( 'wp_ajax_osoe_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_nopriv_osoe_submit', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_osoe_captcha', array( $this, 'ajax_refresh_captcha' ) );
        add_action( 'wp_ajax_nopriv_osoe_captcha', array( $this, 'ajax_refresh_captcha' ) );
    }

    private function is_product_screen() {
        return ( function_exists( 'is_product' ) && is_product() );
    }

    public function enqueue_assets() {
        if ( ! $this->is_product_screen() ) {
            return;
        }
        wp_enqueue_style(
            'osoe-public',
            OSOE_URL . 'public/css/osoe-public.css',
            array(),
            OSOE_VERSION
        );
        wp_enqueue_script(
            'osoe-public',
            OSOE_URL . 'public/js/osoe-public.js',
            array(),
            OSOE_VERSION,
            true
        );
        wp_localize_script( 'osoe-public', 'osoe', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'osoe_nonce' ),
            'strings'  => array(
                'required' => __( 'Это поле обязательно', 'order-share-extended' ),
                'email'    => __( 'Введите корректный email', 'order-share-extended' ),
                'success'  => __( 'Заявка отправлена!', 'order-share-extended' ),
                'error'    => __( 'Ошибка отправки. Попробуйте позже.', 'order-share-extended' ),
            ),
        ) );
    }

    public function render_block() {
        if ( ! $this->is_product_screen() ) {
            return;
        }
        if ( self::$block_done ) {
            return;
        }
        self::$block_done = true;
        $s = osoe_get_settings();

        $has_btn = ! empty( $s['btn1_enabled'] ) || ! empty( $s['btn2_enabled'] ) || ! empty( $s['btn3_enabled'] );
        if ( ! $has_btn ) {
            return;
        }

        $base     = osoe_get_base_style();
        $btn_fs   = max( 8, (int) round( (int) $base['font_size'] * (int) $s['btn_font_ratio'] / 100 ) );
        $cap_fs   = max( 6, (int) round( $btn_fs * (int) $s['caption_font_ratio'] / 100 ) );
        $title_fs = max( 8, (int) round( (int) $base['font_size'] * (int) $s['title_font_ratio'] / 100 ) );

        $font_family_css = '';
        if ( 'inherit' !== $base['font_family'] && '' !== $base['font_family'] ) {
            $font_family_css = 'font-family:' . $base['font_family'] . ',sans-serif;';
        }

        $upper = ! empty( $base['uppercase'] ) ? 'uppercase' : 'none';
        $border = (int) $base['border_width'] > 0 ? (int) $base['border_width'] . 'px solid ' . $base['border_color'] : 'none';

        $block_border = (int) $s['block_border_width'] > 0 ? (int) $s['block_border_width'] . 'px solid ' . $s['block_border_color'] : 'none';

        global $product;
        $material = '';
        if ( $product instanceof WC_Product ) {
            $material = $product->get_name();
        }
        if ( '' === $material ) {
            $material = wp_get_document_title();
        }

        $buttons = array(
            'btn1' => array( 'type' => 'sample', 'enabled' => $s['btn1_enabled'], 'label' => $s['btn1_label'], 'caption' => $s['btn1_caption'], 'icon' => $s['btn1_icon'] ),
            'btn2' => array( 'type' => 'test',   'enabled' => $s['btn2_enabled'], 'label' => $s['btn2_label'], 'caption' => $s['btn2_caption'], 'icon' => $s['btn2_icon'] ),
            'btn3' => array( 'type' => 'visit',  'enabled' => $s['btn3_enabled'], 'label' => $s['btn3_label'], 'caption' => $s['btn3_caption'], 'icon' => $s['btn3_icon'] ),
        );

        $show_icons = ! empty( $s['show_icons'] );
        $layout     = ( 'row' === $s['layout'] ) ? 'row' : 'column';

        $btn_style = 'display:inline-flex;align-items:center;justify-content:center;gap:8px;background:' . $base['bg_color'] . ';color:' . $base['text_color'] . ';border:' . $border . ';border-radius:' . (int) $base['border_radius'] . 'px;padding:' . max(4,(int) round((int)$base['padding_v']*0.8)) . 'px ' . max(6,(int) round((int)$base['padding_h']*0.8)) . 'px;font-size:' . $btn_fs . 'px;font-weight:' . (int) $base['font_weight'] . ';text-transform:' . $upper . ';line-height:1.2;text-decoration:none;cursor:pointer;' . $font_family_css;

        ?>
        <div class="osoe-block osoe-layout-<?php echo esc_attr( $layout ); ?>" data-material="<?php echo esc_attr( $material ); ?>" style="background:<?php echo esc_attr( $s['block_bg'] ); ?>;border:<?php echo esc_attr( $block_border ); ?>;border-radius:<?php echo esc_attr( (int) $s['block_border_radius'] ); ?>px;padding:<?php echo esc_attr( (int) $s['block_padding'] ); ?>px;<?php echo $font_family_css; ?>">
            <div class="osoe-block-title" style="font-size:<?php echo esc_attr( $title_fs ); ?>px;font-weight:700;text-align:center;margin-bottom:16px;"><?php echo esc_html( $s['block_title'] ); ?></div>
            <div class="osoe-block-buttons">
                <?php foreach ( $buttons as $b ) : ?>
                    <?php if ( empty( $b['enabled'] ) ) { continue; } ?>
                    <div class="osoe-btn-item">
                        <a href="javascript:void(0)" class="osoe-btn" data-type="<?php echo esc_attr( $b['type'] ); ?>" data-label="<?php echo esc_attr( $b['label'] ); ?>" style="<?php echo esc_attr( $btn_style ); ?>">
                            <?php if ( $show_icons && '' !== $b['icon'] ) : ?>
                                <span class="osoe-btn-ico" aria-hidden="true"><i class="<?php echo esc_attr( $b['icon'] ); ?>"></i></span>
                            <?php endif; ?>
                            <span class="osoe-btn-txt"><?php echo esc_html( $b['label'] ); ?></span>
                        </a>
                        <?php if ( '' !== $b['caption'] ) : ?>
                            <div class="osoe-btn-caption" style="font-size:<?php echo esc_attr( $cap_fs ); ?>px;line-height:1.4;color:#666;text-align:center;margin-top:6px;"><?php echo esc_html( $b['caption'] ); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function render_popup() {
        if ( ! $this->is_product_screen() ) {
            return;
        }
        include OSOE_DIR . 'public/partials/popup-form.php';
    }

    public function ajax_refresh_captcha() {
        check_ajax_referer( 'osoe_nonce', 'nonce' );
        $captcha = $this->generate_captcha();
        wp_send_json_success( $captcha );
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
        set_transient( 'osoe_captcha_' . $key, $data, 5 * MINUTE_IN_SECONDS );
        return array(
            'question' => sprintf( __( '%1$d + %2$d = ?', 'order-share-extended' ), $num1, $num2 ),
            'key'      => $key,
        );
    }

    public function handle_submit() {
        check_ajax_referer( 'osoe_nonce', 'nonce' );

        $errors = array();

        $type           = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $material       = isset( $_POST['material'] ) ? sanitize_text_field( wp_unslash( $_POST['material'] ) ) : '';
        $address        = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
        $name           = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $comment        = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
        $captcha_answer = isset( $_POST['captcha_answer'] ) ? intval( $_POST['captcha_answer'] ) : 0;
        $captcha_key    = isset( $_POST['captcha_key'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_key'] ) ) : '';

        if ( empty( $address ) ) {
            $errors['address'] = __( 'Укажите адрес объекта', 'order-share-extended' );
        }
        if ( empty( $name ) ) {
            $errors['name'] = __( 'Укажите, как к Вам обращаться', 'order-share-extended' );
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            $errors['email'] = __( 'Введите корректный email', 'order-share-extended' );
        }
        if ( empty( $phone ) ) {
            $errors['phone'] = __( 'Укажите телефон', 'order-share-extended' );
        }

        $captcha_data = get_transient( 'osoe_captcha_' . $captcha_key );
        if ( empty( $captcha_data ) || ! isset( $captcha_data['answer'] ) || $captcha_answer !== intval( $captcha_data['answer'] ) ) {
            $errors['captcha'] = __( 'Неверный ответ. Попробуйте снова.', 'order-share-extended' );
        }
        delete_transient( 'osoe_captcha_' . $captcha_key );

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'errors' => $errors ) );
        }

        $type_labels = array(
            'sample' => __( 'Заказать образец', 'order-share-extended' ),
            'test'   => __( 'Провести испытания', 'order-share-extended' ),
            'visit'  => __( 'Заказать выезд на объект', 'order-share-extended' ),
        );
        $type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type;

        $to = osoe_get_email_to();

        $from_email = get_option( 'oso_email_from', '' );
        if ( empty( $from_email ) || ! is_email( $from_email ) ) {
            $from_email = 'no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST );
        }
        $from_name = get_bloginfo( 'name' );

        $subject = sprintf( __( '%s: %s', 'order-share-extended' ), $type_label, $material );

        $message  = __( 'Новая заявка на сайте', 'order-share-extended' ) . "\r\n\r\n";
        $message .= __( 'Тип запроса:', 'order-share-extended' ) . ' ' . $type_label . "\r\n";
        $message .= __( 'Наименование материала:', 'order-share-extended' ) . ' ' . $material . "\r\n";
        $message .= __( 'Адрес объекта:', 'order-share-extended' ) . ' ' . $address . "\r\n";
        $message .= __( 'Имя:', 'order-share-extended' ) . ' ' . $name . "\r\n";
        $message .= __( 'Email:', 'order-share-extended' ) . ' ' . $email . "\r\n";
        $message .= __( 'Телефон:', 'order-share-extended' ) . ' ' . $phone . "\r\n";
        if ( $comment ) {
            $message .= __( 'Комментарий:', 'order-share-extended' ) . "\r\n" . $comment . "\r\n";
        }
        $message .= "\r\n" . __( '--- Отправлено с сайта ---', 'order-share-extended' );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $email,
            'From: ' . $from_name . ' <' . $from_email . '>',
        );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( ! $sent ) {
            wp_send_json_error( array( 'errors' => array( 'general' => __( 'Ошибка при отправке письма. Попробуйте позже.', 'order-share-extended' ) ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Заявка отправлена! Мы свяжемся с Вами в ближайшее время.', 'order-share-extended' ) ) );
    }
}
