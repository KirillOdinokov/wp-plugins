<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="osoe-modal" class="osoe-modal" style="display:none;">
    <div class="osoe-modal-content">
        <button class="osoe-modal-close" type="button" aria-label="<?php esc_attr_e( 'Закрыть', 'order-share-extended' ); ?>">&times;</button>
        <h3 class="osoe-modal-title"></h3>

        <form id="osoe-form" method="post">
            <input type="hidden" name="type" id="osoe-type" value="">
            <input type="hidden" name="captcha_key" id="osoe-captcha-key" value="">

            <div class="osoe-form-group">
                <label for="osoe-material"><?php esc_html_e( 'Наименование материала', 'order-share-extended' ); ?></label>
                <input type="text" name="material" id="osoe-material" value="">
            </div>

            <div class="osoe-form-group">
                <label for="osoe-address"><?php esc_html_e( 'Адрес объекта', 'order-share-extended' ); ?> <span class="osoe-required">*</span></label>
                <input type="text" name="address" id="osoe-address" required>
            </div>

            <div class="osoe-form-group">
                <label for="osoe-name"><?php esc_html_e( 'ФИО, как к Вам обращаться', 'order-share-extended' ); ?> <span class="osoe-required">*</span></label>
                <input type="text" name="name" id="osoe-name" required>
            </div>

            <div class="osoe-form-group">
                <label for="osoe-email"><?php esc_html_e( 'Email для ответа', 'order-share-extended' ); ?> <span class="osoe-required">*</span></label>
                <input type="email" name="email" id="osoe-email" required>
            </div>

            <div class="osoe-form-group">
                <label for="osoe-phone"><?php esc_html_e( 'Телефон', 'order-share-extended' ); ?> <span class="osoe-required">*</span></label>
                <input type="tel" name="phone" id="osoe-phone" required>
            </div>

            <div class="osoe-form-group">
                <label for="osoe-comment"><?php esc_html_e( 'Развернутый комментарий', 'order-share-extended' ); ?></label>
                <textarea name="comment" id="osoe-comment" rows="5"></textarea>
            </div>

            <div class="osoe-form-group">
                <label for="osoe-captcha"><?php esc_html_e( 'Капча', 'order-share-extended' ); ?></label>
                <div class="osoe-captcha-row">
                    <div class="osoe-captcha-wrap">
                        <span class="osoe-captcha-question"></span>
                        <a href="#" class="osoe-captcha-refresh"><?php esc_html_e( 'Обновить', 'order-share-extended' ); ?></a>
                    </div>
                    <input type="number" name="captcha_answer" id="osoe-captcha" required>
                </div>
            </div>

            <div class="osoe-form-group">
                <button type="submit" class="osoe-submit-btn"><?php esc_html_e( 'Отправить', 'order-share-extended' ); ?></button>
            </div>

            <div class="osoe-form-messages"></div>
        </form>
    </div>
</div>
