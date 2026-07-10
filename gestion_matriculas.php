<?php
// gestion_matriculas.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COORD])) {
    header("Location: home.php");
    exit();
}

$af_id = (int)($_GET['af_id'] ?? 0);
if (!$af_id) die("ID de Acción Formativa no proporcionado.");

// Obtener datos de la Acción Formativa
$af = $pdo->prepare("SELECT af.*, c.nombre_largo as titulo FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE af.id = ?");
$af->execute([$af_id]);
$accion = $af->fetch();

if (!$accion) die("Acción Formativa no encontrada.");

// Buscar el grupo asociado (o crear uno por defecto si no existe)
$stmt = $pdo->prepare("SELECT * FROM grupos WHERE accion_id = ? LIMIT 1");
$stmt->execute([$af_id]);
$grupo_full = $stmt->fetch();

if (!$grupo_full) {
    // Crear grupo automático para esta acción
    $stmt = $pdo->prepare("INSERT INTO grupos (accion_id, numero_grupo, modalidad, horas) VALUES (?, ?, ?, ?)");
    $stmt->execute([$af_id, 'G1', $accion['modalidad'], $accion['duracion']]);
    $grupo_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
    $stmt->execute([$grupo_id]);
    $grupo_full = $stmt->fetch();
} else {
    $grupo_id = $grupo_full['id'];
}

