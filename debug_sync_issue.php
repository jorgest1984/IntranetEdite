<?php
// debug_sync_issue.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

if (!has_permission([ROLE_ADMIN])) {
    die("Acceso denegado. Se requiere ser Administrador.");
}

// Procesar el borrado de IDs de Moodle para forzar recreación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_moodle_ids') {
    $af_id_to_reset = (int)($_POST['af_id'] ?? 0);
    if ($af_id_to_reset > 0) {
        $stmt = $pdo->prepare("SELECT curso_id FROM acciones_formativas WHERE id = ?");
        $stmt->execute([$af_id_to_reset]);
        $row = $stmt->fetch();
        if ($row) {
            $curso_id = $row['curso_id'];
            $pdo->prepare("UPDATE cursos SET moodle_id = NULL WHERE id = ?")->execute([$curso_id]);
        }
        $pdo->prepare("UPDATE grupos SET id_plataforma = NULL WHERE accion_id = ?")->execute([$af_id_to_reset]);
        
        header("Location: debug_sync_issue.php?id=" . $af_id_to_reset . "&reset_success=1");
        exit;
    }
}

// Procesar vinculación con curso existente en Moodle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_moodle_course') {
    $af_id_to_link = (int)($_POST['af_id'] ?? 0);
    $moodle_course_id = (int)($_POST['moodle_course_id'] ?? 0);
    if ($af_id_to_link > 0 && $moodle_course_id > 0) {
        $stmt = $pdo->prepare("SELECT curso_id FROM acciones_formativas WHERE id = ?");
        $stmt->execute([$af_id_to_link]);
        $row = $stmt->fetch();
        if ($row) {
            $curso_id = $row['curso_id'];
            $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?")->execute([$moodle_course_id, $curso_id]);
        }
        header("Location: debug_sync_issue.php?id=" . $af_id_to_link . "&link_success=1");
        exit;
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Sincronización - <?= APP_NAME ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #1e293b; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        h1 { color: #1e3a8a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0; }
        .section { margin-bottom: 25px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .section-title { font-weight: bold; color: #1e40af; margin-bottom: 10px; font-size: 1.1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #e2e8f0; text-align: left; font-size: 0.9rem; }
        th { background: #cbd5e1; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .btn { display: inline-block; background: #1e3a8a; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: bold; border: none; cursor: pointer; }
        .btn:hover { background: #1e40af; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Diagnóstico de Sincronización con Moodle</h1>

    <?php if (isset($_GET['reset_success'])): ?>
        <div style="background: #dcfce7; border-left: 4px solid #16a34a; padding: 15px; margin-bottom: 20px; color: #15803d; border-radius: 4px; font-weight: bold;">
            ✓ Éxito: Se han limpiado los IDs de Moodle vinculados (Curso y Grupo) en la base de datos local de preproducción. Al volver a sincronizar se crearán de nuevo en pre-aulavirtual.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['link_success'])): ?>
        <div style="background: #dcfce7; border-left: 4px solid #16a34a; padding: 15px; margin-bottom: 20px; color: #15803d; border-radius: 4px; font-weight: bold;">
            ✓ Éxito: Se ha vinculado correctamente la Acción Formativa al curso existente en Moodle.
        </div>
    <?php endif; ?>

    <?php
    $af_id = (int)($_GET['id'] ?? 0);

    if (!$af_id) {
        // Mostrar lista de acciones formativas recientes para seleccionar
        echo "<p>Selecciona una Acción Formativa para diagnosticar:</p>";
        $stmt = $pdo->query("SELECT af.id, c.nombre_largo as titulo, af.abreviatura, c.moodle_id 
                             FROM acciones_formativas af 
                             JOIN cursos c ON af.curso_id = c.id 
                             ORDER BY af.id DESC LIMIT 30");
        echo "<ul>";
        while ($row = $stmt->fetch()) {
            $moodleText = $row['moodle_id'] ? " (Moodle ID: {$row['moodle_id']})" : " (Sin Moodle ID)";
            echo "<li><a href='?id={$row['id']}'>[#{$row['id']}] {$row['titulo']} - {$row['abreviatura']}</a>$moodleText</li>";
        }
        echo "</ul>";
        echo "</div></body></html>";
        exit;
    }

    try {
        $moodle = new MoodleAPI($pdo);
        if (!$moodle->isConfigured()) {
            throw new Exception("Moodle no está configurado.");
        }

        // 1. Obtener datos de la Acción Formativa, su Curso Moodle y su Convocatoria
        $stmt = $pdo->prepare("SELECT af.*, c.moodle_id as curso_moodle_id, c.nombre_largo as titulo, c.nombre_corto, conv.nombre as convocatoria_nombre
                               FROM acciones_formativas af 
                               JOIN cursos c ON af.curso_id = c.id 
                               LEFT JOIN planes p ON af.plan_id = p.id
                               LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
                               WHERE af.id = ?");
        $stmt->execute([$af_id]);
        $af = $stmt->fetch();

        if (!$af) {
            throw new Exception("Acción Formativa con ID $af_id no encontrada.");
        }

        // Obtener grupo
        $stmt = $pdo->prepare("SELECT id, id_plataforma FROM grupos WHERE accion_id = ? LIMIT 1");
        $stmt->execute([$af_id]);
        $grupo = $stmt->fetch();
        $grupo_id_local = $grupo ? $grupo['id'] : null;
        $moodleGroupId = $grupo ? $grupo['id_plataforma'] : null;

        echo "<div class='section'>";
        echo "<div class='section-title'>1. Información de la Acción Formativa en Intranet</div>";
        echo "<p><strong>ID Acción Formativa:</strong> {$af['id']}</p>";
        echo "<p><strong>Título del Curso:</strong> " . htmlspecialchars($af['titulo']) . "</p>";
        echo "<p><strong>Moodle ID del Curso (en DB):</strong> " . ($af['curso_moodle_id'] ?: '<span class="badge badge-danger">VACÍO (No creado en Moodle)</span>') . "</p>";
        echo "<p><strong>ID Grupo Local:</strong> " . ($grupo_id_local ?: '<span class="badge badge-danger">SIN GRUPO LOCAL</span>') . "</p>";
        echo "<p><strong>Moodle ID Grupo (en DB):</strong> " . ($moodleGroupId ?: '<span class="badge badge-warning">VACÍO o SIN GRUPO EN MOODLE</span>') . "</p>";
        
        if ($af['curso_moodle_id'] || $moodleGroupId) {
            echo "<form method='POST' action='' onsubmit=\"return confirm('¿Seguro que deseas limpiar los IDs de Moodle vinculados a este curso/grupo? Esto forzará su recreación en el Moodle de preproducción la próxima vez que sincronices.');\" style='margin-top: 15px;'>";
            echo "<input type='hidden' name='action' value='reset_moodle_ids'>";
            echo "<input type='hidden' name='af_id' value='{$af['id']}'>";
            echo "<button type='submit' class='btn' style='background: #dc2626;'>🗑️ Limpiar IDs de Moodle en Local</button>";
            echo "</form>";
        }
        echo "</div>";

        // 2. Verificar existencia del curso en Moodle
        echo "<div class='section'>";
        echo "<div class='section-title'>2. Verificación del Curso en el Moodle de Preproducción</div>";
        
        $courseId = $af['curso_moodle_id'];
        $moodleCourseExists = false;
        
        if ($courseId) {
            try {
                $moodleCourses = $moodle->getCourses();
                $foundCourse = null;
                foreach ($moodleCourses as $mc) {
                    if ($mc['id'] == $courseId) {
                        $foundCourse = $mc;
                        break;
                    }
                }

                if ($foundCourse) {
                    $moodleCourseExists = true;
                    echo "<p class='badge badge-success'>✓ El curso existe en Moodle.</p>";
                    echo "<p><strong>Nombre en Moodle:</strong> " . htmlspecialchars($foundCourse['fullname']) . "</p>";
                    echo "<p><strong>Nombre corto en Moodle:</strong> " . htmlspecialchars($foundCourse['shortname']) . "</p>";
                } else {
                    echo "<p class='badge badge-danger'>✗ ERROR: El curso con ID $courseId NO EXISTE en este Moodle.</p>";
                    echo "<p style='color: #dc2626;'>Esto significa que el ID registrado ($courseId) es incorrecto (probablemente heredado de producción) o el curso fue eliminado en Moodle.</p>";
                }
            } catch (Exception $ce) {
                echo "<p class='badge badge-danger'>Error al conectar/listar cursos de Moodle: " . htmlspecialchars($ce->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='badge badge-warning'>El curso aún no ha sido creado o vinculado en Moodle.</p>";
            try {
                $moodleCourses = $moodle->getCourses();
                $duplicateCourse = null;
                $targetShortname = $af['abreviatura'] ?: 'CURSO-' . $af['id'];
                foreach ($moodleCourses as $mc) {
                    if (strcasecmp($mc['shortname'], $targetShortname) === 0) {
                        $duplicateCourse = $mc;
                        break;
                    }
                }
                if ($duplicateCourse) {
                    echo "<div style='margin-top: 15px; padding: 15px; background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #d97706; border-radius: 8px; color: #92400e; font-size: 0.95rem;'>";
                    echo "<strong>⚠️ Conflicto detectado:</strong> Ya existe un curso en Moodle con el nombre corto/abreviatura <strong>" . htmlspecialchars($targetShortname) . "</strong> (ID: <strong>" . $duplicateCourse['id'] . "</strong>).<br>";
                    echo "Nombre en Moodle: <em>" . htmlspecialchars($duplicateCourse['fullname']) . "</em><br><br>";
                    echo "<form method='POST' action=''>";
                    echo "<input type='hidden' name='action' value='link_moodle_course'>";
                    echo "<input type='hidden' name='af_id' value='{$af['id']}'>";
                    echo "<input type='hidden' name='moodle_course_id' value='{$duplicateCourse['id']}'>";
                    echo "<button type='submit' class='btn' style='background: #d97706;'>🔗 Vincular a este curso existente en Moodle</button>";
                    echo "</form>";
                    echo "</div>";
                }
            } catch (Exception $ce) {
                // Silently ignore search error
            }
        }
        echo "</div>";

        // 3. Alumnos Matriculados e Inspección
        echo "<div class='section'>";
        echo "<div class='section-title'>3. Estado de los Alumnos Matriculados</div>";

        if (!$grupo_id_local) {
            echo "<p class='badge badge-danger'>No se puede verificar alumnos porque no hay un grupo local asociado.</p>";
        } else {
            $stmt = $pdo->prepare("SELECT a.*, m.id as matricula_id FROM matriculas m JOIN alumnos a ON m.alumno_id = a.id WHERE m.grupo_id = ?");
            $stmt->execute([$grupo_id_local]);
            $alumnos = $stmt->fetchAll();

            if (empty($alumnos)) {
                echo "<p>No hay alumnos matriculados localmente en este grupo.</p>";
            } else {
                echo "<table>";
                echo "<thead><tr><th>Alumno</th><th>DNI / Email</th><th>moodle_user_id (DB)</th><th>Estado en Moodle</th></tr></thead>";
                echo "<tbody>";

                foreach ($alumnos as $alumno) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($alumno['nombre'] . ' ' . ($alumno['primer_apellido'] ?? '')) . "</td>";
                    echo "<td>DNI: {$alumno['dni']}<br>Email: {$alumno['email']}</td>";
                    echo "<td>" . ($alumno['moodle_user_id'] ?: '<span class="badge badge-warning">Ninguno</span>') . "</td>";
                    
                    // Verificar en Moodle
                    echo "<td>";
                    try {
                        // Buscar por email
                        $existingUsers = $moodle->getUsersByField('email', [$alumno['email']]);
                        $moodleUser = null;
                        if (!empty($existingUsers) && isset($existingUsers['users'][0])) {
                            $moodleUser = $existingUsers['users'][0];
                        }

                        if ($moodleUser) {
                            $moodleId = $moodleUser['id'];
                            echo "<span class='badge badge-success'>✓ Creado en Moodle (ID: $moodleId)</span><br>";
                            
                            // Verificar si está matriculado en el curso en Moodle
                            if ($moodleCourseExists) {
                                try {
                                    $enrolledUsers = $moodle->getEnrolledUsers($courseId);
                                    $isEnrolled = false;
                                    foreach ($enrolledUsers as $eu) {
                                        if ($eu['id'] == $moodleId) {
                                            $isEnrolled = true;
                                            break;
                                        }
                                    }

                                    if ($isEnrolled) {
                                        echo "<span class='badge badge-success'>✓ Matriculado en el curso</span>";
                                    } else {
                                        echo "<span class='badge badge-danger'>✗ NO matriculado en este curso en Moodle</span>";
                                    }
                                } catch (Exception $enrolEx) {
                                    echo "<span class='badge badge-warning'>⚠ Creado en Moodle (Falta permiso para verificar matriculación)</span>";
                                }
                            } else {
                                echo "<span class='badge badge-warning'>No se pudo verificar matriculación (Curso no existe en Moodle)</span>";
                            }
                        } else {
                            echo "<span class='badge badge-danger'>✗ NO EXISTE USUARIO en Moodle con este correo</span>";
                        }
                    } catch (Exception $ae) {
                        echo "<span class='badge badge-danger'>Error API Moodle: " . htmlspecialchars($ae->getMessage()) . "</span>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }

                echo "</tbody>";
                echo "</table>";
            }
        }
        echo "</div>";

        echo "<p><a href='debug_sync_issue.php' class='btn'>Volver a la lista</a></p>";

    } catch (Exception $e) {
        echo "<div style='background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; color: #dc2626;'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
        echo "<p><a href='debug_sync_issue.php' class='btn'>Volver a la lista</a></p>";
    }
    ?>
</div>
</body>
</html>
