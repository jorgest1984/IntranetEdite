<?php
// incidencias.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Todos los usuarios pueden reportar incidencias, pero solo Admin las gestiona
$is_admin = has_permission([ROLE_ADMIN]);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'report') {
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $gravedad = $_POST['gravedad'];
        
        if (empty($titulo) || empty($descripcion)) {
            $error = "Por favor, describe el incidente.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO incidencias_seguridad (usuario_id, titulo, descripcion, gravedad, estado) VALUES (?, ?, ?, ?, 'Abierta')");
            $stmt->execute([$_SESSION['user_id'], $titulo, $descripcion, $gravedad]);
            
            audit_log($pdo, 'INCIDENCIA_REPORTADA', 'incidencias', $pdo->lastInsertId(), null, ['titulo' => $titulo]);
            $success = "Incidencia registrada correctamente. El responsable de seguridad la revisará en breve.";
        }
    }
    
    if ($_POST['action'] == 'resolve' && $is_admin) {
        $id = intval($_POST['incidencia_id']);
        $comentario = trim($_POST['comentario_resolucion']);
        
        $stmt = $pdo->prepare("UPDATE incidencias_seguridad SET estado = 'Resuelta', resolucion = ?, fecha_resolucion = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$comentario, $id]);
        
        audit_log($pdo, 'INCIDENCIA_RESUELTA', 'incidencias', $id);
        $success = "Incidencia marcada como resuelta.";
    }
}

// Cargar incidencias
if ($is_admin) {
    $stmt = $pdo->query("SELECT i.*, u.username FROM incidencias_seguridad i JOIN usuarios u ON i.usuario_id = u.id ORDER BY i.fecha_reporte DESC");
} else {
    $stmt = $pdo->prepare("SELECT i.*, u.username FROM incidencias_seguridad i JOIN usuarios u ON i.usuario_id = u.id WHERE i.usuario_id = ? ORDER BY i.fecha_reporte DESC");
    $stmt->execute([$_SESSION['user_id']]);
}
$incidencias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Incidencias - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .incident-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
        .grid-incidencias { display: grid; gap: 1.5rem; }
        .incidencia-item { background: #fff; border: 1px solid var(--border-color); border-radius: 8px; padding: 1.2rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .status-pill { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-Abierta { background: #fee2e2; color: #dc2626; }
        .status-Resuelta { background: #d1fae5; color: #059669; }
        .gravedad-Alta { border-left: 4px solid #dc2626; }
        .gravedad-Media { border-left: 4px solid #f59e0b; }
        .gravedad-Baja { border-left: 4px solid #3b82f6; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Gestión de Incidencias de Seguridad</h1>
                <p>ISO 27001 - Control A.6.8: Notificación y Gestión de Incidentes</p>
            </div>
        </header>

        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="incident-card">
            <h2 style="font-size: 1.1rem; margin-top: 0;">Reportar Nueva Incidencia</h2>
            <form method="POST">
                <input type="hidden" name="action" value="report">
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div style="flex: 2;">
                        <label class="form-label">Título del incidente</label>
                        <input type="text" name="titulo" class="form-input" placeholder="Ej: Pérdida de dispositivo, Intento de acceso sospechoso..." required style="width: 100%;">
                    </div>
                    <div style="flex: 1;">
                        <label class="form-label">Gravedad</label>
                        <select name="gravedad" class="form-input" style="width: 100%;">
                            <option value="Baja">Baja</option>
                            <option value="Media">Media</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label class="form-label">Descripción Detallada</label>
                    <textarea name="descripcion" class="form-input" style="width: 100%; min-height: 80px;" required placeholder="Explique qué sucedió y cuándo..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Enviar Reporte</button>
            </form>
        </div>

        <h2 style="font-size: 1.2rem; margin-bottom: 1rem;">Historial de Incidencias</h2>
        <div class="grid-incidencias">
            <?php foreach ($incidencias as $i): ?>
                <div class="incidencia-item gravedad-<?= $i['gravedad'] ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <span class="status-pill status-<?= $i['estado'] ?>"><?= $i['estado'] ?></span>
                            <strong style="margin-left: 0.5rem; font-size: 1.05rem;"><?= htmlspecialchars($i['titulo']) ?></strong>
                        </div>
                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($i['fecha_reporte'])) ?></span>
                    </div>
                    <p style="font-size: 0.9rem; color: #4b5563; margin: 0.5rem 0;"><?= nl2br(htmlspecialchars($i['descripcion'])) ?></p>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                        Reportado por: <strong><?= htmlspecialchars($i['username']) ?></strong> | Gravedad: <?= $i['gravedad'] ?>
                    </div>
                    
                    <?php if ($i['estado'] == 'Resuelta'): ?>
                        <div style="margin-top: 1rem; padding: 0.8rem; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                            <strong>Resolución:</strong> <?= htmlspecialchars($i['resolucion']) ?>
                            <div style="font-size: 0.75rem; margin-top: 0.3rem;">Resuelto el <?= date('d/m/Y', strtotime($i['fecha_resolucion'])) ?></div>
                        </div>
                    <?php elseif ($is_admin): ?>
                        <form method="POST" style="margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="incidencia_id" value="<?= $i['id'] ?>">
                            <input type="text" name="comentario_resolucion" class="form-input" placeholder="Comentario de resolución..." required style="width: 70%; margin-right: 0.5rem;">
                            <button type="submit" class="btn btn-primary">Marcar como Resuelta</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

</body>
</html>
