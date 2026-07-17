<?php
// moodle_informes.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Verificamos si el usuario tiene acceso (Tutor o superior)
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

$cursoid = isset($_GET['cursoid']) ? (int)$_GET['cursoid'] : 0;

if (!$cursoid) {
    $error = "No se ha proporcionado el ID del curso de Moodle.";
} else {
    // Buscar a qué grupo y acción corresponde este curso en Moodle
    $stmt = $pdo->prepare("SELECT g.id as grupo_id, g.numero_grupo, af.id as accion_id, af.titulo, af.num_accion
                           FROM grupos g
                           JOIN acciones_formativas af ON g.accion_id = af.id
                           JOIN cursos c ON af.curso_id = c.id
                           WHERE c.moodle_id = ?");
    $stmt->execute([$cursoid]);
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        $error = "No se ha encontrado un grupo vinculado a este curso de Moodle ($cursoid) en la Intranet.";
    } else {
        // Cargar los alumnos
        $stmtAlumnos = $pdo->prepare("
            SELECT a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            WHERE m.grupo_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
            ORDER BY a.primer_apellido ASC, a.segundo_apellido ASC, a.nombre ASC
        ");
        $stmtAlumnos->execute([$grupo['grupo_id']]);
        $alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informes de Alumnos - Moodle</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>Informes Individuales de Seguimiento</h1>
                    <p>Generación de informes desde Moodle</p>
                </div>
            </header>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php else: ?>
                <div class="card" style="margin-bottom: 2rem;">
                    <h2><?= htmlspecialchars($grupo['num_accion']) ?> - <?= htmlspecialchars($grupo['titulo']) ?></h2>
                    <p style="color: var(--text-secondary);">Grupo: <?= htmlspecialchars($grupo['numero_grupo']) ?></p>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Apellidos y Nombre</th>
                                <th>DNI / NIE</th>
                                <th style="text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alumnos)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">No hay alumnos matriculados en este grupo.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alumnos as $al): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500; color: var(--text-primary);">
                                                <?= htmlspecialchars($al['primer_apellido'] . ' ' . $al['segundo_apellido'] . ', ' . $al['nombre']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($al['dni']) ?></td>
                                        <td style="text-align: right;">
                                            <a href="pdf_informe_alumno.php?accion_id=<?= $grupo['accion_id'] ?>&grupo_id=<?= $grupo['grupo_id'] ?>&alumno_id=<?= $al['id'] ?>" 
                                               target="_blank" 
                                               class="btn btn-primary" 
                                               style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                                               <i class="fas fa-file-pdf"></i> Generar Informe
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
