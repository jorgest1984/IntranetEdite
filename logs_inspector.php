<?php
// logs_inspector.php - LOGS LOCALIZADOS Y AUDITORÍA DE INSPECTORES SEPE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_db.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR, ROLE_LECTURA])) {
    die("No tiene permisos para acceder a los logs de inspección.");
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// 1. Obtener datos del grupo y su curso
$stmt = $pdo->prepare("SELECT g.*, COALESCE(g.numero_grupo, CONCAT('G-', g.id)) as codigo, af.num_accion, af.titulo as af_nombre, af.id_plataforma as af_moodle_id,
                              c.nombre_largo as curso_titulo, c.id as curso_id,
                              cen.nombre as centro_nombre
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       LEFT JOIN centros cen ON g.centro_id = cen.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo) {
    die("Grupo no encontrado.");
}

$moodleCourseId = (int)($grupo['id_plataforma'] ?: $grupo['af_moodle_id']);
$gestorUser = trim($grupo['usuario_gestor'] ?? '');

$moodleDb = new MoodleDB();
$stats = $moodleDb->fetchInspectorStats($moodleCourseId, $gestorUser);

function format_duration_full($seconds) {
    if (!$seconds) return '0 seg';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    $res = [];
    if ($h > 0) $res[] = "{$h}h";
    if ($m > 0) $res[] = "{$m}m";
    if ($s > 0 || empty($res)) $res[] = "{$s}s";
    return implode(' ', $res);
}

function get_day_name_es($dateStr) {
    if (empty($dateStr)) return 'N/A';
    $ts = strtotime((string)$dateStr);
    if (!$ts) return 'N/A';
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    return $days[date('w', $ts)];
}

