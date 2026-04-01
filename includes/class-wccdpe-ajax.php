<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Ajax {

    public function __construct() {
        // WooCommerce native hook (for native checkout pages)
        add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_session_from_post' ], 1 );

        // Custom AJAX endpoint (for shortcode pages)
        add_action( 'wp_ajax_wccdpe_update_delivery', [ $this, 'ajax_update_delivery' ] );
        add_action( 'wp_ajax_nopriv_wccdpe_update_delivery', [ $this, 'ajax_update_delivery' ] );
    }

    /**
     * Update session from WooCommerce's native update_order_review.
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

        $this->save_to_session( $data );
    }

    /**
     * Custom AJAX: update session, recalculate cart, return rendered table.
     */
    public function ajax_update_delivery() {
        $tipo     = isset( $_POST['tipo'] ) ? sanitize_text_field( $_POST['tipo'] ) : '';
        $distrito = isset( $_POST['distrito'] ) ? sanitize_text_field( $_POST['distrito'] ) : '';

        $this->save_to_session( [
            'billing_tipo_entrega' => $tipo,
            'billing_lima_distrito' => $distrito,
        ] );

        // Recalculate cart totals so fees are applied
        WC()->cart->calculate_totals();

        // Render the review order template
        ob_start();
        woocommerce_order_review();
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    /**
     * Save delivery data to WC session.
     */
    private function save_to_session( $data ) {
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
