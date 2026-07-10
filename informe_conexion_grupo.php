<?php
// informe_conexion_grupo.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_db.php';

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// 1. Obtener datos del grupo y su curso
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, c.nombre_largo as curso_titulo, af.id_plataforma as course_moodle_id
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    die("Grupo no encontrado.");
}

// 2. Obtener alumnos del grupo
$stmtAl = $pdo->prepare("SELECT m.id as matricula_id, a.id as alumno_id, a.nombre, a.apellidos, a.moodle_user_id
                         FROM matriculas m
                         JOIN alumnos a ON m.alumno_id = a.id
                         WHERE m.grupo_id = ?
                         ORDER BY a.apellidos ASC, a.nombre ASC");
$stmtAl->execute([$grupo_id]);
$alumnos = $stmtAl->fetchAll();

// 3. Obtener logs desde Moodle
$user_logs = [];
$moodleUserIds = [];
foreach ($alumnos as $al) {
    if (!empty($al['moodle_user_id'])) {
        $moodleUserIds[] = (int)$al['moodle_user_id'];
    }
}

$courseMoodleId = (int)$grupo['course_moodle_id'];
$moodleDb = new MoodleDB();
$moodleConnected = $moodleDb->isConnected();

if ($moodleConnected && !empty($moodleUserIds)) {
    try {
        $mpdo = $moodleDb->getPDO();
        $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
        
        $placeholders = implode(',', array_fill(0, count($moodleUserIds), '?'));
        $sqlLogs = "SELECT userid, timecreated 
                    FROM {$prefix}logstore_standard_log 
                    WHERE courseid = ? AND userid IN ($placeholders) 
                    ORDER BY userid ASC, timecreated ASC";
        $stmtLogs = $mpdo->prepare($sqlLogs);
        $params = array_merge([$courseMoodleId], $moodleUserIds);
        $stmtLogs->execute($params);
        
        while ($row = $stmtLogs->fetch(PDO::FETCH_ASSOC)) {
            $user_logs[(int)$row['userid']][] = (int)$row['timecreated'];
        }
    } catch (Exception $e) {
        // Fallback silencioso
    }
}

// Funciones helpers
function get_day_of_week_es($timestamp) {
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    return $days[date('w', $timestamp)];
}

function format_duration_hm($seconds) {
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds % 3600) / 60);
    return "{$hours} h {$mins} min";
}

// 4. Calcular sesiones por alumno
$student_sessions = [];
foreach ($alumnos as $al) {
    $muid = (int)$al['moodle_user_id'];
    $times = isset($user_logs[$muid]) ? $user_logs[$muid] : [];
    $sessions = [];
    $total_seconds = 0;
    
    if (!empty($times)) {
        sort($times);
        $current_start = $times[0];
        $current_last = $times[0];
        $n = count($times);
        
        for ($i = 1; $i < $n; $i++) {
            $diff = $times[$i] - $current_last;
            if ($diff < 1800) { // 30 minutos
                $current_last = $times[$i];
            } else {
                $approx = $current_last - $current_start + 120; // 2 min cortesía
                $sessions[] = [
                    'date' => date('d/m/Y', $current_start),
                    'day' => get_day_of_week_es($current_start),
                    'start_time' => date('H:i', $current_start),
                    'approx' => $approx,
                    'adjusted' => $approx
                ];
                $total_seconds += $approx;
                
                $current_start = $times[$i];
                $current_last = $times[$i];
            }
        }
        $approx = $current_last - $current_start + 120;
        $sessions[] = [
            'date' => date('d/m/Y', $current_start),
            'day' => get_day_of_week_es($current_start),
            'start_time' => date('H:i', $current_start),
            'approx' => $approx,
            'adjusted' => $approx
        ];
        $total_seconds += $approx;
    }
    
    $student_sessions[$al['alumno_id']] = [
        'sessions' => $sessions,
        'total_seconds' => $total_seconds
    ];
}

