<?php
defined( 'ABSPATH' ) || exit;

// Build fee info directly from session as fallback
$wccdpe_tipo  = WC()->session ? WC()->session->get( 'wccdpe_tipo_entrega', '' ) : '';
$wccdpe_dist  = WC()->session ? WC()->session->get( 'wccdpe_lima_distrito', '' ) : '';
$wccdpe_fee_label = '';
$wccdpe_fee_amount = 0;

if ( $wccdpe_tipo ) {
    switch ( $wccdpe_tipo ) {
        case 'lima_24h':
            $prices = WCCDPE_Data::get_lima_districts_with_prices();
            $wccdpe_fee_label = 'Delivery Lima 24h';
            if ( $wccdpe_dist && isset( $prices[ $wccdpe_dist ] ) ) {
                $wccdpe_fee_amount = $prices[ $wccdpe_dist ];
                $wccdpe_fee_label .= ' – ' . $wccdpe_dist;
            }
            break;
        case 'lima_48h':
            $wccdpe_fee_amount = 10;
            $wccdpe_fee_label = 'Delivery Lima 48h';
            break;
        case 'provincia_shalom_prepago':
            $wccdpe_fee_amount = 15;
            $wccdpe_fee_label = 'Envío Provincia – Shalom';
            break;
        case 'provincia_shalom_contra':
            $wccdpe_fee_amount = 0;
            $wccdpe_fee_label = 'Envío Shalom (pago en agencia)';
            break;
        case 'provincia_olva':
            $wccdpe_fee_amount = 15;
            $wccdpe_fee_label = 'Envío Provincia – Olva Courier';
            break;
        case 'recojo_tienda':
            $wccdpe_fee_amount = 0;
            $wccdpe_fee_label = 'Recojo en Tienda (Gratis)';
            break;
    }
}
?>
<div class="wccdpe-order-table-wrap">
    <table class="wccdpe-order-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) :
            ?>
            <tr>
                <td><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?> <strong>&times;<?php echo esc_html( $cart_item['quantity'] ); ?></strong></td>
                <td><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?></td>
            </tr>
            <?php endif; endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="wccdpe-subtotal">
                <td>Subtotal</td>
                <td><?php wc_cart_totals_subtotal_html(); ?></td>
            </tr>

            <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
            <tr class="wccdpe-fee">
                <td><?php echo esc_html( $fee->name ); ?></td>
                <td><?php wc_cart_totals_fee_html( $fee ); ?></td>
            </tr>
            <?php endforeach; ?>

            <?php if ( empty( WC()->cart->get_fees() ) && $wccdpe_fee_label ) : ?>
            <tr class="wccdpe-fee">
                <td><?php echo esc_html( $wccdpe_fee_label ); ?></td>
                <td><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">S/</span>&nbsp;<?php echo number_format( $wccdpe_fee_amount, 2 ); ?></bdi></span></td>
            </tr>
            <?php endif; ?>

            <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) :
                foreach ( WC()->cart->get_tax_totals() as $code => $tax_total ) : ?>
            <tr class="wccdpe-tax">
                <td><?php echo esc_html( $tax_total->label ); ?></td>
                <td><?php echo wp_kses_post( $tax_total->formatted_amount ); ?></td>
            </tr>
            <?php endforeach; endif; ?>

            <tr class="wccdpe-total">
                <td>Total</td>
                <td><?php wc_cart_totals_order_total_html(); ?></td>
            </tr>
        </tfoot>
    </table>
</div>
