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
    SELECT m.*, c.nombre as convocatoria_nombre, c.codigo_expediente,
           p.nombre as plan_nombre, e.nombre as empresa_nombre,
           g.numero_grupo, g.codigo_plataforma as grupo_cod, g.fecha_inicio as grupo_inicio, g.fecha_fin as grupo_fin, g.horas,
           af.abreviatura as af_abreviatura, af.prioridad as af_prioridad, cu.nombre_corto as curso_nombre,
           u_tutor.nombre as tutor_nombre, u_tutor.apellidos as tutor_apellidos,
           COALESCE(af.modalidad, g.modalidad) as modalidad_real
    FROM matriculas m
    LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
    LEFT JOIN planes p ON c.id = p.convocatoria_id
    LEFT JOIN grupos g ON m.grupo_id = g.id
    LEFT JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN cursos cu ON af.curso_id = cu.id
    LEFT JOIN usuarios u_tutor ON g.tutor_id = u_tutor.id
    LEFT JOIN alumnos a ON m.alumno_id = a.id
    LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
    WHERE m.alumno_id = ?
    ORDER BY m.creado_en DESC
");
$stmtMatriculas->execute([$id]);
$matriculas = $stmtMatriculas->fetchAll();

// Cargar todas las convocatorias para el select de agregar inscripción
$convocatorias = $pdo->query("SELECT id, nombre, codigo_expediente FROM convocatorias ORDER BY nombre ASC")->fetchAll();
$comerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll();
$empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 100")->fetchAll();
$provincias = ["Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"];

$active_tab = $_GET['tab'] ?? 'personales';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die("Error: Token CSRF no válido o expirado.");
    }
}

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

// Acción: Eliminar Alumno (a la Papelera)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_alumno') {
    try {
        $stmtAl = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
        $stmtAl->execute([$id]);
        $alumno_data = $stmtAl->fetch(PDO::FETCH_ASSOC);

        if ($alumno_data) {
            // Obtener matrículas asociadas para archivarlas
            $stmtMat = $pdo->prepare("SELECT * FROM matriculas WHERE alumno_id = ?");
            $stmtMat->execute([$id]);
            $matriculas_data = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

            // Obtener documentos asociados
            $stmtDocs = $pdo->prepare("SELECT * FROM documentos_alumno WHERE alumno_id = ?");
            $stmtDocs->execute([$id]);
            $docs_data = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

            $datos_archivados = [
                'alumnos' => $alumno_data,
                'matriculas' => $matriculas_data,
                'documentos_alumno' => $docs_data
            ];

            require_once 'includes/Papelera.php';
            $titulo_papelera = trim($alumno_data['nombre'] . ' ' . ($alumno_data['primer_apellido'] ?? '') . ' ' . ($alumno_data['segundo_apellido'] ?? '')) . " (DNI: " . $alumno_data['dni'] . ")";
            
            $pdo->beginTransaction();
            try {
                Papelera::archivar($pdo, 'alumnos', $id, $titulo_papelera, $datos_archivados);
                
                // Borrar matrículas, documentos y el alumno
                $pdo->prepare("DELETE FROM matriculas WHERE alumno_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM documentos_alumno WHERE alumno_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM alumnos WHERE id = ?")->execute([$id]);
                
                $pdo->commit();
                
                header("Location: alumnos.php?deleted=1");
                exit();
            } catch (Exception $transactionEx) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $transactionEx;
            }
        } else {
            throw new Exception("Alumno no encontrado.");
        }
    } catch (Exception $e) {
        $error = "Error al borrar alumno: " . $e->getMessage();
    }
}

