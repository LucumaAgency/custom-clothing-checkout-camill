<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Validation {

    public function __construct() {
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_fields' ] );
    }

    public function validate_fields() {
        $tipo = isset( $_POST['billing_tipo_entrega'] ) ? sanitize_text_field( $_POST['billing_tipo_entrega'] ) : '';

        if ( empty( $tipo ) ) {
            wc_add_notice( 'Por favor selecciona un tipo de entrega.', 'error' );
            return;
        }

        switch ( $tipo ) {
            case 'lima_24h':
            case 'lima_48h':
                if ( empty( $_POST['billing_lima_distrito'] ) ) {
                    wc_add_notice( 'Por favor selecciona un distrito de Lima.', 'error' );
                }
                if ( empty( $_POST['billing_direccion'] ) ) {
                    wc_add_notice( 'Por favor ingresa tu dirección de entrega.', 'error' );
                }
                break;

            case 'provincia_shalom':
                if ( empty( $_POST['billing_departamento'] ) ) {
                    wc_add_notice( 'Por favor selecciona un departamento.', 'error' );
                }
                if ( empty( $_POST['billing_agencia_shalom'] ) ) {
                    wc_add_notice( 'Por favor ingresa el nombre de la agencia Shalom.', 'error' );
                }
                $shalom_sub = isset( $_POST['billing_shalom_sub_tipo'] ) ? sanitize_text_field( $_POST['billing_shalom_sub_tipo'] ) : '';
                if ( empty( $shalom_sub ) ) {
                    wc_add_notice( 'Por favor selecciona la modalidad de pago del envío Shalom (prepago o contraentrega).', 'error' );
                }
                break;

            case 'provincia_olva':
                if ( empty( $_POST['billing_olva_departamento'] ) ) {
                    wc_add_notice( 'Por favor selecciona un departamento.', 'error' );
                }
                $sub = isset( $_POST['billing_olva_sub_tipo'] ) ? sanitize_text_field( $_POST['billing_olva_sub_tipo'] ) : '';
                if ( empty( $sub ) ) {
                    wc_add_notice( 'Por favor selecciona si deseas envío a domicilio o recojo en agencia Olva.', 'error' );
                } elseif ( $sub === 'domicilio' && empty( $_POST['billing_olva_direccion'] ) ) {
                    wc_add_notice( 'Por favor ingresa tu dirección para el envío a domicilio (Olva).', 'error' );
                } elseif ( $sub === 'agencia' && empty( $_POST['billing_olva_agencia_nombre'] ) ) {
                    wc_add_notice( 'Por favor ingresa el nombre de la agencia Olva.', 'error' );
                }
                break;

            case 'recojo_tienda':
                if ( empty( $_POST['billing_tienda_especifica'] ) ) {
                    wc_add_notice( 'Por favor selecciona una tienda para recojo.', 'error' );
                }
                break;
        }
    }
}

new WCCDPE_Validation();
