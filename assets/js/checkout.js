(function ($) {
    'use strict';

    if (typeof wccdpe_data === 'undefined') {
        return;
    }

    var limaDistricts = wccdpe_data.lima_districts || {};
    var ubigeo = wccdpe_data.ubigeo || {};
    /**
     * Show/hide delivery groups based on selected tipo_entrega.
     */
    function toggleGroups(tipo) {
        $('#wccdpe-delivery-fields .wccdpe-group').each(function () {
            var showFor = $(this).data('show').split(',');
            if (showFor.indexOf(tipo) !== -1) {
                $(this).slideDown(200);
            } else {
                $(this).slideUp(200);
            }
        });
    }

    /**
     * Populate a select element with options.
     */
    function populateSelect($select, options, placeholder) {
        $select.empty();
        $select.append('<option value="">' + (placeholder || '— Selecciona —') + '</option>');
        $.each(options, function (key, val) {
            // If val is a string, use it as label; if object/array, key is label
            var label = typeof val === 'string' ? val : key;
            var value = typeof val === 'string' ? val : key;
            // For arrays (ubigeo), key is numeric index, val is string
            if ($.isArray(options)) {
                value = val;
                label = val;
            } else {
                value = key;
                label = key;
            }
            $select.append('<option value="' + $('<span>').text(value).html() + '">' + $('<span>').text(label).html() + '</option>');
        });
    }

    /**
     * Populate departamento selects from ubigeo data.
     */
    function populateDepartamentos() {
        var deptos = Object.keys(ubigeo);
        var $shaDepto = $('#billing_departamento');
        var $olvaDepto = $('#billing_olva_departamento');

        if (deptos.length === 0) return;

        populateSelect($shaDepto, deptos, '— Selecciona departamento —');
        populateSelect($olvaDepto, deptos, '— Selecciona departamento —');
    }

    /**
     * Update price display for Lima 24h.
     */
    function updateDistrictPrice() {
        var tipo = $('#billing_tipo_entrega').val();
        var distrito = $('#billing_lima_distrito').val();
        var $priceEl = $('.wccdpe-distrito-price');

        if (tipo === 'lima_24h' && distrito && limaDistricts[distrito]) {
            $priceEl.text('Costo de envío: s/' + limaDistricts[distrito]).slideDown(150);
        } else if (tipo === 'lima_48h' && distrito) {
            $priceEl.text('Costo de envío: s/10 (todos los distritos)').slideDown(150);
        } else {
            $priceEl.slideUp(150);
        }
    }

    var isShortcode = wccdpe_data.is_shortcode || false;

    /**
     * Trigger WooCommerce checkout update to recalculate totals.
     */
    function triggerUpdateCheckout() {
        if ( ! isShortcode ) {
            $(document.body).trigger('update_checkout');
        }
    }

    // ── Event Handlers ──

    // Tipo de entrega change
    $(document).on('change', '#billing_tipo_entrega', function () {
        var tipo = $(this).val();
        toggleGroups(tipo);
        updateDistrictPrice();
        triggerUpdateCheckout();
    });

    // Lima district change
    $(document).on('change', '#billing_lima_distrito', function () {
        updateDistrictPrice();
        triggerUpdateCheckout();
    });

    // Shalom: Departamento → Provincia
    $(document).on('change', '#billing_departamento', function () {
        var depto = $(this).val();
        var $prov = $('#billing_provincia');
        var $dist = $('#billing_distrito_prov');

        $dist.empty().append('<option value="">— Selecciona distrito —</option>');

        if (depto && ubigeo[depto]) {
            var provincias = Object.keys(ubigeo[depto]);
            populateSelect($prov, provincias, '— Selecciona provincia —');
        } else {
            $prov.empty().append('<option value="">— Selecciona provincia —</option>');
        }
    });

    // Shalom: Provincia → Distrito
    $(document).on('change', '#billing_provincia', function () {
        var depto = $('#billing_departamento').val();
        var prov = $(this).val();
        var $dist = $('#billing_distrito_prov');

        if (depto && prov && ubigeo[depto] && ubigeo[depto][prov]) {
            var distritos = ubigeo[depto][prov];
            populateSelect($dist, distritos, '— Selecciona distrito —');
        } else {
            $dist.empty().append('<option value="">— Selecciona distrito —</option>');
        }
    });

    // Shalom: Sub-tipo (prepago / contraentrega)
    $(document).on('change', 'input[name="billing_shalom_sub_tipo"]', function () {
        var val = $(this).val();
        if (val === 'contraentrega') {
            $('.wccdpe-shalom-contraentrega-info').slideDown(200);
        } else {
            $('.wccdpe-shalom-contraentrega-info').slideUp(200);
        }
        triggerUpdateCheckout();
    });

    // Olva: Departamento → Provincia
    $(document).on('change', '#billing_olva_departamento', function () {
        var depto = $(this).val();
        var $prov = $('#billing_olva_provincia');
        var $dist = $('#billing_olva_distrito');

        $dist.empty().append('<option value="">— Selecciona distrito —</option>');

        if (depto && ubigeo[depto]) {
            var provincias = Object.keys(ubigeo[depto]);
            populateSelect($prov, provincias, '— Selecciona provincia —');
        } else {
            $prov.empty().append('<option value="">— Selecciona provincia —</option>');
        }
    });

    // Olva: Provincia → Distrito
    $(document).on('change', '#billing_olva_provincia', function () {
        var depto = $('#billing_olva_departamento').val();
        var prov = $(this).val();
        var $dist = $('#billing_olva_distrito');

        if (depto && prov && ubigeo[depto] && ubigeo[depto][prov]) {
            var distritos = ubigeo[depto][prov];
            populateSelect($dist, distritos, '— Selecciona distrito —');
        } else {
            $dist.empty().append('<option value="">— Selecciona distrito —</option>');
        }
    });

    // Olva: Sub-tipo (domicilio / agencia)
    $(document).on('change', 'input[name="billing_olva_sub_tipo"]', function () {
        var val = $(this).val();
        if (val === 'domicilio') {
            $('.wccdpe-olva-domicilio').slideDown(200);
            $('.wccdpe-olva-agencia').slideUp(200);
        } else if (val === 'agencia') {
            $('.wccdpe-olva-domicilio').slideUp(200);
            $('.wccdpe-olva-agencia').slideDown(200);
        }
    });

    // Recojo: Tienda selection
    $(document).on('change', '#billing_tienda_especifica', function () {
        triggerUpdateCheckout();
    });

    // ── Init ──
    $(document).ready(function () {
        populateDepartamentos();

        // If tipo_entrega already has a value (e.g. page reload), trigger visibility
        var currentTipo = $('#billing_tipo_entrega').val();
        if (currentTipo) {
            toggleGroups(currentTipo);
            updateDistrictPrice();
        }
    });

})(jQuery);