// Acción: Actualizar Datos Personales
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_personales') {
    try {
        $fields = [
            'nombre', 'primer_apellido', 'segundo_apellido', 'dni', 'comercial_id',
            'bloqueado', 'restringido', 'baja', 'alias', 'fecha_nacimiento',
            'seguridad_social', 'profesion', 'sexo', 'estudios', 'tipo_via',
            'nombre_via', 'tipo_num', 'num_domicilio', 'calificador', 'bloque',
            'portal', 'escalera', 'planta', 'puerta', 'complemento', 'domicilio',
            'cp', 'localidad', 'provincia', 'telefono', 'telefono_empresa',
            'mananas_desde', 'mananas_hasta', 'tardes_desde', 'tardes_hasta', 'solo_los',
            'email', 'email_2', 'email_personal', 'cuenta_bancaria', 'teams', 'nacionalidad',
            'activo_hasta', 'es_nuestro', 'ultima_empresa_id', 'centro_trabajo', 'enviar_emails',
            'plat_usuario', 'plat_clave', 'id_plat_2015', 'id_plat_2016', 'pref_presencial',
            'modulacion', 'horarios', 'observaciones', 'entrega_atencion', 'entrega_domicilio',
            'entrega_cp', 'entrega_localidad', 'entrega_provincia'
        ];
        
        $set = [];
        $params = [];
        foreach($fields as $f) {
            $set[] = "$f = ?";
            
            // Checkboxes
            if (in_array($f, ['bloqueado', 'restringido', 'baja', 'enviar_emails', 'es_nuestro'])) {
                $val = isset($_POST[$f]) ? 1 : 0;
            } else {
                $val = isset($_POST[$f]) ? trim($_POST[$f]) : null;
                if ($val === '') {
                    $val = null;
                }
            }
            $params[] = $val;
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
        
        .form-section { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .form-section:last-child { border-bottom: none; }
        .field-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; align-items: center; }
        .field-group { display: flex; align-items: center; gap: 5px; }
        .field-group label { font-weight: 700; color: #1e40af; white-space: nowrap; font-size: 0.75rem; }
        .field-group input, .field-group select, .field-group textarea { font-size: 0.8rem; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 6px; background-color: #fff; }
        .label-red { color: #b91c1c !important; font-weight: 800 !important; }
        .checkbox-group { display: flex; align-items: center; gap: 4px; font-weight: 700; color: #b91c1c; font-size: 0.75rem; }
        .section-header { font-weight: 800; color: #1e40af; text-transform: uppercase; margin-bottom: 15px; font-size: 0.8rem; border-left: 3px solid #1e40af; padding-left: 8px; }
        .w-60 { width: 60px; } .w-100 { width: 100px; } .w-150 { width: 150px; } .w-200 { width: 200px; } .w-250 { width: 250px; } .w-300 { width: 300px; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="header-premium" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem;">
            <div>
                <a href="alumnos.php" class="btn-back" style="text-decoration:none; color: var(--primary-color); font-weight:700;">← Volver al listado</a>
                <h1 style="margin-top: 0.5rem; margin-bottom:0.25rem;"><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?></h1>
                <p style="margin:0; color:#64748b; font-weight:500;">DNI/NIE: <strong><?= htmlspecialchars($alumno['dni']) ?></strong> | Moodle ID: <strong><?= $alumno['moodle_user_id'] ?: 'No sincronizado' ?></strong></p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="moodle_update">
                    <button type="submit" class="btn btn-primary" style="background: #0284c7; color:white; border:none; padding: 10px 20px; border-radius:8px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        🔄 Sincronizar Moodle
                    </button>
                </form>
                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar permanentemente a este alumno? Se archivará en la Papelera con todos sus documentos e inscripciones asociadas.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="delete_alumno">
                    <button type="submit" style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                        🗑️ Eliminar Alumno
                    </button>
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
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="update_personales">
                    
                    <!-- SECCIÓN 1: DATOS PERSONALES -->
                    <div class="form-section" style="padding-top: 0;">
                        <div class="section-header">Datos Personales y de Control</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label class="label-red">NOMBRE *</label>
                                <input type="text" name="nombre" class="w-150" value="<?= htmlspecialchars($alumno['nombre'] ?? '') ?>" required>
                            </div>
                            <div class="field-group">
                                <label class="label-red">1º APELLIDO *</label>
                                <input type="text" name="primer_apellido" class="w-150" value="<?= htmlspecialchars($alumno['primer_apellido'] ?? '') ?>" required>
                            </div>
                            <div class="field-group">
                                <label>2º APELLIDO</label>
                                <input type="text" name="segundo_apellido" class="w-150" value="<?= htmlspecialchars($alumno['segundo_apellido'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label class="label-red">NIF/NIE *</label>
                                <input type="text" name="dni" class="w-100" value="<?= htmlspecialchars($alumno['dni'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-group">
                                <label>COMERCIAL</label>
                                <select name="comercial_id" class="w-150">
                                    <option value="">---</option>
                                    <?php foreach ($comerciales as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($alumno['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="checkbox-group" style="margin-left: 20px;">
                                <input type="checkbox" name="bloqueado" id="bloqueado" <?= ($alumno['bloqueado'] ?? 0) ? 'checked' : '' ?>>
                                <label for="bloqueado">BLOQUEADO</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="restringido" id="restringido" <?= ($alumno['restringido'] ?? 0) ? 'checked' : '' ?>>
                                <label for="restringido">RESTRINGIDO</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="baja" id="baja" <?= ($alumno['baja'] ?? 0) ? 'checked' : '' ?>>
                                <label for="baja">BAJA</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="es_nuestro" id="es_nuestro" <?= ($alumno['es_nuestro'] ?? 0) ? 'checked' : '' ?>>
                                <label for="es_nuestro">ES NUESTRO</label>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-group">
                                <label>ALIAS</label>
                                <input type="text" name="alias" class="w-150" value="<?= htmlspecialchars($alumno['alias'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>F. NACIMIENTO</label>
                                <input type="date" name="fecha_nacimiento" style="padding: 4px 6px;" value="<?= htmlspecialchars($alumno['fecha_nacimiento'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>Nº S. SOCIAL</label>
                                <input type="text" name="seguridad_social" class="w-100" value="<?= htmlspecialchars($alumno['seguridad_social'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>PROFESIÓN</label>
                                <input type="text" name="profesion" class="w-150" value="<?= htmlspecialchars($alumno['profesion'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>SEXO</label>
                                <select name="sexo">
                                    <option <?= ($alumno['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                                    <option <?= ($alumno['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>ESTUDIOS</label>
                                <select name="estudios">
                                    <option value="">---</option>
                                    <?php 
                                    $opcionesEstudios = ["Sin estudios", "Primaria", "ESO/EGB", "Bachillerato", "FP Grado Medio", "FP Grado Superior", "Universidad"];
                                    foreach ($opcionesEstudios as $est):
                                    ?>
                                        <option <?= ($alumno['estudios'] ?? '') == $est ? 'selected' : '' ?>><?= $est ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: DIRECCIÓN Y CONTACTO -->
                    <div class="form-section">
                        <div class="section-header">Domicilio y Contacto</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label>TIPO VÍA</label>
                                <select name="tipo_via" class="w-100">
                                    <?php 
                                    $vias = ["Calle", "Avenida", "Plaza", "Carretera", "Paseo"];
                                    foreach($vias as $v):
                                    ?>
                                        <option <?= ($alumno['tipo_via'] ?? '') == $v ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>NOMBRE VÍA</label>
                                <input type="text" name="nombre_via" class="w-250" value="<?= htmlspecialchars($alumno['nombre_via'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>TIPO Nº</label>
                                <select name="tipo_num">
                                    <option <?= ($alumno['tipo_num'] ?? '') == 'Número' ? 'selected' : '' ?>>Número</option>
                                    <option <?= ($alumno['tipo_num'] ?? '') == 'Kilómetro' ? 'selected' : '' ?>>Kilómetro</option>
                                    <option <?= ($alumno['tipo_num'] ?? '') == 'Sin Número' ? 'selected' : '' ?>>Sin Número</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Nº</label>
                                <input type="text" name="num_domicilio" class="w-60" value="<?= htmlspecialchars($alumno['num_domicilio'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>CALIFICADOR</label>
                                <select name="calificador">
                                    <option value=""></option>
                                    <option <?= ($alumno['calificador'] ?? '') == 'Bis' ? 'selected' : '' ?>>Bis</option>
                                    <option <?= ($alumno['calificador'] ?? '') == 'Duplicado' ? 'selected' : '' ?>>Duplicado</option>
                                    <option <?= ($alumno['calificador'] ?? '') == 'Moderno' ? 'selected' : '' ?>>Moderno</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-group"><label>BLOQUE</label><input type="text" name="bloque" class="w-60" value="<?= htmlspecialchars($alumno['bloque'] ?? '') ?>"></div>
                            <div class="field-group"><label>PORTAL</label><input type="text" name="portal" class="w-60" value="<?= htmlspecialchars($alumno['portal'] ?? '') ?>"></div>
                            <div class="field-group"><label>ESCALERA</label><input type="text" name="escalera" class="w-60" value="<?= htmlspecialchars($alumno['escalera'] ?? '') ?>"></div>
                            <div class="field-group"><label>PLANTA</label><input type="text" name="planta" class="w-60" value="<?= htmlspecialchars($alumno['planta'] ?? '') ?>"></div>
                            <div class="field-group"><label>PUERTA</label><input type="text" name="puerta" class="w-60" value="<?= htmlspecialchars($alumno['puerta'] ?? '') ?>"></div>
                            <div class="field-group"><label>COMPLEMENTO</label><input type="text" name="complemento" class="w-150" value="<?= htmlspecialchars($alumno['complemento'] ?? '') ?>"></div>
                        </div>
                        
                        <input type="hidden" name="domicilio_full" id="domicilio_full">

                        <div class="field-row" style="margin-top: 10px;">
                            <div class="field-group"><label>CP</label><input type="text" name="cp" class="w-60" value="<?= htmlspecialchars($alumno['cp'] ?? '') ?>"></div>
                            <div class="field-group"><label>LOCALIDAD</label><input type="text" name="localidad" class="w-150" value="<?= htmlspecialchars($alumno['localidad'] ?? '') ?>"></div>
                            <div class="field-group">
                                <label>PROVINCIA</label>
                                <select name="provincia" class="w-150">
                                    <option value="">---</option>
                                    <?php foreach ($provincias as $p): ?>
                                        <option value="<?= $p ?>" <?= ($alumno['provincia'] ?? '') == $p ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field-row" style="margin-top: 15px;">
                            <div class="field-group">
                                <label>TELÉFONO</label>
                                <input type="text" name="telefono" class="w-100" value="<?= htmlspecialchars($alumno['telefono'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>MÓVIL / EMPRESA</label>
                                <input type="text" name="telefono_empresa" class="w-100" value="<?= htmlspecialchars($alumno['telefono_empresa'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label class="label-red">EMAIL PRINCIPAL *</label>
                                <input type="email" name="email" class="w-200" value="<?= htmlspecialchars($alumno['email'] ?? '') ?>" required>
                            </div>
                            <div class="field-group">
                                <label>EMAIL SECUNDARIO</label>
                                <input type="email" name="email_2" class="w-200" value="<?= htmlspecialchars($alumno['email_2'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-group">
                                <label>EMAIL PERSONAL</label>
                                <input type="email" name="email_personal" class="w-200" value="<?= htmlspecialchars($alumno['email_personal'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>TEAMS</label>
                                <input type="text" name="teams" class="w-150" value="<?= htmlspecialchars($alumno['teams'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>NACIONALIDAD</label>
                                <input type="text" name="nacionalidad" class="w-150" value="<?= htmlspecialchars($alumno['nacionalidad'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>ACTIVO HASTA</label>
                                <input type="date" name="activo_hasta" style="padding: 4px 6px;" value="<?= htmlspecialchars($alumno['activo_hasta'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: PLATAFORMA MOODLE -->
                    <div class="form-section" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <div class="section-header" style="color: #0369a1; border-left-color: #0369a1;">Configuración Moodle (Intranet)</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label>USUARIO PLAT.</label>
                                <input type="text" name="plat_usuario" class="w-150" value="<?= htmlspecialchars($alumno['plat_usuario'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>CLAVE PLAT.</label>
                                <input type="text" name="plat_clave" class="w-150" value="<?= htmlspecialchars($alumno['plat_clave'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>ID PLAT. 2015</label>
                                <input type="text" name="id_plat_2015" class="w-100" value="<?= htmlspecialchars($alumno['id_plat_2015'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>ID PLAT. 2016</label>
                                <input type="text" name="id_plat_2016" class="w-100" value="<?= htmlspecialchars($alumno['id_plat_2016'] ?? '') ?>">
                            </div>
                            <div class="checkbox-group" style="margin-left: 20px; color: #0369a1;">
                                <input type="checkbox" name="enviar_emails" id="enviar_emails" <?= ($alumno['enviar_emails'] ?? 1) ? 'checked' : '' ?>>
                                <label for="enviar_emails">NOTIFICAR POR EMAIL</label>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 4: INFORMACIÓN LABORAL Y ACADÉMICA -->
                    <div class="form-section">
                        <div class="section-header">Información Académica y Laboral</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label>CUENTA BANCARIA</label>
                                <input type="text" name="cuenta_bancaria" class="w-250" value="<?= htmlspecialchars($alumno['cuenta_bancaria'] ?? '') ?>" placeholder="ES00 0000 0000 0000 0000 0000">
                            </div>
                            <div class="field-group">
                                <label>ÚLTIMA EMPRESA</label>
                                <select name="ultima_empresa_id" class="w-200">
                                    <option value="">---</option>
                                    <?php foreach ($empresas as $e): ?>
                                        <option value="<?= $e['id'] ?>" <?= ($alumno['ultima_empresa_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>CENTRO TRABAJO</label>
                                <input type="text" name="centro_trabajo" class="w-200" value="<?= htmlspecialchars($alumno['centro_trabajo'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-group">
                                <label>PREF. PRESENCIAL</label>
                                <input type="text" name="pref_presencial" class="w-150" value="<?= htmlspecialchars($alumno['pref_presencial'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>MODULACIÓN</label>
                                <input type="text" name="modulacion" class="w-150" value="<?= htmlspecialchars($alumno['modulacion'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>HORARIOS DISP.</label>
                                <input type="text" name="horarios" class="w-200" value="<?= htmlspecialchars($alumno['horarios'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-group"><label>MAÑANAS DE</label><input type="text" name="mananas_desde" class="w-60" value="<?= htmlspecialchars($alumno['mananas_desde'] ?? '') ?>"></div>
                            <div class="field-group"><label>HASTA</label><input type="text" name="mananas_hasta" class="w-60" value="<?= htmlspecialchars($alumno['mananas_hasta'] ?? '') ?>"></div>
                            <div class="field-group" style="margin-left: 20px;"><label>TARDES DE</label><input type="text" name="tardes_desde" class="w-60" value="<?= htmlspecialchars($alumno['tardes_desde'] ?? '') ?>"></div>
                            <div class="field-group"><label>HASTA</label><input type="text" name="tardes_hasta" class="w-60" value="<?= htmlspecialchars($alumno['tardes_hasta'] ?? '') ?>"></div>
                            <div class="field-group" style="margin-left: 20px;"><label>SOLO LOS</label><input type="text" name="solo_los" class="w-150" value="<?= htmlspecialchars($alumno['solo_los'] ?? '') ?>"></div>
                        </div>

                        <div class="field-row" style="margin-top: 15px;">
                            <div class="field-group">
                                <label>OBSERVACIONES</label>
                                <textarea name="observaciones" rows="3" style="width: 600px; border: 1px solid var(--border-color); border-radius: 6px; padding: 6px 10px;"><?= htmlspecialchars($alumno['observaciones'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 5: DIRECCIÓN DE ENTREGA -->
                    <div class="form-section" style="background: #faf5ff; border: 1px solid #f3e8ff; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <div class="section-header" style="color: #6b21a8; border-left-color: #6b21a8;">Dirección de Entrega de Material</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label>ATENCIÓN A</label>
                                <input type="text" name="entrega_atencion" class="w-200" value="<?= htmlspecialchars($alumno['entrega_atencion'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>DOMICILIO</label>
                                <input type="text" name="entrega_domicilio" class="w-300" value="<?= htmlspecialchars($alumno['entrega_domicilio'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>CP</label><input type="text" name="entrega_cp" class="w-60" value="<?= htmlspecialchars($alumno['entrega_cp'] ?? '') ?>"></div>
                            <div class="field-group"><label>LOCALIDAD</label><input type="text" name="entrega_localidad" class="w-150" value="<?= htmlspecialchars($alumno['entrega_localidad'] ?? '') ?>"></div>
                            <div class="field-group">
                                <label>PROVINCIA</label>
                                <select name="entrega_provincia" class="w-150">
                                    <option value="">---</option>
                                    <?php foreach ($provincias as $p): ?>
                                        <option value="<?= $p ?>" <?= ($alumno['entrega_provincia'] ?? '') == $p ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-weight: 700; font-size: 0.9rem;">💾 Guardar Todos los Cambios</button>
                    </div>
                </form>
            </div>

            <!-- TAB: Inscripciones -->
            <div id="tab-inscripciones" style="<?= $active_tab == 'inscripciones' ? '' : 'display:none;' ?>">
                
                <div style="text-align: center; margin-bottom: 10px;">
                    <button style="font-size: 0.7rem; padding: 2px 5px; border: 1px solid #999; background: #eee; cursor: pointer;">Guardar registro</button>
                    <h2 style="color: #b91c1c; font-size: 1rem; font-weight: bold; margin: 10px 0;">CURSOS CONTRATOS-PROGRAMA</h2>
                    <div style="color: #1e3a8a; font-size: 0.8rem; font-weight: bold;">
                        Se muestran cursos de todas las convocatorias. <a href="#" style="color: #1e3a8a; text-decoration: underline;">VER SÓLO CURSOS DE CONVOCATORIA ACTUAL</a>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.75rem; text-align: left; background: #fff; min-width: 1000px; font-family: Arial, sans-serif;">
                        <thead>
                            <tr>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Empresa</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Plan</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Nº Acción</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Nº Grupo</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Modalidad</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Horas</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Curso</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Tutor</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Situación</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Inicio</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Fin</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0;"></th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matriculas as $mat): ?>
                                <tr>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['empresa_nombre'] ?? 'DESEMPLEADO') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['plan_nombre'] ?? 'Formacion 2025') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['af_abreviatura'] ?? '2PRL') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['numero_grupo'] ?? '1') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['modalidad_real'] ?? 'T') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['horas'] ?? '30') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['curso_nombre'] ?? $mat['convocatoria_nombre'] ?? '') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars(trim(($mat['tutor_nombre'] ?? '') . ' ' . ($mat['tutor_apellidos'] ?? ''))) ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat['estado'] ?? 'Finalizado') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= !empty($mat['grupo_inicio']) && $mat['grupo_inicio'] != '0000-00-00' ? date('d/m/Y', strtotime($mat['grupo_inicio'])) : '' ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= !empty($mat['grupo_fin']) && $mat['grupo_fin'] != '0000-00-00' ? date('d/m/Y', strtotime($mat['grupo_fin'])) : '' ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; text-align: center;">
                                        <a href="#" style="text-decoration: none;">📝</a>
                                    </td>
                                    <td style="border: 1px solid #999; padding: 4px; text-align: center;">
                                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta inscripción?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <input type="hidden" name="action" value="delete_inscripcion">
                                            <input type="hidden" name="matricula_id" value="<?= $mat['id'] ?>">
                                            <button type="submit" style="background: none; border: none; cursor: pointer; color: #b91c1c; font-weight: bold; padding: 0;">❌</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Añadir nueva inscripcion row -->
                            <tr>
                                <td colspan="13" style="border: 1px solid #999; padding: 4px; text-align: center;">
                                    <a href="#" onclick="document.getElementById('form-nueva-inscripcion').style.display='block'; return false;" style="color: #1e3a8a; text-decoration: none;">Nueva inscripción</a>
                                </td>
                            </tr>
                            
                            <!-- Bonificados Header -->
                            <tr>
                                <td colspan="13" style="border: 1px solid #999; padding: 4px; background: #f0f0f0; text-align: center; color: #b91c1c; font-weight: bold;">CURSOS BONIFICADOS</td>
                            </tr>
                            <!-- Bonificados Empty row -->
                            <tr>
                                <td colspan="13" style="border: 1px solid #999; padding: 4px; text-align: center; color: #1e3a8a; font-weight: bold;">No hay inscripciones bonificadas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Formulario Añadir Inscripcion (Oculto inicialmente) -->
                <div id="form-nueva-inscripcion" style="display: none; margin-top: 20px; border: 1px solid #999; padding: 15px; background: #f8fafc;">
                    <h3 style="margin-top: 0; color: #1e3a8a; font-family: Arial, sans-serif; font-size: 1rem;">Añadir Inscripción</h3>
                    <form method="POST" style="font-family: Arial, sans-serif;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="add_inscripcion">
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; color: #1e3a8a; margin-bottom: 0.4rem;">Convocatoria / Curso *</label>
                            <select name="convocatoria_id" required style="width: 100%; max-width: 400px; padding: 0.4rem; border: 1px solid #999;">
                                <option value="">-- Seleccionar Convocatoria --</option>
                                <?php foreach ($convocatorias as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars(($c['codigo_expediente'] ? '['.$c['codigo_expediente'].'] ' : '') . $c['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; color: #1e3a8a; margin-bottom: 0.4rem;">Estado *</label>
                            <select name="estado" required style="width: 100%; max-width: 200px; padding: 0.4rem; border: 1px solid #999;">
                                <option value="Inscrito" selected>Inscrito</option>
                                <option value="Activo">Activo</option>
                                <option value="Finalizada">Finalizada</option>
                                <option value="Baja">Baja</option>
                                <option value="Cancelada">Cancelada</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; color: #1e3a8a; margin-bottom: 0.4rem;">Fecha de Matrícula</label>
                            <input type="date" name="fecha_matricula" value="<?= date('Y-m-d') ?>" style="width: 100%; max-width: 150px; padding: 0.4rem; border: 1px solid #999;">
                        </div>
                        
                        <button type="submit" style="padding: 5px 15px; border: 1px solid #999; background: #eee; cursor: pointer; color: #1e3a8a; font-weight: bold;">
                            Registrar Inscripción
                        </button>
                        <button type="button" onclick="document.getElementById('form-nueva-inscripcion').style.display='none';" style="padding: 5px 15px; border: 1px solid #999; background: #eee; cursor: pointer; color: #b91c1c; font-weight: bold; margin-left: 10px;">
                            Cancelar
                        </button>
                    </form>
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
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
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

<script>
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function() {
            const via = document.querySelector('[name="tipo_via"]').value;
            const nombre = document.querySelector('[name="nombre_via"]').value;
            const num = document.querySelector('[name="num_domicilio"]').value;
            document.getElementById('domicilio_full').value = via + ' ' + nombre + ', ' + num;
        });
    }
</script>
</body>
</html>
