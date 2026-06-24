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
           p.nombre as plan_nombre, 
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

$empresas = $pdo->query("SELECT id, cif, nombre FROM empresas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

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
        header("Location: ficha_matricula.php?id=$id&success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error al actualizar (es posible que algunos campos como SS o Profesion no estén en la base de datos aún): " . $e->getMessage();
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
            <button class="tab-btn active" data-target="tab-personales">Datos Personales</button>
            <button class="tab-btn" data-target="tab-laborales">Datos Laborales</button>
            <button class="tab-btn" data-target="tab-curso">Datos Curso</button>
            <button class="tab-btn" data-target="tab-docs">Material y doc.</button>
        </div>

        <div id="tab-personales" class="tab-panel">
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
                            <option value="Calle" <?= ($matricula['tipo_via'] ?? '') == 'Calle' ? 'selected' : '' ?>>Calle</option>
                            <option value="Avenida" <?= ($matricula['tipo_via'] ?? '') == 'Avenida' ? 'selected' : '' ?>>Avenida</option>
                            <option value="Camino" <?= ($matricula['tipo_via'] ?? '') == 'Camino' ? 'selected' : '' ?>>Camino</option>
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
                            <option value="MADRID" <?= ($matricula['provincia'] ?? '') == 'MADRID' ? 'selected' : '' ?>>MADRID</option>
                            <option value="BARCELONA" <?= ($matricula['provincia'] ?? '') == 'BARCELONA' ? 'selected' : '' ?>>BARCELONA</option>
                            <option value="VALENCIA" <?= ($matricula['provincia'] ?? '') == 'VALENCIA' ? 'selected' : '' ?>>VALENCIA</option>
                            <option value="SEVILLA" <?= ($matricula['provincia'] ?? '') == 'SEVILLA' ? 'selected' : '' ?>>SEVILLA</option>
                            <option value="VALLADOLID" <?= ($matricula['provincia'] ?? '') == 'VALLADOLID' ? 'selected' : '' ?>>VALLADOLID</option>
                            <!-- Idealmente rellenar con un array PHP -->
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

        <div id="tab-laborales" class="tab-panel hidden">
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
                                    <?= htmlspecialchars($emp['nombre'] . ' [' . ($emp['cif'] ?? 'Sin CIF') . ']') ?>
                                </option>
                            <?php endforeach; ?>
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
                            <option value="Trabajador por cuenta ajena" <?= ($matricula['colectivo'] ?? '') == 'Trabajador por cuenta ajena' ? 'selected' : '' ?>>Trabajador por cuenta ajena</option>
                            <option value="Desempleado" <?= ($matricula['colectivo'] ?? '') == 'Desempleado' ? 'selected' : '' ?>>Desempleado</option>
                            <option value="Autónomo" <?= ($matricula['colectivo'] ?? '') == 'Autónomo' ? 'selected' : '' ?>>Autónomo</option>
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
                            <!-- Lista de ocupaciones iría aquí -->
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
                            <!-- Lista de categorías -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Área Funcional</label>
                        <select name="area_funcional" class="form-control">
                            <option value=""></option>
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
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contrato</label>
                        <select name="contrato" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div id="tab-curso" class="tab-panel hidden">
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
                            <option value=""><?= htmlspecialchars($matricula['plan_nombre'] ?? 'Seleccione Plan') ?></option>
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
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.6rem;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="captado_ugt" value="1" style="width: 16px; height: 16px;">
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
                        <input type="text" name="prioridad" class="form-control" value="<?= htmlspecialchars($matricula['af_prioridad'] ?? '1') ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Estado para SEPE</label>
                        <select name="estado_sepe" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Fecha abandono</label>
                        <input type="date" name="fecha_abandono" class="form-control">
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
                        <input type="checkbox" name="no_preinscrito" style="width: 16px; height: 16px;"> No volver preinscrito
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #b91c1c; font-weight: 600;">
                        <input type="checkbox" name="bloqueado" <?= !empty($matricula['bloqueado']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> BLOQUEADO
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #b91c1c; font-weight: 600;">
                        <input type="checkbox" name="no_desmatricular" style="width: 16px; height: 16px;"> NO DESMATRICULAR
                    </label>
                    
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">CERTIFICABLES:</label>
                        <select name="certificables" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value="SI">SI</option><option value="NO">NO</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">FACTURABLES:</label>
                        <select name="facturables" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value=""></option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">ANULAR para SEPE:</label>
                        <select name="anular_sepe" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value="NO">NO</option><option value="SI">SI</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; font-size: 0.85rem;">Evaluación TIC:</label>
                        <select name="evaluacion_tic" class="form-control" style="width: auto; padding: 0.3rem;">
                            <option value=""></option>
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
                        <textarea name="preferencia_fechas" class="form-control" rows="2"></textarea>
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
                            <option value="<?= $matricula['grupo_id'] ?? '' ?>"><?= htmlspecialchars($matricula['numero_grupo'] ?? 'Seleccione Grupo') ?></option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 1.5rem; font-weight: 600; color: #2563eb;">
                        <?= htmlspecialchars($matricula['af_abreviatura'] ?? '') ?>-G<?= htmlspecialchars($matricula['numero_grupo'] ?? '') ?>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label>Si el alumno causa baja o abandono en el curso, indica aquí el motivo:</label>
                        <select name="motivo_baja" class="form-control"><option value=""></option></select>
                    </div>
                    <div class="form-group">
                        <label>Motivo abandono para el SEPE:</label>
                        <select name="motivo_sepe" class="form-control"><option value=""></option></select>
                    </div>
                </div>
                
                <div class="grid-form" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>Otros motivos:</label>
                        <textarea name="otros_motivos" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label>Tutor:</label>
                        <select name="tutor_id" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsable seguimiento:</label>
                        <select name="responsable_seguimiento" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div id="tab-docs" class="tab-panel hidden">
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
                            <input type="checkbox" name="entrega_mat_1" style="width: 16px; height: 16px;"> Entrega mat 1 :
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-weight: 600; color: #475569;">Fechas envío:</label>
                            <select name="fechas_envio" class="form-control" style="width: auto;"><option value=""></option></select>
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
                            <input type="checkbox" name="diploma_entregado" <?= !empty($matricula['diploma_entregado']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Diploma:
                        </label>
                        <select name="diploma_tipo" class="form-control" style="width: auto;"><option value=""></option></select>
                        <button type="button" class="btn-modern" style="background: #fbbf24; color: #92400e; font-weight: 700; border: 1px solid #f59e0b; padding: 0.4rem 1rem;">
                            🎓 Enviar Diploma
                        </button>
                        
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #431407; margin-left: 1rem;">
                            <input type="checkbox" name="comunicado" style="width: 16px; height: 16px;"> Comunicado:
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="font-weight: 600; color: #431407;">Fecha comunicación:</label>
                            <input type="date" name="fecha_comunicacion" class="form-control" style="width: auto;">
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #431407; margin-left: 1rem;">
                            <input type="checkbox" name="comunicado_ugt" style="width: 16px; height: 16px;"> Comunicado UGT:
                        </label>
                    </div>
                </div>

                <h3 class="form-section-title" style="color: #b91c1c;">Documentación general alumno:</h3>
                <div style="background: #fef08a; padding: 1.5rem; border-radius: 8px; border: 1px solid #fde047; margin-bottom: 2rem;">
                    <div class="grid-form" style="grid-template-columns: auto auto auto 1fr; align-items: center; margin-bottom: 0;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700; color: #1e3a8a;">
                            <input type="checkbox" name="nomina_entregada" <?= !empty($matricula['nomina_entregada']) ? 'checked' : '' ?> style="width: 16px; height: 16px;"> Validar nómina atrasada:
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px; margin-left: 1rem;">
                            <label style="font-weight: 700; color: #1e3a8a;">Anexo 1:</label>
                            <select name="anexo1_entregado" class="form-control" style="width: 150px;">
                                <option value=""></option>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-left: 1rem;">
                            <label style="font-weight: 700; color: #1e3a8a;">Matrícula:</label>
                            <select name="matricula_doc" class="form-control" style="width: 150px;">
                                <option value=""></option>
                            </select>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700; color: #1e3a8a; margin-left: 1rem;">
                            <input type="checkbox" name="correcto" checked style="width: 16px; height: 16px;"> Correcto:
                        </label>
                    </div>
                </div>

                <h3 class="form-section-title" style="color: #b91c1c;">Curso presencial:</h3>
                <div class="grid-form" style="grid-template-columns: auto auto auto auto auto auto auto; align-items: center; gap: 1rem;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e3a8a;">
                        <input type="checkbox" name="recibi_material" style="width: 16px; height: 16px;"> Recibí material:
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e3a8a;">
                        <input type="checkbox" name="asistencia" style="width: 16px; height: 16px;"> Asistencia:
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Días que asiste:</label>
                        <input type="text" name="dias_asiste" class="form-control" value="0.0" style="width: 60px; text-align: right;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Recibí diploma:</label>
                        <select name="recibi_diploma" class="form-control" style="width: 100px;"><option value=""></option></select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Copia diploma:</label>
                        <select name="copia_diploma" class="form-control" style="width: 100px;"><option value=""></option></select>
                    </div>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e3a8a;">
                        <input type="checkbox" name="evaluacion_docente" style="width: 16px; height: 16px;"> Evaluación Docente:
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #1e3a8a;">Apto:</label>
                        <select name="apto" class="form-control" style="width: 80px;">
                            <option value="NO">NO</option><option value="SI">SI</option>
                        </select>
                    </div>
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