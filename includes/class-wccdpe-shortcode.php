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
            [ 'jquery', 'wc-checkout' ],
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

        // Full checkout needs update_checkout to recalculate fees
        wp_localize_script( 'wccdpe-checkout', 'wccdpe_data', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'wccdpe_nonce' ),
            'lima_districts' => WCCDPE_Data::get_lima_districts_with_prices(),
            'ubigeo'         => WCCDPE_Data::get_ubigeo(),
            'is_shortcode'   => false,
        ] );

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
            'label'       => '&nbsp;',
            'required'    => true,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Email o número de contacto',
            'autocomplete'=> 'email',
        ], $checkout->get_value( 'billing_email' ) );

        // Nombre y Apellidos 50/50
        woocommerce_form_field( 'billing_first_name', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => true,
            'class'       => [ 'form-row-first' ],
            'placeholder' => 'Nombre',
            'autocomplete'=> 'given-name',
        ], $checkout->get_value( 'billing_first_name' ) );

        woocommerce_form_field( 'billing_last_name', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => true,
            'class'       => [ 'form-row-last' ],
            'placeholder' => 'Apellidos',
            'autocomplete'=> 'family-name',
        ], $checkout->get_value( 'billing_last_name' ) );

        // DNI
        woocommerce_form_field( 'billing_dni', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => true,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'DNI',
            'maxlength'   => 8,
        ], $checkout->get_value( 'billing_dni' ) );

        // Teléfono
        woocommerce_form_field( 'billing_phone', [
            'type'        => 'tel',
            'label'       => '&nbsp;',
            'required'    => true,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Teléfono',
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
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Notas del pedido (ej: notas especiales para la entrega)',
        ], '' );
        echo '</div>';

        echo '</div>'; // col-inner
        echo '</div>'; // col--fields

        // ── Contenedor B (50% derecho, fondo #F5F5F5) ──
        echo '<div class="wccdpe-checkout-col wccdpe-checkout-col--order">';
        echo '<div class="wccdpe-checkout-col-inner">';

        echo '<h3 class="wccdpe-order-title">Tu Pedido</h3>';
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
        woocommerce_form_field( 'billing_tipo_entrega', [
            'type'        => 'select',
            'label'       => '&nbsp;',
            'required'    => true,
            'class'       => [ 'form-row-wide', 'wccdpe-tipo-entrega-field' ],
            'placeholder' => 'Selecciona',
            'options'     => $delivery_options,
        ] );

        echo '<div id="wccdpe-delivery-fields">';
        wp_nonce_field( 'wccdpe_nonce', 'wccdpe_nonce', false );

        // ── Lima (24h & 48h) ──
        echo '<div class="wccdpe-group" data-show="lima_24h,lima_48h" style="display:none;">';

        $lima_districts = WCCDPE_Data::get_lima_districts_with_prices();
        $lima_options = array_combine(
            array_keys( $lima_districts ),
            array_keys( $lima_districts )
        );

        woocommerce_form_field( 'billing_lima_distrito', [
            'type'        => 'select',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Distrito',
            'options'     => $lima_options,
        ] );

        echo '<p class="wccdpe-distrito-price" style="display:none;"></p>';

        woocommerce_form_field( 'billing_direccion', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Dirección (Av. / Jr. / Calle y número)',
        ] );

        woocommerce_form_field( 'billing_referencia', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Referencia (Cerca de...)',
        ] );

        woocommerce_form_field( 'billing_vivienda', [
            'type'        => 'select',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Tipo de vivienda',
            'options'     => [
                'casa'       => 'Casa',
                'apartamento'=> 'Apartamento',
                'interior'   => 'Interior / Oficina',
            ],
        ] );

        echo '</div>'; // lima

        // ── Provincia Shalom Prepago ──
        echo '<div class="wccdpe-group" data-show="provincia_shalom_prepago" style="display:none;">';

        echo '<p class="form-row form-row-wide"><select name="billing_departamento" id="billing_departamento" class="select"><option value="">Departamento</option></select></p>';
        echo '<p class="form-row form-row-wide"><select name="billing_provincia" id="billing_provincia" class="select"><option value="">Provincia</option></select></p>';
        echo '<p class="form-row form-row-wide"><select name="billing_distrito_prov" id="billing_distrito_prov" class="select"><option value="">Distrito</option></select></p>';

        woocommerce_form_field( 'billing_agencia_shalom', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Nombre de Agencia Shalom',
        ] );

        echo '<input type="hidden" name="billing_shalom_sub_tipo" value="prepago">';

        echo '</div>'; // shalom prepago

        // ── Provincia Shalom Contraentrega ──
        echo '<div class="wccdpe-group" data-show="provincia_shalom_contra" style="display:none;">';

        echo '<p class="form-row form-row-wide"><select name="billing_departamento_contra" id="billing_departamento_contra" class="select"><option value="">Departamento</option></select></p>';
        echo '<p class="form-row form-row-wide"><select name="billing_provincia_contra" id="billing_provincia_contra" class="select"><option value="">Provincia</option></select></p>';
        echo '<p class="form-row form-row-wide"><select name="billing_distrito_prov_contra" id="billing_distrito_prov_contra" class="select"><option value="">Distrito</option></select></p>';

        woocommerce_form_field( 'billing_agencia_shalom_contra', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Nombre de Agencia Shalom',
        ] );

        echo '<input type="hidden" name="billing_shalom_sub_tipo_contra" value="contraentrega">';

        echo '<p class="wccdpe-shalom-contraentrega-info">';
        echo '<strong>Contraentrega:</strong> El costo de envío (s/15) se paga directamente en la agencia Shalom al momento de recoger el producto. El producto ya está pagado en esta compra.';
        echo '</p>';

        echo '</div>'; // shalom contra

        // ── Provincia Olva ──
        echo '<div class="wccdpe-group" data-show="provincia_olva" style="display:none;">';

        echo '<p class="form-row form-row-wide"><select name="billing_olva_departamento" id="billing_olva_departamento" class="select"><option value="">Departamento</option></select></p>';
        echo '<p class="form-row form-row-wide"><select name="billing_olva_provincia" id="billing_olva_provincia" class="select"><option value="">Provincia</option></select></p>';
        echo '<p class="form-row form-row-wide"><select name="billing_olva_distrito" id="billing_olva_distrito" class="select"><option value="">Distrito</option></select></p>';

        echo '<div class="wccdpe-radio-group">';
        echo '<div class="wccdpe-radio-option">';
        echo '<input type="radio" class="input-radio" value="domicilio" name="billing_olva_sub_tipo" id="billing_olva_sub_tipo_domicilio">';
        echo '<label for="billing_olva_sub_tipo_domicilio">Envío a domicilio</label>';
        echo '</div>';
        echo '<div class="wccdpe-radio-option">';
        echo '<input type="radio" class="input-radio" value="agencia" name="billing_olva_sub_tipo" id="billing_olva_sub_tipo_agencia">';
        echo '<label for="billing_olva_sub_tipo_agencia">Recojo en agencia Olva</label>';
        echo '</div>';
        echo '</div>';

        echo '<div class="wccdpe-olva-domicilio" style="display:none;">';

        woocommerce_form_field( 'billing_olva_direccion', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Dirección (Av. / Jr. / Calle y número)',
        ] );

        woocommerce_form_field( 'billing_olva_referencia', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Referencia (Cerca de...)',
        ] );

        echo '</div>';

        echo '<div class="wccdpe-olva-agencia" style="display:none;">';

        woocommerce_form_field( 'billing_olva_agencia_nombre', [
            'type'        => 'text',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Nombre de Agencia Olva',
        ] );

        echo '</div>';

        echo '</div>'; // olva

        // ── Recojo en Tienda ──
        echo '<div class="wccdpe-group" data-show="recojo_tienda" style="display:none;">';

        $tiendas = WCCDPE_Data::get_tiendas();
        $tienda_options = [];
        foreach ( $tiendas as $loc ) {
            $tienda_options[ $loc ] = $loc;
        }

        woocommerce_form_field( 'billing_tienda_especifica', [
            'type'        => 'select',
            'label'       => '&nbsp;',
            'required'    => false,
            'class'       => [ 'form-row-wide', 'wccdpe-tienda-select' ],
            'placeholder' => 'Selecciona tienda',
            'options'     => $tienda_options,
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
