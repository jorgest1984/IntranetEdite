<?php
// documentacion_didactica.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
    header("Location: dashboard.php");
    exit();
}

$grupo_id = isset($_GET['grupo_id']) ? intval($_GET['grupo_id']) : 0;
if (!$grupo_id) {
    die("Se requiere el ID del grupo.");
}

// Fetch group info
$stmt = $pdo->prepare("
    SELECT g.numero_grupo, c.nombre_largo as curso_titulo, c.nombre_corto as curso_codigo
    FROM grupos g
    JOIN acciones_formativas af ON g.accion_id = af.id
    JOIN cursos c ON af.curso_id = c.id
    WHERE g.id = ?
");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo) {
    die("Grupo no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentación Didáctica - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .didactica-container {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            max-width: 1200px;
            margin: 0 auto;
        }

        .didactica-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .didactica-subtitle {
            font-size: 1.5rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 2rem;
            text-transform: uppercase;
        }

        .didactica-instruction {
            font-size: 1rem;
            color: #475569;
            margin-bottom: 1.5rem;
        }

        .didactica-links {
            list-style-type: disc;
            padding-left: 2rem;
            margin-bottom: 3rem;
        }

        .didactica-links li {
            margin-bottom: 0.5rem;
        }

        .didactica-links a {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .didactica-links a:hover {
            text-decoration: underline;
        }

        .notas-section h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 1rem;
        }

        .notas-section p {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 1rem;
        }

        mark.yellow-highlight {
            background-color: #fef08a;
            padding: 0 0.2rem;
            color: #000;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="margin-bottom: 1rem;">
            <div class="page-title">
                <h1>Área de Documentación Didáctica</h1>
            </div>
            <div class="page-actions">
                <a href="documentacion.php" class="btn btn-secondary">Volver a Documentación</a>
            </div>
        </header>

        <div class="didactica-container">
            <h1 class="didactica-title">Documentación del grupo <?= htmlspecialchars($grupo['numero_grupo']) ?></h1>
            <div class="didactica-subtitle">
                <?= htmlspecialchars($grupo['curso_codigo'] ? $grupo['curso_codigo'] . ' - ' : '') ?><?= htmlspecialchars($grupo['curso_titulo']) ?>
            </div>

            <p class="didactica-instruction">Selecciona el documento que deseas generar y descargar:</p>

            <ul class="didactica-links">
                <li><a href="#">Programación didáctica</a></li>
                <li><a href="#">Planificación didáctica</a></li>
                <li><a href="#">Planificación de la evaluación</a></li>
                <li><a href="#">Justificante de comunicación con la administración sobre la preselección de desempleados</a></li>
                <li><a href="#">Cumplimiento de los requisitos de acceso de los alumnos</a></li>
                <li><a href="#">Criterios para la selección de participantes en funcion de requisitos de formación</a></li>
            </ul>

            <div class="notas-section">
                <h2>Notas</h2>
                <p>Los textos resaltados con <mark class="yellow-highlight">marcador amarillo</mark> tienen que modificarse manualmente.</p>
                <p>Si las fechas no aparecen es porque no están establecidas aún en el grupo y hay que avisar a Administración.</p>
                <p>Los objetivos generales y específicos, así como el título del supuesto práctico, tienen que estar descritos en la ficha del curso en la intranet.</p>
                <p>Si no aparecen las unidades didácticas es porque no están establecidas en el curso. Hay que insertarlas en la ficha del mismo, haciendo clic en el enlace <strong>Ver / Editar unidades</strong>, indicando la programación de horas de cada unidad.</p>
                <p>Las fechas de la <strong>Planificación didáctica</strong> se calculan automáticamente en base a la asignación de horas de las unidades didácticas. Por favor, comprobar la planificación y ajustar la fecha de la <strong>Evaluación intermedia</strong> provisional para que concuerde con la mitad del curso. Si es necesario modificar la fecha intermedia, por favor, avisar al coordinador de tutores para que lo verifique.</p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
