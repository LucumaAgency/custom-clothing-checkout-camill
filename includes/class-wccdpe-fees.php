<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Fees {

    public function __construct() {
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_delivery_fee' ] );
    }

    /**
     * Add delivery fee based on selected type and district.
     */
    public function add_delivery_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $tipo = WC()->session->get( 'wccdpe_tipo_entrega' );
        if ( ! $tipo ) {
            return;
        }

        $fee = 0;
        $label = 'Envío';

        switch ( $tipo ) {
            case 'lima_24h':
                $distrito = WC()->session->get( 'wccdpe_lima_distrito' );
                $prices = WCCDPE_Data::get_lima_districts_with_prices();
                $label = 'Delivery Lima 24h';
                if ( $distrito && isset( $prices[ $distrito ] ) ) {
                    $fee = $prices[ $distrito ];
                    $label .= ' – ' . $distrito;
                }
                break;

            case 'lima_48h':
                $fee = 10;
                $label = 'Delivery Lima 48h';
                break;

            case 'provincia_shalom':
                $fee = 0;
                $label = 'Envío Provincia – Shalom (pago en agencia)';
                break;

            case 'provincia_olva':
                $fee = 15;
                $label = 'Envío Provincia – Olva Courier';
                break;

            case 'recojo_tienda':
                $fee = 0;
                $label = 'Recojo en Tienda (Gratis)';
                break;
        }

        if ( $tipo ) {
            $cart->add_fee( $label, $fee, false );
        }
    }
}

new WCCDPE_Fees();
