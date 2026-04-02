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
        add_filter( 'woocommerce_checkout_fields', [ $this, 'remove_shipping_validation' ], 999 );
        add_filter( 'woocommerce_checkout_posted_data', [ $this, 'inject_missing_fields' ] );

        // Remove MercadoPago DNI error after their validation runs
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'remove_mp_dni_error' ], 999, 2 );

        // DEBUG: Log all checkout fields, POST data, errors
        add_action( 'woocommerce_checkout_process', [ $this, 'debug_checkout_process' ], 1 );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'debug_after_validation' ], 1000, 2 );

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

    /**
     * Remove MercadoPago's DNI validation error if our field has a value.
     */
    public function remove_mp_dni_error( $data, $errors ) {
        $dni = isset( $_POST['billing_dni'] ) ? sanitize_text_field( $_POST['billing_dni'] ) : '';
        if ( ! empty( $dni ) ) {
            $errors->remove( 'billing_dni_required' );
        }

        // Also remove via notices
        $notices = wc_get_notices( 'error' );
        if ( ! empty( $notices ) ) {
            $filtered = [];
            foreach ( $notices as $notice ) {
                $msg = is_array( $notice ) ? ( $notice['notice'] ?? '' ) : $notice;
                if ( ! empty( $dni ) && ( stripos( $msg, 'DNI' ) !== false || stripos( $msg, 'dni' ) !== false ) ) {
                    continue;
                }
                $filtered[] = $notice;
            }
            wc_clear_notices();
            foreach ( $filtered as $notice ) {
                $msg = is_array( $notice ) ? ( $notice['notice'] ?? '' ) : $notice;
                wc_add_notice( $msg, 'error' );
            }
        }
    }

    /**
     * DEBUG: Log checkout data at start of process.
     */
    public function debug_checkout_process() {
        $log = "\n\n===== CHECKOUT DEBUG " . date( 'Y-m-d H:i:s' ) . " =====\n";

        // All POST data (billing fields)
        $log .= "\n-- POST billing_* fields --\n";
        foreach ( $_POST as $key => $val ) {
            if ( strpos( $key, 'billing_' ) === 0 || strpos( $key, 'shipping_' ) === 0 ) {
                $log .= "  {$key} = " . sanitize_text_field( $val ) . "\n";
            }
        }

        // Registered checkout fields from WooCommerce
        $log .= "\n-- WC registered checkout fields (billing) --\n";
        $checkout = WC()->checkout();
        $fields = $checkout->get_checkout_fields( 'billing' );
        foreach ( $fields as $key => $field ) {
            $req = ! empty( $field['required'] ) ? 'REQUIRED' : 'optional';
            $label = isset( $field['label'] ) ? $field['label'] : '(no label)';
            $log .= "  {$key} [{$req}] label: {$label}\n";
        }

        $log .= "\n-- WC registered checkout fields (shipping) --\n";
        $sfields = $checkout->get_checkout_fields( 'shipping' );
        if ( $sfields ) {
            foreach ( $sfields as $key => $field ) {
                $req = ! empty( $field['required'] ) ? 'REQUIRED' : 'optional';
                $log .= "  {$key} [{$req}]\n";
            }
        } else {
            $log .= "  (no shipping fields)\n";
        }

        // Current error notices
        $log .= "\n-- Existing error notices at process start --\n";
        $notices = wc_get_notices( 'error' );
        foreach ( $notices as $n ) {
            $msg = is_array( $n ) ? ( $n['notice'] ?? json_encode( $n ) ) : $n;
            $log .= "  ERROR: {$msg}\n";
        }

        file_put_contents( WCCDPE_PLUGIN_DIR . 'debug.log', $log, FILE_APPEND );
    }

    /**
     * DEBUG: Log errors after all validation.
     */
    public function debug_after_validation( $data, $errors ) {
        $log = "\n-- After validation (priority 1000) --\n";

        // WP_Error codes
        $log .= "WP_Error codes: " . implode( ', ', $errors->get_error_codes() ) . "\n";
        foreach ( $errors->get_error_codes() as $code ) {
            foreach ( $errors->get_error_messages( $code ) as $msg ) {
                $log .= "  [{$code}] {$msg}\n";
            }
        }

        // WC notices
        $notices = wc_get_notices( 'error' );
        $log .= "WC error notices (" . count( $notices ) . "):\n";
        foreach ( $notices as $n ) {
            $msg = is_array( $n ) ? ( $n['notice'] ?? json_encode( $n ) ) : $n;
            $log .= "  NOTICE: {$msg}\n";
        }

        $log .= "===== END DEBUG =====\n";

        file_put_contents( WCCDPE_PLUGIN_DIR . 'debug.log', $log, FILE_APPEND );
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
