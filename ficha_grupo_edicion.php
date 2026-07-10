<?php
// ficha_grupo_edicion.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    die("No tiene permisos suficientes.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$accion_id = isset($_GET['accion_id']) ? (int)$_GET['accion_id'] : null;

// Self-healing database check
function check_and_add_columns_edit($pdo) {
    $columns = [
        'convocatoria_id' => "INT NULL",
        'plan_id' => "INT NULL",
        'expediente' => "VARCHAR(100) NULL",
        'consultora_id' => "INT NULL",
        'curso_id' => "INT NULL",
        'codigo_plat' => "VARCHAR(50) NULL",
        'denominacion_grupo' => "VARCHAR(255) NULL",
        'fecha_solicitud_desempleados' => "DATE NULL",
        'hay_desempleados' => "VARCHAR(5) DEFAULT 'NO'",
        'contestacion_ca' => "VARCHAR(5) DEFAULT 'NO'",
        'comunidad_autonoma' => "VARCHAR(100) NULL",
        'usuario_gestor' => "VARCHAR(100) NULL",
        'contrasena_gestor' => "VARCHAR(100) NULL",
        'plazo_hoja_seleccion' => "DATE NULL",
        'comunicacion_finalizacion' => "DATE NULL",
        'fecha_tramitacion_becas' => "DATE NULL",
        'fecha_ds15' => "DATE NULL",
        'fecha_actas_evaluacion' => "DATE NULL",
        'fecha_cuestionarios_calidad' => "DATE NULL",
        'num_ac' => "INT DEFAULT 1",
        'fecha_25' => "DATE NULL",
        'plazo_s10' => "DATE NULL",
        'modificacion_s10' => "DATE NULL",
        'plazo_s20' => "DATE NULL",
        'modificacion_s20' => "DATE NULL",
        'fecha_1_2_curso' => "DATE NULL",
        'fecha_7_dias_fin' => "DATE NULL",
        'fecha_3_dias_fin' => "DATE NULL",
        'total_sesiones' => "INT DEFAULT 0",
        'sesion_15' => "VARCHAR(100) NULL",
        'sesion_25' => "VARCHAR(100) NULL",
        'sesion_anterior' => "DATE NULL",
        'sesion_50' => "VARCHAR(100) NULL",
        'comunicado' => "TINYINT(1) DEFAULT 0",
        'fecha_comunicacion' => "DATE NULL",
        'horas_tutorias_programadas' => "DECIMAL(10,2) DEFAULT 0.00",
        'horas_af' => "INT DEFAULT 0",
        'provincia' => "VARCHAR(100) NULL",
        'sede' => "VARCHAR(150) NULL",
        'no_certificar' => "TINYINT(1) DEFAULT 0",
        'objeto_control' => "TINYINT(1) DEFAULT 0",
        'material' => "TEXT NULL",

        'modulacion' => "VARCHAR(150) NULL",
        'horario_desde' => "VARCHAR(5) NULL",
        'horario_hasta' => "VARCHAR(5) NULL",
        'horario_desde_2' => "VARCHAR(5) NULL",
        'horario_hasta_2' => "VARCHAR(5) NULL",
        'horario_presencial_desde' => "VARCHAR(5) NULL",
        'horario_presencial_hasta' => "VARCHAR(5) NULL",
        'horario_presencial_desde_2' => "VARCHAR(5) NULL",
        'horario_presencial_hasta_2' => "VARCHAR(5) NULL",
        'horario_distancia_desde' => "VARCHAR(5) NULL",
        'horario_distancia_hasta' => "VARCHAR(5) NULL",
        'horario_distancia_desde_2' => "VARCHAR(5) NULL",
        'horario_distancia_hasta_2' => "VARCHAR(5) NULL",
        'horario_telef_desde' => "VARCHAR(5) NULL",
        'horario_telef_hasta' => "VARCHAR(5) NULL",
        'horario_telef_desde_2' => "VARCHAR(5) NULL",
        'horario_telef_hasta_2' => "VARCHAR(5) NULL",
        'dias_lunes' => "TINYINT(1) DEFAULT 0",
        'dias_martes' => "TINYINT(1) DEFAULT 0",
        'dias_miercoles' => "TINYINT(1) DEFAULT 0",
        'dias_jueves' => "TINYINT(1) DEFAULT 0",
        'dias_viernes' => "TINYINT(1) DEFAULT 0",
        'dias_sabado' => "TINYINT(1) DEFAULT 0",
        'dias_domingo' => "TINYINT(1) DEFAULT 0",
        'horario_info' => "VARCHAR(100) NULL",
        'tutor_id_2' => "INT NULL",
        'mostrar_tutor' => "TINYINT(1) DEFAULT 1",
        'tutor_reserva_id' => "INT NULL",
        'teleformador_id' => "INT NULL",
        'tecnico_id' => "INT NULL",
        'fecha_modificado' => "DATE NULL",
        'coste_hora_aula' => "DECIMAL(10,2) DEFAULT 0.00",
        'coste_hora_profesor' => "DECIMAL(10,2) DEFAULT 0.00",
        'encuestas_finales' => "INT DEFAULT 0",
        'doc_ficha_aula' => "TINYINT(1) DEFAULT 0",
        'doc_cv_profesor' => "TINYINT(1) DEFAULT 0",
        'doc_contrato_profesor' => "TINYINT(1) DEFAULT 0",
        'doc_contrato_aula' => "TINYINT(1) DEFAULT 0",
        'doc_cert_ejecucion' => "TINYINT(1) DEFAULT 0",
        'observaciones' => "TEXT NULL",
        'modificacion_texto' => "TEXT NULL",
        'motivo_anulacion' => "TEXT NULL",
        'justificacion' => "TEXT NULL",
        'orientacion_ugt' => "TEXT NULL",
        'notas_internas' => "TEXT NULL",
        'material_facturado' => "VARCHAR(5) DEFAULT 'NO'",
        'inspeccionado' => "TINYINT(1) DEFAULT 0",
        'fecha_inspeccion' => "DATE NULL"
    ];

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM grupos");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing)) {
                $pdo->exec("ALTER TABLE grupos ADD COLUMN `$name` $definition");
            }
        }
    } catch (Exception $e) {}
}

