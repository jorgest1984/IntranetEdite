<?php
// email_masivo.php - Módulo de Envío Masivo de Correos con Filtros Reales y Conexión a Moodle
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/smtp_mailer.php';

// Permisos: Solo administradores, tutores y coordinadores
if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COORD])) {
    header("Location: home.php");
    exit();
}

$current_page = 'email_masivo.php';
$mensaje_resultado = '';
$tipo_resultado = '';

// 1. Cargar datos para dropdowns
$convocatorias = [];
$planes = [];
$comerciales = [];
$tutores = [];
$centros_db = [];

try {
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales->fetchAll(PDO::FETCH_ASSOC);

    $stmtTutores = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%' OR r.nombre LIKE '%Docente%' OR u.rol_id = 3) AND u.activo = 1 ORDER BY u.nombre ASC");
    $tutores = $stmtTutores->fetchAll(PDO::FETCH_ASSOC);

    $stmtEmpresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 200");
    $centros_db = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silencioso o log
}

// 2. Variables de formulario (preservar estado de búsqueda)
$search_params = [
    'n_accion' => trim($_REQUEST['n_accion'] ?? ''),
    'n_grupo' => trim($_REQUEST['n_grupo'] ?? ''),
    'convocatoria' => trim($_REQUEST['convocatoria'] ?? ''),
    'plan' => trim($_REQUEST['plan'] ?? ''),
    'modalidad' => trim($_REQUEST['modalidad'] ?? ''),
    'inicio_desde' => trim($_REQUEST['inicio_desde'] ?? ''),
    'inicio_hasta' => trim($_REQUEST['inicio_hasta'] ?? ''),
    'fin_desde' => trim($_REQUEST['fin_desde'] ?? ''),
    'fin_hasta' => trim($_REQUEST['fin_hasta'] ?? ''),
    'empresa' => trim($_REQUEST['empresa'] ?? ''),
    'estado' => trim($_REQUEST['estado'] ?? ''),
    'comercial' => trim($_REQUEST['comercial'] ?? ''),
    'claves' => trim($_REQUEST['claves'] ?? ''),
    'conectados' => trim($_REQUEST['conectados'] ?? ''),
    'encuesta' => trim($_REQUEST['encuesta'] ?? ''),
    'tutor' => trim($_REQUEST['tutor'] ?? ''),
    'remitente' => trim($_REQUEST['remitente'] ?? 'elena.adame@editeformacion.com'),
    'asunto' => trim($_REQUEST['asunto'] ?? ''),
    'diploma_desde' => trim($_REQUEST['diploma_desde'] ?? ''),
    'diploma_hasta' => trim($_REQUEST['diploma_hasta'] ?? ''),
    'mensaje_email' => $_REQUEST['mensaje_email'] ?? ''
];

$action_type = $_POST['action_type'] ?? ($_GET['action_type'] ?? '');

