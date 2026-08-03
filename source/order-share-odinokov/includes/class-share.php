<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OSO_Share {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style_and_fonts' ), 20 );
        add_action( 'wp_head', array( $this, 'inline_styles' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );

        add_action( 'woocommerce_single_product_summary', array( $this, 'render_buttons_product' ), 35 );
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_buttons_product' ), 5 );
        add_action( 'woocommerce_share', array( $this, 'render_buttons_product' ), 50 );
        add_action( 'porto_woocommerce_share', array( $this, 'render_buttons_product' ), 50 );
        add_filter( 'the_content', array( $this, 'filter_the_content' ), 20 );
    }

    private function is_share_screen() {
        if ( is_singular( 'product' ) ) {
            $s = oso_get_settings();
            return ! empty( $s['enable_share_product'] );
        }
        if ( is_singular( 'post' ) ) {
            $s = oso_get_settings();
            return ! empty( $s['enable_share_post'] );
        }
        return false;
    }

    public function enqueue_style_and_fonts() {
        if ( ! $this->is_share_screen() ) {
            return;
        }
        $s = oso_get_settings();
        if ( ! empty( $s['custom_fonts'] ) ) {
            $families = array();
            foreach ( explode( '|', $s['custom_fonts'] ) as $f ) {
                $f = trim( $f );
                if ( '' !== $f ) {
                    $families[] = str_replace( '+', ' ', $f ) . ':wght@300;400;500;600;700;800';
                }
            }
            if ( ! empty( $families ) ) {
                $url = add_query_arg(
                    array(
                        'family'  => rawurlencode( implode( '&family=', $families ) ),
                        'display' => 'swap',
                    ),
                    'https://fonts.googleapis.com/css2'
                );
                wp_enqueue_style( 'oso-share-fonts', esc_url( $url ), array(), null );
            }
        }
    }

    public function inline_styles() {
        if ( ! $this->is_share_screen() ) {
            return;
        }
        $s = oso_get_settings();
        $font_family_css = '';
        if ( 'inherit' !== $s['font_family'] && '' !== $s['font_family'] ) {
            $font_family_css = "font-family: {$s['font_family']}, sans-serif;";
        }
        $bg     = $s['bg_color'] ? $s['bg_color'] : '#ffffff';
        $tc     = $s['text_color'] ? $s['text_color'] : '#222222';
        $border = (int) $s['border_width'] > 0 ? "{$s['border_width']}px solid {$s['border_color']}" : 'none';
        $radius = (int) $s['border_radius'];
        $pv     = (int) $s['padding_v'];
        $ph     = (int) $s['padding_h'];
        $gap    = (int) $s['gap'];
        $fs     = (int) $s['font_size'];
        $is     = (int) $s['icon_size'];
        $weight = (int) $s['font_weight'];
        $upper  = ! empty( $s['uppercase'] ) ? 'uppercase' : 'none';
        $bcolor = $s['border_color'] ? $s['border_color'] : '#222222';

        $css = ".oso-btn-row{margin:16px 0;display:flex;flex-wrap:wrap;align-items:center;gap:{$gap}px;}
.oso-btn-row .oso-btn{display:inline-flex;align-items:center;gap:8px;background:{$bg};color:{$tc};border:{$border};border-radius:{$radius}px;padding:{$pv}px {$ph}px;font-size:{$fs}px;font-weight:{$weight};text-transform:{$upper};line-height:1.2;text-decoration:none;cursor:pointer;{$font_family_css}}
.oso-btn-row .oso-btn:hover{opacity:.9;}
.oso-btn-row .oso-btn:focus{outline:2px solid {$bcolor};outline-offset:2px;}
.oso-btn-row .oso-btn:active{transform:translateY(1px);}
.oso-btn-row .oso-btn-ico{display:inline-flex;align-items:center;justify-content:center;line-height:1;flex-shrink:0;width:{$is}px;height:{$is}px;max-width:{$is}px;max-height:{$is}px;overflow:hidden;}
.oso-btn-row .oso-btn-ico img{width:{$is}px !important;height:{$is}px !important;max-width:{$is}px !important;max-height:{$is}px !important;object-fit:contain;display:block;}
.oso-btn-row .oso-btn-ico i{font-size:{$is}px !important;line-height:1;width:{$is}px;height:{$is}px;display:inline-flex;align-items:center;justify-content:center;}
.oso-btn-row .oso-btn.is-shared{opacity:.7;}
@media print{.oso-btn-row{display:none !important;}}";
        echo '<style id="oso-share-inline">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_buttons_product() {
        if ( oso_share_is_done() ) {
            return;
        }
        $s = oso_get_settings();
        if ( empty( $s['enable_share_product'] ) ) {
            return;
        }
        oso_share_mark_done();
        $this->output( $s, 'product' );
    }

    public function filter_the_content( $content ) {
        if ( ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        $s = oso_get_settings();
        $on_product = ( function_exists( 'is_product' ) && is_product() );
        $on_post    = is_singular( 'post' );
        if ( $on_product && empty( $s['enable_share_product'] ) ) {
            return $content;
        }
        if ( $on_post && empty( $s['enable_share_post'] ) ) {
            return $content;
        }
        if ( ! $on_product && ! $on_post ) {
            return $content;
        }
        if ( $on_product && oso_share_is_done() ) {
            return $content;
        }
        ob_start();
        $this->output( $s, $on_product ? 'product' : 'post' );
        if ( $on_product ) {
            oso_share_mark_done();
        }
        $buttons = ob_get_clean();
        return $content . $buttons;
    }

    private function output( $s, $context ) {
        $title = wp_get_document_title();
        $url   = '';
        $desc  = '';
        if ( 'product' === $context ) {
            global $product;
            if ( $product instanceof WC_Product ) {
                $url  = $product->get_permalink();
                $desc = wp_strip_all_tags( $product->get_short_description() );
                if ( '' === $title ) {
                    $title = $product->get_name();
                }
            } else {
                $post_id = get_the_ID();
                if ( $post_id ) {
                    $url = get_permalink( $post_id );
                }
            }
        } else {
            $url  = get_permalink();
            $post = get_post();
            if ( $post instanceof WP_Post ) {
                $desc = wp_strip_all_tags( wp_trim_words( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 30, '…' ) );
            }
        }
        if ( '' === $url ) {
            $url = get_permalink();
        }
        if ( '' === $title ) {
            $title = wp_get_document_title();
        }

        $unique   = oso_next_id( 'oso-btn-' );
        $share_id = $unique . '-share';
        $pdf_id   = $unique . '-pdf';
        $order_id = $unique . '-order';

        $data = array(
            'title'   => $title,
            'url'     => $url,
            'desc'    => $desc,
            'pdfName' => $s['pdf_filename'],
        );

        $style  = oso_build_button_style( $s );

        echo '<div class="oso-btn-row" data-oso-share="' . esc_attr( wp_json_encode( $data ) ) . '">';

        echo '<button type="button" id="' . esc_attr( $share_id ) . '" class="oso-btn oso-btn-share" aria-label="' . esc_attr( $s['share_text'] ) . '" style="' . esc_attr( $style ) . '">';
        echo '<span class="oso-btn-ico" aria-hidden="true">' . oso_render_icon( $s, 'share' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="oso-btn-txt">' . esc_html( $s['share_text'] ) . '</span>';
        echo '</button>';

        if ( ! empty( $s['print_button'] ) ) {
            echo '<button type="button" id="' . esc_attr( $pdf_id ) . '" class="oso-btn oso-btn-pdf" aria-label="' . esc_attr( $s['pdf_text'] ) . '" style="' . esc_attr( $style ) . '">';
            echo '<span class="oso-btn-ico" aria-hidden="true">' . oso_render_icon( $s, 'pdf' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="oso-btn-txt">' . esc_html( $s['pdf_text'] ) . '</span>';
            echo '</button>';
        }

        $has_order_btn = false;
        if ( 'product' === $context && ! empty( $s['enable_order_product'] ) ) {
            $has_order_btn = true;
        }
        if ( $has_order_btn ) {
            global $product;
            if ( $product instanceof WC_Product ) {
                $pid  = $product->get_id();
                $pnam = $product->get_name();
                echo '<a href="javascript:void(0)" id="' . esc_attr( $order_id ) . '" class="oso-btn oso-btn-order oso-order-btn" data-product-id="' . esc_attr( $pid ) . '" data-product-name="' . esc_attr( $pnam ) . '" style="' . esc_attr( $style ) . '">';
                echo '<span class="oso-btn-ico" aria-hidden="true">' . oso_render_icon( $s, 'order' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span class="oso-btn-txt">' . esc_html( $s['order_text'] ) . '</span>';
                echo '</a>';
            }
        }

        echo '</div>';
    }

    public function enqueue_script() {
        if ( ! $this->is_share_screen() ) {
            return;
        }
        wp_register_script( 'oso-share-front', false, array(), OSO_VERSION, true );
        wp_enqueue_script( 'oso-share-front' );

        $js = "(function(){
    function openOrderModal(btn){
        var $modal = document.getElementById('oso-order-modal');
        if (!$modal) return false;
        var productName = btn.getAttribute('data-product-name') || '';
        var pn = document.getElementById('oso-product-name');
        if (pn) pn.value = productName;
        var titleEl = $modal.querySelector('.oso-product-title');
        if (titleEl) titleEl.textContent = productName;
        $modal.classList.add('oso-modal-open');
        $modal.style.display = 'flex';
        $modal.style.opacity = '1';
        $modal.style.visibility = 'visible';
        $modal.style.pointerEvents = 'auto';
        $modal.setAttribute('aria-hidden', 'false');
        if (document.documentElement) document.documentElement.style.overflow = 'hidden';
        return true;
    }
    function handler(e){
        var t = e.target;
        if (!t || !t.closest) return;
        // Only handle order button here. Share/PDF buttons are handled
        // by direct binding in oso-public.js to avoid double-firing.
        var btn = t.closest('.oso-btn-order');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        openOrderModal(btn);
    }
    function bind(){
        document.addEventListener('click', handler, true);
        document.addEventListener('touchend', handler, true);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();";
        wp_add_inline_script( 'oso-share-front', $js );
    }
}
