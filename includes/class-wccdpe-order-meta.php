<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Order_Meta {

    /**
     * Fields that are ALWAYS saved regardless of delivery type.
     */
    private $global_fields = [
        'billing_dni',
        'billing_tipo_entrega',
    ];

    /**
     * Map: tipo_entrega => fields to save for that type.
     */
    private $fields_by_tipo = [
        'lima_24h' => [
            'billing_lima_distrito',
            'billing_direccion',
            'billing_referencia',
            'billing_vivienda',
        ],
        'lima_48h' => [
            'billing_lima_distrito',
            'billing_direccion',
            'billing_referencia',
            'billing_vivienda',
        ],
        'provincia_shalom_prepago' => [
            'billing_departamento',
            'billing_provincia',
            'billing_distrito_prov',
            'billing_agencia_shalom',
            'billing_shalom_sub_tipo',
        ],
        'provincia_shalom_contra' => [
            'billing_departamento_contra',
            'billing_provincia_contra',
            'billing_distrito_prov_contra',
            'billing_agencia_shalom_contra',
            'billing_shalom_sub_tipo_contra',
        ],
        'provincia_olva' => [
            'billing_olva_departamento',
            'billing_olva_provincia',
            'billing_olva_distrito',
            'billing_olva_sub_tipo',
            'billing_olva_direccion',
            'billing_olva_referencia',
            'billing_olva_agencia_nombre',
        ],
        'recojo_tienda' => [
            'billing_tienda_especifica',
        ],
    ];

    /**
     * Map: tipo_entrega => fields to display in admin & emails (with labels).
     */
    private $display_by_tipo = [
        'lima_24h' => [
            '_billing_lima_distrito' => 'Distrito (Lima)',
            '_billing_direccion'    => 'Dirección',
            '_billing_referencia'   => 'Referencia',
            '_billing_vivienda'     => 'Tipo de vivienda',
        ],
        'lima_48h' => [
            '_billing_lima_distrito' => 'Distrito (Lima)',
            '_billing_direccion'    => 'Dirección',
            '_billing_referencia'   => 'Referencia',
            '_billing_vivienda'     => 'Tipo de vivienda',
        ],
        'provincia_shalom_prepago' => [
            '_billing_departamento'    => 'Departamento',
            '_billing_provincia'       => 'Provincia',
            '_billing_distrito_prov'   => 'Distrito',
            '_billing_agencia_shalom'  => 'Agencia Shalom',
            '_billing_shalom_sub_tipo' => 'Modalidad',
        ],
        'provincia_shalom_contra' => [
            '_billing_departamento_contra'    => 'Departamento',
            '_billing_provincia_contra'       => 'Provincia',
            '_billing_distrito_prov_contra'   => 'Distrito',
            '_billing_agencia_shalom_contra'  => 'Agencia Shalom',
            '_billing_shalom_sub_tipo_contra' => 'Modalidad',
        ],
        'provincia_olva' => [
            '_billing_olva_departamento'   => 'Departamento',
            '_billing_olva_provincia'      => 'Provincia',
            '_billing_olva_distrito'       => 'Distrito',
            '_billing_olva_sub_tipo'       => 'Tipo recepción',
            '_billing_olva_direccion'      => 'Dirección',
            '_billing_olva_referencia'     => 'Referencia',
            '_billing_olva_agencia_nombre' => 'Agencia Olva',
        ],
        'recojo_tienda' => [
            '_billing_tienda_especifica' => 'Tienda de recojo',
        ],
    ];

    public function __construct() {
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_meta' ] );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_admin_meta' ] );
        add_filter( 'woocommerce_email_order_meta_fields', [ $this, 'email_meta_fields' ], 10, 3 );
    }

    /**
     * Save only the fields relevant to the selected delivery type.
     */
    public function save_meta( $order_id ) {
        $tipo = isset( $_POST['billing_tipo_entrega'] ) ? sanitize_text_field( $_POST['billing_tipo_entrega'] ) : '';

        // Always save global fields
        foreach ( $this->global_fields as $key ) {
            if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
                update_post_meta( $order_id, '_' . $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }

        // Only save fields that belong to the selected tipo
        if ( $tipo && isset( $this->fields_by_tipo[ $tipo ] ) ) {
            $fields_to_save = $this->fields_by_tipo[ $tipo ];

            // Olva: filter by sub_tipo — only keep address OR agency fields, not both.
            if ( $tipo === 'provincia_olva' ) {
                $sub = isset( $_POST['billing_olva_sub_tipo'] ) ? sanitize_text_field( $_POST['billing_olva_sub_tipo'] ) : '';
                $exclude = [];
                if ( $sub === 'domicilio' ) {
                    $exclude = [ 'billing_olva_agencia_nombre' ];
                } elseif ( $sub === 'agencia' ) {
                    $exclude = [ 'billing_olva_direccion', 'billing_olva_referencia' ];
                }
                $fields_to_save = array_values( array_diff( $fields_to_save, $exclude ) );
                // Also delete any stale values from the excluded side
                foreach ( $exclude as $key ) {
                    delete_post_meta( $order_id, '_' . $key );
                }
            }

            foreach ( $fields_to_save as $key ) {
                if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
                    update_post_meta( $order_id, '_' . $key, sanitize_text_field( $_POST[ $key ] ) );
                }
            }
        }

        // Delete meta from OTHER tipos that WooCommerce core may have auto-saved
        // (all billing_* fields registered via woocommerce_form_field get persisted
        // regardless of which delivery group was visible on the form).
        foreach ( $this->fields_by_tipo as $other_tipo => $other_fields ) {
            if ( $other_tipo === $tipo ) {
                continue;
            }
            foreach ( $other_fields as $key ) {
                delete_post_meta( $order_id, '_' . $key );
            }
        }
    }

    /**
     * Display delivery info in admin order page, filtered by tipo.
     */
    public function display_admin_meta( $order ) {
        $order_id = $order->get_id();
        $tipo = get_post_meta( $order_id, '_billing_tipo_entrega', true );

        if ( ! $tipo ) return;

        $labels = WCCDPE_Data::get_delivery_types();
        $tipo_label = isset( $labels[ $tipo ] ) ? $labels[ $tipo ] : $tipo;

        echo '<div class="wccdpe-admin-meta" style="margin-top:15px;padding:12px;background:#f8f8f8;border-left:4px solid #7f54b3;">';
        echo '<h3 style="margin:0 0 10px;">Datos de Entrega</h3>';

        // DNI
        $dni = get_post_meta( $order_id, '_billing_dni', true );
        if ( $dni ) {
            echo '<p><strong>DNI:</strong> ' . esc_html( $dni ) . '</p>';
        }

        // Tipo de entrega
        echo '<p><strong>Tipo de entrega:</strong> ' . esc_html( $tipo_label ) . '</p>';

        // Only show fields relevant to this tipo
        if ( isset( $this->display_by_tipo[ $tipo ] ) ) {
            foreach ( $this->display_by_tipo[ $tipo ] as $meta_key => $label ) {
                $val = get_post_meta( $order_id, $meta_key, true );
                if ( $val ) {
                    echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $val ) . '</p>';
                }
            }
        }

        echo '</div>';
    }

    /**
     * Add delivery info to order emails, filtered by tipo.
     */
    public function email_meta_fields( $fields, $sent_to_admin, $order ) {
        $order_id = $order->get_id();
        $tipo = get_post_meta( $order_id, '_billing_tipo_entrega', true );

        if ( ! $tipo ) return $fields;

        // DNI in all emails
        $dni = get_post_meta( $order_id, '_billing_dni', true );
        if ( $dni ) {
            $fields['billing_dni'] = [
                'label' => 'DNI',
                'value' => $dni,
            ];
        }

        // Tipo de entrega
        $labels = WCCDPE_Data::get_delivery_types();
        $fields['tipo_entrega'] = [
            'label' => 'Tipo de entrega',
            'value' => isset( $labels[ $tipo ] ) ? $labels[ $tipo ] : $tipo,
        ];

        // Only include fields relevant to this tipo
        if ( isset( $this->display_by_tipo[ $tipo ] ) ) {
            foreach ( $this->display_by_tipo[ $tipo ] as $meta_key => $label ) {
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
