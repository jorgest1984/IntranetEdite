<?php
// ficha_alumno.php - Perfil Exclusivo para Alumnos
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';
$moodle = new MoodleAPI($pdo);

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
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

// Migración automática: Añadir columna `accion_id` a la tabla `documentos_alumno` si no existe
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `documentos_alumno` LIKE 'accion_id'")->fetch();
    if (!$checkColumn) {
        $pdo->exec("ALTER TABLE `documentos_alumno` ADD COLUMN `accion_id` INT(11) DEFAULT NULL AFTER `usuario_id`");
    }
} catch (Exception $e) {
    // Ignorar errores silenciosamente en producción
}

// Cargar Acciones Formativas en las que el alumno está inscrito (para clasificar y agrupar documentos)
$acciones_inscrito = [];
try {
    $stmtAcciones = $pdo->prepare("
        SELECT DISTINCT af.id as accion_id, c.nombre_largo as curso_titulo, c.nombre_corto as curso_codigo
        FROM (
            SELECT m.alumno_id, g.accion_id 
            FROM matriculas m 
            JOIN grupos g ON m.grupo_id = g.id 
            WHERE m.alumno_id = ?
            UNION
            SELECT m.alumno_id, af.id as accion_id 
            FROM matriculas m 
            JOIN planes p ON m.convocatoria_id = p.convocatoria_id 
            JOIN acciones_formativas af ON af.plan_id = p.id 
            WHERE m.alumno_id = ?
        ) res
        JOIN acciones_formativas af ON res.accion_id = af.id
        JOIN cursos c ON af.curso_id = c.id
    ");
    $stmtAcciones->execute([$id, $id]);
    $acciones_inscrito = $stmtAcciones->fetchAll();
} catch (Exception $e) {
    $acciones_inscrito = [];
}

// Cargar documentos asociados con detalles de acción formativa si aplica
$stmtDocs = $pdo->prepare("
    SELECT d.*, u.nombre as username, c.nombre_largo as accion_titulo
    FROM documentos_alumno d
    JOIN usuarios u ON d.usuario_id = u.id
    LEFT JOIN acciones_formativas af ON d.accion_id = af.id
    LEFT JOIN cursos c ON af.curso_id = c.id
    WHERE d.alumno_id = ?
    ORDER BY d.fecha_subida DESC
");
$stmtDocs->execute([$id]);
$documentos = $stmtDocs->fetchAll();

// Cargar matrículas/inscripciones asociadas
$stmtMatriculas = $pdo->prepare("
    SELECT m.*, c.nombre as convocatoria_nombre, c.codigo_expediente
    FROM matriculas m
    LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
    WHERE m.alumno_id = ?
    ORDER BY m.creado_en DESC
");
$stmtMatriculas->execute([$id]);
$matriculas = $stmtMatriculas->fetchAll();

// Cargar todas las convocatorias para el select de agregar inscripción
$convocatorias = $pdo->query("SELECT id, nombre, codigo_expediente FROM convocatorias ORDER BY nombre ASC")->fetchAll();

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

// Acción: Añadir Inscripción
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_inscripcion') {
    try {
        $convocatoria_id = $_POST['convocatoria_id'] ?? null;
        $estado = $_POST['estado'] ?? 'Inscrito';
        $fecha_matricula = !empty($_POST['fecha_matricula']) ? $_POST['fecha_matricula'] : date('Y-m-d');
        
        if (empty($convocatoria_id)) {
            throw new Exception("Debes seleccionar una convocatoria.");
        }
        
        // Comprobar si ya está inscrito
        $stmtCheckMat = $pdo->prepare("SELECT id FROM matriculas WHERE alumno_id = ? AND convocatoria_id = ?");
        $stmtCheckMat->execute([$id, $convocatoria_id]);
        if ($stmtCheckMat->rowCount() > 0) {
            throw new Exception("El alumno ya está inscrito en esta convocatoria.");
        }
        
        // Insertar
        $stmtInsert = $pdo->prepare("INSERT INTO matriculas (alumno_id, convocatoria_id, estado, fecha_matricula, creado_en) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->execute([$id, $convocatoria_id, $estado, $fecha_matricula, date('Y-m-d H:i:s')]);
        $nuevaMatriculaId = $pdo->lastInsertId();
        
        audit_log($pdo, 'MATRICULA_CREADA', 'matriculas', $nuevaMatriculaId, null, [
            'alumno_id' => $id,
            'convocatoria_id' => $convocatoria_id,
            'estado' => $estado,
            'fecha_matricula' => $fecha_matricula
        ]);
        
        header("Location: ficha_alumno.php?id=$id&tab=inscripciones&success_add=1");
        exit();
    } catch (Exception $e) {
        $error = "Error al añadir inscripción: " . $e->getMessage();
    }
}

// Acción: Eliminar Inscripción
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_inscripcion') {
    try {
        $matricula_id = $_POST['matricula_id'] ?? null;
        if (empty($matricula_id)) {
            throw new Exception("Inscripción no válida.");
        }
        
        // Obtener datos antes de borrar para el log y para el título de la Papelera
        $stmtGetMat = $pdo->prepare("
            SELECT m.*, a.nombre, a.primer_apellido, a.segundo_apellido, 
                   c.nombre as convocatoria_nombre,
                   cur.nombre_largo as curso_titulo
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
            LEFT JOIN grupos g ON m.grupo_id = g.id
            LEFT JOIN acciones_formativas af ON g.accion_id = af.id
            LEFT JOIN cursos cur ON af.curso_id = cur.id
            WHERE m.id = ? AND m.alumno_id = ?
        ");
        $stmtGetMat->execute([$matricula_id, $id]);
        $oldMat = $stmtGetMat->fetch(PDO::FETCH_ASSOC);
        
        if ($oldMat) {
            require_once 'includes/Papelera.php';
            $alumno_nombre = trim($oldMat['nombre'] . ' ' . ($oldMat['primer_apellido'] ?? '') . ' ' . ($oldMat['segundo_apellido'] ?? ''));
            $nombre_curso = $oldMat['curso_titulo'] ?: ($oldMat['convocatoria_nombre'] ?? 'Sin Convocatoria/Curso');
            $titulo_papelera = $alumno_nombre . " - " . $nombre_curso;
            
            $pdo->beginTransaction();
            try {
                // Obtener el registro limpio de la matrícula para archivar en Papelera (solo campos de la tabla matriculas)
                $stmtMatClean = $pdo->prepare("SELECT * FROM matriculas WHERE id = ?");
                $stmtMatClean->execute([$matricula_id]);
                $matricula_clean = $stmtMatClean->fetch(PDO::FETCH_ASSOC);

                if ($matricula_clean) {
                    Papelera::archivar($pdo, 'matriculas', $matricula_id, $titulo_papelera, ['matriculas' => $matricula_clean]);
                }
                
                $stmtDel = $pdo->prepare("DELETE FROM matriculas WHERE id = ?");
                $stmtDel->execute([$matricula_id]);
                
                audit_log($pdo, 'MATRICULA_ELIMINADA', 'matriculas', $matricula_id, $oldMat, null);
                
                $pdo->commit();
            } catch (Exception $transactionEx) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $transactionEx;
            }
        }
        
        header("Location: ficha_alumno.php?id=$id&tab=inscripciones&success_delete=1");
        exit();
    } catch (Exception $e) {
        $error = "Error al eliminar inscripción: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
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
            <?php if (isset($_GET['success']) && $active_tab != 'inscripciones'): ?><div class="alert alert-success">Datos actualizados.</div><?php endif; ?>
            <?php if (isset($_GET['success_add'])): ?><div class="alert alert-success">¡Inscripción añadida correctamente!</div><?php endif; ?>
            <?php if (isset($_GET['success_delete'])): ?><div class="alert alert-success">Inscripción eliminada correctamente.</div><?php endif; ?>
            <?php if (isset($_GET['moodle_ok'])): ?><div class="alert alert-success">Sincronización con Moodle completada.</div><?php endif; ?>
            <?php if (isset($_GET['upload_success'])): ?><div class="alert alert-success">✓ Documento subido y clasificado correctamente.</div><?php endif; ?>
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

            <!-- TAB: Inscripciones -->
            <div id="tab-inscripciones" style="<?= $active_tab == 'inscripciones' ? '' : 'display:none;' ?>">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                    
                    <!-- Columna Izquierda: Listado de Inscripciones Existentes -->
                    <div>
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; color: var(--text-color);">
                            <svg style="width: 20px; height: 20px; fill: var(--primary-color);" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                            Cursos e Inscripciones Actuales
                        </h3>
                        
                        <?php if (empty($matriculas)): ?>
                            <div class="empty-state" style="border: 1px dashed var(--border-color); padding: 3rem; text-align: center; border-radius: 8px; background: #fafafa; margin-top: 1rem;">
                                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0;">Este alumno no tiene ninguna inscripción registrada todavía.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="margin-top: 1rem;">
                                <table class="table-custom" style="width: 100%; border-collapse: collapse; text-align: left;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--border-color); background: #f8fafc;">
                                            <th style="padding: 10px; font-weight: 600; font-size: 0.8rem; color: var(--text-muted);">Cod. Expediente</th>
                                            <th style="padding: 10px; font-weight: 600; font-size: 0.8rem; color: var(--text-muted);">Convocatoria / Curso</th>
                                            <th style="padding: 10px; font-weight: 600; font-size: 0.8rem; color: var(--text-muted);">Estado</th>
                                            <th style="padding: 10px; font-weight: 600; font-size: 0.8rem; color: var(--text-muted);">F. Matrícula</th>
                                            <th style="padding: 10px; font-weight: 600; font-size: 0.8rem; color: var(--text-muted); text-align: center;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($matriculas as $mat): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                                                <td style="padding: 10px; font-weight: 600; font-size: 0.85rem; color: var(--text-color);"><?= htmlspecialchars($mat['codigo_expediente'] ?? 'N/A') ?></td>
                                                <td style="padding: 10px; font-size: 0.85rem; color: var(--text-color); font-weight: 500;"><?= htmlspecialchars($mat['convocatoria_nombre'] ?? 'Curso Desconocido') ?></td>
                                                <td style="padding: 10px; font-size: 0.8rem;">
                                                    <?php
                                                    $statusColors = [
                                                        'Inscrito' => ['bg' => '#eff6ff', 'text' => '#1e40af'],
                                                        'Activo' => ['bg' => '#d1fae5', 'text' => '#065f46'],
                                                        'Finalizada' => ['bg' => '#ecfdf5', 'text' => '#047857'],
                                                        'Finalizado' => ['bg' => '#ecfdf5', 'text' => '#047857'],
                                                        'Baja' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
                                                        'Cancelada' => ['bg' => '#f3f4f6', 'text' => '#374151']
                                                    ];
                                                    $color = $statusColors[$mat['estado']] ?? ['bg' => '#f3f4f6', 'text' => '#374151'];
                                                    ?>
                                                    <span style="background-color: <?= $color['bg'] ?>; color: <?= $color['text'] ?>; padding: 2px 8px; border-radius: 9999px; font-weight: 600; font-size: 0.75rem;">
                                                        <?= htmlspecialchars($mat['estado']) ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 10px; font-size: 0.85rem; color: var(--text-muted);"><?= $mat['fecha_matricula'] ? date('d/m/Y', strtotime($mat['fecha_matricula'])) : 'N/A' ?></td>
                                                <td style="padding: 10px; text-align: center;">
                                                    <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta inscripción?');">
                                                        <input type="hidden" name="action" value="delete_inscripcion">
                                                        <input type="hidden" name="matricula_id" value="<?= $mat['id'] ?>">
                                                        <button type="submit" style="background: none; border: none; cursor: pointer; color: #dc2626; padding: 4px; display: inline-flex; align-items: center; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.7" onmouseout="this.style.opacity=1" title="Eliminar inscripción">
                                                            <svg style="width: 18px; height: 18px; fill: currentColor;" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Columna Derecha: Formulario para Añadir Nueva Inscripción -->
                    <div>
                        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; color: var(--text-color); margin-bottom: 1.2rem;">
                                <svg style="width: 20px; height: 20px; fill: var(--primary-color);" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                Añadir Inscripción
                            </h3>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="add_inscripcion">
                                
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 0.4rem;">Convocatoria / Curso *</label>
                                    <select name="convocatoria_id" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background-color: white;">
                                        <option value="">-- Seleccionar Convocatoria --</option>
                                        <?php foreach ($convocatorias as $c): ?>
                                            <option value="<?= $c['id'] ?>">
                                                <?= htmlspecialchars(($c['codigo_expediente'] ? '['.$c['codigo_expediente'].'] ' : '') . $c['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 0.4rem;">Estado *</label>
                                    <select name="estado" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background-color: white;">
                                        <option value="Inscrito" selected>Inscrito</option>
                                        <option value="Activo">Activo</option>
                                        <option value="Finalizada">Finalizada</option>
                                        <option value="Baja">Baja</option>
                                        <option value="Cancelada">Cancelada</option>
                                    </select>
                                </div>
                                
                                <div style="margin-bottom: 1.5rem;">
                                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 0.4rem;">Fecha de Matrícula</label>
                                    <input type="date" name="fecha_matricula" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; background-color: white;">
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem;">
                                    <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                    Registrar Inscripción
                                </button>
                            </form>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- TAB: Documentación -->
            <div id="tab-documentacion" style="<?= $active_tab == 'documentacion' ? '' : 'display:none;' ?>">
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                    
                    <!-- Columna Izquierda: Documentos Categorizados -->
                    <div>
                        <!-- 1. Documentación Común / General -->
                        <div style="background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; color: #1e3a8a; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem;">
                                📁 Documentación Común / General
                            </h3>
                            
                            <?php 
                            $docsGenerales = array_filter($documentos, function($d) { return empty($d['accion_id']); });
                            ?>
                            
                            <?php if (empty($docsGenerales)): ?>
                                <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 2rem 0; margin: 0;">No hay documentos comunes subidos.</p>
                            <?php else: ?>
                                <table class="table-custom" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--border-color); background: #f8fafc;">
                                            <th style="padding: 10px; font-weight: 600;">Nombre del Archivo</th>
                                            <th style="padding: 10px; font-weight: 600; width: 140px;">Fecha Subida</th>
                                            <th style="padding: 10px; font-weight: 600; width: 120px;">Subido Por</th>
                                            <th style="padding: 10px; font-weight: 600; text-align: center; width: 100px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($docsGenerales as $doc): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <td style="padding: 10px; font-weight: 500; color: var(--text-color);"><?= htmlspecialchars($doc['nombre_archivo']) ?></td>
                                                <td style="padding: 10px; color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></td>
                                                <td style="padding: 10px; color: var(--text-muted);"><?= htmlspecialchars($doc['username']) ?></td>
                                                <td style="padding: 10px; text-align: center;">
                                                    <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn" style="padding: 4px 10px; font-size: 0.75rem; background: #eff6ff; color: #1e40af; text-decoration: none; border-radius: 4px; font-weight: 600;">
                                                        Descargar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 2. Documentación Propia de cada Acción Formativa -->
                        <h3 style="margin-top: 2rem; margin-bottom: 1rem; font-size: 1.1rem; color: var(--text-color);">
                            🎓 Documentación por Acción Formativa
                        </h3>
                        
                        <?php if (empty($acciones_inscrito)): ?>
                            <div style="border: 1px dashed var(--border-color); padding: 2rem; text-align: center; border-radius: 12px; background: #fafafa;">
                                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">El alumno no está inscrito en ninguna acción formativa para clasificar documentos específicos.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($acciones_inscrito as $acc): ?>
                                <div style="background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                                    <h4 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; color: #8e1d52; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                                        <span style="background: #fdf2f8; color: #9d174d; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;"><?= htmlspecialchars($acc['curso_codigo']) ?></span>
                                        <?= htmlspecialchars($acc['curso_titulo']) ?>
                                    </h4>
                                    
                                    <?php 
                                    $docsAccion = array_filter($documentos, function($d) use ($acc) { 
                                        return $d['accion_id'] == $acc['accion_id']; 
                                    });
                                    ?>
                                    
                                    <?php if (empty($docsAccion)): ?>
                                        <p style="color: var(--text-muted); font-size: 0.8rem; text-align: center; padding: 1rem 0; margin: 0; font-style: italic;">No hay documentos específicos para esta acción formativa.</p>
                                    <?php else: ?>
                                        <table class="table-custom" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                                            <thead>
                                                <tr style="border-bottom: 2px solid var(--border-color); background: #f8fafc;">
                                                    <th style="padding: 10px; font-weight: 600;">Nombre del Archivo</th>
                                                    <th style="padding: 10px; font-weight: 600; width: 140px;">Fecha Subida</th>
                                                    <th style="padding: 10px; font-weight: 600; width: 120px;">Subido Por</th>
                                                    <th style="padding: 10px; font-weight: 600; text-align: center; width: 100px;">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($docsAccion as $doc): ?>
                                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                                        <td style="padding: 10px; font-weight: 500; color: var(--text-color);"><?= htmlspecialchars($doc['nombre_archivo']) ?></td>
                                                        <td style="padding: 10px; color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></td>
                                                        <td style="padding: 10px; color: var(--text-muted);"><?= htmlspecialchars($doc['username']) ?></td>
                                                        <td style="padding: 10px; text-align: center;">
                                                            <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn" style="padding: 4px 10px; font-size: 0.75rem; background: #eff6ff; color: #1e40af; text-decoration: none; border-radius: 4px; font-weight: 600;">
                                                                Descargar
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- 3. Documentación de Historial (Acciones no actuales si existen) -->
                        <?php 
                        $enrolledActionIds = array_column($acciones_inscrito, 'accion_id');
                        $docsOtros = array_filter($documentos, function($d) use ($enrolledActionIds) {
                            return !empty($d['accion_id']) && !in_array($d['accion_id'], $enrolledActionIds);
                        });
                        ?>
                        
                        <?php if (!empty($docsOtros)): ?>
                            <div style="background: #fafafa; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-top: 2rem;">
                                <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1rem; color: var(--text-muted); border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                                    📂 Historial / Otros Cursos
                                </h3>
                                <table class="table-custom" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid var(--border-color); background: #f8fafc;">
                                            <th style="padding: 10px; font-weight: 600;">Nombre del Archivo</th>
                                            <th style="padding: 10px; font-weight: 600;">Curso / Acción Relacionada</th>
                                            <th style="padding: 10px; font-weight: 600; width: 120px;">Subido Por</th>
                                            <th style="padding: 10px; font-weight: 600; text-align: center; width: 100px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($docsOtros as $doc): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <td style="padding: 10px; font-weight: 500; color: var(--text-color);"><?= htmlspecialchars($doc['nombre_archivo']) ?></td>
                                                <td style="padding: 10px; color: #8e1d52; font-weight: 500;"><?= htmlspecialchars($doc['accion_titulo'] ?? 'Acción Formativa desvinculada') ?></td>
                                                <td style="padding: 10px; color: var(--text-muted);"><?= htmlspecialchars($doc['username']) ?></td>
                                                <td style="padding: 10px; text-align: center;">
                                                    <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn" style="padding: 4px 10px; font-size: 0.75rem; background: #eff6ff; color: #1e40af; text-decoration: none; border-radius: 4px; font-weight: 600;">
                                                        Descargar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Columna Derecha: Formulario de Subida -->
                    <div>
                        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); position: sticky; top: 20px;">
                            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; color: var(--text-color); margin-bottom: 1.2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                                📤 Subir Documentación
                            </h3>
                            
                            <form action="subir_documento.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="alumno_id" value="<?= $id ?>">
                                
                                <div style="margin-bottom: 1.2rem;">
                                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 0.4rem;">Seleccionar Archivo *</label>
                                    <input type="file" name="archivo" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                                </div>
                                
                                <div style="margin-bottom: 1.2rem;">
                                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 0.4rem;">Clasificación / Destino *</label>
                                    <select name="accion_id" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background-color: white;">
                                        <option value="0">📁 Documentación Común / General</option>
                                        <?php if (!empty($acciones_inscrito)): ?>
                                            <optgroup label="Cursos / Acciones Formativas">
                                                <?php foreach ($acciones_inscrito as $acc): ?>
                                                    <option value="<?= $acc['accion_id'] ?>">
                                                        🎓 [<?= htmlspecialchars($acc['curso_codigo']) ?>] <?= htmlspecialchars($acc['curso_titulo']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <p style="color: var(--text-muted); font-size: 0.75rem; margin: 0.4rem 0 0 0; line-height: 1.3;">
                                        Elige si el documento es genérico del alumno o si pertenece de forma exclusiva a una acción formativa.
                                    </p>
                                </div>
                                
                                <div style="margin-bottom: 1.5rem;">
                                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-color); margin-bottom: 0.4rem;">Tipo de Documento</label>
                                    <select name="tipo_documento" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; background-color: white;">
                                        <option value="General" selected>General / Otro</option>
                                        <option value="DNI">DNI / NIE</option>
                                        <option value="Contrato">Contrato de Trabajo</option>
                                        <option value="Cabecera_Nomina">Cabecera de Nómina</option>
                                        <option value="Recibo_Autonomo">Recibo de Autónomo</option>
                                        <option value="Vida_Laboral">Vida Laboral</option>
                                        <option value="Diploma">Diploma / Certificado</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem; gap: 0.5rem; font-weight: 700;">
                                    <svg style="width: 16px; height: 16px; fill: currentColor;" viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                                    Subir y Clasificar
                                </button>
                            </form>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
