<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Fields {

    public function __construct() {
        // Add custom fields to billing section
        add_filter( 'woocommerce_checkout_fields', [ $this, 'customize_checkout_fields' ], 20 );
        // Render additional dynamic fields after billing
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'render_delivery_fields' ] );
        // Info text at the end
        add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'render_info_text' ] );
    }

    /**
     * Customize billing fields: add email/contacto and tipo_entrega.
     */
    public function customize_checkout_fields( $fields ) {
        // Ensure email is first
        if ( isset( $fields['billing']['billing_email'] ) ) {
            $fields['billing']['billing_email']['priority'] = 5;
            $fields['billing']['billing_email']['label'] = 'Email o número de contacto';
            $fields['billing']['billing_email']['placeholder'] = 'correo@ejemplo.com o 9XXXXXXXX';
        }

        // Add tipo_entrega selector right after core billing fields
        $fields['billing']['billing_tipo_entrega'] = [
            'type'     => 'select',
            'label'    => 'Tipo de entrega',
            'required' => true,
            'class'    => [ 'form-row-wide', 'wccdpe-tipo-entrega-field' ],
            'options'  => WCCDPE_Data::get_delivery_types(),
            'priority' => 200,
        ];

        return $fields;
    }

    /**
     * Render all conditional delivery fields (hidden by default, JS controls visibility).
     */
    public function render_delivery_fields( $checkout ) {
        echo '<div id="wccdpe-delivery-fields">';
        wp_nonce_field( 'wccdpe_nonce', 'wccdpe_nonce', false );

        // ── Lima fields (24h & 48h) ──
        echo '<div class="wccdpe-group" data-show="lima_24h,lima_48h" style="display:none;">';

        woocommerce_form_field( 'billing_lima_distrito', [
            'type'     => 'select',
            'label'    => 'Distrito de Lima',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => array_merge(
                [ '' => 'Selecciona' ],
                array_combine(
                    array_keys( WCCDPE_Data::get_lima_districts_with_prices() ),
                    array_keys( WCCDPE_Data::get_lima_districts_with_prices() )
                )
            ),
        ], $checkout->get_value( 'billing_lima_distrito' ) );

        echo '<p class="wccdpe-distrito-price" style="display:none;"></p>';

        woocommerce_form_field( 'billing_direccion', [
            'type'        => 'text',
            'label'       => 'Dirección',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Av. / Jr. / Calle y número',
        ], $checkout->get_value( 'billing_direccion' ) );

        woocommerce_form_field( 'billing_referencia', [
            'type'        => 'text',
            'label'       => 'Referencia',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Cerca de...',
        ], $checkout->get_value( 'billing_referencia' ) );

        woocommerce_form_field( 'billing_vivienda', [
            'type'    => 'select',
            'label'   => 'Tipo de vivienda',
            'required'=> false,
            'class'   => [ 'form-row-wide' ],
            'options' => [
                ''           => 'Selecciona',
                'casa'       => 'Casa',
                'apartamento'=> 'Apartamento',
                'interior'   => 'Interior / Oficina',
            ],
        ], $checkout->get_value( 'billing_vivienda' ) );

        echo '</div>'; // lima

        // ── Provincia Shalom ──
        echo '<div class="wccdpe-group" data-show="provincia_shalom" style="display:none;">';

        // UBIGEO selects rendered empty — JS populates them
        woocommerce_form_field( 'billing_departamento', [
            'type'     => 'select',
            'label'    => 'Departamento',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => 'Selecciona' ],
        ], $checkout->get_value( 'billing_departamento' ) );

        woocommerce_form_field( 'billing_provincia', [
            'type'     => 'select',
            'label'    => 'Provincia',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => 'Selecciona' ],
        ], $checkout->get_value( 'billing_provincia' ) );

        woocommerce_form_field( 'billing_distrito_prov', [
            'type'     => 'select',
            'label'    => 'Distrito',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => 'Selecciona' ],
        ], $checkout->get_value( 'billing_distrito_prov' ) );

        woocommerce_form_field( 'billing_agencia_shalom', [
            'type'        => 'text',
            'label'       => 'Nombre de Agencia Shalom',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Ej: Agencia Shalom Trujillo Centro',
        ], $checkout->get_value( 'billing_agencia_shalom' ) );

        woocommerce_form_field( 'billing_shalom_sub_tipo', [
            'type'    => 'radio',
            'label'   => 'Modalidad de pago del envío',
            'required'=> false,
            'class'   => [ 'form-row-wide', 'wccdpe-radio-group' ],
            'options' => [
                'prepago'       => 'Pago de envío en checkout (s/15)',
                'contraentrega' => 'Pago de envío en agencia (contraentrega)',
            ],
        ], $checkout->get_value( 'billing_shalom_sub_tipo' ) );

        echo '<p class="wccdpe-shalom-contraentrega-info" style="display:none;">';
        echo '<strong>Contraentrega:</strong> El costo de envío (s/15) se paga directamente en la agencia Shalom al momento de recoger el producto. El producto ya está pagado en esta compra.';
        echo '</p>';

        echo '</div>';

        // ── Provincia Olva ──
        echo '<div class="wccdpe-group" data-show="provincia_olva" style="display:none;">';

        woocommerce_form_field( 'billing_olva_departamento', [
            'type'     => 'select',
            'label'    => 'Departamento',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => 'Selecciona' ],
        ], $checkout->get_value( 'billing_olva_departamento' ) );

        woocommerce_form_field( 'billing_olva_provincia', [
            'type'     => 'select',
            'label'    => 'Provincia',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => 'Selecciona' ],
        ], $checkout->get_value( 'billing_olva_provincia' ) );

        woocommerce_form_field( 'billing_olva_distrito', [
            'type'     => 'select',
            'label'    => 'Distrito',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'options'  => [ '' => 'Selecciona' ],
        ], $checkout->get_value( 'billing_olva_distrito' ) );

        woocommerce_form_field( 'billing_olva_sub_tipo', [
            'type'    => 'radio',
            'label'   => '&nbsp;',
            'required'=> false,
            'class'   => [ 'form-row-wide', 'wccdpe-radio-group' ],
            'options' => [
                'domicilio' => 'Envío a domicilio',
                'agencia'   => 'Recojo en agencia Olva',
            ],
        ], $checkout->get_value( 'billing_olva_sub_tipo' ) );

        // Domicilio sub-fields
        echo '<div class="wccdpe-olva-domicilio" style="display:none;">';

        woocommerce_form_field( 'billing_olva_direccion', [
            'type'        => 'text',
            'label'       => 'Dirección',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Av. / Jr. / Calle y número',
        ], $checkout->get_value( 'billing_olva_direccion' ) );

        woocommerce_form_field( 'billing_olva_referencia', [
            'type'        => 'text',
            'label'       => 'Referencia',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Cerca de...',
        ], $checkout->get_value( 'billing_olva_referencia' ) );

        echo '</div>';

        // Agencia sub-field
        echo '<div class="wccdpe-olva-agencia" style="display:none;">';

        woocommerce_form_field( 'billing_olva_agencia_nombre', [
            'type'        => 'text',
            'label'       => 'Nombre de Agencia Olva',
            'required'    => false,
            'class'       => [ 'form-row-wide' ],
            'placeholder' => 'Ej: Olva Courier Chiclayo',
        ], $checkout->get_value( 'billing_olva_agencia_nombre' ) );

        echo '</div>';

        echo '</div>'; // olva

        // ── Recojo en Tienda ──
        echo '<div class="wccdpe-group" data-show="recojo_tienda" style="display:none;">';

        $tiendas = WCCDPE_Data::get_tiendas();
        $tienda_options = [ '' => 'Selecciona' ];
        foreach ( $tiendas as $loc ) {
            $tienda_options[ $loc ] = $loc;
        }

        woocommerce_form_field( 'billing_tienda_especifica', [
            'type'        => 'select',
            'label'       => 'Selecciona la tienda para recojo',
            'required'    => false,
            'class'       => [ 'form-row-wide', 'wccdpe-tienda-select' ],
            'options'     => $tienda_options,
        ], $checkout->get_value( 'billing_tienda_especifica' ) );

        echo '<p class="wccdpe-recojo-info">';
        echo '<strong>Tiempo estimado:</strong> Listo en 2-4 días.<br>';
        echo '<strong>Horario:</strong> 10:30 am a 7:30 pm – Lunes a Sábados.';
        echo '</p>';

        echo '</div>'; // recojo

        echo '</div>'; // #wccdpe-delivery-fields
    }

    /**
     * Fixed informational text at checkout bottom.
     */
    public function render_info_text() {
        echo '<div class="wccdpe-info-text">';
        echo '<p>Los envíos se procesan 24 horas después de confirmada la compra. ';
        echo 'Tener en cuenta este plazo al calcular el tiempo de entrega según el tipo de envío seleccionado.</p>';
        echo '<p>Realizamos las distribuciones de lunes a sábado.</p>';
        echo '</div>';
    }
}

new WCCDPE_Fields();
