<?php
// empresas.php
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$active_tab = $_GET['tab'] ?? 'search';
$error = '';
$success = '';

// Lógica de Búsqueda
$search_results = [];
if ($active_tab == 'search') {
    $nombre = $_GET['nombre'] ?? '';
    $telefono = $_GET['telefono'] ?? '';
    $contacto = $_GET['contacto'] ?? '';
    $provincia = $_GET['provincia'] ?? '';
    $es_vigilante = $_GET['es_vigilante'] ?? 'NO';

    $sql = "SELECT * FROM empresas WHERE 1=1";
    $params = [];

    if ($nombre) { $sql .= " AND nombre LIKE ?"; $params[] = "%$nombre%"; }
    if ($telefono) { $sql .= " AND (telefono LIKE ? OR contacto_telefono LIKE ?)"; $params[] = "%$telefono%"; $params[] = "%$telefono%"; }
    if ($contacto) { $sql .= " AND contacto_nombre LIKE ?"; $params[] = "%$contacto%"; }
    if ($provincia) { $sql .= " AND provincia = ?"; $params[] = $provincia; }
    if ($es_vigilante == 'SI') { $sql .= " AND es_vigilante = 1"; }

    $sql .= " ORDER BY nombre ASC LIMIT 100";
    
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $search_results = $st->fetchAll();
    } catch (Exception $e) { $error = "Error en la búsqueda: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresas / Centros - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>Gestión de Empresas / Centros</h1>
                    <p>Centros de Impartición y Colaboradores</p>
                </div>
            </header>

            <!-- Tabs Navigation -->
            <div class="tabs-container">
                <div class="tabs">
                    <a href="?tab=search" class="tab-link <?= $active_tab == 'search' ? 'active' : '' ?>">Búsqueda de Empresas</a>
                    <a href="?tab=new" class="tab-link <?= $active_tab == 'new' ? 'active' : '' ?>">Nueva Empresa</a>
                </div>
            </div>

            <div class="tab-content-wrapper">
                
                <!-- TAB: BÚSQUEDA -->
                <?php if ($active_tab == 'search'): ?>
                <div class="card search-card">
                    <div class="card-header" style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <h5 style="color: #c2410c; text-transform: uppercase; font-size: 0.9rem; font-weight: 700; text-align: center; width: 100%;">
                            Centros de Impartición - Campos de Búsqueda
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="search-fields-grid">
                            <input type="hidden" name="tab" value="search">
                            
                            <div class="search-row">
                                <div class="search-group">
                                    <label>Nombre:</label>
                                    <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
                                </div>
                                <div class="search-group">
                                    <label>Teléfono:</label>
                                    <input type="text" name="telefono" value="<?= htmlspecialchars($_GET['telefono'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="search-row">
                                <div class="search-group">
                                    <label>Contacto:</label>
                                    <input type="text" name="contacto" value="<?= htmlspecialchars($_GET['contacto'] ?? '') ?>">
                                </div>
                                <div class="search-group">
                                    <label>Provincia:</label>
                                    <select name="provincia">
                                        <option value="">Todas</option>
                                        <option value="MADRID" <?= ($_GET['provincia'] ?? '') == 'MADRID' ? 'selected' : '' ?>>MADRID</option>
                                        <option value="PONTEVEDRA" <?= ($_GET['provincia'] ?? '') == 'PONTEVEDRA' ? 'selected' : '' ?>>PONTEVEDRA</option>
                                        <!-- Más provincias aquí -->
                                    </select>
                                </div>
                                <div class="search-group">
                                    <label>Ver Centros sólo A. Vigilantes Seg.:</label>
                                    <select name="es_vigilante">
                                        <option value="NO" <?= ($_GET['es_vigilante'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                                        <option value="SI" <?= ($_GET['es_vigilante'] ?? '') == 'SI' ? 'selected' : '' ?>>SÍ</option>
                                    </select>
                                </div>
                            </div>

                            <div style="text-align: center; margin-top: 1rem;">
                                <button type="submit" class="btn-buscar">Buscar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card results-card" style="margin-top: 2rem;">
                    <div class="card-header" style="background: #fff; border-bottom: 2px solid #e2e8f0;">
                        <h5 style="color: #c2410c; text-transform: uppercase; font-size: 0.9rem; font-weight: 700; text-align: center; width: 100%;">
                            Resultado de la Búsqueda
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Nombre</th>
                                    <th>CIF</th>
                                    <th>Localidad</th>
                                    <th>Provincia</th>
                                    <th>Teléfono</th>
                                    <th>Contacto</th>
                                    <th colspan="2">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($search_results)): ?>
                                <tr><td colspan="9" style="text-align: center; padding: 2rem; color: #64748b;">No se han encontrado resultados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($search_results as $emp): ?>
                                    <tr>
                                        <td style="text-align: center;"><svg viewBox="0 0 24 24" width="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg></td>
                                        <td style="color: #1e40af; font-weight: 600;"><?= htmlspecialchars($emp['nombre']) ?></td>
                                        <td><?= htmlspecialchars($emp['cif'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($emp['localidad'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($emp['provincia'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($emp['telefono'] ?? '') ?></td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($emp['contacto_nombre'] ?? '') ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($emp['contacto_telefono'] ?? '') ?></div>
                                        </td>
                                        <td style="width: 60px;"><a href="ficha_empresa.php?id=<?= $emp['id'] ?>" class="link-action">Datos</a></td>
                                        <td style="width: 60px;"><a href="#" class="link-action delete" onclick="return confirm('¿Seguro?')">Borrar</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB: NUEVA EMPRESA -->
                <?php if ($active_tab == 'new'): ?>
                <div class="card form-card">
                    <div class="card-header">
                        <h5>Registro de Nueva Empresa</h5>
                    </div>
                    <div class="card-body">
                        <p style="text-align: center; color: #64748b; padding: 2rem;">Esperando diseño detallado para el formulario de creación...</p>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>

<style>
/* Estilos específicos para el módulo de Empresas */
.search-fields-grid {
    background: #fff;
    padding: 1rem;
}
.search-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 0.75rem;
    align-items: center;
    justify-content: center;
}
.search-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.search-group label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e40af;
    white-space: nowrap;
}
.search-group input, .search-group select {
    border: 1px solid #cbd5e1;
    padding: 0.25rem 0.5rem;
    border-radius: 2px;
    font-size: 0.9rem;
    background: #f1f5f9;
}
.search-group input { width: 250px; }
.btn-buscar {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    padding: 0.2rem 1.5rem;
    font-size: 0.85rem;
    cursor: pointer;
    border-radius: 2px;
}
.btn-buscar:hover { background: #e2e8f0; }

.table-custom {
    width: 100%;
    border-collapse: collapse;
}
.table-custom th {
    background: #f1f5f9;
    padding: 0.75rem;
    text-align: left;
    font-size: 0.85rem;
    color: #1e40af;
    border-right: 1px solid #e2e8f0;
}
.table-custom td {
    padding: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.9rem;
    vertical-align: middle;
}
.link-action {
    color: #1e40af;
    text-decoration: none;
    font-weight: 500;
}
.link-action.delete { color: #dc2626; }
</style>

</body>
</html>
