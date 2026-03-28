# WC Custom Checkout Delivery PE - Documentación

## Descripción General

Plugin de WordPress/WooCommerce que reemplaza el checkout estándar con un sistema de entrega personalizado para una tienda de ropa en Perú. Ofrece 5 métodos de envío con precios dinámicos, validación condicional y shortcodes para uso con Elementor.

**Versión actual:** 1.6.0
**Requiere:** WordPress + WooCommerce
**Autor:** Clothing Custom

---

## Estructura de Archivos

```
custom-clothing-checkout-camill/
├── wc-custom-checkout-delivery-pe.php      # Archivo principal del plugin
├── assets/
│   ├── css/checkout.css                    # Estilos del checkout
│   └── js/checkout.js                      # Lógica frontend (jQuery)
├── includes/
│   ├── class-wccdpe-data.php               # Datos estáticos (precios, UBIGEO, tiendas)
│   ├── class-wccdpe-fields.php             # Campos del checkout nativo WooCommerce
│   ├── class-wccdpe-fees.php               # Cálculo de tarifas de envío
│   ├── class-wccdpe-validation.php         # Validación del formulario
│   ├── class-wccdpe-order-meta.php         # Metadatos en órdenes y emails
│   ├── class-wccdpe-ajax.php               # Actualización de sesión via AJAX
│   ├── class-wccdpe-shortcode.php          # Shortcodes para Elementor
│   └── data/ubigeo.json                    # Datos UBIGEO de Perú
└── ga.md                                   # Esta documentación
```

---

## Arquitectura y Flujo

### Patrón Singleton

El plugin usa patrón singleton en `WC_Custom_Checkout_Delivery_PE`. Se inicializa en `plugins_loaded` y verifica que WooCommerce esté activo antes de cargar las dependencias.

### Flujo del Checkout

```
1. Usuario abre checkout
   │
2. Se renderizan campos de billing (email, nombre, apellidos, teléfono)
   │
3. Usuario selecciona "Tipo de entrega" en dropdown
   │
4. JavaScript muestra/oculta campos condicionales según selección
   │  ├── Lima 24h/48h → Distrito, dirección, referencia, tipo vivienda
   │  ├── Shalom → UBIGEO (depto→prov→distrito), agencia, modalidad pago
   │  ├── Olva → UBIGEO, sub-tipo (domicilio/agencia), campos condicionales
   │  └── Recojo → Selector de tienda
   │
5. AJAX actualiza sesión de WooCommerce (tipo_entrega, distrito, sub_tipo)
   │
6. WCCDPE_Fees calcula tarifa de envío según sesión
   │
7. WooCommerce recalcula totales y muestra en order review
   │
8. Usuario envía formulario → WCCDPE_Validation valida campos requeridos
   │
9. WCCDPE_Order_Meta guarda datos de entrega en la orden
   │
10. Datos de entrega aparecen en admin y emails de confirmación
```

---

## Clases y Responsabilidades

### WCCDPE_Data (`class-wccdpe-data.php`)

Clase estática con datos de configuración:

| Método | Retorna |
|--------|---------|
| `get_lima_districts_with_prices()` | Array de 43 distritos de Lima con precios (s/12 - s/28) |
| `get_ubigeo()` | Estructura jerárquica Departamento → Provincia → Distritos desde JSON |
| `get_tiendas()` | 2 ubicaciones de tienda en Gamarra |
| `get_delivery_types()` | 5 tipos de entrega con labels |

### WCCDPE_Fields (`class-wccdpe-fields.php`)

Maneja los campos del checkout nativo de WooCommerce (no shortcode):

- **Hook `woocommerce_checkout_fields`:** Personaliza email, agrega campo `billing_tipo_entrega`
- **Hook `woocommerce_after_checkout_billing_form`:** Renderiza campos condicionales de entrega
- **Hook `woocommerce_checkout_after_customer_details`:** Texto informativo de envíos

### WCCDPE_Fees (`class-wccdpe-fees.php`)

