<?php
// encuesta_fundae.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// No requiere auth.php para permitir el acceso directo a los alumnos desde Moodle,
// pero realizamos una validación rigurosa de los parámetros de consulta.
require_once 'includes/config.php';

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$id_alumno = isset($_GET['id_alumno']) ? (int)$_GET['id_alumno'] : 0;
$moodle_user_id = isset($_GET['moodle_user_id']) ? (int)$_GET['moodle_user_id'] : null;
$moodle_course_id = isset($_GET['moodle_course_id']) ? (int)$_GET['moodle_course_id'] : null;

if (!$id_curso || !$id_alumno) {
    die("Acceso denegado: Parámetros requeridos inválidos.");
}

// Buscar la matrícula del alumno y los datos del curso y grupo
try {
    $stmt = $pdo->prepare("
        SELECT m.id as matricula_id, af.id as accion_id, af.titulo as curso_nombre, af.abreviatura, af.duracion,
               af.modalidad, af.num_accion, g.numero_grupo, g.fecha_inicio, g.fecha_fin,
               co.codigo_expediente,
               a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.sexo, a.email
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN planes pl ON af.plan_id = pl.id
        LEFT JOIN convocatorias co ON pl.convocatoria_id = co.id
        WHERE a.id = ? AND af.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id_alumno, $id_curso]);
    $matricula = $stmt->fetch();
} catch (Exception $e) {
    die("Error al consultar la matrícula: " . $e->getMessage());
}

if (!$matricula) {
    die("Acceso denegado: No se encontró una matrícula activa para este alumno en la acción formativa especificada.");
}

// Si la encuesta ya se ha completado, redirigir a success
try {
    $stmtCheck = $pdo->prepare("SELECT id FROM encuestas_resultados WHERE matricula_id = ?");
    $stmtCheck->execute([$matricula['matricula_id']]);
    $existingSurvey = $stmtCheck->fetch();
    if ($existingSurvey) {
        header("Location: encuesta_success.php?encuesta_id=" . $existingSurvey['id']);
        exit();
    }
} catch (Exception $e) {}

