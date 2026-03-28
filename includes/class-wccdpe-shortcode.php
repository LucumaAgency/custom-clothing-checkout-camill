<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Shortcode {

    public function __construct() {
        add_shortcode( 'wccdpe_delivery_form',   [ $this, 'render_delivery_form' ] );
        add_shortcode( 'wccdpe_order_review',     [ $this, 'render_order_review' ] );
        add_shortcode( 'wccdpe_payment_methods',  [ $this, 'render_payment_methods' ] );
        add_shortcode( 'wccdpe_full_checkout',    [ $this, 'render_full_checkout' ] );
    }

    /**
     * Enqueue required assets.
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'wccdpe-checkout',
            WCCDPE_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            WCCDPE_VERSION
        );

        wp_enqueue_script(
            'wccdpe-checkout',
            WCCDPE_PLUGIN_URL . 'assets/js/checkout.js',
            [ 'jquery' ],
            WCCDPE_VERSION,
            true
        );

        wp_localize_script( 'wccdpe-checkout', 'wccdpe_data', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'wccdpe_nonce' ),
            'lima_districts' => WCCDPE_Data::get_lima_districts_with_prices(),
            'ubigeo'         => WCCDPE_Data::get_ubigeo(),
            'is_shortcode'   => true,
        ] );
    }

    /**
     * Ensure WooCommerce checkout scripts are loaded.
     */
    private function enqueue_wc_checkout_assets() {
        if ( ! is_null( WC()->cart ) && ! WC()->cart->is_empty() ) {
            wp_enqueue_script( 'wc-checkout' );
            wp_enqueue_script( 'wc-cart-fragments' );
        }
    }

    // ─────────────────────────────────────────────
    // [wccdpe_delivery_form]
    // ─────────────────────────────────────────────

    public function render_delivery_form( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<p>WooCommerce es requerido para mostrar este formulario.</p>';
        }

        $this->enqueue_assets();

        ob_start();
        echo '<div id="wccdpe-shortcode-wrapper" class="woocommerce">';
        $this->output_delivery_fields();
        echo '</div>';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // [wccdpe_order_review]
    // ─────────────────────────────────────────────

    public function render_order_review( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<p>WooCommerce es requerido.</p>';
        }

        if ( is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return '<p class="wccdpe-empty-cart">Tu carrito est&aacute; vac&iacute;o.</p>';
        }

        $this->enqueue_assets();
        $this->enqueue_wc_checkout_assets();

        ob_start();
        echo '<div id="wccdpe-order-review-wrapper" class="woocommerce">';
        echo '<h3 id="order_review_heading">Tu pedido</h3>';
        echo '<div id="order_review" class="woocommerce-checkout-review-order">';
        woocommerce_order_review();
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // [wccdpe_payment_methods]
    // ─────────────────────────────────────────────

    public function render_payment_methods( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<p>WooCommerce es requerido.</p>';
        }

        if ( is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return '<p class="wccdpe-empty-cart">Agrega productos al carrito para ver los m&eacute;todos de pago.</p>';
        }

        $this->enqueue_assets();
        $this->enqueue_wc_checkout_assets();

        ob_start();
        echo '<div id="wccdpe-payment-wrapper" class="woocommerce">';
        woocommerce_checkout_payment();
        echo '</div>';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // [wccdpe_full_checkout]
    // ─────────────────────────────────────────────

    public function render_full_checkout( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<p>WooCommerce es requerido.</p>';
        }

        if ( is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return '<div class="woocommerce wccdpe-empty-cart">
                <p>Tu carrito est&aacute; vac&iacute;o. Agrega productos antes de continuar.</p>
                <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" class="button">Ir a la tienda</a>
            </div>';
        }

        $this->enqueue_assets();
        $this->enqueue_wc_checkout_assets();

        $checkout = WC()->checkout();

        ob_start();

        echo '<div id="wccdpe-full-checkout" class="woocommerce">';

        wc_print_notices();

        echo '<form name="checkout" method="post" class="checkout woocommerce-checkout" action="' . esc_url( wc_get_checkout_url() ) . '" enctype="multipart/form-data" novalidate="novalidate">';

        echo '<div class="wccdpe-checkout-columns">';

        // ── Contenedor A (50% izquierdo) ──
        echo '<div class="wccdpe-checkout-col wccdpe-checkout-col--fields">';
        echo '<div class="wccdpe-checkout-col-inner">';

        echo '<h3>Detalles de facturaci&oacute;n</h3>';
        echo '<div class="woocommerce-billing-fields">';

        // Email
        woocommerce_form_field( 'billing_email', [
            'type'        => 'email',
            'label'       => 'Email o n&uacute;mero de contacto',
            'required'    => true,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'correo@ejemplo.com o 9XXXXXXXX',
            'autocomplete'=> 'email',
        ], $checkout->get_value( 'billing_email' ) );

        // Nombre y Apellidos 50/50
        woocommerce_form_field( 'billing_first_name', [
            'type'        => 'text',
            'label'       => 'Nombre',
            'required'    => true,
            'class'       => [ 'form-row-first' ],
            'placeholder' => 'Nombre',
            'autocomplete'=> 'given-name',
        ], $checkout->get_value( 'billing_first_name' ) );

        woocommerce_form_field( 'billing_last_name', [
            'type'        => 'text',
            'label'       => 'Apellidos',
            'required'    => true,
            'class'       => [ 'form-row-last' ],
            'placeholder' => 'Apellidos',
            'autocomplete'=> 'family-name',
        ], $checkout->get_value( 'billing_last_name' ) );

        // Teléfono
        woocommerce_form_field( 'billing_phone', [
            'type'        => 'tel',
            'label'       => 'Tel&eacute;fono',
            'required'    => true,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Tel&eacute;fono',
            'autocomplete'=> 'tel',
        ], $checkout->get_value( 'billing_phone' ) );

        echo '</div>';

        // Tipo de entrega (justo después de teléfono)
        $this->output_delivery_fields();

        // Información adicional
        echo '<div class="wccdpe-additional-info">';
        echo '<h3>Informaci&oacute;n adicional</h3>';
        woocommerce_form_field( 'order_comments', [
            'type'        => 'textarea',
            'label'       => 'Notas del pedido',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Notas sobre tu pedido, por ejemplo, notas especiales para la entrega.',
        ], '' );
        echo '</div>';

        echo '</div>'; // col-inner
        echo '</div>'; // col--fields

        // ── Contenedor B (50% derecho, fondo #F5F5F5) ──
        echo '<div class="wccdpe-checkout-col wccdpe-checkout-col--order">';
        echo '<div class="wccdpe-checkout-col-inner">';

        echo '<h3 id="order_review_heading">Tu pedido</h3>';
        echo '<div id="order_review" class="woocommerce-checkout-review-order">';
        woocommerce_order_review();
        echo '</div>';

        woocommerce_checkout_payment();

        echo '</div>'; // col-inner
        echo '</div>'; // col--order

        echo '</div>'; // columns

        echo '</form>';
        echo '</div>'; // #wccdpe-full-checkout

        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // Helper: Output delivery fields using woocommerce_form_field
    // ─────────────────────────────────────────────

    private function output_delivery_fields() {

        // Tipo de entrega
        $delivery_options = WCCDPE_Data::get_delivery_types();
        unset( $delivery_options[''] ); // quitar placeholder duplicado
        woocommerce_form_field( 'billing_tipo_entrega', [
            'type'        => 'select',
            'label'       => '&nbsp;',
            'required'    => true,
            'class'       => [ 'form-row-wide', 'wccdpe-tipo-entrega-field' ],
            'placeholder' => '— Selecciona tipo de entrega —',
            'options'     => $delivery_options,
        ] );

        echo '<div id="wccdpe-delivery-fields">';

        // ── Lima (24h & 48h) ──
        echo '<div class="wccdpe-group" data-show="lima_24h,lima_48h" style="display:none;">';

        $lima_districts = WCCDPE_Data::get_lima_districts_with_prices();
        $lima_options = array_merge(
            [ '' => '— Selecciona distrito —' ],
            array_combine(
                array_keys( $lima_districts ),
                array_keys( $lima_districts )
            )
        );

        woocommerce_form_field( 'billing_lima_distrito', [
            'type'     => 'select',
            'label'    => 'Distrito de Lima',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => $lima_options,
        ] );

        echo '<p class="wccdpe-distrito-price" style="display:none;"></p>';

        woocommerce_form_field( 'billing_direccion', [
            'type'        => 'text',
            'label'       => 'Dirección',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Av. / Jr. / Calle y número',
        ] );

        woocommerce_form_field( 'billing_referencia', [
            'type'        => 'text',
            'label'       => 'Referencia',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Cerca de...',
        ] );

        woocommerce_form_field( 'billing_vivienda', [
            'type'    => 'select',
            'label'   => 'Tipo de vivienda',
            'required'=> false,
            'class'   => [ 'form-row-wide' ],
            'options' => [
                ''           => '— Selecciona —',
                'casa'       => 'Casa',
                'apartamento'=> 'Apartamento',
                'interior'   => 'Interior / Oficina',
            ],
        ] );

        echo '</div>'; // lima

        // ── Provincia Shalom ──
        echo '<div class="wccdpe-group" data-show="provincia_shalom" style="display:none;">';

        woocommerce_form_field( 'billing_departamento', [
            'type'     => 'select',
            'label'    => 'Departamento',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => '— Selecciona departamento —' ],
        ] );

        woocommerce_form_field( 'billing_provincia', [
            'type'     => 'select',
            'label'    => 'Provincia',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => '— Selecciona provincia —' ],
        ] );

        woocommerce_form_field( 'billing_distrito_prov', [
            'type'     => 'select',
            'label'    => 'Distrito',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => '— Selecciona distrito —' ],
        ] );

        woocommerce_form_field( 'billing_agencia_shalom', [
            'type'        => 'text',
            'label'       => 'Nombre de Agencia Shalom',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Ej: Agencia Shalom Trujillo Centro',
        ] );

        woocommerce_form_field( 'billing_shalom_sub_tipo', [
            'type'    => 'radio',
            'label'   => 'Modalidad de pago del envío',
            'required'=> false,
            'class'   => [ 'form-row-wide', 'wccdpe-radio-group', 'wccdpe-radio-inline' ],
            'options' => [
                'prepago'       => 'Pago de envío en checkout (s/15)',
                'contraentrega' => 'Pago de envío en agencia (contraentrega)',
            ],
        ] );

        echo '<p class="wccdpe-shalom-contraentrega-info" style="display:none;">';
        echo '<strong>Contraentrega:</strong> El costo de envío (s/15) se paga directamente en la agencia Shalom al momento de recoger el producto. El producto ya está pagado en esta compra.';
        echo '</p>';

        echo '</div>'; // shalom

        // ── Provincia Olva ──
        echo '<div class="wccdpe-group" data-show="provincia_olva" style="display:none;">';

        woocommerce_form_field( 'billing_olva_departamento', [
            'type'     => 'select',
            'label'    => 'Departamento',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => '— Selecciona departamento —' ],
        ] );

        woocommerce_form_field( 'billing_olva_provincia', [
            'type'     => 'select',
            'label'    => 'Provincia',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => '— Selecciona provincia —' ],
        ] );

        woocommerce_form_field( 'billing_olva_distrito', [
            'type'     => 'select',
            'label'    => 'Distrito',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => '— Selecciona distrito —' ],
        ] );

        woocommerce_form_field( 'billing_olva_sub_tipo', [
            'type'    => 'radio',
            'label'   => '&nbsp;',
            'required'=> false,
            'class'   => [ 'form-row-wide', 'wccdpe-radio-group', 'wccdpe-radio-inline' ],
            'options' => [
                'domicilio' => 'Envío a domicilio',
                'agencia'   => 'Recojo en agencia Olva',
            ],
        ] );

        echo '<div class="wccdpe-olva-domicilio" style="display:none;">';

        woocommerce_form_field( 'billing_olva_direccion', [
            'type'        => 'text',
            'label'       => 'Dirección',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Av. / Jr. / Calle y número',
        ] );

        woocommerce_form_field( 'billing_olva_referencia', [
            'type'        => 'text',
            'label'       => 'Referencia',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Cerca de...',
        ] );

        echo '</div>';

        echo '<div class="wccdpe-olva-agencia" style="display:none;">';

        woocommerce_form_field( 'billing_olva_agencia_nombre', [
            'type'        => 'text',
            'label'       => 'Nombre de Agencia Olva',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Ej: Olva Courier Chiclayo',
        ] );

        echo '</div>';

        echo '</div>'; // olva

        // ── Recojo en Tienda ──
        echo '<div class="wccdpe-group" data-show="recojo_tienda" style="display:none;">';

        $tiendas = WCCDPE_Data::get_tiendas();
        $tienda_options = [ '' => '— Selecciona tienda —' ];
        foreach ( $tiendas as $loc ) {
            $tienda_options[ $loc ] = $loc;
        }

        woocommerce_form_field( 'billing_tienda_especifica', [
            'type'    => 'select',
            'label'   => 'Selecciona la tienda para recojo',
            'required'=> false,
            'class'   => [ 'form-row-wide', 'wccdpe-tienda-select' ],
            'options' => $tienda_options,
        ] );

        echo '<p class="wccdpe-recojo-info">';
        echo '<strong>Tiempo estimado:</strong> Listo en 2-4 días.<br>';
        echo '<strong>Horario:</strong> 10:30 am a 7:30 pm – Lunes a Sábados.';
        echo '</p>';

        echo '</div>'; // recojo

        echo '</div>'; // #wccdpe-delivery-fields

        echo '<div class="wccdpe-info-text">';
        echo '<p>Los envíos se procesan 24 horas después de confirmada la compra. ';
        echo 'Tener en cuenta este plazo al calcular el tiempo de entrega según el tipo de envío seleccionado.</p>';
        echo '<p>Realizamos las distribuciones de lunes a sábado.</p>';
        echo '</div>';
    }
}

new WCCDPE_Shortcode();