Calcula tarifas dinámicas via `woocommerce_cart_calculate_fees`:

| Tipo de entrega | Tarifa |
|----------------|--------|
| Lima 24h | Dinámica por distrito (s/12 - s/28) |
| Lima 48h | Fija s/10 |
| Shalom Prepago | s/15 |
| Shalom Contraentrega | s/0 (se paga en agencia) |
| Olva Courier | s/15 |
| Recojo en Tienda | Gratis |

Lee datos de `WC()->session` para determinar el tipo seleccionado y distrito.

### WCCDPE_Validation (`class-wccdpe-validation.php`)

Validación en `woocommerce_checkout_process`:

- **Siempre:** tipo_entrega requerido
- **Lima:** distrito + dirección
- **Shalom:** departamento + agencia + modalidad de pago
- **Olva:** departamento + sub-tipo + (dirección si domicilio / agencia si agencia)
- **Recojo:** tienda seleccionada

### WCCDPE_Order_Meta (`class-wccdpe-order-meta.php`)

Guarda 18 campos custom en la orden:

- `_billing_tipo_entrega`, `_billing_lima_distrito`, `_billing_direccion`, `_billing_referencia`, `_billing_vivienda`
- `_billing_departamento`, `_billing_provincia`, `_billing_distrito_prov`, `_billing_agencia_shalom`, `_billing_shalom_sub_tipo`
- `_billing_olva_departamento`, `_billing_olva_provincia`, `_billing_olva_distrito`, `_billing_olva_sub_tipo`, `_billing_olva_direccion`, `_billing_olva_referencia`, `_billing_olva_agencia_nombre`
- `_billing_tienda_especifica`

Muestra datos en:
- Página de admin de la orden (hook `woocommerce_admin_order_data_after_billing_address`)
- Emails de confirmación (hook `woocommerce_email_order_meta_fields`)

### WCCDPE_Ajax (`class-wccdpe-ajax.php`)

Hook `woocommerce_checkout_update_order_review`:
- Parsea datos del POST del checkout
- Guarda en sesión: `wccdpe_tipo_entrega`, `wccdpe_lima_distrito`, `wccdpe_shalom_sub_tipo`
- Estos valores los lee `WCCDPE_Fees` para calcular tarifas

### WCCDPE_Shortcode (`class-wccdpe-shortcode.php`)

4 shortcodes para uso con Elementor:

| Shortcode | Descripción |
|-----------|-------------|
| `[wccdpe_delivery_form]` | Solo el formulario de tipo de entrega con campos condicionales |
| `[wccdpe_order_review]` | Resumen del pedido (tabla de productos, subtotal, total) |
| `[wccdpe_payment_methods]` | Métodos de pago disponibles + botón "Realizar pedido" |
| `[wccdpe_full_checkout]` | Checkout completo: billing + entrega + pedido + pagos en layout 2 columnas |

**`[wccdpe_full_checkout]`** es el shortcode principal para Elementor. Renderiza:

