<?php
// informe_evaluaciones_grupo.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// 1. Obtener datos del grupo y su curso
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, c.nombre_largo as curso_titulo
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    die("Grupo no encontrado.");
}

// 2. Obtener alumnos con sus calificaciones y estados de Moodle
$stmtAl = $pdo->prepare("SELECT m.id as matricula_id, m.estado as matricula_estado, 
                                m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade, 
                                m.moodle_e1_completed, m.moodle_e2_completed, m.moodle_e3_completed,
                                m.moodle_final_grade, m.moodle_aptitud,
                                a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.moodle_user_id, a.provincia
                         FROM matriculas m
                         JOIN alumnos a ON m.alumno_id = a.id
                         WHERE m.grupo_id = ?
                         ORDER BY a.primer_apellido ASC, a.nombre ASC");
$stmtAl->execute([$grupo_id]);
$alumnos = $stmtAl->fetchAll();

$current_page = 'grupos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluaciones del Grupo - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .premium-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .premium-table th {
            background: rgba(0, 108, 228, 0.05);
            border-bottom: 2px solid var(--border-color);
            padding: 12px 15px;
            text-align: left;
            font-weight: 700;
            color: var(--primary-color);
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .premium-table td {
            padding: 14px 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .premium-table tr:hover td {
            background-color: rgba(0, 108, 228, 0.015);
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.2);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
            filter: brightness(1.1);
        }

        .btn-back {
            background: rgba(0, 108, 228, 0.08);
            color: var(--primary-color);
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 108, 228, 0.15);
            font-size: 0.85rem;
        }

        .btn-back:hover {
            background: rgba(0, 108, 228, 0.12);
            transform: translateY(-2px);
        }

        /* Badges */
        .badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 50px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            text-align: center;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
        }

        .badge-warning {
            background: rgba(245, 158, 11L, 0.12);
            color: #f59e0b;
        }

        .badge-secondary {
            background: rgba(100, 116, 139, 0.12);
            color: #64748b;
        }
        
        .grade-number {
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/fp_sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto; padding: 2rem;">
            <!-- Breadcrumbs -->
            <div class="breadcrumb-premium" style="margin-bottom: 2rem; background: var(--glass-bg); padding: 0.75rem 1.5rem; border-radius: 10px; border: 1px solid var(--glass-border); font-size: 0.85rem; display: flex; gap: 8px; align-items: center;">
                <a href="home.php" style="color: var(--primary-color); text-decoration: none;">Inicio</a>
                <span style="color: var(--text-muted);">/</span>
                <a href="grupos.php" style="color: var(--primary-color); text-decoration: none;">Grupos</a>
                <span style="color: var(--text-muted);">/</span>
                <a href="ficha_grupo_edicion.php?id=<?= $grupo_id ?>" style="color: var(--primary-color); text-decoration: none;">Grupo</a>
                <span style="color: var(--text-muted);">/</span>
                <span style="color: var(--text-color); font-weight: 600;">Evaluaciones del grupo</span>
            </div>

            <!-- Page Title -->
            <div style="margin-bottom: 2rem;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Grupos</h1>
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 700; color: var(--text-color); margin: 0 0 0.5rem 0;"><?= htmlspecialchars($grupo['num_accion'] . ' - ' . $grupo['curso_titulo'] . ' - G' . $grupo['numero_grupo']) ?></h2>
                <p style="font-size: 1rem; color: var(--text-muted); font-weight: 500; margin: 0;">Informe de Calificaciones y Evaluaciones del Grupo</p>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <a href="ficha_grupo_edicion.php?id=<?= $grupo_id ?>" class="btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Volver al Grupo
                </a>
                <a href="pdf_informe_evaluaciones.php?grupo_id=<?= $grupo_id ?>" class="btn-pdf" target="_blank">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Imprimir PDF Evaluaciones
                </a>
            </div>

            <!-- Table Card -->
            <div class="premium-card" style="overflow-x: auto;">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th style="text-align: center;">Provincia</th>
                            <th style="text-align: center;">Evaluación Inicial</th>
                            <th style="text-align: center;">Evaluación Intermedia</th>
                            <th style="text-align: center;">Evaluación Final</th>
                            <th style="text-align: center;">¿Completó Todas?</th>
                            <th style="text-align: center;">Nota Media</th>
                            <th style="text-align: center;">Aptitud / Resultado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alumnos)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 2rem 0;">No hay alumnos matriculados en este grupo.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alumnos as $alumno): ?>
                                <?php 
                                $apellidos = trim(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''));
                                $nombre_completo = mb_strtoupper($apellidos . ', ' . $alumno['nombre']);
                                
                                // Calcular completado de todas
                                $completed_all = ($alumno['moodle_e1_completed'] == 1 && $alumno['moodle_e2_completed'] == 1 && $alumno['moodle_e3_completed'] == 1);
                                
                                // Calcular nota media
                                $grades = [];
                                if ($alumno['moodle_e1_grade'] !== null) $grades[] = (float)$alumno['moodle_e1_grade'];
                                if ($alumno['moodle_e2_grade'] !== null) $grades[] = (float)$alumno['moodle_e2_grade'];
                                if ($alumno['moodle_e3_grade'] !== null) $grades[] = (float)$alumno['moodle_e3_grade'];
                                
                                $media = count($grades) > 0 ? round(array_sum($grades) / count($grades), 2) : null;
                                
                                // Colores de aptitud
                                $aptitud = mb_strtoupper(trim($alumno['moodle_aptitud'] ?: 'PENDIENTE'));
                                if ($aptitud === 'APTO') {
                                    $apt_class = 'badge-success';
                                } elseif ($aptitud === 'NO APTO') {
                                    $apt_class = 'badge-danger';
                                } else {
                                    $apt_class = 'badge-warning';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-color); font-size: 0.95rem; line-height: 1.2;">
                                            <?= htmlspecialchars($nombre_completo) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span style="font-size: 0.85rem; color: var(--text-color); font-weight: 500;">
                                            <?= htmlspecialchars($alumno['provincia'] ?? '-') ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Ev Inicial -->
                                    <td style="text-align: center;">
                                        <?php if ($alumno['moodle_e1_grade'] !== null): ?>
                                            <span class="grade-number"><?= number_format($alumno['moodle_e1_grade'], 2) ?></span>
                                            <br><small class="badge badge-success" style="font-size: 0.65rem; padding: 2px 6px;">Hecho</small>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">—</span>
                                            <br><small class="badge badge-secondary" style="font-size: 0.65rem; padding: 2px 6px;">Pendiente</small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Ev Intermedia -->
                                    <td style="text-align: center;">
                                        <?php if ($alumno['moodle_e2_grade'] !== null): ?>
                                            <span class="grade-number"><?= number_format($alumno['moodle_e2_grade'], 2) ?></span>
                                            <br><small class="badge badge-success" style="font-size: 0.65rem; padding: 2px 6px;">Hecho</small>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">—</span>
                                            <br><small class="badge badge-secondary" style="font-size: 0.65rem; padding: 2px 6px;">Pendiente</small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Ev Final -->
                                    <td style="text-align: center;">
                                        <?php if ($alumno['moodle_e3_grade'] !== null): ?>
                                            <span class="grade-number"><?= number_format($alumno['moodle_e3_grade'], 2) ?></span>
                                            <br><small class="badge badge-success" style="font-size: 0.65rem; padding: 2px 6px;">Hecho</small>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">—</span>
                                            <br><small class="badge badge-secondary" style="font-size: 0.65rem; padding: 2px 6px;">Pendiente</small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Completó todas -->
                                    <td style="text-align: center;">
                                        <?php if ($completed_all): ?>
                                            <span class="badge badge-success">SÍ</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">NO</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Nota Media -->
                                    <td style="text-align: center; font-weight: 700; color: var(--primary-color);">
                                        <?= $media !== null ? number_format($media, 2) : '—' ?>
                                    </td>
                                    
                                    <!-- Aptitud -->
                                    <td style="text-align: center;">
                                        <span class="badge <?= $apt_class ?>"><?= htmlspecialchars($aptitud) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
