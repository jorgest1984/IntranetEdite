<?php
// convocatorias.php
session_start();
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
        $codigo = trim($_POST['codigo_expediente']);
        $nombre = trim($_POST['nombre']);
        $tipo = trim($_POST['tipo']);
        $organismo = trim($_POST['organismo']);
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convocatorias SEPE/FUNDAE - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .list-section { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .form-section { background: #fdf2f2; border-radius: 12px; border: 1px solid #fecaca; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.05); }
        
        .horizontal-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        /* Tables & Filters */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { font-weight: 600; color: var(--text-muted); background-color: #f8fafc; }
        .data-table tr:hover td { background-color: #fef2f2; }
        
        .filters-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-input { flex: 1; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; min-width: 200px;}
        .filter-select { padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background-color: white;}
        
        /* Badges */
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-primary { background: #fee2e2; color: #dc2626; }
        .badge-neutral { background: #f3f4f6; color: #6b7280; }
        
        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; }
        .form-input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
        
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
                <h1>Convocatorias y Expedientes</h1>
                <p>Gestión de acciones formativas subvencionadas y privadas</p>
            </div>
        </header>

        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

        <!-- Nueva Convocatoria (ARRIBA) -->
        <?php if (has_permission([ROLE_ADMIN, ROLE_COORD])): ?>
        <section class="form-section">
            <h2 style="margin-top: 0; font-size: 1rem; color: var(--primary-color); border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                Apertura de Expediente
            </h2>
            
            <form method="POST" action="" class="horizontal-form">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Código Expediente *</label>
                    <input type="text" name="codigo_expediente" class="form-input" required placeholder="Ej: 98/2026/001">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre del Proyecto *</label>
                    <input type="text" name="nombre" class="form-input" required placeholder="Nombre del proyecto">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tipo de Formación *</label>
                    <select name="tipo" class="form-input" required>
                        <option value="SEPE_DESEMPLEADOS">SEPE - Desempleados</option>
                        <option value="FUNDAE_OCUPADOS">FUNDAE - Ocupados</option>
                        <option value="PRIVADA">Privada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Entidad / Organismo</label>
                    <input type="text" name="organismo" class="form-input" placeholder="Ej: SEPE">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px; justify-content: center; font-weight: 600;">
                        CREAR EXPEDIENTE
                    </button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- Listado de Convocatorias -->
        <section class="list-section">
            <form method="GET" class="filters-bar">
                <input type="text" name="search" class="filter-input" placeholder="Buscar por Código o Nombre..." value="<?= htmlspecialchars($search) ?>">
                
                <select name="tipo" class="filter-select">
                    <option value="">Cualquier Tipo</option>
                    <option value="SEPE_DESEMPLEADOS" <?= $tipoFilter=='SEPE_DESEMPLEADOS'?'selected':'' ?>>SEPE Desempleados</option>
                    <option value="FUNDAE_OCUPADOS" <?= $tipoFilter=='FUNDAE_OCUPADOS'?'selected':'' ?>>FUNDAE Ocupados</option>
                    <option value="PRIVADA" <?= $tipoFilter=='PRIVADA'?'selected':'' ?>>Privada</option>
                </select>
                
                <select name="estado" class="filter-select">
                    <option value="">Cualquier Estado</option>
                    <option value="Borrador" <?= $estadoFilter=='Borrador'?'selected':'' ?>>Borrador</option>
                    <option value="Aprobada" <?= $estadoFilter=='Aprobada'?'selected':'' ?>>Aprobada</option>
                    <option value="En Ejecución" <?= $estadoFilter=='En Ejecución'?'selected':'' ?>>En Ejecución</option>
                    <option value="Finalizada" <?= $estadoFilter=='Finalizada'?'selected':'' ?>>Finalizada</option>
                    <option value="Justificada" <?= $estadoFilter=='Justificada'?'selected':'' ?>>Justificada</option>
                </select>

                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="convocatorias.php" class="btn" style="border: 1px solid #e5e7eb;">Limpiar</a>
            </form>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Expediente</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Alumnos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($convocatoriasList)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No se encontraron convocatorias.</td></tr>
                        <?php else: ?>
                            <?php foreach ($convocatoriasList as $conv): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($conv['codigo_expediente']) ?></td>
                                <td><?= htmlspecialchars($conv['nombre']) ?></td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 500;"><?= str_replace('_', ' ', htmlspecialchars($conv['tipo'])) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($conv['organismo']) ?></div>
                                </td>
                                <td><span class="badge <?= getBadgeClass($conv['estado']) ?>"><?= htmlspecialchars($conv['estado']) ?></span></td>
                                <td style="text-align: center; font-weight: 600;"><?= $conv['total_alumnos'] ?></td>
                                <td>
                                    <a href="matriculas.php?convocatoria_id=<?= $conv['id'] ?>" class="btn" style="padding: 0.4rem 0.8rem; border: 1px solid var(--border-color); font-size: 0.8rem;">
                                        Gestionar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Nueva Convocatoria (ABAJO) -->
        <?php if (has_permission([ROLE_ADMIN, ROLE_COORD])): ?>
        <section class="form-section">
            <h2 style="margin-top: 0; font-size: 1rem; color: var(--primary-color); border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                Apertura de Expediente (Repetido)
            </h2>
            
            <form method="POST" action="" class="horizontal-form">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Código Expediente *</label>
                    <input type="text" name="codigo_expediente" class="form-input" required placeholder="Ej: 98/2026/001">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre del Proyecto *</label>
                    <input type="text" name="nombre" class="form-input" required placeholder="Nombre del proyecto">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tipo de Formación *</label>
                    <select name="tipo" class="form-input" required>
                        <option value="SEPE_DESEMPLEADOS">SEPE - Desempleados</option>
                        <option value="FUNDAE_OCUPADOS">FUNDAE - Ocupados</option>
                        <option value="PRIVADA">Privada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px; justify-content: center; font-weight: 600;">
                        ABRIR NUEVO EXPEDIENTE
                    </button>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </main>
</div>

</body>
</html>
