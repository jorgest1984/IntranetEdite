<?php
// facturas.php
require_once 'includes/auth.php';

// Verificación de permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: home.php");
    exit();
}

$active_tab = 'contabilidad';
$error = '';
$success = '';

// Lógica de Búsqueda
$facturas = [];
$total_registros = 0;
$search_executed = false;

try {
    $sql = "SELECT f.* FROM facturas f WHERE 1=1";
    $params = [];

    // Capturar Filtros
    if (!empty($_GET['cif'])) {
        $sql .= " AND f.cif LIKE ?";
        $params[] = "%" . $_GET['cif'] . "%";
    }
    if (!empty($_GET['razon'])) {
        $sql .= " AND f.razon_social LIKE ?";
        $params[] = "%" . $_GET['razon'] . "%";
    }
    if (!empty($_GET['referencia'])) {
        $sql .= " AND f.referencia LIKE ?";
        $params[] = "%" . $_GET['referencia'] . "%";
    }
    if (!empty($_GET['num_factura'])) {
        $sql .= " AND f.numero_factura LIKE ?";
        $params[] = "%" . $_GET['num_factura'] . "%";
    }
    if (!empty($_GET['fecha_desde'])) {
        $sql .= " AND f.fecha_emision >= ?";
        $params[] = $_GET['fecha_desde'];
    }
    if (!empty($_GET['fecha_hasta'])) {
        $sql .= " AND f.fecha_emision <= ?";
        $params[] = $_GET['fecha_hasta'];
    }
    if (!empty($_GET['plan_id'])) {
        $sql .= " AND f.plan_id = ?";
        $params[] = $_GET['plan_id'];
    }
    if (!empty($_GET['convocatoria_id'])) {
        $sql .= " AND f.convocatoria_id = ?";
        $params[] = $_GET['convocatoria_id'];
    }

    $sql .= " ORDER BY f.fecha_emision DESC, f.id DESC";
    
    // Si hay parámetros o se pulsó "Mostrar todos", ejecutamos
    if (!empty($params) || isset($_GET['full'])) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $facturas = $stmt->fetchAll();
        $total_registros = count($facturas);
        $search_executed = true;
    } else {
        // Carga mínima inicial si se desea
        $stmt = $pdo->prepare($sql . " LIMIT 20");
        $stmt->execute();
        $facturas = $stmt->fetchAll();
        $total_registros = count($facturas);
    }

} catch (Exception $e) {
    $error = "Error en la consulta: " . $e->getMessage();
}

