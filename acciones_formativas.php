<?php
// acciones_formativas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COMERCIAL, ROLE_ADMINISTRATIVO])) {
    header("Location: home.php");
    exit();
}

$is_subvencionada = (isset($_GET['context']) && $_GET['context'] === 'subvencionada');
$is_comercial = (isset($_GET['context']) && $_GET['context'] === 'comercial');

if ($is_subvencionada) {
    $page_title_prefix = 'FORMACIÓN SUBVENCIONADA';
    $back_url = 'formacion_subvencionada.php';
} elseif ($is_comercial) {
    $page_title_prefix = 'ACCIONES FORMATIVAS';
    $back_url = 'comerciales_acciones.php';
} else {
    $page_title_prefix = 'ACCIONES FORMATIVAS';
    $back_url = 'formacion_bonificada.php';
}

// Fetch lists for selects
$convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC")->fetchAll();
$planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC")->fetchAll();

// Dynamic lists from acciones_formativas table
// Helper function for safe filter queries
function getSafeDistinctValues($pdo, $column, $table = 'acciones_formativas') {
    try {
        return $pdo->query("SELECT DISTINCT $column FROM $table WHERE $column IS NOT NULL AND $column != '' ORDER BY $column ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

$solicitantes = getSafeDistinctValues($pdo, 'solicitante');
$sectores = getSafeDistinctValues($pdo, 'sector');
$proveedores = getSafeDistinctValues($pdo, 'proveedor');
$catalogos = getSafeDistinctValues($pdo, 'catalogo');
$consultoras = getSafeDistinctValues($pdo, 'consultora');


$modalidades = ['TELEFORMACIÓN', 'PRESENCIAL', 'MIXTA', 'AULA VIRTUAL'];
$prioridades = ['Alta', 'Media', 'Baja'];

// Search Logic
$results = [];
$searched = false;
$current_plan_name = '';

if (!empty($_GET['plan_id'])) {
    $stmt = $pdo->prepare("SELECT nombre FROM planes WHERE id = ?");
    $stmt->execute([$_GET['plan_id']]);
    $current_plan_name = $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $searched = true;
    $params = [];
    $sql = "SELECT af.*, c.nombre_largo as titulo, p.nombre as nombre_plan,
            (SELECT COUNT(*) FROM matriculas m 
             JOIN grupos g ON m.grupo_id = g.id 
             WHERE g.accion_id = af.id) as participantes
            FROM acciones_formativas af
            JOIN cursos c ON af.curso_id = c.id
            LEFT JOIN planes p ON af.plan_id = p.id
            WHERE 1=1";

    if (!empty($_GET['nombre'])) {
        $sql .= " AND c.nombre_largo LIKE ?";
        $params[] = "%" . $_GET['nombre'] . "%";
    }
    if (!empty($_GET['convocatoria_id'])) {
        $sql .= " AND p.convocatoria_id = ?";
        $params[] = $_GET['convocatoria_id'];
    }
    if (!empty($_GET['plan_id'])) {
        $sql .= " AND af.plan_id = ?";
        $params[] = $_GET['plan_id'];
    }
    if (!empty($_GET['solicitante'])) {
        $sql .= " AND af.solicitante = ?";
        $params[] = $_GET['solicitante'];
    }
    if (!empty($_GET['sector'])) {
        $sql .= " AND af.sector = ?";
        $params[] = $_GET['sector'];
    }
    if (!empty($_GET['proveedor'])) {
        $sql .= " AND af.proveedor = ?";
        $params[] = $_GET['proveedor'];
    }
    if (!empty($_GET['catalogo'])) {
        $sql .= " AND af.catalogo = ?";
        $params[] = $_GET['catalogo'];
    }
    if (!empty($_GET['consultora'])) {
        $sql .= " AND af.consultora = ?";
        $params[] = $_GET['consultora'];
    }
    if (!empty($_GET['id_accion'])) {
        $sql .= " AND af.id = ?";
        $params[] = $_GET['id_accion'];
    }
    if (!empty($_GET['prioridad'])) {
        $sql .= " AND af.prioridad = ?";
        $params[] = $_GET['prioridad'];
    }
    if (!empty($_GET['modalidad'])) {
        $sql .= " AND af.modalidad = ?";
        $params[] = $_GET['modalidad'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $current_plan_name ? 'Cursos del Plan: ' . htmlspecialchars($current_plan_name) : ($is_subvencionada ? 'Formación Subvencionada' : 'Acciones Formativas') ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --primary-color: #1e3a8a;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; margin: 0; }
        .main-content { padding: 1.5rem; }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header-custom {
            background: #fff;
            padding: 0.5rem;
            border-bottom: 2px solid var(--border-gray);
            text-align: center;
        }

        .card-header-custom h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
        }

        .search-form { padding: 1rem; }

        .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
            align-items: center;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--label-blue);
            white-space: nowrap;
        }

        .form-control {
            font-size: 0.8rem;
            padding: 3px 6px;
            border: 1px solid var(--border-gray);
            border-radius: 2px;
            background: #fff;
        }

        select.form-control { height: 26px; padding: 0 6px; }
        input[type="text"].form-control { height: 24px; }

        .button-bar {
            text-align: center;
            margin-top: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .btn-buscar {
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
            padding: 4px 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 3px;
        }

        .btn-buscar:hover { background: #e2e8f0; }

        .btn-print {
            background: #fff;
            border: 1px solid var(--border-gray);
            padding: 3px 12px;
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 3px;
        }

        .btn-print:hover { background: #f8fafc; }

        /* Results Table */
        .results-section {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            overflow: hidden;
        }

        .results-header {
            padding: 0.6rem;
            text-align: center;
            border-bottom: 1px solid var(--border-gray);
            position: relative;
        }

        .results-header .check-group {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--label-blue);
            font-weight: 700;
        }

        .results-header h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
        }

        .table-responsive { 
            overflow-x: auto; 
            width: 100%;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border-gray);
        }
        
        .table-custom {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-custom th {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            padding: 8px;
            text-align: center;
            color: var(--label-blue);
            font-weight: 700;
            white-space: nowrap;
        }

        .table-custom th svg {
            width: 10px;
            height: 10px;
            vertical-align: middle;
            margin-right: 5px;
            color: #64748b;
        }

        .table-custom td {
            border: 1px solid #f1f5f9;
            padding: 8px;
            white-space: nowrap;
        }

        .table-custom tr:nth-child(even) { background: #f8fafc; }
        .table-custom tr:hover { background: #f1f5f9; }

        .btn-volver {
            margin-top: 15px;
            padding: 5px 20px;
            font-size: 0.75rem;
            cursor: pointer;
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
            border-radius: 3px;
        }
        
        .btn-volver:hover { background: #e2e8f0; }

        /* Estilos Premium para Acciones */
        .table-premium th { padding: 15px; border-bottom: 2px solid #f1f5f9; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.7rem; }
        .table-premium tr:hover { background: #f8fafc; }
        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: white;
            border: 1px solid #e2e8f0;
            color: #1e3a8a;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: currentColor;
        }

        /* Loading Spinner */
        .syncing { animation: spin 1s linear infinite; color: #ea580c !important; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* Tabs Styles */
        .tabs-header-af {
            display: flex;
            gap: 5px;
            margin-bottom: -1px;
            padding-left: 10px;
        }

        .tab-af-btn {
            padding: 10px 25px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
        }

        .tab-af-btn.active {
            background: white;
            color: #1e3a8a;
            border-bottom: 2px solid white;
            margin-bottom: -1px;
            z-index: 2;
        }

        .tab-content-af {
            display: none;
        }

        .tab-content-af.active {
            display: block;
        }

        /* Form Styles for Tab */
        .form-card-tab {
            background: white;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .section-title-tab {
            color: #1e3a8a;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .grid-form-tab {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .form-group-tab label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .form-control-tab {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .moodle-sync-box-tab {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
    <script>
    function syncMoodle(afId) {
        if (!confirm('¿Deseas sincronizar esta Acción Formativa y sus alumnos con el Aula Virtual?')) return;
        
        const btn = event.currentTarget;
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="syncing"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>';
        btn.disabled = true;

        fetch('api_sync_moodle.php?id=' + afId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Sincronización completada: ' + data.message);
                } else {
                    alert('✕ Error: ' + data.error);
                }
            })
            .catch(err => alert('✕ Error de conexión con el servidor.'))
            .finally(() => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
    }

    function switchTab(tabId) {
        document.querySelectorAll('.tab-af-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content-af').forEach(content => content.classList.remove('active'));
        
        document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
        document.getElementById(tabId).classList.add('active');
    }
    </script>
</head>
<body>

<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto;">
        
        <div class="search-card">
            <div class="card-header-custom" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 20px;">
                <h2 style="margin: 0; color: #b91c1c; font-size: 0.85rem; font-weight: 800; text-transform: uppercase;"><?= $current_plan_name ? 'CURSOS DEL PLAN: ' . htmlspecialchars($current_plan_name) : ($page_title_prefix . ' - CAMPOS DE BÚSQUEDA') ?></h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <?php if ($current_plan_name): ?>
                        <button onclick="document.getElementById('searchForm').style.display = (document.getElementById('searchForm').style.display === 'none' ? 'block' : 'none')" class="btn-small" style="font-size: 0.7rem; padding: 4px 10px; cursor:pointer; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px;">🔍 Filtros</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <form id="searchForm" class="search-form" method="GET" style="<?= $current_plan_name ? 'display: none;' : '' ?>">
                <input type="hidden" name="context" value="<?= htmlspecialchars($_GET['context'] ?? '') ?>">
                
                <!-- Fila 1 -->
                <div class="search-row" style="justify-content: center;">
                    <div class="form-group">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control" style="width: 400px;">
                    </div>
                </div>

                <!-- Fila 2 -->
                <div class="search-row">
                    <div class="form-group" style="flex: 1;">
                        <label>Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control" style="width: 100%; max-width: 300px;">
                            <option value="">Todas</option>
                            <?php foreach ($convocatorias as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (($_GET['convocatoria_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>Plan:</label>
                        <select name="plan_id" class="form-control" style="width: 100%;">
                            <option value="">Todos los planes</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (($_GET['plan_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Fila 3 -->
                <div class="search-row">
                    <div class="form-group" style="flex: 1;">
                        <label>Solicitante:</label>
                        <select name="solicitante" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($solicitantes as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= (($_GET['solicitante'] ?? '') == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Sector:</label>
                        <select name="sector" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= (($_GET['sector'] ?? '') == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Fila 4 -->
                <div class="search-row">
                    <div class="form-group" style="flex: 1;">
                        <label>Proveedor:</label>
                        <select name="proveedor" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>" <?= (($_GET['proveedor'] ?? '') == $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Catálogo:</label>
                        <select name="catalogo" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($catalogos as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= (($_GET['catalogo'] ?? '') == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Fila 5 -->
                <div class="search-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Consultora:</label>
                        <select name="consultora" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($consultoras as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= (($_GET['consultora'] ?? '') == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Num. acción:</label>
                        <input type="text" name="id_accion" class="form-control" value="<?= htmlspecialchars($_GET['id_accion'] ?? '') ?>" style="width: 80px;">
                    </div>
                    <div class="form-group">
                        <label>Prioridad:</label>
                        <select name="prioridad" class="form-control" style="width: 100px;">
                            <option value=""></option>
                            <?php foreach ($prioridades as $p): ?>
                                <option value="<?= $p ?>" <?= (($_GET['prioridad'] ?? '') == $p) ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modalidad:</label>
                        <select name="modalidad" class="form-control" style="width: 140px;">
                            <option value=""></option>
                            <?php foreach ($modalidades as $m): ?>
                                <option value="<?= $m ?>" <?= (($_GET['modalidad'] ?? '') == $m) ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reserva:</label>
                        <select name="reserva" class="form-control" style="width: 80px;">
                            <option value=""></option>
                        </select>
                    </div>
                </div>

                <div class="button-bar">
                    <button type="submit" class="btn-buscar">Buscar</button>
                    <button type="button" class="btn-print" onclick="alert('Funcionalidad en desarrollo')">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg" width="14" alt="PDF">
                        Imprimir Contenidos
                    </button>
                    <button type="button" class="btn-print" onclick="alert('Funcionalidad en desarrollo')">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg" width="14" alt="PDF">
                        Contenidos resumidos
                    </button>
                    <button type="button" class="btn-print" onclick="window.print()">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg" width="14" alt="PDF">
                        Imprimir
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($_GET['plan_id'])): ?>
        <div class="tabs-header-af">
            <button class="tab-af-btn active" data-tab="tab-listado" onclick="switchTab('tab-listado')">Listado de Acciones</button>
            <button class="tab-af-btn" data-tab="tab-nueva" onclick="switchTab('tab-nueva')">Nueva Acción Formativa</button>
        </div>
        <?php endif; ?>

        <div id="tab-listado" class="tab-content-af active">
            <div class="results-section">
                <div class="results-header" style="background: white; border-bottom: 2px solid #e2e8f0; padding: 15px 25px; border-radius: 8px 8px 0 0;">
                    <h2 style="margin: 0; color: #1e3a8a; font-size: 1rem; text-transform: uppercase;">Gestión de Acciones Formativas</h2>
                </div>

            <div class="table-responsive" style="background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 15px; text-align: left; font-size: 0.7rem; color: #64748b;">ID / CÓDIGO</th>
                            <th style="padding: 15px; text-align: left; font-size: 0.7rem; color: #64748b;">NOMBRE DEL CURSO</th>
                            <th style="padding: 15px; text-align: center; font-size: 0.7rem; color: #64748b;">MODALIDAD</th>
                            <th style="padding: 15px; text-align: center; font-size: 0.7rem; color: #64748b;">DURACIÓN</th>
                            <th style="padding: 15px; text-align: center; font-size: 0.7rem; color: #64748b;">MATRÍCULAS</th>
                            <th style="padding: 15px; text-align: center; font-size: 0.7rem; color: #64748b;">ESTADO</th>
                            <th style="padding: 15px; text-align: center; font-size: 0.7rem; color: #64748b;">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($searched && count($results) > 0): ?>
                            <?php foreach ($results as $row): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                    <td style="padding: 15px;">
                                        <div style="font-weight: 800; color: #1e3a8a;">#<?= $row['id'] ?></div>
                                        <small style="color: #94a3b8; font-weight: 600;"><?= htmlspecialchars($row['num_accion'] ?? '---') ?></small>
                                    </td>
                                    <td style="padding: 15px;">
                                        <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem;"><?= htmlspecialchars($row['titulo'] ?? '') ?></div>
                                        <small style="color: #64748b; font-weight: 600;"><?= htmlspecialchars($row['nombre_plan'] ?? 'Sin Plan') ?></small>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <span style="background: #eff6ff; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800;"><?= htmlspecialchars($row['modalidad'] ?? '') ?></span>
                                    </td>
                                    <td style="padding: 15px; text-align: center; font-weight: 800; color: #1e293b;"><?= $row['duracion'] ?>h</td>
                                    <td style="padding: 15px; text-align: center;">
                                        <div style="background: #f1f5f9; padding: 5px; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px;">
                                            <span style="font-weight: 800; color: #1e3a8a; font-size: 1rem;"><?= $row['participantes'] ?? 0 ?></span>
                                            <small style="font-weight: 700; color: #94a3b8; font-size: 0.6rem;">ALUMNOS</small>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <span style="font-weight: 800; font-size: 0.7rem; color: <?= ($row['estado'] == 'ACTIVA' || $row['estado'] == 'En curso') ? '#16a34a' : '#ef4444' ?>;">
                                            ● <?= htmlspecialchars($row['estado'] ?? 'PENDIENTE') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <a href="editar_af.php?id=<?= $row['id'] ?>" class="btn-action" title="Editar Parámetros">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <a href="gestion_matriculas.php?af_id=<?= $row['id'] ?>" class="btn-action" style="color: #16a34a;" title="Matricular Alumnos">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="17" y1="11" x2="23" y2="11"></line></svg>
                                            </a>
                                            <button onclick="syncMoodle(<?= $row['id'] ?>)" class="btn-action" style="color: #ea580c; border:none; background:none; cursor:pointer;" title="Sincronizar Aula Virtual">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17.5 19a3.5 3.5 0 0 1-3.5-3.5c0-1.57 1.03-2.9 2.45-3.3a3.5 3.5 0 0 1 5.05-3.2c.46.22.86.54 1.18.94A5 5 0 0 1 21 19h-3.5z"></path><path d="M12 13l-3 3 3 3"></path><path d="M9 16h9"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif ($searched): ?>
                            <tr>
                                <td colspan="7" style="padding: 40px; text-align: center;">
                                    <div style="color: #ef4444; font-weight: 600; margin-bottom: 20px;">No se encontraron resultados para los filtros aplicados.</div>
                                    <?php if (!empty($_GET['plan_id'])): ?>
                                        <a href="nueva_af.php?plan_id=<?= (int)$_GET['plan_id'] ?>" class="btn-buscar" style="background: #1e3a8a; color: white; border: none; text-decoration: none; padding: 10px 25px; display: inline-block;">
                                            Crear Nueva Acción Formativa para este Plan
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding: 40px; text-align: center; color: #64748b;">Utilice los filtros superiores para comenzar la gestión.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>

        <?php if (!empty($_GET['plan_id'])): ?>
        <div id="tab-nueva" class="tab-content-af">
            <form action="procesar_nueva_af.php" method="POST" class="form-card-tab">
                <input type="hidden" name="plan_id" value="<?= (int)$_GET['plan_id'] ?>">
                
                <div class="section-title-tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    Identificación de la Acción
                </div>
                
                <div class="grid-form-tab">
                    <div class="form-group-tab" style="grid-column: span 2;">
                        <label>Título Completo del Curso:</label>
                        <input type="text" name="titulo" class="form-control-tab" placeholder="Ej: Gestión de Equipos de Trabajo" required>
                    </div>
                    <div class="form-group-tab">
                        <label>Nombre Corto / Abrev:</label>
                        <input type="text" name="abreviatura" class="form-control-tab" placeholder="Ej: GET-2024" required>
                    </div>
                    <div class="form-group-tab">
                        <label>Nº de Acción (Código):</label>
                        <input type="text" name="num_accion" class="form-control-tab" placeholder="0001">
                    </div>
                </div>

                <div class="section-title-tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                    Configuración Didáctica
                </div>

                <div class="grid-form-tab">
                    <div class="form-group-tab">
                        <label>Plan Estratégico:</label>
                        <select class="form-control-tab" disabled>
                            <option><?= htmlspecialchars($current_plan_name) ?></option>
                        </select>
                    </div>
                    <div class="form-group-tab">
                        <label>Modalidad:</label>
                        <select name="modalidad" class="form-control-tab">
                            <option value="TELEFORMACIÓN">TELEFORMACIÓN</option>
                            <option value="PRESENCIAL">PRESENCIAL</option>
                            <option value="MIXTA">MIXTA</option>
                            <option value="AULA VIRTUAL">AULA VIRTUAL</option>
                        </select>
                    </div>
                    <div class="form-group-tab">
                        <label>Duración (Horas Totales):</label>
                        <input type="number" name="duracion" class="form-control-tab" value="60">
                    </div>
                    <div class="form-group-tab">
                        <label>Familia Profesional:</label>
                        <select name="familia_profesional" class="form-control-tab">
                            <option value=""></option>
                            <option value="Administración y Gestión">Administración y Gestión</option>
                            <option value="Comercio y Marketing">Comercio y Marketing</option>
                            <option value="Hostelería y Turismo">Hostelería y Turismo</option>
                            <option value="Informática y Comunicaciones">Informática y Comunicaciones</option>
                            <option value="Sanidad">Sanidad</option>
                            <option value="Servicios Socioculturales">Servicios Socioculturales</option>
                            <option value="Transversal">Transversal</option>
                        </select>
                    </div>
                </div>

                <div class="moodle-sync-box-tab">
                    <input type="checkbox" name="crear_moodle" id="crear_moodle_tab" checked style="width: 18px; height: 18px;">
                    <div>
                        <label for="crear_moodle_tab" style="font-weight: 700; color: #1e40af; cursor: pointer; display: block; font-size: 0.8rem;">Aprovisionar automáticamente en el Aula Virtual</label>
                        <span style="font-size: 0.7rem; color: #3b82f6;">Se creará un curso en Moodle y se vinculará automáticamente.</span>
                    </div>
                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 0.9rem; border-radius: 8px; cursor: pointer;">Crear Acción Formativa</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?= $back_url ?>" class="btn-volver" style="padding: 10px 30px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">« Volver</a>
        </div>

    </main>
</div>

</body>
</html>
