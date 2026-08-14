<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;
if ( ! is_a( $product, 'WC_Product' ) ) return;

$columns = isset( $GLOBALS['otv_columns'] ) ? $GLOBALS['otv_columns'] : 5;
?>
<tr <?php wc_product_class( '', $product ); ?>>
    <td class="otv-td-img" data-title="<?php esc_attr_e( 'Фото', 'odinokov-table-view' ); ?>">
        <a href="<?php the_permalink(); ?>">
            <?php
            $thumb_id = $product->get_image_id();
            if ( $thumb_id ) {
                echo wp_get_attachment_image( $thumb_id, 'thumbnail', false, [ 'class' => 'otv-table-img', 'loading' => 'lazy' ] );
            } else {
                echo wc_placeholder_img( 'thumbnail' );
            }
            ?>
        </a>
    </td>
    <td class="otv-td-name" data-title="<?php esc_attr_e( 'Наименование', 'odinokov-table-view' ); ?>">
        <a href="<?php the_permalink(); ?>" class="otv-table-link"><?php the_title(); ?></a>
        <?php if ( $product->get_sku() ) : ?>
            <span class="otv-sku"><?php echo esc_html( $product->get_sku() ); ?></span>
        <?php endif; ?>
    </td>
    <td class="otv-td-price" data-title="<?php esc_attr_e( 'Цена', 'odinokov-table-view' ); ?>">
        <?php woocommerce_template_loop_price(); ?>
    </td>
    <td class="otv-td-cart">
        <?php woocommerce_template_loop_add_to_cart(); ?>
    </td>
</tr>
