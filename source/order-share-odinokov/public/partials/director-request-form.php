<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="oso-director-wrap">
    <h3 class="oso-director-title"><?php esc_html_e( 'Написать директору', 'order-share-odinokov' ); ?></h3>

    <form id="oso-director-form" method="post">
        <div class="oso-director-field">
            <label for="oso-director-fio"><?php esc_html_e( 'ФИО', 'order-share-odinokov' ); ?></label>
            <input type="text" id="oso-director-fio" name="fio" placeholder="<?php esc_attr_e( 'Не обязательно', 'order-share-odinokov' ); ?>">
        </div>

        <div class="oso-director-field">
            <label for="oso-director-email"><?php esc_html_e( 'Email для ответа', 'order-share-odinokov' ); ?></label>
            <input type="email" id="oso-director-email" name="email" placeholder="<?php esc_attr_e( 'Не обязательно', 'order-share-odinokov' ); ?>">
        </div>

        <div class="oso-director-field">
            <label for="oso-director-phone"><?php esc_html_e( 'Телефон', 'order-share-odinokov' ); ?></label>
            <input type="tel" id="oso-director-phone" name="phone" placeholder="<?php esc_attr_e( 'Не обязательно', 'order-share-odinokov' ); ?>">
        </div>

        <div class="oso-director-field">
            <label for="oso-director-message"><?php esc_html_e( 'Опишите проблему', 'order-share-odinokov' ); ?> <span class="oso-required">*</span></label>
            <p class="oso-director-hint"><?php esc_html_e( 'Подробно опишите проблему, с которой Вы столкнулись', 'order-share-odinokov' ); ?></p>
            <textarea id="oso-director-message" name="message" rows="6" required></textarea>
        </div>

        <div class="oso-director-captcha-row">
            <span class="oso-director-captcha-question"></span>
            <a href="#" class="oso-director-captcha-refresh"><?php esc_html_e( 'Обновить', 'order-share-odinokov' ); ?></a>
            <input type="number" id="oso-director-captcha-answer" placeholder="?" required>
            <input type="hidden" id="oso-director-captcha-key" value="">
        </div>

        <button type="submit" class="oso-director-submit"><?php esc_html_e( 'Отправить', 'order-share-odinokov' ); ?></button>
        <div class="oso-director-messages"></div>
    </form>
</div>
