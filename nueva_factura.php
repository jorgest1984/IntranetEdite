<?php
// nueva_factura.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();   
}

$active_tab = 'contabilidad';

// Simulación de siguiente código si fuera autoincremental real
$next_id = 1564; 

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Factura - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/facturas.css">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Crear Nueva Factura</h1>
                <p>Introduzca los datos para la nueva emisión</p>
            </div>
        </header>

        <section class="invoice-detail-card">
            <form id="formNuevaFactura" action="guardar_factura.php" method="POST">
                
                <!-- Código -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Código</label>
                    <input type="text" value="<?= $next_id ?>" class="form-input-invoice" style="width: 100px;" disabled>
                </div>

                <!-- Número de factura -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Número de factura</label>
                    <input type="text" name="numero_factura" class="form-input-invoice" required>
                </div>

                <!-- Emisor -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Emisor</label>
                    <div class="radio-group-invoice">
                        <label class="radio-option">
                            <input type="radio" name="tipo_emisor" value="Proveedor" checked> Proveedor
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="tipo_emisor" value="Usuario"> Usuario / Profesor
                        </label>
                    </div>
                </div>

                <!-- Proveedor -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Proveedor</label>
                    <div class="provider-search-group">
                        <input type="text" name="emisor_search_cif" class="form-input-invoice provider-input-cif" placeholder="Nombre o CIF">
                        <input type="text" name="emisor_search_name" class="form-input-invoice provider-input-name" placeholder="" readonly>
                        
                        <button type="button" class="btn-action-invoice" id="btnAddProvider" title="Añadir proveedor" onclick="location.href='nuevo_proveedor.php?from=factura'">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </button>
                        <button type="button" class="btn-action-invoice" id="btnEditProvider" title="Ver/Editar proveedor">
                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Importe total -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Importe total</label>
                    <div class="input-with-suffix">
                        <input type="number" name="total" step="0.01" class="form-input-invoice" required>
                        <span class="input-suffix">€</span>
                    </div>
                </div>

                <!-- Fecha de emisión -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Fecha de emisión</label>
                    <input type="date" name="fecha_emision" class="form-input-invoice" style="width: 200px;" required>
                </div>

                <!-- Fecha de pago -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Fecha de pago</label>
                    <input type="date" name="fecha_pago" class="form-input-invoice" style="width: 200px;">
                </div>

                <div class="form-footer-invoice">
                    <div style="text-align: center;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 3rem;">Guardar</button>
                        <a href="facturas.php" class="btn btn-invoice-secondary" style="margin-left: 1rem; text-decoration: none;">Cancelar</a>
                    </div>
                </div>

            </form>
        </section>
    </main>
</div>

<script>
    // Alerta para el botón editar (placeholder por ahora)
    document.getElementById('btnEditProvider').onclick = () => {
        alert('Funcionalidad de edición de proveedor: Se abrirá la ficha del emisor seleccionado.');
    };

    // Cambio de tipo de emisor (Placeholder visual)
    document.querySelectorAll('input[name="tipo_emisor"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            console.log("Cambiando búsqueda a: " + e.target.value);
        });
    });
</script>

</body>
</html>
