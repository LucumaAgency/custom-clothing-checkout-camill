<?php
/**
 * Plugin Name: WC Custom Checkout Delivery PE
 * Description: Plugin de checkout personalizado para entregas en Perú. Controla métodos de envío condicionales con precios dinámicos por distrito de Lima, envíos a provincia via Shalom/Olva, y recojo en tienda.
 * Version: 1.7.1
 * Author: Clothing Custom
 * Text Domain: wc-custom-checkout-pe
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WCCDPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCCDPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCDPE_VERSION', '1.7.1.' . time() );

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
    }

}

WC_Custom_Checkout_Delivery_PE::instance();
