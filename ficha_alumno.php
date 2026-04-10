<?php
// ficha_alumno.php - Perfil Exclusivo para Alumnos
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';
$moodle = new MoodleAPI($pdo);

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: alumnos.php");
    exit();
}

// Cargar datos del alumno
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->execute([$id]);
$alumno = $stmt->fetch();

if (!$alumno) {
    die("Alumno no encontrado.");
}

// Cargar documentos asociados
$stmtDocs = $pdo->prepare("
    SELECT d.*, u.nombre as username 
    FROM documentos_alumno d
    JOIN usuarios u ON d.usuario_id = u.id
    WHERE d.alumno_id = ?
    ORDER BY d.fecha_subida DESC
");
$stmtDocs->execute([$id]);
$documentos = $stmtDocs->fetchAll();

$active_tab = $_GET['tab'] ?? 'personales';

// Acción: Sincronización Inteligente Moodle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'moodle_update') {
    try {
        if (!$moodle->isConfigured()) throw new Exception("Moodle no está configurado.");
        
        $muid = $alumno['moodle_user_id'];
        $was_created = false;
        
        $mResult = $moodle->getUsersByField('email', [$alumno['email']]);
        
        if (!empty($mResult['users'])) {
            $muid = $mResult['users'][0]['id'];
        } else {
            $pass = "T" . substr(md5(time()), 0, 8) . "!";
            $newUsers = $moodle->createUser(
                strtolower(explode('@', $alumno['email'])[0]),
                $pass,
                $alumno['nombre'],
                $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido'],
                $alumno['email']
            );
            $muid = $newUsers[0]['id'];
            $was_created = true;
        }
        
        if ($muid && $alumno['moodle_user_id'] != $muid) {
            $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$muid, $id]);
            $alumno['moodle_user_id'] = $muid;
        }
        
        $moodle->updateUser($muid, [
            'firstname' => $alumno['nombre'],
            'lastname'  => $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido'],
            'email'     => $alumno['email']
        ]);
        
        $msg = $was_created ? "&moodle_ok=1&created=1" : "&moodle_ok=1";
        header("Location: ficha_alumno.php?id=$id$msg");
        exit();
    } catch (Exception $e) {
        $error = "Moodle Sync Error: " . $e->getMessage();
    }
}

