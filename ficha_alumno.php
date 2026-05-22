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
        
        // Obtener datos antes de borrar para el log
        $stmtGetMat = $pdo->prepare("SELECT * FROM matriculas WHERE id = ? AND alumno_id = ?");
        $stmtGetMat->execute([$matricula_id, $id]);
        $oldMat = $stmtGetMat->fetch();
        
        if ($oldMat) {
            $stmtDel = $pdo->prepare("DELETE FROM matriculas WHERE id = ?");
            $stmtDel->execute([$matricula_id]);
            
            audit_log($pdo, 'MATRICULA_ELIMINADA', 'matriculas', $matricula_id, $oldMat, null);
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