$current_page = 'grupos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Conexión - <?= APP_NAME ?></title>
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

        .student-header {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(0, 108, 228, 0.15);
            padding-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .connection-summary {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 1.25rem;
        }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .premium-table th {
            background: rgba(0, 108, 228, 0.05);
            border-bottom: 2px solid var(--border-color);
            padding: 10px 15px;
            text-align: left;
            font-weight: 700;
            color: var(--primary-color);
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .premium-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .premium-table tr:hover td {
            background-color: rgba(0, 108, 228, 0.015);
        }

        .total-row td {
            font-weight: 700;
            background-color: rgba(0, 0, 0, 0.02);
            color: var(--text-color);
            border-bottom: 2px solid var(--border-color);
            border-top: 1px solid var(--border-color);
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

        .badge-status {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 50px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-online {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }

        .status-offline {
            background: rgba(100, 116, 139, 0.1);
            color: #64748b;
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
                <span style="color: var(--text-color); font-weight: 600;">Informe de conexión del grupo</span>
            </div>

            <!-- Page Title -->
            <div style="margin-bottom: 2rem;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Grupos</h1>
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 700; color: var(--text-color); margin: 0 0 0.5rem 0;"><?= htmlspecialchars($grupo['num_accion'] . ' - ' . $grupo['curso_titulo'] . ' - G' . $grupo['numero_grupo']) ?></h2>
                <p style="font-size: 1rem; color: var(--text-muted); font-weight: 500; margin: 0;">Informe de conexión en el Aula Virtual</p>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <a href="ficha_grupo_edicion.php?id=<?= $grupo_id ?>" class="btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Volver al Grupo
                </a>
                <a href="pdf_informe_conexion.php?grupo_id=<?= $grupo_id ?>" class="btn-pdf" target="_blank">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Imprimir PDF Conexiones
                </a>
            </div>

            <!-- Status Indicator if Moodle is not connected -->
            <?php if (!$moodleConnected): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 1rem 1.5rem; color: #ef4444; font-weight: 600; margin-bottom: 2rem; display: flex; align-items: center; gap: 10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>No se pudo conectar a la base de datos de Moodle. Se muestran datos locales / simulados.</span>
                </div>
            <?php endif; ?>

            <!-- Alumnos detailed connection cards -->
            <?php foreach ($alumnos as $alumno): ?>
                <?php 
                $nombre_completo = mb_strtoupper($alumno['apellidos'] . ', ' . $alumno['nombre']);
                $stats = $student_sessions[$alumno['alumno_id']];
                $sessions = $stats['sessions'];
                $total_seconds = $stats['total_seconds'];
                ?>
                <div class="premium-card">
                    <div class="student-header">
                        <span><?= htmlspecialchars($nombre_completo) ?></span>
                        <span class="badge-status <?= $alumno['moodle_user_id'] ? 'status-online' : 'status-offline' ?>">
                            <?= $alumno['moodle_user_id'] ? 'Moodle ID: ' . $alumno['moodle_user_id'] : 'Sin Moodle ID' ?>
                        </span>
                    </div>
                    <div class="connection-summary">
                        Tiempo de conexión: <?= format_duration_hm($total_seconds) ?>
                    </div>

                    <div style="overflow-x: auto; width: 100%;">
                        <table class="premium-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Día de la semana</th>
                                    <th>Hora de inicio de actividad</th>
                                    <th>Duración aproximada</th>
                                    <th>Duración ajustada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sessions)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 1.5rem 0;">No se registran accesos al Aula Virtual para este alumno.</td>
                                    </tr>
                                    <tr class="total-row">
                                        <td>Total</td>
                                        <td></td>
                                        <td></td>
                                        <td>0 h 0 min</td>
                                        <td>0 h 0 min</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($session['date']) ?></td>
                                            <td><?= htmlspecialchars($session['day']) ?></td>
                                            <td><?= htmlspecialchars($session['start_time']) ?></td>
                                            <td><?= format_duration_hm($session['approx']) ?></td>
                                            <td><?= format_duration_hm($session['adjusted']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td>Total</td>
                                        <td></td>
                                        <td></td>
                                        <td><?= format_duration_hm($total_seconds) ?></td>
                                        <td><?= format_duration_hm($total_seconds) ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </main>
    </div>
</body>
</html>