// Obtener listado de tutores activos
$stmtTutores = $pdo->query("SELECT u.id, CONCAT(u.nombre, ' ', u.apellidos) as nombre, u.email, u.username
                            FROM usuarios u 
                            JOIN roles r ON u.rol_id = r.id 
                            WHERE (r.nombre LIKE '%Tutor%' OR r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Docente%') 
                            AND u.activo = 1
                            ORDER BY u.nombre ASC");
$tutores = $stmtTutores ? $stmtTutores->fetchAll(PDO::FETCH_ASSOC) : [];

// Procesar Guardado y Matriculación de Personal (Tutores e Inspector)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_save_personal'])) {
    $tutor_id = $_POST['tutor_id'] !== '' ? (int)$_POST['tutor_id'] : null;
    $tutor_id_2 = $_POST['tutor_id_2'] !== '' ? (int)$_POST['tutor_id_2'] : null;
    $tutor_reserva_id = $_POST['tutor_reserva_id'] !== '' ? (int)$_POST['tutor_reserva_id'] : null;
    $usuario_gestor = trim($_POST['usuario_gestor'] ?? '');
    $contrasena_gestor = trim($_POST['contrasena_gestor'] ?? '');

    // Asegurar que la contraseña cumpla con la política de Moodle (al menos un carácter especial)
    if ($contrasena_gestor !== '' && !preg_match('/[^a-zA-Z0-9]/', $contrasena_gestor)) {
        $contrasena_gestor .= '-*';
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Guardar en la base de datos de la Intranet
        $stmtUpdate = $pdo->prepare("UPDATE grupos 
                                     SET tutor_id = ?, tutor_id_2 = ?, tutor_reserva_id = ?, usuario_gestor = ?, contrasena_gestor = ?
                                     WHERE id = ?");
        $stmtUpdate->execute([$tutor_id, $tutor_id_2, $tutor_reserva_id, $usuario_gestor, $contrasena_gestor, $grupo_id]);
        
        $pdo->commit();
        
        // 2. Intentar matricular y sincronizar en Moodle si está configurado
        $sync_msg = '';
        require_once 'includes/moodle_api.php';
        $moodle = new MoodleAPI($pdo);
        
        if ($moodle->isConfigured()) {
            // El ID del curso Moodle está en el campo de la acción formativa:
            $courseMoodleId = $accion['id_plataforma'] ?: null;
            
            if ($courseMoodleId) {
                // Sincronizar tutores seleccionados
                $tutors_synced = [];
                $tutor_ids_to_sync = array_filter([$tutor_id, $tutor_id_2, $tutor_reserva_id]);
                
                foreach ($tutor_ids_to_sync as $tid) {
                    $stmtT = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $stmtT->execute([$tid]);
                    $tutor_user = $stmtT->fetch();
                    
                    if ($tutor_user && !empty($tutor_user['email'])) {
                        // Limpiar username
                        $t_username = $tutor_user['username'] ?: strtolower(explode('@', $tutor_user['email'])[0]);
                        $t_username = preg_replace('/[^a-z0-9_.-]/', '', strtolower($t_username));
                        
                        $existingT = $moodle->getUsersByField('email', [$tutor_user['email']]);
                        $moodleTutorId = null;
                        if (!empty($existingT) && isset($existingT['users'][0])) {
                            $moodleTutorId = $existingT['users'][0]['id'];
                        } else {
                            $newT = $moodle->createUser(
                                $t_username,
                                'EditeTutor-2026*',
                                $tutor_user['nombre'],
                                $tutor_user['apellidos'] ?: 'Tutor',
                                $tutor_user['email']
                            );
                            if (isset($newT[0]['id'])) {
                                $moodleTutorId = $newT[0]['id'];
                            }
                        }
                        
                        if ($moodleTutorId) {
                            // Enrol tutor as editing teacher (role ID 3)
                            $moodle->enrolUser($moodleTutorId, $courseMoodleId, 3);
                            $tutors_synced[] = $tutor_user['nombre'];
                        }
                    }
                }
                
                if (!empty($tutors_synced)) {
                    $sync_msg .= "Tutores matriculados en Moodle: " . implode(', ', $tutors_synced) . ".";
                }
                
                // Sincronizar Inspector del SEPE (Usuario Gestor)
                if (!empty($usuario_gestor)) {
                    $gestor_username = preg_replace('/[^a-z0-9_.-]/', '', strtolower(str_replace(' ', '_', $usuario_gestor)));
                    $gestor_email = $gestor_username . '@avefp.es';
                    $gestor_pass = !empty($contrasena_gestor) ? $contrasena_gestor : 'InspectorSepe-2026*';
                    if (!preg_match('/[^a-zA-Z0-9]/', $gestor_pass)) {
                        $gestor_pass .= '-*';
                    }
                    
                    $existingG = $moodle->getUsersByField('username', [$gestor_username]);
                    $moodleGestorId = null;
                    if (!empty($existingG) && isset($existingG['users'][0])) {
                        $moodleGestorId = $existingG['users'][0]['id'];
                    } else {
                        $newG = $moodle->createUser(
                            $gestor_username,
                            $gestor_pass,
                            'Inspector',
                            'SEPE',
                            $gestor_email
                        );
                        if (isset($newG[0]['id'])) {
                            $moodleGestorId = $newG[0]['id'];
                        }
                    }
                    
                    if ($moodleGestorId) {
                        // Enrol inspector as non-editing teacher (role ID 4)
                        $moodle->enrolUser($moodleGestorId, $courseMoodleId, 4);
                        $sync_msg .= ($sync_msg ? ' ' : '') . "Inspector SEPE ('$gestor_username') matriculado en Moodle.";
                    }
                }
            } else {
                $sync_msg .= " (Nota: El curso de Moodle no está creado o vinculado todavía. Los cambios se guardaron en la Intranet local).";
            }
        } else {
            $sync_msg .= " (Nota: Moodle no está configurado. Los cambios se guardaron en la Intranet local).";
        }
        
        $msg = "Personal del grupo actualizado con éxito." . ($sync_msg ? " " . $sync_msg : "");
        header("Location: gestion_matriculas.php?af_id=$af_id&success_sync=1&sync_msg=" . urlencode($msg));
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error al guardar el personal: " . $e->getMessage();
    }
}

// Procesar Alta de Alumno
if (isset($_POST['add_alumno_id']) || !empty($_POST['student_search_text'])) {
    $alumno_id = (int)($_POST['add_alumno_id'] ?? 0);
    $search_text = trim($_POST['student_search_text'] ?? '');

    try {
        if (!$alumno_id && !empty($search_text)) {
            // Buscar por DNI exacto o nombre exacto si no hay ID
            $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ? OR CONCAT(nombre, ' ', primer_apellido) = ? LIMIT 1");
            $stmt->execute([$search_text, $search_text]);
            $found = $stmt->fetch();
            if ($found) {
                $alumno_id = $found['id'];
            } else {
                throw new Exception("No se encontró ningún alumno con ese DNI o nombre exacto.");
            }
        }

        if ($alumno_id) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO matriculas (alumno_id, grupo_id, convocatoria_id, estado, fecha_matricula) 
                                   VALUES (?, ?, ?, 'Inscrito', CURDATE())");
            $stmt->execute([$alumno_id, $grupo_id, $accion['plan_id']]);
            header("Location: gestion_matriculas.php?af_id=$af_id&success=1");
            exit();
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Procesar Baja de Alumno
if (isset($_GET['remove_id'])) {
    $matricula_id = (int)$_GET['remove_id'];
    $moodle_error = null;
    $moodle_status = 'skipped';
    
    // Obtener los IDs de Moodle y datos del alumno/curso antes de borrar la matrícula
    try {
        $stmtMat = $pdo->prepare("SELECT m.alumno_id, a.moodle_user_id, a.email, a.nombre, a.primer_apellido, a.segundo_apellido, c.nombre_largo as curso_titulo, c.moodle_id as curso_moodle_id
                                  FROM matriculas m 
                                  JOIN alumnos a ON m.alumno_id = a.id
                                  JOIN grupos g ON m.grupo_id = g.id
                                  JOIN acciones_formativas af ON g.accion_id = af.id
                                  JOIN cursos c ON af.curso_id = c.id
                                  WHERE m.id = ? AND m.grupo_id = ?");
        $stmtMat->execute([$matricula_id, $grupo_id]);
        $mat_info = $stmtMat->fetch(PDO::FETCH_ASSOC);

        if ($mat_info) {
            $moodleUserId = $mat_info['moodle_user_id'];
            $courseMoodleId = $mat_info['curso_moodle_id'];
            $email = $mat_info['email'];

            require_once 'includes/moodle_api.php';
            $moodle = new MoodleAPI($pdo);
            if ($moodle->isConfigured()) {
                // Fallback: si no tenemos el moodle_user_id guardado localmente, lo buscamos en Moodle por email
                if (empty($moodleUserId) && !empty($email)) {
                    try {
                        $existingUsers = $moodle->getUsersByField('email', [$email]);
                        if (!empty($existingUsers) && isset($existingUsers['users'][0])) {
                            $moodleUserId = $existingUsers['users'][0]['id'];
                            // Actualizar localmente para no repetir la búsqueda
                            $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$moodleUserId, $mat_info['alumno_id']]);
                        }
                    } catch (Exception $lookupEx) {
                        // Silencioso
                    }
                }

                if (!empty($moodleUserId) && !empty($courseMoodleId)) {
                    try {
                        $moodle->unenrolUser($moodleUserId, $courseMoodleId);
                        $moodle_status = 'success';
                    } catch (Exception $moodleEx) {
                        $moodle_error = $moodleEx->getMessage();
                        $moodle_status = 'error';
                    }
                } else {
                    $moodle_status = 'missing_ids';
                }
            }
        }
    } catch (Exception $e) {
        $moodle_error = $e->getMessage();
        $moodle_status = 'error';
    }

    // Iniciar transacción de BD para archivar en Papelera y eliminar localmente
    try {
        // Asegurar que la tabla Papelera existe ANTES de iniciar la transacción.
        // Esto evita que el DDL implícito (CREATE TABLE) de Papelera rompa la transacción PDO activa.
        require_once 'includes/Papelera.php';
        Papelera::checkTable($pdo);

        $pdo->beginTransaction();

        // Obtener el registro limpio de la matrícula para archivar en Papelera
        $stmtMatClean = $pdo->prepare("SELECT * FROM matriculas WHERE id = ?");
        $stmtMatClean->execute([$matricula_id]);
        $matricula_clean = $stmtMatClean->fetch(PDO::FETCH_ASSOC);

        if ($matricula_clean && $mat_info) {
            require_once 'includes/Papelera.php';
            $alumno_nombre = trim($mat_info['nombre'] . ' ' . ($mat_info['primer_apellido'] ?? '') . ' ' . ($mat_info['segundo_apellido'] ?? ''));
            $titulo_papelera = $alumno_nombre . " - " . $mat_info['curso_titulo'];
            
            // Archivar en papelera
            Papelera::archivar($pdo, 'matriculas', $matricula_id, $titulo_papelera, ['matriculas' => $matricula_clean]);
        }

        $pdo->prepare("DELETE FROM matriculas WHERE id = ? AND grupo_id = ?")->execute([$matricula_id, $grupo_id]);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $moodle_error = ($moodle_error ? $moodle_error . " | " : "") . "Error al eliminar matrícula local: " . $e->getMessage();
        $moodle_status = 'error';
    }
    
    $redirectUrl = "gestion_matriculas.php?af_id=$af_id&removed=1&moodle_status=$moodle_status";
    if ($moodle_error) {
        $redirectUrl .= "&error=" . urlencode("La matrícula se eliminó de la Intranet, pero ocurrió un problema: " . $moodle_error);
    }
    header("Location: $redirectUrl");
    exit();
}

// Obtener alumnos matriculados
$matriculados = $pdo->prepare("SELECT m.id as matricula_id, a.* FROM matriculas m 
                               JOIN alumnos a ON m.alumno_id = a.id 
                               WHERE m.grupo_id = ? ORDER BY a.nombre ASC");
$matriculados->execute([$grupo_id]);
$alumnos = $matriculados->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Alumnos - <?= htmlspecialchars($accion['titulo']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .gestion-container { display: grid; grid-template-columns: 1fr 350px; gap: 30px; }
        .student-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .student-card:hover { border-color: #3b82f6; transform: translateX(5px); }
        .search-box { position: relative; margin-bottom: 25px; }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 100;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .search-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }
        .search-item:hover { background: #eff6ff; }
        .badge-count {
            background: #1e3a8a;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header" style="margin-bottom: 30px;">
            <div class="page-title">
                <span style="color: #64748b; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;">Gestión de Matrículas</span>
                <h1 style="margin: 5px 0;"><?= htmlspecialchars($accion['titulo']) ?></h1>
                <p>Grupo ID: <strong><?= $grupo_id ?></strong> | Modalidad: <strong><?= $accion['modalidad'] ?></strong></p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php if (has_permission([ROLE_ADMIN])): ?>
                    <a href="papelera.php" class="btn" style="background: #ef4444; color: white; text-decoration:none; padding: 10px 15px; border-radius:8px; font-weight:700; display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Papelera
                    </a>
                <?php endif; ?>
                <button id="btnSyncMoodle" onclick="syncMoodle(<?= $af_id ?>)" class="btn btn-primary" style="background: #ea580c; border: none; font-size: 0.85rem; padding: 10px 15px; border-radius: 8px;">
                    🚀 Volcar al Aula Virtual
                </button>
                <?php if (!empty($alumnos)): ?>
                    <button type="button" onclick="openMassKeysModal()" class="btn" style="background: #0284c7; color: white; border: none; font-weight: 700; padding: 10px 15px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem;">
                        📨 Envío Masivo Claves
                    </button>
                <?php endif; ?>
                <a href="acciones_formativas.php?plan_id=<?= $accion['plan_id'] ?>" class="btn" style="background: #f1f5f9; color: #1e3a8a; text-decoration:none; padding: 10px 15px; border-radius:8px; font-weight:700; font-size: 0.85rem;">Volver</a>
            </div>
        </header>

        <?php if (!empty($error) || !empty($_GET['error'])): ?>
            <div class="alert alert-danger" style="background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; border: 1px solid #fca5a5; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= htmlspecialchars($error ?: $_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; border: 1px solid #a7f3d0; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span>Alumno matriculado correctamente.</span>
            </div>
        <?php elseif (isset($_GET['success_sync'])): ?>
            <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; border: 1px solid #a7f3d0; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?= htmlspecialchars($_GET['sync_msg'] ?? 'Sincronización con Moodle realizada correctamente.') ?></span>
            </div>
        <?php elseif (isset($_GET['removed']) && !isset($_GET['error'])): ?>
            <?php
            $moodle_status = $_GET['moodle_status'] ?? '';
            $removed_msg = 'Matrícula eliminada correctamente de la Intranet.';
            if ($moodle_status === 'success') {
                $removed_msg .= ' También se desmatriculó al alumno de Moodle con éxito.';
            } elseif ($moodle_status === 'missing_ids') {
                $removed_msg .= ' ⚠️ Nota: No se pudo desmatricular del aula virtual porque el alumno no tenía una cuenta de Moodle vinculada.';
            }
            ?>
            <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; border: 1px solid #a7f3d0; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?= htmlspecialchars($removed_msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="gestion-container">
            <div class="enrolled-section">
                <h2 style="font-size: 1.1rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    Alumnos Matriculados <span class="badge-count"><?= count($alumnos) ?></span>
                </h2>
                
                <?php if (empty($alumnos)): ?>
                    <div style="padding: 40px; text-align: center; background: white; border-radius: 16px; border: 2px dashed #e2e8f0; color: #94a3b8;">
                        No hay alumnos matriculados en este curso todavía.
                    </div>
                <?php else: ?>
                    <?php foreach($alumnos as $a): ?>
                        <div class="student-card">
                            <div>
                                <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($a['nombre'] . ' ' . ($a['primer_apellido'] ?? '') . ' ' . ($a['segundo_apellido'] ?? '')) ?></div>
                                <small style="color: #64748b; font-weight: 600;"><?= $a['dni'] ?> | <?= $a['email'] ?></small>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <!-- Enviar Claves Individual -->
                                <button type="button" onclick="openSingleKeysModal(<?= $a['matricula_id'] ?>, <?= htmlspecialchars(json_encode($a['nombre'] . ' ' . ($a['primer_apellido'] ?? '') . ' ' . ($a['segundo_apellido'] ?? ''))) ?>, <?= htmlspecialchars(json_encode($a['email'])) ?>, <?= htmlspecialchars(json_encode($a['plat_usuario'] ?? '')) ?>, <?= htmlspecialchars(json_encode($a['plat_clave'] ?? '')) ?>)" style="background: none; border: none; cursor: pointer; color: #0284c7; padding: 6px; display: inline-flex; align-items: center; justify-content: center; hover:color: #0369a1;" title="Enviar Claves de Acceso">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                </button>
                                
                                <a href="?af_id=<?= $af_id ?>&remove_id=<?= $a['matricula_id'] ?>" 
                                   onclick="return confirm('¿Dar de baja a este alumno?')"
                                   style="color: #ef4444; padding: 6px; border-radius: 8px; transition: background 0.2s; display: inline-flex; align-items: center; justify-content: center;"
                                   title="Dar de baja">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="add-section">
                    <!-- Formulario de Búsqueda -->
                    <div id="searchFormContainer" style="margin-bottom: 25px;">
                        <div class="form-group" style="margin-bottom: 15px; position: relative;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a;">DNI:</label>
                            <input type="text" id="searchDni" class="form-control" placeholder="Ej: 12345678X" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;" autocomplete="off">
                            <div id="dniAutocomplete" class="search-results"></div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px; position: relative;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a;">Nombre Completo:</label>
                            <input type="text" id="searchNombre" class="form-control" placeholder="Nombre y apellidos..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;" autocomplete="off">
                            <div id="nombreAutocomplete" class="search-results"></div>
                        </div>
                        <button type="button" id="btnBuscar" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; background: #1e3a8a; border: none; font-weight: 700;">
                            🔍 Buscar Alumno
                        </button>
                    </div>

                    <!-- Resultado de Búsqueda -->
                    <div id="searchResultArea" style="display: none; background: white; padding: 20px; border-radius: 12px; border: 2px solid #3b82f6; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(59,130,246,0.1);">
                        <h4 style="margin-top: 0; color: #1e40af; font-size: 0.9rem;">Alumno Encontrado:</h4>
                        <div id="foundStudentInfo" style="margin-bottom: 20px;">
                            <div id="foundName" style="font-weight: 700; color: #1e293b;"></div>
                            <div id="foundDni" style="font-size: 0.85rem; color: #64748b;"></div>
                        </div>
                        
                        <form id="addForm" method="POST">
                            <input type="hidden" name="add_alumno_id" id="selectedAlumnoId">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; background: #059669; border: none; font-weight: 700;">
                                ✅ Matricular Alumno
                            </button>
                        </form>
                        
                        <button type="button" onclick="resetSearch()" style="width: 100%; margin-top: 10px; background: none; border: none; color: #64748b; font-size: 0.8rem; cursor: pointer; text-decoration: underline;">
                            Nueva búsqueda
                        </button>
                    </div>

                    <div id="noResultsMsg" style="display: none; background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 20px; font-size: 0.85rem; text-align: center;">
                        ❌ No se encontró ningún alumno con esos datos.
                    </div>

                    <div style="background: #eff6ff; padding: 15px; border-radius: 12px; border: 1px solid #bfdbfe; font-size: 0.8rem; color: #1e40af; margin-bottom: 25px;">
                        <strong>Instrucciones:</strong> Escribe en cualquiera de los campos anteriores para activar la búsqueda predictiva al instante.
                    </div>

                    <!-- Personal del Grupo (Tutores y SEPE Inspector) -->
                    <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.02);">
                        <h3 style="font-size: 0.95rem; font-weight: 700; color: #1e3a8a; margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">👨‍🏫 Personal del Grupo</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action_save_personal" value="1">
                            
                            <div style="margin-bottom: 12px; text-align: left;">
                                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 6px;">Tutor Principal:</label>
                                <select name="tutor_id" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-family: inherit;">
                                    <option value="">-- Sin asignar --</option>
                                    <?php foreach ($tutores as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= ($grupo_full['tutor_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="margin-bottom: 12px; text-align: left;">
                                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 6px;">Tutor Auxiliar (Tutor 2):</label>
                                <select name="tutor_id_2" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-family: inherit;">
                                    <option value="">-- Sin asignar --</option>
                                    <?php foreach ($tutores as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= ($grupo_full['tutor_id_2'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="margin-bottom: 12px; text-align: left;">
                                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 6px;">Tutor de Reserva:</label>
                                <select name="tutor_reserva_id" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-family: inherit;">
                                    <option value="">-- Sin asignar --</option>
                                    <?php foreach ($tutores as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= ($grupo_full['tutor_reserva_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="border-top: 1px solid #f1f5f9; margin-top: 15px; padding-top: 10px; margin-bottom: 15px; text-align: left;">
                                <label style="font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 5px;">🕵️‍♂️ Inspector SEPE (Usuario Gestor):</label>
                                
                                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                                    <div style="flex: 1;">
                                        <span style="font-size: 0.7rem; font-weight: 600; color: #64748b;">Usuario:</span>
                                        <input type="text" name="usuario_gestor" class="form-control" value="<?= htmlspecialchars($grupo_full['usuario_gestor'] ?? '') ?>" placeholder="Ej: inspector_sepe" style="width: 100%; padding: 6px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.8rem; font-family: inherit;">
                                    </div>
                                    <div style="flex: 1;">
                                        <span style="font-size: 0.7rem; font-weight: 600; color: #64748b;">Contraseña:</span>
                                        <input type="text" name="contrasena_gestor" class="form-control" value="<?= htmlspecialchars($grupo_full['contrasena_gestor'] ?? '') ?>" placeholder="Clave..." style="width: 100%; padding: 6px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.8rem; font-family: inherit;">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn" style="width: 100%; padding: 12px; border-radius: 8px; background: #ea580c; color: white; border: none; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;">
                                💾 Guardar y Matricular Personal
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const btnBuscar = document.getElementById('btnBuscar');
const searchDni = document.getElementById('searchDni');
const searchNombre = document.getElementById('searchNombre');
const resultArea = document.getElementById('searchResultArea');
const noResultsMsg = document.getElementById('noResultsMsg');
const foundName = document.getElementById('foundName');
const foundDni = document.getElementById('foundDni');
const selectedIdInput = document.getElementById('selectedAlumnoId');

const dniAutocomplete = document.getElementById('dniAutocomplete');
const nombreAutocomplete = document.getElementById('nombreAutocomplete');

function setupAutocomplete(inputEl, resultsEl, type) {
    let debounceTimer;
    
    inputEl.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const val = this.value.trim();
        
        if (val.length === 0) {
            resultsEl.innerHTML = '';
            resultsEl.style.display = 'none';
            return;
        }
        
        // Si es búsqueda por DNI y no contiene ningún número, no mostramos resultados predictivos
        if (type === 'dni' && !/\d/.test(val)) {
            resultsEl.innerHTML = '';
            resultsEl.style.display = 'none';
            return;
        }
        
        debounceTimer = setTimeout(() => {
            fetch(`api_buscar_alumnos.php?q=${encodeURIComponent(val)}&type=${type}`)
                .then(r => r.json())
                .then(data => {
                    resultsEl.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(a => {
                            const item = document.createElement('div');
                            item.className = 'search-item';
                            
                            let fullName = `${a.nombre} ${a.primer_apellido || ''}`.trim();
                            item.textContent = `${fullName} (${a.dni})`;
                            
                            item.addEventListener('click', function() {
                                selectStudent(a);
                                resultsEl.innerHTML = '';
                                resultsEl.style.display = 'none';
                            });
                            
                            resultsEl.appendChild(item);
                        });
                        resultsEl.style.display = 'block';
                    } else {
                        resultsEl.innerHTML = '<div class="search-item" style="color: #ef4444; cursor: default; padding: 12px 15px;">No hay coincidencias</div>';
                        resultsEl.style.display = 'block';
                    }
                })
                .catch(err => console.error("Error autocomplete:", err));
        }, 150); // Fast, responsive debounce
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== inputEl && e.target !== resultsEl && !resultsEl.contains(e.target)) {
            resultsEl.style.display = 'none';
        }
    });
}

function selectStudent(a) {
    foundName.innerText = `${a.nombre} ${a.primer_apellido || ''}`;
    foundDni.innerText = `DNI: ${a.dni}`;
    selectedIdInput.value = a.id;
    
    resultArea.style.display = 'block';
    document.getElementById('searchFormContainer').style.display = 'none';
    noResultsMsg.style.display = 'none';
}

setupAutocomplete(searchDni, dniAutocomplete, 'dni');
setupAutocomplete(searchNombre, nombreAutocomplete, 'nombre');

btnBuscar.addEventListener('click', function() {
    const dni = searchDni.value.trim();
    const nombre = searchNombre.value.trim();
    
    const q = dni || nombre;
    
    if (q.length === 0) {
        alert("Por favor, introduce el DNI o el nombre para buscar.");
        return;
    }

    this.disabled = true;
    this.innerHTML = "⌛ Buscando...";

    fetch(`api_buscar_alumnos.php?q=${encodeURIComponent(q)}&exact=1`)
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            this.innerHTML = "🔍 Buscar Alumno";
            
            resultArea.style.display = 'none';
            noResultsMsg.style.display = 'none';

            if (data && data.length > 0) {
                selectStudent(data[0]);
            } else {
                noResultsMsg.style.display = 'block';
            }
        })
        .catch(err => {
            this.disabled = false;
            this.innerHTML = "🔍 Buscar Alumno";
            alert("Error al realizar la búsqueda. Por favor, inténtalo de nuevo.");
            console.error(err);
        });
});

function resetSearch() {
    document.getElementById('searchFormContainer').style.display = 'block';
    resultArea.style.display = 'none';
    noResultsMsg.style.display = 'none';
    searchDni.value = '';
    searchNombre.value = '';
    dniAutocomplete.innerHTML = '';
    nombreAutocomplete.innerHTML = '';
}

function syncMoodle(afId) {
    const btn = document.getElementById('btnSyncMoodle');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '⌛ Sincronizando con Moodle...';
    btn.style.opacity = '0.7';
    
    fetch(`api_sync_moodle.php?id=${afId}`)
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            btn.style.opacity = '1';
            
            if (data.success) {
                alert('✅ Sincronización exitosa:\n\n' + data.message);
                window.location.href = `gestion_matriculas.php?af_id=${afId}&success_sync=1&sync_msg=${encodeURIComponent(data.message)}`;
            } else {
                alert('❌ Error al sincronizar con Moodle:\n\n' + data.error);
                window.location.href = `gestion_matriculas.php?af_id=${afId}&error=${encodeURIComponent(data.error)}`;
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            btn.style.opacity = '1';
            alert('❌ Error de red al comunicarse con la intranet para sincronizar.');
            console.error('Sync error:', err);
        });
}
</script>

<!-- MODAL: Enviar Claves Individual -->
<div id="modal-envio-claves-single" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box;">
    <div style="background: white; width: 100%; max-width: 600px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow: hidden; display: flex; flex-direction: column; animation: modalFadeIn 0.3s ease;">
        <div style="background: #1e3a8a; color: white; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: white;">📨 Enviar Claves de Acceso a Moodle</h3>
            <button type="button" onclick="closeSingleKeysModal()" style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <form id="singleKeysForm" onsubmit="submitSingleKeys(event);" style="margin: 0;">
            <input type="hidden" name="matricula_id" id="single-matricula-id">
            
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; box-sizing: border-box; text-align: left;">
                <div id="singleKeysError" style="display: none; background: #fee2e2; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 6px; border-left: 4px solid #ef4444; font-size: 0.85rem; font-weight: 600;"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; font-size: 0.85rem; box-sizing: border-box;">
                    <div><strong>Alumno:</strong> <span id="s-alumno-nombre">Cargando...</span></div>
                    <div><strong>E-mail:</strong> <span id="s-alumno-email">Cargando...</span></div>
                    <div><strong>Usuario Moodle:</strong> <span id="s-alumno-usuario">Cargando...</span></div>
                    <div><strong>Contraseña:</strong> <span id="s-alumno-clave">Cargando...</span></div>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="font-weight: 600; color: #334155; font-size: 0.85rem;">Asunto del Correo:</label>
                    <input type="text" name="subject" id="s-correo-subject" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-family: inherit; font-size: 0.9rem; box-sizing: border-box;" value="Datos de Acceso al Aula Virtual - {curso}">
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="font-weight: 600; color: #334155; font-size: 0.85rem;">Cuerpo del Mensaje (Placeholders: {nombre}, {curso}, {url}, {usuario}, {contrasena}):</label>
                    <textarea name="body" id="s-correo-body" style="height: 180px; width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; font-family: inherit; font-size: 0.85rem; line-height: 1.4; resize: vertical; box-sizing: border-box;">Estimado/a {nombre},

Nos complace darte la bienvenida al curso "{curso}".

A continuación, te facilitamos tus datos de acceso al Aula Virtual:

Plataforma: {url}
Usuario: {usuario}
Contraseña: {contrasena}

Te recomendamos acceder a la plataforma lo antes posible para comenzar tu formación.

Quedamos a tu entera disposición para cualquier consulta.

Un cordial saludo,
Equipo de Soporte de Formación</textarea>
                </div>
            </div>
            <div style="background: #f1f5f9; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; box-sizing: border-box;">
                <button type="button" onclick="closeSingleKeysModal()" class="btn-modern" style="background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; padding: 0.5rem 1.25rem; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" id="btn-confirm-send-single" class="btn-modern btn-primary-modern" style="background: #2563eb; color: white; border: 1px solid #1d4ed8; font-weight: 600; min-width: 130px; padding: 0.5rem 1.25rem; border-radius: 6px; cursor: pointer;">Enviar Claves</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Envío Masivo de Claves -->
<div id="modal-envio-claves-mass" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box;">
    <div style="background: white; width: 100%; max-width: 650px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow: hidden; display: flex; flex-direction: column; animation: modalFadeIn 0.3s ease;">
        <div style="background: #0284c7; color: white; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: white;">📨 Envío Masivo de Claves (<?= count($alumnos) ?> alumnos)</h3>
            <button type="button" onclick="closeMassKeysModal()" id="btn-close-mass-modal" style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        
        <div id="mass-setup-view" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; box-sizing: border-box; text-align: left;">
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label style="font-weight: 600; color: #334155; font-size: 0.85rem;">Asunto del Correo para todos:</label>
                <input type="text" id="m-mass-subject" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-family: inherit; font-size: 0.9rem; box-sizing: border-box;" value="Datos de Acceso al Aula Virtual - {curso}">
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label style="font-weight: 600; color: #334155; font-size: 0.85rem;">Cuerpo del Mensaje (Placeholders: {nombre}, {curso}, {url}, {usuario}, {contrasena}):</label>
                <textarea id="m-mass-body" style="height: 180px; width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; font-family: inherit; font-size: 0.85rem; line-height: 1.4; resize: vertical; box-sizing: border-box;">Estimado/a {nombre},

Nos complace darte la bienvenida al curso "{curso}".

A continuación, te facilitamos tus datos de acceso al Aula Virtual:

Plataforma: {url}
Usuario: {usuario}
Contraseña: {contrasena}

Te recomendamos acceder a la plataforma lo antes posible para comenzar tu formación.

Quedamos a tu entera disposición para cualquier consulta.

Un cordial saludo,
Equipo de Soporte de Formación</textarea>
            </div>
            
            <div style="background: #f1f5f9; padding: 1rem; border-radius: 8px; font-size: 0.82rem; color: #475569; border-left: 4px solid #0284c7; box-sizing: border-box;">
                ℹ️ Esta acción procesará secuencialmente el envío individual a cada uno de los <strong><?= count($alumnos) ?></strong> alumnos. El proceso es transparente y podrás ver su evolución en tiempo real.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 1rem; box-sizing: border-box;">
                <button type="button" onclick="closeMassKeysModal()" class="btn-modern" style="background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; font-weight: 600; padding: 0.5rem 1.25rem; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="button" onclick="startMassSending()" class="btn-modern" style="background: #0284c7; color: white; border: 1px solid #025a87; font-weight: 600; padding: 0.5rem 1.25rem; border-radius: 6px; cursor: pointer;">Iniciar Envío Masivo</button>
            </div>
        </div>

        <div id="mass-progress-view" style="display: none; padding: 1.5rem; flex-direction: column; gap: 1rem; box-sizing: border-box; text-align: left;">
            <div style="font-weight: 700; color: #1e293b; font-size: 1rem;" id="mass-progress-title">Procesando envíos...</div>
            
            <!-- Progress Bar -->
            <div style="width: 100%; height: 16px; background: #e2e8f0; border-radius: 99px; overflow: hidden; position: relative;">
                <div id="mass-progress-bar" style="width: 0%; height: 100%; background: #0284c7; transition: width 0.3s ease;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b; font-weight: 600;">
                <span id="mass-progress-pct">0% completado</span>
                <span id="mass-progress-counts">0 / 0 enviados</span>
            </div>

            <!-- Logging Area -->
            <div id="mass-sending-log" style="height: 180px; overflow-y: auto; background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 0.78rem; padding: 10px; border-radius: 8px; border: 1px solid #1e293b; box-sizing: border-box; line-height: 1.5;">
            </div>

            <div style="display: flex; justify-content: flex-end; border-top: 1px solid #e2e8f0; padding-top: 1rem; box-sizing: border-box;">
                <button type="button" id="btn-finish-mass" disabled onclick="location.reload();" class="btn-modern" style="background: #e2e8f0; color: #94a3b8; border: 1px solid #cbd5e1; font-weight: 600; padding: 0.5rem 1.5rem; border-radius: 6px; cursor: not-allowed;">Espere a que termine...</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Inyección de alumnos para uso del bucle de JS
    const alumnosParaEnvio = <?= json_encode($alumnos) ?>;
    
    // Modal Single Claves
    function openSingleKeysModal(matriculaId, nombre, email, usuario, clave) {
        document.getElementById('single-matricula-id').value = matriculaId;
        document.getElementById('s-alumno-nombre').textContent = nombre;
        document.getElementById('s-alumno-email').textContent = email;
        document.getElementById('s-alumno-usuario').textContent = usuario || '---';
        document.getElementById('s-alumno-clave').textContent = clave || '---';
        document.getElementById('singleKeysError').style.display = 'none';
        
        document.getElementById('modal-envio-claves-single').style.display = 'flex';
    }

    function closeSingleKeysModal() {
        document.getElementById('modal-envio-claves-single').style.display = 'none';
    }

    function submitSingleKeys(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-confirm-send-single');
        const originalText = btn.innerHTML;
        const errDiv = document.getElementById('singleKeysError');
        
        btn.disabled = true;
        btn.innerHTML = '⌛ Enviando...';
        errDiv.style.display = 'none';
        
        const formData = new FormData(document.getElementById('singleKeysForm'));
        
        fetch('api_send_matricula_keys.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (data.success) {
                alert('¡Claves de acceso enviadas correctamente al alumno!');
                closeSingleKeysModal();
                location.reload();
            } else {
                errDiv.textContent = data.error || 'Ocurrió un error inesperado al enviar.';
                errDiv.style.display = 'block';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            errDiv.textContent = 'Error de comunicación con el servidor.';
            errDiv.style.display = 'block';
        });
    }

    // Modal Mass Claves
    function openMassKeysModal() {
        if (!alumnosParaEnvio || alumnosParaEnvio.length === 0) {
            alert('No hay alumnos matriculados en este curso para realizar envíos.');
            return;
        }
        document.getElementById('mass-setup-view').style.display = 'flex';
        document.getElementById('mass-progress-view').style.display = 'none';
        document.getElementById('modal-envio-claves-mass').style.display = 'flex';
    }

    function closeMassKeysModal() {
        document.getElementById('modal-envio-claves-mass').style.display = 'none';
    }

    async function startMassSending() {
        // Ocultar modal close button temporalmente para evitar interrupciones
        document.getElementById('btn-close-mass-modal').style.display = 'none';
        document.getElementById('mass-setup-view').style.display = 'none';
        document.getElementById('mass-progress-view').style.display = 'flex';
        
        const subject = document.getElementById('m-mass-subject').value;
        const body = document.getElementById('m-mass-body').value;
        const logArea = document.getElementById('mass-sending-log');
        const progressBar = document.getElementById('mass-progress-bar');
        const progressPct = document.getElementById('mass-progress-pct');
        const progressCounts = document.getElementById('mass-progress-counts');
        const finishBtn = document.getElementById('btn-finish-mass');
        
        logArea.innerHTML = '';
        logArea.innerHTML += `> Iniciando envío masivo para ${alumnosParaEnvio.length} alumnos...\n`;
        
        let successCount = 0;
        let errorCount = 0;
        
        for (let i = 0; i < alumnosParaEnvio.length; i++) {
            const student = alumnosParaEnvio[i];
            const name = student.nombre + ' ' + (student.primer_apellido || '') + ' ' + (student.segundo_apellido || '');
            
            logArea.innerHTML += `> [${i+1}/${alumnosParaEnvio.length}] Procesando: ${name}... `;
            logArea.scrollTop = logArea.scrollHeight;
            
            const formData = new FormData();
            formData.append('matricula_id', student.matricula_id);
            formData.append('subject', subject);
            formData.append('body', body);
            
            try {
                const response = await fetch('api_send_matricula_keys.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    successCount++;
                    logArea.innerHTML += `<span style="color:#22c55e;">[ÉXITO]</span>\n`;
                } else {
                    errorCount++;
                    logArea.innerHTML += `<span style="color:#ef4444;">[ERROR: ${data.error}]</span>\n`;
                }
            } catch (err) {
                errorCount++;
                logArea.innerHTML += `<span style="color:#ef4444;">[ERROR CONEXIÓN]</span>\n`;
            }
            
            // Actualizar interfaz
            const pct = Math.round(((i + 1) / alumnosParaEnvio.length) * 100);
            progressBar.style.width = `${pct}%`;
            progressPct.textContent = `${pct}% completado`;
            progressCounts.textContent = `${i + 1} / ${alumnosParaEnvio.length} procesados`;
            logArea.scrollTop = logArea.scrollHeight;
        }
        
        logArea.innerHTML += `\n> --- PROCESO COMPLETADO ---\n`;
        logArea.innerHTML += `> Envíos con éxito: ${successCount}\n`;
        logArea.innerHTML += `> Errores / Omisiones: ${errorCount}\n`;
        logArea.scrollTop = logArea.scrollHeight;
        
        document.getElementById('mass-progress-title').textContent = '¡Proceso masivo completado!';
        finishBtn.disabled = false;
        finishBtn.style.background = '#10b981';
        finishBtn.style.color = 'white';
        finishBtn.style.border = '1px solid #059669';
        finishBtn.style.cursor = 'pointer';
        finishBtn.textContent = 'Cerrar y Actualizar';
        document.getElementById('btn-close-mass-modal').style.display = 'block';
    }
</script>
</body>
</html>
