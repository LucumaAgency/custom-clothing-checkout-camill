<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCCDPE_Data {

    /**
     * Lima districts with delivery prices (24h).
     */
    public static function get_lima_districts_with_prices() {
        return [
            // s/12
            'Ate'                       => 12,
            'Barranco'                  => 12,
            'Breña'                     => 12,
            'Carabayllo'                => 12,
            'Chorrillos'                => 12,
            'Comas'                     => 12,
            'El Agustino'               => 12,
            'Independencia'             => 12,
            'Jesús María'               => 12,
            'La Molina'                 => 12,
            'La Victoria'               => 12,
            'Lima (Cercado)'            => 12,
            'Lince'                     => 12,
            'Los Olivos'                => 12,
            'Magdalena del Mar'         => 12,
            'Miraflores'                => 12,
            'Pueblo Libre'              => 12,
            'Puente Piedra'             => 12,
            'Rímac'                     => 12,
            'San Borja'                 => 12,
            'San Isidro'                => 12,
            'San Juan de Lurigancho'    => 12,
            'San Juan de Miraflores'    => 12,
            'San Luis'                  => 12,
            'San Martín de Porres'      => 12,
            'San Miguel'                => 12,
            'Santa Anita'               => 12,
            'Santiago de Surco'         => 12,
            'Surquillo'                 => 12,
            'Villa El Salvador'         => 12,
            'Villa María del Triunfo'   => 12,
            // s/14
            'Lurigancho-Chosica'        => 14,
            'Lurín'                     => 14,
            // s/17
            'Ancón'                     => 17,
            'Chaclacayo'                => 17,
            'Cieneguilla'               => 17,
            'Punta Hermosa'             => 17,
            'Santa Rosa'                => 17,
            // s/19
            'Pachacamac'                => 19,
            // s/21
            'Punta Negra'               => 21,
            // s/22
            'San Bartolo'               => 22,
            // s/25
            'Santa María del Mar'       => 25,
            // s/28
            'Pucusana'                  => 28,
        ];
    }

    /**
     * Simplified UBIGEO structure for Peru.
     * Departamento => Provincia => [Distritos]
     */
    public static function get_ubigeo() {
        static $cache = null;
        if ( $cache !== null ) {
            return $cache;
        }

        $cache = get_transient( 'wccdpe_ubigeo' );
        if ( $cache !== false ) {
            return $cache;
        }

        $file = WCCDPE_PLUGIN_DIR . 'includes/data/ubigeo.json';
        if ( file_exists( $file ) ) {
            $cache = json_decode( file_get_contents( $file ), true );
            if ( is_array( $cache ) ) {
                set_transient( 'wccdpe_ubigeo', $cache, 7 * DAY_IN_SECONDS );
                return $cache;
            }
        }

        $cache = [];
        return $cache;
    }

    /**
     * Store info for pickup.
     */
    public static function get_tiendas() {
        return [
            'Galería El Dorado, tda. 208 – Jr. Agustín Gamarra 906',
            'Galería Damero, tda. 103 – Jr. Agustín Gamarra 939',
            'Galería Damero, tda. 126 – Jr. Agustín Gamarra 939',
            'Galería Ya!, tda. 56 – Jr. Agustín Gamarra 1043',
            'Galería San Pedro, tda. 121 – Jr. Agustín Gamarra 1160',
            'C.C Gama, tda. 167 – Jr. Agustín Gamarra 1275',
            'C.C Estilo, tda. 211 – Jr. Agustín Gamarra 949',
        ];
    }

    /**
     * Delivery type options.
     */
    public static function get_delivery_types() {
        return [
            'lima_24h'                  => 'Lima – Delivery 24 horas',
            'lima_48h'                  => 'Lima – Delivery 48 horas',
            'provincia_shalom_prepago'  => 'Provincia – Shalom (Prepago)',
            'provincia_shalom_contra'   => 'Provincia – Shalom (Contraentrega)',
            'provincia_olva'            => 'Provincia – Olva Courier',
            'recojo_tienda'             => 'Recojo en Tienda',
        ];
    }
}
