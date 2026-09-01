<?php
// conexion_inspectores.php - SEGUIMIENTO DE CONEXIÓN DE INSPECTORES SEPE / GESTORES
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_db.php';

// Verificar permisos (Admin, Coordinador, Tutor)
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR, ROLE_LECTURA])) {
    die("No tiene permisos para acceder al módulo de conexión de inspectores.");
}

$moodleDb = new MoodleDB();
$moodleConnected = $moodleDb->isConnected();

// Parámetros de búsqueda y filtrado
$search_q     = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? 'all'); // all, active, low, none, missing

// Obtener grupos y sus inspectores (usuario_gestor)
$sql = "SELECT g.id as grupo_id, g.codigo as grupo_codigo, g.usuario_gestor, g.contrasena_gestor,
               g.fecha_inicio, g.fecha_fin, g.id_plataforma as grupo_moodle_id,
               af.num_accion, af.denominacion as af_nombre, af.id_plataforma as af_moodle_id,
               c.nombre_largo as curso_titulo, c.id as curso_id
        FROM grupos g
        JOIN acciones_formativas af ON g.accion_id = af.id
        JOIN cursos c ON af.curso_id = c.id
        WHERE 1=1";

$params = [];

if (!empty($_SESSION['centro_id'])) {
    $sql .= " AND " . get_user_centro_filter('g.centro_id');
}

