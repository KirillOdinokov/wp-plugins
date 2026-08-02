<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$s = oso_get_settings();
?>
<div id="oso-order-modal" class="oso-modal" style="display:none;">
    <div class="oso-modal-content">
        <button class="oso-modal-close" type="button" aria-label="<?php esc_attr_e( 'Закрыть', 'order-share-odinokov' ); ?>">&times;</button>
        <h3><?php echo esc_html( $s['order_text'] ); ?></h3>
        <p class="oso-product-title"></p>

        <form id="oso-order-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="product_name" id="oso-product-name" value="">
            <input type="hidden" name="captcha_key" id="oso-captcha-key" value="">

            <?php if ( ! empty( $s['field_inn'] ) ) : ?>
            <div class="oso-form-group">
                <label for="oso-inn"><?php esc_html_e( 'ИНН компании', 'order-share-odinokov' ); ?></label>
                <input type="number" name="inn" id="oso-inn" placeholder="<?php esc_attr_e( 'Если Вы — физическое лицо, оставьте пустым', 'order-share-odinokov' ); ?>">
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $s['field_name'] ) ) : ?>
            <div class="oso-form-group">
                <label for="oso-name"><?php esc_html_e( 'Как к Вам обращаться', 'order-share-odinokov' ); ?> <span class="oso-required">*</span></label>
                <input type="text" name="name" id="oso-name" required>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $s['field_email'] ) ) : ?>
            <div class="oso-form-group">
                <label for="oso-email"><?php esc_html_e( 'Email', 'order-share-odinokov' ); ?> <span class="oso-required">*</span></label>
                <input type="email" name="email" id="oso-email" required>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $s['field_accessories'] ) ) : ?>
            <div class="oso-form-group">
                <label for="oso-accessories"><?php esc_html_e( 'Комплектующие', 'order-share-odinokov' ); ?></label>
                <textarea name="accessories" id="oso-accessories" rows="3" placeholder="<?php esc_attr_e( 'Напишите в простой форме, если ещё что-то нужно', 'order-share-odinokov' ); ?>"></textarea>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $s['field_files'] ) ) : ?>
            <div class="oso-form-group">
                <label for="oso-files"><?php esc_html_e( 'Вложения', 'order-share-odinokov' ); ?></label>
                <input type="file" name="files[]" id="oso-files" multiple accept=".jpg,.jpeg,.pdf,.dwg,.png,.webp,.doc,.xls,.csv">
                <p class="oso-hint"><?php esc_html_e( 'До 3 файлов, до 20 МБ. Форматы: JPG, JPEG, PDF, DWG, PNG, WEBP, DOC, XLS, CSV.', 'order-share-odinokov' ); ?></p>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $s['field_delivery'] ) ) : ?>
            <div class="oso-form-group">
                <label><?php esc_html_e( 'Доставка', 'order-share-odinokov' ); ?></label>
                <label class="oso-radio"><input type="radio" name="delivery" value="no" checked> <?php esc_html_e( 'Нет', 'order-share-odinokov' ); ?></label>
                <label class="oso-radio"><input type="radio" name="delivery" value="yes"> <?php esc_html_e( 'Да', 'order-share-odinokov' ); ?></label>
            </div>
            <div class="oso-form-group oso-delivery-address" style="display:none;">
                <label for="oso-delivery-address"><?php esc_html_e( 'Адрес доставки', 'order-share-odinokov' ); ?></label>
                <textarea name="delivery_address" id="oso-delivery-address" rows="2"></textarea>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $s['field_captcha'] ) ) : ?>
            <div class="oso-form-group">
                <label for="oso-captcha"><?php esc_html_e( 'Капча', 'order-share-odinokov' ); ?></label>
                <div class="oso-captcha-row">
                    <div class="oso-captcha-wrap">
                        <span class="oso-captcha-question"></span>
                        <a href="#" class="oso-captcha-refresh"><?php esc_html_e( 'Обновить', 'order-share-odinokov' ); ?></a>
                    </div>
                    <input type="number" name="captcha_answer" id="oso-captcha" required>
                </div>
            </div>
            <?php endif; ?>

            <div class="oso-form-group">
                <button type="submit" class="oso-submit-btn"><?php esc_html_e( 'Отправить заявку', 'order-share-odinokov' ); ?></button>
            </div>

            <div class="oso-form-messages"></div>
        </form>
    </div>
</div>
