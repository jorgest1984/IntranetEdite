<?php
// ficha_factura.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO])) {
    header("Location: home.php");
    exit();
}

$active_tab = 'contabilidad';
$error = '';
$success = '';

// Obtener ID de factura
$factura_id = intval($_GET['id'] ?? 0);
if ($factura_id <= 0) {
    header("Location: facturas.php");
    exit();
}

// Cargar datos de la factura
$factura = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ?");
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch();
    
    if (!$factura) {
        header("Location: facturas.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error al cargar la factura: " . $e->getMessage();
}

// Cargar proveedor asociado (si existe)
$proveedor = null;
if (!empty($factura['emisor_id'])) {
    try {
        $stmt_prov = $pdo->prepare("SELECT id, cif, nombre FROM proveedores WHERE id = ?");
        $stmt_prov->execute([$factura['emisor_id']]);
        $proveedor = $stmt_prov->fetch();
    } catch (Exception $e) {}
}

// Cargar imputaciones a planes
$imputaciones = [];
$total_imputaciones = 0;
try {
    $stmt_imp = $pdo->prepare("
        SELECT fi.*, p.nombre as plan_nombre 
        FROM factura_imputaciones fi 
        LEFT JOIN planes p ON fi.plan_id = p.id 
        WHERE fi.factura_id = ?
        ORDER BY fi.id ASC
    ");
    $stmt_imp->execute([$factura_id]);
    $imputaciones = $stmt_imp->fetchAll();
    foreach ($imputaciones as $imp) {
        $total_imputaciones += floatval($imp['total_imputable'] ?? 0);
    }
} catch (Exception $e) {
    // La tabla puede no existir aún
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE facturas SET 
                numero_factura = ?,
                tipo_emisor = ?,
                emisor_id = ?,
                total = ?,
                fecha_emision = ?,
                fecha_pago = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['numero_factura'],
            $_POST['tipo_emisor'] ?? 'Proveedor',
            !empty($_POST['emisor_id']) ? $_POST['emisor_id'] : null,
            $_POST['total'],
            $_POST['fecha_emision'] ?: null,
            $_POST['fecha_pago'] ?: null,
            $factura_id
        ]);
        
        $success = "Factura actualizada correctamente.";
        
        // Recargar datos
        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch();
        
        // Recargar proveedor
        if (!empty($factura['emisor_id'])) {
            $stmt_prov = $pdo->prepare("SELECT id, cif, nombre FROM proveedores WHERE id = ?");
            $stmt_prov->execute([$factura['emisor_id']]);
            $proveedor = $stmt_prov->fetch();
        }
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// Cargar lista de proveedores para el select/autocompletar
$proveedores_list = [];
try {
    $proveedores_list = $pdo->query("SELECT id, cif, nombre FROM proveedores ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {}

// Cargar lista de usuarios/tutores para emisor tipo Usuario
$usuarios_list = [];
try {
    $usuarios_list = $pdo->query("SELECT id, nombre, apellidos, email FROM usuarios WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {}

// Cargar planes para links
$planes_map = [];
try {
    $planes_result = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC")->fetchAll();
    foreach ($planes_result as $p) {
        $planes_map[$p['id']] = $p['nombre'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura #<?= $factura_id ?> - <?= APP_NAME ?></title>
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
                <h1>Modificar Factura</h1>
                <p>Editar datos de la factura #<?= $factura_id ?></p>
            </div>
            <div>
                <a href="facturas.php" class="btn btn-invoice-secondary" style="text-decoration: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver a Facturas
                </a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="ficha-alert ficha-alert-error">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="ficha-alert ficha-alert-success">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <section class="invoice-detail-card">
            <form id="formEditarFactura" method="POST">
                
                <!-- Código (solo lectura) -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Código</label>
                    <input type="text" value="<?= $factura['id'] ?>" class="form-input-invoice" style="width: 100px; background: #f1f5f9; color: #94a3b8;" disabled>
                </div>

                <!-- Número de factura -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Número de factura</label>
                    <input type="text" name="numero_factura" value="<?= htmlspecialchars($factura['numero_factura'] ?? '') ?>" class="form-input-invoice" required>
                </div>

                <!-- Emisor -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice">Emisor</label>
                    <div class="radio-group-invoice">
                        <label class="radio-option">
                            <input type="radio" name="tipo_emisor" value="Proveedor" <?= ($factura['tipo_emisor'] ?? 'Proveedor') === 'Proveedor' ? 'checked' : '' ?>> Proveedor
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="tipo_emisor" value="Usuario / Profesor" <?= ($factura['tipo_emisor'] ?? '') === 'Usuario / Profesor' ? 'checked' : '' ?>> Usuario / Profesor
                        </label>
                    </div>
                </div>

                <!-- Proveedor (visible cuando tipo_emisor = Proveedor) -->
                <div class="form-row-invoice" id="seccionProveedor">
                    <label class="form-label-invoice">Proveedor</label>
                    <div class="provider-search-group">
                        <input type="text" id="emisor_search_cif" class="form-input-invoice provider-input-cif" 
                               placeholder="Código" 
                               value="<?= $proveedor ? htmlspecialchars($proveedor['id']) : '' ?>">
                        <input type="hidden" name="emisor_id" id="emisor_id" value="<?= $proveedor ? $proveedor['id'] : '' ?>">
                        
                        <select id="proveedorSelect" class="form-input-invoice" style="flex: 1; max-width: 350px;">
                            <option value="">-- Seleccione proveedor --</option>
                            <?php foreach ($proveedores_list as $prov): ?>
                                <option value="<?= $prov['id'] ?>" 
                                        data-cif="<?= htmlspecialchars($prov['cif']) ?>"
                                        <?= ($proveedor && $proveedor['id'] == $prov['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov['nombre']) ?> – <?= htmlspecialchars($prov['cif']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="button" class="btn-action-invoice" id="btnAddProvider" title="Añadir proveedor" onclick="location.href='nuevo_proveedor.php?from=ficha_factura&factura_id=<?= $factura_id ?>'">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </button>
                        <button type="button" class="btn-action-invoice" id="btnEditProvider" title="Ver/Editar proveedor">
                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Usuario / Profesor (visible cuando tipo_emisor = Usuario / Profesor) -->
                <div class="form-row-invoice" id="seccionUsuario" style="display: none;">
                    <label class="form-label-invoice">Usuario / Profesor</label>
                    <div class="provider-search-group">
                        <select id="usuarioSelect" class="form-input-invoice" style="flex: 1; max-width: 450px;">
                            <option value="">-- Seleccione usuario / tutor --</option>
                            <?php foreach ($usuarios_list as $usr): ?>
                                <option value="<?= $usr['id'] ?>" 
                                        <?= (($factura['tipo_emisor'] ?? '') === 'Usuario / Profesor' && ($factura['emisor_id'] ?? '') == $usr['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usr['nombre'] . ' ' . $usr['apellidos']) ?> – <?= htmlspecialchars($usr['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="button" class="btn-action-invoice" id="btnViewUser" title="Ver ficha de trabajador">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Importe total -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice" style="color: var(--invoice-blue);">Importe total</label>
                    <div class="input-with-suffix">
                        <input type="number" name="total" step="0.01" class="form-input-invoice" 
                               value="<?= number_format(floatval($factura['total'] ?? 0), 2, '.', '') ?>" required>
                        <span class="input-suffix">€</span>
                    </div>
                </div>

                <!-- Fecha de emisión -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice" style="font-style: italic;">Fecha de emisión</label>
                    <input type="date" name="fecha_emision" class="form-input-invoice" style="width: 200px;" 
                           value="<?= $factura['fecha_emision'] ? date('Y-m-d', strtotime($factura['fecha_emision'])) : '' ?>">
                </div>

                <!-- Fecha de pago -->
                <div class="form-row-invoice">
                    <label class="form-label-invoice" style="font-style: italic;">Fecha de pago</label>
                    <input type="date" name="fecha_pago" class="form-input-invoice" style="width: 200px;" 
                           value="<?= $factura['fecha_pago'] ? date('Y-m-d', strtotime($factura['fecha_pago'])) : '' ?>">
                </div>

                <div class="form-footer-invoice">
                    <div style="text-align: center;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 3rem;">Guardar</button>
                    </div>
                </div>

            </form>
        </section>

        <!-- IMPUTACIONES A PLANES -->
        <section class="imputaciones-section">
            <h2 class="imputaciones-title">Imputaciones a planes</h2>
            <div class="table-container" style="overflow-x: auto;">
                <table class="table-invoices table-imputaciones">
                    <thead>
                        <tr>
                            <th>Cod</th>
                            <th>Referencia</th>
                            <th style="text-align: right;">Total imputable</th>
                            <th>Plan</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($imputaciones)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                                    No hay imputaciones a planes para esta factura.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($imputaciones as $imp): ?>
                                <tr>
                                    <td><strong><?= $imp['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($imp['referencia'] ?? '') ?></td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?= number_format(floatval($imp['total_imputable'] ?? 0), 2, ',', '.') ?> €
                                    </td>
                                    <td>
                                        <?php if (!empty($imp['plan_id'])): ?>
                                            <a href="editar_plan.php?id=<?= $imp['plan_id'] ?>" class="plan-link">
                                                <?= htmlspecialchars($planes_map[$imp['plan_id']] ?? 'Plan #'.$imp['plan_id']) ?>
                                            </a>
                                        <?php else: ?>
                                            ---
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-imp-delete" title="Eliminar imputación" onclick="eliminarImputacion(<?= $imp['id'] ?>)">
                                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($imputaciones)): ?>
                        <tfoot>
                            <tr class="imputaciones-total-row">
                                <td><strong>Total</strong></td>
                                <td><?= count($imputaciones) ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--invoice-blue);">
                                    <?= number_format($total_imputaciones, 2, ',', '.') ?> €
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </section>

        <div style="text-align: center; margin-top: 2rem; margin-bottom: 2rem;">
            <a href="facturas.php" class="btn btn-invoice-secondary" style="text-decoration: none;">
                Volver a Facturas
            </a>
        </div>

    </main>
</div>

<script>
    // === ELEMENTOS ===
    const proveedorSelect = document.getElementById('proveedorSelect');
    const emisorCif = document.getElementById('emisor_search_cif');
    const emisorId = document.getElementById('emisor_id');
    const usuarioSelect = document.getElementById('usuarioSelect');
    const seccionProveedor = document.getElementById('seccionProveedor');
    const seccionUsuario = document.getElementById('seccionUsuario');
    const radiosEmisor = document.querySelectorAll('input[name="tipo_emisor"]');

    // === TOGGLE PROVEEDOR / USUARIO ===
    function toggleEmisorSections() {
        const tipoSeleccionado = document.querySelector('input[name="tipo_emisor"]:checked').value;
        
        if (tipoSeleccionado === 'Proveedor') {
            seccionProveedor.style.display = '';
            seccionUsuario.style.display = 'none';
        } else {
            seccionProveedor.style.display = 'none';
            seccionUsuario.style.display = '';
        }
    }

    // Ejecutar al cargar
    toggleEmisorSections();

    // Ejecutar al cambiar radio
    radiosEmisor.forEach(radio => {
        radio.addEventListener('change', function() {
            toggleEmisorSections();
            // Al cambiar de tipo, sincronizar el emisor_id
            if (this.value === 'Usuario / Profesor') {
                emisorId.value = usuarioSelect.value || '';
            } else {
                emisorId.value = proveedorSelect.value || '';
            }
        });
    });

    // === PROVEEDOR: Sincronizar selector con campo de código ===
    proveedorSelect.addEventListener('change', function() {
        if (this.value) {
            emisorCif.value = this.value;
            emisorId.value = this.value;
        } else {
            emisorCif.value = '';
            emisorId.value = '';
        }
    });

    // Buscar al escribir código de proveedor
    emisorCif.addEventListener('change', function() {
        const val = this.value.trim();
        if (val) {
            for (let i = 0; i < proveedorSelect.options.length; i++) {
                if (proveedorSelect.options[i].value === val) {
                    proveedorSelect.selectedIndex = i;
                    emisorId.value = val;
                    return;
                }
            }
        }
        proveedorSelect.selectedIndex = 0;
        emisorId.value = '';
    });

    // === USUARIO: Sincronizar selector ===
    usuarioSelect.addEventListener('change', function() {
        emisorId.value = this.value || '';
    });

    // === BOTÓN EDITAR PROVEEDOR (lápiz) → editar_proveedor.php ===
    document.getElementById('btnEditProvider').onclick = () => {
        const id = proveedorSelect.value || emisorId.value;
        if (id) {
            window.location.href = 'editar_proveedor.php?id=' + id + '&from=ficha_factura&factura_id=<?= $factura_id ?>';
        } else {
            alert('Seleccione un proveedor para editarlo.');
        }
    };

    // === BOTÓN VER FICHA USUARIO → ficha_trabajador.php ===
    document.getElementById('btnViewUser').onclick = () => {
        const id = usuarioSelect.value;
        if (id) {
            window.location.href = 'ficha_trabajador.php?id=' + id;
        } else {
            alert('Seleccione un usuario/profesor para ver su ficha.');
        }
    };

    // === ELIMINAR IMPUTACIÓN ===
    function eliminarImputacion(id) {
        if (confirm('¿Está seguro de que desea eliminar esta imputación?')) {
            // TODO: Implementar API de eliminación
            alert('Funcionalidad de eliminación pendiente de implementar.');
        }
    }
</script>

</body>
</html>