$current_page = 'conexion_inspectores.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs Localizados Inspector - <?= htmlspecialchars($grupo['codigo']) ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .inspector-header {
            background: linear-gradient(135deg, #700000 0%, #991b1b 100%);
            color: #ffffff;
            padding: 2.25rem 2.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px -5px rgba(128, 0, 0, 0.3);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .header-title h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: #ffffff;
        }

        .header-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .credentials-pill {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            backdrop-filter: blur(8px);
            display: inline-flex;
            gap: 1.5rem;
            align-items: center;
        }

        .cred-item {
            display: flex;
            flex-direction: column;
        }

        .cred-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
        }

        .cred-val {
            font-family: monospace;
            font-size: 1rem;
            font-weight: 700;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card-box {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }

        .card-box h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 1.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-logs {
            width: 100%;
            border-collapse: collapse;
        }

        .table-logs th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .table-logs td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.88rem;
            color: #334155;
        }

        .ip-tag {
            background: #e2e8f0;
            color: #334155;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.8rem;
            display: inline-block;
            margin: 2px;
        }

        .event-tag {
            background: #f1f5f9;
            color: #0f172a;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-family: monospace;
        }

        .btn-top {
            background: #ffffff;
            color: #800000;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.88rem;
            transition: background 0.2s;
        }

        .btn-top:hover {
            background: #fef2f2;
        }

        .btn-pdf {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.4);
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.88rem;
        }

        .btn-pdf:hover {
            background: rgba(255,255,255,0.3);
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- HEADER -->
        <div class="inspector-header">
            <div class="header-top">
                <div class="header-title">
                    <h1>🕵️‍♂️ Auditoría de Inspección: Grupo <?= htmlspecialchars($grupo['codigo']) ?></h1>
                    <div class="header-subtitle"><?= htmlspecialchars($grupo['curso_titulo']) ?> (Acción <?= htmlspecialchars($grupo['num_accion']) ?>)</div>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="conexion_inspectores.php" class="btn-top">← Volver a Inspectores</a>
                    <a href="pdf_informe_conexion_inspector.php?grupo_id=<?= $grupo['id'] ?>" target="_blank" class="btn-pdf">📄 Generar PDF Oficial</a>
                </div>
            </div>

            <div class="credentials-pill">
                <div class="cred-item">
                    <span class="cred-label">Usuario Inspector (SEPE)</span>
                    <span class="cred-val"><?= htmlspecialchars($gestorUser ?: 'No asignado') ?></span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Contraseña Gestor</span>
                    <span class="cred-val"><?= htmlspecialchars($grupo['contrasena_gestor'] ?: 'N/A') ?></span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Estado en Moodle</span>
                    <span class="cred-val" style="color: <?= $stats['found'] ? '#4ade80' : '#f87171' ?>;">
                        <?= $stats['found'] ? '✓ Registrado' : '✗ No encontrado' ?>
                    </span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Tiempo Total Conexión</span>
                    <span class="cred-val" style="color: #fbbf24;"><?= format_duration_full($stats['total_seconds']) ?></span>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- SESIONES DE INSPECCIÓN -->
            <div class="card-box">
                <h3>⏱️ Sesiones de Inspección Registradas</h3>
                <?php if (empty($stats['sessions'])): ?>
                    <p style="color: #64748b; font-style: italic;">No hay sesiones de inspección registradas en Moodle para este curso.</p>
                <?php else: ?>
                    <table class="table-logs">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Día</th>
                                <th>Hora Inicio</th>
                                <th>Hora Fin</th>
                                <th>Duración</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['sessions'] as $s): ?>
                                <tr>
                                    <td><strong><?= $s['date'] ?></strong></td>
                                    <td><?= get_day_name_es($s['date']) ?></td>
                                    <td><?= $s['start_time'] ?></td>
                                    <td><?= $s['end_time'] ?></td>
                                    <td><strong style="color: #800000;"><?= format_duration_full($s['duration']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- IPs Y RESUMEN -->
            <div class="card-box">
                <h3>🌐 Direcciones IP de Acceso</h3>
                <?php if (empty($stats['ips'])): ?>
                    <p style="color: #64748b; font-style: italic;">Sin direcciones IP registradas.</p>
                <?php else: ?>
                    <div style="margin-bottom: 1.5rem;">
                        <?php foreach ($stats['ips'] as $ip): ?>
                            <span class="ip-tag">📍 <?= htmlspecialchars($ip) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3 style="margin-top: 1.5rem;">📊 Métricas de Auditoría</h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <span style="color: #64748b;">Nº Total de Sesiones:</span>
                        <strong><?= $stats['session_count'] ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <span style="color: #64748b;">Primer Acceso:</span>
                        <strong><?= !empty($stats['first_access']) ? date('d/m/Y H:i:s', strtotime((string)$stats['first_access'])) : 'N/A' ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <span style="color: #64748b;">Último Acceso:</span>
                        <strong><?= !empty($stats['last_access']) ? date('d/m/Y H:i:s', strtotime((string)$stats['last_access'])) : 'N/A' ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Total Registros en Log:</span>
                        <strong><?= count($stats['logs']) ?> eventos</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE LOGS DETALLADOS LINEA A LINEA -->
        <div class="card-box">
            <h3>📜 Logs Localizados en Moodle (Auditoría Detallada)</h3>
            <?php if (empty($stats['logs'])): ?>
                <p style="color: #64748b; font-style: italic;">No hay eventos registrados en el log de Moodle.</p>
            <?php else: ?>
                <table class="table-logs">
                    <thead>
                        <tr>
                            <th>ID Evento</th>
                            <th>Fecha y Hora</th>
                            <th>IP</th>
                            <th>Acción / Evento</th>
                            <th>Objetivo (Target)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['logs'] as $log): ?>
                            <tr>
                                <td style="font-family: monospace; color: #64748b;"><?= $log['id'] ?></td>
                                <td><strong><?= $log['datetime'] ?></strong></td>
                                <td><span class="ip-tag"><?= htmlspecialchars($log['ip']) ?></span></td>
                                <td><span class="event-tag"><?= htmlspecialchars($log['action'] . ' (' . $log['eventname'] . ')') ?></span></td>
                                <td><?= htmlspecialchars($log['target']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
