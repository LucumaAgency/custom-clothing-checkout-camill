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
        parse_str( $posted_data, $data );

        $tipo = isset( $data['billing_tipo_entrega'] ) ? sanitize_text_field( $data['billing_tipo_entrega'] ) : '';
        WC()->session->set( 'wccdpe_tipo_entrega', $tipo );

        $distrito = isset( $data['billing_lima_distrito'] ) ? sanitize_text_field( $data['billing_lima_distrito'] ) : '';
        WC()->session->set( 'wccdpe_lima_distrito', $distrito );

        $shalom_sub = isset( $data['billing_shalom_sub_tipo'] ) ? sanitize_text_field( $data['billing_shalom_sub_tipo'] ) : '';
        WC()->session->set( 'wccdpe_shalom_sub_tipo', $shalom_sub );
    }
}

new WCCDPE_Ajax();
