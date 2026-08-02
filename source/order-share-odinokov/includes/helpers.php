<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function oso_build_button_style( $s ) {
    $bg     = $s['bg_color'] ? $s['bg_color'] : '#ffffff';
    $tc     = $s['text_color'] ? $s['text_color'] : '#222222';
    $border = (int) $s['border_width'] > 0 ? "{$s['border_width']}px solid {$s['border_color']}" : 'none';
    $radius = (int) $s['border_radius'];
    $pv     = (int) $s['padding_v'];
    $ph     = (int) $s['padding_h'];
    $fs     = (int) $s['font_size'];
    $weight = (int) $s['font_weight'];
    $upper  = ! empty( $s['uppercase'] ) ? 'uppercase' : 'none';

    $ff = '';
    if ( 'inherit' !== $s['font_family'] && '' !== $s['font_family'] ) {
        $ff = "font-family:{$s['font_family']},sans-serif;";
    }

    return "display:inline-flex;align-items:center;gap:8px;background:{$bg};color:{$tc};border:{$border};border-radius:{$radius}px;padding:{$pv}px {$ph}px;font-size:{$fs}px;font-weight:{$weight};text-transform:{$upper};line-height:1.2;text-decoration:none;cursor:pointer;{$ff}";
}

function oso_render_icon( $s, $which ) {
    $custom_key = 'share' === $which ? 'custom_share_icon' : ( 'pdf' === $which ? 'custom_pdf_icon' : 'custom_order_icon' );
    $fa_key     = 'share' === $which ? 'share_icon' : ( 'pdf' === $which ? 'pdf_icon' : 'order_icon' );

    if ( ! empty( $s[ $custom_key ] ) ) {
        $alt = 'share' === $which ? esc_attr__( 'Поделиться', 'order-share-odinokov' ) : ( 'pdf' === $which ? esc_attr__( 'PDF', 'order-share-odinokov' ) : esc_attr__( 'Заявка', 'order-share-odinokov' ) );
        return '<img src="' . esc_url( $s[ $custom_key ] ) . '" alt="' . $alt . '" width="18" height="18" style="width:18px;height:18px;max-width:18px;max-height:18px;object-fit:contain;" loading="lazy" decoding="async">';
    }

    $icon_set = isset( $s['icon_set'] ) ? $s['icon_set'] : 'black and bold';
    $png_url  = oso_get_icon_set_png( $icon_set, $which );
    if ( $png_url ) {
        $alt = 'share' === $which ? esc_attr__( 'Поделиться', 'order-share-odinokov' ) : ( 'pdf' === $which ? esc_attr__( 'PDF', 'order-share-odinokov' ) : esc_attr__( 'Заявка', 'order-share-odinokov' ) );
        return '<img src="' . esc_url( $png_url ) . '" alt="' . $alt . '" width="18" height="18" style="width:18px;height:18px;max-width:18px;max-height:18px;object-fit:contain;" loading="lazy" decoding="async">';
    }

    if ( ! empty( $s[ $fa_key ] ) ) {
        return '<i class="' . esc_attr( $s[ $fa_key ] ) . '"></i>';
    }
    if ( 'share' === $which ) {
        return '<i class="fa fa-share-alt"></i>';
    }
    if ( 'pdf' === $which ) {
        return '<i class="fa fa-file-pdf-o"></i>';
    }
    return '<i class="fa fa-paper-plane"></i>';
}

function oso_get_icon_set_png( $icon_set, $which ) {
    $base_dir = OSO_DIR . 'icons/' . $icon_set . '/';
    $base_url = OSO_URL . 'icons/' . rawurlencode( $icon_set ) . '/';

    if ( 'share' === $which ) {
        $file = 'share.png';
    } elseif ( 'pdf' === $which ) {
        $file = 'pdf.png';
    } else {
        if ( file_exists( $base_dir . 'mail.png' ) ) {
            $file = 'mail.png';
        } elseif ( file_exists( $base_dir . 'main.png' ) ) {
            $file = 'main.png';
        } else {
            return '';
        }
    }

    if ( file_exists( $base_dir . $file ) ) {
        return $base_url . $file;
    }
    return '';
}

function oso_next_id( $prefix = 'oso-' ) {
    static $counter = 0;
    $counter++;
    return $prefix . $counter;
}

function oso_share_mark_done() {
    global $oso_share_done;
    $oso_share_done = true;
}
function oso_share_is_done() {
    global $oso_share_done;
    return ! empty( $oso_share_done );
}