if (!empty($search_q)) {
    $sql .= " AND (g.codigo LIKE ? OR af.num_accion LIKE ? OR c.nombre_largo LIKE ? OR g.usuario_gestor LIKE ?)";
    $term = "%{$search_q}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$sql .= " ORDER BY g.fecha_inicio DESC, g.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar datos de conexión por grupo
$inspector_list = [];
$total_grupos = count($grupos);
$count_configurados = 0;
$count_activos = 0;
$count_sin_conexion = 0;
$total_segundos_global = 0;
$ultimo_acceso_global = null;

foreach ($grupos as $g) {
    $moodleCourseId = (int)($g['grupo_moodle_id'] ?: $g['af_moodle_id']);
    $gestorUser = trim($g['usuario_gestor'] ?? '');

    $stats = [
        'found' => false,
        'moodle_user_id' => null,
        'username' => $gestorUser,
        'fullname' => $gestorUser ?: 'Inspector no asignado',
        'email' => '',
        'first_access' => null,
        'last_access' => null,
        'total_seconds' => 0,
        'session_count' => 0,
        'sessions' => [],
        'ips' => [],
        'logs' => []
    ];

    if (!empty($gestorUser)) {
        $count_configurados++;
        if ($moodleConnected && $moodleCourseId > 0) {
            $stats = $moodleDb->fetchInspectorStats($moodleCourseId, $gestorUser);
        }
    }

    $total_segundos_global += $stats['total_seconds'];

    if ($stats['session_count'] > 0) {
        $count_activos++;
        if ($stats['last_access']) {
            $last_ts = strtotime($stats['last_access']);
            if ($ultimo_acceso_global === null || $last_ts > $ultimo_acceso_global) {
                $ultimo_acceso_global = $last_ts;
            }
        }
    } elseif (!empty($gestorUser)) {
        $count_sin_conexion++;
    }

    // Determinar badge de estado
    if (empty($gestorUser)) {
        $status_code = 'missing';
        $status_label = 'Sin Inspector';
        $badge_class = 'badge-missing';
    } elseif ($stats['session_count'] >= 3) {
        $status_code = 'active';
        $status_label = 'Conectado (' . $stats['session_count'] . ' sesiones)';
        $badge_class = 'badge-active';
    } elseif ($stats['session_count'] > 0) {
        $status_code = 'low';
        $status_label = 'Acceso Puntual (' . $stats['session_count'] . ' sesión)';
        $badge_class = 'badge-low';
    } else {
        $status_code = 'none';
        $status_label = 'Sin Accesos en Moodle';
        $badge_class = 'badge-none';
    }

    // Filtro por estado
    if ($status_filter !== 'all' && $status_filter !== $status_code) {
        continue;
    }

    $inspector_list[] = [
        'grupo' => $g,
        'stats' => $stats,
        'status_code' => $status_code,
        'status_label' => $status_label,
        'badge_class' => $badge_class,
        'moodle_course_id' => $moodleCourseId
    ];
}

function format_hours_minutes($seconds) {
    if (!$seconds) return '0 h 0 min';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return "{$h} h {$m} min";
}

$current_page = 'conexion_inspectores.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexión de Inspectores - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --inspector-red: #800000;
            --inspector-red-dark: #5c0000;
            --inspector-red-light: #991b1b;
            --inspector-bg: #fef2f2;
        }

        .page-header-inspector {
            background: linear-gradient(135deg, #700000 0%, #991b1b 100%);
            color: #ffffff;
            padding: 2rem 2.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px -5px rgba(128, 0, 0, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .header-title-group h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 700;
            margin: 0 0 0.4rem 0;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-title-group p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1.35rem 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }

        .kpi-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .kpi-icon svg {
            width: 26px;
            height: 26px;
        }

        .kpi-icon.red { background: #fee2e2; color: #991b1b; }
        .kpi-icon.green { background: #dcfce7; color: #166534; }
        .kpi-icon.yellow { background: #fef9c3; color: #854d0e; }
        .kpi-icon.blue { background: #e0f2fe; color: #075985; }

        .kpi-data {
            display: flex;
            flex-direction: column;
        }

        .kpi-value {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .kpi-label {
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }

        /* Filter card */
        .filter-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input-wrap {
            flex: 1;
            min-width: 260px;
            position: relative;
        }

        .filter-input-wrap input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.5rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .filter-input-wrap input:focus {
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        .filter-input-wrap svg {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #94a3b8;
        }

        .filter-select {
            padding: 0.65rem 1rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 0.9rem;
            background: #ffffff;
            color: #334155;
            outline: none;
        }

        .btn-filter {
            background: #800000;
            color: #ffffff;
            padding: 0.65rem 1.35rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-filter:hover {
            background: #600000;
        }

        /* Table */
        .table-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .custom-table th {
            background: #f8fafc;
            padding: 1rem 1.25rem;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }

        .custom-table td {
            padding: 1.1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            color: #334155;
            vertical-align: middle;
        }

        .custom-table tbody tr:hover {
            background: #fafafa;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-low { background: #fef9c3; color: #a16207; }
        .badge-none { background: #fee2e2; color: #b91c1c; }
        .badge-missing { background: #f1f5f9; color: #64748b; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-action-primary {
            background: #800000;
            color: #ffffff;
        }

        .btn-action-primary:hover {
            background: #600000;
            color: #ffffff;
        }

        .btn-action-pdf {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-action-pdf:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .user-code-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.85rem;
            color: #0f172a;
            display: inline-block;
        }

        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- HEADER -->
        <div class="page-header-inspector">
            <div class="header-title-group">
                <h1>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    Conexión de Inspectores SEPE
                </h1>
                <p>Supervisión, auditoría y control de tiempos de acceso de los gestores e inspectores en el Aula Virtual</p>
            </div>
            <div>
                <a href="home.php" class="btn-action btn-action-pdf">
                    ← Volver al Panel
                </a>
            </div>
        </div>

        <!-- METRIC CARDS -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon red">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                </div>
                <div class="kpi-data">
                    <div class="kpi-value"><?= $total_grupos ?></div>
                    <div class="kpi-label">Cursos / Grupos Totales</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon green">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <div class="kpi-data">
                    <div class="kpi-value"><?= $count_activos ?> / <?= $count_configurados ?></div>
                    <div class="kpi-label">Inspectores con Accesos</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon blue">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                </div>
                <div class="kpi-data">
                    <div class="kpi-value"><?= format_hours_minutes($total_segundos_global) ?></div>
                    <div class="kpi-label">Tiempo Inspección Acumulado</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon yellow">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                </div>
                <div class="kpi-data">
                    <div class="kpi-value"><?= $ultimo_acceso_global ? date('d/m H:i', $ultimo_acceso_global) : 'Sin datos' ?></div>
                    <div class="kpi-label">Último Acceso Registrado</div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-card">
            <form method="GET" action="conexion_inspectores.php" class="filter-form">
                <div class="filter-input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" placeholder="Buscar por código de grupo, curso o usuario inspector...">
                </div>

                <select name="status" class="filter-select">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Todos los estados</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>🟢 Conectados (≥3 sesiones)</option>
                    <option value="low" <?= $status_filter === 'low' ? 'selected' : '' ?>>🟡 Acceso Puntual (1-2 sesiones)</option>
                    <option value="none" <?= $status_filter === 'none' ? 'selected' : '' ?>>🔴 Sin Accesos Registrados</option>
                    <option value="missing" <?= $status_filter === 'missing' ? 'selected' : '' ?>>⚪ Sin Inspector Configurado</option>
                </select>

                <button type="submit" class="btn-filter">Filtrar Resultados</button>
            </form>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <?php if (empty($inspector_list)): ?>
                <div class="empty-state">
                    <h3>No se encontraron grupos o inspectores</h3>
                    <p>Intenta ajustar los criterios de búsqueda o filtro.</p>
                </div>
            <?php else: ?>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Grupo / Acción</th>
                            <th>Curso / Denominación</th>
                            <th>Usuario Inspector (Gestor)</th>
                            <th>Estado de Inspección</th>
                            <th>Sesiones</th>
                            <th>Primer / Último Acceso</th>
                            <th>Tiempo Conexión</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inspector_list as $item): 
                            $g = $item['grupo'];
                            $st = $item['stats'];
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: #0f172a;"><?= htmlspecialchars($g['grupo_codigo']) ?></strong>
                                    <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">AF: <?= htmlspecialchars($g['num_accion']) ?></div>
                                </td>
                                <td style="max-width: 260px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($g['curso_titulo']) ?></div>
                                    <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                        Fechas: <?= date('d/m/Y', strtotime($g['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($g['fecha_fin'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($g['usuario_gestor'])): ?>
                                        <div class="user-code-box">🕵️‍♂️ <?= htmlspecialchars($g['usuario_gestor']) ?></div>
                                        <?php if (!empty($g['contrasena_gestor'])): ?>
                                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 3px;">Pass: <?= htmlspecialchars($g['contrasena_gestor']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">No configurado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $item['badge_class'] ?>">
                                        <?= $item['status_label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="font-size: 1rem; color: #0f172a;"><?= $st['session_count'] ?></strong>
                                </td>
                                <td style="font-size: 0.82rem;">
                                    <?php if ($st['first_access']): ?>
                                        <div><span style="color: #64748b;">Inicio:</span> <?= date('d/m/Y H:i', strtotime($st['first_access'])) ?></div>
                                        <div><span style="color: #64748b;">Último:</span> <?= date('d/m/Y H:i', strtotime($st['last_access'])) ?></div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">Sin accesos</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #800000; font-size: 0.95rem;">
                                        <?= format_hours_minutes($st['total_seconds']) ?>
                                    </strong>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <a href="logs_inspector.php?grupo_id=<?= $g['grupo_id'] ?>" class="btn-action btn-action-primary">
                                            🔍 Logs Localizados
                                        </a>
                                        <a href="pdf_informe_conexion_inspector.php?grupo_id=<?= $g['grupo_id'] ?>" target="_blank" class="btn-action btn-action-pdf" title="Descargar Informe PDF">
                                            📄 PDF
                                        </a>
                                    </div>
                                </td>
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
