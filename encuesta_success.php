<?php
// encuesta_success.php
require_once 'includes/config.php';

$encuesta_id = isset($_GET['encuesta_id']) ? (int)$_GET['encuesta_id'] : 0;
if (!$encuesta_id) {
    die("ID de encuesta no proporcionado.");
}

// Obtener datos básicos para verificar que existe
try {
    $stmt = $pdo->prepare("
        SELECT er.id, af.titulo as curso_nombre
        FROM encuestas_resultados er
        JOIN matriculas m ON er.matricula_id = m.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        WHERE er.id = ?
    ");
    $stmt->execute([$encuesta_id]);
    $survey = $stmt->fetch();
} catch (Exception $e) {
    die("Error al consultar la encuesta: " . $e->getMessage());
}

if (!$survey) {
    die("Cuestionario no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuestionario Completado - Fundae</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --card-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .success-card {
            background: var(--glass-bg);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .icon-container {
            width: 80px;
            height: 80px;
            background: #ecfdf5;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            border: 3px solid #a7f3d0;
        }

        .icon-container svg {
            width: 40px;
            height: 40px;
            color: var(--primary);
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 10px;
        }

        p {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .course-title {
            background: #f0fdf4;
            border: 1px solid #c6f6d5;
            border-radius: 8px;
            padding: 12px 15px;
            font-weight: 600;
            color: #047857;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            color: white;
            text-decoration: none;
            height: 48px;
            padding: 0 25px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            transition: all 0.2s;
            width: 100%;
        }

        .btn-download:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.25);
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="icon-container">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h1>¡Cuestionario Completado!</h1>
        <p>Tu valoración sobre el curso ha sido registrada correctamente. Agradecemos mucho tu colaboración para la mejora de la calidad de nuestras acciones formativas.</p>
        
        <div class="course-title">
            <?= htmlspecialchars($survey['curso_nombre']) ?>
        </div>

        <a href="pdf_encuesta.php?id=<?= $survey['id'] ?>" class="btn-download" target="_blank">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Descargar Cuestionario en PDF
        </a>
    </div>
</body>
</html>
