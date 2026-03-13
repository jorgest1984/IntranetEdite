<?php
// ficha_alumno.php
session_start();
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

// Cargar detalles de profesorado
$stmtProf = $pdo->prepare("SELECT * FROM profesorado_detalles WHERE alumno_id = ?");
$stmtProf->execute([$id]);
$prof = $stmtProf->fetch() ?: [];

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

// Procesar actualización de profesorado (Refactorizado para no perder datos)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profesor') {
    try {
        $allowed_fields = [
            'titulacion', 'es_tutor', 'es_teleformador', 'es_presencial', 'hace_seguimiento',
            'tope_alumnos_turno', 'centro', 'id_plataforma', 'id_plataforma_2010', 'id_plataforma_2011',
            'id_plataforma_2013', 'id_plataforma_2015', 'id_plataforma_2016',
            'tramo1_de', 'tramo1_a', 'tramo1_v2_de', 'tramo1_v2_a',
            'tramo2_de', 'tramo2_a', 'tramo2_v2_de', 'tramo2_v2_a', 'aplicar_viernes',
            'com_fijo', 'com_tramo1', 'com_alumnos_fijo', 'com_fecha_fijo',
            'com_tramo2', 'com_tope2', 'com_presenciales', 'com_tramo3', 'com_tope3',
            'horario_general', 'obs_asistencia', 'vac_dias_pendientes'
        ];
        
        if (empty($prof)) {
            // Insert (Solo si no existe, inicializamos con lo que viene)
            $cols = ['alumno_id'];
            $vals = [$id];
            $placeholders = ['?'];
            foreach($allowed_fields as $f) {
                if (isset($_POST[$f])) {
                    $cols[] = $f;
                    $vals[] = $_POST[$f];
                    $placeholders[] = '?';
                }
            }
            $sql = "INSERT INTO profesorado_detalles (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $st = $pdo->prepare($sql);
            $st->execute($vals);
        } else {
            // Update parcial (Solo actualizamos lo que se envía en el formulario)
            $set = [];
            $params = [];
            foreach($allowed_fields as $f) {
                if (isset($_POST[$f])) {
                    $set[] = "$f = ?";
                    $params[] = $_POST[$f];
                }
            }
            
            if (!empty($set)) {
                $params[] = $prof['id'];
                $st = $pdo->prepare("UPDATE profesorado_detalles SET " . implode(', ', $set) . " WHERE id = ?");
                $st->execute($params);
            }
        }
        
        $goto_tab = $_POST['redirect_tab'] ?? 'profesorado';
        header("Location: ficha_alumno.php?id=$id&tab=$goto_tab&success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// Acción: Eliminar Asistencia con Auditoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_asistencia') {
    try {
        $asist_id = (int)$_POST['asist_id'];
        
        // 1. Obtener datos antes de borrar para registro
        $stGet = $pdo->prepare("SELECT * FROM prof_asistencia WHERE id = ? AND profesor_id = ?");
        $stGet->execute([$asist_id, $id]);
        $oldData = $stGet->fetch();
        
        if ($oldData) {
            // 2. Borrar Registro
            $stDel = $pdo->prepare("DELETE FROM prof_asistencia WHERE id = ?");
            $stDel->execute([$asist_id]);
            
            // 3. Auditoría
            audit_log($pdo, 'ASISTENCIA_ELIMINADA', 'prof_asistencia', $asist_id, $oldData, ['info' => 'Registro de asistencia borrado por usuario']);
        }
        
        header("Location: ficha_alumno.php?id=$id&tab=asistencia&success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error al eliminar asistencia: " . $e->getMessage();
    }
}

// Acción: Crear/Actualizar en Moodle
if (isset($_GET['action']) && $_GET['action'] == 'moodle_sync') {
    try {
        if (!$moodle->isConfigured()) throw new Exception("Moodle no está configurado.");

        // Buscar si ya existe por email
        $mUsers = $moodle->getUsersByField('email', [$alumno['email']]);
        $muid = null;

        if (!empty($mUsers['users'])) {
            $muid = $mUsers['users'][0]['id'];
            // Opcional: Podríamos actualizar datos aquí si fuera necesario
        } else {
            // Generar password básico
            $pass = "T" . substr(md5(time()), 0, 8) . "!";
            $newUsers = $moodle->createUser(
                strtolower(explode('@', $alumno['email'])[0]),
                $pass,
                $alumno['nombre'],
                $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido'],
                $alumno['email']
            );
            $muid = $newUsers[0]['id'];
        }

        // Guardar ID en local si no estaba
        if ($muid && $alumno['moodle_user_id'] != $muid) {
            $stU = $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?");
            $stU->execute([$muid, $id]);
            $alumno['moodle_user_id'] = $muid;
        }

        header("Location: ficha_alumno.php?id=$id&tab=profesorado&moodle_ok=1");
        exit();
    } catch (Exception $e) {
        $error = "Moodle Sync Error: " . $e->getMessage();
    }
}

// Acción: Actualizar datos en Moodle (Update)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'moodle_update') {
    try {
        if (!$moodle->isConfigured()) throw new Exception("Moodle no está configurado.");
        
        $muid = $alumno['moodle_user_id'];
        
        // Si no tenemos ID, intentamos buscar por email
        if (!$muid) {
            $existing = $moodle->getUsersByField('email', [$alumno['email']]);
            if (!empty($existing['users'])) {
                $muid = $existing['users'][0]['id'];
                // Guardar ID localmente para futuras syncs
                $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$muid, $id]);
            } else {
                throw new Exception("El usuario no existe en Moodle. Usa el botón de creación primero.");
            }
        }
        
        // Actualizar datos básicos
        $moodle->updateUser($muid, [
            'firstname' => $alumno['nombre'],
            'lastname'  => $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido'],
            'email'     => $alumno['email']
        ]);
        
        header("Location: ficha_alumno.php?id=$id&moodle_ok=1");
        exit();
    } catch (Exception $e) {
        $error = "Moodle Update Error: " . $e->getMessage();
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
            $params[] = $_POST[$f] ?? null;
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
// Acción: Actualizar Otros Datos de Interés (CV)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_cv_general') {
    try {
        $st = $pdo->prepare("UPDATE alumnos SET otros_datos_interes = ?, cv_updated_at = NOW() WHERE id = ?");
        $st->execute([$_POST['otros_datos_interes'] ?? '', $id]);
        header("Location: ficha_alumno.php?id=$id&tab=cv&success=1");
        exit();
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Acciones CV: Agregar Registros (Formación, Experiencia, Idiomas, Informática)
$cv_actions = [
    'add_formacion' => ['table' => 'prof_formacion', 'fields' => ['denominacion', 'organismo', 'centro', 'desde', 'hasta', 'horas', 'tipo_formacion']],
    'add_experiencia' => ['table' => 'prof_experiencia', 'fields' => ['empresa', 'desde', 'hasta', 'cargo', 'tareas']],
    'add_idioma' => ['table' => 'prof_idiomas', 'fields' => ['idioma', 'nivel_hablado', 'nivel_oral', 'nivel_escrito', 'nivel_leido']],
    'add_informatica' => ['table' => 'prof_informatica', 'fields' => ['programa', 'dominio']],
    'add_tutoria' => ['table' => 'prof_tutorias', 'fields' => ['anio', 'curso', 'modalidad']],
    'add_formacion_interna' => ['table' => 'prof_formacion_interna', 'fields' => ['accion_formativa', 'fecha_desde', 'fecha_hasta', 'duracion_horas', 'calificacion', 'valoracion_usuario', 'observaciones']],
    'add_prof_tarea' => ['table' => 'prof_tareas', 'fields' => [
        'expediente_id', 'tipo_tarea', 'num_accion', 'anio', 'horas_imparticion', 'horas_tutorizacion',
        'mes_1', 'mes_2', 'mes_3', 'mes_4', 'mes_5', 'mes_6', 
        'mes_7', 'mes_8', 'mes_9', 'mes_10', 'mes_11', 'mes_12'
    ]],
    'add_asistencia' => ['table' => 'prof_asistencia', 'fields' => ['fecha_desde', 'fecha_hasta', 'tipo', 'duracion_dias', 'duracion_horas', 'observaciones']]
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($cv_actions[$_POST['action']])) {
    try {
        $act = $cv_actions[$_POST['action']];
        $fields = $act['fields'];
        $sql = "INSERT INTO " . $act['table'] . " (profesor_id, " . implode(', ', $fields) . ") VALUES (?, " . implode(', ', array_fill(0, count($fields), '?')) . ")";
        $params = [$id];
        foreach($fields as $f) $params[] = $_POST[$f] ?? null;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        
        // Actualizar fecha de modificación del CV
        $pdo->prepare("UPDATE alumnos SET cv_updated_at = NOW() WHERE id = ?")->execute([$id]);
        
        $goto = $_POST['redirect_tab'] ?? 'cv';
        header("Location: ficha_alumno.php?id=$id&tab=$goto&success=1");
        exit();
    } catch (Exception $e) { $error = "Error al añadir registro: " . $e->getMessage(); }
}

// Obtener registros para el CV
$cv_formacion = $pdo->prepare("SELECT * FROM prof_formacion WHERE profesor_id = ? ORDER BY desde DESC");
$cv_formacion->execute([$id]);
$cv_formacion = $cv_formacion->fetchAll();

$cv_experiencia = $pdo->prepare("SELECT * FROM prof_experiencia WHERE profesor_id = ? ORDER BY desde DESC");
$cv_experiencia->execute([$id]);
$cv_experiencia = $cv_experiencia->fetchAll();

$cv_idiomas = $pdo->prepare("SELECT * FROM prof_idiomas WHERE profesor_id = ?");
$cv_idiomas->execute([$id]);
$cv_idiomas = $cv_idiomas->fetchAll();

$cv_informatica = $pdo->prepare("SELECT * FROM prof_informatica WHERE profesor_id = ?");
$cv_informatica->execute([$id]);
$cv_informatica = $cv_informatica->fetchAll();

$cv_tutorias = $pdo->prepare("SELECT * FROM prof_tutorias WHERE profesor_id = ? ORDER BY anio DESC");
$cv_tutorias->execute([$id]);
$cv_tutorias = $cv_tutorias->fetchAll();

$formacion_interna = $pdo->prepare("SELECT * FROM prof_formacion_interna WHERE profesor_id = ? ORDER BY fecha_desde DESC");
$formacion_interna->execute([$id]);
$formacion_interna = $formacion_interna->fetchAll();

// Cargar departamentos y perfiles seleccionados
$dept_stmt = $pdo->prepare("SELECT departamento FROM empleado_departamentos WHERE alumno_id = ?");
$dept_stmt->execute([$id]);
$selected_depts = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

$perfil_stmt = $pdo->prepare("SELECT perfil FROM empleado_perfiles WHERE alumno_id = ?");
$perfil_stmt->execute([$id]);
$selected_perfiles = $perfil_stmt->fetchAll(PDO::FETCH_COLUMN);

// Cargar expedientes y tareas
$expedientes_stmt = $pdo->query("SELECT id, codigo_expediente FROM convocatorias ORDER BY codigo_expediente DESC");
$expedientes = $expedientes_stmt->fetchAll();

$tareas_stmt = $pdo->prepare("
    SELECT t.*, c.codigo_expediente as expediente_codigo 
    FROM prof_tareas t 
    LEFT JOIN convocatorias c ON t.expediente_id = c.id 
    WHERE t.profesor_id = ? 
    ORDER BY t.anio DESC, t.id DESC
");
$tareas_stmt->execute([$id]);
$tareas = $tareas_stmt->fetchAll();

// Cargar asistencias
$asistencias_stmt = $pdo->prepare("SELECT * FROM prof_asistencia WHERE profesor_id = ? ORDER BY fecha_desde DESC");
$asistencias_stmt->execute([$id]);
$asistencias = $asistencias_stmt->fetchAll();

// Procesar actualización de perfiles y departamentos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_perfiles_dept') {
    try {
        $pdo->beginTransaction();
        
        // Departamentos
        $pdo->prepare("DELETE FROM empleado_departamentos WHERE alumno_id = ?")->execute([$id]);
        if (isset($_POST['depts']) && is_array($_POST['depts'])) {
            $stD = $pdo->prepare("INSERT INTO empleado_departamentos (alumno_id, departamento) VALUES (?, ?)");
            foreach($_POST['depts'] as $d) $stD->execute([$id, $d]);
        }
        
        // Perfiles
        $pdo->prepare("DELETE FROM empleado_perfiles WHERE alumno_id = ?")->execute([$id]);
        if (isset($_POST['perfiles']) && is_array($_POST['perfiles'])) {
            $stP = $pdo->prepare("INSERT INTO empleado_perfiles (alumno_id, perfil) VALUES (?, ?)");
            foreach($_POST['perfiles'] as $p) $stP->execute([$id, $p]);
        }
        
        $pdo->commit();
        header("Location: ficha_alumno.php?id=$id&tab=perfil&success=1");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error al actualizar perfiles: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha: <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Estilos específicos para la ficha avanzada con enfoque responsivo */
        .ficha-header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .tabs-header {
            display: flex;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) #f1f5f9;
        }
        /* Estilo para un scrollbar fino y moderno */
        .tabs-header::-webkit-scrollbar { height: 5px; }
        .tabs-header::-webkit-scrollbar-track { background: #f1f5f9; }
        .tabs-header::-webkit-scrollbar-thumb { background: var(--primary-color); border-radius: 10px; }

        .tab-btn {
            padding: 1rem 1.25rem;
            border: none;
            background: none;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            border-right: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .tab-btn.active { background: white; color: var(--primary-color); font-weight: 600; border-bottom: 2px solid var(--primary-color); }

        .tab-panel {
            background: white;
            padding: clamp(1rem, 5vw, 2rem);
            border-radius: 0 0 12px 12px;
            border: 1px solid var(--border-color);
            border-top: none;
            min-height: 500px;
            box-sizing: border-box;
        }

        /* Rejilla de información totalmente flexible */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 350px), 1fr));
            gap: 1.25rem;
            margin-top: 1.25rem;
        }

        .info-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .info-card h3 {
            margin-top: 0;
            font-size: 0.95rem;
            color: var(--primary-color);
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        .data-item {
            margin-bottom: 1rem;
        }

        .data-label {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .data-value {
            font-weight: 500;
            color: var(--text-color);
            word-break: break-word;
        }

        /* Sub-pestañas de acción (Estilo corporativo rojo) */
        .action-bar {
            display: flex;
            gap: 1px;
            background: var(--border-color);
            padding: 1px;
            border-radius: 6px;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 0.6rem 1.2rem;
            background: white;
            border: none;
            color: var(--primary-color);
            font-size: 0.9rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
        .action-btn:hover { background: #fee2e2; }
        .action-btn:first-child { border-radius: 5px 0 0 5px; }
        .action-btn:last-child { border-radius: 0 5px 5px 0; }
        
        @media (max-width: 640px) {
            .action-btn { border-radius: 4px !important; margin: 1px; }
            .action-bar { background: transparent; padding: 0; gap: 0.5rem; }
        }

        /* Formulario Profesorado Responsivo */
        .prof-form-row { 
            display: flex; 
            flex-direction: column; 
            gap: 0.25rem; 
            margin-bottom: 0.75rem; 
        }
        @media (min-width: 768px) {
            .prof-form-row { flex-direction: row; align-items: center; gap: 0.75rem; }
            .prof-form-label { width: 110px; flex-shrink: 0; text-align: right; }
        }
        
        .prof-form-label { font-weight: 600; color: var(--text-color); font-size: 0.9rem; }
        .prof-form-input { 
            width: 100%; 
            border: 1px solid var(--border-color); 
            border-radius: 6px; 
            padding: 0.6rem; 
            font-size: 0.9rem;
            box-sizing: border-box;
        }
        .prof-form-input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
        
        .time-tramo { 
            display: flex; 
            flex-wrap: wrap;
            align-items: center; 
            gap: 1rem; 
            background: #f8fafc; 
            padding: 1rem; 
            border-radius: 8px; 
            border: 1px solid var(--border-color); 
            width: 100%;
            box-sizing: border-box;
        }

        /* Estilos Documentos */
        .doc-list-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .doc-list { width: 100%; border-collapse: collapse; min-width: 600px; }
        .doc-list th { text-align: left; padding: 0.75rem; background: #f8fafc; border-bottom: 2px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); }
        .doc-list td { padding: 1rem 0.75rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }

        /* Modal responsivo */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        /* Refuerzo para formularios de CV para evitar solapamientos */
        .cv-form-grid {
            display: grid;
            gap: 1.25rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 992px) {
            .cv-form-grid { grid-template-columns: 1fr 1.5fr 1fr; }
            .cv-form-grid.formacion-grid { grid-template-columns: repeat(3, 1fr); }
        }
        .cv-date-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }
        .cv-date-group span { white-space: nowrap; font-size: 0.75rem; color: var(--text-muted); }
        .cv-date-group input { flex: 1; min-width: 0; }
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 16px;
            width: min(95%, 550px);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<div id="uploadModal" class="modal">
    <div class="modal-content">
        <h2 style="margin-bottom: 1.5rem;">Subir Documento</h2>
        <form action="subir_documento.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="alumno_id" value="<?= $id ?>">
            <div class="info-section">
                <div class="data-item">
                    <label class="data-label">Tipo de Documento</label>
                    <select name="tipo_documento" class="prof-form-input" style="width: 100%;">
                        <option value="Titulación">Titulación / CV</option>
                        <option value="DNI">DNI / NIE</option>
                        <option value="Certificado">Certificado de Empresa</option>
                        <option value="Contrato">Contrato / Anexo</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>
                <div class="data-item" style="margin-top: 1rem;">
                    <label class="data-label">Seleccionar Archivo</label>
                    <input type="file" name="archivo" required class="prof-form-input" style="width: 100%;">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn" onclick="hideUploadModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Subir Archivo</button>
            </div>
        </form>
    </div>
</div>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ficha-header">
            <div>
                <a href="alumnos.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg> 
                    Volver
                </a>
                <h1 style="margin:0; font-size: 1.5rem;"><?= htmlspecialchars(($alumno['nombre'] ?? '') . ' ' . ($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? '')) ?></h1>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="moodle_update">
                    <button type="submit" class="btn" style="background: #f8fafc; border: 1px solid var(--border-color); color: var(--text-color); display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg>
                        Sincronizar Aula Virtual
                    </button>
                </form>
                <button class="btn" style="background: white; border: 1px solid #d1d5db;">Editar Perfil</button>
            </div>
        </div>

        <nav class="tabs-header">
            <button class="tab-btn <?= $active_tab == 'personales' ? 'active' : '' ?>" onclick="switchTab('personales')">Datos Personales</button>
            <button class="tab-btn <?= $active_tab == 'profesorado' ? 'active' : '' ?>" onclick="switchTab('profesorado')">Datos Profesorado</button>
            <button class="tab-btn <?= $active_tab == 'cv' ? 'active' : '' ?>" onclick="switchTab('cv')">CV Profesor</button>
            <button class="tab-btn <?= $active_tab == 'documentacion' ? 'active' : '' ?>" onclick="switchTab('documentacion')">Documentación</button>
            <button class="tab-btn <?= $active_tab == 'asistencia' ? 'active' : '' ?>" onclick="switchTab('asistencia')">Asistencia</button>
            <button class="tab-btn <?= $active_tab == 'cuenta' ? 'active' : '' ?>" onclick="switchTab('cuenta')">Cuenta</button>
            <button class="tab-btn <?= $active_tab == 'formacion' ? 'active' : '' ?>" onclick="switchTab('formacion')">Formación</button>
            <button class="tab-btn <?= $active_tab == 'perfil' ? 'active' : '' ?>" onclick="switchTab('perfil')">Perfil/Depto</button>
            <button class="tab-btn <?= $active_tab == 'comerciales' ? 'active' : '' ?>" onclick="switchTab('comerciales')">Comerciales</button>
            <button class="tab-btn <?= $active_tab == 'tareas' ? 'active' : '' ?>" onclick="switchTab('tareas')">Tareas</button>
        </nav>

        <div class="tab-panel">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Datos actualizados correctamente.</span>
                    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:inherit; cursor:pointer; font-size: 1.2rem;">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['upload_success'])): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Archivo subido correctamente con trazabilidad de usuario.</span>
                    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:inherit; cursor:pointer; font-size: 1.2rem;">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['moodle_ok'])): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Profesor sincronizado correctamente en el Aula Virtual.</span>
                    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:inherit; cursor:pointer; font-size: 1.2rem;">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span><?= htmlspecialchars($error) ?></span>
                    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:inherit; cursor:pointer; font-size: 1.2rem;">&times;</button>
                </div>
            <?php endif; ?>
            
            <!-- TAB: Datos Personales (REDISEÑADA SEGÚN IMAGEN) -->
            <div id="tab-personales" class="tab-content" style="<?= $active_tab == 'personales' ? '' : 'display:none;' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="update_personales">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 0.75rem;">
                        <div class="prof-form-row">
                            <label class="prof-form-label">DNI:</label>
                            <input type="text" name="dni" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #1e40af; font-weight: 600;" value="<?= htmlspecialchars($alumno['dni']) ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Fecha de nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #1e40af; font-weight: 600;" value="<?= $alumno['fecha_nacimiento'] ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 0.75rem;">
                        <div class="prof-form-row">
                            <label class="prof-form-label">Nombre:</label>
                            <input type="text" name="nombre" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['nombre'] ?? '') ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Primer apellido:</label>
                            <input type="text" name="primer_apellido" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['primer_apellido'] ?? '') ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Segundo apellido:</label>
                            <input type="text" name="segundo_apellido" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['segundo_apellido'] ?? '') ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="prof-form-row">
                            <label class="prof-form-label">Seguridad Social:</label>
                            <input type="text" name="seguridad_social" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['seguridad_social'] ?? '') ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Cuenta Bancaria:</label>
                            <input type="text" name="cuenta_bancaria" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['cuenta_bancaria'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="prof-form-row" style="margin-bottom: 1.5rem;">
                        <label class="prof-form-label">Domicilio:</label>
                        <input type="text" name="domicilio" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['domicilio']) ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="prof-form-row">
                            <label class="prof-form-label">CP:</label>
                            <input type="text" name="cp" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['cp']) ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Localidad:</label>
                            <input type="text" name="localidad" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['localidad']) ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Provincia:</label>
                            <select name="provincia" id="provincia" class="prof-form-input" style="flex: 1; border: 1px solid #d1d5db; color: #dc2626; font-weight: 600;">
                                <option value="">-- Seleccionar --</option>
                                <?php
                                $provincias = [
                                    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
                                ];
                                foreach ($provincias as $prov):
                                    $pUpper = mb_strtoupper($prov, 'UTF-8');
                                    $sel = (mb_strtoupper($alumno['provincia'] ?? '', 'UTF-8') == $pUpper) ? 'selected' : '';
                                    echo "<option value=\"$pUpper\" $sel>".htmlspecialchars($prov)."</option>";
                                endforeach;
                                ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="prof-form-row">
                            <label class="prof-form-label">Teléfono:</label>
                            <input type="text" name="telefono" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['telefono']) ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Teléfono empresa:</label>
                            <input type="text" name="telefono_empresa" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['telefono_empresa']) ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="prof-form-row">
                            <label class="prof-form-label">E-mail:</label>
                            <input type="email" name="email" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['email']) ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">E-mail personal:</label>
                            <input type="email" name="email_personal" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['email_personal']) ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Teams:</label>
                            <input type="text" name="teams" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['teams']) ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="prof-form-row">
                            <label class="prof-form-label">Sexo:</label>
                            <select name="sexo" class="prof-form-input" style="flex: 1; border: 1px solid #d1d5db; color: #dc2626; font-weight: 600;">
                                <option value="Hombre" <?= $alumno['sexo'] == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                                <option value="Mujer" <?= $alumno['sexo'] == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                                <option value="Otro" <?= $alumno['sexo'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Nacionalidad:</label>
                            <input type="text" name="nacionalidad" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['nacionalidad']) ?>">
                        </div>
                        <div class="prof-form-row">
                            <label class="prof-form-label">Activo hasta:</label>
                            <input type="text" name="activo_hasta" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db; color: #dc2626; font-weight: 600;" value="<?= htmlspecialchars($alumno['activo_hasta']) ?>">
                        </div>
                    </div>

                    <div class="prof-form-row" style="margin-bottom: 1.5rem;">
                        <label class="prof-form-label">Nuestro:</label>
                        <div class="prof-form-radio">
                            <label><input type="radio" name="es_nuestro" value="1" <?= ($alumno['es_nuestro'] ?? 0) == 1 ? 'checked' : '' ?>> Si</label>
                            <label><input type="radio" name="es_nuestro" value="0" <?= ($alumno['es_nuestro'] ?? 0) == 0 ? 'checked' : '' ?>> No</label>
                        </div>
                    </div>

                    <div class="info-section">
                        <label class="prof-form-label">Observaciones:</label>
                        <textarea name="observaciones" class="prof-form-input" style="width: 100%; height: 80px; margin-top: 0.5rem; border: 1px solid #d1d5db;"><?= htmlspecialchars($alumno['observaciones']) ?></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 2rem;">Actualizar</button>
                    </div>
                </form>
            </div>
            
            <!-- TAB: PROFESORADO (REDISEÑADA SEGÚN IMAGEN) -->
            <div id="tab-profesorado" class="tab-content" style="<?= $active_tab == 'profesorado' ? '' : 'display:none;' ?>">
                
                <!-- Barra de Acciones Superior -->
                <div class="action-bar">
                    <a href="generar_certificado.php?id=<?= $id ?>" target="_blank" class="action-btn" style="text-decoration: none; text-align: center;">Certificado</a>
                    <a href="ficha_alumno.php?id=<?= $id ?>&action=moodle_sync&tab=profesorado" class="action-btn" style="text-decoration: none; text-align: center;">Crear/actualizar profesor en Aula Virtual</a>
                    <button class="action-btn" onclick="showUploadModal()">Subir documento</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="update_profesor">
                    
                    <div class="prof-form-row">
                        <label class="prof-form-label">Titulación:</label>
                        <input type="text" name="titulacion" class="prof-form-input" style="flex: 1; border: none; border-bottom: 1px solid #d1d5db;" value="<?= htmlspecialchars($prof['titulacion'] ?? '') ?>">
                    </div>

                    <?php 
                    $radios = [
                        'es_tutor' => 'Tutor:',
                        'es_teleformador' => 'Teleformador:',
                        'es_presencial' => 'Presencial:',
                        'hace_seguimiento' => 'Seguimiento:'
                    ];
                    foreach($radios as $key => $label): ?>
                    <div class="prof-form-row">
                        <label class="prof-form-label"><?= $label ?></label>
                        <div class="prof-form-radio">
                            <label><input type="radio" name="<?= $key ?>" value="1" <?= ($prof[$key] ?? 0) == 1 ? 'checked' : '' ?>> Sí</label>
                            <label><input type="radio" name="<?= $key ?>" value="0" <?= ($prof[$key] ?? 0) == 0 ? 'checked' : '' ?>> No</label>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="prof-form-row">
                        <label class="prof-form-label">Tope de alumnos por turno:</label>
                        <input type="number" name="tope_alumnos_turno" class="prof-form-input" style="width: 60px;" value="<?= $prof['tope_alumnos_turno'] ?? 0 ?>">
                    </div>

                    <div style="margin: 2rem 0; border-top: 1px solid #f3f4f6; padding-top: 1rem;">
                        <?php 
                        $plats = [
                            'id_plataforma' => 'ID plataforma:',
                            'id_plataforma_2010' => 'ID plataforma 2010:',
                            'id_plataforma_2011' => 'ID plataforma 2011:',
                            'id_plataforma_2013' => 'ID plataforma 2013:',
                            'id_plataforma_2015' => 'ID plataforma 2015:',
                            'id_plataforma_2016' => 'ID plataforma 2016:'
                        ];
                        foreach($plats as $id => $pl): ?>
                        <div class="prof-form-row">
                            <label class="prof-form-label"><?= $pl ?></label>
                            <input type="text" name="<?= $id ?>" class="prof-form-input" style="width: 150px; background: #fef2f2;" value="<?= htmlspecialchars($prof[$id] ?? '') ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="info-section">
                        <h4 style="margin-bottom: 1rem; color: #111827;">Tramos de tutorías (horarios):</h4>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div class="time-tramo">
                                <strong>Tramo 1, de</strong> 
                                <input type="time" name="tramo1_de" class="prof-form-input" value="<?= $prof['tramo1_de'] ?? '' ?>"> 
                                <strong>a</strong> 
                                <input type="time" name="tramo1_a" class="prof-form-input" value="<?= $prof['tramo1_a'] ?? '' ?>">
                                <span style="margin: 0 1rem; color: #9ca3af;">|</span>
                                <strong>y de</strong>
                                <input type="time" name="tramo1_v2_de" class="prof-form-input" value="<?= $prof['tramo1_v2_de'] ?? '' ?>">
                                <strong>a</strong>
                                <input type="time" name="tramo1_v2_a" class="prof-form-input" value="<?= $prof['tramo1_v2_a'] ?? '' ?>">
                            </div>
                            <div class="time-tramo">
                                <strong>Tramo 2, de</strong> 
                                <input type="time" name="tramo2_de" class="prof-form-input" value="<?= $prof['tramo2_de'] ?? '' ?>"> 
                                <strong>a</strong> 
                                <input type="time" name="tramo2_a" class="prof-form-input" value="<?= $prof['tramo2_a'] ?? '' ?>">
                                <span style="margin: 0 1rem; color: #9ca3af;">|</span>
                                <strong>y de</strong>
                                <input type="time" name="tramo2_v2_de" class="prof-form-input" value="<?= $prof['tramo2_v2_de'] ?? '' ?>">
                                <strong>a</strong>
                                <input type="time" name="tramo2_v2_a" class="prof-form-input" value="<?= $prof['tramo2_v2_a'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="prof-form-row" style="margin-top: 1.5rem;">
                        <label class="prof-form-label" style="width: auto;">Aplicar horario de tutorías también los viernes:</label>
                        <div class="prof-form-radio">
                            <label><input type="radio" name="aplicar_viernes" value="1" <?= ($prof['aplicar_viernes'] ?? 0) == 1 ? 'checked' : '' ?>> Sí</label>
                            <label><input type="radio" name="aplicar_viernes" value="0" <?= ($prof['aplicar_viernes'] ?? 0) == 0 ? 'checked' : '' ?>> No</label>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 3rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 2rem;">Actualizar</button>
                    </div>
                </form>

                <!-- Sección Documentación Integrada -->
                <div style="margin-top: 4rem; border-top: 2px solid #f3f4f6; padding-top: 2rem;">
                    <h3 style="font-size: 1.1rem; color: #111827;">Documentos del Alumno / Profesor</h3>
                    <table class="doc-list">
                        <thead>
                            <tr>
                                <th>Nombre del archivo</th>
                                <th>Tipo</th>
                                <th>Subido por</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($documentos)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #9ca3af;">No hay documentos asociados.</td></tr>
                            <?php else: foreach($documentos as $doc): ?>
                                <tr>
                                    <td style="font-weight: 500; color: #1d4ed8;"><?= htmlspecialchars($doc['nombre_archivo']) ?></td>
                                    <td><span class="sync-badge" style="background: #f3f4f6; color: #4b5563;"><?= $doc['tipo_documento'] ?></span></td>
                                    <td><?= htmlspecialchars($doc['username']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></td>
                                    <td>
                                        <a href="<?= $doc['ruta_archivo'] ?>" target="_blank" class="btn" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; text-decoration: none;">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: CV PROFESOR (NUEVO) -->
            <div id="tab-cv" class="tab-content" style="<?= $active_tab == 'cv' ? '' : 'display:none;' ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 style="margin: 0; color: var(--primary-color);">Curriculum Vitae</h2>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        Última actualización: <strong><?= ($alumno['cv_updated_at'] ?? null) ? date('d/m/Y H:i', strtotime($alumno['cv_updated_at'])) : 'Nunca' ?></strong>
                    </div>
                </div>

                <!-- 1. EXPERIENCIA COMO TUTOR (AUTOMÁTICA) -->
                <section class="info-section">
                    <h3 style="color: var(--primary-color); border-bottom: 2px solid #fee2e2; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">EXPERIENCIA COMO TUTOR</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">* Cargada automáticamente desde el historial de cursos.</p>
                    <div class="doc-list-wrapper">
                        <table class="doc-list">
                            <thead>
                                <tr><th>Año</th><th>Curso</th><th>Modalidad</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($cv_tutorias as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['anio']) ?></td>
                                    <td><?= htmlspecialchars($t['curso']) ?></td>
                                    <td><?= htmlspecialchars($t['modalidad']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($cv_tutorias)): ?><tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No hay registros históricos de tutorías en el sistema.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <form method="POST" style="background: #fdf2f2; padding: 1.5rem; border-radius: 12px; border: 1px solid #fecaca; margin-top: 1rem;">
                        <input type="hidden" name="action" value="add_tutoria">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                            <input type="text" name="anio" class="prof-form-input" placeholder="Año (Ej: 2023) *" required>
                            <input type="text" name="curso" class="prof-form-input" placeholder="Nombre del Curso *" required>
                            <input type="text" name="modalidad" class="prof-form-input" placeholder="Modalidad">
                        </div>
                        <div style="display: flex; justify-content: center; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="padding-left: 2.5rem; padding-right: 2.5rem;">Añadir Tutoría</button>
                        </div>
                    </form>
                </section>

                <!-- 2. FORMACIÓN -->
                <section class="info-section" style="margin-top: 2rem;">
                    <h3 style="color: var(--primary-color); border-bottom: 2px solid #fee2e2; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">FORMACIÓN</h3>
                    <div class="doc-list-wrapper" style="margin-bottom: 1.5rem;">
                        <table class="doc-list">
                            <thead>
                                <tr><th>Denominación</th><th>Organismo</th><th>Desde/Hasta</th><th>Horas</th><th>Tipo</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($cv_formacion as $f): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($f['denominacion']) ?></td>
                                    <td><?= htmlspecialchars($f['organismo']) ?></td>
                                    <td><?= $f['desde'] ? date('m/Y', strtotime($f['desde'])) : '' ?> - <?= $f['hasta'] ? date('m/Y', strtotime($f['hasta'])) : 'Pres.' ?></td>
                                    <td><?= $f['horas'] ?>h</td>
                                    <td><span style="font-size: 0.75rem; background: #fee2e2; color: #b91c1c; padding: 2px 8px; border-radius: 99px; font-weight: 600;"><?= $f['tipo_formacion'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($cv_formacion)): ?><tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No se han añadido títulos de formación.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <form method="POST" style="background: #fdf2f2; padding: 1.5rem; border-radius: 12px; border: 1px solid #fecaca;">
                        <input type="hidden" name="action" value="add_formacion">
                        <div class="cv-form-grid formacion-grid">
                            <input type="text" name="denominacion" class="prof-form-input" placeholder="Título / Denominación *" required>
                            <input type="text" name="organismo" class="prof-form-input" placeholder="Organismo">
                            <input type="text" name="centro" class="prof-form-input" placeholder="Centro">
                            <div class="cv-date-group">
                                <span>Desde/Hasta:</span>
                                <input type="date" name="desde" class="prof-form-input">
                                <input type="date" name="hasta" class="prof-form-input">
                            </div>
                            <input type="number" name="horas" class="prof-form-input" placeholder="Horas">
                            <select name="tipo_formacion" class="prof-form-input">
                                <option value="Reglada">Reglada</option>
                                <option value="No Reglada">No Reglada</option>
                                <option value="Complementaria">Complementaria</option>
                            </select>
                        </div>
                        <div style="display: flex; justify-content: center; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="padding-left: 2.5rem; padding-right: 2.5rem;"> AÑADIR FORMACIÓN </button>
                        </div>
                    </form>
                </section>

                <!-- 3. EXPERIENCIA PROFESIONAL -->
                <section class="info-section" style="margin-top: 2rem;">
                    <h3 style="color: var(--primary-color); border-bottom: 2px solid #fee2e2; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">EXPERIENCIA PROFESIONAL</h3>
                    <div class="doc-list-wrapper" style="margin-bottom: 1.5rem;">
                        <table class="doc-list">
                            <thead>
                                <tr><th>Empresa</th><th>Periodo</th><th>Cargo</th><th>Tareas</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($cv_experiencia as $e): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($e['empresa']) ?></td>
                                    <td><?= $e['desde'] ? date('m/Y', strtotime($e['desde'])) : '' ?> - <?= $e['hasta'] ? date('m/Y', strtotime($e['hasta'])) : 'Actualmente' ?></td>
                                    <td><?= htmlspecialchars($e['cargo']) ?></td>
                                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?= nl2br(htmlspecialchars($e['tareas'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($cv_experiencia)): ?><tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Sin experiencia profesional registrada.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <form method="POST" style="background: #fdf2f2; padding: 1.5rem; border-radius: 12px; border: 1px solid #fecaca;">
                        <input type="hidden" name="action" value="add_experiencia">
                        <div class="cv-form-grid">
                            <input type="text" name="empresa" class="prof-form-input" placeholder="Empresa / Institución *" required>
                            <div class="cv-date-group">
                                <span>Periodo:</span>
                                <input type="date" name="desde" class="prof-form-input">
                                <input type="date" name="hasta" class="prof-form-input">
                            </div>
                            <input type="text" name="cargo" class="prof-form-input" placeholder="Cargo o Puesto">
                            <textarea name="tareas" class="prof-form-input" placeholder="Resumen de tareas realizadas..." style="grid-column: 1 / -1; height: 80px;"></textarea>
                        </div>
                        <div style="display: flex; justify-content: center; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="padding-left: 2.5rem; padding-right: 2.5rem;">Añadir Experiencia</button>
                        </div>
                    </form>
                </section>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; margin-top: 2rem;">
                    <!-- 4. IDIOMAS -->
                    <section class="info-section">
                        <h4 style="color: var(--primary-color); border-bottom: 2px solid #fee2e2; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">IDIOMAS</h4>
                        <div class="doc-list-wrapper" style="margin-bottom: 1rem;">
                            <table class="doc-list" style="min-width: 100%;">
                                <thead>
                                    <tr><th>Idioma</th><th>Hab / Ord / Esc / Leíd</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($cv_idiomas as $i): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($i['idioma']) ?></td>
                                        <td><?= $i['nivel_hablado'] ?> / <?= $i['nivel_oral'] ?> / <?= $i['nivel_escrito'] ?> / <?= $i['nivel_leido'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($cv_idiomas)): ?><tr><td colspan="2" style="text-align: center; color: var(--text-muted);">No registrados.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <form method="POST" style="background: #fdf2f2; padding: 1rem; border-radius: 12px; border: 1px solid #fecaca;">
                            <input type="hidden" name="action" value="add_idioma">
                            <input type="text" name="idioma" class="prof-form-input" placeholder="Idioma (Ej: Inglés) *" required style="margin-bottom: 0.5rem;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <input type="text" name="nivel_hablado" class="prof-form-input" placeholder="Nivel Hablado">
                                <input type="text" name="nivel_oral" class="prof-form-input" placeholder="Nivel Oral">
                                <input type="text" name="nivel_escrito" class="prof-form-input" placeholder="Nivel Escrito">
                                <input type="text" name="nivel_leido" class="prof-form-input" placeholder="Nivel Leído">
                            </div>
                            <div style="display: flex; justify-content: center; margin-top: 1rem;">
                                <button type="submit" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;">Añadir idioma</button>
                            </div>
                        </form>
                    </section>

                    <!-- 5. INFORMÁTICA -->
                    <section class="info-section">
                        <h4 style="color: var(--primary-color); border-bottom: 2px solid #fee2e2; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">INFORMÁTICA</h4>
                        <div class="doc-list-wrapper" style="margin-bottom: 1rem;">
                            <table class="doc-list" style="min-width: 100%;">
                                <thead>
                                    <tr><th>Software / Especialización</th><th>Dominio</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($cv_informatica as $inf): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($inf['programa']) ?></td>
                                        <td style="font-weight: 600; color: var(--primary-color);"><?= $inf['dominio'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($cv_informatica)): ?><tr><td colspan="2" style="text-align: center; color: var(--text-muted);">Sin datos registrados.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <form method="POST" style="background: #fdf2f2; padding: 1rem; border-radius: 12px; border: 1px solid #fecaca;">
                            <input type="hidden" name="action" value="add_informatica">
                            <input type="text" name="programa" class="prof-form-input" placeholder="Software o área informática *" required style="margin-bottom: 0.5rem;">
                            <select name="dominio" class="prof-form-input">
                                <option value="Básico">Básico</option>
                                <option value="Medio" selected>Medio</option>
                                <option value="Avanzado">Avanzado</option>
                                <option value="Experto">Experto</option>
                            </select>
                            <div style="display: flex; justify-content: center; margin-top: 1rem;">
                                <button type="submit" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;">Añadir software</button>
                            </div>
                        </form>
                    </section>
                </div>

                <!-- 6. OTROS DATOS DE INTERÉS -->
                <section class="info-section" style="margin-top: 2rem;">
                    <h3 style="color: var(--primary-color); border-bottom: 2px solid #fee2e2; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">OTROS DATOS DE INTERÉS</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_cv_general">
                        <textarea name="otros_datos_interes" class="prof-form-input" style="height: 100px; padding: 1rem;" placeholder="Vehículo propio, disponibilidad geográfica, cursos adicionales..."><?= htmlspecialchars($alumno['otros_datos_interes'] ?? '') ?></textarea>
                        <div style="text-align: right; margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 3rem;">Actualizar Datos CV</button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- TABS MOCKUPS (IGUAL QUE ANTES PERO SIN DOCUMENTOS GLOBAL) -->
            <div id="tab-personales" class="tab-content" style="<?= $active_tab == 'personales' ? '' : 'display:none;' ?>">
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Identidad</h3>
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?></p>
                        <p><strong>DNI:</strong> <?= htmlspecialchars($alumno['dni']) ?></p>
                    </div>
                </div>
            </div>

            <!-- TAB: Documentación / Contratos (REDISEÑADA) -->
            <div id="tab-documentacion" class="tab-content" style="<?= $active_tab == 'documentacion' ? '' : 'display:none;' ?>">
                <div style="background: #fdf2f2; padding: 2rem; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 2.5rem;">
                    <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Subir Documento / Contrato
                    </h2>
                    
                    <form action="subir_documento.php" method="POST" enctype="multipart/form-data" class="horizontal-form" style="grid-template-columns: 1fr 2fr auto;">
                        <input type="hidden" name="alumno_id" value="<?= $id ?>">
                        
                        <div class="form-group">
                            <label class="form-label" style="color: var(--primary-color);">Tipo de Documento</label>
                            <select name="tipo_documento" class="form-input">
                                <option value="Contrato">Contrato Laboral</option>
                                <option value="Anexo">Anexo de Contrato</option>
                                <option value="Nomina">Nómina</option>
                                <option value="Certificado">Certificado de Empresa</option>
                                <option value="Otros">Otros Documentos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" style="color: var(--primary-color);">Seleccionar Archivo (PDF, JPG, PNG)</label>
                            <input type="file" name="archivo" required class="form-input" style="padding: 0.55rem;">
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 2rem; font-weight: 600;">
                                SUBIR ARCHIVO
                            </button>
                        </div>
                    </form>
                </div>

                <div class="list-section" style="margin-top: 1rem;">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: #374151; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                        Listado de Documentos y Contratos
                    </h3>
                    
                    <div class="doc-list-wrapper">
                        <table class="doc-list">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Fecha Subida</th>
                                    <th>Subido por</th>
                                    <th>Formato</th>
                                    <th style="text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documentos)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                            No hay documentos subidos para este profesor.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documentos as $doc): ?>
                                    <tr>
                                        <td style="font-weight: 500;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div style="width: 35px; height: 35px; background: #fee2e2; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary-color);">
                                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                                </div>
                                                <div>
                                                    <div style="font-size: 0.95rem;"><?= htmlspecialchars($doc['tipo_documento']) ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($doc['nombre_archivo']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></td>
                                        <td><span style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($doc['username']) ?></span></td>
                                        <td>
                                            <?php 
                                            $ext = strtoupper(pathinfo($doc['nombre_archivo'], PATHINFO_EXTENSION));
                                            $color = $ext == 'PDF' ? '#dc2626' : ($ext == 'ZIP' ? '#d97706' : '#2563eb');
                                            ?>
                                            <span style="background: <?= $color ?>20; color: <?= $color ?>; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; border: 1px solid <?= $color ?>40;">
                                                <?= $ext ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn" style="padding: 0.4rem 0.8rem; border: 1px solid var(--border-color); font-size: 0.8rem; backgroun: white;">
                                                Ver / Descargar
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB: Formación (ROBUSTA Y REDISEÑADA) -->
            <div id="tab-formacion" class="tab-content" style="<?= $active_tab == 'formacion' ? '' : 'display:none;' ?>">
                <div style="background: #fdf2f2; padding: 2rem; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 2.5rem;">
                    <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.25rem; margin-bottom: 2rem; border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; text-align: center;">
                        ACCIÓN FORMATIVA
                    </h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_formacion_interna">
                        
                        <div class="prof-form-row">
                            <label class="prof-form-label" style="width: 150px; color: #1e40af;">Acción formativa:</label>
                            <input type="text" name="accion_formativa" class="prof-form-input" required placeholder="Ej: Curso de Prevención de Riesgos">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem;">
                            <div class="prof-form-row">
                                <label class="prof-form-label" style="width: 120px; color: #1e40af;">Fecha desde:</label>
                                <input type="date" name="fecha_desde" class="prof-form-input">
                            </div>
                            <div class="prof-form-row">
                                <label class="prof-form-label" style="width: 120px; color: #1e40af;">Fecha hasta:</label>
                                <input type="date" name="fecha_hasta" class="prof-form-input">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem;">
                            <div class="prof-form-row">
                                <label class="prof-form-label" style="width: 120px; color: #1e40af;">Duración (horas):</label>
                                <input type="number" name="duracion_horas" class="prof-form-input" placeholder="0">
                            </div>
                            <div class="prof-form-row">
                                <label class="prof-form-label" style="width: 120px; color: #1e40af;">Calificación:</label>
                                <input type="text" name="calificacion" class="prof-form-input" placeholder="Ej: Apto / Sobresaliente">
                            </div>
                        </div>

                        <div class="prof-form-row" style="margin-top: 1rem; align-items: flex-start;">
                            <label class="prof-form-label" style="width: 150px; color: #1e40af; padding-top: 0.5rem;">Valoración del usuario:</label>
                            <textarea name="valoracion_usuario" class="prof-form-input" style="height: 80px;" placeholder="Impresiones del profesor sobre el curso..."></textarea>
                        </div>

                        <div class="prof-form-row" style="margin-top: 1rem; align-items: flex-start;">
                            <label class="prof-form-label" style="width: 150px; color: #1e40af; padding-top: 0.5rem;">Observaciones:</label>
                            <textarea name="observaciones" class="prof-form-input" style="height: 80px;" placeholder="Notas adicionales del departamento de formación..."></textarea>
                        </div>

                        <div style="display: flex; justify-content: center; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 3rem; font-weight: 700;">
                                AÑADIR ACCIÓN FORMATIVA
                            </button>
                        </div>
                    </form>
                </div>

                <div class="list-section">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: #374151; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                        Histórico de Formación Interna
                    </h3>
                    <div class="doc-list-wrapper">
                        <table class="doc-list">
                            <thead>
                                <tr>
                                    <th>Acción formativa</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Duración</th>
                                    <th>Calificación</th>
                                    <th>Valoración</th>
                                    <th style="text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($formacion_interna)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">No hay acciones formativas registradas.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($formacion_interna as $fi): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: #1e40af;"><?= htmlspecialchars($fi['accion_formativa']) ?></td>
                                        <td><?= $fi['fecha_desde'] ? date('d/m/Y', strtotime($fi['fecha_desde'])) : '-' ?></td>
                                        <td><?= $Fi['fecha_hasta'] ? date('d/m/Y', strtotime($fi['fecha_hasta'])) : '-' ?></td>
                                        <td><?= $fi['duracion_horas'] ?>h</td>
                                        <td><span style="font-weight: 500;"><?= htmlspecialchars($fi['calificacion'] ?: '-') ?></span></td>
                                        <td><span title="<?= htmlspecialchars($fi['valoracion_usuario']) ?>" style="cursor: help; color: var(--text-muted);"><?= mb_strimwidth($fi['valoracion_usuario'], 0, 30, "...") ?></span></td>
                                        <td style="text-align: right;">
                                            <button class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Detalles</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB: Perfil / Departamentos (REDISEÑADA) -->
            <div id="tab-perfil" class="tab-content" style="<?= $active_tab == 'perfil' ? '' : 'display:none;' ?>">
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_perfiles_dept">
                        
                        <!-- Sección Departamentos -->
                        <div style="background: #fdf2f2; padding: 1.5rem; border-bottom: 2px solid #fecaca;">
                            <h2 style="margin: 0; color: var(--primary-color); font-size: 1.1rem; text-align: center; text-transform: uppercase; letter-spacing: 0.05em;">
                                DEPARTAMENTOS ASOCIADOS
                            </h2>
                        </div>
                        <div style="padding: 2rem;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                                <?php 
                                $depts_list = ["Tutorial", "Gestión y Documentación", "Informática", "Comercial y Marketing", "Administración", "I+D"];
                                foreach($depts_list as $dp): ?>
                                <label style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                                    <span style="font-weight: 600; color: #1e40af;"><?= $dp ?></span>
                                    <input type="checkbox" name="depts[]" value="<?= $dp ?>" <?= in_array($dp, $selected_depts) ? 'checked' : '' ?> style="width: 20px; height: 20px; accent-color: var(--primary-color);">
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Sección Perfiles -->
                        <div style="background: #fdf2f2; padding: 1.5rem; border-top: 1px solid var(--border-color); border-bottom: 2px solid #fecaca;">
                            <h2 style="margin: 0; color: var(--primary-color); font-size: 1.1rem; text-align: center; text-transform: uppercase; letter-spacing: 0.05em;">
                                PERFILES
                            </h2>
                        </div>
                        <div style="padding: 2rem;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                                <?php 
                                $perfiles_list = ["ADMINISTRATIVO", "COMERCIAL/TELEOPERADOR", "INFORMÁTICO", "TUTOR/FORMADOR", "DIRECTOR", "RESPONSABLE DE CALIDAD", "COORDINADOR DE ÁREA"];
                                foreach($perfiles_list as $pf): ?>
                                <label style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                                    <span style="font-weight: 600; color: #1e40af;"><?= $pf ?></span>
                                    <input type="checkbox" name="perfiles[]" value="<?= $pf ?>" <?= in_array($pf, $selected_perfiles) ? 'checked' : '' ?> style="width: 20px; height: 20px; accent-color: var(--primary-color);">
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: center; padding: 2.5rem; background: #f8fafc; border-top: 1px solid var(--border-color);">
                            <button type="submit" class="btn btn-primary" style="padding: 0.8rem 4rem; font-weight: 700; font-size: 1rem; letter-spacing: 0.05em;">
                                ACTUALIZAR PERFIL Y DEPARTAMENTOS
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB: Comerciales / Comisiones (REDISEÑADA SEGÚN IMAGEN) -->
            <div id="tab-comerciales" class="tab-content" style="<?= $active_tab == 'comerciales' ? '' : 'display:none;' ?>">
                <div style="background: #fdf2f2; padding: 2.5rem; border-radius: 12px; border: 1px solid #fecaca;">
                    <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.25rem; margin-bottom: 2.5rem; border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; text-align: center; text-transform: uppercase; letter-spacing: 0.05em;">
                        ESTRUCTURA DE COMISIONES
                    </h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profesor">
                        
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; align-items: end;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Fijo:</label>
                                <input type="number" step="0.01" name="com_fijo" class="prof-form-input" value="<?= $prof['com_fijo'] ?? 0 ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Comisión tramo 1:</label>
                                <input type="number" step="0.01" name="com_tramo1" class="prof-form-input" value="<?= $prof['com_tramo1'] ?? 0 ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Alumnos fijo:</label>
                                <input type="number" name="com_alumnos_fijo" class="prof-form-input" value="<?= $prof['com_alumnos_fijo'] ?? 0 ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Fecha fijo:</label>
                                <input type="date" name="com_fecha_fijo" class="prof-form-input" value="<?= $prof['com_fecha_fijo'] ?? '' ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem; align-items: end;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Comisión tramo 2:</label>
                                <input type="number" step="0.01" name="com_tramo2" class="prof-form-input" value="<?= $prof['com_tramo2'] ?? 0 ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Tope tramo 2:</label>
                                <input type="number" name="com_tope2" class="prof-form-input" value="<?= $prof['com_tope2'] ?? 0 ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Comisión presenciales:</label>
                                <input type="number" step="0.01" name="com_presenciales" class="prof-form-input" value="<?= $prof['com_presenciales'] ?? 0 ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem; align-items: end;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Comisión tramo 3:</label>
                                <input type="number" step="0.01" name="com_tramo3" class="prof-form-input" value="<?= $prof['com_tramo3'] ?? 0 ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Tope tramo 3:</label>
                                <input type="number" name="com_tope3" class="prof-form-input" value="<?= $prof['com_tope3'] ?? 0 ?>">
                            </div>
                            <div></div> <!-- Espacio vacío para mantener la rejilla -->
                        </div>

                        <div style="display: flex; justify-content: center; margin-top: 3.5rem;">
                            <button type="submit" class="btn btn-primary" style="padding: 1rem 4rem; font-weight: 700; font-size: 1rem; letter-spacing: 0.05em;">
                                ACTUALIZAR COMISIONES
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB: Tareas / Seguimiento (REDISEÑADA SEGÚN IMAGEN) -->
            <div id="tab-tareas" class="tab-content" style="<?= $active_tab == 'tareas' ? '' : 'display:none;' ?>">
                <div style="background: #fdf2f2; padding: 2rem; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 2.5rem;">
                    <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.25rem; margin-bottom: 2rem; border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; text-align: center; text-transform: uppercase; letter-spacing: 0.05em;">
                        NUEVA TAREA / ASIGNACIÓN
                    </h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_prof_tarea">
                        <input type="hidden" name="redirect_tab" value="tareas">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Expediente:</label>
                                <select name="expediente_id" class="prof-form-input">
                                    <option value="">Seleccionar expediente...</option>
                                    <?php foreach($expedientes as $exp): ?>
                                        <option value="<?= $exp['id'] ?>"><?= htmlspecialchars($exp['codigo_expediente']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Tipo tarea:</label>
                                <select name="tipo_tarea" class="prof-form-input">
                                    <option value="">Seleccionar tipo...</option>
                                    <option value="Docencia">Docencia</option>
                                    <option value="Tutorización">Tutorización</option>
                                    <option value="Coordinación">Coordinación</option>
                                    <option value="Materiales">Materiales</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-size: 0.85rem;">Nº Acción:</label>
                                <input type="text" name="num_accion" class="prof-form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-size: 0.85rem;">Año:</label>
                                <input type="number" name="anio" class="prof-form-input" value="<?= date('Y') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-size: 0.85rem;">Horas impartición:</label>
                                <input type="number" name="horas_imparticion" class="prof-form-input" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-size: 0.85rem;">Horas tutorización:</label>
                                <input type="number" name="horas_tutorizacion" class="prof-form-input" value="0">
                            </div>
                        </div>

                        <div style="background: rgba(255,255,255,0.5); padding: 1.5rem; border-radius: 8px; border: 1px solid #fecaca;">
                            <h3 style="margin-top: 0; font-size: 0.9rem; color: var(--primary-color); margin-bottom: 1rem; text-align: center;">DETALLE DE HORAS POR MESES</h3>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                                <?php 
                                $meses = [1=>"Enero", 2=>"Febrero", 3=>"Marzo", 4=>"Abril", 5=>"Mayo", 6=>"Junio", 7=>"Julio", 8=>"Agosto", 9=>"Septiembre", 10=>"Octubre", 11=>"Noviembre", 12=>"Diciembre"];
                                foreach($meses as $mIdx => $mName): ?>
                                <div class="form-group">
                                    <label class="form-label" style="color: #475569; font-size: 0.75rem;"><?= $mName ?>:</label>
                                    <input type="number" name="mes_<?= $mIdx ?>" class="prof-form-input" value="0" style="padding: 0.4rem;">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: center; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 3rem; font-weight: 700;">
                                AÑADIR TAREA
                            </button>
                        </div>
                    </form>
                </div>

                <div class="list-section">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: #374151; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                        Registro de Tareas / Histórico Móvil
                    </h3>
                    <div class="doc-list-wrapper" style="overflow-x: auto;">
                        <table class="doc-list" style="min-width: 1200px;">
                            <thead>
                                <tr>
                                    <th>Expediente</th>
                                    <th>Nº Acción</th>
                                    <th>Año</th>
                                    <?php foreach($meses as $mIdx => $mShort): ?>
                                        <th style="width: 40px; text-align: center; font-size: 0.7rem;"><?= substr($mShort, 0, 3) ?></th>
                                    <?php endforeach; ?>
                                    <th>H. Imp.</th>
                                    <th>H. Tut.</th>
                                    <th style="text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tareas)): ?>
                                    <tr>
                                        <td colspan="18" style="text-align: center; padding: 3rem; color: var(--text-muted);">No hay tareas asignadas.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tareas as $t): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: #1e40af;"><?= htmlspecialchars($t['expediente_codigo'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($t['num_accion']) ?></td>
                                        <td><?= $t['anio'] ?></td>
                                        <?php for($i=1; $i<=12; $i++): ?>
                                            <td style="text-align: center; font-size: 0.85rem; <?= $t['mes_'.$i] > 0 ? 'background: #f0f9ff; font-weight: 600;' : '' ?>">
                                                <?= $t['mes_'.$i] ?: '-' ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td style="font-weight: 600; text-align: center; background: #fff1f2;"><?= $t['horas_imparticion'] ?>h</td>
                                        <td style="font-weight: 600; text-align: center; background: #fff1f2;"><?= $t['horas_tutorizacion'] ?>h</td>
                                        <td style="text-align: right;">
                                            <button class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">Eliminar</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB: Asistencia / Horarios (REDISEÑADA SEGÚN IMAGEN) -->
            <div id="tab-asistencia" class="tab-content" style="<?= $active_tab == 'asistencia' ? '' : 'display:none;' ?>">
                <div style="background: #fdf2f2; padding: 2rem; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 2rem;">
                    <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.25rem; margin-bottom: 2rem; border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; text-align: center; text-transform: uppercase;">
                        NUEVO CONTROL DE ASISTENCIA
                    </h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_asistencia">
                        <input type="hidden" name="redirect_tab" value="asistencia">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Fecha desde:</label>
                                <input type="date" name="fecha_desde" class="prof-form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Fecha hasta:</label>
                                <input type="date" name="fecha_hasta" class="prof-form-input">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Tipo:</label>
                                <select name="tipo" class="prof-form-input">
                                    <option value="">Seleccionar tipo...</option>
                                    <option value="Vacaciones">Vacaciones</option>
                                    <option value="Baja Médica">Baja Médica</option>
                                    <option value="Ausencia Justificada">Ausencia Justificada</option>
                                    <option value="Asuntos Propios">Asuntos Propios</option>
                                    <option value="Otros">Otros</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Duración (días):</label>
                                <input type="number" step="0.5" name="duracion_dias" class="prof-form-input" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af; font-weight: 700;">Duración (horas):</label>
                                <input type="number" step="0.5" name="duracion_horas" class="prof-form-input" value="0">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label class="form-label" style="color: #1e40af; font-weight: 700;">Observaciones:</label>
                            <input type="text" name="observaciones" class="prof-form-input">
                        </div>

                        <div style="display: flex; justify-content: center;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 3rem; font-weight: 700;">
                                AÑADIR ASISTENCIA
                            </button>
                        </div>
                    </form>
                </div>

                <div class="list-section" style="margin-bottom: 3rem;">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: #374151; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                        Histórico de Asistencias
                    </h3>
                    <div class="doc-list-wrapper">
                        <table class="doc-list">
                            <thead>
                                <tr>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Días</th>
                                    <th>Horas</th>
                                    <th>Tipo</th>
                                    <th style="width: 30%;">Observaciones</th>
                                    <th style="text-align: right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($asistencias)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay registros de asistencia.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($asistencias as $as): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($as['fecha_desde'])) ?></td>
                                        <td><?= $as['fecha_hasta'] ? date('d/m/Y', strtotime($as['fecha_hasta'])) : '-' ?></td>
                                        <td style="font-weight: 600;"><?= $as['duracion_dias'] ?> d</td>
                                        <td><?= $as['duracion_horas'] ?> h</td>
                                        <td><span style="background: #eff6ff; color: #1e40af; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 500;"><?= htmlspecialchars($as['tipo']) ?></span></td>
                                        <td style="font-size: 0.85rem; color: #4b5563;"><?= htmlspecialchars($as['observaciones']) ?></td>
                                        <td style="text-align: right;">
                                            <form method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este registro de asistencia? Esta acción quedará registrada.');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_asistencia">
                                                <input type="hidden" name="asist_id" value="<?= $as['id'] ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.75rem; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Configuración General de Horario según imagen -->
                <div style="background: #f8fafc; padding: 2rem; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start;">
                    <form method="POST" style="flex: 1; min-width: 300px;">
                        <input type="hidden" name="action" value="update_profesor">
                        <input type="hidden" name="redirect_tab" value="asistencia">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="color: #1e40af; font-weight: 700;">Horario:</label>
                            <input type="text" name="horario_general" class="prof-form-input" value="<?= htmlspecialchars($prof['horario_general'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="color: #1e40af; font-weight: 700;">Observaciones Generales:</label>
                            <textarea name="obs_asistencia" class="prof-form-input" style="height: 100px; resize: none;"><?= htmlspecialchars($prof['obs_asistencia'] ?? '') ?></textarea>
                        </div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-weight: 600;">ACTUALIZAR DATOS GENERALES</button>
                        </div>
                    </form>

                    <div style="width: 250px; background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <label class="data-label" style="color: #1e40af; margin-bottom: 1rem;">Vac. días pendientes:</label>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profesor">
                            <input type="hidden" name="redirect_tab" value="asistencia">
                            <input type="number" name="vac_dias_pendientes" class="prof-form-input" style="font-size: 2rem; text-align: center; font-weight: 700; color: var(--primary-color); border: 2px solid #fecaca; margin-bottom: 1rem;" value="<?= $prof['vac_dias_pendientes'] ?? 0 ?>">
                            <button type="submit" class="btn" style="width: 100%; font-size: 0.8rem;">Actualizar Saldo</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php 
            $mock_tabs = [];
            foreach($mock_tabs as $mt): ?>
            <!-- TAB: Datos de la Cuenta (REDISEÑADA SEGÚN IMAGEN) -->
            <div id="tab-cuenta" class="tab-content" style="<?= $active_tab == 'cuenta' ? '' : 'display:none;' ?>">
                <div style="background: #fdf2f2; padding: 2rem; border-radius: 12px; border: 1px solid #fecaca;">
                    <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.25rem; margin-bottom: 2rem; border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; text-align: center;">
                        DATOS DE LA CUENTA
                    </h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profesor">
                        <!-- Preservar otros campos ya cargados -->
                        <input type="hidden" name="titulacion" value="<?= htmlspecialchars($prof['titulacion'] ?? '') ?>">
                        <input type="hidden" name="es_tutor" value="<?= $prof['es_tutor'] ?? 0 ?>">
                        <input type="hidden" name="es_teleformador" value="<?= $prof['es_teleformador'] ?? 0 ?>">
                        <input type="hidden" name="es_presencial" value="<?= $prof['es_presencial'] ?? 0 ?>">
                        <input type="hidden" name="hace_seguimiento" value="<?= $prof['hace_seguimiento'] ?? 0 ?>">
                        <input type="hidden" name="tope_alumnos_turno" value="<?= $prof['tope_alumnos_turno'] ?? 0 ?>">
                        <input type="hidden" name="id_plataforma" value="<?= $prof['id_plataforma'] ?? '' ?>">
                        <input type="hidden" name="tramo1_de" value="<?= $prof['tramo1_de'] ?? '' ?>">
                        <input type="hidden" name="tramo1_a" value="<?= $prof['tramo1_a'] ?? '' ?>">
                        <!-- ... (se podrían añadir todos los ocultos necesarios para no perder datos si no se envían todos) -->

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af;">Usuario:</label>
                                <input type="text" name="moodle_username" class="prof-form-input" readonly value="<?= htmlspecialchars(strtolower(explode('@', $alumno['email'])[0])) ?>" style="background: #f1f5f9;">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af;">Clave:</label>
                                <input type="password" name="new_password" class="prof-form-input" placeholder="(dejar en blanco para conservar)">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af;">Centro:</label>
                                <select name="centro" class="prof-form-input">
                                    <option value="">Seleccionar Centro...</option>
                                    <?php 
                                    $centros = ["Madrid - Francisco Silvela", "Granada", "Almería", "Barcelona", "Valladolid", "Vícar", "Madrid-Fray Ceferino González"];
                                    foreach($centros as $c): ?>
                                        <option value="<?= $c ?>" <?= ($prof['centro'] ?? '') == $c ? 'selected' : '' ?>><?= $c ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color: #1e40af;">Código profesor:</label>
                                <input type="text" name="codigo_prof" class="prof-form-input" value="<?= $prof['id'] ?? '0' ?>" readonly style="background: #f1f5f9;">
                            </div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 2rem; margin-top: 2rem; padding: 1rem; background: rgba(255,255,255,0.5); border-radius: 8px;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" id="activo" name="activo" checked disabled>
                                <label for="activo" style="font-weight: 600; font-size: 0.9rem;">Activo</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" id="multicentro" name="multicentro">
                                <label for="multicentro" style="font-weight: 600; font-size: 0.9rem;">Acceso multicentro</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" id="comercial" name="comercial">
                                <label for="comercial" style="font-weight: 600; font-size: 0.9rem;">Comercial</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" id="mensajeria" name="mensajeria" checked>
                                <label for="mensajeria" style="font-weight: 600; font-size: 0.9rem;">Mensajería interna</label>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: center; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 3rem; font-weight: 700;">
                                ACTUALIZAR CUENTA
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </main>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById('tab-' + tabId).style.display = 'block';
    
    const btns = document.querySelectorAll('.tab-btn');
    btns.forEach(b => {
        if(b.getAttribute('onclick').includes(tabId)) b.classList.add('active');
    });

    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
}

function showUploadModal() {
    document.getElementById('uploadModal').style.display = 'block';
}

function hideUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    let modal = document.getElementById('uploadModal');
    if (event.target == modal) {
        hideUploadModal();
    }
}
</script>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

// CP Lookup Functionality
const cpInput = document.querySelector('input[name="cp"]');
const localityInput = document.querySelector('input[name="localidad"]');
const provinceSelect = document.getElementById('provincia');

if (cpInput) {
    cpInput.addEventListener('input', function(e) {
        const cp = e.target.value.trim();
        if (cp.length === 5) {
            // 1. Mapeo inmediato de provincia por los 2 primeros dígitos
            const cpPrefix = cp.substring(0, 2);
            const provinceMap = {
                "01": "ÁLAVA", "02": "ALBACETE", "03": "ALICANTE", "04": "ALMERÍA", "05": "ÁVILA", 
                "06": "BADAJOZ", "07": "BALEARES", "08": "BARCELONA", "09": "BURGOS", "10": "CÁCERES", 
                "11": "CÁDIZ", "12": "CASTELLÓN", "13": "CIUDAD REAL", "14": "CÓRDOBA", "15": "CORUÑA (LA)", 
                "16": "CUENCA", "17": "GERONA", "18": "GRANADA", "19": "GUADALAJARA", "20": "GUIPÚZCOA", 
                "21": "HUELVA", "22": "HUESCA", "23": "JAÉN", "24": "LEÓN", "25": "LÉRIDA", 
                "26": "LA RIOJA", "27": "LUGO", "28": "MADRID", "29": "MÁLAGA", "30": "MURCIA", 
                "31": "NAVARRA", "32": "ORENSE", "33": "ASTURIAS", "34": "PALENCIA", "35": "LAS PALMAS", 
                "36": "PONTEVEDRA", "37": "SALAMANCA", "38": "SANTA CRUZ DE TENERIFE", "39": "CANTABRIA", 
                "40": "SEGOVIA", "41": "SEVILLA", "42": "SORIA", "43": "TARRAGONA", "44": "TERUEL", 
                "45": "TOLEDO", "46": "VALENCIA", "47": "VALLADOLID", "48": "VIZCAYA", "49": "ZAMORA", 
                "50": "ZARAGOZA", "51": "CEUTA", "52": "MELILLA"
            };
            
            const provinceName = provinceMap[cpPrefix];
            if (provinceName && provinceSelect) {
                provinceSelect.value = provinceName;
                console.log("Provincia autocompletada:", provinceName);
            }

            // 2. Fetch de localidad desde API externa
            fetch(`https://api.zippopotam.us/es/${cp}`)
                .then(response => {
                    if (!response.ok) throw new Error("CP no encontrado");
                    return response.json();
                })
                .then(data => {
                    if (data.places && data.places.length > 0 && localityInput) {
                        const city = data.places[0]['place name'].toUpperCase();
                        localityInput.value = city;
                        console.log("Localidad autocompletada:", city);
                    }
                })
                .catch(err => {
                    console.log("Aviso: No se pudo obtener la localidad de la API para el CP:", cp);
                });
        }
    });
}
window.onload = function() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if(tab) switchTab(tab);

    // Limpiar parámetros de éxito de la URL tras mostrar el mensaje
    if (params.has('success') || params.has('moodle_ok') || params.has('upload_success')) {
        const url = new URL(window.location);
        url.searchParams.delete('success');
        url.searchParams.delete('upload_success');
        url.searchParams.delete('moodle_ok');
        window.history.replaceState({}, '', url);
    }
}
</script>

</body>
</html>
