<?php
/**
 * Plugin Name: WC Custom Checkout Delivery PE
 * Description: Plugin de checkout personalizado para entregas en Perú. Controla métodos de envío condicionales con precios dinámicos por distrito de Lima, envíos a provincia via Shalom/Olva, y recojo en tienda.
 * Version: 1.8.0
 * Author: Clothing Custom
 * Text Domain: wc-custom-checkout-pe
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WCCDPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCCDPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCDPE_VERSION', '1.8.0.' . time() );

/**
 * Main plugin class
 */
final class WC_Custom_Checkout_Delivery_PE {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="error"><p><strong>WC Custom Checkout Delivery PE</strong> requiere WooCommerce activo.</p></div>';
            } );
            return;
        }

        // Load classes
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-data.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-fees.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-validation.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-order-meta.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-ajax.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-shortcode.php';

        // Hide default shipping methods from order review (we use custom fees instead)
        add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );

        // Remove shipping fields validation and make MP fields optional
        add_filter( 'woocommerce_checkout_fields', [ $this, 'remove_shipping_validation' ], PHP_INT_MAX );
        add_filter( 'woocommerce_checkout_posted_data', [ $this, 'inject_missing_fields' ] );

        // Sync billing_dni → billing_wooccm9 and remove its validation error
        add_action( 'woocommerce_checkout_process', [ $this, 'sync_dni_to_wooccm' ], 1 );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'remove_wooccm_dni_error' ], 999, 2 );

        // Override WooCommerce review-order template with our custom table
        add_filter( 'woocommerce_locate_template', [ $this, 'override_review_order_template' ], 10, 3 );
    }

    public function remove_shipping_validation( $fields ) {
        // Remove all shipping fields
        unset( $fields['shipping'] );

        // Make ALL billing fields added by MercadoPago/WooCommerce not required
        // (we handle our own validation in WCCDPE_Validation)
        if ( isset( $fields['billing'] ) ) {
            foreach ( $fields['billing'] as $key => &$field ) {
                // Keep only our rendered fields as required
                $our_fields = [ 'billing_email', 'billing_first_name', 'billing_last_name', 'billing_phone' ];
                if ( ! in_array( $key, $our_fields, true ) ) {
                    $field['required'] = false;
                }
            }
        }

        return $fields;
    }

    /**
     * Inject default values for fields MercadoPago expects but we don't render.
     */
    public function inject_missing_fields( $data ) {
        $tipo = isset( $_POST['billing_tipo_entrega'] ) ? sanitize_text_field( $_POST['billing_tipo_entrega'] ) : '';

        // Build a human-readable billing address based on the delivery type the
        // customer actually selected, so the WooCommerce admin order page shows
        // the real delivery destination instead of a placeholder "—".
        $address  = '';
        $city     = 'Lima';
        $state    = 'LIM';

        switch ( $tipo ) {
            case 'lima_24h':
            case 'lima_48h':
                $address = isset( $_POST['billing_direccion'] ) ? sanitize_text_field( $_POST['billing_direccion'] ) : '';
                $ref     = isset( $_POST['billing_referencia'] ) ? sanitize_text_field( $_POST['billing_referencia'] ) : '';
                if ( $ref ) {
                    $address = trim( $address . ' (Ref: ' . $ref . ')' );
                }
                $city = isset( $_POST['billing_lima_distrito'] ) ? sanitize_text_field( $_POST['billing_lima_distrito'] ) : 'Lima';
                break;

            case 'provincia_shalom_prepago':
                $agencia = isset( $_POST['billing_agencia_shalom'] ) ? sanitize_text_field( $_POST['billing_agencia_shalom'] ) : '';
                $address = 'Agencia Shalom: ' . $agencia;
                $city    = isset( $_POST['billing_distrito_prov'] ) ? sanitize_text_field( $_POST['billing_distrito_prov'] ) : '';
                $state   = isset( $_POST['billing_departamento'] ) ? sanitize_text_field( $_POST['billing_departamento'] ) : '';
                break;

            case 'provincia_shalom_contra':
                $agencia = isset( $_POST['billing_agencia_shalom_contra'] ) ? sanitize_text_field( $_POST['billing_agencia_shalom_contra'] ) : '';
                $address = 'Agencia Shalom (contraentrega): ' . $agencia;
                $city    = isset( $_POST['billing_distrito_prov_contra'] ) ? sanitize_text_field( $_POST['billing_distrito_prov_contra'] ) : '';
                $state   = isset( $_POST['billing_departamento_contra'] ) ? sanitize_text_field( $_POST['billing_departamento_contra'] ) : '';
                break;

            case 'provincia_olva':
                $sub = isset( $_POST['billing_olva_sub_tipo'] ) ? sanitize_text_field( $_POST['billing_olva_sub_tipo'] ) : '';
                if ( $sub === 'domicilio' ) {
                    $dir = isset( $_POST['billing_olva_direccion'] ) ? sanitize_text_field( $_POST['billing_olva_direccion'] ) : '';
                    $ref = isset( $_POST['billing_olva_referencia'] ) ? sanitize_text_field( $_POST['billing_olva_referencia'] ) : '';
                    $address = $ref ? trim( $dir . ' (Ref: ' . $ref . ')' ) : $dir;
                } else {
                    $ag = isset( $_POST['billing_olva_agencia_nombre'] ) ? sanitize_text_field( $_POST['billing_olva_agencia_nombre'] ) : '';
                    $address = 'Agencia Olva: ' . $ag;
                }
                $city  = isset( $_POST['billing_olva_distrito'] ) ? sanitize_text_field( $_POST['billing_olva_distrito'] ) : '';
                $state = isset( $_POST['billing_olva_departamento'] ) ? sanitize_text_field( $_POST['billing_olva_departamento'] ) : '';
                break;

            case 'recojo_tienda':
                $tienda = isset( $_POST['billing_tienda_especifica'] ) ? sanitize_text_field( $_POST['billing_tienda_especifica'] ) : '';
                $address = 'Recojo en tienda: ' . $tienda;
                break;
        }

        if ( empty( $address ) ) {
            $address = '—';
        }
        if ( empty( $state ) ) {
            $state = 'LIM';
        }
        if ( empty( $city ) ) {
            $city = 'Lima';
        }

        $defaults = [
            'billing_country'    => 'PE',
            'billing_state'      => $state,
            'billing_city'       => $city,
            'billing_postcode'   => '15001',
            'billing_address_1'  => $address,
            'shipping_country'   => 'PE',
            'shipping_state'     => $state,
            'shipping_city'      => $city,
            'shipping_postcode'  => '15001',
            'shipping_address_1' => $address,
        ];

        // Inject billing_dni from our custom field
        if ( empty( $data['billing_dni'] ) && ! empty( $_POST['billing_dni'] ) ) {
            $data['billing_dni'] = sanitize_text_field( $_POST['billing_dni'] );
        }

        // Force-override address/state/city fields: the shortcode posts
        // hardcoded hidden inputs ("—", "LIM", "Lima") that would otherwise win.
        $force_override = [
            'billing_address_1', 'billing_state', 'billing_city',
            'shipping_address_1', 'shipping_state', 'shipping_city',
        ];
        foreach ( $defaults as $key => $val ) {
            if ( in_array( $key, $force_override, true ) || empty( $data[ $key ] ) ) {
                $data[ $key ] = $val;
            }
        }

        return $data;
    }

    /**
     * Copy billing_dni to billing_wooccm9 before validation.
     */
    public function sync_dni_to_wooccm() {
        if ( ! empty( $_POST['billing_dni'] ) && empty( $_POST['billing_wooccm9'] ) ) {
            $_POST['billing_wooccm9'] = sanitize_text_field( $_POST['billing_dni'] );
        }
    }

    /**
     * Remove wooccm9 (DNI) validation error if our field has a value.
     */
    public function remove_wooccm_dni_error( $data, $errors ) {
        if ( ! empty( $_POST['billing_dni'] ) ) {
            $errors->remove( 'billing_wooccm9_required' );
        }
    }

    public function override_review_order_template( $template, $template_name, $template_path ) {
        if ( $template_name === 'checkout/review-order.php' ) {
            $plugin_template = WCCDPE_PLUGIN_DIR . 'templates/checkout/review-order.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
    }

}

WC_Custom_Checkout_Delivery_PE::instance();
