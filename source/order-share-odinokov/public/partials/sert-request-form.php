<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$categories = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'orderby'    => 'name',
    'order'      => 'ASC',
) );
?>
<div class="oso-sert-wrap">
    <h3 class="oso-sert-title"><?php esc_html_e( 'Запрос документации', 'order-share-odinokov' ); ?></h3>

    <div class="oso-sert-step oso-sert-step-1">
        <label for="oso-sert-category"><?php esc_html_e( 'Выберите категорию', 'order-share-odinokov' ); ?></label>
        <select id="oso-sert-category" class="oso-sert-select">
            <option value=""><?php esc_html_e( '— Выберите категорию —', 'order-share-odinokov' ); ?></option>
            <?php foreach ( $categories as $cat ) : ?>
                <option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="oso-sert-step oso-sert-step-2" style="display:none;">
        <label for="oso-sert-product"><?php esc_html_e( 'Выберите товар', 'order-share-odinokov' ); ?></label>
        <div class="oso-sert-product-wrap">
            <input type="text" id="oso-sert-product-search" class="oso-sert-search" placeholder="<?php esc_attr_e( 'Поиск товара...', 'order-share-odinokov' ); ?>">
            <select id="oso-sert-product" class="oso-sert-select" size="8">
                <option value=""><?php esc_html_e( '— Загрузка... —', 'order-share-odinokov' ); ?></option>
            </select>
            <div class="oso-sert-product-info">
                <span class="oso-sert-product-count"></span>
                <button type="button" class="oso-sert-load-more" style="display:none;"><?php esc_html_e( 'Загрузить ещё', 'order-share-odinokov' ); ?></button>
            </div>
        </div>
    </div>

    <div class="oso-sert-step oso-sert-step-3" style="display:none;">
        <label><?php esc_html_e( 'Выберите тип документации', 'order-share-odinokov' ); ?></label>
        <div class="oso-sert-checkboxes">
            <label class="oso-sert-check"><input type="checkbox" name="doc_type" value="bim"> <?php esc_html_e( 'BIM-модель', 'order-share-odinokov' ); ?></label>
            <label class="oso-sert-check"><input type="checkbox" name="doc_type" value="sert"> <?php esc_html_e( 'Сертификаты', 'order-share-odinokov' ); ?></label>
            <label class="oso-sert-check"><input type="checkbox" name="doc_type" value="passport"> <?php esc_html_e( 'Паспорт', 'order-share-odinokov' ); ?></label>
            <label class="oso-sert-check"><input type="checkbox" name="doc_type" value="datasheet"> <?php esc_html_e( 'Технический лист (Data Sheet)', 'order-share-odinokov' ); ?></label>
            <label class="oso-sert-check"><input type="checkbox" name="doc_type" value="catalog"> <?php esc_html_e( 'Каталог продукции', 'order-share-odinokov' ); ?></label>
            <label class="oso-sert-check"><input type="checkbox" name="doc_type" value="album_dwg"> <?php esc_html_e( 'Альбом типовых решений DWG', 'order-share-odinokov' ); ?></label>
            <label class="oso-sert-check"><input type="checkbox" name="doc_type" value="other"> <?php esc_html_e( 'Другое', 'order-share-odinokov' ); ?></label>
        </div>
        <div class="oso-sert-other-wrap" style="display:none;">
            <textarea id="oso-sert-other-text" rows="2" placeholder="<?php esc_attr_e( 'Укажите, что именно Вам нужно...', 'order-share-odinokov' ); ?>"></textarea>
        </div>
    </div>

    <div class="oso-sert-step oso-sert-step-4" style="display:none;">
        <label><?php esc_html_e( 'Контактные данные', 'order-share-odinokov' ); ?></label>
        <div class="oso-sert-fields">
            <div class="oso-sert-field">
                <input type="text" id="oso-sert-name" placeholder="<?php esc_attr_e( 'Как к Вам обращаться *', 'order-share-odinokov' ); ?>" required>
            </div>
            <div class="oso-sert-field">
                <input type="text" id="oso-sert-inn" placeholder="<?php esc_attr_e( 'ИНН компании *', 'order-share-odinokov' ); ?>" required>
            </div>
            <div class="oso-sert-field">
                <input type="email" id="oso-sert-email" placeholder="<?php esc_attr_e( 'Email для ответа *', 'order-share-odinokov' ); ?>" required>
            </div>
            <div class="oso-sert-field">
                <input type="tel" id="oso-sert-phone" placeholder="<?php esc_attr_e( 'Телефон (не обязательно)', 'order-share-odinokov' ); ?>">
            </div>
        </div>

        <div class="oso-sert-captcha-row">
            <span class="oso-sert-captcha-question"></span>
            <a href="#" class="oso-sert-captcha-refresh"><?php esc_html_e( 'Обновить', 'order-share-odinokov' ); ?></a>
            <input type="number" id="oso-sert-captcha-answer" placeholder="?" required>
            <input type="hidden" id="oso-sert-captcha-key" value="">
        </div>

        <button type="button" class="oso-sert-submit"><?php esc_html_e( 'Отправить запрос', 'order-share-odinokov' ); ?></button>
        <div class="oso-sert-messages"></div>
    </div>
</div>
