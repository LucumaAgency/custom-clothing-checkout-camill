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
        add_filter( 'woocommerce_checkout_fields', [ $this, 'remove_shipping_validation' ], 99 );
        add_filter( 'woocommerce_checkout_posted_data', [ $this, 'inject_missing_fields' ] );

        // Override WooCommerce review-order template with our custom table
        add_filter( 'woocommerce_locate_template', [ $this, 'override_review_order_template' ], 10, 3 );
    }

    public function remove_shipping_validation( $fields ) {
        // Remove all shipping fields
        unset( $fields['shipping'] );

        // Make any billing fields added by MercadoPago not required
        $make_optional = [
            'billing_state', 'billing_city', 'billing_postcode',
            'billing_address_1', 'billing_country', 'billing_dni',
            'shipping_state', 'shipping_city', 'shipping_postcode',
            'shipping_address_1', 'shipping_country',
        ];
        foreach ( $make_optional as $key ) {
            if ( isset( $fields['billing'][ $key ] ) ) {
                $fields['billing'][ $key ]['required'] = false;
            }
            if ( isset( $fields['shipping'][ $key ] ) ) {
                $fields['shipping'][ $key ]['required'] = false;
            }
        }

        return $fields;
    }

    /**
     * Inject default values for fields MercadoPago expects but we don't render.
     */
    public function inject_missing_fields( $data ) {
        $defaults = [
            'billing_country'    => 'PE',
            'billing_state'      => 'LIM',
            'billing_city'       => 'Lima',
            'billing_postcode'   => '15001',
            'billing_address_1'  => isset( $_POST['billing_direccion'] ) && $_POST['billing_direccion'] ? sanitize_text_field( $_POST['billing_direccion'] ) : '—',
            'shipping_country'   => 'PE',
            'shipping_state'     => 'LIM',
            'shipping_city'      => 'Lima',
            'shipping_postcode'  => '15001',
            'shipping_address_1' => '—',
        ];

        // Inject billing_dni from our custom field
        if ( empty( $data['billing_dni'] ) && ! empty( $_POST['billing_dni'] ) ) {
            $data['billing_dni'] = sanitize_text_field( $_POST['billing_dni'] );
        }

        foreach ( $defaults as $key => $val ) {
            if ( empty( $data[ $key ] ) ) {
                $data[ $key ] = $val;
            }
        }

        return $data;
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
