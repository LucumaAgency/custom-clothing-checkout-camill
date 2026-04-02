(function ($) {
    'use strict';

    if (typeof wccdpe_data === 'undefined') {
        return;
    }

    var limaDistricts = wccdpe_data.lima_districts || {};
    var ubigeo = wccdpe_data.ubigeo || {};
    var ajaxUrl = wccdpe_data.ajax_url || '';

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
        $select.append('<option value="">' + (placeholder || 'Selecciona') + '</option>');
        $.each(options, function (key, val) {
            var label, value;
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
     * Generic UBIGEO cascading handler.
     */
    function setupUbigeoCascade(deptoId, provId, distId) {
        $(document).on('change', deptoId, function () {
            var depto = $(this).val();
            var $prov = $(provId);
            var $dist = $(distId);

            $dist.empty().append('<option value="">Distrito</option>');

            if (depto && ubigeo[depto]) {
                populateSelect($prov, Object.keys(ubigeo[depto]), 'Provincia');
            } else {
                $prov.empty().append('<option value="">Provincia</option>');
            }
        });

        $(document).on('change', provId, function () {
            var depto = $(deptoId).val();
            var prov = $(this).val();
            var $dist = $(distId);

            if (depto && prov && ubigeo[depto] && ubigeo[depto][prov]) {
                populateSelect($dist, ubigeo[depto][prov], 'Distrito');
            } else {
                $dist.empty().append('<option value="">Distrito</option>');
            }
        });
    }

    /**
     * Populate all departamento selects from ubigeo data.
     */
    function populateDepartamentos() {
        var deptos = Object.keys(ubigeo);
        if (deptos.length === 0) return;

        $('#billing_departamento, #billing_departamento_contra, #billing_olva_departamento').each(function () {
            populateSelect($(this), deptos, 'Departamento');
        });
    }

    /**
     * Update price display for Lima districts.
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

    /**
     * Send AJAX to update delivery fee and refresh order review table.
     */
    function updateOrderReview() {
        var tipo = $('#billing_tipo_entrega').val() || '';
        var distrito = $('#billing_lima_distrito').val() || '';

        $.post(ajaxUrl, {
            action: 'wccdpe_update_delivery',
            tipo: tipo,
            distrito: distrito
        }, function (response) {
            if (response.success && response.data.html) {
                $('#order_review').html(response.data.html);
            }
        });
    }

    // ── UBIGEO cascading setups ──
    setupUbigeoCascade('#billing_departamento', '#billing_provincia', '#billing_distrito_prov');
    setupUbigeoCascade('#billing_departamento_contra', '#billing_provincia_contra', '#billing_distrito_prov_contra');
    setupUbigeoCascade('#billing_olva_departamento', '#billing_olva_provincia', '#billing_olva_distrito');

    // ── Event Handlers ──

    // Tipo de entrega change
    $(document).on('change', '#billing_tipo_entrega', function () {
        var tipo = $(this).val();
        toggleGroups(tipo);
        updateDistrictPrice();
        updateOrderReview();
    });

    // Lima district change
    $(document).on('change', '#billing_lima_distrito', function () {
        updateDistrictPrice();
        updateOrderReview();
    });

    // Sync billing_address_1 hidden field with actual address inputs
    $(document).on('change keyup', '#billing_direccion, #billing_olva_direccion', function () {
        var val = $(this).val();
        if (val) {
            $('#billing_address_1').val(val);
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
        updateOrderReview();
    });

    // ── Init ──
    $(document).ready(function () {
        populateDepartamentos();

        var currentTipo = $('#billing_tipo_entrega').val();
        if (currentTipo) {
            toggleGroups(currentTipo);
            updateDistrictPrice();
        }
    });

})(jQuery);
