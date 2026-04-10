<?php
// convocatorias.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Procesar formulario de nueva convocatoria
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && has_permission([ROLE_ADMIN, ROLE_COORD])) {
    if ($_POST['action'] == 'create') {
        $codigo = trim($_POST['codigo_expediente'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $organismo = trim($_POST['organismo'] ?? '');
        $presupuesto = empty($_POST['presupuesto']) ? null : floatval($_POST['presupuesto']);
        
        if (empty($codigo) || empty($nombre) || empty($tipo)) {
            $error = "El código, nombre y tipo son obligatorios.";
        } else {
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM convocatorias WHERE codigo_expediente = ?");
                $stmtCheck->execute([$codigo]);
                if ($stmtCheck->rowCount() > 0) {
                    throw new Exception("Ya existe una convocatoria con ese código de expediente.");
                }
                
                $stmt = $pdo->prepare("INSERT INTO convocatorias (codigo_expediente, nombre, tipo, organismo, presupuesto) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$codigo, $nombre, $tipo, $organismo, $presupuesto]);
                $nuevaConvocatoriaId = $pdo->lastInsertId();
                
                audit_log($pdo, 'CONVOCATORIA_CREADA', 'convocatorias', $nuevaConvocatoriaId, null, [
                    'codigo' => $codigo, 'tipo' => $tipo
                ]);
                
                $success = "Convocatoria creada correctamente.";
            } catch (Exception $e) {
                $error = "Error al crear la convocatoria: " . $e->getMessage();
            }
        }
    }
}

// Listar convocatorias
$search = $_GET['search'] ?? '';
$tipoFilter = $_GET['tipo'] ?? '';
$estadoFilter = $_GET['estado'] ?? '';

$query = "SELECT c.*, (SELECT COUNT(*) FROM matriculas m WHERE m.convocatoria_id = c.id) as total_alumnos FROM convocatorias c WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (c.codigo_expediente LIKE ? OR c.nombre LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($tipoFilter)) {
    $query .= " AND c.tipo = ?";
    $params[] = $tipoFilter;
}
if (!empty($estadoFilter)) {
    $query .= " AND c.estado = ?";
    $params[] = $estadoFilter;
}

$query .= " ORDER BY c.creado_en DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$convocatoriasList = $stmt->fetchAll();

function getBadgeClass($estado) {
    switch ($estado) {
        case 'Aprobada': return 'badge-success';
        case 'En Ejecución': return 'badge-primary';
        case 'Finalizada': return 'badge-warning';
        case 'Justificada': return 'badge-success';
        case 'Borrador': default: return 'badge-neutral';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convocatorias SEPE/FUNDAE - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-end; 
            margin-bottom: 2rem; 
        }
        .page-title h1 { font-size: 1.8rem; margin: 0; color: #333; }
        .page-title h2 { font-size: 1.4rem; margin: 0.5rem 0 0 0; color: #444; font-weight: 500; }
        
        /* Breadcrumb style */
        .breadcrumb { 
            background: #f1f5f9; 
            padding: 0.75rem 1.5rem; 
            border-radius: 8px; 
            margin-bottom: 2rem; 
            font-size: 0.9rem; 
            color: #64748b; 
        }
        .breadcrumb a { color: #006ce4; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { margin: 0 0.5rem; color: #94a3b8; }

        .btn-nova {
            background-color: #006ce4;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: background 0.2s;
        }
        .btn-nova:hover { background-color: #0056b3; }

        /* Modified Table */
        .convocatorias-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .convocatorias-table thead th { 
            background-color: #1e293b; 
            color: white; 
            text-align: left; 
            padding: 0.75rem 1.5rem; 
            font-weight: 600; 
            text-transform: none; 
        }
        .convocatorias-table tbody td { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .convocatorias-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        
        .entry-title { color: #006ce4; font-weight: 500; text-decoration: none; font-size: 1rem; display: block; margin-bottom: 0.25rem; }
        .entry-title:hover { text-decoration: underline; }
        .entry-subtitle { color: #64748b; font-size: 0.85rem; }

        .actions-cell { display: flex; align-items: center; justify-content: flex-end; gap: 2rem; }
        .actas-link { color: #006ce4; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
        .actas-link:hover { text-decoration: underline; }
        .action-icons { display: flex; gap: 0.75rem; align-items: center; }
        
        .icon-btn { cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .icon-edit { color: #006ce4; }
        .icon-delete { color: #ef4444; font-weight: bold; font-size: 1.2rem; line-height: 1; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Formación</h1>
                <h2>Convocatorias</h2>
            </div>
            <?php if (has_permission([ROLE_ADMIN, ROLE_COORD])): ?>
                <a href="#" class="btn-nova" onclick="document.getElementById('form-nueva').style.display='block'; return false;">
                    Nueva convocatoria
                </a>
            <?php endif; ?>
        </header>

        <div class="breadcrumb">
            <a href="home.php">Inicio</a><span>/</span><a href="formacion_profesional.php">Formación</a><span>/</span>Convocatorias
        </div>

        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

        <!-- Formulario oculto por defecto para crear -->
        <section id="form-nueva" class="list-section" style="display:none; margin-bottom: 2rem; border: 1px solid #cbd5e1;">
            <h3 style="margin-top: 0;">Nueva Convocatoria</h3>
            <form method="POST" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
                <input type="hidden" name="action" value="create">
                <div>
                    <label style="display:block; margin-bottom:0.5rem; font-size:0.85rem;">Código Expediente *</label>
                    <input type="text" name="codigo_expediente" required style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:0.5rem; font-size:0.85rem;">Nombre *</label>
                    <input type="text" name="nombre" required style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:0.5rem; font-size:0.85rem;">Tipo *</label>
                    <select name="tipo" style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:4px;">
                        <option value="SEPE_DESEMPLEADOS">SEPE - Desempleados</option>
                        <option value="FUNDAE_OCUPADOS">FUNDAE - Ocupados</option>
                        <option value="PRIVADA">Privada</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-nova" style="width:100%; border:none; cursor:pointer;">Guardar Convocatoria</button>
                    <button type="button" onclick="document.getElementById('form-nueva').style.display='none'" style="width:100%; margin-top:0.5rem; background:none; border:none; color:#666; cursor:pointer;">Cancelar</button>
                </div>
            </form>
        </section>

        <section class="list-container">
            <table class="convocatorias-table">
                <thead>
                    <tr>
                        <th>Convocatoria</th>
                        <th style="text-align: right; padding-right: 1.5rem;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($convocatoriasList)): ?>
                        <tr><td colspan="2" style="text-align: center; color: #64748b; padding: 3rem;">No hay convocatorias registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($convocatoriasList as $conv): ?>
                        <tr>
                            <td>
                                <a href="matriculas.php?convocatoria_id=<?= $conv['id'] ?>" class="entry-title">
                                    <?= htmlspecialchars($conv['nombre']) ?>
                                </a>
                                <div class="entry-subtitle">1 planes</div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="#" class="actas-link">
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>
                                        Actas de evaluación
                                    </a>
                                    <div class="action-icons">
                                        <a href="editar_convocatoria.php?id=<?= $conv['id'] ?>" class="icon-btn icon-edit" title="Editar">
                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                        </a>
                                        <a href="#" class="icon-btn icon-delete" title="Eliminar">
                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>