// Acción: Actualizar Datos Personales
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_personales') {
    try {
        $fields = [
            'nombre', 'primer_apellido', 'segundo_apellido', 'dni', 'fecha_nacimiento',
            'seguridad_social', 'cuenta_bancaria', 'domicilio', 'cp', 'localidad',
            'provincia', 'telefono', 'telefono_empresa', 'email', 'email_personal',
            'teams', 'nacionalidad', 'sexo', 'activo_hasta', 'es_nuestro', 'observaciones'
        ];
        
        $set = [];
        $params = [];
        foreach($fields as $f) {
            $set[] = "$f = ?";
            $val = isset($_POST[$f]) ? trim($_POST[$f]) : null;
            $params[] = ($val === '') ? null : $val;
        }
        $params[] = $id;
        
        $st = $pdo->prepare("UPDATE alumnos SET " . implode(', ', $set) . " WHERE id = ?");
        $st->execute($params);
        
        header("Location: ficha_alumno.php?id=$id&tab=personales&success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error al actualizar datos personales: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Alumno: <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['primer_apellido']) ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .tabs-header {
            display: flex;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
        }
        .tab-btn {
            padding: 1rem 1.5rem;
            border: none;
            background: none;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            border-right: 1px solid var(--border-color);
        }
        .tab-btn.active { background: white; color: var(--primary-color); font-weight: 600; border-bottom: 2px solid var(--primary-color); }
        .tab-panel {
            background: white;
            padding: 2rem;
            border-radius: 0 0 12px 12px;
            border: 1px solid var(--border-color);
            border-top: none;
            min-height: 400px;
        }
        .prof-form-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .prof-form-label { width: 140px; font-weight: 600; font-size: 0.9rem; color: var(--text-color); }
        .prof-form-input { flex: 1; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="header-premium" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <a href="alumnos.php" class="btn-back">← Volver al listado</a>
                <h1 style="margin-top: 0.5rem;">Ficha del Alumno</h1>
            </div>
            <div style="display: flex; gap: 1rem;">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="moodle_update">
                    <button type="submit" class="btn btn-primary">Sincronizar Moodle</button>
                </form>
            </div>
        </div>

        <nav class="tabs-header">
            <button class="tab-btn <?= $active_tab == 'personales' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=personales'">Datos Personales</button>
            <button class="tab-btn <?= $active_tab == 'inscripciones' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=inscripciones'">Cursos / Inscripciones</button>
            <button class="tab-btn <?= $active_tab == 'documentacion' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=documentacion'">Documentación</button>
        </nav>

        <div class="tab-panel">
            <?php if (isset($_GET['success'])): ?><div class="alert alert-success">Datos actualizados.</div><?php endif; ?>
            <?php if (isset($_GET['moodle_ok'])): ?><div class="alert alert-success">Sincronización con Moodle completada.</div><?php endif; ?>
            <?php if (isset($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <!-- TAB: Personales -->
            <div id="tab-personales" style="<?= $active_tab == 'personales' ? '' : 'display:none;' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="update_personales">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <h3>Identificación</h3>
                            <div class="prof-form-row">
                                <label class="prof-form-label">Nombre:</label>
                                <input type="text" name="nombre" class="prof-form-input" value="<?= htmlspecialchars($alumno['nombre'] ?? '') ?>">
                            </div>
                            <div class="prof-form-row">
                                <label class="prof-form-label">Apellidos:</label>
                                <input type="text" name="primer_apellido" class="prof-form-input" style="width: 45%;" value="<?= htmlspecialchars($alumno['primer_apellido'] ?? '') ?>">
                                <input type="text" name="segundo_apellido" class="prof-form-input" style="width: 45%;" value="<?= htmlspecialchars($alumno['segundo_apellido'] ?? '') ?>">
                            </div>
                            <div class="prof-form-row">
                                <label class="prof-form-label">DNI/NIE:</label>
                                <input type="text" name="dni" class="prof-form-input" value="<?= htmlspecialchars($alumno['dni'] ?? '') ?>">
                            </div>
                        </div>
                        <div>
                            <h3>Contacto</h3>
                            <div class="prof-form-row">
                                <label class="prof-form-label">Email Principal:</label>
                                <input type="email" name="email" class="prof-form-input" value="<?= htmlspecialchars($alumno['email'] ?? '') ?>">
                            </div>
                            <div class="prof-form-row">
                                <label class="prof-form-label">Teléfono:</label>
                                <input type="text" name="telefono" class="prof-form-input" value="<?= htmlspecialchars($alumno['telefono'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 2rem; text-align: right;">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>

            <!-- TAB: Inscripciones (Placeholder para futuros cursos) -->
            <div id="tab-inscripciones" style="<?= $active_tab == 'inscripciones' ? '' : 'display:none;' ?>">
                <div class="empty-state">
                    <p>No hay cursos registrados para este alumno todavía.</p>
                </div>
            </div>

            <!-- TAB: Documentación -->
            <div id="tab-documentacion" style="<?= $active_tab == 'documentacion' ? '' : 'display:none;' ?>">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($documentos as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['nombre_archivo']) ?></td>
                            <td><?= $doc['fecha_subida'] ?></td>
                            <td><?= htmlspecialchars($doc['username']) ?></td>
                            <td><a href="<?= $doc['ruta'] ?>" target="_blank">Descargar</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($documentos)): ?>
                        <tr><td colspan="4" style="text-align:center;">No hay documentos subidos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

</body>
</html>
