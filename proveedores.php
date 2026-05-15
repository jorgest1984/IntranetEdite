<?php
// proveedores.php - Listado de Proveedores
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO])) {
    header("Location: home.php");
    exit();
}

$active_tab = 'contabilidad';
$error = '';
$success = '';

// Lógica de Búsqueda
$proveedores = [];
$total_registros = 0;

try {
    $sql = "SELECT * FROM proveedores WHERE 1=1";
    $params = [];

    if (!empty($_GET['nombre'])) {
        $sql .= " AND (nombre LIKE ? OR nombre_comercial LIKE ?)";
        $params[] = "%" . $_GET['nombre'] . "%";
        $params[] = "%" . $_GET['nombre'] . "%";
    }
    if (!empty($_GET['cif'])) {
        $sql .= " AND cif LIKE ?";
        $params[] = "%" . $_GET['cif'] . "%";
    }
    if (!empty($_GET['sector'])) {
        $sql .= " AND sector = ?";
        $params[] = $_GET['sector'];
    }
    if (!empty($_GET['poblacion'])) {
        $sql .= " AND poblacion LIKE ?";
        $params[] = "%" . $_GET['poblacion'] . "%";
    }

    $sql .= " ORDER BY nombre ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $proveedores = $stmt->fetchAll();
    $total_registros = count($proveedores);

} catch (Exception $e) {
    $error = "Error al cargar proveedores: " . $e->getMessage();
}

$sectores = ['Servicios', 'Formación', 'Informática', 'Construcción', 'Comercio', 'Hostelería', 'Otros'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/proveedores.css">
    <style>
        .proveedor-search-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .proveedor-table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .proveedor-table {
            width: 100%;
            border-collapse: collapse;
        }
        .proveedor-table th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        .proveedor-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-activo { background: #dcfce7; color: #166534; }
        .status-inactivo { background: #fee2e2; color: #991b1b; }
        
        .btn-icon-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: #eff6ff;
            color: #3b82f6;
            transition: all 0.2s;
        }
        .btn-icon-action:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Listado de Proveedores</h1>
                <p>Gestión de empresas colaboradoras y suministradores</p>
            </div>
            <div>
                <a href="nuevo_proveedor.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Nuevo Proveedor
                </a>
            </div>
        </header>

        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fecaca;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="proveedor-search-card">
            <form action="" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group-proveedor">
                    <label>Nombre / Razón Social</label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control-proveedor" placeholder="Buscar por nombre...">
                </div>
                <div class="form-group-proveedor">
                    <label>CIF / NIF</label>
                    <input type="text" name="cif" value="<?= htmlspecialchars($_GET['cif'] ?? '') ?>" class="form-control-proveedor" placeholder="B12345678">
                </div>
                <div class="form-group-proveedor">
                    <label>Sector</label>
                    <select name="sector" class="form-control-proveedor">
                        <option value="">Todos los sectores</option>
                        <?php foreach($sectores as $s): ?>
                            <option value="<?= $s ?>" <?= ($_GET['sector'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-proveedor">
                    <label>Población</label>
                    <input type="text" name="poblacion" value="<?= htmlspecialchars($_GET['poblacion'] ?? '') ?>" class="form-control-proveedor" placeholder="Ej: Barcelona">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px; cursor: pointer; border: none; font-weight: 600;">Filtrar</button>
                </div>
            </form>
        </section>

        <section class="proveedor-results">
            <div class="proveedor-table-container">
                <table class="proveedor-table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>CIF</th>
                            <th>Sector</th>
                            <th>Localización</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proveedores)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 4rem; color: #64748b;">
                                    <div style="font-size: 2rem; margin-bottom: 10px;">🔍</div>
                                    No se han encontrado proveedores con los criterios de búsqueda.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($proveedores as $p): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #1e3a8a;"><?= htmlspecialchars($p['nombre']) ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($p['nombre_comercial'] ?? '') ?></div>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 600; color: #475569;">
                                        <?= htmlspecialchars($p['cif']) ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.8rem; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; color: #475569;">
                                            <?= htmlspecialchars($p['sector'] ?? '---') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($p['poblacion'] ?? '---') ?></div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;"><?= htmlspecialchars($p['provincia'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($p['telefono'] ?? ($p['movil'] ?? '---')) ?></div>
                                        <div style="font-size: 0.75rem; color: #3b82f6;"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($p['estado'] ?? 'activo') ?>">
                                            <?= htmlspecialchars($p['estado'] ?? 'Activo') ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="editar_proveedor.php?id=<?= $p['id'] ?>" class="btn-icon-action" title="Editar ficha de proveedor">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="font-size: 0.85rem; color: #64748b;">
                Se han encontrado <strong><?= $total_registros ?></strong> proveedores.
            </div>
        </section>
    </main>
</div>

</body>
</html>