// Cargar catálogos para filtros
$planes = [];
$convocatorias = [];
try {
    $planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC")->fetchAll();
    $convocatorias = $pdo->query("SELECT id, codigo_expediente, nombre FROM convocatorias ORDER BY codigo_expediente ASC")->fetchAll();
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturas - <?= APP_NAME ?></title>
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
                <h1>Gestión de Facturas</h1>
                <p>Relación de facturas emitidas y estado de cobro</p>
            </div>
            <div class="invoice-actions-top">
                <button class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Nueva factura
                </button>
                <button class="btn btn-invoice-secondary" style="background: #475569; color: white; border: none;">
                    <svg style="fill: currentColor;" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    Importar facturas
                </button>
                <button class="btn btn-invoice-secondary" style="background: #475569; color: white; border: none;">
                    <svg style="fill: currentColor;" viewBox="0 0 24 24"><path d="M5 20h14v-2H5v2zM19 9h-4V3H9v6H5l7 7 7-7z" transform="rotate(180 12 12)"/></svg>
                    Exportar facturas
                </button>
            </div>
        </header>

        <!-- PANEL DE BÚSQUEDA -->
        <section class="invoice-search-card">
            <div class="invoice-card-header">
                <h2>FACTURAS - CAMPOS DE BÚSQUEDA</h2>
            </div>
            <form action="" method="GET" class="invoice-form">
                <div class="invoice-row">
                    <div class="form-group-compact">
                        <label>CIF:</label>
                        <input type="text" name="cif" value="<?= htmlspecialchars($_GET['cif'] ?? '') ?>" class="invoice-input" style="width: 110px;">
                    </div>
                    <div class="form-group-compact">
                        <label>Razón Social / Nombre:</label>
                        <input type="text" name="razon" value="<?= htmlspecialchars($_GET['razon'] ?? '') ?>" class="invoice-input" style="width: 250px;">
                    </div>
                    <div class="form-group-compact">
                        <label>Referencia:</label>
                        <input type="text" name="referencia" value="<?= htmlspecialchars($_GET['referencia'] ?? '') ?>" class="invoice-input" style="width: 140px;">
                    </div>
                    <div class="form-group-compact">
                        <label>Nº Factura:</label>
                        <input type="text" name="num_factura" value="<?= htmlspecialchars($_GET['num_factura'] ?? '') ?>" class="invoice-input" style="width: 140px;">
                    </div>
                </div>

                <div class="invoice-row">
                    <div class="form-group-compact">
                        <label>Fecha de emisión:</label>
                        <span style="font-size: 0.7rem; color: var(--text-muted);">De</span>
                        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($_GET['fecha_desde'] ?? '') ?>" class="invoice-input">
                        <span style="font-size: 0.7rem; color: var(--text-muted);">a</span>
                        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($_GET['fecha_hasta'] ?? '') ?>" class="invoice-input">
                    </div>
                    <div class="form-group-compact">
                        <label>Plan:</label>
                        <select name="plan_id" class="invoice-input" style="width: 180px;">
                            <option value="">--- Seleccione ---</option>
                            <?php foreach($planes as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($_GET['plan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-compact">
                        <label>Expediente:</label>
                        <select name="convocatoria_id" class="invoice-input" style="width: 180px;">
                            <option value="">--- Seleccione ---</option>
                            <?php foreach($convocatorias as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($_GET['convocatoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['codigo_expediente']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="invoice-form-actions">
                    <button type="submit" class="btn-invoice btn-invoice-primary">Filtrar</button>
                    <a href="facturas.php?full=1" class="btn-invoice btn-invoice-secondary" style="text-decoration: none;">Mostrar todos los registros</a>
                </div>
            </form>
        </section>

        <!-- TABLA DE RESULTADOS -->
        <section class="invoice-results">
            <div class="table-container" style="overflow-x: auto;">
                <table class="table-invoices">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg> Nº de factura</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg> CIF</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg> Razón Social / Nombre</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg> Total</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg> Fecha de emisión</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg> Fecha de pago</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg> Referencias</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facturas)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    No se han encontrado facturas con los criterios seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($facturas as $f): ?>
                                <tr>
                                    <td>
                                        <a href="ficha_factura.php?id=<?= $f['id'] ?>" class="invoice-id-link">
                                            <?= $f['id'] ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($f['numero_factura']) ?></td>
                                    <td><?= htmlspecialchars($f['cif']) ?></td>
                                    <td><?= htmlspecialchars($f['razon_social']) ?></td>
                                    <td style="font-weight: 600; text-align: right;">
                                        <?= number_format($f['total'], 2, ',', '.') ?> €
                                    </td>
                                    <td><?= $f['fecha_emision'] ? date('d/m/Y', strtotime($f['fecha_emision'])) : '---' ?></td>
                                    <td><?= $f['fecha_pago'] ? date('d/m/Y', strtotime($f['fecha_pago'])) : '---' ?></td>
                                    <td style="color: var(--text-muted);"><?= htmlspecialchars($f['referencia'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="results-info">
                Se han encontrado <strong><?= $total_registros ?></strong> registros.
            </div>
        </section>

        <!-- PAGINACIÓN (Simplificada) -->
        <div class="invoice-pagination">
            <a href="#" class="page-btn disabled">Primero</a>
            <a href="#" class="page-btn disabled">Anterior</a>
            <a href="#" class="page-btn active">1</a>
            <a href="#" class="page-btn">2</a>
            <a href="#" class="page-btn">3</a>
            <a href="#" class="page-btn">Siguiente</a>
            <a href="#" class="page-btn">Último</a>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="contabilidad.php" class="btn btn-invoice-secondary" style="text-decoration: none;">
                Volver a Contabilidad
            </a>
        </div>

    </main>
</div>

</body>
</html>
