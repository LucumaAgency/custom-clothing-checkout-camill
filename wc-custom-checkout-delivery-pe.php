<?php
/**
 * Plugin Name: WC Custom Checkout Delivery PE
 * Description: Plugin de checkout personalizado para entregas en Perú. Controla métodos de envío condicionales con precios dinámicos por distrito de Lima, envíos a provincia via Shalom/Olva, y recojo en tienda.
 * Version: 1.3.0
 * Author: Clothing Custom
 * Text Domain: wc-custom-checkout-pe
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WCCDPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCCDPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCDPE_VERSION', '1.3.0' );

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

        // Load data helpers
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-data.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-fields.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-fees.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-validation.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-order-meta.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-ajax.php';
        require_once WCCDPE_PLUGIN_DIR . 'includes/class-wccdpe-shortcode.php';

        // Enqueue scripts/styles
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Hide default shipping fields
        add_filter( 'woocommerce_checkout_fields', [ $this, 'remove_default_shipping' ], 5 );

        // Hide default shipping methods from order review (we use custom fees instead)
        add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
    }

    public function enqueue_assets() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_style(
            'wccdpe-checkout',
            WCCDPE_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            WCCDPE_VERSION
        );

        wp_enqueue_script(
            'wccdpe-checkout',
            WCCDPE_PLUGIN_URL . 'assets/js/checkout.js',
            [ 'jquery', 'wc-checkout' ],
            WCCDPE_VERSION,
            true
        );

        wp_localize_script( 'wccdpe-checkout', 'wccdpe_data', [
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'wccdpe_nonce' ),
            'lima_districts'    => WCCDPE_Data::get_lima_districts_with_prices(),
            'ubigeo'            => WCCDPE_Data::get_ubigeo(),
            'is_shortcode'      => false,
        ] );
    }

    /**
     * Remove default WooCommerce shipping fields since we handle everything custom.
     */
    public function remove_default_shipping( $fields ) {
        // Keep billing but remove shipping section entirely
        unset( $fields['shipping'] );
        return $fields;
    }
}

WC_Custom_Checkout_Delivery_PE::instance();
