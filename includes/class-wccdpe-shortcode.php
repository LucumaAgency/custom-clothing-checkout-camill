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
    // [wccdpe_delivery_form] — Solo formulario de entrega
    // ─────────────────────────────────────────────

    public function render_delivery_form( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<p>WooCommerce es requerido para mostrar este formulario.</p>';
        }

        $this->enqueue_assets();

        ob_start();
        echo '<div id="wccdpe-shortcode-wrapper" class="wccdpe-shortcode-form">';
        $this->output_delivery_fields();
        echo '</div>';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // [wccdpe_order_review] — Resumen del pedido
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
        echo '<div id="wccdpe-order-review-wrapper" class="wccdpe-shortcode-form">';
        echo '<h3>Tu pedido</h3>';
        woocommerce_order_review();
        echo '</div>';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // [wccdpe_payment_methods] — Métodos de pago
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
        echo '<div id="wccdpe-payment-wrapper" class="wccdpe-shortcode-form">';
        woocommerce_checkout_payment();
        echo '</div>';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // [wccdpe_full_checkout] — Checkout completo
    // ─────────────────────────────────────────────

    public function render_full_checkout( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<p>WooCommerce es requerido.</p>';
        }

        if ( is_null( WC()->cart ) || WC()->cart->is_empty() ) {
            return '<div class="wccdpe-empty-cart">
                <p>Tu carrito est&aacute; vac&iacute;o. Agrega productos antes de continuar.</p>
                <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '" class="button">Ir a la tienda</a>
            </div>';
        }

        $this->enqueue_assets();
        $this->enqueue_wc_checkout_assets();

        $checkout = WC()->checkout();

        ob_start();

        echo '<div id="wccdpe-full-checkout" class="wccdpe-shortcode-form woocommerce">';

        // WooCommerce notices
        wc_print_notices();

        // Open checkout form
        echo '<form name="checkout" method="post" class="checkout woocommerce-checkout" action="' . esc_url( wc_get_checkout_url() ) . '" enctype="multipart/form-data" novalidate="novalidate">';

        // ── Columna izquierda: Datos del cliente + Entrega ──
        echo '<div class="wccdpe-checkout-columns">';

        echo '<div class="wccdpe-checkout-col wccdpe-checkout-col--fields">';

        // Billing fields (nombre, email, teléfono)
        echo '<h3>Datos del cliente</h3>';
        echo '<div class="wccdpe-billing-fields">';

        $billing_fields = $checkout->get_checkout_fields( 'billing' );
        foreach ( $billing_fields as $key => $field ) {
            // Skip tipo_entrega here, we render it in delivery section
            if ( $key === 'billing_tipo_entrega' ) {
                continue;
            }
            woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
        }

        echo '</div>';

        // Delivery fields
        echo '<h3>Tipo de entrega</h3>';
        $this->output_delivery_fields();

        echo '</div>'; // col--fields

        // ── Columna derecha: Pedido + Pago ──
        echo '<div class="wccdpe-checkout-col wccdpe-checkout-col--order">';

        // Order review
        echo '<h3 id="order_review_heading">Tu pedido</h3>';
        echo '<div id="order_review" class="woocommerce-checkout-review-order">';
        woocommerce_order_review();
        echo '</div>';

        // Payment methods
        woocommerce_checkout_payment();

        echo '</div>'; // col--order

        echo '</div>'; // columns

        echo '</form>';
        echo '</div>'; // #wccdpe-full-checkout

        return ob_get_clean();
    }

    // ─────────────────────────────────────────────
    // Helper: Output delivery fields HTML
    // ─────────────────────────────────────────────

    private function output_delivery_fields() {

        // Delivery type selector
        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_tipo_entrega">Tipo de entrega <abbr class="required" title="obligatorio">*</abbr></label>';
        echo '<select id="billing_tipo_entrega" name="billing_tipo_entrega" class="wccdpe-select">';
        foreach ( WCCDPE_Data::get_delivery_types() as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div id="wccdpe-delivery-fields">';

        // ── Lima (24h & 48h) ──
        echo '<div class="wccdpe-group" data-show="lima_24h,lima_48h" style="display:none;">';

        $lima_districts = WCCDPE_Data::get_lima_districts_with_prices();

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_lima_distrito">Distrito de Lima</label>';
        echo '<select id="billing_lima_distrito" name="billing_lima_distrito" class="wccdpe-select">';
        echo '<option value="">— Selecciona distrito —</option>';
        foreach ( $lima_districts as $district => $price ) {
            echo '<option value="' . esc_attr( $district ) . '">' . esc_html( $district ) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<p class="wccdpe-distrito-price" style="display:none;"></p>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_direccion">Direcci&oacute;n</label>';
        echo '<input type="text" id="billing_direccion" name="billing_direccion" placeholder="Av. / Jr. / Calle y n&uacute;mero" class="wccdpe-input">';
        echo '</div>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_referencia">Referencia</label>';
        echo '<input type="text" id="billing_referencia" name="billing_referencia" placeholder="Cerca de..." class="wccdpe-input">';
        echo '</div>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_vivienda">Tipo de vivienda</label>';
        echo '<select id="billing_vivienda" name="billing_vivienda" class="wccdpe-select">';
        echo '<option value="">— Selecciona —</option>';
        echo '<option value="casa">Casa</option>';
        echo '<option value="apartamento">Apartamento</option>';
        echo '<option value="interior">Interior / Oficina</option>';
        echo '</select>';
        echo '</div>';

        echo '</div>'; // lima

        // ── Provincia Shalom ──
        echo '<div class="wccdpe-group" data-show="provincia_shalom" style="display:none;">';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_departamento">Departamento</label>';
        echo '<select id="billing_departamento" name="billing_departamento" class="wccdpe-select">';
        echo '<option value="">— Selecciona departamento —</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_provincia">Provincia</label>';
        echo '<select id="billing_provincia" name="billing_provincia" class="wccdpe-select">';
        echo '<option value="">— Selecciona provincia —</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_distrito_prov">Distrito</label>';
        echo '<select id="billing_distrito_prov" name="billing_distrito_prov" class="wccdpe-select">';
        echo '<option value="">— Selecciona distrito —</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_agencia_shalom">Nombre de Agencia Shalom</label>';
        echo '<input type="text" id="billing_agencia_shalom" name="billing_agencia_shalom" placeholder="Ej: Agencia Shalom Trujillo Centro" class="wccdpe-input">';
        echo '</div>';

        echo '<div class="wccdpe-field-group wccdpe-radio-group wccdpe-radio-inline">';
        echo '<label>Modalidad de pago del env&iacute;o</label>';
        echo '<label><input type="radio" name="billing_shalom_sub_tipo" value="prepago"> Pago de env&iacute;o en checkout (s/15)</label>';
        echo '<label><input type="radio" name="billing_shalom_sub_tipo" value="contraentrega"> Pago de env&iacute;o en agencia (contraentrega)</label>';
        echo '</div>';

        echo '<p class="wccdpe-shalom-contraentrega-info" style="display:none;">';
        echo '<strong>Contraentrega:</strong> El costo de env&iacute;o (s/15) se paga directamente en la agencia Shalom al momento de recoger el producto. El producto ya est&aacute; pagado en esta compra.';
        echo '</p>';

        echo '</div>'; // shalom

        // ── Provincia Olva ──
        echo '<div class="wccdpe-group" data-show="provincia_olva" style="display:none;">';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_olva_departamento">Departamento</label>';
        echo '<select id="billing_olva_departamento" name="billing_olva_departamento" class="wccdpe-select">';
        echo '<option value="">— Selecciona departamento —</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_olva_provincia">Provincia</label>';
        echo '<select id="billing_olva_provincia" name="billing_olva_provincia" class="wccdpe-select">';
        echo '<option value="">— Selecciona provincia —</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_olva_distrito">Distrito</label>';
        echo '<select id="billing_olva_distrito" name="billing_olva_distrito" class="wccdpe-select">';
        echo '<option value="">— Selecciona distrito —</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="wccdpe-field-group wccdpe-radio-group wccdpe-radio-inline">';
        echo '<label>&nbsp;</label>';
        echo '<label><input type="radio" name="billing_olva_sub_tipo" value="domicilio"> Env&iacute;o a domicilio</label>';
        echo '<label><input type="radio" name="billing_olva_sub_tipo" value="agencia"> Recojo en agencia Olva</label>';
        echo '</div>';

        echo '<div class="wccdpe-olva-domicilio" style="display:none;">';
        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_olva_direccion">Direcci&oacute;n</label>';
        echo '<input type="text" id="billing_olva_direccion" name="billing_olva_direccion" placeholder="Av. / Jr. / Calle y n&uacute;mero" class="wccdpe-input">';
        echo '</div>';
        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_olva_referencia">Referencia</label>';
        echo '<input type="text" id="billing_olva_referencia" name="billing_olva_referencia" placeholder="Cerca de..." class="wccdpe-input">';
        echo '</div>';
        echo '</div>';

        echo '<div class="wccdpe-olva-agencia" style="display:none;">';
        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_olva_agencia_nombre">Nombre de Agencia Olva</label>';
        echo '<input type="text" id="billing_olva_agencia_nombre" name="billing_olva_agencia_nombre" placeholder="Ej: Olva Courier Chiclayo" class="wccdpe-input">';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // olva

        // ── Recojo en Tienda ──
        echo '<div class="wccdpe-group" data-show="recojo_tienda" style="display:none;">';

        $tiendas = WCCDPE_Data::get_tiendas();

        echo '<div class="wccdpe-field-group">';
        echo '<label for="billing_tienda_especifica">Selecciona la tienda para recojo</label>';
        echo '<select id="billing_tienda_especifica" name="billing_tienda_especifica" class="wccdpe-select">';
        echo '<option value="">— Selecciona tienda —</option>';
        foreach ( $tiendas as $tienda ) {
            echo '<option value="' . esc_attr( $tienda ) . '">' . esc_html( $tienda ) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<p class="wccdpe-recojo-info">';
        echo '<strong>Tiempo estimado:</strong> Listo en 2-4 d&iacute;as.<br>';
        echo '<strong>Horario:</strong> 10:30 am a 7:30 pm – Lunes a S&aacute;bados.';
        echo '</p>';

        echo '</div>'; // recojo

        echo '</div>'; // #wccdpe-delivery-fields

        // Info text
        echo '<div class="wccdpe-info-text">';
        echo '<p>Los env&iacute;os se procesan 24 horas despu&eacute;s de confirmada la compra. ';
        echo 'Tener en cuenta este plazo al calcular el tiempo de entrega seg&uacute;n el tipo de env&iacute;o seleccionado.</p>';
        echo '<p>Realizamos las distribuciones de lunes a s&aacute;bado.</p>';
        echo '</div>';
    }
}

new WCCDPE_Shortcode();
