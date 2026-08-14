<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$columns = isset( $GLOBALS['otv_columns'] ) ? (int) $GLOBALS['otv_columns'] : 5;
?>
<table class="otv-products-table">
    <thead>
        <tr>
            <th class="otv-th-img"><?php esc_html_e( 'Фото', 'odinokov-table-view' ); ?></th>
            <th class="otv-th-name"><?php esc_html_e( 'Наименование', 'odinokov-table-view' ); ?></th>
            <th class="otv-th-price"><?php esc_html_e( 'Цена', 'odinokov-table-view' ); ?></th>
            <th class="otv-th-cart"><?php esc_html_e( 'В корзину', 'odinokov-table-view' ); ?></th>
        </tr>
    </thead>
    <tbody>
