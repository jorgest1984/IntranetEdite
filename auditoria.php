<?php
// auditoria.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Solo administradores pueden ver los logs de auditoría (ISO 27001 - A.5.33)
if (!has_permission([ROLE_ADMIN])) {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';

// Filtros
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

// Consulta de logs
$query = "SELECT a.*, u.username, u.nombre, u.apellidos 
          FROM audit_log a 
          JOIN usuarios u ON a.usuario_id = u.id 
          WHERE DATE(a.fecha) >= ? AND DATE(a.fecha) <= ?";
$params = [$fecha_desde, $fecha_hasta];

if ($user_id > 0) {
    $query .= " AND a.usuario_id = ?";
    $params[] = $user_id;
}
if (!empty($accion)) {
    $query .= " AND a.accion LIKE ?";
    $params[] = "%$accion%";
}

$query .= " ORDER BY a.fecha DESC LIMIT 200";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener lista de usuarios para el filtro
$usuarios = $pdo->query("SELECT id, username FROM usuarios ORDER BY username ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría ISO 27001 - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .filter-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: flex-end;
        }
        .log-table-container {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .log-table th, .log-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .log-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        .log-table tr:hover td {
            background-color: #fef2f2;
        }
        .badge-action {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
        }
        .log-details {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        .iso-notice {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: #92400e;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Auditoría y Trazabilidad</h1>
                <p>Registro de eventos inmutable (ISO 27001 Compliance)</p>
            </div>
        </header>

        <div class="iso-notice">
            <strong>Protección de Registros (ISO 27001 - A.8.15):</strong> Este registro es de solo lectura y no puede ser modificado ni eliminado desde el panel. Registra fallos de autenticación, accesos a datos sensibles y cambios de configuración.
        </div>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <select name="user_id" class="form-input">
                        <option value="0">Todos los usuarios</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_id == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Acción</label>
                    <input type="text" name="accion" class="form-input" placeholder="Ej: LOGIN_FAIL" value="<?= htmlspecialchars($accion) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" class="form-input" value="<?= htmlspecialchars($fecha_desde) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-input" value="<?= htmlspecialchars($fecha_hasta) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>
        </div>

        <div class="log-table-container">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Módulo</th>
                        <th>ID Entidad</th>
                        <th>IP / Navegador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No se encontraron registros de auditoría para los criterios seleccionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;">
                                    <?= date('d/m/Y H:i:s', strtotime($log['fecha'])) ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($log['username']) ?></strong><br>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($log['nombre'] . ' ' . $log['apellidos']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-action"><?= htmlspecialchars($log['accion']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($log['entidad']) ?></td>
                                <td><?= $log['entidad_id'] ?: '-' ?></td>
                                <td class="log-details" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                    <?= htmlspecialchars($log['ip_address']) ?><br>
                                    <span style="font-size: 0.7rem;"><?= htmlspecialchars(substr($log['user_agent'], 0, 50)) ?>...</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>