- Columna izquierda (50%): Detalles de facturación, tipo de entrega, información adicional
- Columna derecha (50%, fondo #F5F5F5): Tu Pedido (order review) + métodos de pago

Usa `woocommerce_form_field()` para todos los campos, heredando los estilos de Elementor/tema.

---

## Frontend (JavaScript)

### checkout.js - Funciones principales

| Función | Descripción |
|---------|-------------|
| `toggleGroups(tipo)` | Muestra/oculta grupos de campos según tipo de entrega |
| `populateSelect($select, options, placeholder)` | Llena un `<select>` con opciones |
| `populateDepartamentos()` | Carga departamentos en selects de Shalom y Olva desde UBIGEO |
| `updateDistrictPrice()` | Muestra precio dinámico para Lima 24h o fijo para 48h |
| `triggerUpdateCheckout()` | Dispara recálculo de WooCommerce (solo en checkout, no en shortcode) |

### Cascada UBIGEO

```
Departamento seleccionado
  → Poblar provincias del departamento
    → Provincia seleccionada
      → Poblar distritos de la provincia
```

Se aplica independientemente para Shalom y Olva (campos separados).

### Variable `isShortcode`

Cuando el JS se carga desde un shortcode, `wccdpe_data.is_shortcode = true` y se desactiva `triggerUpdateCheckout()` para evitar errores en páginas que no son el checkout de WooCommerce.

---

## CSS - Layout del Full Checkout

### Estructura de contenedores (desktop)

```
┌───────────────────────────────────────────────────────────────┐
│                    .wccdpe-checkout-columns                    │
│                                                               │
│  ┌─ Col A (50%, fondo blanco) ─┬─ Col B (50%, fondo #F5F5F5)─┐
│  │  padding-left: 40px         │  padding-right: 40px         │
│  │                             │                              │
│  │  ┌── inner (max 600px) ──┐  │  ┌── inner (max 600px) ──┐  │
│  │  │                       │  │  │                        │  │
│  │  │  Detalles facturación │  │  │  Tu Pedido             │  │
│  │  │  - Email              │  │  │  - Tabla productos     │  │
│  │  │  - Nombre | Apellidos │  │  │  - Subtotal / Total    │  │
│  │  │  - Teléfono           │  │  │                        │  │
│  │  │  - Tipo de entrega    │  │  │  Métodos de pago       │  │
│  │  │  - [Campos delivery]  │  │  │  - Tarjeta, QR, etc.   │  │
│  │  │                       │  │  │  - Botón pagar         │  │
│  │  │  Info adicional       │  │  │                        │  │
│  │  │  - Notas del pedido   │  │  │                        │  │
│  │  │                       │  │  │                        │  │
│  │  └───────────────────────┘  │  └────────────────────────┘  │
│  │          flex-end →         │         ← flex-start         │
│  └─────────────────────────────┴──────────────────────────────┘
└───────────────────────────────────────────────────────────────┘
```

- **Col A:** `flex: 0 0 50%`, `justify-content: flex-end` (contenido pegado al centro)
- **Col B:** `flex: 0 0 50%`, `background: #F5F5F5`, `justify-content: flex-start`
- **Inner:** `max-width: 600px`, `padding: 30px 40px`
- **Sticky:** La columna derecha tiene `position: sticky; top: 20px` para seguir al scroll

### Responsive (<=960px)

```
┌──────────────────────┐
│  Col A (90%, centrada)│
│  ┌── inner ────────┐ │
│  │ Billing + Entrega│ │
│  └─────────────────┘ │
│                       │
│  Col B (90%, centrada)│
│  ┌── inner ────────┐ │
│  │ Pedido + Pago    │ │
│  └─────────────────┘ │
└──────────────────────┘
```

Una sola columna al 90% de ancho, centrada, sin sticky.

---

## Métodos de Entrega - Detalle

### 1. Lima - Delivery 24 horas

Entrega en 24h dentro de Lima Metropolitana. Precio variable por distrito:

- **s/12:** Ate, Barranco, Breña, Carabayllo, Chorrillos, Comas, El Agustino, Independencia, Jesús María, La Molina, La Victoria, Lima (Cercado), Lince, Los Olivos, Magdalena del Mar, Miraflores, Pueblo Libre, Puente Piedra, Rímac, San Borja, San Isidro, San Juan de Lurigancho, San Juan de Miraflores, San Luis, San Martín de Porres, San Miguel, Santa Anita, Santiago de Surco, Surquillo, Villa El Salvador, Villa María del Triunfo
- **s/14:** Lurigancho-Chosica, Lurín
- **s/17:** Ancón, Chaclacayo, Cieneguilla, Punta Hermosa, Santa Rosa
- **s/19:** Pachacamac
- **s/21:** Punta Negra
- **s/22:** San Bartolo
- **s/25:** Santa María del Mar
- **s/28:** Pucusana

**Campos:** Distrito, dirección, referencia, tipo de vivienda

### 2. Lima - Delivery 48 horas

Entrega en 48h. Tarifa plana de **s/10** para todos los distritos de Lima.

**Campos:** Distrito, dirección, referencia, tipo de vivienda

### 3. Provincia - Shalom (s/15)

Envío a provincia vía courier Shalom. Dos modalidades de pago:

- **Prepago:** s/15 se cobra en el checkout
- **Contraentrega:** s/0 en checkout, se paga en la agencia Shalom al recoger

**Campos:** Departamento, provincia, distrito (UBIGEO), nombre de agencia Shalom, modalidad de pago

### 4. Provincia - Olva Courier (s/15)

Envío a provincia vía Olva Courier. Tarifa fija s/15. Dos modalidades:

- **Domicilio:** Entrega a dirección del cliente (campos: dirección + referencia)
- **Agencia:** Recojo en agencia Olva (campo: nombre de agencia)

**Campos:** Departamento, provincia, distrito (UBIGEO), sub-tipo, campos condicionales

### 5. Recojo en Tienda (Gratis)

Recojo gratuito en tienda física. Tiempo de preparación: 2-4 días.
Horario: 10:30 am - 7:30 pm, lunes a sábados.

**Tiendas disponibles:**
- Galería Damero, tienda 125 – Jr. Agustín Gamarra 939
- Galería YA!, tienda 03 – Jr. Agustín Gamarra 1043

---

## Hooks de WordPress/WooCommerce Utilizados

### Filters
| Hook | Clase | Propósito |
|------|-------|-----------|
| `woocommerce_checkout_fields` | Fields | Personalizar campos de billing, agregar tipo_entrega |
| `woocommerce_checkout_fields` | Main | Remover campos de shipping |
| `woocommerce_cart_needs_shipping` | Main | Desactivar métodos de envío nativos |

### Actions
| Hook | Clase | Propósito |
|------|-------|-----------|
| `plugins_loaded` | Main | Inicializar plugin |
| `wp_enqueue_scripts` | Main | Cargar CSS/JS en checkout |
| `woocommerce_after_checkout_billing_form` | Fields | Renderizar campos de entrega |
| `woocommerce_checkout_after_customer_details` | Fields | Texto informativo |
| `woocommerce_cart_calculate_fees` | Fees | Calcular tarifa de envío |
| `woocommerce_checkout_update_order_review` | Ajax | Actualizar sesión con datos del form |
| `woocommerce_checkout_process` | Validation | Validar campos al enviar |
| `woocommerce_checkout_update_order_meta` | Order_Meta | Guardar metadatos en orden |
| `woocommerce_admin_order_data_after_billing_address` | Order_Meta | Mostrar datos en admin |
| `woocommerce_email_order_meta_fields` | Order_Meta | Incluir datos en emails |

---

## Uso con Elementor

### Opción recomendada: `[wccdpe_full_checkout]`

1. Crear/editar la página de checkout con Elementor
2. Agregar widget **"Shortcode"**
3. Pegar `[wccdpe_full_checkout]`
4. Publicar

Este shortcode genera el checkout completo con layout de 2 columnas, incluyendo el `<form>` de WooCommerce necesario para procesar la orden.

### Shortcodes individuales (uso avanzado)

Solo usar `[wccdpe_order_review]` y `[wccdpe_payment_methods]` dentro de una página que ya tenga el form de checkout de WooCommerce. Fuera de ese contexto, no podrán procesar órdenes.

---

## Historial de Versiones

| Versión | Cambios |
|---------|---------|
| 1.0.0 | Plugin inicial: 5 métodos de envío, campos condicionales, validación, fees, order meta |
| 1.1.0 | Shortcodes para Elementor, markup nativo WooCommerce |
| 1.2.0 | Columna derecha #F5F5F5, billing simplificado (email, nombre, apellidos, teléfono) |
| 1.3.0 | Layout 50/50, max-width 600px por columna, responsive 1 col 90% |
| 1.4.0 | Estructura contenedor A/B con internos de 600px pegados al centro |
| 1.5.0 | Padding lateral en contenedores para no pegar al borde |
| 1.6.0 | Select tipo entrega full width, fix opción duplicada en dropdown |
