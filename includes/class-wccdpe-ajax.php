<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Ajax {

    public function __construct() {
        // Update session on checkout update — priority 1 to run before fee calculation
        add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_session_from_post' ], 1 );
    }

    /**
     * Parse posted checkout data and store in session so fees can read it.
     */
    public function update_session_from_post( $posted_data ) {
        if ( ! is_string( $posted_data ) ) {
            return;
        }

        $data = [];
        parse_str( $posted_data, $data );

        if ( empty( $data ) || ! is_array( $data ) ) {
            return;
        }

        $allowed_tipos = [
            'lima_24h', 'lima_48h', 'provincia_shalom_prepago', 'provincia_shalom_contra', 'provincia_olva', 'recojo_tienda',
        ];

        $tipo = isset( $data['billing_tipo_entrega'] ) ? sanitize_text_field( $data['billing_tipo_entrega'] ) : '';
        if ( $tipo !== '' && ! in_array( $tipo, $allowed_tipos, true ) ) {
            $tipo = '';
        }
        WC()->session->set( 'wccdpe_tipo_entrega', $tipo );

        $distrito = isset( $data['billing_lima_distrito'] ) ? sanitize_text_field( $data['billing_lima_distrito'] ) : '';
        WC()->session->set( 'wccdpe_lima_distrito', $distrito );
    }
}

new WCCDPE_Ajax();
