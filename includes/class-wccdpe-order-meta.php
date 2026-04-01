<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Order_Meta {

    /**
     * All custom field keys we may save.
     */
    private $meta_fields = [
        'billing_dni',
        'billing_tipo_entrega',
        'billing_lima_distrito',
        'billing_direccion',
        'billing_referencia',
        'billing_vivienda',
        'billing_departamento',
        'billing_provincia',
        'billing_distrito_prov',
        'billing_agencia_shalom',
        'billing_shalom_sub_tipo',
        'billing_olva_departamento',
        'billing_olva_provincia',
        'billing_olva_distrito',
        'billing_olva_sub_tipo',
        'billing_olva_direccion',
        'billing_olva_referencia',
        'billing_olva_agencia_nombre',
        'billing_tienda_especifica',
    ];

    public function __construct() {
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_meta' ] );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_admin_meta' ] );
        add_filter( 'woocommerce_email_order_meta_fields', [ $this, 'email_meta_fields' ], 10, 3 );
    }

    /**
     * Save all relevant fields to order meta.
     */
    public function save_meta( $order_id ) {
        foreach ( $this->meta_fields as $key ) {
            if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
                update_post_meta( $order_id, '_' . $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }
    }

    /**
     * Display in admin order page.
     */
    public function display_admin_meta( $order ) {
        $order_id = $order->get_id();
        $tipo = get_post_meta( $order_id, '_billing_tipo_entrega', true );

        if ( ! $tipo ) return;

        $labels = WCCDPE_Data::get_delivery_types();
        $tipo_label = isset( $labels[ $tipo ] ) ? $labels[ $tipo ] : $tipo;

        echo '<div class="wccdpe-admin-meta">';
        echo '<h3>Datos de Entrega Personalizado</h3>';
        $dni = get_post_meta( $order_id, '_billing_dni', true );
        if ( $dni ) {
            echo '<p><strong>DNI:</strong> ' . esc_html( $dni ) . '</p>';
        }
        echo '<p><strong>Tipo de entrega:</strong> ' . esc_html( $tipo_label ) . '</p>';

        $display_fields = [
            '_billing_lima_distrito'        => 'Distrito (Lima)',
            '_billing_direccion'            => 'Dirección',
            '_billing_referencia'           => 'Referencia',
            '_billing_vivienda'             => 'Tipo de vivienda',
            '_billing_departamento'         => 'Departamento',
            '_billing_provincia'            => 'Provincia',
            '_billing_distrito_prov'        => 'Distrito',
            '_billing_agencia_shalom'       => 'Agencia Shalom',
            '_billing_shalom_sub_tipo'      => 'Modalidad Shalom',
            '_billing_olva_departamento'    => 'Departamento (Olva)',
            '_billing_olva_provincia'       => 'Provincia (Olva)',
            '_billing_olva_distrito'        => 'Distrito (Olva)',
            '_billing_olva_sub_tipo'        => 'Tipo recepción Olva',
            '_billing_olva_direccion'       => 'Dirección (Olva)',
            '_billing_olva_referencia'      => 'Referencia (Olva)',
            '_billing_olva_agencia_nombre'  => 'Agencia Olva',
            '_billing_tienda_especifica'    => 'Tienda de recojo',
        ];

        foreach ( $display_fields as $meta_key => $label ) {
            $val = get_post_meta( $order_id, $meta_key, true );
            if ( $val ) {
                echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $val ) . '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * Add delivery info to order emails.
     */
    public function email_meta_fields( $fields, $sent_to_admin, $order ) {
        $order_id = $order->get_id();
        $tipo = get_post_meta( $order_id, '_billing_tipo_entrega', true );

        if ( ! $tipo ) return $fields;

        $labels = WCCDPE_Data::get_delivery_types();
        $fields['tipo_entrega'] = [
            'label' => 'Tipo de entrega',
            'value' => isset( $labels[ $tipo ] ) ? $labels[ $tipo ] : $tipo,
        ];

        // Add relevant fields based on type
        $conditional_meta = [
            'lima_24h'          => [ '_billing_lima_distrito' => 'Distrito', '_billing_direccion' => 'Dirección', '_billing_referencia' => 'Referencia' ],
            'lima_48h'          => [ '_billing_lima_distrito' => 'Distrito', '_billing_direccion' => 'Dirección', '_billing_referencia' => 'Referencia' ],
            'provincia_shalom'  => [ '_billing_departamento' => 'Departamento', '_billing_provincia' => 'Provincia', '_billing_distrito_prov' => 'Distrito', '_billing_agencia_shalom' => 'Agencia Shalom', '_billing_shalom_sub_tipo' => 'Modalidad pago envío' ],
            'provincia_olva'    => [ '_billing_olva_departamento' => 'Departamento', '_billing_olva_provincia' => 'Provincia', '_billing_olva_distrito' => 'Distrito', '_billing_olva_sub_tipo' => 'Tipo recepción', '_billing_olva_direccion' => 'Dirección', '_billing_olva_agencia_nombre' => 'Agencia Olva' ],
            'recojo_tienda'     => [ '_billing_tienda_especifica' => 'Tienda de recojo' ],
        ];

        if ( isset( $conditional_meta[ $tipo ] ) ) {
            foreach ( $conditional_meta[ $tipo ] as $meta_key => $label ) {
                $val = get_post_meta( $order_id, $meta_key, true );
                if ( $val ) {
                    $fields[ $meta_key ] = [
                        'label' => $label,
                        'value' => $val,
                    ];
                }
            }
        }

        return $fields;
    }
}

new WCCDPE_Order_Meta();
