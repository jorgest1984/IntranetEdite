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

// Migración automática: Añadir columnas si no existen
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `documentos_alumno` LIKE 'accion_id'")->fetch();
    if (!$checkColumn) {
        $pdo->exec("ALTER TABLE `documentos_alumno` ADD COLUMN `accion_id` INT(11) DEFAULT NULL AFTER `usuario_id`");
    }
    $checkColumnFoto = $pdo->query("SHOW COLUMNS FROM `alumnos` LIKE 'foto'")->fetch();
    if (!$checkColumnFoto) {
        $pdo->exec("ALTER TABLE `alumnos` ADD COLUMN `foto` VARCHAR(255) DEFAULT NULL AFTER `teams`");
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
    GROUP BY m.id
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
        
        // Sincronizar foto de perfil con Moodle si existe localmente
        if (!empty($alumno['foto']) && file_exists(__DIR__ . '/' . $alumno['foto'])) {
            try {
                $moodle->updateUserPicture($muid, __DIR__ . '/' . $alumno['foto']);
            } catch (Exception $photoEx) {
                // Omitir fallos de foto para no bloquear el flujo principal de Moodle
            }
        }
        
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
        
        // Procesar subida de foto de perfil
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, $allowed)) {
                $upload_dir = 'uploads/alumnos/' . $id . '/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                // Borrar foto anterior si existe
                if (!empty($alumno['foto']) && file_exists(__DIR__ . '/' . $alumno['foto'])) {
                    @unlink(__DIR__ . '/' . $alumno['foto']);
                }
                $new_avatar_path = $upload_dir . 'avatar_' . time() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/' . $new_avatar_path)) {
                    $pdo->prepare("UPDATE alumnos SET foto = ? WHERE id = ?")->execute([$new_avatar_path, $id]);
                    $alumno['foto'] = $new_avatar_path; // Actualizar en memoria
                    
                    // Sincronizar automáticamente con Moodle si ya tiene cuenta
                    if (!empty($alumno['moodle_user_id'])) {
                        try {
                            $moodle->updateUserPicture($alumno['moodle_user_id'], __DIR__ . '/' . $new_avatar_path);
                        } catch (Exception $photoEx) {
                            // Ignorar errores silenciosamente para no interrumpir el guardado local
                        }
                    }
                }
            }
        }

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* Tabs Premium Navigation */
        .tabs-header {
            display: flex;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 14px 14px 0 0;
            padding: 0.5rem 0.5rem 0 0.5rem;
            gap: 4px;
            overflow-x: auto;
        }
        .tab-btn {
            padding: 0.8rem 1.6rem;
            border: none;
            background: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            border-radius: 10px 10px 0 0;
            transition: all 0.3s ease;
            position: relative;
        }
        .tab-btn:hover {
            color: var(--primary-color);
            background: rgba(30, 64, 175, 0.05);
        }
        .tab-btn.active {
            background: var(--card-bg);
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            border-bottom: 2px solid var(--card-bg);
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.03);
            margin-bottom: -1px;
            z-index: 10;
        }
        .tab-panel {
            background: var(--card-bg);
            padding: 2.2rem;
            border-radius: 0 0 16px 16px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            min-height: 450px;
            margin-bottom: 2rem;
        }
        
        /* Grid Form Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1rem 1.25rem;
        }
        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .form-group-custom label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }
        .form-group-custom label.label-red {
            color: #ef4444 !important;
        }
        .form-control-edit {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.82rem;
            background: var(--input-bg);
            color: var(--text-color);
            outline: none;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
            height: 38px;
        }
        .form-control-edit:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15);
            background: #fff;
        }
        textarea.form-control-edit {
            height: auto;
            min-height: 80px;
            resize: vertical;
        }
        
        /* Columns sizing */
        .span-1 { grid-column: span 1; }
        .span-2 { grid-column: span 2; }
        .span-3 { grid-column: span 3; }
        .span-4 { grid-column: span 4; }
        .span-5 { grid-column: span 5; }
        .span-6 { grid-column: span 6; }
        .span-7 { grid-column: span 7; }
        .span-8 { grid-column: span 8; }
        .span-9 { grid-column: span 9; }
        .span-10 { grid-column: span 10; }
        .span-11 { grid-column: span 11; }
        .span-12 { grid-column: span 12; }

        /* Card Section Styles */
        .card-section-premium {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.01);
        }
        .card-section-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            margin-bottom: 1.25rem;
            border-left: 4px solid var(--primary-color);
            padding-left: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-section-moodle {
            background: #f0f9ff;
            border-color: #bae6fd;
        }
        .card-section-moodle .card-section-title {
            color: #0369a1;
            border-left-color: #0369a1;
        }
        .card-section-entrega {
            background: #faf5ff;
            border-color: #f3e8ff;
        }
        .card-section-entrega .card-section-title {
            color: #6b21a8;
            border-left-color: #6b21a8;
        }

        /* Checkbox Layout */
        .checkbox-group-custom {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            height: 38px;
            padding-top: 15px;
            font-weight: 700;
            font-size: 0.75rem;
            color: #ef4444;
        }
        .checkbox-group-custom input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        /* Avatar styles */
        .avatar-wrapper:hover .avatar-upload-overlay {
            opacity: 1 !important;
        }
        .avatar-wrapper:hover {
            transform: scale(1.02);
        }

        /* Table styles */
        .table-premium-dense {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .table-premium-dense th {
            background: #f8fafc;
            padding: 10px 12px;
            font-weight: 700;
            color: #334155;
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }
        .table-premium-dense td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .table-premium-dense tbody tr:hover {
            background: #f8fafc;
        }
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
                <form method="POST" id="editForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="update_personales">
                    
                    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 2rem; align-items: stretch; margin-bottom: 2rem;">
                        <!-- Columna Izquierda: Perfil / Foto -->
                        <div>
                            <!-- Foto de Perfil del Alumno -->
                            <div class="card-section-premium" style="text-align: center; display: flex; flex-direction: column; align-items: center; padding: 2rem 1.5rem; margin-bottom: 0; height: 100%; justify-content: center; box-sizing: border-box;">
                                <div class="avatar-wrapper" style="position: relative; width: 140px; height: 140px; border-radius: 50%; overflow: hidden; border: 4px solid var(--primary-color); background: #f8fafc; cursor: pointer; box-shadow: 0 8px 24px rgba(0,0,0,0.06); transition: transform 0.3s ease;">
                                    <div id="avatar-preview" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                        <?php 
                                        $foto_path = $alumno['foto'] ?? '';
                                        if (!empty($foto_path) && file_exists(__DIR__ . '/' . $foto_path)) {
                                            echo '<img src="' . htmlspecialchars($foto_path) . '" alt="Foto Alumno" style="width: 100%; height: 100%; object-fit: cover;">';
                                        } else {
                                            echo '<i class="fas fa-user-circle" style="font-size: 140px; color: #cbd5e1;"></i>';
                                        }
                                        ?>
                                    </div>
                                    <div class="avatar-upload-overlay" onclick="document.getElementById('foto').click();" style="position: absolute; inset: 0; background: rgba(0, 0, 0, 0.4); display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; opacity: 0; transition: opacity 0.2s ease; font-size: 0.8rem; gap: 5px; backdrop-filter: blur(2px);">
                                        <i class="fas fa-camera" style="font-size: 1.5rem;"></i>
                                        <span>Cambiar Foto</span>
                                    </div>
                                </div>
                                <input type="file" name="foto" id="foto" accept="image/*" style="display: none;" onchange="previewImage(this);">
                                
                                <div style="margin-top: 15px; text-align: center;">
                                    <span style="font-size: 0.72rem; color: #64748b; display: block;">Formatos: JPG, PNG, WEBP</span>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Derecha: Datos Personales -->
                        <div>
                            <!-- SECCIÓN 1: DATOS PERSONALES -->
                            <div class="card-section-premium" style="margin-bottom: 0; height: 100%; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <h3 class="card-section-title" style="margin-bottom: 1.25rem;"><i class="fas fa-id-card"></i> Datos Personales y de Identificación</h3>
                                    <div class="form-grid">
                                        <div class="form-group-custom span-3">
                                            <label class="label-red">Nombre *</label>
                                            <input type="text" name="nombre" class="form-control-edit" value="<?= htmlspecialchars($alumno['nombre'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group-custom span-3">
                                            <label class="label-red">1º Apellido *</label>
                                            <input type="text" name="primer_apellido" class="form-control-edit" value="<?= htmlspecialchars($alumno['primer_apellido'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group-custom span-3">
                                            <label>2º Apellido</label>
                                            <input type="text" name="segundo_apellido" class="form-control-edit" value="<?= htmlspecialchars($alumno['segundo_apellido'] ?? '') ?>">
                                        </div>
                                        <div class="form-group-custom span-3">
                                            <label class="label-red">NIF / NIE *</label>
                                            <input type="text" name="dni" class="form-control-edit" value="<?= htmlspecialchars($alumno['dni'] ?? '') ?>" required>
                                        </div>

                                        <div class="form-group-custom span-4">
                                            <label>Comercial Asignado</label>
                                            <select name="comercial_id" class="form-control-edit">
                                                <option value="">---</option>
                                                <?php foreach ($comerciales as $c): ?>
                                                    <option value="<?= $c['id'] ?>" <?= ($alumno['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group-custom span-4">
                                            <label>Alias / Apodo</label>
                                            <input type="text" name="alias" class="form-control-edit" value="<?= htmlspecialchars($alumno['alias'] ?? '') ?>">
                                        </div>
                                        <div class="form-group-custom span-4">
                                            <label>Fecha Nacimiento</label>
                                            <input type="date" name="fecha_nacimiento" class="form-control-edit" value="<?= htmlspecialchars($alumno['fecha_nacimiento'] ?? '') ?>">
                                        </div>

                                        <div class="form-group-custom span-3">
                                            <label>Nº Seguridad Social</label>
                                            <input type="text" name="seguridad_social" class="form-control-edit" value="<?= htmlspecialchars($alumno['seguridad_social'] ?? '') ?>">
                                        </div>
                                        <div class="form-group-custom span-3">
                                            <label>Profesión</label>
                                            <input type="text" name="profesion" class="form-control-edit" value="<?= htmlspecialchars($alumno['profesion'] ?? '') ?>">
                                        </div>
                                        <div class="form-group-custom span-3">
                                            <label>Sexo</label>
                                            <select name="sexo" class="form-control-edit">
                                                <option value="">---</option>
                                                <option value="Hombre" <?= ($alumno['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                                                <option value="Mujer" <?= ($alumno['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                                                <option value="Otro" <?= ($alumno['sexo'] ?? '') == 'Otro' ? 'selected' : '' ?>>Otro</option>
                                            </select>
                                        </div>
                                        <div class="form-group-custom span-3">
                                            <label>Nivel de Estudios</label>
                                            <select name="estudios" class="form-control-edit">
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

                                <!-- Fila inferior de Checkboxes de Control -->
                                <div style="border-top: 1px solid var(--border-color); padding-top: 1.25rem; margin-top: 1.25rem;">
                                    <div class="form-grid">
                                        <div class="checkbox-group-custom span-3" style="padding-top: 0;">
                                            <input type="checkbox" name="bloqueado" id="bloqueado" <?= ($alumno['bloqueado'] ?? 0) ? 'checked' : '' ?>>
                                            <label for="bloqueado" style="color: #ef4444; cursor: pointer;">🚫 BLOQUEADO</label>
                                        </div>
                                        <div class="checkbox-group-custom span-3" style="padding-top: 0;">
                                            <input type="checkbox" name="restringido" id="restringido" <?= ($alumno['restringido'] ?? 0) ? 'checked' : '' ?>>
                                            <label for="restringido" style="color: #f59e0b; cursor: pointer;">⚠️ RESTRINGIDO</label>
                                        </div>
                                        <div class="checkbox-group-custom span-3" style="padding-top: 0;">
                                            <input type="checkbox" name="baja" id="baja" <?= ($alumno['baja'] ?? 0) ? 'checked' : '' ?>>
                                            <label for="baja" style="color: #64748b; cursor: pointer;">📉 BAJA</label>
                                        </div>
                                        <div class="checkbox-group-custom span-3" style="padding-top: 0; color: var(--primary-color);">
                                            <input type="checkbox" name="es_nuestro" id="es_nuestro" <?= ($alumno['es_nuestro'] ?? 0) ? 'checked' : '' ?>>
                                            <label for="es_nuestro" style="color: var(--primary-color); cursor: pointer;">⭐ ES NUESTRO</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                            <!-- SECCIÓN 2: DIRECCIÓN Y CONTACTO -->
                            <div class="card-section-premium">
                                <h3 class="card-section-title"><i class="fas fa-map-marked-alt"></i> Domicilio y Contacto</h3>
                                <div class="form-grid">
                                    <div class="form-group-custom span-2">
                                        <label>Tipo Vía</label>
                                        <select name="tipo_via" class="form-control-edit">
                                            <option value="">---</option>
                                            <?php 
                                            $vias = ["Calle", "Avenida", "Plaza", "Carretera", "Paseo"];
                                            foreach($vias as $v):
                                            ?>
                                                <option <?= ($alumno['tipo_via'] ?? '') == $v ? 'selected' : '' ?>><?= $v ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group-custom span-5">
                                        <label>Nombre de la Vía</label>
                                        <input type="text" name="nombre_via" class="form-control-edit" value="<?= htmlspecialchars($alumno['nombre_via'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-3">
                                        <label>Tipo Número</label>
                                        <select name="tipo_num" class="form-control-edit">
                                            <option <?= ($alumno['tipo_num'] ?? '') == 'Número' ? 'selected' : '' ?>>Número</option>
                                            <option <?= ($alumno['tipo_num'] ?? '') == 'Kilómetro' ? 'selected' : '' ?>>Kilómetro</option>
                                            <option <?= ($alumno['tipo_num'] ?? '') == 'Sin Número' ? 'selected' : '' ?>>Sin Número</option>
                                        </select>
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Número</label>
                                        <input type="text" name="num_domicilio" class="form-control-edit" value="<?= htmlspecialchars($alumno['num_domicilio'] ?? '') ?>">
                                    </div>

                                    <div class="form-group-custom span-2">
                                        <label>Bloque</label>
                                        <input type="text" name="bloque" class="form-control-edit" value="<?= htmlspecialchars($alumno['bloque'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Portal</label>
                                        <input type="text" name="portal" class="form-control-edit" value="<?= htmlspecialchars($alumno['portal'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Escalera</label>
                                        <input type="text" name="escalera" class="form-control-edit" value="<?= htmlspecialchars($alumno['escalera'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Planta</label>
                                        <input type="text" name="planta" class="form-control-edit" value="<?= htmlspecialchars($alumno['planta'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Puerta</label>
                                        <input type="text" name="puerta" class="form-control-edit" value="<?= htmlspecialchars($alumno['puerta'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Calificador</label>
                                        <select name="calificador" class="form-control-edit">
                                            <option value=""></option>
                                            <option <?= ($alumno['calificador'] ?? '') == 'Bis' ? 'selected' : '' ?>>Bis</option>
                                            <option <?= ($alumno['calificador'] ?? '') == 'Duplicado' ? 'selected' : '' ?>>Duplicado</option>
                                            <option <?= ($alumno['calificador'] ?? '') == 'Moderno' ? 'selected' : '' ?>>Moderno</option>
                                        </select>
                                    </div>

                                    <div class="form-group-custom span-8">
                                        <label>Complemento Dirección</label>
                                        <input type="text" name="complemento" class="form-control-edit" value="<?= htmlspecialchars($alumno['complemento'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-4">
                                        <label>Dirección Corta (Compatibilidad)</label>
                                        <input type="text" name="domicilio" class="form-control-edit" value="<?= htmlspecialchars($alumno['domicilio'] ?? '') ?>">
                                    </div>
                                    
                                    <input type="hidden" name="domicilio_full" id="domicilio_full">

                                    <div class="form-group-custom span-2">
                                        <label>Código Postal</label>
                                        <input type="text" name="cp" class="form-control-edit" value="<?= htmlspecialchars($alumno['cp'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-5">
                                        <label>Localidad</label>
                                        <input type="text" name="localidad" class="form-control-edit" value="<?= htmlspecialchars($alumno['localidad'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-5">
                                        <label>Provincia</label>
                                        <select name="provincia" class="form-control-edit">
                                            <option value="">---</option>
                                            <?php foreach ($provincias as $p): ?>
                                                <option value="<?= $p ?>" <?= ($alumno['provincia'] ?? '') == $p ? 'selected' : '' ?>><?= $p ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group-custom span-3">
                                        <label>Teléfono Principal</label>
                                        <input type="text" name="telefono" class="form-control-edit" value="<?= htmlspecialchars($alumno['telefono'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-3">
                                        <label>Móvil / Trabajo</label>
                                        <input type="text" name="telefono_empresa" class="form-control-edit" value="<?= htmlspecialchars($alumno['telefono_empresa'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-3">
                                        <label class="label-red">Email Académico *</label>
                                        <input type="email" name="email" class="form-control-edit" value="<?= htmlspecialchars($alumno['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group-custom span-3">
                                        <label>Email Secundario</label>
                                        <input type="email" name="email_2" class="form-control-edit" value="<?= htmlspecialchars($alumno['email_2'] ?? '') ?>">
                                    </div>

                                    <div class="form-group-custom span-4">
                                        <label>Email Personal</label>
                                        <input type="email" name="email_personal" class="form-control-edit" value="<?= htmlspecialchars($alumno['email_personal'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-3">
                                        <label>Nacionalidad</label>
                                        <input type="text" name="nacionalidad" class="form-control-edit" value="<?= htmlspecialchars($alumno['nacionalidad'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-3">
                                        <label>Usuario Teams</label>
                                        <input type="text" name="teams" class="form-control-edit" value="<?= htmlspecialchars($alumno['teams'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Activo Hasta</label>
                                        <input type="date" name="activo_hasta" class="form-control-edit" value="<?= htmlspecialchars($alumno['activo_hasta'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 3: CONFIGURACIÓN MOODLE -->
                            <div class="card-section-premium card-section-moodle">
                                <h3 class="card-section-title"><i class="fas fa-graduation-cap"></i> Acceso Plataforma Moodle (Datos Manuales)</h3>
                                <div class="form-grid">
                                    <div class="form-group-custom span-3">
                                        <label>Usuario Moodle</label>
                                        <input type="text" name="plat_usuario" class="form-control-edit" value="<?= htmlspecialchars($alumno['plat_usuario'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-3">
                                        <label>Clave Acceso</label>
                                        <input type="text" name="plat_clave" class="form-control-edit" value="<?= htmlspecialchars($alumno['plat_clave'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>ID Moodle 2015</label>
                                        <input type="text" name="id_plat_2015" class="form-control-edit" value="<?= htmlspecialchars($alumno['id_plat_2015'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>ID Moodle 2016</label>
                                        <input type="text" name="id_plat_2016" class="form-control-edit" value="<?= htmlspecialchars($alumno['id_plat_2016'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <div class="checkbox-group-custom" style="color: #0369a1; padding-top: 15px;">
                                            <input type="checkbox" name="enviar_emails" id="enviar_emails" <?= ($alumno['enviar_emails'] ?? 1) ? 'checked' : '' ?>>
                                            <label for="enviar_emails" style="color: #0369a1; cursor: pointer;">Notificaciones</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 4: INFORMACIÓN LABORAL Y ACADÉMICA -->
                            <div class="card-section-premium">
                                <h3 class="card-section-title"><i class="fas fa-briefcase"></i> Información Laboral y Académica</h3>
                                <div class="form-grid">
                                    <div class="form-group-custom span-4">
                                        <label>IBAN Cuenta Bancaria</label>
                                        <input type="text" name="cuenta_bancaria" class="form-control-edit" value="<?= htmlspecialchars($alumno['cuenta_bancaria'] ?? '') ?>" placeholder="ES00 0000 0000 0000 0000 0000">
                                    </div>
                                    <div class="form-group-custom span-4">
                                        <label>Empresa Actual (Vínculo)</label>
                                        <select name="ultima_empresa_id" class="form-control-edit">
                                            <option value="">---</option>
                                            <?php foreach ($empresas as $e): ?>
                                                <option value="<?= $e['id'] ?>" <?= ($alumno['ultima_empresa_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group-custom span-4">
                                        <label>Centro de Trabajo</label>
                                        <input type="text" name="centro_trabajo" class="form-control-edit" value="<?= htmlspecialchars($alumno['centro_trabajo'] ?? '') ?>">
                                    </div>

                                    <div class="form-group-custom span-4">
                                        <label>Preferencia Presencialidad</label>
                                        <input type="text" name="pref_presencial" class="form-control-edit" value="<?= htmlspecialchars($alumno['pref_presencial'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-4">
                                        <label>Modulación</label>
                                        <input type="text" name="modulacion" class="form-control-edit" value="<?= htmlspecialchars($alumno['modulacion'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-4">
                                        <label>Horarios Disponibles</label>
                                        <input type="text" name="horarios" class="form-control-edit" value="<?= htmlspecialchars($alumno['horarios'] ?? '') ?>">
                                    </div>

                                    <div class="form-group-custom span-2">
                                        <label>Mañanas Desde</label>
                                        <input type="text" name="mananas_desde" class="form-control-edit" value="<?= htmlspecialchars($alumno['mananas_desde'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Mañanas Hasta</label>
                                        <input type="text" name="mananas_hasta" class="form-control-edit" value="<?= htmlspecialchars($alumno['mananas_hasta'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Tardes Desde</label>
                                        <input type="text" name="tardes_desde" class="form-control-edit" value="<?= htmlspecialchars($alumno['tardes_desde'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-2">
                                        <label>Tardes Hasta</label>
                                        <input type="text" name="tardes_hasta" class="form-control-edit" value="<?= htmlspecialchars($alumno['tardes_hasta'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-4">
                                        <label>Solo los días</label>
                                        <input type="text" name="solo_los" class="form-control-edit" value="<?= htmlspecialchars($alumno['solo_los'] ?? '') ?>">
                                    </div>

                                    <div class="form-group-custom span-12">
                                        <label>Observaciones Generales</label>
                                        <textarea name="observaciones" class="form-control-edit" rows="3"><?= htmlspecialchars($alumno['observaciones'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 5: DIRECCIÓN DE ENTREGA -->
                            <div class="card-section-premium card-section-entrega">
                                <h3 class="card-section-title"><i class="fas fa-truck"></i> Dirección de Entrega de Material</h3>
                                <div class="form-grid">
                                    <div class="form-group-custom span-4">
                                        <label>A la atención de</label>
                                        <input type="text" name="entrega_atencion" class="form-control-edit" value="<?= htmlspecialchars($alumno['entrega_atencion'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-8">
                                        <label>Dirección de Entrega</label>
                                        <input type="text" name="entrega_domicilio" class="form-control-edit" value="<?= htmlspecialchars($alumno['entrega_domicilio'] ?? '') ?>">
                                    </div>

                                    <div class="form-group-custom span-2">
                                        <label>Código Postal</label>
                                        <input type="text" name="entrega_cp" class="form-control-edit" value="<?= htmlspecialchars($alumno['entrega_cp'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-5">
                                        <label>Localidad</label>
                                        <input type="text" name="entrega_localidad" class="form-control-edit" value="<?= htmlspecialchars($alumno['entrega_localidad'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-custom span-5">
                                        <label>Provincia</label>
                                        <select name="entrega_provincia" class="form-control-edit">
                                            <option value="">---</option>
                                            <?php foreach ($provincias as $p): ?>
                                                <option value="<?= $p ?>" <?= ($alumno['entrega_provincia'] ?? '') == $p ? 'selected' : '' ?>><?= $p ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 2rem; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                                <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-weight: 700; font-size: 0.9rem;">💾 Guardar Todos los Cambios</button>
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
                                        <a href="ficha_matricula.php?id=<?= $mat['id'] ?>" style="text-decoration: none;">📝</a>
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
                                        <option value="Anexo1">Anexo 1</option>
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

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-preview').innerHTML = '<img src="' + e.target.result + '" alt="Foto Alumno" style="width: 100%; height: 100%; object-fit: cover;">';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</body>
</html>
