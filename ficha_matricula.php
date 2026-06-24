<?php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID de matrícula no especificado.");
}

// 1. Obtener datos masivos de la matrícula
$stmtMatricula = $pdo->prepare("
    SELECT m.*, m.id as matricula_id,
           a.*,
           c.nombre as convocatoria_nombre, c.codigo_expediente,
           p.id as matricula_plan_id, p.nombre as plan_nombre, 
           e.nombre as empresa_nombre,
           g.numero_grupo, g.codigo_plataforma as grupo_cod, g.fecha_inicio as grupo_inicio, g.fecha_fin as grupo_fin,
           af.abreviatura as af_abreviatura, af.prioridad as af_prioridad, 
           cu.nombre_corto as curso_nombre, cu.nombre_largo as curso_titulo
    FROM matriculas m
    LEFT JOIN alumnos a ON m.alumno_id = a.id
    LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
    LEFT JOIN planes p ON c.id = p.convocatoria_id
    LEFT JOIN grupos g ON m.grupo_id = g.id
    LEFT JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN cursos cu ON af.curso_id = cu.id
    LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
    WHERE m.id = ?
");
$stmtMatricula->execute([$id]);
$matricula = $stmtMatricula->fetch(PDO::FETCH_ASSOC);

if (!$matricula) {
    die("Matrícula no encontrada.");
}

$empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Cargar Comerciales, Tutores y lista de Grupos
$comerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$tutores = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%') AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$todos_grupos = $pdo->query("
    SELECT g.id, g.numero_grupo, c.nombre_corto as curso_codigo
    FROM grupos g
    LEFT JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN cursos c ON af.curso_id = c.id
    ORDER BY c.nombre_corto ASC, g.numero_grupo ASC
")->fetchAll(PDO::FETCH_ASSOC);

$active_tab = $_GET['active_tab'] ?? 'tab-personales';

// 2. Procesar formulario (Si el usuario guarda datos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_datos_personales') {
    // Aquí actualizaríamos la tabla alumnos. Como algunos campos de la imagen podrían no existir, 
    // hacemos un try-catch o actualizamos solo los seguros por ahora.
    $sql = "UPDATE alumnos SET 
            dni = ?, seguridad_social = ?, fecha_nacimiento = ?, 
            nombre = ?, primer_apellido = ?, segundo_apellido = ?, 
            sexo = ?,
            tipo_via = ?, nombre_via = ?, num_domicilio = ?, escalera = ?, planta = ?, puerta = ?,
            cp = ?, provincia = ?, localidad = ?,
            telefono = ?, email = ?
            WHERE id = ?";
            
    try {
        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute([
            $_POST['dni'] ?? null, $_POST['ss'] ?? null, $_POST['fecha_nacimiento'] ?? null,
            $_POST['nombre'] ?? null, $_POST['primer_apellido'] ?? null, $_POST['segundo_apellido'] ?? null,
            $_POST['sexo'] ?? null,
            $_POST['tipo_via'] ?? null, $_POST['nombre_via'] ?? null, $_POST['num_domicilio'] ?? null, $_POST['escalera'] ?? null, $_POST['planta'] ?? null, $_POST['puerta'] ?? null,
            $_POST['codigo_postal'] ?? null, $_POST['provincia'] ?? null, $_POST['localidad'] ?? null,
            $_POST['telefono'] ?? null, $_POST['email'] ?? null,
            $matricula['alumno_id']
        ]);
        header("Location: ficha_matricula.php?id=$id&success=1&active_tab=tab-personales");
        exit();
    } catch (Exception $e) {
        $error = "Error al actualizar (es posible que algunos campos como SS o Profesion no estén en la base de datos aún): " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_datos_laborales') {
    $sql = "UPDATE alumnos SET 
            ultima_empresa_id = ?, 
            colectivo = ?, 
            desempleado_larga_duracion = ?, 
            parado_sepe = ?, 
            conductor = ?, 
            ocupacion = ?, 
            puesto_sepe = ?, 
            categoria_profesional = ?, 
            area_funcional = ?, 
            antiguedad = ?, 
            grupo_cotizacion = ?, 
            contrato = ?
            WHERE id = ?";
            
    try {
        $empresa_id = empty($_POST['ultima_empresa_id']) ? null : $_POST['ultima_empresa_id'];
        if ($empresa_id && !is_numeric($empresa_id)) {
            $stmtEmp = $pdo->prepare("SELECT id FROM empresas WHERE nombre = ?");
            $stmtEmp->execute([$empresa_id]);
            $emp = $stmtEmp->fetchColumn();
            if ($emp) {
                $empresa_id = $emp;
            } else {
                $stmtInst = $pdo->prepare("INSERT INTO empresas (nombre) VALUES (?)");
                $stmtInst->execute([$empresa_id]);
                $empresa_id = $pdo->lastInsertId();
            }
        }

        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute([
            $empresa_id,
            $_POST['colectivo'] ?? null,
            $_POST['desempleado_larga_duracion'] ?? null,
            $_POST['parado_sepe'] ?? null,
            $_POST['conductor'] ?? null,
            $_POST['ocupacion'] ?? null,
            $_POST['puesto_sepe'] ?? null,
            $_POST['categoria_profesional'] ?? null,
            $_POST['area_funcional'] ?? null,
            empty($_POST['antiguedad']) ? null : $_POST['antiguedad'],
            $_POST['grupo_cotizacion'] ?? null,
            $_POST['contrato'] ?? null,
            $matricula['alumno_id']
        ]);
        header("Location: ficha_matricula.php?id=$id&success=1&active_tab=tab-laborales");
        exit();
    } catch (Exception $e) {
        $error = "Error al actualizar datos laborales: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['update_datos_curso', 'update_datos_docs', 'update_datos_seguimiento'])) {
    try {
        $action = $_POST['action'];
        
        // 1. Obtener columnas existentes en matriculas y alumnos
        $stmtM = $pdo->query("DESCRIBE matriculas");
        $matriculas_columns = $stmtM->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtA = $pdo->query("DESCRIBE alumnos");
        $alumnos_columns = $stmtA->fetchAll(PDO::FETCH_COLUMN);
        
        // Mapeo de campos a columnas de la base de datos
        $matriculas_mapping = [
            'grupo_id' => 'grupo_id',
            'estado_nuevo' => 'estado',
            'observaciones' => 'observaciones',
            'comercial_id' => 'comercial_id',
            'captado_ugt' => 'captado_ugt',
            'prioridad' => 'prioridad',
            'fecha_abandono' => 'fecha_abandono',
            'no_preinscrito' => 'no_preinscrito',
            'no_desmatricular' => 'no_desmatricular',
            'certificables' => 'certificables',
            'facturables' => 'facturables',
            'anular_sepe' => 'anular_sepe',
            'evaluacion_tic' => 'evaluacion_tic',
            'preferencia_fechas' => 'preferencia_fechas',
            'motivo_baja' => 'motivo_baja',
            'motivo_sepe' => 'motivo_sepe',
            'otros_motivos' => 'otros_motivos',
            'tutor_id' => 'tutor_id',
            'responsable_seguimiento' => 'responsable_seguimiento',
            'diploma_entregado' => 'diploma_entregado',
            'diploma_tipo' => 'diploma_tipo',
            'comunicado' => 'comunicado',
            'fecha_comunicacion' => 'fecha_comunicacion',
            'comunicado_ugt' => 'comunicado_ugt',
            'nomina_entregada' => 'nomina_entregada',
            'anexo1_entregado' => 'anexo1_entregado',
            'matricula_doc' => 'matricula_doc',
            'correcto' => 'correcto',
            'recibi_material' => 'recibi_material',
            'asistencia' => 'asistencia',
            'dias_asiste' => 'dias_asiste',
            'recibi_diploma' => 'recibi_diploma',
            'copia_diploma' => 'copia_diploma',
            'evaluacion_docente' => 'evaluacion_docente',
            'apto' => 'apto',
            'entrega_mat_1' => 'entrega_mat_1',
            'fechas_envio' => 'fechas_envio',
            'envio_claves' => 'envio_claves',
            'fecha_claves' => 'fecha_claves',
            'email_admision_enviado' => 'email_admision_enviado',
            'encuesta' => 'encuesta',
            'conectado' => 'conectado',
            'fecha_conectado' => 'fecha_conectado',
            'email_1_check' => 'email_1_check',
            'email_1_fecha' => 'email_1_fecha',
            'email_2_check' => 'email_2_check',
            'email_2_fecha' => 'email_2_fecha',
            'email_3_check' => 'email_3_check',
            'email_3_fecha' => 'email_3_fecha',
            'email_4_check' => 'email_4_check',
            'email_4_fecha' => 'email_4_fecha',
            'email_5_check' => 'email_5_check',
            'email_5_fecha' => 'email_5_fecha',
            'email_6_check' => 'email_6_check',
            'email_6_fecha' => 'email_6_fecha',
            'email_7_check' => 'email_7_check',
            'email_7_fecha' => 'email_7_fecha',
            'eval_inicial' => 'eval_inicial',
            'fecha_eval_inicial' => 'fecha_eval_inicial',
            'eval_final' => 'eval_final',
            'fecha_eval_final' => 'fecha_eval_final',
            'nota_media' => 'nota_media',
            'observaciones_solicitante' => 'observaciones_solicitante',
            'llamada_inicio' => 'llamada_inicio',
            'llamada_mitad' => 'llamada_mitad',
            'llamada_7dias' => 'llamada_7dias',
            'llamada_cierre' => 'llamada_cierre',
            'llamada_4_fecha' => 'llamada_4_fecha',
            'llamada_5_fecha' => 'llamada_5_fecha',
            'llamada_6_fecha' => 'llamada_6_fecha',
            'llamada_8_fecha' => 'llamada_8_fecha',
            'no_pedir_nomina' => 'no_pedir_nomina'
        ];
        
        $alumnos_mapping = [
            'enviar_mail' => 'enviar_emails',
            'bloqueado' => 'bloqueado'
        ];
        
        // 2. Resolver convocatoria_id a partir de plan_id si aplica
        $convocatoria_id = null;
        if (isset($_POST['plan_id']) && !empty($_POST['plan_id'])) {
            $stmtPlan = $pdo->prepare("SELECT convocatoria_id FROM planes WHERE id = ?");
            $stmtPlan->execute([$_POST['plan_id']]);
            $convocatoria_id = $stmtPlan->fetchColumn();
        }
        
        // 3. Preparar consulta de matrículas
        $update_matriculas = [];
        $update_matriculas_params = [];
        
        if ($convocatoria_id && in_array('convocatoria_id', $matriculas_columns)) {
            $update_matriculas[] = "`convocatoria_id` = ?";
            $update_matriculas_params[] = $convocatoria_id;
        }
        
        foreach ($matriculas_mapping as $post_key => $col_name) {
            if (in_array($col_name, $matriculas_columns)) {
                if (isset($_POST[$post_key])) {
                    $val = $_POST[$post_key];
                    $update_matriculas[] = "`$col_name` = ?";
                    $update_matriculas_params[] = ($val === '') ? null : $val;
                } elseif (in_array($col_name, [
                    'captado_ugt', 'no_preinscrito', 'no_desmatricular', 'diploma_entregado', 'comunicado', 'comunicado_ugt', 
                    'nomina_entregada', 'correcto', 'recibi_material', 'asistencia', 'evaluacion_docente', 'entrega_mat_1',
                    'envio_claves', 'email_admision_enviado', 'encuesta', 'conectado', 'email_1_check', 'email_2_check', 
                    'email_3_check', 'email_4_check', 'email_5_check', 'email_6_check', 'email_7_check', 'llamada_inicio', 
                    'llamada_mitad', 'llamada_7dias', 'llamada_cierre', 'no_pedir_nomina'
                ])) {
                    // Checkbox no enviado = 0
                    $update_matriculas[] = "`$col_name` = ?";
                    $update_matriculas_params[] = 0;
                }
            }
        }
        
        // 4. Preparar consulta de alumnos
        $update_alumnos = [];
        $update_alumnos_params = [];
        foreach ($alumnos_mapping as $post_key => $col_name) {
            if (in_array($col_name, $alumnos_columns)) {
                if (isset($_POST[$post_key])) {
                    $val = $_POST[$post_key];
                    $update_alumnos[] = "`$col_name` = ?";
                    $update_alumnos_params[] = ($val === '') ? null : $val;
                } elseif (in_array($col_name, ['enviar_emails', 'bloqueado'])) {
                    // Checkbox no enviado = 0
                    $update_alumnos[] = "`$col_name` = ?";
                    $update_alumnos_params[] = 0;
                }
            }
        }
        
        // 5. Ejecutar actualizaciones
        $pdo->beginTransaction();
        
        if (!empty($update_matriculas)) {
            $update_matriculas_params[] = $id;
            $sqlM = "UPDATE matriculas SET " . implode(', ', $update_matriculas) . " WHERE id = ?";
            $pdo->prepare($sqlM)->execute($update_matriculas_params);
        }
        
        if (!empty($update_alumnos)) {
            $update_alumnos_params[] = $matricula['alumno_id'];
            $sqlA = "UPDATE alumnos SET " . implode(', ', $update_alumnos) . " WHERE id = ?";
            $pdo->prepare($sqlA)->execute($update_alumnos_params);
        }
        
        $pdo->commit();
        
        $target_tab = ($action === 'update_datos_curso') ? 'tab-curso' : (($action === 'update_datos_docs') ? 'tab-docs' : 'tab-seguimiento');
        header("Location: ficha_matricula.php?id=$id&success=1&active_tab=$target_tab");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error al actualizar la inscripción: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición de Matrícula - <?= htmlspecialchars($matricula['nombre'] ?? '') ?> - <?= APP_NAME ?? 'Gestión' ?></title>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .card-resumen {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .resumen-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .resumen-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
        }
        .resumen-value {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
        }
        
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
            text-decoration: none;
            display: inline-block;
        }
        .tab-btn.active { 
            background: white; 
            color: var(--primary-color); 
            font-weight: 600; 
            border-bottom: 2px solid var(--primary-color); 
        }
        .tab-panel {
            background: white;
            padding: 2rem;
            border-radius: 0 0 12px 12px;
            border: 1px solid var(--border-color);
            border-top: none;
        }
        
        .form-section-title {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .grid-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
        }
        
        .form-control {
            width: 100%;
            padding: 0.65rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-modern {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-primary-modern {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 4px rgba(37,99,235,0.2);
        }
        .btn-primary-modern:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        
        /* Simulating the old top buttons inside a modern dropdown or action bar */
        .actions-bar {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .btn-outline {
            background: white;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
        }
        .btn-outline:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        
        .tab-panel.hidden {
            display: none;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        
        <!-- HEADER -->
        <div class="header-premium" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
            <div>
                <a href="ficha_alumno.php?id=<?= $matricula['alumno_id'] ?>" class="btn-back" style="text-decoration:none; color: var(--primary-color); font-weight:700;">⬅ Volver a Ficha de Alumno</a>
                <h1 style="margin-top: 0.8rem; margin-bottom:0.4rem; font-size: 1.8rem; color: #1e293b;">
                    Edición: <?= htmlspecialchars($matricula['nombre'] ?? '') ?> <?= htmlspecialchars($matricula['primer_apellido'] ?? '') ?>
                </h1>
                <p style="margin:0; color:#64748b; font-weight:500;">
                    DNI: <strong style="color:#0f172a;"><?= htmlspecialchars($matricula['dni'] ?? 'N/A') ?></strong> | 
                    Matrícula ID: <strong style="color:#0f172a;">#<?= $matricula['matricula_id'] ?></strong>
                </p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #ef4444;">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div style="background: #dcfce3; color: #15803d; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #22c55e;">
                ✅ Datos guardados correctamente.
            </div>
        <?php endif; ?>

        <!-- TARJETA RESUMEN CURSO -->
        <div class="card-resumen">
            <div class="resumen-grid">
                <div class="resumen-item">
                    <span class="resumen-label">Acción / Grupo</span>
                    <span class="resumen-value">
                        <span style="background: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-right: 4px;">AC</span><?= htmlspecialchars($matricula['af_abreviatura'] ?? 'N/A') ?> 
                        <span style="background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-left: 8px; margin-right: 4px;">GR</span><?= htmlspecialchars($matricula['numero_grupo'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="resumen-item" style="grid-column: span 2;">
                    <span class="resumen-label">Plan / Curso</span>
                    <span class="resumen-value">
                        <div style="color: #64748b; font-size: 0.8rem; margin-bottom: 2px;"><?= htmlspecialchars($matricula['plan_nombre'] ?? 'Sin Plan') ?></div>
                        <div style="color: #1e3a8a;"><?= htmlspecialchars($matricula['curso_titulo'] ?? 'Sin Curso') ?></div>
                    </span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Fechas</span>
                    <span class="resumen-value">
                        📅 <?= !empty($matricula['grupo_inicio']) ? date('d/m/Y', strtotime($matricula['grupo_inicio'])) : '--/--/----' ?> <br>
                        🏁 <?= !empty($matricula['grupo_fin']) ? date('d/m/Y', strtotime($matricula['grupo_fin'])) : '--/--/----' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ACCIONES RÁPIDAS (Migradas del Top Bar antiguo) -->
        <div class="actions-bar">
            <button class="btn-outline">📄 Documentos</button>
            <button class="btn-outline">⚠️ Incidencia</button>
            <button class="btn-outline">🔄 Sincronizar Moodle</button>
            <button class="btn-outline">📥 Notificar Baja/Aban</button>
            <button class="btn-outline">🔑 Envío Claves</button>
        </div>

        <!-- TABS MODERNOS -->
        <div class="tabs-header">
            <button class="tab-btn <?= $active_tab === 'tab-personales' ? 'active' : '' ?>" data-target="tab-personales">Datos Personales</button>
            <button class="tab-btn <?= $active_tab === 'tab-laborales' ? 'active' : '' ?>" data-target="tab-laborales">Datos Laborales</button>
            <button class="tab-btn <?= $active_tab === 'tab-curso' ? 'active' : '' ?>" data-target="tab-curso">Datos Curso</button>
            <button class="tab-btn <?= $active_tab === 'tab-docs' ? 'active' : '' ?>" data-target="tab-docs">Material y doc.</button>
            <button class="tab-btn <?= $active_tab === 'tab-seguimiento' ? 'active' : '' ?>" data-target="tab-seguimiento">Seguimiento</button>
        </div>

        <div id="tab-personales" class="tab-panel <?= $active_tab === 'tab-personales' ? '' : 'hidden' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_datos_personales">
                
                <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                    <button type="submit" class="btn-modern btn-primary-modern">
                        💾 Guardar Cambios
                    </button>
                </div>

                <!-- SECCIÓN: IDENTIFICACIÓN -->
                <h3 class="form-section-title">Información Básica</h3>
                <div class="grid-form">
                    <div class="form-group">
                        <label>NIF / NIE</label>
                        <input type="text" name="dni" class="form-control" value="<?= htmlspecialchars($matricula['dni'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Seguridad Social</label>
                        <input type="text" name="ss" class="form-control" value="<?= htmlspecialchars($matricula['seguridad_social'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" value="<?= !empty($matricula['fecha_nacimiento']) ? date('Y-m-d', strtotime($matricula['fecha_nacimiento'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Sexo</label>
                        <select name="sexo" class="form-control">
                            <option value=""></option>
                            <option value="Hombre" <?= ($matricula['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                            <option value="Mujer" <?= ($matricula['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                        </select>
                    </div>
                </div>

                <div class="grid-form">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($matricula['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Primer Apellido</label>
                        <input type="text" name="primer_apellido" class="form-control" value="<?= htmlspecialchars($matricula['primer_apellido'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Segundo Apellido</label>
                        <input type="text" name="segundo_apellido" class="form-control" value="<?= htmlspecialchars($matricula['segundo_apellido'] ?? '') ?>">
                    </div>
                </div>

                <!-- SECCIÓN: PERFIL -->
                <h3 class="form-section-title" style="margin-top: 2rem;">Perfil Académico / Profesional</h3>
                <div class="grid-form">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Profesión</label>
                        <input type="text" name="profesion" class="form-control" value="<?= htmlspecialchars($matricula['profesion'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nivel de Estudios</label>
                        <select name="estudios" class="form-control">
                            <option value=""></option>
                            <option value="Bachillerato, BUP" <?= ($matricula['estudios'] ?? '') == 'Bachillerato, BUP' ? 'selected' : '' ?>>Bachillerato, BUP</option>
                            <option value="Carnet profesional" <?= ($matricula['estudios'] ?? '') == 'Carnet profesional' ? 'selected' : '' ?>>Carnet profesional</option>
                            <option value="Certificados de Profesionalidad nivel 1" <?= ($matricula['estudios'] ?? '') == 'Certificados de Profesionalidad nivel 1' ? 'selected' : '' ?>>Certificados de Profesionalidad nivel 1</option>
                            <option value="Certificados de profesionalidad nivel 2" <?= ($matricula['estudios'] ?? '') == 'Certificados de profesionalidad nivel 2' ? 'selected' : '' ?>>Certificados de profesionalidad nivel 2</option>
                            <option value="Certificados de profesionalidad nivel 3" <?= ($matricula['estudios'] ?? '') == 'Certificados de profesionalidad nivel 3' ? 'selected' : '' ?>>Certificados de profesionalidad nivel 3</option>
                            <option value="Educación primaria" <?= ($matricula['estudios'] ?? '') == 'Educación primaria' ? 'selected' : '' ?>>Educación primaria</option>
                            <option value="Enseñanzas de escuelas oficiales de idiomas" <?= ($matricula['estudios'] ?? '') == 'Enseñanzas de escuelas oficiales de idiomas' ? 'selected' : '' ?>>Enseñanzas de escuelas oficiales de idiomas</option>
                            <option value="Especialidades en CC. Salud (residentes)" <?= ($matricula['estudios'] ?? '') == 'Especialidades en CC. Salud (residentes)' ? 'selected' : '' ?>>Especialidades en CC. Salud (residentes)</option>
                            <option value="Estudios Universitarios 1er ciclo (Diplomatura - Grados)" <?= ($matricula['estudios'] ?? '') == 'Estudios Universitarios 1er ciclo (Diplomatura - Grados)' ? 'selected' : '' ?>>Estudios Universitarios 1er ciclo (Diplomatura - Grados)</option>
                            <option value="Estudios Universitarios 2º ciclo (Licenciatura - Máster)" <?= ($matricula['estudios'] ?? '') == 'Estudios Universitarios 2º ciclo (Licenciatura - Máster)' ? 'selected' : '' ?>>Estudios Universitarios 2º ciclo (Licenciatura - Máster)</option>
                            <option value="Estudios Universitarios 3er ciclo (Doctorado)" <?= ($matricula['estudios'] ?? '') == 'Estudios Universitarios 3er ciclo (Doctorado)' ? 'selected' : '' ?>>Estudios Universitarios 3er ciclo (Doctorado)</option>
                            <option value="Formación Profesional Básica/Cualificación Profesional Inicial" <?= ($matricula['estudios'] ?? '') == 'Formación Profesional Básica/Cualificación Profesional Inicial' ? 'selected' : '' ?>>Formación Profesional Básica/Cualificación Profesional Inicial</option>
                            <option value="FP grado medio, FPI" <?= ($matricula['estudios'] ?? '') == 'FP grado medio, FPI' ? 'selected' : '' ?>>FP grado medio, FPI</option>
                            <option value="FPII" <?= ($matricula['estudios'] ?? '') == 'FPII' ? 'selected' : '' ?>>FPII</option>
                            <option value="Grados Universitarios de hasta 240 créditos" <?= ($matricula['estudios'] ?? '') == 'Grados Universitarios de hasta 240 créditos' ? 'selected' : '' ?>>Grados Universitarios de hasta 240 créditos</option>
                            <option value="Grados Universitarios de más 240 créditos" <?= ($matricula['estudios'] ?? '') == 'Grados Universitarios de más 240 créditos' ? 'selected' : '' ?>>Grados Universitarios de más 240 créditos</option>
                            <option value="Másteres Oficiales Universitarios" <?= ($matricula['estudios'] ?? '') == 'Másteres Oficiales Universitarios' ? 'selected' : '' ?>>Másteres Oficiales Universitarios</option>
                            <option value="Nivel de idioma A1 del MCER" <?= ($matricula['estudios'] ?? '') == 'Nivel de idioma A1 del MCER' ? 'selected' : '' ?>>Nivel de idioma A1 del MCER</option>
                            <option value="Nivel de idioma A2 del MCER" <?= ($matricula['estudios'] ?? '') == 'Nivel de idioma A2 del MCER' ? 'selected' : '' ?>>Nivel de idioma A2 del MCER</option>
                            <option value="Nivel de idioma B1 del MCER" <?= ($matricula['estudios'] ?? '') == 'Nivel de idioma B1 del MCER' ? 'selected' : '' ?>>Nivel de idioma B1 del MCER</option>
                            <option value="Nivel de idioma B2 del MCER" <?= ($matricula['estudios'] ?? '') == 'Nivel de idioma B2 del MCER' ? 'selected' : '' ?>>Nivel de idioma B2 del MCER</option>
                            <option value="Nivel de idioma C1 del MCER" <?= ($matricula['estudios'] ?? '') == 'Nivel de idioma C1 del MCER' ? 'selected' : '' ?>>Nivel de idioma C1 del MCER</option>
                            <option value="Nivel de idioma C2 del MCER" <?= ($matricula['estudios'] ?? '') == 'Nivel de idioma C2 del MCER' ? 'selected' : '' ?>>Nivel de idioma C2 del MCER</option>
                            <option value="Otras titulaciones" <?= ($matricula['estudios'] ?? '') == 'Otras titulaciones' ? 'selected' : '' ?>>Otras titulaciones</option>
                            <option value="Segunda etapa de educación secundaria (Bachillerato, FP Grado Medio, BUP, FPI y FPII)" <?= ($matricula['estudios'] ?? '') == 'Segunda etapa de educación secundaria (Bachillerato, FP Grado Medio, BUP, FPI y FPII)' ? 'selected' : '' ?>>Segunda etapa de educación secundaria (Bachillerato, FP Grado Medio, BUP, FPI y FPII)</option>
                            <option value="Sin titulación" <?= ($matricula['estudios'] ?? '') == 'Sin titulación' ? 'selected' : '' ?>>Sin titulación</option>
                            <option value="Técnico Superior / FP grado superior y equivalente" <?= ($matricula['estudios'] ?? '') == 'Técnico Superior / FP grado superior y equivalente' ? 'selected' : '' ?>>Técnico Superior / FP grado superior y equivalente</option>
                            <option value="Título de Doctor" <?= ($matricula['estudios'] ?? '') == 'Título de Doctor' ? 'selected' : '' ?>>Título de Doctor</option>
                            <option value="Título de ESO, EGB, Graduado Escolar" <?= ($matricula['estudios'] ?? '') == 'Título de ESO, EGB, Graduado Escolar' ? 'selected' : '' ?>>Título de ESO, EGB, Graduado Escolar</option>
                            <option value="Título profesional enseñanzas música/danza; artes plásticas - diseño" <?= ($matricula['estudios'] ?? '') == 'Título profesional enseñanzas música/danza; artes plásticas - diseño' ? 'selected' : '' ?>>Título profesional enseñanzas música/danza; artes plásticas - diseño</option>
                        </select>
                    </div>
                </div>

                <!-- SECCIÓN: DIRECCIÓN -->
                <h3 class="form-section-title" style="margin-top: 2rem;">Dirección y Contacto</h3>
                <div class="grid-form">
                    <div class="form-group">
                        <label>Tipo de Vía</label>
                        <select name="tipo_via" class="form-control">
                            <option value=""></option>
                            <option value="Agregado" <?= ($matricula['tipo_via'] ?? '') == 'Agregado' ? 'selected' : '' ?>>Agregado</option>
                            <option value="Alameda" <?= ($matricula['tipo_via'] ?? '') == 'Alameda' ? 'selected' : '' ?>>Alameda</option>
                            <option value="Aldea" <?= ($matricula['tipo_via'] ?? '') == 'Aldea' ? 'selected' : '' ?>>Aldea</option>
                            <option value="Apartamento" <?= ($matricula['tipo_via'] ?? '') == 'Apartamento' ? 'selected' : '' ?>>Apartamento</option>
                            <option value="Area" <?= ($matricula['tipo_via'] ?? '') == 'Area' ? 'selected' : '' ?>>Area</option>
                            <option value="Arroyo" <?= ($matricula['tipo_via'] ?? '') == 'Arroyo' ? 'selected' : '' ?>>Arroyo</option>
                            <option value="Avenida" <?= ($matricula['tipo_via'] ?? '') == 'Avenida' ? 'selected' : '' ?>>Avenida</option>
                            <option value="Bajada" <?= ($matricula['tipo_via'] ?? '') == 'Bajada' ? 'selected' : '' ?>>Bajada</option>
                            <option value="Barranco" <?= ($matricula['tipo_via'] ?? '') == 'Barranco' ? 'selected' : '' ?>>Barranco</option>
                            <option value="Barriada" <?= ($matricula['tipo_via'] ?? '') == 'Barriada' ? 'selected' : '' ?>>Barriada</option>
                            <option value="Barrio" <?= ($matricula['tipo_via'] ?? '') == 'Barrio' ? 'selected' : '' ?>>Barrio</option>
                            <option value="Bloque" <?= ($matricula['tipo_via'] ?? '') == 'Bloque' ? 'selected' : '' ?>>Bloque</option>
                            <option value="Calle" <?= ($matricula['tipo_via'] ?? '') == 'Calle' ? 'selected' : '' ?>>Calle</option>
                            <option value="Calleja" <?= ($matricula['tipo_via'] ?? '') == 'Calleja' ? 'selected' : '' ?>>Calleja</option>
                            <option value="Camino" <?= ($matricula['tipo_via'] ?? '') == 'Camino' ? 'selected' : '' ?>>Camino</option>
                            <option value="Campa" <?= ($matricula['tipo_via'] ?? '') == 'Campa' ? 'selected' : '' ?>>Campa</option>
                            <option value="Carrera" <?= ($matricula['tipo_via'] ?? '') == 'Carrera' ? 'selected' : '' ?>>Carrera</option>
                            <option value="Carretera" <?= ($matricula['tipo_via'] ?? '') == 'Carretera' ? 'selected' : '' ?>>Carretera</option>
                            <option value="Caserio" <?= ($matricula['tipo_via'] ?? '') == 'Caserio' ? 'selected' : '' ?>>Caserio</option>
                            <option value="Chalet" <?= ($matricula['tipo_via'] ?? '') == 'Chalet' ? 'selected' : '' ?>>Chalet</option>
                            <option value="Colegio" <?= ($matricula['tipo_via'] ?? '') == 'Colegio' ? 'selected' : '' ?>>Colegio</option>
                            <option value="Colonia" <?= ($matricula['tipo_via'] ?? '') == 'Colonia' ? 'selected' : '' ?>>Colonia</option>
                            <option value="Conjunto" <?= ($matricula['tipo_via'] ?? '') == 'Conjunto' ? 'selected' : '' ?>>Conjunto</option>
                            <option value="Corregidor" <?= ($matricula['tipo_via'] ?? '') == 'Corregidor' ? 'selected' : '' ?>>Corregidor</option>
                            <option value="Cuesta" <?= ($matricula['tipo_via'] ?? '') == 'Cuesta' ? 'selected' : '' ?>>Cuesta</option>
                            <option value="Diputación" <?= ($matricula['tipo_via'] ?? '') == 'Diputación' ? 'selected' : '' ?>>Diputación</option>
                            <option value="Diseminados" <?= ($matricula['tipo_via'] ?? '') == 'Diseminados' ? 'selected' : '' ?>>Diseminados</option>
                            <option value="Edificio" <?= ($matricula['tipo_via'] ?? '') == 'Edificio' ? 'selected' : '' ?>>Edificio</option>
                            <option value="Entrada" <?= ($matricula['tipo_via'] ?? '') == 'Entrada' ? 'selected' : '' ?>>Entrada</option>
                            <option value="Escalinata" <?= ($matricula['tipo_via'] ?? '') == 'Escalinata' ? 'selected' : '' ?>>Escalinata</option>
                            <option value="Explanada" <?= ($matricula['tipo_via'] ?? '') == 'Explanada' ? 'selected' : '' ?>>Explanada</option>
                            <option value="Extramuros" <?= ($matricula['tipo_via'] ?? '') == 'Extramuros' ? 'selected' : '' ?>>Extramuros</option>
                            <option value="Extrarradio" <?= ($matricula['tipo_via'] ?? '') == 'Extrarradio' ? 'selected' : '' ?>>Extrarradio</option>
                            <option value="Ferrocarril" <?= ($matricula['tipo_via'] ?? '') == 'Ferrocarril' ? 'selected' : '' ?>>Ferrocarril</option>
                            <option value="Glorieta" <?= ($matricula['tipo_via'] ?? '') == 'Glorieta' ? 'selected' : '' ?>>Glorieta</option>
                            <option value="Gran Vía" <?= ($matricula['tipo_via'] ?? '') == 'Gran Vía' ? 'selected' : '' ?>>Gran Vía</option>
                            <option value="Grupo" <?= ($matricula['tipo_via'] ?? '') == 'Grupo' ? 'selected' : '' ?>>Grupo</option>
                            <option value="Huerta" <?= ($matricula['tipo_via'] ?? '') == 'Huerta' ? 'selected' : '' ?>>Huerta</option>
                            <option value="Jardines" <?= ($matricula['tipo_via'] ?? '') == 'Jardines' ? 'selected' : '' ?>>Jardines</option>
                            <option value="Ladera" <?= ($matricula['tipo_via'] ?? '') == 'Ladera' ? 'selected' : '' ?>>Ladera</option>
                            <option value="Lugar" <?= ($matricula['tipo_via'] ?? '') == 'Lugar' ? 'selected' : '' ?>>Lugar</option>
                            <option value="Manzana" <?= ($matricula['tipo_via'] ?? '') == 'Manzana' ? 'selected' : '' ?>>Manzana</option>
                            <option value="Masía" <?= ($matricula['tipo_via'] ?? '') == 'Masía' ? 'selected' : '' ?>>Masía</option>
                            <option value="Mercado" <?= ($matricula['tipo_via'] ?? '') == 'Mercado' ? 'selected' : '' ?>>Mercado</option>
                            <option value="Monte" <?= ($matricula['tipo_via'] ?? '') == 'Monte' ? 'selected' : '' ?>>Monte</option>
                            <option value="Muelle" <?= ($matricula['tipo_via'] ?? '') == 'Muelle' ? 'selected' : '' ?>>Muelle</option>
                            <option value="Municipio" <?= ($matricula['tipo_via'] ?? '') == 'Municipio' ? 'selected' : '' ?>>Municipio</option>
                            <option value="Parque" <?= ($matricula['tipo_via'] ?? '') == 'Parque' ? 'selected' : '' ?>>Parque</option>
                            <option value="Pasaje" <?= ($matricula['tipo_via'] ?? '') == 'Pasaje' ? 'selected' : '' ?>>Pasaje</option>
                            <option value="Passatge" <?= ($matricula['tipo_via'] ?? '') == 'Passatge' ? 'selected' : '' ?>>Passatge</option>
                            <option value="Poligono" <?= ($matricula['tipo_via'] ?? '') == 'Poligono' ? 'selected' : '' ?>>Poligono</option>
                            <option value="Ramal" <?= ($matricula['tipo_via'] ?? '') == 'Ramal' ? 'selected' : '' ?>>Ramal</option>
                            <option value="Rampa" <?= ($matricula['tipo_via'] ?? '') == 'Rampa' ? 'selected' : '' ?>>Rampa</option>
                            <option value="Riera" <?= ($matricula['tipo_via'] ?? '') == 'Riera' ? 'selected' : '' ?>>Riera</option>
                            <option value="Ronda" <?= ($matricula['tipo_via'] ?? '') == 'Ronda' ? 'selected' : '' ?>>Ronda</option>
                            <option value="Rua" <?= ($matricula['tipo_via'] ?? '') == 'Rua' ? 'selected' : '' ?>>Rua</option>
                            <option value="Salida" <?= ($matricula['tipo_via'] ?? '') == 'Salida' ? 'selected' : '' ?>>Salida</option>
                            <option value="Senda" <?= ($matricula['tipo_via'] ?? '') == 'Senda' ? 'selected' : '' ?>>Senda</option>
                            <option value="sin definir" <?= ($matricula['tipo_via'] ?? '') == 'sin definir' ? 'selected' : '' ?>>sin definir</option>
                            <option value="Solar" <?= ($matricula['tipo_via'] ?? '') == 'Solar' ? 'selected' : '' ?>>Solar</option>
                            <option value="Subida" <?= ($matricula['tipo_via'] ?? '') == 'Subida' ? 'selected' : '' ?>>Subida</option>
                            <option value="Terrenos" <?= ($matricula['tipo_via'] ?? '') == 'Terrenos' ? 'selected' : '' ?>>Terrenos</option>
                            <option value="Torrente" <?= ($matricula['tipo_via'] ?? '') == 'Torrente' ? 'selected' : '' ?>>Torrente</option>
                            <option value="Travesía" <?= ($matricula['tipo_via'] ?? '') == 'Travesía' ? 'selected' : '' ?>>Travesía</option>
                            <option value="Urbanización" <?= ($matricula['tipo_via'] ?? '') == 'Urbanización' ? 'selected' : '' ?>>Urbanización</option>
                            <option value="Vía" <?= ($matricula['tipo_via'] ?? '') == 'Vía' ? 'selected' : '' ?>>Vía</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Nombre de Vía</label>
                        <input type="text" name="nombre_via" class="form-control" value="<?= htmlspecialchars($matricula['nombre_via'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nº Domicilio</label>
                        <input type="text" name="num_domicilio" class="form-control" value="<?= htmlspecialchars($matricula['num_domicilio'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="form-group">
                        <label>Escalera</label>
                        <input type="text" name="escalera" class="form-control" value="<?= htmlspecialchars($matricula['escalera'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Planta</label>
                        <input type="text" name="planta" class="form-control" value="<?= htmlspecialchars($matricula['planta'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Puerta</label>
                        <input type="text" name="puerta" class="form-control" value="<?= htmlspecialchars($matricula['puerta'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Código Postal</label>
                        <input type="text" name="codigo_postal" class="form-control" value="<?= htmlspecialchars($matricula['cp'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid-form">
                    <div class="form-group">
                        <label>Localidad</label>
                        <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($matricula['localidad'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Provincia</label>
                        <select name="provincia" class="form-control">
                            <option value=""></option>
                            <option value="A CORUÑA" <?= ($matricula['provincia'] ?? '') == 'A CORUÑA' ? 'selected' : '' ?>>A CORUÑA</option>
                            <option value="ALAVA" <?= ($matricula['provincia'] ?? '') == 'ALAVA' ? 'selected' : '' ?>>ALAVA</option>
                            <option value="ALBACETE" <?= ($matricula['provincia'] ?? '') == 'ALBACETE' ? 'selected' : '' ?>>ALBACETE</option>
                            <option value="ALICANTE" <?= ($matricula['provincia'] ?? '') == 'ALICANTE' ? 'selected' : '' ?>>ALICANTE</option>
                            <option value="ALMERIA" <?= ($matricula['provincia'] ?? '') == 'ALMERIA' ? 'selected' : '' ?>>ALMERIA</option>
                            <option value="ASTURIAS" <?= ($matricula['provincia'] ?? '') == 'ASTURIAS' ? 'selected' : '' ?>>ASTURIAS</option>
                            <option value="AVILA" <?= ($matricula['provincia'] ?? '') == 'AVILA' ? 'selected' : '' ?>>AVILA</option>
                            <option value="BADAJOZ" <?= ($matricula['provincia'] ?? '') == 'BADAJOZ' ? 'selected' : '' ?>>BADAJOZ</option>
                            <option value="BALEARES" <?= ($matricula['provincia'] ?? '') == 'BALEARES' ? 'selected' : '' ?>>BALEARES</option>
                            <option value="BARCELONA" <?= ($matricula['provincia'] ?? '') == 'BARCELONA' ? 'selected' : '' ?>>BARCELONA</option>
                            <option value="BURGOS" <?= ($matricula['provincia'] ?? '') == 'BURGOS' ? 'selected' : '' ?>>BURGOS</option>
                            <option value="CACERES" <?= ($matricula['provincia'] ?? '') == 'CACERES' ? 'selected' : '' ?>>CACERES</option>
                            <option value="CADIZ" <?= ($matricula['provincia'] ?? '') == 'CADIZ' ? 'selected' : '' ?>>CADIZ</option>
                            <option value="CANTABRIA" <?= ($matricula['provincia'] ?? '') == 'CANTABRIA' ? 'selected' : '' ?>>CANTABRIA</option>
                            <option value="CASTELLON" <?= ($matricula['provincia'] ?? '') == 'CASTELLON' ? 'selected' : '' ?>>CASTELLON</option>
                            <option value="CEUTA" <?= ($matricula['provincia'] ?? '') == 'CEUTA' ? 'selected' : '' ?>>CEUTA</option>
                            <option value="CIUDAD REAL" <?= ($matricula['provincia'] ?? '') == 'CIUDAD REAL' ? 'selected' : '' ?>>CIUDAD REAL</option>
                            <option value="CORDOBA" <?= ($matricula['provincia'] ?? '') == 'CORDOBA' ? 'selected' : '' ?>>CORDOBA</option>
                            <option value="CUENCA" <?= ($matricula['provincia'] ?? '') == 'CUENCA' ? 'selected' : '' ?>>CUENCA</option>
                            <option value="GIRONA" <?= ($matricula['provincia'] ?? '') == 'GIRONA' ? 'selected' : '' ?>>GIRONA</option>
                            <option value="GRANADA" <?= ($matricula['provincia'] ?? '') == 'GRANADA' ? 'selected' : '' ?>>GRANADA</option>
                            <option value="GUADALAJARA" <?= ($matricula['provincia'] ?? '') == 'GUADALAJARA' ? 'selected' : '' ?>>GUADALAJARA</option>
                            <option value="GUIPUZCOA" <?= ($matricula['provincia'] ?? '') == 'GUIPUZCOA' ? 'selected' : '' ?>>GUIPUZCOA</option>
                            <option value="HUELVA" <?= ($matricula['provincia'] ?? '') == 'HUELVA' ? 'selected' : '' ?>>HUELVA</option>
                            <option value="HUESCA" <?= ($matricula['provincia'] ?? '') == 'HUESCA' ? 'selected' : '' ?>>HUESCA</option>
                            <option value="JAEN" <?= ($matricula['provincia'] ?? '') == 'JAEN' ? 'selected' : '' ?>>JAEN</option>
                            <option value="LA RIOJA" <?= ($matricula['provincia'] ?? '') == 'LA RIOJA' ? 'selected' : '' ?>>LA RIOJA</option>
                            <option value="LAS PALMAS" <?= ($matricula['provincia'] ?? '') == 'LAS PALMAS' ? 'selected' : '' ?>>LAS PALMAS</option>
                            <option value="LEON" <?= ($matricula['provincia'] ?? '') == 'LEON' ? 'selected' : '' ?>>LEON</option>
                            <option value="LLEIDA" <?= ($matricula['provincia'] ?? '') == 'LLEIDA' ? 'selected' : '' ?>>LLEIDA</option>
                            <option value="LUGO" <?= ($matricula['provincia'] ?? '') == 'LUGO' ? 'selected' : '' ?>>LUGO</option>
                            <option value="MADRID" <?= ($matricula['provincia'] ?? '') == 'MADRID' ? 'selected' : '' ?>>MADRID</option>
                            <option value="MALAGA" <?= ($matricula['provincia'] ?? '') == 'MALAGA' ? 'selected' : '' ?>>MALAGA</option>
                            <option value="MELILLA" <?= ($matricula['provincia'] ?? '') == 'MELILLA' ? 'selected' : '' ?>>MELILLA</option>
                            <option value="MURCIA" <?= ($matricula['provincia'] ?? '') == 'MURCIA' ? 'selected' : '' ?>>MURCIA</option>
                            <option value="NAVARRA" <?= ($matricula['provincia'] ?? '') == 'NAVARRA' ? 'selected' : '' ?>>NAVARRA</option>
                            <option value="OURENSE" <?= ($matricula['provincia'] ?? '') == 'OURENSE' ? 'selected' : '' ?>>OURENSE</option>
                            <option value="PALENCIA" <?= ($matricula['provincia'] ?? '') == 'PALENCIA' ? 'selected' : '' ?>>PALENCIA</option>
                            <option value="PONTEVEDRA" <?= ($matricula['provincia'] ?? '') == 'PONTEVEDRA' ? 'selected' : '' ?>>PONTEVEDRA</option>
                            <option value="SALAMANCA" <?= ($matricula['provincia'] ?? '') == 'SALAMANCA' ? 'selected' : '' ?>>SALAMANCA</option>
                            <option value="SANTA CRUZ DE TENERIFE" <?= ($matricula['provincia'] ?? '') == 'SANTA CRUZ DE TENERIFE' ? 'selected' : '' ?>>SANTA CRUZ DE TENERIFE</option>
                            <option value="SEGOVIA" <?= ($matricula['provincia'] ?? '') == 'SEGOVIA' ? 'selected' : '' ?>>SEGOVIA</option>
                            <option value="SEVILLA" <?= ($matricula['provincia'] ?? '') == 'SEVILLA' ? 'selected' : '' ?>>SEVILLA</option>
                            <option value="SORIA" <?= ($matricula['provincia'] ?? '') == 'SORIA' ? 'selected' : '' ?>>SORIA</option>
                            <option value="TARRAGONA" <?= ($matricula['provincia'] ?? '') == 'TARRAGONA' ? 'selected' : '' ?>>TARRAGONA</option>
                            <option value="TERUEL" <?= ($matricula['provincia'] ?? '') == 'TERUEL' ? 'selected' : '' ?>>TERUEL</option>
                            <option value="TOLEDO" <?= ($matricula['provincia'] ?? '') == 'TOLEDO' ? 'selected' : '' ?>>TOLEDO</option>
                            <option value="VALENCIA" <?= ($matricula['provincia'] ?? '') == 'VALENCIA' ? 'selected' : '' ?>>VALENCIA</option>
                            <option value="VALLADOLID" <?= ($matricula['provincia'] ?? '') == 'VALLADOLID' ? 'selected' : '' ?>>VALLADOLID</option>
                            <option value="VIZCAYA" <?= ($matricula['provincia'] ?? '') == 'VIZCAYA' ? 'selected' : '' ?>>VIZCAYA</option>
                            <option value="ZAMORA" <?= ($matricula['provincia'] ?? '') == 'ZAMORA' ? 'selected' : '' ?>>ZAMORA</option>
                            <option value="ZARAGOZA" <?= ($matricula['provincia'] ?? '') == 'ZARAGOZA' ? 'selected' : '' ?>>ZARAGOZA</option>
                        </select>
                    </div>
                </div>

                <div class="grid-form">
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($matricula['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Correo Electrónico</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($matricula['email'] ?? '') ?>">
                    </div>
                </div>
            </form>
        </div>

        <div id="tab-laborales" class="tab-panel <?= $active_tab === 'tab-laborales' ? '' : 'hidden' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_datos_laborales">
                
                <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                    <button type="button" class="btn-modern btn-outline" style="margin-right: 10px;">
                        Ficha Seguimiento
                    </button>
                    <button type="submit" class="btn-modern btn-primary-modern">
                        💾 Guardar Registro
                    </button>
                </div>

                <h3 class="form-section-title">Datos de la Empresa</h3>
                <div class="grid-form" style="grid-template-columns: 1fr 2fr;">
                    <div class="form-group">
                        <label>Buscar CIF</label>
                        <input type="text" id="buscar_cif" class="form-control" placeholder="Introduzca CIF...">
                    </div>
                    <div class="form-group">
                        <label>Empresa</label>
                        <select name="ultima_empresa_id" id="empresa_select" class="form-control">
                            <option value="">DESEMPLEADO [D00000001]</option>
                            <?php foreach($empresas as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= ($matricula['ultima_empresa_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="A. F. C. CONSULTING DEPORTIVO" <?= ($matricula['ultima_empresa_id'] ?? '') == 'A. F. C. CONSULTING DEPORTIVO' ? 'selected' : '' ?>>A. F. C. CONSULTING DEPORTIVO</option>
                            <option value="ACADEMIA CERVANTES , CARLOS AMEZ LAIZ CB" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ACADEMIA CERVANTES , CARLOS AMEZ LAIZ CB' ? 'selected' : '' ?>>ACADEMIA CERVANTES , CARLOS AMEZ LAIZ CB</option>
                            <option value="ACADEMIA FIPP" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ACADEMIA FIPP' ? 'selected' : '' ?>>ACADEMIA FIPP</option>
                            <option value="ACADEMIA SOCE S.L.U." <?= ($matricula['ultima_empresa_id'] ?? '') == 'ACADEMIA SOCE S.L.U.' ? 'selected' : '' ?>>ACADEMIA SOCE S.L.U.</option>
                            <option value="ACADEMIA TECNAS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ACADEMIA TECNAS' ? 'selected' : '' ?>>ACADEMIA TECNAS</option>
                            <option value="ACADEMIA VIGILANT S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'ACADEMIA VIGILANT S.L.' ? 'selected' : '' ?>>ACADEMIA VIGILANT S.L.</option>
                            <option value="ACADEMIA VISAN" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ACADEMIA VISAN' ? 'selected' : '' ?>>ACADEMIA VISAN</option>
                            <option value="ADAMS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ADAMS' ? 'selected' : '' ?>>ADAMS</option>
                            <option value="AE S. MARTIN" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AE S. MARTIN' ? 'selected' : '' ?>>AE S. MARTIN</option>
                            <option value="AEFOL EXPOELEARNING S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'AEFOL EXPOELEARNING S.L.' ? 'selected' : '' ?>>AEFOL EXPOELEARNING S.L.</option>
                            <option value="AESS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AESS' ? 'selected' : '' ?>>AESS</option>
                            <option value="AFA-FORMACION CONTINUA S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'AFA-FORMACION CONTINUA S.L.' ? 'selected' : '' ?>>AFA-FORMACION CONTINUA S.L.</option>
                            <option value="AGE" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AGE' ? 'selected' : '' ?>>AGE</option>
                            <option value="AMUSAL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AMUSAL' ? 'selected' : '' ?>>AMUSAL</option>
                            <option value="AREA FORMACION AULAS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AREA FORMACION AULAS' ? 'selected' : '' ?>>AREA FORMACION AULAS</option>
                            <option value="asimag servicios empresariales, s.l" <?= ($matricula['ultima_empresa_id'] ?? '') == 'asimag servicios empresariales, s.l' ? 'selected' : '' ?>>asimag servicios empresariales, s.l</option>
                            <option value="ASIMAG SERVICIOS EMPRESARIALES, S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'ASIMAG SERVICIOS EMPRESARIALES, S.L.' ? 'selected' : '' ?>>ASIMAG SERVICIOS EMPRESARIALES, S.L.</option>
                            <option value="Association Puerta de Alcalá" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Association Puerta de Alcalá' ? 'selected' : '' ?>>Association Puerta de Alcalá</option>
                            <option value="ATENTO TELESERVICIOS ESPAÑA, S.A." <?= ($matricula['ultima_empresa_id'] ?? '') == 'ATENTO TELESERVICIOS ESPAÑA, S.A.' ? 'selected' : '' ?>>ATENTO TELESERVICIOS ESPAÑA, S.A.</option>
                            <option value="AUDEMA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AUDEMA' ? 'selected' : '' ?>>AUDEMA</option>
                            <option value="AUTOESCUELA EMERITA S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'AUTOESCUELA EMERITA S.L.' ? 'selected' : '' ?>>AUTOESCUELA EMERITA S.L.</option>
                            <option value="AVEFOR ARAGÓN DAIDA PEREZ HERNANDEZ" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AVEFOR ARAGÓN DAIDA PEREZ HERNANDEZ' ? 'selected' : '' ?>>AVEFOR ARAGÓN DAIDA PEREZ HERNANDEZ</option>
                            <option value="AVIZOR, CENTRO SUPERIOR DE FORMACIÓN EN ESTUDIOS D" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AVIZOR, CENTRO SUPERIOR DE FORMACIÓN EN ESTUDIOS D' ? 'selected' : '' ?>>AVIZOR, CENTRO SUPERIOR DE FORMACIÓN EN ESTUDIOS D</option>
                            <option value="Ayuntamiento de Cajar" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Ayuntamiento de Cajar' ? 'selected' : '' ?>>Ayuntamiento de Cajar</option>
                            <option value="AZUVIS S.C.A" <?= ($matricula['ultima_empresa_id'] ?? '') == 'AZUVIS S.C.A' ? 'selected' : '' ?>>AZUVIS S.C.A</option>
                            <option value="BODYFACTORY SOMOSAGUAS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'BODYFACTORY SOMOSAGUAS' ? 'selected' : '' ?>>BODYFACTORY SOMOSAGUAS</option>
                            <option value="BOROXSPORT CLUB SPORT" <?= ($matricula['ultima_empresa_id'] ?? '') == 'BOROXSPORT CLUB SPORT' ? 'selected' : '' ?>>BOROXSPORT CLUB SPORT</option>
                            <option value="C/ CORCEGA,371" <?= ($matricula['ultima_empresa_id'] ?? '') == 'C/ CORCEGA,371' ? 'selected' : '' ?>>C/ CORCEGA,371</option>
                            <option value="CAD-SEGURIDAD" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CAD-SEGURIDAD' ? 'selected' : '' ?>>CAD-SEGURIDAD</option>
                            <option value="CENTRO DE ENSEÑANZAS PROFESIONALES Y TECNOLOGICAS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE ENSEÑANZAS PROFESIONALES Y TECNOLOGICAS' ? 'selected' : '' ?>>CENTRO DE ENSEÑANZAS PROFESIONALES Y TECNOLOGICAS</option>
                            <option value="Centro de Estudio Arsenio Toral S.A.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'Centro de Estudio Arsenio Toral S.A.L.' ? 'selected' : '' ?>>Centro de Estudio Arsenio Toral S.A.L.</option>
                            <option value="Centro de Estudio Arsenio Toral S.A.L.. 2012" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Centro de Estudio Arsenio Toral S.A.L.. 2012' ? 'selected' : '' ?>>Centro de Estudio Arsenio Toral S.A.L.. 2012</option>
                            <option value="CENTRO DE ESTUDIOS APPA SCL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE ESTUDIOS APPA SCL' ? 'selected' : '' ?>>CENTRO DE ESTUDIOS APPA SCL</option>
                            <option value="CENTRO DE ESTUDIOS DE FORMACION ALFER" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE ESTUDIOS DE FORMACION ALFER' ? 'selected' : '' ?>>CENTRO DE ESTUDIOS DE FORMACION ALFER</option>
                            <option value="CENTRO DE ESTUDIOS DE FORMACION ALFER S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE ESTUDIOS DE FORMACION ALFER S.L.' ? 'selected' : '' ?>>CENTRO DE ESTUDIOS DE FORMACION ALFER S.L.</option>
                            <option value="CENTRO DE ESTUDIOS LA ACADEMIA CB" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE ESTUDIOS LA ACADEMIA CB' ? 'selected' : '' ?>>CENTRO DE ESTUDIOS LA ACADEMIA CB</option>
                            <option value="Centro de Estudios y Experimentación de Obras Públ" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Centro de Estudios y Experimentación de Obras Públ' ? 'selected' : '' ?>>Centro de Estudios y Experimentación de Obras Públ</option>
                            <option value="CENTRO DE FORMACION ALFER" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE FORMACION ALFER' ? 'selected' : '' ?>>CENTRO DE FORMACION ALFER</option>
                            <option value="CENTRO DE FORMACION ARSENIO JIMENO" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE FORMACION ARSENIO JIMENO' ? 'selected' : '' ?>>CENTRO DE FORMACION ARSENIO JIMENO</option>
                            <option value="centro de formación oasis" <?= ($matricula['ultima_empresa_id'] ?? '') == 'centro de formación oasis' ? 'selected' : '' ?>>centro de formación oasis</option>
                            <option value="CENTRO DE FORMACION PRAXIS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE FORMACION PRAXIS' ? 'selected' : '' ?>>CENTRO DE FORMACION PRAXIS</option>
                            <option value="CENTRO DE FORMACION PRAXIS II" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO DE FORMACION PRAXIS II' ? 'selected' : '' ?>>CENTRO DE FORMACION PRAXIS II</option>
                            <option value="CENTRO EMPRESARIAL CEMEI" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CENTRO EMPRESARIAL CEMEI' ? 'selected' : '' ?>>CENTRO EMPRESARIAL CEMEI</option>
                            <option value="CEPAL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CEPAL' ? 'selected' : '' ?>>CEPAL</option>
                            <option value="CFI SEGURIDAD" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CFI SEGURIDAD' ? 'selected' : '' ?>>CFI SEGURIDAD</option>
                            <option value="CICE S.A" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CICE S.A' ? 'selected' : '' ?>>CICE S.A</option>
                            <option value="CIS-FORMACION ESPECIALIZADA SEGURIDAD-SALUD S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'CIS-FORMACION ESPECIALIZADA SEGURIDAD-SALUD S.L.' ? 'selected' : '' ?>>CIS-FORMACION ESPECIALIZADA SEGURIDAD-SALUD S.L.</option>
                            <option value="Ciudad Escuela de Formacion" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Ciudad Escuela de Formacion' ? 'selected' : '' ?>>Ciudad Escuela de Formacion</option>
                            <option value="CLUB DE GOLF GUADALMINA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CLUB DE GOLF GUADALMINA' ? 'selected' : '' ?>>CLUB DE GOLF GUADALMINA</option>
                            <option value="CLUB DE TENIS Y PADEL MONTEVERDE" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CLUB DE TENIS Y PADEL MONTEVERDE' ? 'selected' : '' ?>>CLUB DE TENIS Y PADEL MONTEVERDE</option>
                            <option value="Club Natació Barcelona" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Club Natació Barcelona' ? 'selected' : '' ?>>Club Natació Barcelona</option>
                            <option value="CLUB NAUTICO DE GANDIA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CLUB NAUTICO DE GANDIA' ? 'selected' : '' ?>>CLUB NAUTICO DE GANDIA</option>
                            <option value="COMERCIANTES DEL PONIENTE, S.A." <?= ($matricula['ultima_empresa_id'] ?? '') == 'COMERCIANTES DEL PONIENTE, S.A.' ? 'selected' : '' ?>>COMERCIANTES DEL PONIENTE, S.A.</option>
                            <option value="Consultores de Formacion" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Consultores de Formacion' ? 'selected' : '' ?>>Consultores de Formacion</option>
                            <option value="CONSULTORIA Y FORMACION BALBO S.L" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CONSULTORIA Y FORMACION BALBO S.L' ? 'selected' : '' ?>>CONSULTORIA Y FORMACION BALBO S.L</option>
                            <option value="CONTROL DE FORMACION" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CONTROL DE FORMACION' ? 'selected' : '' ?>>CONTROL DE FORMACION</option>
                            <option value="CREATI MOMENTUM" <?= ($matricula['ultima_empresa_id'] ?? '') == 'CREATI MOMENTUM' ? 'selected' : '' ?>>CREATI MOMENTUM</option>
                            <option value="D.D. SPORT FG S.L. (CIS)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'D.D. SPORT FG S.L. (CIS)' ? 'selected' : '' ?>>D.D. SPORT FG S.L. (CIS)</option>
                            <option value="Dedalo Proyectos XYZ (Vicar)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Dedalo Proyectos XYZ (Vicar)' ? 'selected' : '' ?>>Dedalo Proyectos XYZ (Vicar)</option>
                            <option value="EDIFICIO SINDICATOS (A CORUÑA)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'EDIFICIO SINDICATOS (A CORUÑA)' ? 'selected' : '' ?>>EDIFICIO SINDICATOS (A CORUÑA)</option>
                            <option value="EDITEFORMACION (Madrid)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'EDITEFORMACION (Madrid)' ? 'selected' : '' ?>>EDITEFORMACION (Madrid)</option>
                            <option value="EDITEFORMACION-MERCAOLID" <?= ($matricula['ultima_empresa_id'] ?? '') == 'EDITEFORMACION-MERCAOLID' ? 'selected' : '' ?>>EDITEFORMACION-MERCAOLID</option>
                            <option value="EDITRAIN SL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'EDITRAIN SL' ? 'selected' : '' ?>>EDITRAIN SL</option>
                            <option value="EDITRAIN, S.L. (P.E.LA FINCA)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'EDITRAIN, S.L. (P.E.LA FINCA)' ? 'selected' : '' ?>>EDITRAIN, S.L. (P.E.LA FINCA)</option>
                            <option value="El Ser Creativo SL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'El Ser Creativo SL' ? 'selected' : '' ?>>El Ser Creativo SL</option>
                            <option value="EL VENTAL DE OCASION S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'EL VENTAL DE OCASION S.L.' ? 'selected' : '' ?>>EL VENTAL DE OCASION S.L.</option>
                            <option value="ELOGOS, S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'ELOGOS, S.L.' ? 'selected' : '' ?>>ELOGOS, S.L.</option>
                            <option value="EMPRESA MIXTA DE SERVICIOS FUNERARIOS DE MADRID" <?= ($matricula['ultima_empresa_id'] ?? '') == 'EMPRESA MIXTA DE SERVICIOS FUNERARIOS DE MADRID' ? 'selected' : '' ?>>EMPRESA MIXTA DE SERVICIOS FUNERARIOS DE MADRID</option>
                            <option value="ENSEÑANZAS ORTHOS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ENSEÑANZAS ORTHOS' ? 'selected' : '' ?>>ENSEÑANZAS ORTHOS</option>
                            <option value="ESCUELA DE FORMACIÓN PROFESIONAL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ESCUELA DE FORMACIÓN PROFESIONAL' ? 'selected' : '' ?>>ESCUELA DE FORMACIÓN PROFESIONAL</option>
                            <option value="ESCUELA DE FORMACIÓN PROFESIONAL (Vícar)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ESCUELA DE FORMACIÓN PROFESIONAL (Vícar)' ? 'selected' : '' ?>>ESCUELA DE FORMACIÓN PROFESIONAL (Vícar)</option>
                            <option value="Escuela Internacional de Gerencia" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Escuela Internacional de Gerencia' ? 'selected' : '' ?>>Escuela Internacional de Gerencia</option>
                            <option value="ESTACION DISEÑO" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ESTACION DISEÑO' ? 'selected' : '' ?>>ESTACION DISEÑO</option>
                            <option value="ESTACION DISEÑO (Antiguo)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ESTACION DISEÑO (Antiguo)' ? 'selected' : '' ?>>ESTACION DISEÑO (Antiguo)</option>
                            <option value="EUROPEANQUALITY S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'EUROPEANQUALITY S.L.' ? 'selected' : '' ?>>EUROPEANQUALITY S.L.</option>
                            <option value="F.I.P.P" <?= ($matricula['ultima_empresa_id'] ?? '') == 'F.I.P.P' ? 'selected' : '' ?>>F.I.P.P</option>
                            <option value="FEDERAC. PROV. DE MINUSVALIDOS FISICOS DE CORDOBA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'FEDERAC. PROV. DE MINUSVALIDOS FISICOS DE CORDOBA' ? 'selected' : '' ?>>FEDERAC. PROV. DE MINUSVALIDOS FISICOS DE CORDOBA</option>
                            <option value="FESS LA SALLE" <?= ($matricula['ultima_empresa_id'] ?? '') == 'FESS LA SALLE' ? 'selected' : '' ?>>FESS LA SALLE</option>
                            <option value="FONDO DE PROMOCION Y DESARROLLO PROFESIONAL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'FONDO DE PROMOCION Y DESARROLLO PROFESIONAL' ? 'selected' : '' ?>>FONDO DE PROMOCION Y DESARROLLO PROFESIONAL</option>
                            <option value="FPDP" <?= ($matricula['ultima_empresa_id'] ?? '') == 'FPDP' ? 'selected' : '' ?>>FPDP</option>
                            <option value="FPDP-VALENCIA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'FPDP-VALENCIA' ? 'selected' : '' ?>>FPDP-VALENCIA</option>
                            <option value="FUNDACIÓN SAN VALERO" <?= ($matricula['ultima_empresa_id'] ?? '') == 'FUNDACIÓN SAN VALERO' ? 'selected' : '' ?>>FUNDACIÓN SAN VALERO</option>
                            <option value="GENERAL PLAN" <?= ($matricula['ultima_empresa_id'] ?? '') == 'GENERAL PLAN' ? 'selected' : '' ?>>GENERAL PLAN</option>
                            <option value="GESTIÓN DE LA EXCELENCIA Y COACHING APLICADO A LOS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'GESTIÓN DE LA EXCELENCIA Y COACHING APLICADO A LOS' ? 'selected' : '' ?>>GESTIÓN DE LA EXCELENCIA Y COACHING APLICADO A LOS</option>
                            <option value="Gimnasio Triunfo S.A." <?= ($matricula['ultima_empresa_id'] ?? '') == 'Gimnasio Triunfo S.A.' ? 'selected' : '' ?>>Gimnasio Triunfo S.A.</option>
                            <option value="Green Apple School" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Green Apple School' ? 'selected' : '' ?>>Green Apple School</option>
                            <option value="GREEN TAL S.A." <?= ($matricula['ultima_empresa_id'] ?? '') == 'GREEN TAL S.A.' ? 'selected' : '' ?>>GREEN TAL S.A.</option>
                            <option value="Grupo Coremsa" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Grupo Coremsa' ? 'selected' : '' ?>>Grupo Coremsa</option>
                            <option value="GRUPO DTM CONSULTING S.L.U." <?= ($matricula['ultima_empresa_id'] ?? '') == 'GRUPO DTM CONSULTING S.L.U.' ? 'selected' : '' ?>>GRUPO DTM CONSULTING S.L.U.</option>
                            <option value="GRUPO EDNE, S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'GRUPO EDNE, S.L.' ? 'selected' : '' ?>>GRUPO EDNE, S.L.</option>
                            <option value="GRUPO SUR RECICLAJE Y FORMACIÓN S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'GRUPO SUR RECICLAJE Y FORMACIÓN S.L.' ? 'selected' : '' ?>>GRUPO SUR RECICLAJE Y FORMACIÓN S.L.</option>
                            <option value="Hotel Avenida" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Hotel Avenida' ? 'selected' : '' ?>>Hotel Avenida</option>
                            <option value="IDFO" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IDFO' ? 'selected' : '' ?>>IDFO</option>
                            <option value="IFES" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES' ? 'selected' : '' ?>>IFES</option>
                            <option value="IFES ( ZARAGOZA)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES ( ZARAGOZA)' ? 'selected' : '' ?>>IFES ( ZARAGOZA)</option>
                            <option value="IFES (EUSKADI)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES (EUSKADI)' ? 'selected' : '' ?>>IFES (EUSKADI)</option>
                            <option value="IFES NAVARRA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES NAVARRA' ? 'selected' : '' ?>>IFES NAVARRA</option>
                            <option value="IFES UGT" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES UGT' ? 'selected' : '' ?>>IFES UGT</option>
                            <option value="IFES-CENTRO DE FORMACION ARSENIO JIMENO" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES-CENTRO DE FORMACION ARSENIO JIMENO' ? 'selected' : '' ?>>IFES-CENTRO DE FORMACION ARSENIO JIMENO</option>
                            <option value="IFES-SEVILLA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES-SEVILLA' ? 'selected' : '' ?>>IFES-SEVILLA</option>
                            <option value="IFES-UGT (ALICANTE)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'IFES-UGT (ALICANTE)' ? 'selected' : '' ?>>IFES-UGT (ALICANTE)</option>
                            <option value="INGAFOR" <?= ($matricula['ultima_empresa_id'] ?? '') == 'INGAFOR' ? 'selected' : '' ?>>INGAFOR</option>
                            <option value="INSFORCAN, S.L CENTRO DE ESTUDIOS EMPRESARIALES" <?= ($matricula['ultima_empresa_id'] ?? '') == 'INSFORCAN, S.L CENTRO DE ESTUDIOS EMPRESARIALES' ? 'selected' : '' ?>>INSFORCAN, S.L CENTRO DE ESTUDIOS EMPRESARIALES</option>
                            <option value="Instituto Educacion Secundaria Elaios" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Instituto Educacion Secundaria Elaios' ? 'selected' : '' ?>>Instituto Educacion Secundaria Elaios</option>
                            <option value="INSTITUTO FORMACION ESTUDIOS SOCIALES" <?= ($matricula['ultima_empresa_id'] ?? '') == 'INSTITUTO FORMACION ESTUDIOS SOCIALES' ? 'selected' : '' ?>>INSTITUTO FORMACION ESTUDIOS SOCIALES</option>
                            <option value="INSTITUTO MADRILEÑO DE FORMACION S.L" <?= ($matricula['ultima_empresa_id'] ?? '') == 'INSTITUTO MADRILEÑO DE FORMACION S.L' ? 'selected' : '' ?>>INSTITUTO MADRILEÑO DE FORMACION S.L</option>
                            <option value="LA MIRADA DIGITAL" <?= ($matricula['ultima_empresa_id'] ?? '') == 'LA MIRADA DIGITAL' ? 'selected' : '' ?>>LA MIRADA DIGITAL</option>
                            <option value="LA MIRADA DIGITAL, S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'LA MIRADA DIGITAL, S.L.' ? 'selected' : '' ?>>LA MIRADA DIGITAL, S.L.</option>
                            <option value="MAREN" <?= ($matricula['ultima_empresa_id'] ?? '') == 'MAREN' ? 'selected' : '' ?>>MAREN</option>
                            <option value="MARSDIGITAL S.L (antiguo)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'MARSDIGITAL S.L (antiguo)' ? 'selected' : '' ?>>MARSDIGITAL S.L (antiguo)</option>
                            <option value="Marsdigital S.L (Granada )" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Marsdigital S.L (Granada )' ? 'selected' : '' ?>>Marsdigital S.L (Granada )</option>
                            <option value="Marsdigital S.L. (Barcelona)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Marsdigital S.L. (Barcelona)' ? 'selected' : '' ?>>Marsdigital S.L. (Barcelona)</option>
                            <option value="Marsdigital S.L. (la Mirada)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Marsdigital S.L. (la Mirada)' ? 'selected' : '' ?>>Marsdigital S.L. (la Mirada)</option>
                            <option value="MASTER (CENTRO DE ESTUDIOS - TIENDA DE INFORMATICA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'MASTER (CENTRO DE ESTUDIOS - TIENDA DE INFORMATICA' ? 'selected' : '' ?>>MASTER (CENTRO DE ESTUDIOS - TIENDA DE INFORMATICA</option>
                            <option value="MBNA EUROPE BANK LIMITED ESPAÑA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'MBNA EUROPE BANK LIMITED ESPAÑA' ? 'selected' : '' ?>>MBNA EUROPE BANK LIMITED ESPAÑA</option>
                            <option value="Método Consultores, S.L" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Método Consultores, S.L' ? 'selected' : '' ?>>Método Consultores, S.L</option>
                            <option value="METODO ESTUDIOS CONSULTORES ( ARENAL)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'METODO ESTUDIOS CONSULTORES ( ARENAL)' ? 'selected' : '' ?>>METODO ESTUDIOS CONSULTORES ( ARENAL)</option>
                            <option value="METODO ESTUDIOS CONSULTORES, S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'METODO ESTUDIOS CONSULTORES, S.L.' ? 'selected' : '' ?>>METODO ESTUDIOS CONSULTORES, S.L.</option>
                            <option value="METODO ESTUDIOS CONSULTORES,S.L (C/DIEGO)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'METODO ESTUDIOS CONSULTORES,S.L (C/DIEGO)' ? 'selected' : '' ?>>METODO ESTUDIOS CONSULTORES,S.L (C/DIEGO)</option>
                            <option value="MGI NEVA CENTROS DE FORMACION" <?= ($matricula['ultima_empresa_id'] ?? '') == 'MGI NEVA CENTROS DE FORMACION' ? 'selected' : '' ?>>MGI NEVA CENTROS DE FORMACION</option>
                            <option value="MORTUALBA SCL ( TANATORIO MUNICIPAL ALBACETE)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'MORTUALBA SCL ( TANATORIO MUNICIPAL ALBACETE)' ? 'selected' : '' ?>>MORTUALBA SCL ( TANATORIO MUNICIPAL ALBACETE)</option>
                            <option value="OROVIDA S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'OROVIDA S.L.' ? 'selected' : '' ?>>OROVIDA S.L.</option>
                            <option value="PARCESA, PARQUES DE LA PAZ S.A" <?= ($matricula['ultima_empresa_id'] ?? '') == 'PARCESA, PARQUES DE LA PAZ S.A' ? 'selected' : '' ?>>PARCESA, PARQUES DE LA PAZ S.A</option>
                            <option value="PARCESA, PARQUES DE LA PAZ S.A ( segundo centro)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'PARCESA, PARQUES DE LA PAZ S.A ( segundo centro)' ? 'selected' : '' ?>>PARCESA, PARQUES DE LA PAZ S.A ( segundo centro)</option>
                            <option value="PARCESA, PARQUES DE LA PAZ S.A ( tercer centro)" <?= ($matricula['ultima_empresa_id'] ?? '') == 'PARCESA, PARQUES DE LA PAZ S.A ( tercer centro)' ? 'selected' : '' ?>>PARCESA, PARQUES DE LA PAZ S.A ( tercer centro)</option>
                            <option value="POLIDEPORTIVO LAS CRUCES" <?= ($matricula['ultima_empresa_id'] ?? '') == 'POLIDEPORTIVO LAS CRUCES' ? 'selected' : '' ?>>POLIDEPORTIVO LAS CRUCES</option>
                            <option value="PRODUCCIONES HINOJOSA BECERRA MEDIA2 S.L" <?= ($matricula['ultima_empresa_id'] ?? '') == 'PRODUCCIONES HINOJOSA BECERRA MEDIA2 S.L' ? 'selected' : '' ?>>PRODUCCIONES HINOJOSA BECERRA MEDIA2 S.L</option>
                            <option value="PROINTEC S.A." <?= ($matricula['ultima_empresa_id'] ?? '') == 'PROINTEC S.A.' ? 'selected' : '' ?>>PROINTEC S.A.</option>
                            <option value="PROMAX S.L.L" <?= ($matricula['ultima_empresa_id'] ?? '') == 'PROMAX S.L.L' ? 'selected' : '' ?>>PROMAX S.L.L</option>
                            <option value="Remo RCNGandia" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Remo RCNGandia' ? 'selected' : '' ?>>Remo RCNGandia</option>
                            <option value="SANTAGADEA GESTIÓN S.L. ( CENTRO DE DEPORTIVO DEHESA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'SANTAGADEA GESTIÓN S.L. ( CENTRO DE DEPORTIVO DEHESA' ? 'selected' : '' ?>>SANTAGADEA GESTIÓN S.L. ( CENTRO DE DEPORTIVO DEHESA</option>
                            <option value="SEGURIDAD CERES S.A." <?= ($matricula['ultima_empresa_id'] ?? '') == 'SEGURIDAD CERES S.A.' ? 'selected' : '' ?>>SEGURIDAD CERES S.A.</option>
                            <option value="SERVICIOS FUNERARIOS DE BARCELONA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'SERVICIOS FUNERARIOS DE BARCELONA' ? 'selected' : '' ?>>SERVICIOS FUNERARIOS DE BARCELONA</option>
                            <option value="SERVICIOS SECURITAS S.A." <?= ($matricula['ultima_empresa_id'] ?? '') == 'SERVICIOS SECURITAS S.A.' ? 'selected' : '' ?>>SERVICIOS SECURITAS S.A.</option>
                            <option value="Soom Management S.L" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Soom Management S.L' ? 'selected' : '' ?>>Soom Management S.L</option>
                            <option value="SQUASH GYM SIERRA S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'SQUASH GYM SIERRA S.L.' ? 'selected' : '' ?>>SQUASH GYM SIERRA S.L.</option>
                            <option value="Swiss Sports Club" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Swiss Sports Club' ? 'selected' : '' ?>>Swiss Sports Club</option>
                            <option value="TALKING ENGLISH" <?= ($matricula['ultima_empresa_id'] ?? '') == 'TALKING ENGLISH' ? 'selected' : '' ?>>TALKING ENGLISH</option>
                            <option value="TANATORIO MONTSERRAT TRUYOLS" <?= ($matricula['ultima_empresa_id'] ?? '') == 'TANATORIO MONTSERRAT TRUYOLS' ? 'selected' : '' ?>>TANATORIO MONTSERRAT TRUYOLS</option>
                            <option value="TANATORIO MUNICIPAL CIUDAD DE VALENCIA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'TANATORIO MUNICIPAL CIUDAD DE VALENCIA' ? 'selected' : '' ?>>TANATORIO MUNICIPAL CIUDAD DE VALENCIA</option>
                            <option value="TANATORIO SAN LAZARO S.L." <?= ($matricula['ultima_empresa_id'] ?? '') == 'TANATORIO SAN LAZARO S.L.' ? 'selected' : '' ?>>TANATORIO SAN LAZARO S.L.</option>
                            <option value="TANATORIO SERVICIOS FUNERARIOS SAGUNTO. FUALRUB S." <?= ($matricula['ultima_empresa_id'] ?? '') == 'TANATORIO SERVICIOS FUNERARIOS SAGUNTO. FUALRUB S.' ? 'selected' : '' ?>>TANATORIO SERVICIOS FUNERARIOS SAGUNTO. FUALRUB S.</option>
                            <option value="TANATORIO TORRERO" <?= ($matricula['ultima_empresa_id'] ?? '') == 'TANATORIO TORRERO' ? 'selected' : '' ?>>TANATORIO TORRERO</option>
                            <option value="TANATORIO VELATORIO LUCENSES" <?= ($matricula['ultima_empresa_id'] ?? '') == 'TANATORIO VELATORIO LUCENSES' ? 'selected' : '' ?>>TANATORIO VELATORIO LUCENSES</option>
                            <option value="Tecnas" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Tecnas' ? 'selected' : '' ?>>Tecnas</option>
                            <option value="TWENTY4HELP KNOWLEDGE SERVICE ESPAÑA" <?= ($matricula['ultima_empresa_id'] ?? '') == 'TWENTY4HELP KNOWLEDGE SERVICE ESPAÑA' ? 'selected' : '' ?>>TWENTY4HELP KNOWLEDGE SERVICE ESPAÑA</option>
                            <option value="ULTRAGYM/BODY FACTORY" <?= ($matricula['ultima_empresa_id'] ?? '') == 'ULTRAGYM/BODY FACTORY' ? 'selected' : '' ?>>ULTRAGYM/BODY FACTORY</option>
                            <option value="Universidad de Granada" <?= ($matricula['ultima_empresa_id'] ?? '') == 'Universidad de Granada' ? 'selected' : '' ?>>Universidad de Granada</option>
                            <option value="VALLADOLID 1402 S.L. ESCUELA DE SEGURIDAD" <?= ($matricula['ultima_empresa_id'] ?? '') == 'VALLADOLID 1402 S.L. ESCUELA DE SEGURIDAD' ? 'selected' : '' ?>>VALLADOLID 1402 S.L. ESCUELA DE SEGURIDAD</option>
                            <option value="vigilantes" <?= ($matricula['ultima_empresa_id'] ?? '') == 'vigilantes' ? 'selected' : '' ?>>vigilantes</option>
                        </select>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label>Centro de trabajo</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" name="centro_trabajo" class="form-control" value="<?= htmlspecialchars($matricula['centro_trabajo'] ?? '1') ?>" style="width: 100px;">
                            <span style="font-size: 1.2rem; cursor: pointer;">📋</span>
                        </div>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.6rem;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="no_valido" value="1" style="width: 16px; height: 16px;">
                            <span style="font-weight: 600; color: #475569;">No válido</span>
                        </label>
                    </div>
                </div>

                <h3 class="form-section-title" style="margin-top: 2rem;">Situación Laboral</h3>
                <div class="grid-form" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>Colectivo</label>
                        <select name="colectivo" class="form-control">
                            <option value=""></option>
                            <option value="Administración pública" <?= ($matricula['colectivo'] ?? '') == 'Administración pública' ? 'selected' : '' ?>>Administración pública</option>
                            <option value="Cuidadores no profesionales de las personas en situación de dependencia" <?= ($matricula['colectivo'] ?? '') == 'Cuidadores no profesionales de las personas en situación de dependencia' ? 'selected' : '' ?>>Cuidadores no profesionales de las personas en situación de dependencia</option>
                            <option value="Empleado hogar" <?= ($matricula['colectivo'] ?? '') == 'Empleado hogar' ? 'selected' : '' ?>>Empleado hogar</option>
                            <option value="ERE (Art. 51 y 52 del Estatuto de los Trabajadores)" <?= ($matricula['colectivo'] ?? '') == 'ERE (Art. 51 y 52 del Estatuto de los Trabajadores)' ? 'selected' : '' ?>>ERE (Art. 51 y 52 del Estatuto de los Trabajadores)</option>
                            <option value="ERTE (Art. 47 del Estatuto de los Trabajadores)" <?= ($matricula['colectivo'] ?? '') == 'ERTE (Art. 47 del Estatuto de los Trabajadores)' ? 'selected' : '' ?>>ERTE (Art. 47 del Estatuto de los Trabajadores)</option>
                            <option value="Fijos discontinuos en periodo de no ocupación" <?= ($matricula['colectivo'] ?? '') == 'Fijos discontinuos en periodo de no ocupación' ? 'selected' : '' ?>>Fijos discontinuos en periodo de no ocupación</option>
                            <option value="Mutualistas de Colegios Profesionales no incluidos como autónomos" <?= ($matricula['colectivo'] ?? '') == 'Mutualistas de Colegios Profesionales no incluidos como autónomos' ? 'selected' : '' ?>>Mutualistas de Colegios Profesionales no incluidos como autónomos</option>
                            <option value="Persona actualmente desempleada que anteriormente ha estado en situación de ERTE." <?= ($matricula['colectivo'] ?? '') == 'Persona actualmente desempleada que anteriormente ha estado en situación de ERTE.' ? 'selected' : '' ?>>Persona actualmente desempleada que anteriormente ha estado en situación de ERTE.</option>
                            <option value="Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE." <?= ($matricula['colectivo'] ?? '') == 'Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE.' ? 'selected' : '' ?>>Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE.</option>
                            <option value="Régimen especial agrario por cuenta ajena" <?= ($matricula['colectivo'] ?? '') == 'Régimen especial agrario por cuenta ajena' ? 'selected' : '' ?>>Régimen especial agrario por cuenta ajena</option>
                            <option value="Régimen especial agrario por cuenta propia" <?= ($matricula['colectivo'] ?? '') == 'Régimen especial agrario por cuenta propia' ? 'selected' : '' ?>>Régimen especial agrario por cuenta propia</option>
                            <option value="Régimen especial autónomos" <?= ($matricula['colectivo'] ?? '') == 'Régimen especial autónomos' ? 'selected' : '' ?>>Régimen especial autónomos</option>
                            <option value="Régimen general" <?= ($matricula['colectivo'] ?? '') == 'Régimen general' ? 'selected' : '' ?>>Régimen general</option>
                            <option value="Regulación de empleo en periodos de no ocupación" <?= ($matricula['colectivo'] ?? '') == 'Regulación de empleo en periodos de no ocupación' ? 'selected' : '' ?>>Regulación de empleo en periodos de no ocupación</option>
                            <option value="Trabajador con contrato a tiempo parcial" <?= ($matricula['colectivo'] ?? '') == 'Trabajador con contrato a tiempo parcial' ? 'selected' : '' ?>>Trabajador con contrato a tiempo parcial</option>
                            <option value="Trabajador con contrato temporal" <?= ($matricula['colectivo'] ?? '') == 'Trabajador con contrato temporal' ? 'selected' : '' ?>>Trabajador con contrato temporal</option>
                            <option value="Trabajadores a tiempo parcial de carácter indefinido con trabajos discontinuos en sus periodos de no ocupación" <?= ($matricula['colectivo'] ?? '') == 'Trabajadores a tiempo parcial de carácter indefinido con trabajos discontinuos en sus periodos de no ocupación' ? 'selected' : '' ?>>Trabajadores a tiempo parcial de carácter indefinido con trabajos discontinuos en sus periodos de no ocupación</option>
                            <option value="Trabajadores con convenio especial con la Seguridad Social" <?= ($matricula['colectivo'] ?? '') == 'Trabajadores con convenio especial con la Seguridad Social' ? 'selected' : '' ?>>Trabajadores con convenio especial con la Seguridad Social</option>
                            <option value="Trabajadores con relaciones laborales de carácter especial que se recogen en el art.2 del Estatuto de los Trabajadores" <?= ($matricula['colectivo'] ?? '') == 'Trabajadores con relaciones laborales de carácter especial que se recogen en el art.2 del Estatuto de los Trabajadores' ? 'selected' : '' ?>>Trabajadores con relaciones laborales de carácter especial que se recogen en el art.2 del Estatuto de los Trabajadores</option>
                            <option value="Trabajadores incluidos en el Régimen especial del mar" <?= ($matricula['colectivo'] ?? '') == 'Trabajadores incluidos en el Régimen especial del mar' ? 'selected' : '' ?>>Trabajadores incluidos en el Régimen especial del mar</option>
                        </select>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="form-group">
                        <label>Desempleado larga duración</label>
                        <select name="desempleado_larga_duracion" class="form-control">
                            <option value="NO">NO</option>
                            <option value="SI">SI</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Parado selección SEPE</label>
                        <select name="parado_sepe" class="form-control">
                            <option value="NO">NO</option>
                            <option value="SI">SI</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Conductor</label>
                        <select name="conductor" class="form-control">
                            <option value="NO">NO</option>
                            <option value="SI">SI</option>
                        </select>
                    </div>
                </div>

                <h3 class="form-section-title" style="margin-top: 2rem;">Ocupación y Puesto</h3>
                <div class="grid-form" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>Ocupación</label>
                        <select name="ocupacion" class="form-control">
                            <option value=""></option>
                            <option value="Artesanos y trabajadores cualificados de las industrias manufactureras y la construcción" <?= ($matricula['ocupacion'] ?? '') == 'Artesanos y trabajadores cualificados de las industrias manufactureras y la construcción' ? 'selected' : '' ?>>Artesanos y trabajadores cualificados de las industrias manufactureras y la construcción</option>
                            <option value="Directores y gerentes" <?= ($matricula['ocupacion'] ?? '') == 'Directores y gerentes' ? 'selected' : '' ?>>Directores y gerentes</option>
                            <option value="Empleados contables, administrativos y otros empleados de oficina" <?= ($matricula['ocupacion'] ?? '') == 'Empleados contables, administrativos y otros empleados de oficina' ? 'selected' : '' ?>>Empleados contables, administrativos y otros empleados de oficina</option>
                            <option value="Ocupaciones elementales" <?= ($matricula['ocupacion'] ?? '') == 'Ocupaciones elementales' ? 'selected' : '' ?>>Ocupaciones elementales</option>
                            <option value="Ocupaciones militares" <?= ($matricula['ocupacion'] ?? '') == 'Ocupaciones militares' ? 'selected' : '' ?>>Ocupaciones militares</option>
                            <option value="Operadores de instalaciones y maquinaria y montadores" <?= ($matricula['ocupacion'] ?? '') == 'Operadores de instalaciones y maquinaria y montadores' ? 'selected' : '' ?>>Operadores de instalaciones y maquinaria y montadores</option>
                            <option value="Técnicos profesionales de apoyo" <?= ($matricula['ocupacion'] ?? '') == 'Técnicos profesionales de apoyo' ? 'selected' : '' ?>>Técnicos profesionales de apoyo</option>
                            <option value="Técnicos y profesionales científicos e intelectuales" <?= ($matricula['ocupacion'] ?? '') == 'Técnicos y profesionales científicos e intelectuales' ? 'selected' : '' ?>>Técnicos y profesionales científicos e intelectuales</option>
                            <option value="Trabaj. cualificado agrícola, ganadero, forestal y pesquero" <?= ($matricula['ocupacion'] ?? '') == 'Trabaj. cualificado agrícola, ganadero, forestal y pesquero' ? 'selected' : '' ?>>Trabaj. cualificado agrícola, ganadero, forestal y pesquero</option>
                            <option value="Trabaj. de restauración, personales, protección y vendedores" <?= ($matricula['ocupacion'] ?? '') == 'Trabaj. de restauración, personales, protección y vendedores' ? 'selected' : '' ?>>Trabaj. de restauración, personales, protección y vendedores</option>
                        </select>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 2fr 1fr 1fr 1fr;">
                    <div class="form-group">
                        <label>Puesto de trabajo SEPE</label>
                        <input type="text" name="puesto_sepe" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Categoría Profesional</label>
                        <select name="categoria_profesional" class="form-control">
                            <option value=""></option>
                            <option value="Directivo" <?= ($matricula['categoria_profesional'] ?? '') == 'Directivo' ? 'selected' : '' ?>>Directivo</option>
                            <option value="Mando Intermedio" <?= ($matricula['categoria_profesional'] ?? '') == 'Mando Intermedio' ? 'selected' : '' ?>>Mando Intermedio</option>
                            <option value="Técnico especializado" <?= ($matricula['categoria_profesional'] ?? '') == 'Técnico especializado' ? 'selected' : '' ?>>Técnico especializado</option>
                            <option value="Trabajador cualificado" <?= ($matricula['categoria_profesional'] ?? '') == 'Trabajador cualificado' ? 'selected' : '' ?>>Trabajador cualificado</option>
                            <option value="Trabajador no cualificado" <?= ($matricula['categoria_profesional'] ?? '') == 'Trabajador no cualificado' ? 'selected' : '' ?>>Trabajador no cualificado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Área Funcional</label>
                        <select name="area_funcional" class="form-control">
                            <option value=""></option>
                            <option value="Administración" <?= ($matricula['area_funcional'] ?? '') == 'Administración' ? 'selected' : '' ?>>Administración</option>
                            <option value="Comercial" <?= ($matricula['area_funcional'] ?? '') == 'Comercial' ? 'selected' : '' ?>>Comercial</option>
                            <option value="Dirección" <?= ($matricula['area_funcional'] ?? '') == 'Dirección' ? 'selected' : '' ?>>Dirección</option>
                            <option value="Mantenimiento" <?= ($matricula['area_funcional'] ?? '') == 'Mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                            <option value="Producción" <?= ($matricula['area_funcional'] ?? '') == 'Producción' ? 'selected' : '' ?>>Producción</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Antigüedad</label>
                        <input type="date" name="antiguedad" class="form-control">
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label>Grupo Cotización</label>
                        <select name="grupo_cotizacion" class="form-control">
                            <option value=""></option>
                            <option value="1.- Ingenieros y licenciados" <?= ($matricula['grupo_cotizacion'] ?? '') == '1.- Ingenieros y licenciados' ? 'selected' : '' ?>>1.- Ingenieros y licenciados</option>
                            <option value="2.- Ingenieros técnicos, peritos y Aytes. titulados" <?= ($matricula['grupo_cotizacion'] ?? '') == '2.- Ingenieros técnicos, peritos y Aytes. titulados' ? 'selected' : '' ?>>2.- Ingenieros técnicos, peritos y Aytes. titulados</option>
                            <option value="3.- Jefes Advos. y de taller" <?= ($matricula['grupo_cotizacion'] ?? '') == '3.- Jefes Advos. y de taller' ? 'selected' : '' ?>>3.- Jefes Advos. y de taller</option>
                            <option value="4.- Ayudantes no titulados" <?= ($matricula['grupo_cotizacion'] ?? '') == '4.- Ayudantes no titulados' ? 'selected' : '' ?>>4.- Ayudantes no titulados</option>
                            <option value="5.- Oficiales administrativos" <?= ($matricula['grupo_cotizacion'] ?? '') == '5.- Oficiales administrativos' ? 'selected' : '' ?>>5.- Oficiales administrativos</option>
                            <option value="6.- Subalternos" <?= ($matricula['grupo_cotizacion'] ?? '') == '6.- Subalternos' ? 'selected' : '' ?>>6.- Subalternos</option>
                            <option value="7.- Auxiliares administrativos" <?= ($matricula['grupo_cotizacion'] ?? '') == '7.- Auxiliares administrativos' ? 'selected' : '' ?>>7.- Auxiliares administrativos</option>
                            <option value="8.- Oficiales de primera y segunda" <?= ($matricula['grupo_cotizacion'] ?? '') == '8.- Oficiales de primera y segunda' ? 'selected' : '' ?>>8.- Oficiales de primera y segunda</option>
                            <option value="9.- Oficiales de tercera y especialistas" <?= ($matricula['grupo_cotizacion'] ?? '') == '9.- Oficiales de tercera y especialistas' ? 'selected' : '' ?>>9.- Oficiales de tercera y especialistas</option>
                            <option value="10.- Peones" <?= ($matricula['grupo_cotizacion'] ?? '') == '10.- Peones' ? 'selected' : '' ?>>10.- Peones</option>
                            <option value="11.- Trabajadores menores de 18 años" <?= ($matricula['grupo_cotizacion'] ?? '') == '11.- Trabajadores menores de 18 años' ? 'selected' : '' ?>>11.- Trabajadores menores de 18 años</option>
                            <option value="Trabajadores mayores de 18 años no cualif." <?= ($matricula['grupo_cotizacion'] ?? '') == 'Trabajadores mayores de 18 años no cualif.' ? 'selected' : '' ?>>Trabajadores mayores de 18 años no cualif.</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contrato</label>
                        <select name="contrato" class="form-control">
                            <option value=""></option>
                            <option value="Contrato en prácticas" <?= ($matricula['contrato'] ?? '') == 'Contrato en prácticas' ? 'selected' : '' ?>>Contrato en prácticas</option>
                            <option value="Contrato formación" <?= ($matricula['contrato'] ?? '') == 'Contrato formación' ? 'selected' : '' ?>>Contrato formación</option>
                            <option value="Fijo-discontinuo" <?= ($matricula['contrato'] ?? '') == 'Fijo-discontinuo' ? 'selected' : '' ?>>Fijo-discontinuo</option>
                            <option value="Indefinido tiempo completo" <?= ($matricula['contrato'] ?? '') == 'Indefinido tiempo completo' ? 'selected' : '' ?>>Indefinido tiempo completo</option>
                            <option value="Indefinido tiempo parcial" <?= ($matricula['contrato'] ?? '') == 'Indefinido tiempo parcial' ? 'selected' : '' ?>>Indefinido tiempo parcial</option>
                            <option value="Otro" <?= ($matricula['contrato'] ?? '') == 'Otro' ? 'selected' : '' ?>>Otro</option>
                            <option value="Temporal circunstancias producc." <?= ($matricula['contrato'] ?? '') == 'Temporal circunstancias producc.' ? 'selected' : '' ?>>Temporal circunstancias producc.</option>
                            <option value="Temporal media jornada" <?= ($matricula['contrato'] ?? '') == 'Temporal media jornada' ? 'selected' : '' ?>>Temporal media jornada</option>
                            <option value="Temporal obra-servicio" <?= ($matricula['contrato'] ?? '') == 'Temporal obra-servicio' ? 'selected' : '' ?>>Temporal obra-servicio</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div id="tab-curso" class="tab-panel <?= $active_tab === 'tab-curso' ? '' : 'hidden' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_datos_curso">
                
                <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                    <button type="submit" class="btn-modern btn-primary-modern">
                        💾 Guardar Registro
                    </button>
                </div>

                <h3 class="form-section-title">Asignación de Curso</h3>
                <div class="grid-form" style="grid-template-columns: 1fr auto;">
                    <div class="form-group" style="grid-column: 1;">
                        <label>Plan</label>
                        <select name="plan_id" class="form-control">
                            <option value="">Seleccione Plan</option>
                            <?php foreach($planes as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($matricula['matricula_plan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.6rem; grid-column: 2;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="validar_plan" value="1" style="width: 16px; height: 16px;">
                            <span style="font-weight: 600; color: #475569;">Validar Plan</span>
                        </label>
                    </div>
                </div>
                <div class="grid-form" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>Curso</label>
                        <select name="curso_id" class="form-control">
                            <option value=""><?= htmlspecialchars($matricula['curso_titulo'] ?? 'Seleccione Curso') ?></option>
                        </select>
                    </div>
                </div>

                <h3 class="form-section-title" style="margin-top: 2rem;">Seguimiento y Estado</h3>
                <div class="grid-form" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="form-group">
                        <label>Comercial</label>
                        <select name="comercial_id" class="form-control">
                            <option value="">Seleccione Comercial...</option>
                            <?php foreach ($comerciales as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($matricula['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.6rem;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="captado_ugt" value="1" <?= !empty($matricula['captado_ugt']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            <span style="font-weight: 600; color: #475569;">Captado UGT</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Estados Anteriores</label>
                        <select name="estados_anteriores" class="form-control">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr;">
                    <div class="form-group" style="grid-column: span 1;">
                        <label style="font-size: 0.75rem;">Estados anteriores SEPE</label>
                        <select name="estados_anteriores_sepe" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Estado nuevo</label>
                        <select name="estado_nuevo" class="form-control">
                            <option value="Inscrito" <?= ($matricula['estado'] ?? '') == 'Inscrito' ? 'selected' : '' ?>>Inscrito</option>
                            <option value="Activo" <?= ($matricula['estado'] ?? '') == 'Activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="Finalizada" <?= ($matricula['estado'] ?? '') == 'Finalizada' ? 'selected' : '' ?>>Finalizada</option>
                            <option value="Baja" <?= ($matricula['estado'] ?? '') == 'Baja' ? 'selected' : '' ?>>Baja</option>
                            <option value="Cancelada" <?= ($matricula['estado'] ?? '') == 'Cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Prioridad</label>
                        <input type="text" name="prioridad" class="form-control" value="<?= htmlspecialchars($matricula['prioridad'] ?? $matricula['af_prioridad'] ?? '1') ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Estado para SEPE</label>
                        <select name="estado_sepe" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Fecha abandono</label>
                        <input type="date" name="fecha_abandono" class="form-control" value="<?= htmlspecialchars($matricula['fecha_abandono'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Exento prácticas</label>
                        <select name="exento_practicas" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>
                </div>

                <!-- Flags / Switches -->
                <div class="actions-bar" style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #1e40af; font-weight: 600;">
                        <input type="checkbox" name="enviar_mail" <?= !empty($matricula['enviar_emails']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Enviar mail automáticos
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #b91c1c; font-weight: 600;">
                        <input type="checkbox" name="no_preinscrito" value="1" <?= !empty($matricula['no_preinscrito']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> No volver preinscrito
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #b91c1c; font-weight: 600;">
                        <input type="checkbox" name="bloqueado" <?= !empty($matricula['bloqueado']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> BLOQUEADO
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #b91c1c; font-weight: 600;">
                        <input type="checkbox" name="no_desmatricular" value="1" <?= !empty($matricula['no_desmatricular']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> NO DESMATRICULAR
                    </label>
                    
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">CERTIFICABLES:</label>
                        <select name="certificables" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value="SI" <?= ($matricula['certificables'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                            <option value="NO" <?= ($matricula['certificables'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">FACTURABLES:</label>
                        <select name="facturables" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value=""></option>
                            <option value="SI" <?= ($matricula['facturables'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                            <option value="NO" <?= ($matricula['facturables'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">ANULAR para SEPE:</label>
                        <select name="anular_sepe" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value="NO" <?= ($matricula['anular_sepe'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                            <option value="SI" <?= ($matricula['anular_sepe'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">Evaluación TIC:</label>
                        <select name="evaluacion_tic" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value=""></option>
                            <option value="SI" <?= ($matricula['evaluacion_tic'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                            <option value="NO" <?= ($matricula['evaluacion_tic'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <button type="button" class="btn-modern btn-outline" style="color: #b91c1c; border-color: #fca5a5;">
                        Baja Plataforma
                    </button>
                </div>

                <!-- Info Box -->
                <div style="background: #f1f5f9; padding: 1rem; border-left: 4px solid #3b82f6; border-radius: 4px; margin-bottom: 2rem; font-weight: 500; color: #334155;">
                    Este alumno ha realizado <span style="background: white; padding: 2px 6px; border: 1px solid #cbd5e1; border-radius: 4px;">--</span> h de formación en esta convocatoria, distribuidas en <span style="background: white; padding: 2px 6px; border: 1px solid #cbd5e1; border-radius: 4px;">--</span> cursos. (Máximo permitido: 5000 h) || Inscrito el <?= !empty($matricula['creado_en']) ? date('d/m/Y', strtotime($matricula['creado_en'])) : date('d/m/Y') ?>
                </div>

                <h3 class="form-section-title">Comentarios y Observaciones</h3>
                <div class="grid-form" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>Prefiere las fechas:</label>
                        <textarea name="preferencia_fechas" class="form-control" rows="2"><?= htmlspecialchars($matricula['preferencia_fechas'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Observaciones:</label>
                        <textarea name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($matricula['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>

                <h3 class="form-section-title" style="margin-top: 2rem;">Grupo y Bajas</h3>
                <div class="grid-form" style="grid-template-columns: 1fr 2fr;">
                    <div class="form-group">
                        <label>Código grupo</label>
                        <select name="grupo_id" class="form-control">
                            <option value="">Seleccione Grupo...</option>
                            <?php foreach ($todos_grupos as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= ($matricula['grupo_id'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                                    [<?= htmlspecialchars($g['curso_codigo'] ?? '') ?>] Grupo <?= htmlspecialchars($g['numero_grupo'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 1.5rem; font-weight: 600; color: #2563eb;">
                        <?= htmlspecialchars($matricula['af_abreviatura'] ?? '') ?>-G<?= htmlspecialchars($matricula['numero_grupo'] ?? '') ?>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label>Si el alumno causa baja o abandono en el curso, indica aquí el motivo:</label>
                        <select name="motivo_baja" class="form-control">
                            <option value=""></option>
                            <?php
                            $motivos = ["Voluntaria", "Incompatibilidad horaria", "Trabajo", "Enfermedad", "Otros"];
                            if (!empty($matricula['motivo_baja']) && !in_array($matricula['motivo_baja'], $motivos)) {
                                $motivos[] = $matricula['motivo_baja'];
                            }
                            foreach ($motivos as $m):
                            ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($matricula['motivo_baja'] ?? '') == $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Motivo abandono para el SEPE:</label>
                        <select name="motivo_sepe" class="form-control">
                            <option value=""></option>
                            <?php
                            $motivos_sepe = ["Baja voluntaria", "Colocación", "Incomparecencia", "Otras causas"];
                            if (!empty($matricula['motivo_sepe']) && !in_array($matricula['motivo_sepe'], $motivos_sepe)) {
                                $motivos_sepe[] = $matricula['motivo_sepe'];
                            }
                            foreach ($motivos_sepe as $ms):
                            ?>
                                <option value="<?= htmlspecialchars($ms) ?>" <?= ($matricula['motivo_sepe'] ?? '') == $ms ? 'selected' : '' ?>><?= htmlspecialchars($ms) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid-form" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>Otros motivos:</label>
                        <textarea name="otros_motivos" class="form-control" rows="2"><?= htmlspecialchars($matricula['otros_motivos'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label>Tutor:</label>
                        <select name="tutor_id" class="form-control">
                            <option value="">Seleccione Tutor...</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($matricula['tutor_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsable seguimiento:</label>
                        <select name="responsable_seguimiento" class="form-control">
                            <option value="">Seleccione Responsable...</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($matricula['responsable_seguimiento'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div id="tab-docs" class="tab-panel <?= $active_tab === 'tab-docs' ? '' : 'hidden' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_datos_docs">
                
                <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                    <button type="button" class="btn-modern btn-outline" style="margin-right: 10px;">
                        Ficha Seguimiento
                    </button>
                    <button type="submit" class="btn-modern btn-primary-modern">
                        💾 Guardar Registro
                    </button>
                </div>

                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                    <div class="grid-form" style="grid-template-columns: auto auto 1fr; align-items: center; margin-bottom: 0;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e3a8a;">
                            <input type="checkbox" name="entrega_mat_1" value="1" <?= !empty($matricula['entrega_mat_1']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Entrega mat 1 :
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-weight: 600; color: #475569;">Fechas envío:</label>
                            <select name="fechas_envio" class="form-control" style="width: auto;">
                                <option value=""></option>
                                <?php
                                $fechas_opt = [];
                                if (!empty($matricula['fechas_envio'])) {
                                    $fechas_opt[] = $matricula['fechas_envio'];
                                }
                                $today = date('Y-m-d');
                                if (!in_array($today, $fechas_opt)) {
                                    $fechas_opt[] = $today;
                                }
                                foreach ($fechas_opt as $fo):
                                ?>
                                    <option value="<?= htmlspecialchars($fo) ?>" <?= ($matricula['fechas_envio'] ?? '') == $fo ? 'selected' : '' ?>><?= htmlspecialchars($fo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="btn-modern" style="background: #fbbf24; color: #92400e; font-weight: 700; border: 1px solid #f59e0b; padding: 0.4rem 1rem;">
                                📦 Enviar Material
                            </button>
                        </div>
                    </div>
                </div>

                <div style="background: #ffedd5; padding: 1.5rem; border-radius: 8px; border: 1px solid #fdba74; margin-bottom: 2rem;">
                    <div class="grid-form" style="grid-template-columns: auto auto auto auto 1fr; align-items: center; margin-bottom: 0;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #9a3412;">
                            <input type="checkbox" name="diploma_entregado" value="1" <?= !empty($matricula['diploma_entregado']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Diploma:
                        </label>
                        <select name="diploma_tipo" class="form-control" style="width: auto;">
                            <option value=""></option>
                            <?php
                            $tipos = ["Aprovechamiento", "Asistencia"];
                            if (!empty($matricula['diploma_tipo']) && !in_array($matricula['diploma_tipo'], $tipos)) {
                                $tipos[] = $matricula['diploma_tipo'];
                            }
                            foreach ($tipos as $t):
                            ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= ($matricula['diploma_tipo'] ?? '') == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-modern" style="background: #fbbf24; color: #92400e; font-weight: 700; border: 1px solid #f59e0b; padding: 0.4rem 1rem;">
                            🎓 Enviar Diploma
                        </button>
                        
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #431407; margin-left: 1rem;">
                            <input type="checkbox" name="comunicado" value="1" <?= !empty($matricula['comunicado']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Comunicado:
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-weight: 600; color: #431407;">Fecha comunicación:</label>
                            <input type="date" name="fecha_comunicacion" class="form-control" value="<?= htmlspecialchars($matricula['fecha_comunicacion'] ?? '') ?>" style="width: auto;">
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #431407; margin-left: 1rem;">
                            <input type="checkbox" name="comunicado_ugt" value="1" <?= !empty($matricula['comunicado_ugt']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Comunicado UGT:
                        </label>
                    </div>
                </div>

                <h3 class="form-section-title" style="color: #b91c1c;">Documentación general alumno:</h3>
                <div style="background: #fef08a; padding: 1.5rem; border-radius: 8px; border: 1px solid #fde047; margin-bottom: 2rem;">
                    <div class="grid-form" style="grid-template-columns: auto auto auto 1fr; align-items: center; margin-bottom: 0;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700; color: #1e3a8a;">
                            <input type="checkbox" name="nomina_entregada" value="1" <?= !empty($matricula['nomina_entregada']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Validar nómina atrasada:
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px; margin-left: 1rem;">
                            <label style="font-weight: 700; color: #1e3a8a;">Anexo 1:</label>
                            <select name="anexo1_entregado" class="form-control" style="width: 150px;">
                                <option value=""></option>
                                <option value="SI" <?= ($matricula['anexo1_entregado'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                                <option value="NO" <?= ($matricula['anexo1_entregado'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                                <option value="PENDIENTE" <?= ($matricula['anexo1_entregado'] ?? '') == 'PENDIENTE' ? 'selected' : '' ?>>PENDIENTE</option>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-left: 1rem;">
                            <label style="font-weight: 700; color: #1e3a8a;">Matrícula:</label>
                            <select name="matricula_doc" class="form-control" style="width: 150px;">
                                <option value=""></option>
                                <option value="SI" <?= ($matricula['matricula_doc'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                                <option value="NO" <?= ($matricula['matricula_doc'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                                <option value="PENDIENTE" <?= ($matricula['matricula_doc'] ?? '') == 'PENDIENTE' ? 'selected' : '' ?>>PENDIENTE</option>
                            </select>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700; color: #1e3a8a; margin-left: 1rem;">
                            <input type="checkbox" name="correcto" value="1" <?= !empty($matricula['correcto']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Correcto:
                        </label>
                    </div>
                </div>

                <h3 class="form-section-title" style="color: #b91c1c;">Curso presencial:</h3>
                <div class="grid-form" style="grid-template-columns: auto auto auto auto auto auto auto; align-items: center; gap: 1rem;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e3a8a;">
                        <input type="checkbox" name="recibi_material" value="1" <?= !empty($matricula['recibi_material']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Recibí material:
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e3a8a;">
                        <input type="checkbox" name="asistencia" value="1" <?= !empty($matricula['asistencia']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Asistencia:
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Días que asiste:</label>
                        <input type="text" name="dias_asiste" class="form-control" value="<?= htmlspecialchars($matricula['dias_asiste'] ?? '0.0') ?>" style="width: 60px; text-align: right;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Recibí diploma:</label>
                        <select name="recibi_diploma" class="form-control" style="width: 100px;">
                            <option value=""></option>
                            <option value="SI" <?= ($matricula['recibi_diploma'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                            <option value="NO" <?= ($matricula['recibi_diploma'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Copia diploma:</label>
                        <select name="copia_diploma" class="form-control" style="width: 100px;">
                            <option value=""></option>
                            <option value="SI" <?= ($matricula['copia_diploma'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                            <option value="NO" <?= ($matricula['copia_diploma'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                        </select>
                    </div>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e3a8a;">
                        <input type="checkbox" name="evaluacion_docente" value="1" <?= !empty($matricula['evaluacion_docente']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Evaluación Docente:
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Apto:</label>
                        <select name="apto" class="form-control" style="width: 80px;">
                            <option value=""></option>
                            <option value="SI" <?= ($matricula['apto'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                            <option value="NO" <?= ($matricula['apto'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                        </select>
                    </div>
                </div>

            </form>
        </div>

        <div id="tab-seguimiento" class="tab-panel <?= $active_tab === 'tab-seguimiento' ? '' : 'hidden' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="update_datos_seguimiento">
                
                <!-- Botón de guardado y ficha de seguimiento -->
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 1.5rem;">
                    <button type="submit" class="btn-modern btn-primary-modern">
                        💾 Guardar registro
                    </button>
                    <button type="button" class="btn-modern btn-outline" onclick="window.open('pdf_informe_seguimiento.php?id=<?= $id ?>', '_blank')" style="border-color: #cbd5e1; color: #475569;">
                        📄 Ficha Seguimiento
                    </button>
                </div>

                <!-- SECCIÓN VERDE: Envío de Claves -->
                <div style="background: #e6f4ea; border: 1px solid #a8dab5; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #137333; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="envio_claves" value="1" <?= !empty($matricula['envio_claves']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            Envío claves:
                        </label>
                        
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-weight: 600; color: #137333; margin: 0;">Fecha claves:</label>
                            <input type="date" name="fecha_claves" class="form-control" value="<?= htmlspecialchars($matricula['fecha_claves'] ?? '') ?>" style="width: auto; height: 30px;">
                        </div>

                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #137333; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="email_admision_enviado" value="1" <?= !empty($matricula['email_admision_enviado']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            E-mail admisión enviado:
                        </label>

                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #137333; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="encuesta" value="1" <?= !empty($matricula['encuesta']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            Encuesta:
                        </label>

                        <div style="margin-left: auto;">
                            <button type="button" class="btn-modern" onclick="window.open('envio_claves.php?id=<?= $id ?>', '_blank')" style="background: #f59e0b; color: white; border: 1px solid #d97706; font-weight: 700; padding: 0.4rem 1.2rem;">
                                Enviar Claves
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN AMARILLA: Seguimiento distancia o teleformación -->
                <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; color: #78350f;">
                    <h4 style="margin-top: 0; margin-bottom: 1rem; color: #b91c1c; font-size: 0.95rem; font-weight: 700; border-bottom: 1px solid #fde68a; padding-bottom: 0.5rem;">
                        Seguimiento distancia o teleformación:
                    </h4>
                    
                    <!-- Fila Conectado -->
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; margin-bottom: 1.2rem;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="conectado" value="1" <?= !empty($matricula['conectado']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            Conectado:
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-weight: 600; margin: 0;">Fecha:</label>
                            <input type="date" name="fecha_conectado" class="form-control" value="<?= htmlspecialchars($matricula['fecha_conectado'] ?? '') ?>" style="width: auto; height: 30px;">
                        </div>
                    </div>

                    <!-- Filas E-mails -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.2rem;">
                        <!-- Email 1 -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; min-width: 80px; cursor: pointer;">
                                <input type="checkbox" name="email_1_check" value="1" <?= !empty($matricula['email_1_check']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                                E-mail1:
                            </label>
                            <input type="date" name="email_1_fecha" class="form-control" value="<?= htmlspecialchars($matricula['email_1_fecha'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                        <!-- Email 2 -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; min-width: 80px; cursor: pointer;">
                                <input type="checkbox" name="email_2_check" value="1" <?= !empty($matricula['email_2_check']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                                E-mail2:
                            </label>
                            <input type="date" name="email_2_fecha" class="form-control" value="<?= htmlspecialchars($matricula['email_2_fecha'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                        <!-- Email 3 -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; min-width: 80px; cursor: pointer;">
                                <input type="checkbox" name="email_3_check" value="1" <?= !empty($matricula['email_3_check']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                                E-mail3:
                            </label>
                            <input type="date" name="email_3_fecha" class="form-control" value="<?= htmlspecialchars($matricula['email_3_fecha'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                        <!-- Email 4 -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; min-width: 80px; cursor: pointer;">
                                <input type="checkbox" name="email_4_check" value="1" <?= !empty($matricula['email_4_check']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                                E-mail4:
                            </label>
                            <input type="date" name="email_4_fecha" class="form-control" value="<?= htmlspecialchars($matricula['email_4_fecha'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                        <!-- Email 5 -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; min-width: 80px; cursor: pointer;">
                                <input type="checkbox" name="email_5_check" value="1" <?= !empty($matricula['email_5_check']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                                E-mail5:
                            </label>
                            <input type="date" name="email_5_fecha" class="form-control" value="<?= htmlspecialchars($matricula['email_5_fecha'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                        <!-- Email 6 -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; min-width: 80px; cursor: pointer;">
                                <input type="checkbox" name="email_6_check" value="1" <?= !empty($matricula['email_6_check']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                                E-mail6:
                            </label>
                            <input type="date" name="email_6_fecha" class="form-control" value="<?= htmlspecialchars($matricula['email_6_fecha'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                    </div>

                    <!-- Email 7 & Evals -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.2rem;">
                        <!-- Email 7 -->
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; min-width: 80px; cursor: pointer;">
                                <input type="checkbox" name="email_7_check" value="1" <?= !empty($matricula['email_7_check']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                                E-mail7:
                            </label>
                            <input type="date" name="email_7_fecha" class="form-control" value="<?= htmlspecialchars($matricula['email_7_fecha'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                        
                        <!-- Eval Inicial -->
                        <div style="display: flex; align-items: center; gap: 8px; grid-column: span 1;">
                            <label style="font-weight: 600; margin: 0; min-width: 50px;">Eval I:</label>
                            <input type="text" name="eval_inicial" class="form-control" value="<?= htmlspecialchars($matricula['eval_inicial'] ?? '') ?>" style="width: 50px; height: 28px; text-align: center;">
                            <label style="font-weight: 500; margin: 0; font-size: 0.85rem; white-space: nowrap;">Realizada el</label>
                            <input type="date" name="fecha_eval_inicial" class="form-control" value="<?= htmlspecialchars($matricula['fecha_eval_inicial'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>

                        <!-- Eval Final -->
                        <div style="display: flex; align-items: center; gap: 8px; grid-column: span 1;">
                            <label style="font-weight: 600; margin: 0; min-width: 50px;">Eval F:</label>
                            <input type="text" name="eval_final" class="form-control" value="<?= htmlspecialchars($matricula['eval_final'] ?? '') ?>" style="width: 50px; height: 28px; text-align: center;">
                            <label style="font-weight: 500; margin: 0; font-size: 0.85rem; white-space: nowrap;">Realizada el</label>
                            <input type="date" name="fecha_eval_final" class="form-control" value="<?= htmlspecialchars($matricula['fecha_eval_final'] ?? '') ?>" style="flex: 1; height: 28px;">
                        </div>
                    </div>

                    <!-- Nota Media -->
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 1.2rem;">
                        <label style="font-weight: 600; margin: 0;">Nota media:</label>
                        <input type="text" name="nota_media" class="form-control" value="<?= htmlspecialchars($matricula['nota_media'] ?? '0') ?>" style="width: 60px; height: 28px; text-align: center;">
                    </div>

                    <!-- Connection Time Stats -->
                    <?php
                        $conn_hours = number_format(($matricula['moodle_connected_time'] ?? 0) / 3600, 2);
                        $conn_progress = number_format($matricula['moodle_progress'] ?? 0, 2);
                    ?>
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; padding: 0.8rem 1rem; border-radius: 4px; font-weight: 700; margin-bottom: 1.2rem; color: #92400e;">
                        Tiempo total de conexión: <?= $conn_hours ?> h (<?= $conn_progress ?>%)
                    </div>

                    <!-- Observaciones solicitante -->
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-weight: 700; color: #1e3a8a;">Observaciones para el solicitante:</label>
                        <textarea name="observaciones_solicitante" class="form-control" rows="3" style="background: #f0f7ff; border: 1px solid #b9ddff; color: #1e3a8a; padding: 8px; font-size: 0.85rem;"><?= htmlspecialchars($matricula['observaciones_solicitante'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- SECCIÓN AZUL: Llamadas de seguimiento -->
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; color: #1e3a8a;">
                    <h4 style="margin-top: 0; margin-bottom: 1rem; color: #b91c1c; font-size: 0.95rem; font-weight: 700; border-bottom: 1px solid #bfdbfe; padding-bottom: 0.5rem;">
                        Llamadas de seguimiento:
                    </h4>

                    <!-- Checks & Inputs de Llamadas -->
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 1.2rem; margin-bottom: 1.2rem; font-size: 0.85rem;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="llamada_inicio" value="1" <?= !empty($matricula['llamada_inicio']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                            Inicio curso:
                        </label>

                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="llamada_mitad" value="1" <?= !empty($matricula['llamada_mitad']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                            Mitad curso:
                        </label>

                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="llamada_7dias" value="1" <?= !empty($matricula['llamada_7dias']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                            7 dias fin:
                        </label>

                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; margin: 0; cursor: pointer;">
                            <input type="checkbox" name="llamada_cierre" value="1" <?= !empty($matricula['llamada_cierre']) ? 'checked' : '' ?> style="width: 15px; height: 15px;">
                            Llamada cierre:
                        </label>

                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-weight: 700; margin: 0;">Llamada4:</label>
                            <input type="date" name="llamada_4_fecha" class="form-control" value="<?= htmlspecialchars($matricula['llamada_4_fecha'] ?? '') ?>" style="width: auto; height: 26px; font-size: 0.8rem; padding: 2px 4px;">
                        </div>

                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-weight: 700; margin: 0;">Llamada5:</label>
                            <input type="date" name="llamada_5_fecha" class="form-control" value="<?= htmlspecialchars($matricula['llamada_5_fecha'] ?? '') ?>" style="width: auto; height: 26px; font-size: 0.8rem; padding: 2px 4px;">
                        </div>

                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-weight: 700; margin: 0;">Llamada6:</label>
                            <input type="date" name="llamada_6_fecha" class="form-control" value="<?= htmlspecialchars($matricula['llamada_6_fecha'] ?? '') ?>" style="width: auto; height: 26px; font-size: 0.8rem; padding: 2px 4px;">
                        </div>

                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-weight: 700; margin: 0;">Llamada8:</label>
                            <input type="date" name="llamada_8_fecha" class="form-control" value="<?= htmlspecialchars($matricula['llamada_8_fecha'] ?? '') ?>" style="width: auto; height: 26px; font-size: 0.8rem; padding: 2px 4px;">
                        </div>
                    </div>

                    <!-- NO PEDIR NÓMINA check -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: flex; flex-direction: column; font-weight: 700; color: #1e3a8a; margin: 0; cursor: pointer;">
                            <span>NO PEDIR NÓMINA:</span>
                            <input type="checkbox" name="no_pedir_nomina" value="1" <?= !empty($matricula['no_pedir_nomina']) ? 'checked' : '' ?> style="width: 16px; height: 16px; margin-top: 4px;">
                        </label>
                    </div>

                    <!-- Botones/Enlaces inferiores -->
                    <div style="display: flex; gap: 1rem; border-top: 1px solid #bfdbfe; padding-top: 1.2rem;">
                        <a href="ficha_llamada.php?matricula_id=<?= $id ?>" class="btn-modern" style="background: white; border: 1px solid #bfdbfe; color: #1e40af; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 0.5rem 1.2rem;">
                            📞 Listado de llamadas
                        </a>
                        <a href="tutorias.php?view=llamadas&alumno_id=<?= $matricula['alumno_id'] ?>" class="btn-modern" style="background: white; border: 1px solid #bfdbfe; color: #1e40af; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 0.5rem 1.2rem;">
                            💬 Comunicación con el tutor
                        </a>
                    </div>
                </div>

                <!-- Botón de guardado inferior -->
                <div style="display: flex; justify-content: center; margin-top: 2rem;">
                    <button type="submit" class="btn-modern btn-primary-modern" style="padding: 0.6rem 3rem; font-size: 1rem;">
                        Guardar registro
                    </button>
                </div>
            </form>
        </div>

    </main>
</div>

<script>
    // Tab Switching Logic
    document.addEventListener('DOMContentLoaded', () => {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabPanels = document.querySelectorAll('.tab-panel');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Remove active from all
                tabBtns.forEach(b => b.classList.remove('active'));
                tabPanels.forEach(p => p.classList.add('hidden'));

                // Add active to current
                btn.classList.add('active');
                const targetId = btn.getAttribute('data-target');
                const targetPanel = document.getElementById(targetId);
                if (targetPanel) {
                    targetPanel.classList.remove('hidden');
                }
            });
        });
    });
</script>

</body>
</html>