// Procesar el envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'matricula_id' => $matricula['matricula_id'],
            'edad' => !empty($_POST['edad']) ? (int)$_POST['edad'] : null,
            'sexo' => $_POST['sexo'] ?? null,
            'titulacion' => $_POST['titulacion'] ?? null,
            'otra_titulacion' => $_POST['otra_titulacion'] ?? null,
            'otra_titulacion_txt' => $_POST['otra_titulacion_txt'] ?? null,
            'situacion_laboral' => $_POST['situacion_laboral'] ?? null,
            'residencia_provincia' => $_POST['residencia_provincia'] ?? null,
            'trabajo_provincia' => $_POST['trabajo_provincia'] ?? null,
            'como_conocio' => $_POST['como_conocio'] ?? null,
            'como_conocio_txt' => $_POST['como_conocio_txt'] ?? null,
            'categoria_profesional' => $_POST['categoria_profesional'] ?? null,
            'categoria_profesional_txt' => $_POST['categoria_profesional_txt'] ?? null,
            'horario_curso' => $_POST['horario_curso'] ?? null,
            'jornada_porcentaje' => $_POST['jornada_porcentaje'] ?? null,
            'tamano_empresa' => $_POST['tamano_empresa'] ?? null,
            
            // Valoraciones (1-4)
            'p1_1' => isset($_POST['p1_1']) ? (int)$_POST['p1_1'] : null,
            'p1_2' => isset($_POST['p1_2']) ? (int)$_POST['p1_2'] : null,
            'p2_1' => isset($_POST['p2_1']) ? (int)$_POST['p2_1'] : null,
            'p2_2' => isset($_POST['p2_2']) ? (int)$_POST['p2_2'] : null,
            'p3_1' => isset($_POST['p3_1']) ? (int)$_POST['p3_1'] : null,
            'p3_2' => isset($_POST['p3_2']) ? (int)$_POST['p3_2'] : null,
            'p4_1_f' => isset($_POST['p4_1_f']) ? (int)$_POST['p4_1_f'] : null,
            'p4_2_f' => isset($_POST['p4_2_f']) ? (int)$_POST['p4_2_f'] : null,
            'p4_1_t' => isset($_POST['p4_1_t']) ? (int)$_POST['p4_1_t'] : null,
            'p4_2_t' => isset($_POST['p4_2_t']) ? (int)$_POST['p4_2_t'] : null,
            'p5_1' => isset($_POST['p5_1']) ? (int)$_POST['p5_1'] : null,
            'p5_2' => isset($_POST['p5_2']) ? (int)$_POST['p5_2'] : null,
            'p6_1' => isset($_POST['p6_1']) ? (int)$_POST['p6_1'] : null,
            'p6_2' => isset($_POST['p6_2']) ? (int)$_POST['p6_2'] : null,
            'p7_1' => isset($_POST['p7_1']) ? (int)$_POST['p7_1'] : null,
            'p7_2' => isset($_POST['p7_2']) ? (int)$_POST['p7_2'] : null,
            
            // Si / No
            'p8_1' => $_POST['p8_1'] ?? null,
            'p8_2' => $_POST['p8_2'] ?? null,
            
            // Valoración general (1-4)
            'p9_1' => isset($_POST['p9_1']) ? (int)$_POST['p9_1'] : null,
            'p9_2' => isset($_POST['p9_2']) ? (int)$_POST['p9_2'] : null,
            'p9_3' => isset($_POST['p9_3']) ? (int)$_POST['p9_3'] : null,
            'p9_4' => isset($_POST['p9_4']) ? (int)$_POST['p9_4'] : null,
            'p9_5' => isset($_POST['p9_5']) ? (int)$_POST['p9_5'] : null,
            'p10_1' => isset($_POST['p10_1']) ? (int)$_POST['p10_1'] : null,
            'comentarios' => $_POST['comentarios'] ?? null,
            
            // Prácticas
            'p12_1' => isset($_POST['p12_1']) ? (int)$_POST['p12_1'] : null,
            'p12_2' => $_POST['p12_2'] ?? null,
            'p12_3' => isset($_POST['p12_3']) ? (int)$_POST['p12_3'] : null,
            'p12_4' => isset($_POST['p12_4']) ? (int)$_POST['p12_4'] : null,
            'p12_5' => $_POST['p12_5'] ?? null
        ];

        $sqlInsert = "INSERT INTO encuestas_resultados (
            matricula_id, edad, sexo, titulacion, otra_titulacion, otra_titulacion_txt, 
            situacion_laboral, residencia_provincia, trabajo_provincia, como_conocio, como_conocio_txt, 
            categoria_profesional, categoria_profesional_txt, horario_curso, jornada_porcentaje, tamano_empresa,
            p1_1, p1_2, p2_1, p2_2, p3_1, p3_2, p4_1_f, p4_2_f, p4_1_t, p4_2_t, p5_1, p5_2, 
            p6_1, p6_2, p7_1, p7_2, p8_1, p8_2, p9_1, p9_2, p9_3, p9_4, p9_5, p10_1, comentarios,
            p12_1, p12_2, p12_3, p12_4, p12_5
        ) VALUES (
            :matricula_id, :edad, :sexo, :titulacion, :otra_titulacion, :otra_titulacion_txt, 
            :situacion_laboral, :residencia_provincia, :trabajo_provincia, :como_conocio, :como_conocio_txt, 
            :categoria_profesional, :categoria_profesional_txt, :horario_curso, :jornada_porcentaje, :tamano_empresa,
            :p1_1, :p1_2, :p2_1, :p2_2, :p3_1, :p3_2, :p4_1_f, :p4_2_f, :p4_1_t, :p4_2_t, :p5_1, :p5_2, 
            :p6_1, :p6_2, :p7_1, :p7_2, :p8_1, :p8_2, :p9_1, :p9_2, :p9_3, :p9_4, :p9_5, :p10_1, :comentarios,
            :p12_1, :p12_2, :p12_3, :p12_4, :p12_5
        )";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute($data);
        $encuesta_id = $pdo->lastInsertId();

        header("Location: encuesta_success.php?encuesta_id=" . $encuesta_id);
        exit();

    } catch (Exception $e) {
        $error_msg = "Error al guardar el cuestionario: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluación de Calidad - Fundae</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #b91c1c;
            --primary-light: #ef4444;
            --primary-bg: #fef2f2;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(241, 245, 249, 0.9);
            --card-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.03);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
        }

        .container {
            width: 100%;
            max-width: 900px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        header.form-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            padding: 30px;
            color: white;
            text-align: center;
            border-bottom: 4px solid var(--primary);
        }

        header.form-header img {
            max-height: 50px;
            margin-bottom: 15px;
        }

        header.form-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }

        header.form-header p {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 10px;
        }

        .form-body {
            padding: 40px 30px;
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e3a8a;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
            margin-bottom: 20px;
            text-transform: uppercase;
            margin-top: 30px;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        /* Readonly Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 15px;
        }

        .info-box label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-box span {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Fields styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label.field-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        input[type="number"], input[type="text"], select, textarea {
            width: 100%;
            height: 42px;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 8px;
            padding: 0 15px;
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text-main);
            transition: all 0.2s;
        }

        textarea {
            height: 100px;
            padding: 10px 15px;
            resize: vertical;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
        }

        /* Horizontal Radios & Grid options */
        .options-vertical {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .option-radio {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .option-radio input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .conditional-section {
            background: #f8fafc;
            border-left: 4px solid #1e3a8a;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin-top: 15px;
            display: none;
        }

        /* Valuation Table */
        .valuation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 0.88rem;
        }

        .valuation-table th, .valuation-table td {
            border: 1px solid #e2e8f0;
            padding: 12px;
            text-align: left;
        }

        .valuation-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #1e3a8a;
            text-align: center;
        }

        .valuation-table th.question-header {
            text-align: left;
        }

        .valuation-table td.radio-cell {
            text-align: center;
            width: 70px;
        }

        .valuation-table td.radio-cell input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        /* Buttons */
        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, #991b1b 100%);
            color: white;
            border: none;
            height: 50px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.2);
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 25px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(185, 28, 28, 0.3);
        }

        @media (max-width: 768px) {
            body {
                padding: 10px 0;
            }
            .container {
                border-radius: 0;
            }
            .form-body {
                padding: 20px 15px;
            }
            .valuation-table th, .valuation-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="background: white; padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: center; align-items: center;">
            <img src="img/cabecera_fundae.png" alt="Logos Ministerio y Fundae" style="max-width: 100%; height: auto; max-height: 90px; object-fit: contain;">
        </div>
        <header class="form-header">
            <h1 style="font-size: 1.15rem; line-height: 1.4; margin-bottom: 5px;">CUESTIONARIO DE LA EVALUACIÓN PARA LA CALIDAD DE LAS ACCIONES FORMATIVAS EN EL MARCO DEL SISTEMA DE FORMACIÓN PARA EL EMPLEO<br><span style="color: var(--primary-light);">FORMACIÓN DE OFERTA</span></h1>
            <p style="font-size: 0.85rem; margin-top: 5px; font-weight: 600;">(Orden TAS/718/2008, de 7 de Marzo)</p>
        </header>

        <form method="POST" action="" class="form-body" id="fundaeForm">
            <?php if (isset($error_msg)): ?>
                <div style="background: #fee2e2; border: 1px solid #f87171; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- I. DATOS IDENTIFICATIVOS -->
            <div class="section-title">I. Datos Identificativos del Curso</div>
            <div class="info-grid">
                <div class="info-box">
                    <label>Nº Expediente / Curso</label>
                    <span><?= htmlspecialchars($matricula['codigo_expediente'] ?? '---') ?></span>
                </div>
                <div class="info-box">
                    <label>Nº Acción</label>
                    <span><?= htmlspecialchars($matricula['num_accion'] ?? '---') ?></span>
                </div>
                <div class="info-box">
                    <label>Nº Grupo</label>
                    <span><?= htmlspecialchars($matricula['numero_grupo'] ?? '---') ?></span>
                </div>
            </div>
            <div class="info-grid" style="grid-template-columns: 2fr 1fr 1fr;">
                <div class="info-box">
                    <label>Denominación de la acción</label>
                    <span><?= htmlspecialchars($matricula['curso_nombre']) ?></span>
                </div>
                <div class="info-box">
                    <label>Modalidad</label>
                    <span><?= htmlspecialchars($matricula['modalidad'] ?? 'Teleformación') ?></span>
                </div>
                <div class="info-box">
                    <label>Duración</label>
                    <span><?= htmlspecialchars($matricula['duracion'] ?? '---') ?> h</span>
                </div>
            </div>

            <!-- II. DATOS A CUMPLIMENTAR POR EL PARTICIPANTE -->
            <div class="section-title">II. Datos del Participante</div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="field-label">1. Edad:</label>
                    <input type="number" name="edad" required min="16" max="99" placeholder="Introduce tu edad">
                </div>
                <div class="form-group">
                    <label class="field-label">2. Sexo:</label>
                    <div class="options-vertical" style="flex-direction: row; gap: 30px; margin-top: 10px;">
                        <label class="option-radio">
                            <input type="radio" name="sexo" value="Mujer" required <?= ($matricula['sexo'] == 'Mujer' ? 'checked' : '') ?>> Mujer
                        </label>
                        <label class="option-radio">
                            <input type="radio" name="sexo" value="Varon" required <?= ($matricula['sexo'] == 'Hombre' || $matricula['sexo'] == 'Varon' ? 'checked' : '') ?>> Varón
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label">3. Titulación Actual:</label>
                <select name="titulacion" required>
                    <option value="">-- Selecciona tu nivel de estudios --</option>
                    <option value="1">1. Sin titulación</option>
                    <option value="11">11. Certificado de Profesionalidad Nivel 1</option>
                    <option value="12">12. Formación Profesional Básica / Cualificación Profesional Inicial</option>
                    <option value="2">2. Título Graduado ESO / Graduado Escolar</option>
                    <option value="21">21. Certificado de Profesionalidad Nivel 2</option>
                    <option value="3">3. Título Bachiller</option>
                    <option value="4">4. Título de técnico FP grado medio/FPI</option>
                    <option value="41">41. Título profesional de música y danza, artes plásticas y diseño o deportivas</option>
                    <option value="42">42. Certificado de Profesionalidad Nivel 3</option>
                    <option value="5">5. Título de Técnico FP grado superior/FPII</option>
                    <option value="6">6. Estudios universitarios 1º ciclo (Diplomatura-Grado)</option>
                    <option value="7">7. Estudios universitarios 2º ciclo (Licenciatura-Máster)</option>
                    <option value="8">8. Estudios universitarios 3º ciclo (Doctorado)</option>
                    <option value="9">9. Título de Doctor</option>
                </select>
            </div>

            <div class="form-group">
                <label class="field-label">3.1. Otra titulación:</label>
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                    <select name="otra_titulacion" id="otra_titulacion_select">
                        <option value="0">Ninguna de las siguientes</option>
                        <option value="1">1. Carnet profesional</option>
                        <option value="2">2. Enseñanzas de escuelas oficiales de idiomas</option>
                        <option value="3">3. Otra titulación no formal</option>
                    </select>
                    <input type="text" name="otra_titulacion_txt" id="otra_titulacion_txt" placeholder="Especificar..." disabled>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label">4. Situación Laboral:</label>
                <div class="options-vertical">
                    <label class="option-radio">
                        <input type="radio" name="situacion_laboral" value="1" required onclick="toggleLaborFields(false)"> 1. Desempleado/a
                    </label>
                    <label class="option-radio">
                        <input type="radio" name="situacion_laboral" value="2" required onclick="toggleLaborFields(true)"> 2. Trabajador/a por cuenta propia (empresario, autónomo, cooperativista...)
                    </label>
                    <label class="option-radio">
                        <input type="radio" name="situacion_laboral" value="3" required onclick="toggleLaborFields(true)"> 3. Trabajador/a por cuenta ajena (público/privado)
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label" id="residencia_label">5. Lugar de residencia (Provincia):</label>
                <input type="text" name="residencia_provincia" required placeholder="Provincia...">
            </div>

            <!-- Conditional Section for Ocupados -->
            <div id="labor_conditional_fields" class="conditional-section">
                <div class="form-group">
                    <label class="field-label">5. Lugar del centro de trabajo (Provincia):</label>
                    <input type="text" name="trabajo_provincia" id="trabajo_provincia" placeholder="Provincia...">
                </div>

                <div class="form-group">
                    <label class="field-label">7. Categoría profesional:</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <select name="categoria_profesional" id="categoria_profesional">
                            <option value="">-- Seleccionar --</option>
                            <option value="1">1. Directivo/a</option>
                            <option value="2">2. Mando intermedio</option>
                            <option value="3">3. Técnico/a</option>
                            <option value="4">4. Trabajador/a cualificado/a</option>
                            <option value="5">5. Trabajador/a de baja cualificación</option>
                            <option value="6">6. Otra categoría (Especificar)</option>
                        </select>
                        <input type="text" name="categoria_profesional_txt" id="categoria_profesional_txt" placeholder="Especificar..." disabled>
                    </div>
                </div>

                <div class="form-group">
                    <label class="field-label">8. Horario del curso:</label>
                    <select name="horario_curso" id="horario_curso">
                        <option value="">-- Seleccionar --</option>
                        <option value="1">1. Dentro de la jornada laboral</option>
                        <option value="2">2. Fuera de la jornada laboral</option>
                        <option value="3">3. Ambas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="field-label">8.1. % jornada laboral que abarca el curso:</label>
                    <select name="jornada_porcentaje" id="jornada_porcentaje">
                        <option value="">-- Seleccionar --</option>
                        <option value="1">1. Menos del 25%</option>
                        <option value="2">2. Entre el 25% y el 50%</option>
                        <option value="3">3. Más del 50%</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="field-label">9. Tamaño de la empresa del participante:</label>
                    <select name="tamano_empresa" id="tamano_empresa">
                        <option value="">-- Seleccionar --</option>
                        <option value="1">1. De 1 a 9 empleados</option>
                        <option value="2">2. De 10 a 49 empleados</option>
                        <option value="3">3. De 50 a 99 empleados</option>
                        <option value="4">4. De 100 a 250 empleados</option>
                        <option value="5">5. Más de 250 empleados</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label">6. ¿Cómo conoció la existencia de este curso?</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <select name="como_conocio" id="como_conocio" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="1">1. Servicio Público de Empleo</option>
                        <option value="2">2. Itinerario formativo</option>
                        <option value="3">3. A través de mi empresa</option>
                        <option value="4">4. Organización empresarial o sindical</option>
                        <option value="5">5. Medios de comunicación</option>
                        <option value="6">6. Otros (Especificar)</option>
                    </select>
                    <input type="text" name="como_conocio_txt" id="como_conocio_txt" placeholder="Especificar..." disabled>
                </div>
            </div>


            <!-- III. VALORACIÓN DE LAS ACCIONES FORMATIVAS -->
            <div class="section-title">III. Valoración de la Acción Formativa</div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px; line-height: 1.4;">
                Valore los siguientes aspectos del curso utilizando una escala de puntuación de 1 a 4.<br>
                <strong>1 = Completamente en desacuerdo, 2 = En desacuerdo, 3 = De acuerdo, 4 = Completamente de acuerdo</strong>
            </p>

            <table class="valuation-table">
                <thead>
                    <tr>
                        <th class="question-header">Aspecto a Valorar</th>
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th>4</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Bloque 1: Organización -->
                    <tr>
                        <td colspan="5" style="background:#f1f5f9; font-weight:700; color:#1e3a8a;">1. Organización del curso</td>
                    </tr>
                    <tr>
                        <td>1.1 El curso ha estado bien organizado (información, cumplimiento de fechas y horarios, entrega material)</td>
                        <td class="radio-cell"><input type="radio" name="p1_1" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p1_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p1_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p1_1" value="4"></td>
                    </tr>
                    <tr>
                        <td>1.2 El número de alumnos del grupo ha sido adecuado para el desarrollo del curso</td>
                        <td class="radio-cell"><input type="radio" name="p1_2" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p1_2" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p1_2" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p1_2" value="4"></td>
                    </tr>

                    <!-- Bloque 2: Contenidos -->
                    <tr>
                        <td colspan="5" style="background:#f1f5f9; font-weight:700; color:#1e3a8a;">2. Contenidos del curso</td>
                    </tr>
                    <tr>
                        <td>2.1 Los contenidos del curso han respondido a mis necesidades formativas</td>
                        <td class="radio-cell"><input type="radio" name="p2_1" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p2_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p2_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p2_1" value="4"></td>
                    </tr>
                    <tr>
                        <td>2.2 Ha habido una combinación adecuada de teoría y aplicación práctica</td>
                        <td class="radio-cell"><input type="radio" name="p2_2" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p2_2" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p2_2" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p2_2" value="4"></td>
                    </tr>

                    <!-- Bloque 3: Duración y horario -->
                    <tr>
                        <td colspan="5" style="background:#f1f5f9; font-weight:700; color:#1e3a8a;">3. Duración y horario</td>
                    </tr>
                    <tr>
                        <td>3.1 La duración del curso ha sido suficiente según los objetivos y contenidos</td>
                        <td class="radio-cell"><input type="radio" name="p3_1" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p3_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p3_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p3_1" value="4"></td>
                    </tr>
                    <tr>
                        <td>3.2 El horario ha favorecido la asistencia del curso</td>
                        <td class="radio-cell"><input type="radio" name="p3_2" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p3_2" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p3_2" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p3_2" value="4"></td>
                    </tr>

                    <!-- Bloque 4: Formadores/Tutores -->
                    <tr>
                        <td colspan="5" style="background:#f1f5f9; font-weight:700; color:#1e3a8a;">4. Formadores y Tutores</td>
                    </tr>
                    <tr>
                        <td>4.1.F La forma de impartir del <strong>Formador</strong> ha facilitado el aprendizaje</td>
                        <td class="radio-cell"><input type="radio" name="p4_1_f" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p4_1_f" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p4_1_f" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p4_1_f" value="4"></td>
                    </tr>
                    <tr>
                        <td>4.2.F El <strong>Formador</strong> conoce los temas impartidos en profundidad</td>
                        <td class="radio-cell"><input type="radio" name="p4_2_f" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p4_2_f" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p4_2_f" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p4_2_f" value="4"></td>
                    </tr>
                    <tr>
                        <td>4.1.T La labor de tutorización del <strong>Tutor</strong> ha facilitado el aprendizaje</td>
                        <td class="radio-cell"><input type="radio" name="p4_1_t" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p4_1_t" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p4_1_t" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p4_1_t" value="4"></td>
                    </tr>
                    <tr>
                        <td>4.2.T El <strong>Tutor</strong> conoce los temas del curso en profundidad</td>
                        <td class="radio-cell"><input type="radio" name="p4_2_t" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p4_2_t" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p4_2_t" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p4_2_t" value="4"></td>
                    </tr>

                    <!-- Bloque 5: Medios didácticos -->
                    <tr>
                        <td colspan="5" style="background:#f1f5f9; font-weight:700; color:#1e3a8a;">5. Medios didácticos (guías, manuales, fichas...)</td>
                    </tr>
                    <tr>
                        <td>5.1 La documentación y materiales entregados son comprensibles y adecuados</td>
                        <td class="radio-cell"><input type="radio" name="p5_1" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p5_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p5_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p5_1" value="4"></td>
                    </tr>
                    <tr>
                        <td>5.2 Los medios didácticos están actualizados</td>
                        <td class="radio-cell"><input type="radio" name="p5_2" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p5_2" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p5_2" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p5_2" value="4"></td>
                    </tr>

                    <!-- Bloque 6: Instalaciones -->
                    <tr>
                        <td colspan="5" style="background:#f1f5f9; font-weight:700; color:#1e3a8a;">6. Instalaciones y medios técnicos</td>
                    </tr>
                    <tr>
                        <td>6.1 El aula, el taller o las plataformas online han sido apropiadas para el desarrollo del curso</td>
                        <td class="radio-cell"><input type="radio" name="p6_1" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p6_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p6_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p6_1" value="4"></td>
                    </tr>
                    <tr>
                        <td>6.2 Los medios técnicos han sido adecuados para desarrollar el contenido del curso</td>
                        <td class="radio-cell"><input type="radio" name="p6_2" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p6_2" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p6_2" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p6_2" value="4"></td>
                    </tr>

                    <!-- Bloque 7: Teleformacion / Mixta -->
                    <tr>
                        <td colspan="5" style="background:#f1f5f9; font-weight:700; color:#1e3a8a;">7. Sólo para cursos de Teleformación o Mixtos</td>
                    </tr>
                    <tr>
                        <td>7.1 Las guías tutoriales y materiales didácticos permitieron realizar fácilmente el curso online</td>
                        <td class="radio-cell"><input type="radio" name="p7_1" value="1" <?= ($matricula['modalidad'] == 'Teleformacion' || $matricula['modalidad'] == 'Mixta' ? 'required' : '') ?>></td>
                        <td class="radio-cell"><input type="radio" name="p7_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p7_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p7_1" value="4"></td>
                    </tr>
                    <tr>
                        <td>7.2 Se ha contado con medios de apoyo suficientes (tutorías, correos, bibliotecas virtuales...)</td>
                        <td class="radio-cell"><input type="radio" name="p7_2" value="1" <?= ($matricula['modalidad'] == 'Teleformacion' || $matricula['modalidad'] == 'Mixta' ? 'required' : '') ?>></td>
                        <td class="radio-cell"><input type="radio" name="p7_2" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p7_2" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p7_2" value="4"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Bloque 8: Evaluacion (Si / No) -->
            <div class="form-group">
                <label class="field-label">8. Mecanismos para la evaluación del aprendizaje:</label>
                <div class="options-vertical" style="gap: 15px; margin-bottom: 15px;">
                    <div>
                        <p style="font-size:0.9rem; margin-bottom: 5px;">8.1 ¿Se han dispuesto de pruebas de evaluación que permiten conocer el nivel alcanzado?</p>
                        <div style="display:flex; gap: 20px;">
                            <label class="option-radio"><input type="radio" name="p8_1" value="Si" required> Sí</label>
                            <label class="option-radio"><input type="radio" name="p8_1" value="No"> No</label>
                        </div>
                    </div>
                    <div>
                        <p style="font-size:0.9rem; margin-bottom: 5px;">8.2 ¿El curso te permite obtener una acreditación donde se reconoce tu cualificación?</p>
                        <div style="display:flex; gap: 20px;">
                            <label class="option-radio"><input type="radio" name="p8_2" value="Si" required> Sí</label>
                            <label class="option-radio"><input type="radio" name="p8_2" value="No"> No</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bloque 9: Valoracion General -->
            <div class="section-title">9. Valoración General del Curso</div>
            <table class="valuation-table">
                <thead>
                    <tr>
                        <th class="question-header">Aspecto</th>
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th>4</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>9.1 Puede contribuir a mi incorporación al mercado de trabajo</td>
                        <td class="radio-cell"><input type="radio" name="p9_1" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p9_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p9_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p9_1" value="4"></td>
                    </tr>
                    <tr>
                        <td>9.2 Me ha permitido adquirir nuevas habilidades/capacidades aplicables a mi puesto</td>
                        <td class="radio-cell"><input type="radio" name="p9_2" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p9_2" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p9_2" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p9_2" value="4"></td>
                    </tr>
                    <tr>
                        <td>9.3 Ha mejorado mis posibilidades para cambiar de puesto en la empresa o fuera</td>
                        <td class="radio-cell"><input type="radio" name="p9_3" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p9_3" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p9_3" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p9_3" value="4"></td>
                    </tr>
                    <tr>
                        <td>9.4 He ampliado conocimientos para progresar en mi carrera profesional</td>
                        <td class="radio-cell"><input type="radio" name="p9_4" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p9_4" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p9_4" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p9_4" value="4"></td>
                    </tr>
                    <tr>
                        <td>9.5 Ha favorecido mi desarrollo personal</td>
                        <td class="radio-cell"><input type="radio" name="p9_5" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p9_5" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p9_5" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p9_5" value="4"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Bloque 10: Satisfaccion General -->
            <table class="valuation-table">
                <thead>
                    <tr>
                        <th class="question-header">10. Grado de satisfacción general con el curso</th>
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th>4</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Valora globalmente el nivel de satisfacción que te ha aportado la acción formativa</td>
                        <td class="radio-cell"><input type="radio" name="p10_1" value="1" required></td>
                        <td class="radio-cell"><input type="radio" name="p10_1" value="2"></td>
                        <td class="radio-cell"><input type="radio" name="p10_1" value="3"></td>
                        <td class="radio-cell"><input type="radio" name="p10_1" value="4"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Bloque 11: Comentarios -->
            <div class="form-group">
                <label class="field-label">11. Si desea realizar cualquier sugerencia u observación, por favor use este espacio:</label>
                <textarea name="comentarios" placeholder="Escribe aquí tus sugerencias o propuestas de mejora..."></textarea>
            </div>

            <!-- Bloque 12: Prácticas no laborales (Opcional/Condicional) -->
            <div class="form-group" style="background:#f8fafc; border: 1px dashed #cbd5e1; padding: 20px; border-radius: 8px; margin-top: 30px;">
                <label class="option-radio" style="font-weight: 700; color: #1e3a8a; margin-bottom: 10px;">
                    <input type="checkbox" id="check_practicas" onchange="togglePracticasFields(this.checked)"> ¿Has realizado Prácticas No Laborales en Empresas como parte de este curso?
                </label>
                
                <div id="practicas_fields" style="display: none; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;">
                        Valora los siguientes aspectos de las prácticas del 1 al 4 (o responde Sí/No según aplique).
                    </p>

                    <div class="form-group">
                        <label class="field-label">12.1 Las prácticas están relacionadas con los contenidos teóricos-prácticos:</label>
                        <div style="display:flex; gap: 20px;">
                            <label class="option-radio"><input type="radio" name="p12_1" value="1"> 1</label>
                            <label class="option-radio"><input type="radio" name="p12_1" value="2"> 2</label>
                            <label class="option-radio"><input type="radio" name="p12_1" value="3"> 3</label>
                            <label class="option-radio"><input type="radio" name="p12_1" value="4"> 4</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="field-label">12.2 ¿Han sido suficientes las horas dedicadas a las prácticas no laborales en la empresa?</label>
                        <div style="display:flex; gap: 20px;">
                            <label class="option-radio"><input type="radio" name="p12_2" value="Si"> Sí</label>
                            <label class="option-radio"><input type="radio" name="p12_2" value="No"> No</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="field-label">12.3 Las prácticas le han permitido adquirir las habilidades necesarias para trabajar:</label>
                        <div style="display:flex; gap: 20px;">
                            <label class="option-radio"><input type="radio" name="p12_3" value="1"> 1</label>
                            <label class="option-radio"><input type="radio" name="p12_3" value="2"> 2</label>
                            <label class="option-radio"><input type="radio" name="p12_3" value="3"> 3</label>
                            <label class="option-radio"><input type="radio" name="p12_3" value="4"> 4</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="field-label">12.4 ¿Cómo valora el seguimiento que ha recibido del tutor o tutores de las prácticas?:</label>
                        <div style="display:flex; gap: 20px;">
                            <label class="option-radio"><input type="radio" name="p12_4" value="1"> 1</label>
                            <label class="option-radio"><input type="radio" name="p12_4" value="2"> 2</label>
                            <label class="option-radio"><input type="radio" name="p12_4" value="3"> 3</label>
                            <label class="option-radio"><input type="radio" name="p12_4" value="4"> 4</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="field-label">12.5 Describa, brevemente, cuál ha sido el contenido de las prácticas realizadas:</label>
                        <textarea name="p12_5" placeholder="Contenido de las prácticas..."></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Enviar Cuestionario de Valoración</button>
        </form>
    </div>

    <script>
        // Toggle conditional occupational fields
        function toggleLaborFields(show) {
            const container = document.getElementById('labor_conditional_fields');
            const workProv = document.getElementById('trabajo_provincia');
            const cat = document.getElementById('categoria_profesional');
            const hor = document.getElementById('horario_curso');
            const jorn = document.getElementById('jornada_porcentaje');
            const tam = document.getElementById('tamano_empresa');
            const resLabel = document.getElementById('residencia_label');
            
            if (show) {
                container.style.display = 'block';
                workProv.required = true;
                cat.required = true;
                hor.required = true;
                jorn.required = true;
                tam.required = true;
                resLabel.textContent = "5. Lugar de residencia (Provincia):";
            } else {
                container.style.display = 'none';
                workProv.required = false;
                cat.required = false;
                hor.required = false;
                jorn.required = false;
                tam.required = false;
                resLabel.textContent = "5. Lugar de residencia (Provincia):";
            }
        }

        // Toggle practices fields
        function togglePracticasFields(checked) {
            const container = document.getElementById('practicas_fields');
            const p1 = document.querySelectorAll('input[name="p12_1"]');
            const p2 = document.querySelectorAll('input[name="p12_2"]');
            const p3 = document.querySelectorAll('input[name="p12_3"]');
            const p4 = document.querySelectorAll('input[name="p12_4"]');
            
            container.style.display = checked ? 'block' : 'none';
            p1.forEach(el => el.required = checked);
            p2.forEach(el => el.required = checked);
            p3.forEach(el => el.required = checked);
            p4.forEach(el => el.required = checked);
        }

        // Enable specify texts
        document.getElementById('otra_titulacion_select').addEventListener('change', function() {
            document.getElementById('otra_titulacion_txt').disabled = this.value !== '3';
            if (this.value === '3') document.getElementById('otra_titulacion_txt').required = true;
        });

        document.getElementById('como_conocio').addEventListener('change', function() {
            document.getElementById('como_conocio_txt').disabled = this.value !== '6';
            if (this.value === '6') document.getElementById('como_conocio_txt').required = true;
        });

        document.getElementById('categoria_profesional').addEventListener('change', function() {
            document.getElementById('categoria_profesional_txt').disabled = this.value !== '6';
            if (this.value === '6') document.getElementById('categoria_profesional_txt').required = true;
        });
    </script>
</body>
</html>