check_and_add_columns_edit($pdo);

$grupo = [];
$accion = [];
$tutores = [];
$centros = [];
$convocatorias = [];
$planes = [];
$cursos = [];
$consultoras = [];

try {
    // Tutors
    $stmtTutores = $pdo->query("SELECT u.id, CONCAT(u.nombre, ' ', u.apellidos) as nombre 
                                FROM usuarios u 
                                JOIN roles r ON u.rol_id = r.id 
                                WHERE (r.nombre LIKE '%Tutor%' OR r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Docente%') 
                                AND u.activo = 1
                                ORDER BY u.nombre ASC");
    if ($stmtTutores) $tutores = $stmtTutores->fetchAll(PDO::FETCH_ASSOC);

    // Centers / Consultoras
    $stmtCentros = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    if ($stmtCentros) {
        $centros = $stmtCentros->fetchAll(PDO::FETCH_ASSOC);
        $consultoras = $centros;
    }

    // Convocatorias
    $stmtConv = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC");
    if ($stmtConv) $convocatorias = $stmtConv->fetchAll(PDO::FETCH_ASSOC);

    // Planes
    $stmtPlanes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC");
    if ($stmtPlanes) $planes = $stmtPlanes->fetchAll(PDO::FETCH_ASSOC);

    // Cursos
    $stmtCursos = $pdo->query("SELECT id, CONCAT(nombre_corto, ' - ', nombre_largo) as nombre FROM cursos ORDER BY nombre_largo ASC");
    if ($stmtCursos) $cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
        $stmt->execute([$id]);
        $grupo = $stmt->fetch();
        if ($grupo) {
            $accion_id = $grupo['accion_id'];
        }
    }

    if ($accion_id) {
        $stmt = $pdo->prepare("SELECT id, titulo, num_accion, plan_id FROM acciones_formativas WHERE id = ?");
        $stmt->execute([$accion_id]);
        $accion = $stmt->fetch();
    }

} catch (Throwable $e) {
    // Silently fail
}

$modalidades = ['Teleformación', 'Presencial', 'Mixta', 'Aula Virtual'];
$situaciones = ['Válido', 'Suspendido', 'Finalizado', 'Lista espera', 'Inactivo'];
$asignaciones = ['I', 'E', 'M'];

$ccaa = [
    'Andalucía', 'Aragón', 'Asturias', 'Baleares', 'Canarias', 'Cantabria', 'Castilla y León', 
    'Castilla-La Mancha', 'Cataluña', 'Comunidad Valenciana', 'Extremadura', 'Galicia', 
    'Madrid', 'Murcia', 'Navarra', 'País Vasco', 'La Rioja', 'Ceuta', 'Melilla'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Grupo | Intranet Edite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-content { padding: 1.5rem 2rem; max-width: 1400px; box-sizing: border-box; }
        
        /* Top banner matching screenshot */
        .top-banner {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 6px;
            box-shadow: 0 4px 10px rgba(185, 28, 28, 0.15);
        }
        
        .banner-btn {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.68rem;
            font-weight: 700;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
        }
        
        .banner-btn:hover {
            background: #ffffff;
            color: #b91c1c;
            border-color: #ffffff;
            transform: translateY(-1px);
        }

        .page-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
        }

        .page-header-premium h1 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1e3a8a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Navigation Tabs */
        .tabs-container {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 1px;
        }

        .tab-item {
            padding: 8px 16px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            background: #f1f5f9;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .tab-item:hover {
            background: #e2e8f0;
            color: #1e3a8a;
        }

        .tab-item.active {
            background: #ffffff;
            color: #1e3a8a;
            border-color: #cbd5e1 #cbd5e1 #ffffff #cbd5e1;
            position: relative;
            bottom: -2px;
            z-index: 2;
        }

        .action-info-box {
            background: #fff;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .action-info-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 4px;
        }

        .action-info-content {
            font-size: 1rem;
            font-weight: 700;
            color: #1e3a8a;
        }

        .form-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            padding: 24px;
            margin-bottom: 30px;
        }

        .form-section-title {
            color: #1e3a8a;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 18px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 15px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .form-group.col-span-2 { grid-column: span 2; }
        .form-group.col-span-3 { grid-column: span 3; }
        .form-group.col-span-4 { grid-column: span 4; }

        .form-group label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Inline group for schedule inputs */
        .inline-inputs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }

        .inline-inputs input[type="text"] {
            width: 85px;
            padding-left: 6px;
            padding-right: 6px;
            text-align: center;
        }

        /* Checkbox row layout */
        .checkbox-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 10px 0;
        }

        .checkbox-custom-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            text-transform: uppercase;
        }

        .checkbox-custom-label input {
            width: 16px;
            height: 16px;
            accent-color: #1e3a8a;
        }

        /* Radio group */
        .radio-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .radio-custom-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
        }

        .radio-custom-label input {
            width: 16px;
            height: 16px;
            accent-color: #1e3a8a;
        }

        .btn-save {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 10px rgba(30, 58, 138, 0.2);
            display: block;
            margin: 0 auto;
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(30, 58, 138, 0.3);
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        .footer-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/fp_sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            <!-- Top Actions Crimson Bar -->
            <div class="top-banner">
                <a href="informe_evaluaciones_grupo.php?grupo_id=<?= $id ?>" class="banner-btn">Generar PDF evaluaciones grupo</a>
                <a href="informe_conexion_grupo.php?grupo_id=<?= $id ?>" class="banner-btn">Informe de conexión</a>
                <a href="calendario.php" class="banner-btn">Ver calendario</a>
                <a href="#" class="banner-btn">S20</a>
                <a href="#" class="banner-btn">Registro de diplomas</a>
                <a href="asistencia.php?grupo_id=<?= $id ?>" class="banner-btn">Asistencia presenciales</a>
                <a href="subir_documento.php?grupo_id=<?= $id ?>" class="banner-btn">Subir documento</a>
                <a href="documentacion.php?grupo_id=<?= $id ?>" class="banner-btn">Documentos</a>
                <a href="#" class="banner-btn">Ficha grupo</a>
                <a href="home.php" class="banner-btn">Página Inicio</a>
                <a href="logout.php" class="banner-btn" style="background: rgba(0,0,0,0.2);">Desconectar</a>
            </div>

            <div class="page-header-premium">
                <h1>Ficha de Grupo</h1>
            </div>

            <!-- Tabs Navigation -->
            <?php if ($id): ?>
                <div class="tabs-container">
                    <a href="gestion_matriculas.php?af_id=<?= $accion_id ?>" class="tab-item">Listado de alumnos</a>
                    <a href="relacion_alumnos.php?grupo_id=<?= $id ?>" class="tab-item active">Listado de alumnos nuevo</a>
                </div>
            <?php endif; ?>

            <div class="action-info-box">
                <div class="action-info-title">Acción Formativa vinculada</div>
                <div class="action-info-content">
                    <?= htmlspecialchars($accion['titulo'] ?? '---') ?> 
                    <span style="color:#64748b; font-weight:400; font-size:0.9rem;">
                        (Acción Nº <?= htmlspecialchars($accion['num_accion'] ?? '0') ?>)
                    </span>
                </div>
            </div>

            <form action="guardar_grupo.php" method="POST" class="form-card">
                <?php if ($id): ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                <?php endif; ?>
                <input type="hidden" name="accion_id" value="<?= $accion_id ?>">

                <!-- SECTION 1: IDENTIFICACIÓN Y PLANIFICACIÓN -->
                <div class="form-section-title">Identificación y Planificación del Grupo</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>COD (ID Registro):</label>
                        <input type="text" class="form-control" style="background-color: #f1f5f9; font-weight:700;" value="<?= $id ?: 'Nuevo' ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach ($convocatorias as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($grupo['convocatoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label>Plan Formativo:</label>
                        <select name="plan_id" class="form-control">
                            <option value="">Seleccione plan...</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($grupo['plan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Expediente:</label>
                        <input type="text" name="expediente" class="form-control" value="<?= htmlspecialchars($grupo['expediente'] ?? '') ?>" placeholder="Ej: EXP-2024">
                    </div>
                    <div class="form-group col-span-2">
                        <label>Consultora:</label>
                        <select name="consultora_id" class="form-control">
                            <option value="">Seleccione consultora...</option>
                            <?php foreach ($consultoras as $con): ?>
                                <option value="<?= $con['id'] ?>" <?= ($grupo['consultora_id'] ?? '') == $con['id'] ? 'selected' : '' ?>><?= htmlspecialchars($con['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Código Plat.:</label>
                        <input type="text" name="codigo_plat" class="form-control" value="<?= htmlspecialchars($grupo['codigo_plat'] ?? '') ?>" placeholder="Ej: 1218">
                    </div>

                    <div class="form-group col-span-4">
                        <label>Curso:</label>
                        <select name="curso_id" class="form-control">
                            <option value="">Seleccione curso...</option>
                            <?php foreach ($cursos as $cur): ?>
                                <option value="<?= $cur['id'] ?>" <?= ($grupo['curso_id'] ?? '') == $cur['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cur['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-span-2">
                        <label>Denominación del Grupo:</label>
                        <input type="text" name="denominacion_grupo" class="form-control" value="<?= htmlspecialchars($grupo['denominacion_grupo'] ?? '') ?>" placeholder="Ej: ARGG031PO - ADOBE ILLUSTRATOR AVANZADO CC">
                    </div>
                    <div class="form-group">
                        <label>Fecha solicitud desempleados:</label>
                        <input type="date" name="fecha_solicitud_desempleados" class="form-control" value="<?= $grupo['fecha_solicitud_desempleados'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Hay desempleados:</label>
                        <select name="hay_desempleados" class="form-control">
                            <option value="NO" <?= ($grupo['hay_desempleados'] ?? 'NO') === 'NO' ? 'selected' : '' ?>>NO</option>
                            <option value="SI" <?= ($grupo['hay_desempleados'] ?? 'NO') === 'SI' ? 'selected' : '' ?>>SÍ</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Contestación por CA:</label>
                        <select name="contestacion_ca" class="form-control">
                            <option value="NO" <?= ($grupo['contestacion_ca'] ?? 'NO') === 'NO' ? 'selected' : '' ?>>NO</option>
                            <option value="SI" <?= ($grupo['contestacion_ca'] ?? 'NO') === 'SI' ? 'selected' : '' ?>>SÍ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Comunidad Autónoma:</label>
                        <select name="comunidad_autonoma" class="form-control">
                            <option value=""></option>
                            <?php foreach ($ccaa as $com): ?>
                                <option value="<?= $com ?>" <?= ($grupo['comunidad_autonoma'] ?? '') == $com ? 'selected' : '' ?>><?= $com ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Usuario Gestor:</label>
                        <input type="text" name="usuario_gestor" class="form-control" value="<?= htmlspecialchars($grupo['usuario_gestor'] ?? '') ?>" placeholder="Ej: u24041g1">
                    </div>
                    <div class="form-group">
                        <label>Contraseña Gestor:</label>
                        <input type="text" name="contrasena_gestor" class="form-control" value="<?= htmlspecialchars($grupo['contrasena_gestor'] ?? '') ?>">
                    </div>
                </div>

                <!-- SECTION 2: PLAZOS Y TRÁMITES ADMINISTRATIVOS -->
                <div class="form-section-title">Plazos y Trámites Administrativos</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Plazo Hoja Selección:</label>
                        <input type="date" name="plazo_hoja_seleccion" class="form-control" value="<?= $grupo['plazo_hoja_seleccion'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Com. finalización y Evaluación:</label>
                        <input type="date" name="comunicacion_finalizacion" class="form-control" value="<?= $grupo['comunicacion_finalizacion'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha tramitación Becas:</label>
                        <input type="date" name="fecha_tramitacion_becas" class="form-control" value="<?= $grupo['fecha_tramitacion_becas'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha DS-15:</label>
                        <input type="date" name="fecha_ds15" class="form-control" value="<?= $grupo['fecha_ds15'] ?? '' ?>">
                    </div>

                    <div class="form-group col-span-2">
                        <label>Fecha Actas, informes, pruebas y cuestionarios:</label>
                        <input type="date" name="fecha_actas_evaluacion" class="form-control" value="<?= $grupo['fecha_actas_evaluacion'] ?? '' ?>">
                    </div>
                    <div class="form-group col-span-2">
                        <label>Fecha Cuestionarios Calidad:</label>
                        <input type="date" name="fecha_cuestionarios_calidad" class="form-control" value="<?= $grupo['fecha_cuestionarios_calidad'] ?? '' ?>">
                    </div>
                </div>

                <!-- SECTION 3: GRUPOS Y SEGUIMIENTO DIARIO -->
                <div class="form-section-title">Parámetros de Seguimiento y Fechas Hito</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nº ac.:</label>
                        <input type="number" name="num_ac" class="form-control" value="<?= htmlspecialchars($grupo['num_ac'] ?? '1') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nº Grupo:</label>
                        <input type="text" name="numero_grupo" class="form-control" value="<?= htmlspecialchars($grupo['numero_grupo'] ?? '') ?>" placeholder="Ej: G1">
                    </div>
                    <div class="form-group">
                        <label>Fecha Inicio:</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= $grupo['fecha_inicio'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha Fin:</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= $grupo['fecha_fin'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label>Fecha 25%:</label>
                        <input type="date" name="fecha_25" class="form-control" value="<?= $grupo['fecha_25'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Plazo S10:</label>
                        <input type="date" name="plazo_s10" class="form-control" value="<?= $grupo['plazo_s10'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Modificación S10:</label>
                        <input type="date" name="modificacion_s10" class="form-control" value="<?= $grupo['modificacion_s10'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Plazo S20:</label>
                        <input type="date" name="plazo_s20" class="form-control" value="<?= $grupo['plazo_s20'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Modificación S20:</label>
                        <input type="date" name="modificacion_s20" class="form-control" value="<?= $grupo['modificacion_s20'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label>Fecha 1/2 curso:</label>
                        <input type="date" name="fecha_1_2_curso" class="form-control" value="<?= $grupo['fecha_1_2_curso'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha 7 días fin:</label>
                        <input type="date" name="fecha_7_dias_fin" class="form-control" value="<?= $grupo['fecha_7_dias_fin'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha 3 días fin:</label>
                        <input type="date" name="fecha_3_dias_fin" class="form-control" value="<?= $grupo['fecha_3_dias_fin'] ?? '' ?>">
                    </div>
                </div>

                <!-- SECTION 4: SESIONES E HITOS -->
                <div class="form-section-title">Sesiones e Hitos de Seguimiento</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nº Total Sesiones:</label>
                        <input type="number" name="total_sesiones" class="form-control" value="<?= htmlspecialchars($grupo['total_sesiones'] ?? '0') ?>">
                    </div>
                    <div class="form-group">
                        <label>Hito 15%:</label>
                        <input type="text" name="sesion_15" class="form-control" value="<?= htmlspecialchars($grupo['sesion_15'] ?? '') ?>" placeholder="Ej: sesión 3 - 23/07/2025">
                    </div>
                    <div class="form-group">
                        <label>Hito 25%:</label>
                        <input type="text" name="sesion_25" class="form-control" value="<?= htmlspecialchars($grupo['sesion_25'] ?? '') ?>" placeholder="Ej: sesión 6 - 29/07/2025">
                    </div>
                    <div class="form-group">
                        <label>Sesión Anterior:</label>
                        <input type="date" name="sesion_anterior" class="form-control" value="<?= $grupo['sesion_anterior'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Sesión del 50%:</label>
                        <input type="text" name="sesion_50" class="form-control" value="<?= htmlspecialchars($grupo['sesion_50'] ?? '') ?>" placeholder="Ej: sesión 12 - 03/09/2025">
                    </div>
                </div>

                <!-- SECTION 5: EQUIPO DOCENTE Y TÉCNICO -->
                <div class="form-section-title">Equipo Docente y Técnico</div>
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label>Tutor / Docente Principal:</label>
                        <select name="tutor_id" class="form-control">
                            <option value="">Seleccione tutor...</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($grupo['tutor_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label>Tutor 2:</label>
                        <select name="tutor_id_2" class="form-control">
                            <option value="">Seleccione tutor 2...</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($grupo['tutor_id_2'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-span-2">
                        <label>Tutor de Reserva:</label>
                        <select name="tutor_reserva_id" class="form-control">
                            <option value="">Seleccione tutor de reserva...</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($grupo['tutor_reserva_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <div style="margin-top: 25px;">
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="mostrar_tutor" value="1" <?= ($grupo['mostrar_tutor'] ?? 1) ? 'checked' : '' ?>>
                                Mostrar tutor en plataforma
                            </label>
                        </div>
                    </div>

                    <div class="form-group col-span-2">
                        <label>Teleformador / Formador Moodle:</label>
                        <select name="teleformador_id" class="form-control">
                            <option value="">Seleccione teleformador...</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($grupo['teleformador_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-span-2">
                        <label>Técnico de Apoyo:</label>
                        <select name="tecnico_id" class="form-control">
                            <option value="">Seleccione técnico...</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($grupo['tecnico_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- SECTION 6: MODULACIÓN Y HORARIOS -->
                <div class="form-section-title">Modulación y Horarios de Impartición</div>
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label>Modulación (Días de clase):</label>
                        <input type="text" name="modulacion" class="form-control" value="<?= htmlspecialchars($grupo['modulacion'] ?? 'Lunes a Viernes') ?>" placeholder="Ej: Lunes a Viernes">
                    </div>
                    <div class="form-group col-span-2">
                        <label>Horario General (Formato: hh:mm):</label>
                        <div class="inline-inputs">
                            <span>desde</span>
                            <input type="text" name="horario_desde" class="form-control" value="<?= htmlspecialchars($grupo['horario_desde'] ?? '') ?>" placeholder="09:00">
                            <span>hasta</span>
                            <input type="text" name="horario_hasta" class="form-control" value="<?= htmlspecialchars($grupo['horario_hasta'] ?? '') ?>" placeholder="10:00">
                            <span>y desde</span>
                            <input type="text" name="horario_desde_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_desde_2'] ?? '') ?>" placeholder="00:00">
                            <span>hasta</span>
                            <input type="text" name="horario_hasta_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_hasta_2'] ?? '') ?>" placeholder="00:00">
                        </div>
                    </div>

                    <div class="form-group col-span-4">
                        <label>Horarios por Modalidad Específica</label>
                        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                            <div class="inline-inputs">
                                <span style="width: 140px; font-weight: 600; color: #475569;">Horario Presencial:</span>
                                <span>desde</span>
                                <input type="text" name="horario_presencial_desde" class="form-control" value="<?= htmlspecialchars($grupo['horario_presencial_desde'] ?? '') ?>" placeholder="00:00">
                                <span>hasta</span>
                                <input type="text" name="horario_presencial_hasta" class="form-control" value="<?= htmlspecialchars($grupo['horario_presencial_hasta'] ?? '') ?>" placeholder="00:00">
                                <span>y desde</span>
                                <input type="text" name="horario_presencial_desde_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_presencial_desde_2'] ?? '') ?>" placeholder="00:00">
                                <span>hasta</span>
                                <input type="text" name="horario_presencial_hasta_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_presencial_hasta_2'] ?? '') ?>" placeholder="00:00">
                            </div>
                            <div class="inline-inputs">
                                <span style="width: 140px; font-weight: 600; color: #475569;">Horario Distancia:</span>
                                <span>desde</span>
                                <input type="text" name="horario_distancia_desde" class="form-control" value="<?= htmlspecialchars($grupo['horario_distancia_desde'] ?? '') ?>" placeholder="00:00">
                                <span>hasta</span>
                                <input type="text" name="horario_distancia_hasta" class="form-control" value="<?= htmlspecialchars($grupo['horario_distancia_hasta'] ?? '') ?>" placeholder="00:00">
                                <span>y desde</span>
                                <input type="text" name="horario_distancia_desde_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_distancia_desde_2'] ?? '') ?>" placeholder="00:00">
                                <span>hasta</span>
                                <input type="text" name="horario_distancia_hasta_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_distancia_hasta_2'] ?? '') ?>" placeholder="00:00">
                            </div>
                            <div class="inline-inputs">
                                <span style="width: 140px; font-weight: 600; color: #475569;">Horario Telefónico:</span>
                                <span>desde</span>
                                <input type="text" name="horario_telef_desde" class="form-control" value="<?= htmlspecialchars($grupo['horario_telef_desde'] ?? '') ?>" placeholder="00:00">
                                <span>hasta</span>
                                <input type="text" name="horario_telef_hasta" class="form-control" value="<?= htmlspecialchars($grupo['horario_telef_hasta'] ?? '') ?>" placeholder="00:00">
                                <span>y desde</span>
                                <input type="text" name="horario_telef_desde_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_telef_desde_2'] ?? '') ?>" placeholder="00:00">
                                <span>hasta</span>
                                <input type="text" name="horario_telef_hasta_2" class="form-control" value="<?= htmlspecialchars($grupo['horario_telef_hasta_2'] ?? '') ?>" placeholder="00:00">
                            </div>
                        </div>
                    </div>

                    <div class="form-group col-span-4">
                        <label>Días de Impartición:</label>
                        <div class="checkbox-row">
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="dias_lunes" value="1" <?= ($grupo['dias_lunes'] ?? 1) ? 'checked' : '' ?>> Lunes
                            </label>
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="dias_martes" value="1" <?= ($grupo['dias_martes'] ?? 1) ? 'checked' : '' ?>> Martes
                            </label>
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="dias_miercoles" value="1" <?= ($grupo['dias_miercoles'] ?? 1) ? 'checked' : '' ?>> Miércoles
                            </label>
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="dias_jueves" value="1" <?= ($grupo['dias_jueves'] ?? 1) ? 'checked' : '' ?>> Jueves
                            </label>
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="dias_viernes" value="1" <?= ($grupo['dias_viernes'] ?? 1) ? 'checked' : '' ?>> Viernes
                            </label>
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="dias_sabado" value="1" <?= ($grupo['dias_sabado'] ?? 0) ? 'checked' : '' ?>> Sábado
                            </label>
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="dias_domingo" value="1" <?= ($grupo['dias_domingo'] ?? 0) ? 'checked' : '' ?>> Domingo
                            </label>
                        </div>
                    </div>

                    <div class="form-group col-span-4">
                        <label>Información del Horario (Texto libre):</label>
                        <input type="text" name="horario_info" class="form-control" value="<?= htmlspecialchars($grupo['horario_info'] ?? '09:00 a 10:00 h') ?>" placeholder="Ej: 09:00 a 10:00 h">
                    </div>
                </div>

                <!-- SECTION 7: COMUNICACIÓN Y PLANIFICACIÓN HORARIA -->
                <div class="form-section-title">Horas y Comunicaciones</div>
                <div class="form-grid">
                    <div class="form-group col-span-2" style="justify-content: center;">
                        <label class="checkbox-custom-label">
                            <input type="checkbox" name="comunicado" value="1" <?= ($grupo['comunicado'] ?? 1) ? 'checked' : '' ?>>
                            Comunicado
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Fecha comunicación:</label>
                        <input type="date" name="fecha_communication" class="form-control" value="<?= $grupo['fecha_comunicacion'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Horas de tutorías programadas:</label>
                        <input type="number" step="0.01" name="horas_tutorias_programadas" class="form-control" value="<?= htmlspecialchars($grupo['horas_tutorias_programadas'] ?? '25.00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Horas A.F.:</label>
                        <input type="number" name="horas_af" class="form-control" value="<?= htmlspecialchars($grupo['horas_af'] ?? '25') ?>">
                    </div>
                </div>

                <!-- SECTION 8: UBICACIÓN Y CONTROL -->
                <div class="form-section-title">Ubicación y Centro de Impartición</div>
                <div class="form-grid">
                    <div class="form-group col-span-2">
                        <label>Centro:</label>
                        <select name="centro_id" class="form-control">
                            <option value="">Seleccione centro...</option>
                            <?php foreach ($centros as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($grupo['centro_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Provincia:</label>
                        <input type="text" name="provincia" class="form-control" value="<?= htmlspecialchars($grupo['provincia'] ?? 'GRANADA') ?>">
                    </div>
                    <div class="form-group">
                        <label>Sede:</label>
                        <input type="text" name="sede" class="form-control" value="<?= htmlspecialchars($grupo['sede'] ?? '') ?>" placeholder="Ej: Marsdigital S.L (Granada)">
                    </div>

                    <div class="form-group">
                        <div style="margin-top: 15px;">
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="no_certificar" value="1" <?= ($grupo['no_certificar'] ?? 0) ? 'checked' : '' ?>>
                                No certificar
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div style="margin-top: 15px;">
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="objeto_control" value="1" <?= ($grupo['objeto_control'] ?? 0) ? 'checked' : '' ?>>
                                Objeto de control
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SECTION 9: COSTES Y SEGUIMIENTO -->
                <div class="form-section-title">Costes y Encuestas</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Coste/Hora Aula:</label>
                        <input type="number" step="0.01" name="coste_hora_aula" class="form-control" value="<?= htmlspecialchars($grupo['coste_hora_aula'] ?? '0.00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Coste/Hora Profesor:</label>
                        <input type="number" step="0.01" name="coste_hora_profesor" class="form-control" value="<?= htmlspecialchars($grupo['coste_hora_profesor'] ?? '0.00') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nº Encuestas Finales:</label>
                        <input type="number" name="encuestas_finales" class="form-control" value="<?= htmlspecialchars($grupo['encuestas_finales'] ?? '0') ?>">
                    </div>
                    <div class="form-group">
                        <label>Situación / Estado:</label>
                        <select name="situacion" class="form-control">
                            <?php foreach ($situaciones as $s): ?>
                                <option value="<?= $s ?>" <?= ($grupo['situacion'] ?? '') == $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- SECTION 10: CHECKLIST DE DOCUMENTACIÓN -->
                <div class="form-section-title">Documentación y Checklist</div>
                <div class="checkbox-row" style="margin-bottom: 20px;">
                    <label class="checkbox-custom-label">
                        <input type="checkbox" name="doc_ficha_aula" value="1" <?= ($grupo['doc_ficha_aula'] ?? 0) ? 'checked' : '' ?>> Ficha Aula
                    </label>
                    <label class="checkbox-custom-label">
                        <input type="checkbox" name="doc_cv_profesor" value="1" <?= ($grupo['doc_cv_profesor'] ?? 0) ? 'checked' : '' ?>> CV Profesor
                    </label>
                    <label class="checkbox-custom-label">
                        <input type="checkbox" name="doc_contrato_profesor" value="1" <?= ($grupo['doc_contrato_profesor'] ?? 0) ? 'checked' : '' ?>> Contrato Profesor
                    </label>
                    <label class="checkbox-custom-label">
                        <input type="checkbox" name="doc_contrato_aula" value="1" <?= ($grupo['doc_contrato_aula'] ?? 0) ? 'checked' : '' ?>> Contrato Aula
                    </label>
                    <label class="checkbox-custom-label">
                        <input type="checkbox" name="doc_cert_ejecucion" value="1" <?= ($grupo['doc_cert_ejecucion'] ?? 0) ? 'checked' : '' ?>> Cert. Ejecución
                    </label>
                </div>

                <!-- SECTION 11: TEXTOS DE GESTIÓN Y OBSERVACIONES -->
                <div class="form-section-title">Textos de Gestión y Observaciones</div>
                <div class="form-grid">
                    <div class="form-group col-span-4">
                        <label>Material / Descripción:</label>
                        <textarea name="material" class="form-control" rows="3" placeholder="Indique materiales..."><?= htmlspecialchars($grupo['material'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group col-span-4">
                        <label>Modificaciones:</label>
                        <textarea name="modificacion_texto" class="form-control" rows="3" placeholder="Indique modificaciones realizadas..."><?= htmlspecialchars($grupo['modificacion_texto'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group col-span-4">
                        <label>Motivo de Anulación (en su caso):</label>
                        <textarea name="motivo_anulacion" class="form-control" rows="3" placeholder="Indique el motivo en caso de anular el grupo..."><?= htmlspecialchars($grupo['motivo_anulacion'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group col-span-4">
                        <label>Justificación:</label>
                        <textarea name="justificacion" class="form-control" rows="3"><?= htmlspecialchars($grupo['justificacion'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group col-span-4">
                        <label>Observaciones:</label>
                        <textarea name="observaciones" class="form-control" rows="4"><?= htmlspecialchars($grupo['observaciones'] ?? "Del 01/08/2025 al 29/08/2025 no hay tutorías por periodo vacacional.\nLos participantes tienen la opción de conectarse a la plataforma las 24 horas del día los 7 días de la semana.") ?></textarea>
                    </div>
                    <div class="form-group col-span-4">
                        <label>Servicio de orientación sociolaboral-FES UGT:</label>
                        <textarea name="orientacion_ugt" class="form-control" rows="3"><?= htmlspecialchars($grupo['orientacion_ugt'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group col-span-4">
                        <label>Notas Internas:</label>
                        <textarea name="notas_internas" class="form-control" rows="3"><?= htmlspecialchars($grupo['notas_internas'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- SECTION 12: FACTURACIÓN E INSPECCIÓN -->
                <div class="form-section-title">Facturación e Inspección</div>
                <div class="form-grid" style="align-items: center;">
                    <div class="form-group col-span-2">
                        <label>Material didáctico facturado:</label>
                        <div class="radio-group" style="margin-top: 5px;">
                            <label class="radio-custom-label">
                                <input type="radio" name="material_facturado" value="SI" <?= ($grupo['material_facturado'] ?? 'NO') === 'SI' ? 'checked' : '' ?>> SÍ
                            </label>
                            <label class="radio-custom-label">
                                <input type="radio" name="material_facturado" value="NO" <?= ($grupo['material_facturado'] ?? 'NO') === 'NO' ? 'checked' : '' ?>> NO
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div style="margin-top: 15px;">
                            <label class="checkbox-custom-label">
                                <input type="checkbox" name="inspeccionado" value="1" <?= ($grupo['inspeccionado'] ?? 0) ? 'checked' : '' ?>>
                                Inspeccionado
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Fecha Inspección:</label>
                        <input type="date" name="fecha_inspeccion" class="form-control" value="<?= $grupo['fecha_inspeccion'] ?? '' ?>">
                    </div>
                </div>

                <div class="footer-actions">
                    <button type="submit" class="btn-save">Guardar registro</button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