// 3. Procesar Envío de Emails si se solicitó
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action_type === 'send_email') {
    $selected_ids = $_POST['selected_matriculas'] ?? [];
    $remitente = trim($_POST['remitente'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje_email = $_POST['mensaje_email'] ?? '';

    if (empty($selected_ids)) {
        $mensaje_resultado = "Por favor, seleccione al menos un alumno de la lista para realizar el envío.";
        $tipo_resultado = "danger";
    } elseif (empty($asunto)) {
        $mensaje_resultado = "Debe indicar un Asunto para el correo electrónico.";
        $tipo_resultado = "danger";
    } elseif (empty($mensaje_email)) {
        $mensaje_resultado = "Debe escribir el Mensaje a enviar.";
        $tipo_resultado = "danger";
    } else {
        // Procesar archivos adjuntos si existen
        $attachments = [];
        $uploadDir = __DIR__ . '/uploads/temp_attachments/';
        if (!file_exists($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        foreach (['adjunto1', 'adjunto2', 'adjunto3'] as $adjKey) {
            if (isset($_FILES[$adjKey]) && $_FILES[$adjKey]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$adjKey]['tmp_name'];
                $fileName = basename($_FILES[$adjKey]['name']);
                $targetFile = $uploadDir . time() . '_' . rand(1000, 9999) . '_' . $fileName;
                if (move_uploaded_file($tmpName, $targetFile)) {
                    $attachments[] = $targetFile;
                }
            }
        }

        $enviados_count = 0;
        $errores_count = 0;

        $stmtSel = $pdo->prepare("
            SELECT m.id as matricula_id, m.estado as matricula_estado,
                   a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.email, a.dni, a.plat_usuario, a.plat_clave,
                   g.numero_grupo, g.fecha_inicio, g.fecha_fin,
                   c.nombre_largo as curso_titulo, af.num_accion, af.codigo_af
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            LEFT JOIN grupos g ON m.grupo_id = g.id
            LEFT JOIN acciones_formativas af ON g.accion_id = af.id
            LEFT JOIN cursos c ON af.curso_id = c.id
            WHERE m.id = ?
        ");

        foreach ($selected_ids as $mat_id) {
            $mat_id = (int)$mat_id;
            if (!$mat_id) continue;

            $stmtSel->execute([$mat_id]);
            $rowMat = $stmtSel->fetch(PDO::FETCH_ASSOC);
            if (!$rowMat) continue;

            $to_email = trim($rowMat['email'] ?? '');
            if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                $errores_count++;
                continue;
            }

            // Credenciales y placeholders
            $alumno_nombre = trim($rowMat['nombre'] . ' ' . $rowMat['primer_apellido'] . ' ' . ($rowMat['segundo_apellido'] ?? ''));
            $cleanDni = !empty($rowMat['dni']) ? strtolower(trim(str_replace([' ', '-', '.'], '', $rowMat['dni']))) : '';
            
            $username = trim($rowMat['plat_usuario'] ?? '');
            if (empty($username)) {
                $username = !empty($cleanDni) ? $cleanDni : strtolower(explode('@', $to_email)[0]);
                $pdo->prepare("UPDATE alumnos SET plat_usuario = ? WHERE id = ?")->execute([$username, $rowMat['alumno_id']]);
            }

            $password = trim($rowMat['plat_clave'] ?? '');
            if (empty($password)) {
                $password = !empty($cleanDni) ? 'Edite' . str_replace(['-', '.', ' '], '', $rowMat['dni']) . '!' : 'Efp2026!';
                $pdo->prepare("UPDATE alumnos SET plat_clave = ? WHERE id = ?")->execute([$password, $rowMat['alumno_id']]);
            }

            $curso_nombre = $rowMat['curso_titulo'] ?: 'Aula Virtual';
            $fecha_ini_fmt = !empty($rowMat['fecha_inicio']) ? date('d/m/Y', strtotime($rowMat['fecha_inicio'])) : '';
            $fecha_fin_fmt = !empty($rowMat['fecha_fin']) ? date('d/m/Y', strtotime($rowMat['fecha_fin'])) : '';

            $replacements = [
                '{nombre}' => $alumno_nombre,
                '{alumno}' => $alumno_nombre,
                '{curso}' => $curso_nombre,
                '{grupo}' => $rowMat['numero_grupo'] ?? '',
                '{n_grupo}' => $rowMat['numero_grupo'] ?? '',
                '{usuario}' => $username,
                '{contrasena}' => $password,
                '{clave}' => $password,
                '{url}' => MOODLE_AULA_VIRTUAL_URL,
                '{fecha_inicio}' => $fecha_ini_fmt,
                '{fecha_fin}' => $fecha_fin_fmt
            ];

            $asunto_person = str_replace(array_keys($replacements), array_values($replacements), $asunto);
            $cuerpo_person = str_replace(array_keys($replacements), array_values($replacements), $mensaje_email);

            $sent = send_smtp_email($to_email, $asunto_person, $cuerpo_person, 'Grupo EFP', $attachments, true);

            if ($sent) {
                $enviados_count++;
                // Registrar envío en matricula si corresponde
                $pdo->prepare("UPDATE matriculas SET email_admision_enviado = 1 WHERE id = ?")->execute([$mat_id]);
            } else {
                $errores_count++;
            }
        }

        // Limpiar adjuntos temporales
        foreach ($attachments as $attFile) {
            @unlink($attFile);
        }

        if ($enviados_count > 0) {
            $mensaje_resultado = "✅ Se han enviado correctamente $enviados_count e-mails masivos." . ($errores_count > 0 ? " ($errores_count e-mails fallaron por dirección no válida)." : "");
            $tipo_resultado = "success";
        } else {
            $mensaje_resultado = "❌ No se pudo enviar ningún e-mail. Verifique las direcciones de correo de los alumnos seleccionados.";
            $tipo_resultado = "danger";
        }
    }
}

// 4. Ejecutar consulta de búsqueda de alumnos
$alumnos_resultados = [];
$search_performed = !empty($action_type);

$where = ["1=1"];
$params = [];

if (!empty($search_params['n_accion'])) {
    $where[] = "(af.num_accion LIKE ? OR af.codigo_af LIKE ? OR af.titulo LIKE ?)";
    $params[] = '%' . $search_params['n_accion'] . '%';
    $params[] = '%' . $search_params['n_accion'] . '%';
    $params[] = '%' . $search_params['n_accion'] . '%';
}
if (!empty($search_params['n_grupo'])) {
    $where[] = "g.numero_grupo LIKE ?";
    $params[] = '%' . $search_params['n_grupo'] . '%';
}
if (!empty($search_params['convocatoria'])) {
    $where[] = "(g.convocatoria_id = ? OR m.convocatoria_id = ?)";
    $params[] = (int)$search_params['convocatoria'];
    $params[] = (int)$search_params['convocatoria'];
}
if (!empty($search_params['plan'])) {
    $where[] = "af.plan_id = ?";
    $params[] = (int)$search_params['plan'];
}
if (!empty($search_params['modalidad'])) {
    $where[] = "COALESCE(af.modalidad, g.modalidad) = ?";
    $params[] = $search_params['modalidad'];
}
if (!empty($search_params['inicio_desde'])) {
    $where[] = "g.fecha_inicio >= ?";
    $params[] = $search_params['inicio_desde'];
}
if (!empty($search_params['inicio_hasta'])) {
    $where[] = "g.fecha_inicio <= ?";
    $params[] = $search_params['inicio_hasta'];
}
if (!empty($search_params['fin_desde'])) {
    $where[] = "g.fecha_fin >= ?";
    $params[] = $search_params['fin_desde'];
}
if (!empty($search_params['fin_hasta'])) {
    $where[] = "g.fecha_fin <= ?";
    $params[] = $search_params['fin_hasta'];
}
if (!empty($search_params['empresa'])) {
    $where[] = "(e.nombre LIKE ? OR a.centro_trabajo LIKE ?)";
    $params[] = '%' . $search_params['empresa'] . '%';
    $params[] = '%' . $search_params['empresa'] . '%';
}
if (!empty($search_params['estado'])) {
    $where[] = "m.estado = ?";
    $params[] = $search_params['estado'];
}
if (!empty($search_params['comercial'])) {
    $where[] = "a.comercial_id = ?";
    $params[] = (int)$search_params['comercial'];
}
if (!empty($search_params['tutor'])) {
    $where[] = "(g.tutor_id = ? OR af.tutor_id = ?)";
    $params[] = (int)$search_params['tutor'];
    $params[] = (int)$search_params['tutor'];
}
if (!empty($search_params['claves'])) {
    if ($search_params['claves'] === 'S') {
        $where[] = "m.envio_claves = 1";
    } elseif ($search_params['claves'] === 'N') {
        $where[] = "(m.envio_claves IS NULL OR m.envio_claves = 0)";
    }
}
if (!empty($search_params['encuesta'])) {
    if ($search_params['encuesta'] === 'S') {
        $where[] = "m.encuesta = 1";
    } elseif ($search_params['encuesta'] === 'N') {
        $where[] = "(m.encuesta IS NULL OR m.encuesta = 0)";
    }
}
if (!empty($search_params['diploma_desde'])) {
    $where[] = "m.fecha_comunicacion >= ?";
    $params[] = $search_params['diploma_desde'];
}
if (!empty($search_params['diploma_hasta'])) {
    $where[] = "m.fecha_comunicacion <= ?";
    $params[] = $search_params['diploma_hasta'];
}

// CRÍTICO: Filtro riguroso por Conexión a Moodle / Intranet
if (!empty($search_params['conectados'])) {
    $sqlConectadoCondition = "(
        m.conectado = 1 
        OR m.fecha_conectado IS NOT NULL 
        OR (m.moodle_first_access IS NOT NULL AND m.moodle_first_access NOT IN ('0000-00-00 00:00:00', '1970-01-01 00:00:00', '1970-01-01 01:00:00')) 
        OR (m.moodle_last_access IS NOT NULL AND m.moodle_last_access NOT IN ('0000-00-00 00:00:00', '1970-01-01 00:00:00', '1970-01-01 01:00:00')) 
        OR COALESCE(m.moodle_connected_time, 0) > 0 
        OR COALESCE(m.moodle_e1_completed, 0) = 1 
        OR COALESCE(m.moodle_e2_completed, 0) = 1 
        OR COALESCE(m.moodle_e3_completed, 0) = 1 
        OR (m.moodle_final_grade IS NOT NULL AND m.moodle_final_grade != '')
    )";

    if ($search_params['conectados'] === 'S') {
        $where[] = $sqlConectadoCondition;
    } elseif ($search_params['conectados'] === 'N') {
        $where[] = "NOT " . $sqlConectadoCondition;
    }
}

// Ejecutar consulta de resultados
if ($search_performed) {
    try {
        $sqlSearch = "
            SELECT m.id as matricula_id, m.estado as matricula_estado, m.conectado, m.fecha_conectado,
                   m.moodle_first_access, m.moodle_last_access, m.moodle_connected_time,
                   m.moodle_e1_completed, m.moodle_e2_completed, m.moodle_e3_completed, m.moodle_final_grade,
                   m.envio_claves, m.fecha_claves, m.fecha_comunicacion, m.diploma_entregado,
                   a.id as alumno_id, CONCAT(a.primer_apellido, ' ', COALESCE(a.segundo_apellido, ''), ', ', a.nombre) as alumno_apellidos_nombre,
                   a.nombre as alumno_nombre, a.email, a.dni,
                   g.id as grupo_id, g.numero_grupo, g.fecha_inicio as grupo_inicio, g.fecha_fin as grupo_fin,
                   af.num_accion, af.codigo_af, af.titulo as af_titulo,
                   c.nombre_largo as curso_titulo,
                   p.nombre as plan_nombre,
                   COALESCE(e.nombre, a.centro_trabajo) as empresa_nombre
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            LEFT JOIN grupos g ON m.grupo_id = g.id
            LEFT JOIN acciones_formativas af ON g.accion_id = af.id
            LEFT JOIN cursos c ON af.curso_id = c.id
            LEFT JOIN planes p ON af.plan_id = p.id
            LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY a.primer_apellido ASC, a.nombre ASC
            LIMIT 500
        ";

        $stmtResults = $pdo->prepare($sqlSearch);
        $stmtResults->execute($params);
        $alumnos_resultados = $stmtResults->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $exSearch) {
        $mensaje_resultado = "Error al consultar la base de datos: " . $exSearch->getMessage();
        $tipo_resultado = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Masivo - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --primary-color: #006ce4;
            --primary-hover: #0056b3;
            --accent-blue: #0ea5e9;
            --text-color: #0f172a;
            --text-muted: #64748b;
            --border-color: rgba(0, 108, 228, 0.08);
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 1.5rem; }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header-custom {
            background: #fff;
            padding: 0.5rem;
            border-bottom: 2px solid var(--border-gray);
            text-align: center;
        }

        .card-header-custom h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
        }

        .search-form { padding: 1rem; }

        .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--label-blue);
            white-space: nowrap;
        }

        .form-control {
            font-size: 0.8rem;
            padding: 2px 5px;
            border: 1px solid var(--border-gray);
            border-radius: 2px;
            background: #fff;
        }

        select.form-control { height: 26px; padding: 0 5px; }
        input[type="text"].form-control, input[type="date"].form-control, input[type="email"].form-control { height: 24px; }

        .btn-buscar {
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
            padding: 6px 20px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .btn-buscar:hover { background: #e2e8f0; border-color: #94a3b8; }

        .btn-primary-send {
            background: linear-gradient(135deg, #006ce4 0%, #0056b3 100%);
            color: #ffffff !important;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 108, 228, 0.25);
        }

        .btn-primary-send:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            box-shadow: 0 6px 15px rgba(0, 108, 228, 0.35);
        }

        /* Results Table */
        .results-section {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            overflow: hidden;
        }

        .results-header {
            padding: 0.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border-gray);
        }

        .results-header h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
        }

        .status-header {
            display: flex;
            gap: 5px;
            padding: 5px;
            font-size: 0.65rem;
            font-weight: 700;
            color: #fff;
        }

        .status-box { padding: 2px 5px; border-radius: 2px; }
        .bg-orange { background: #f97316; }
        .bg-cyan { background: #06b6d4; }
        .bg-pink { background: #ec4899; }
        .bg-teal { background: #14b8a6; }
        .bg-green { background: #16a34a; }

        .table-responsive { overflow-x: auto; }
        
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-custom th {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            padding: 6px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 700;
            position: relative;
        }

        .table-custom td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            white-space: nowrap;
        }

        .table-custom tr:nth-child(even) { background: #f8fafc; }
        .table-custom tr:hover { background: #f1f5f9; }

        .badge-conec-yes {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.7rem;
        }

        .badge-conec-no {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.7rem;
        }

        /* Action Bar for Tutorias */
        .action-bar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            background: #ffffff;
            padding: 1.25rem;
            border: 1px solid rgba(0, 108, 228, 0.08);
            border-radius: 16px;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        }

        .action-bar .btn-action {
            padding: 0.7rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            height: 42px;
            box-sizing: border-box;
        }

        .action-bar .btn-action i {
            font-size: 0.95rem;
            color: #006ce4;
        }

        .action-bar .btn-action.active-btn {
            background: linear-gradient(135deg, #006ce4 0%, #0056b3 100%);
            color: #ffffff !important;
            border-color: transparent;
            box-shadow: 0 8px 20px rgba(0, 108, 228, 0.25);
        }

        .action-bar .btn-action.active-btn i {
            color: #ffffff !important;
        }

        .alert-banner {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .alert-banner.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-banner.danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <!-- ACTION BAR -->
            <div class="action-bar">
                <a href="tutorias.php?view=calcular" class="btn-action">
                    <i class="fas fa-calculator"></i> Calcular llamadas
                </a>
                <a href="email_masivo.php" class="btn-action active-btn">
                    <i class="fas fa-envelope"></i> E-mails masivos
                </a>
                <a href="tutorias.php?view=calcular" class="btn-action">
                    <i class="fas fa-play"></i> Inicio curso
                </a>
                <a href="tutorias.php?view=calcular" class="btn-action">
                    <i class="fas fa-adjust"></i> Mitad de curso
                </a>
                <a href="tutorias.php?view=calcular" class="btn-action">
                    <i class="fas fa-calendar-check"></i> 7 días fin
                </a>
                <a href="tutorias.php?view=llamadas" class="btn-action">
                    <i class="fas fa-phone-alt"></i> Llamadas seguimiento
                </a>
                <a href="calendario_tutorias.php" class="btn-action">
                    <i class="fas fa-calendar-alt"></i> Calendario de tutorias
                </a>
            </div>

            <?php if (!empty($mensaje_resultado)): ?>
                <div class="alert-banner <?= $tipo_resultado ?>">
                    <?= htmlspecialchars($mensaje_resultado) ?>
                </div>
            <?php endif; ?>

            <!-- BUSCADOR -->
            <div class="search-card">
                <div class="card-header-custom" style="background:#f8fafc;">
                    <h2 style="color:var(--title-red); margin:0;">EMAIL MASIVO - TUTORÍAS Y ACCESO DE ALUMNOS</h2>
                </div>
                <form class="search-form" id="emailMasivoForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action_type" id="action_type" value="search">
                    
                    <!-- Fila 1 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Nº Acción / Curso:</label>
                            <input type="text" name="n_accion" class="form-control" value="<?= htmlspecialchars($search_params['n_accion']) ?>" style="width: 120px;" placeholder="ej: ADGD352PO">
                        </div>
                        <div class="form-group">
                            <label>Número grupo:</label>
                            <input type="text" name="n_grupo" class="form-control" value="<?= htmlspecialchars($search_params['n_grupo']) ?>" style="width: 90px;">
                        </div>
                        <div class="form-group">
                            <label>Convocatoria:</label>
                            <select name="convocatoria" class="form-control" style="width: 150px;">
                                <option value="">Todas</option>
                                <?php foreach($convocatorias as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $search_params['convocatoria'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plan:</label>
                            <select name="plan" class="form-control" style="width: 220px;">
                                <option value="">Todos los planes</option>
                                <?php foreach($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $search_params['plan'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Modalidad:</label>
                            <select name="modalidad" class="form-control" style="width: 120px;">
                                <option value="">---</option>
                                <option value="Teleformación" <?= $search_params['modalidad'] === 'Teleformación' ? 'selected' : '' ?>>Teleformación</option>
                                <option value="Distancia" <?= $search_params['modalidad'] === 'Distancia' ? 'selected' : '' ?>>Distancia</option>
                                <option value="Mixta" <?= $search_params['modalidad'] === 'Mixta' ? 'selected' : '' ?>>Mixta</option>
                                <option value="Presencial" <?= $search_params['modalidad'] === 'Presencial' ? 'selected' : '' ?>>Presencial</option>
                                <option value="Semipresencial" <?= $search_params['modalidad'] === 'Semipresencial' ? 'selected' : '' ?>>Semipresencial</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Inicio desde:</label><input type="date" name="inicio_desde" value="<?= htmlspecialchars($search_params['inicio_desde']) ?>" class="form-control">
                            <label>hasta:</label><input type="date" name="inicio_hasta" value="<?= htmlspecialchars($search_params['inicio_hasta']) ?>" class="form-control">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Fin desde:</label><input type="date" name="fin_desde" value="<?= htmlspecialchars($search_params['fin_desde']) ?>" class="form-control">
                            <label>hasta:</label><input type="date" name="fin_hasta" value="<?= htmlspecialchars($search_params['fin_hasta']) ?>" class="form-control">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Empresa:</label>
                            <input type="text" name="empresa" value="<?= htmlspecialchars($search_params['empresa']) ?>" class="form-control" list="empresas_list" placeholder="..." style="width: 150px;">
                            <datalist id="empresas_list">
                                <?php foreach($centros_db as $c): ?><option value="<?= htmlspecialchars($c['nombre']) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado" class="form-control" style="width: 130px;">
                                <option value="">---</option>
                                <option value="Admitido" <?= $search_params['estado'] === 'Admitido' ? 'selected' : '' ?>>Admitido</option>
                                <option value="Finalizado" <?= $search_params['estado'] === 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
                                <option value="Baja" <?= $search_params['estado'] === 'Baja' ? 'selected' : '' ?>>Baja</option>
                                <option value="Inscrito" <?= $search_params['estado'] === 'Inscrito' ? 'selected' : '' ?>>Inscrito</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Comercial:</label>
                            <select name="comercial" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach ($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $search_params['comercial'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Claves enviadas:</label>
                            <select name="claves" class="form-control">
                                <option value="">---</option>
                                <option value="S" <?= $search_params['claves'] === 'S' ? 'selected' : '' ?>>Sí</option>
                                <option value="N" <?= $search_params['claves'] === 'N' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group" style="background: #fef08a; padding: 2px 6px; border-radius: 4px; border: 1px solid #fde047;">
                            <label style="color: #854d0e; font-weight: 800;">⚡ Conectados (Moodle/Aula):</label>
                            <select name="conectados" class="form-control" style="font-weight: bold;">
                                <option value="">--- Todos los Alumnos ---</option>
                                <option value="S" <?= $search_params['conectados'] === 'S' ? 'selected' : '' ?>>🟢 SÍ se han conectado</option>
                                <option value="N" <?= $search_params['conectados'] === 'N' ? 'selected' : '' ?>>🔴 NO se han conectado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Realizaron encuesta:</label>
                            <select name="encuesta" class="form-control">
                                <option value="">---</option>
                                <option value="S" <?= $search_params['encuesta'] === 'S' ? 'selected' : '' ?>>Sí</option>
                                <option value="N" <?= $search_params['encuesta'] === 'N' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tutor:</label>
                            <select name="tutor" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $search_params['tutor'] == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr style="margin: 10px 0; border: 0; border-top: 1px solid #e2e8f0;">

                    <!-- Fila 4 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label style="color: #ca8a04;">
                                <i class="fas fa-envelope"></i> E-mail remitente:
                            </label>
                            <input type="email" name="remitente" class="form-control" value="<?= htmlspecialchars($search_params['remitente']) ?>" style="width: 250px;">
                        </div>
                        <div class="form-group" style="flex: 1; margin-left: 15px;">
                            <label>Asunto:</label>
                            <input type="text" name="asunto" class="form-control" value="<?= htmlspecialchars($search_params['asunto']) ?>" placeholder="Ej: Recordatorio de inicio de curso - Aula Virtual" style="width: 100%; min-width: 300px;">
                        </div>
                    </div>

                    <!-- Fila 5 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Fecha diploma desde:</label><input type="date" name="diploma_desde" value="<?= htmlspecialchars($search_params['diploma_desde']) ?>" class="form-control">
                            <label>hasta:</label><input type="date" name="diploma_hasta" value="<?= htmlspecialchars($search_params['diploma_hasta']) ?>" class="form-control">
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-left: auto;">
                            Variables dinámicas disponibles: <code>{nombre}</code>, <code>{curso}</code>, <code>{grupo}</code>, <code>{usuario}</code>, <code>{contrasena}</code>, <code>{url}</code>, <code>{fecha_inicio}</code>
                        </div>
                    </div>

                    <!-- Fila 6 (Archivos) -->
                    <div class="search-row" style="margin-top: 10px; gap: 15px;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--label-blue);">Adjuntos opcionales:</label>
                        <input type="file" name="adjunto1" style="font-size: 0.75rem; border: 1px solid #cbd5e1; padding: 2px;">
                        <input type="file" name="adjunto2" style="font-size: 0.75rem; border: 1px solid #cbd5e1; padding: 2px;">
                        <input type="file" name="adjunto3" style="font-size: 0.75rem; border: 1px solid #cbd5e1; padding: 2px;">
                    </div>

                    <!-- Fila 7 (Mensaje WYSIWYG) -->
                    <div style="margin-top: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: var(--label-blue); display: block; margin-bottom: 5px;">Mensaje del Email:</label>
                        <textarea id="mensaje_email" name="mensaje_email" style="width: 100%; height: 220px;"><?= htmlspecialchars($search_params['mensaje_email']) ?></textarea>
                    </div>

                    <!-- Botones -->
                    <div style="text-align: center; margin-top: 15px; display: flex; justify-content: center; gap: 15px;">
                        <button type="button" onclick="ejecutarBusqueda()" class="btn-buscar"><i class="fas fa-search"></i> Buscar Alumnos</button>
                        <button type="button" onclick="ejecutarEnvio()" class="btn-buscar btn-primary-send"><i class="fas fa-paper-plane"></i> Enviar e-mail a seleccionados</button>
                    </div>

            </div>

            <!-- RESULTADOS -->
            <div class="results-section">
                <div class="results-header">
                    <h2>RESULTADO DE LA BÚSQUEDA <?= $search_performed ? '(' . count($alumnos_resultados) . ' alumnos encontrados)' : '' ?></h2>
                </div>

                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 40px;"><input type="checkbox" id="checkAll" onclick="toggleSelectAll(this)"></th>
                                <th>Plan</th>
                                <th>Acción</th>
                                <th>Grupo</th>
                                <th>Alumno</th>
                                <th>Empresa</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Email</th>
                                <th>Conectado</th>
                                <th>Fecha Conexión</th>
                                <th>Claves Enviadas</th>
                                <th>Fecha Claves</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$search_performed): ?>
                                <tr>
                                    <td colspan="14" style="text-align: center; padding: 2rem; color: #64748b;">
                                        Utilice los filtros superiores (por ejemplo, filtro de <strong>Conectados = 🔴 NO se han conectado</strong> o por <strong>Nº Acción: ADGD352PO</strong>) y haga clic en <strong>Buscar Alumnos</strong>.
                                    </td>
                                </tr>
                            <?php elseif (empty($alumnos_resultados)): ?>
                                <tr>
                                    <td colspan="14" style="text-align: center; padding: 2rem; color: #b91c1c; font-weight: bold;">
                                        No se encontraron alumnos que coincidan exactamente con los criterios de búsqueda especificados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alumnos_resultados as $al): ?>
                                    <?php
                                        // Evaluar si se ha conectado
                                        $has_acc = (!empty($al['conectado']) || !empty($al['fecha_conectado'])
                                                    || (!empty($al['moodle_first_access']) && !in_array($al['moodle_first_access'], ['0000-00-00 00:00:00', '1970-01-01 00:00:00', '1970-01-01 01:00:00']))
                                                    || (!empty($al['moodle_last_access']) && !in_array($al['moodle_last_access'], ['0000-00-00 00:00:00', '1970-01-01 00:00:00', '1970-01-01 01:00:00']))
                                                    || (!empty($al['moodle_connected_time']) && (int)$al['moodle_connected_time'] > 0)
                                                    || !empty($al['moodle_e1_completed']) || !empty($al['moodle_e2_completed']) || !empty($al['moodle_e3_completed'])
                                                    || ($al['moodle_final_grade'] !== null && $al['moodle_final_grade'] !== '')
                                        );

                                        $fecha_con_disp = '';
                                        if (!empty($al['fecha_conectado'])) {
                                            $fecha_con_disp = date('d/m/Y', strtotime($al['fecha_conectado']));
                                        } elseif (!empty($al['moodle_first_access']) && !in_array($al['moodle_first_access'], ['0000-00-00 00:00:00', '1970-01-01 00:00:00'])) {
                                            $fecha_con_disp = date('d/m/Y H:i', strtotime($al['moodle_first_access']));
                                        }
                                    ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <input type="checkbox" name="selected_matriculas[]" value="<?= $al['matricula_id'] ?>" class="student-checkbox">
                                        </td>
                                        <td><?= htmlspecialchars($al['plan_nombre'] ?? '-') ?></td>
                                        <td><strong><?= htmlspecialchars($al['num_accion'] ?: ($al['codigo_af'] ?: '-')) ?></strong></td>
                                        <td><span class="status-box bg-pink"><?= htmlspecialchars($al['numero_grupo'] ?? '-') ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($al['alumno_apellidos_nombre']) ?></strong>
                                            <br><small style="color:#64748b;">DNI: <?= htmlspecialchars($al['dni'] ?? '-') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($al['empresa_nombre'] ?? '-') ?></td>
                                        <td><?= !empty($al['grupo_inicio']) ? date('d/m/Y', strtotime($al['grupo_inicio'])) : '-' ?></td>
                                        <td><?= !empty($al['grupo_fin']) ? date('d/m/Y', strtotime($al['grupo_fin'])) : '-' ?></td>
                                        <td>
                                            <?php if (!empty($al['email'])): ?>
                                                <a href="mailto:<?= htmlspecialchars($al['email']) ?>" style="color:#006ce4; text-decoration:none; font-weight:600;">
                                                    <?= htmlspecialchars($al['email']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:#ef4444; font-weight:bold;">Sin email</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_acc): ?>
                                                <span class="badge-conec-yes">🟢 SÍ</span>
                                            <?php else: ?>
                                                <span class="badge-conec-no">🔴 NO</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $fecha_con_disp ?: '-' ?></td>
                                        <td>
                                            <?= !empty($al['envio_claves']) ? '<span style="color:#16a34a; font-weight:bold;">Sí</span>' : '<span style="color:#64748b;">No</span>' ?>
                                        </td>
                                        <td><?= !empty($al['fecha_claves']) ? date('d/m/Y', strtotime($al['fecha_claves'])) : '-' ?></td>
                                        <td><strong><?= htmlspecialchars($al['matricula_estado'] ?? '-') ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </form>

        </main>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#mensaje_email',
        menubar: false,
        plugins: 'lists link image table code',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code',
        branding: false,
        language: 'es'
      });

      function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
      }

      function ejecutarBusqueda() {
        if (typeof tinymce !== 'undefined' && tinymce.get('mensaje_email')) {
            tinymce.get('mensaje_email').save();
        }
        document.getElementById('action_type').value = 'search';
        document.getElementById('emailMasivoForm').submit();
      }

      function ejecutarEnvio() {
        if (typeof tinymce !== 'undefined' && tinymce.get('mensaje_email')) {
            tinymce.get('mensaje_email').save();
        }
        const checked = document.querySelectorAll('.student-checkbox:checked');
        if (checked.length === 0) {
            alert('Por favor, seleccione al menos un alumno de la lista marcando la casilla correspondiente.');
            return;
        }

        const asunto = document.querySelector('input[name="asunto"]').value.trim();
        if (!asunto) {
            alert('Por favor, introduzca el Asunto del e-mail.');
            return;
        }

        if (confirm('¿Está seguro de enviar este e-mail masivo a los ' + checked.length + ' alumnos seleccionados?')) {
            document.getElementById('action_type').value = 'send_email';
            document.getElementById('emailMasivoForm').submit();
        }
      }
    </script>
</body>
</html>
