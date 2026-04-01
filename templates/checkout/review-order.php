<?php
defined( 'ABSPATH' ) || exit;
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
