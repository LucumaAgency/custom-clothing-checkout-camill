<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Ajax {

    public function __construct() {
        // Update session on checkout update (update_checkout triggers this)
        add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_session_from_post' ] );
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

        // Verify nonce to prevent CSRF
        $nonce = isset( $data['wccdpe_nonce'] ) ? $data['wccdpe_nonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wccdpe_nonce' ) ) {
            return;
        }

        $allowed_tipos = [
            'lima_24h', 'lima_48h', 'provincia_shalom', 'provincia_olva', 'recojo_tienda',
        ];

        $tipo = isset( $data['billing_tipo_entrega'] ) ? sanitize_text_field( $data['billing_tipo_entrega'] ) : '';
        if ( $tipo !== '' && ! in_array( $tipo, $allowed_tipos, true ) ) {
            $tipo = '';
        }
        WC()->session->set( 'wccdpe_tipo_entrega', $tipo );

        $distrito = isset( $data['billing_lima_distrito'] ) ? sanitize_text_field( $data['billing_lima_distrito'] ) : '';
        WC()->session->set( 'wccdpe_lima_distrito', $distrito );

        $allowed_shalom = [ 'prepago', 'contraentrega' ];
        $shalom_sub = isset( $data['billing_shalom_sub_tipo'] ) ? sanitize_text_field( $data['billing_shalom_sub_tipo'] ) : '';
        if ( $shalom_sub !== '' && ! in_array( $shalom_sub, $allowed_shalom, true ) ) {
            $shalom_sub = '';
        }
        WC()->session->set( 'wccdpe_shalom_sub_tipo', $shalom_sub );
    }
}

new WCCDPE_Ajax();
