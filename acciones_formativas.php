<?php
// acciones_formativas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COMERCIAL, ROLE_COORD])) {
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
        /* ===== ACCIONES FORMATIVAS STYLES ===== */

        /* Search Card Premium */
        .search-card-premium {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            transition: background-color 0.4s ease, border-color 0.4s ease;
        }

        .card-header-premium {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 108, 228, 0.15);
        }

        .card-header-premium h2 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: white;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.25rem;
            padding: 2rem;
        }

        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group-custom.span-12 { grid-column: span 12; }
        .form-group-custom.span-8 { grid-column: span 8; }
        .form-group-custom.span-6 { grid-column: span 6; }
        .form-group-custom.span-5 { grid-column: span 5; }
        .form-group-custom.span-4 { grid-column: span 4; }
        .form-group-custom.span-3 { grid-column: span 3; }
        .form-group-custom.span-2 { grid-column: span 2; }

        .form-group-custom label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Results Card Layout */
        .results-section-premium {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .results-header-premium {
            background: rgba(0, 108, 228, 0.03);
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-header-premium h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Table */
        .table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .table-premium th {
            background: rgba(0, 108, 228, 0.04);
            border-bottom: 2px solid var(--border-color);
            padding: 1rem 1.5rem;
            text-align: left;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-premium td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .table-premium tr:last-child td {
            border-bottom: none;
        }

        .table-premium tr:hover td {
            background-color: rgba(0, 108, 228, 0.015);
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.15);
            border-color: var(--primary-color);
        }

        /* Loading Spinner */
        .syncing { animation: spin 1s linear infinite; color: #ea580c !important; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* Tabs Styles */
        .tabs-header-af {
            display: flex;
            gap: 8px;
            margin-bottom: 1.5rem;
            padding-left: 5px;
        }

        .tab-af-btn {
            padding: 10px 24px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.25s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tab-af-btn:hover {
            background: rgba(0, 108, 228, 0.04);
            color: var(--primary-color);
            border-color: var(--card-hover-border);
        }

        .tab-af-btn.active {
            background: var(--primary-color);
            color: white !important;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.2);
        }

        /* Form Styles for Tab */
        .form-card-tab {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            padding: 30px;
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
        }
        
        .section-title-tab {
            color: var(--primary-color);
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 8px;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .grid-form-tab {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group-tab label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .moodle-sync-box-tab {
            background: rgba(0, 108, 228, 0.05);
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 12px;
            margin-top: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Responsive Media Queries */
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column !important;
            }
            .main-content {
                padding: 15px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            .card-header-premium {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 12px !important;
                padding: 15px !important;
            }
            .card-header-premium h2 {
                text-align: center !important;
            }
            .form-grid {
                padding: 15px !important;
                gap: 12px !important;
            }
            .form-group-custom {
                grid-column: span 12 !important;
            }
            .tabs-header-af {
                flex-direction: column !important;
                gap: 5px !important;
                padding-left: 0 !important;
            }
            .tab-af-btn {
                border-radius: 6px !important;
                border: 1px solid var(--glass-border) !important;
                text-align: center !important;
                padding: 12px 15px !important;
            }
            .form-card-tab {
                padding: 15px !important;
                border-radius: 8px !important;
            }
            .grid-form-tab {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .moodle-sync-box-tab {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 8px !important;
            }
            
            /* Responsive Table (Cards transformation) */
            .table-premium, .table-premium thead, .table-premium tbody, .table-premium th, .table-premium td, .table-premium tr {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .table-premium thead {
                display: none !important;
            }
            .table-premium tr {
                margin-bottom: 20px !important;
                border: 1px solid var(--border-color) !important;
                border-radius: 12px !important;
                background: var(--glass-bg) !important;
                box-shadow: var(--glass-shadow) !important;
                padding: 12px !important;
            }
            .table-premium td {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                border-bottom: 1px solid var(--border-color) !important;
                padding: 12px 5px !important;
                text-align: right !important;
                white-space: normal !important;
                color: var(--text-color) !important;
            }
            .table-premium td:last-child {
                border-bottom: none !important;
            }
            .table-premium td::before {
                content: attr(data-label) !important;
                font-weight: 700 !important;
                color: var(--primary-color) !important;
                font-size: 0.75rem !important;
                text-transform: uppercase !important;
                text-align: left !important;
                margin-right: 15px !important;
                flex-shrink: 0 !important;
            }
            .table-premium td div {
                text-align: right !important;
            }
            .table-premium td div[style*="justify-content: center"] {
                justify-content: flex-end !important;
            }
        }
    </style>
    <script>

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
        
        <?php if (!empty($_GET['msg'])): ?>
            <div style="background: #dcfce7; border-left: 4px solid #16a34a; padding: 15px; margin: 15px 0; color: #15803d; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">
                ✓ <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 15px 0; color: #b91c1c; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">
                ✗ Error: <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>
          <div class="search-card-premium">
            <div class="card-header-premium" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem;">
                <h2 style="margin: 0; color: white; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; letter-spacing: 2px;"><?= $current_plan_name ? 'CURSOS DEL PLAN: ' . htmlspecialchars($current_plan_name) : ($page_title_prefix . ' - FILTROS DE BÚSQUEDA') ?></h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <?php if ($current_plan_name): ?>
                        <button type="button" onclick="document.getElementById('searchForm').style.display = (document.getElementById('searchForm').style.display === 'none' ? 'block' : 'none')" class="btn btn-glass" style="font-size: 0.7rem; padding: 4px 10px; cursor:pointer; color: white; border: 1px solid rgba(255,255,255,0.3);">🔍 Filtros</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <form id="searchForm" method="GET" style="margin:0; <?= $current_plan_name ? 'display: none;' : '' ?>">
                <input type="hidden" name="context" value="<?= htmlspecialchars($_GET['context'] ?? '') ?>">
                
                <div class="form-grid">
                    <!-- Fila 1 -->
                    <div class="form-group-custom span-6">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control" style="width: 100%;">
                    </div>
                    
                    <div class="form-group-custom span-6">
                        <label>Plan:</label>
                        <select name="plan_id" class="form-control" style="width: 100%;">
                            <option value="">Todos los planes</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (($_GET['plan_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 2 -->
                    <div class="form-group-custom span-4">
                        <label>Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control" style="width: 100%;">
                            <option value="">Todas</option>
                            <?php foreach ($convocatorias as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (($_GET['convocatoria_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-custom span-4">
                        <label>Solicitante:</label>
                        <select name="solicitante" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($solicitantes as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= (($_GET['solicitante'] ?? '') == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-custom span-4">
                        <label>Sector:</label>
                        <select name="sector" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= (($_GET['sector'] ?? '') == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 3 -->
                    <div class="form-group-custom span-4">
                        <label>Proveedor:</label>
                        <select name="proveedor" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>" <?= (($_GET['proveedor'] ?? '') == $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-custom span-4">
                        <label>Catálogo:</label>
                        <select name="catalogo" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($catalogos as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= (($_GET['catalogo'] ?? '') == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-custom span-4">
                        <label>Consultora:</label>
                        <select name="consultora" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($consultoras as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= (($_GET['consultora'] ?? '') == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 4 -->
                    <div class="form-group-custom span-3">
                        <label>Num. acción:</label>
                        <input type="text" name="id_accion" class="form-control" value="<?= htmlspecialchars($_GET['id_accion'] ?? '') ?>" style="width: 100%;">
                    </div>
                    
                    <div class="form-group-custom span-3">
                        <label>Prioridad:</label>
                        <select name="prioridad" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($prioridades as $p): ?>
                                <option value="<?= $p ?>" <?= (($_GET['prioridad'] ?? '') == $p) ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-custom span-3">
                        <label>Modalidad:</label>
                        <select name="modalidad" class="form-control" style="width: 100%;">
                            <option value=""></option>
                            <?php foreach ($modalidades as $m): ?>
                                <option value="<?= $m ?>" <?= (($_GET['modalidad'] ?? '') == $m) ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-custom span-3">
                        <label>Reserva:</label>
                        <select name="reserva" class="form-control" style="width: 100%;">
                            <option value=""></option>
                        </select>
                    </div>

                    <!-- Botón de Búsqueda -->
                    <div style="grid-column: span 12; display: flex; justify-content: center; margin-top: 15px; align-items: center;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 2.5rem;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            Buscar
                        </button>
                    </div>
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
            <div class="results-section-premium">
                <div class="results-header-premium">
                    <h2>Gestión de Acciones Formativas</h2>
                    <div>
                        <button type="button" class="btn btn-glass" style="border: 1px solid var(--border-color); font-size: 0.78rem; padding: 0.5rem 1rem;" onclick="window.print()">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#475569" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            Imprimir Pantalla
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="background: transparent; border-radius: 0 0 16px 16px; box-shadow: none; border-bottom: none; overflow-x: auto; max-width: 100%;">
                    <table class="table-premium" style="width: 100%; border-collapse: collapse; min-width: 900px;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="text-align: left;">ID / CÓDIGO</th>
                                <th style="text-align: left;">NOMBRE DEL CURSO</th>
                                <th style="text-align: center;">MODALIDAD</th>
                                <th style="text-align: center;">DURACIÓN</th>
                                <th style="text-align: center;">MATRÍCULAS</th>
                                <th style="text-align: center;">ESTADO</th>
                                <th style="text-align: center;">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($searched && count($results) > 0): ?>
                                <?php foreach ($results as $row): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                                        <td data-label="ID / Código">
                                            <div style="font-weight: 800; color: var(--primary-color);">#<?= $row['id'] ?></div>
                                            <small style="color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($row['num_accion'] ?? '---') ?></small>
                                        </td>
                                        <td data-label="Nombre del Curso">
                                            <div style="font-weight: 700; color: var(--text-color); font-size: 0.9rem; white-space: normal;"><?= htmlspecialchars($row['titulo'] ?? '') ?></div>
                                            <small style="color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($row['nombre_plan'] ?? 'Sin Plan') ?></small>
                                        </td>
                                        <td data-label="Modalidad" style="text-align: center;">
                                            <span style="background: rgba(0, 108, 228, 0.08); color: var(--primary-color); padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800;"><?= htmlspecialchars($row['modalidad'] ?? '') ?></span>
                                        </td>
                                        <td data-label="Duración" style="text-align: center; font-weight: 800; color: var(--text-color);"><?= $row['duracion'] ?>h</td>
                                        <td data-label="Matrículas" style="text-align: center;">
                                            <div style="background: rgba(148, 163, 184, 0.08); padding: 5px 10px; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px;">
                                                <span style="font-weight: 800; color: var(--primary-color); font-size: 1rem;"><?= $row['participantes'] ?? 0 ?></span>
                                                <small style="font-weight: 700; color: var(--text-muted); font-size: 0.6rem;">ALUMNOS</small>
                                            </div>
                                        </td>
                                        <td data-label="Estado" style="text-align: center;">
                                            <span style="font-weight: 800; font-size: 0.75rem; color: <?= ($row['estado'] == 'ACTIVA' || $row['estado'] == 'En curso') ? '#16a34a' : '#ef4444' ?>;">
                                                ● <?= htmlspecialchars($row['estado'] ?? 'PENDIENTE') ?>
                                            </span>
                                        </td>
                                        <td data-label="Acciones">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <a href="ficha_accion_formativa.php?id=<?= $row['id'] ?>" class="btn-action" style="color: #6366f1;" title="Ficha Acción Formativa">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </a>
                                                <a href="imprimir_contenidos.php?id_accion=<?= $row['id'] ?>" target="_blank" class="btn-action" style="color: #dc2626;" title="Imprimir Contenidos">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                                </a>
                                                <a href="imprimir_objetivos.php?id_accion=<?= $row['id'] ?>" target="_blank" class="btn-action" style="color: #f59e0b;" title="Imprimir Objetivos">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
                                                </a>
                                                <a href="imprimir_contenidos.php?id_accion=<?= $row['id'] ?>&tipo=resumido" target="_blank" class="btn-action" style="color: #2563eb;" title="Contenidos resumidos">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                                                </a>
                                                <a href="editar_af.php?id=<?= $row['id'] ?>" class="btn-action" title="Editar Parámetros">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                </a>
                                                <a href="gestion_matriculas.php?af_id=<?= $row['id'] ?>" class="btn-action" style="color: #16a34a;" title="Matricular Alumnos">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="17" y1="11" x2="23" y2="11"></line></svg>
                                                </a>
                                                <?php if (has_permission([ROLE_ADMIN])): ?>
                                                <a href="borrar_af.php?id=<?= $row['id'] ?>&csrf_token=<?= urlencode($_SESSION['csrf_token'] ?? '') ?>" class="btn-action" style="color: #ef4444;" title="Borrar Acción Formativa" onclick="return confirm('¿Seguro que deseas eliminar esta Acción Formativa? Esta acción no se puede deshacer y eliminará sus grupos y matrículas.');">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif ($searched): ?>
                                <tr>
                                    <td colspan="7" style="padding: 40px; text-align: center;">
                                        <div style="color: #ef4444; font-weight: 600; margin-bottom: 20px;">No se encontraron resultados para los filtros aplicados.</div>
                                        <?php if (!empty($_GET['plan_id'])): ?>
                                            <a href="nueva_af.php?plan_id=<?= (int)$_GET['plan_id'] ?>" class="btn btn-primary" style="text-decoration: none; padding: 10px 25px; display: inline-block;">
                                                Crear Nueva Acción Formativa para este Plan
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="padding: 40px; text-align: center; color: var(--text-muted);">Utilice los filtros superiores para comenzar la gestión.</td>
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="plan_id" value="<?= (int)$_GET['plan_id'] ?>">
                
                <div class="section-title-tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    Identificación de la Acción
                </div>
                
                <div class="grid-form-tab">
                    <div class="form-group-tab" style="grid-column: span 2;">
                        <label>Título Completo del Curso:</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ej: Gestión de Equipos de Trabajo" required>
                    </div>
                    <div class="form-group-tab">
                        <label>Nombre Corto / Abrev:</label>
                        <input type="text" name="abreviatura" class="form-control" placeholder="Ej: GET-2024" required>
                    </div>
                    <div class="form-group-tab">
                        <label>Nº de Acción (Código):</label>
                        <input type="text" name="num_accion" class="form-control" placeholder="0001">
                    </div>
                </div>

                <div class="section-title-tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                    Configuración Didáctica
                </div>

                <div class="grid-form-tab">
                    <div class="form-group-tab">
                        <label>Plan Estratégico:</label>
                        <select class="form-control" disabled style="opacity: 0.7;">
                            <option><?= htmlspecialchars($current_plan_name) ?></option>
                        </select>
                    </div>
                    <div class="form-group-tab">
                        <label>Modalidad:</label>
                        <select name="modalidad" class="form-control">
                            <option value="TELEFORMACIÓN">TELEFORMACIÓN</option>
                            <option value="PRESENCIAL">PRESENCIAL</option>
                            <option value="MIXTA">MIXTA</option>
                            <option value="AULA VIRTUAL">AULA VIRTUAL</option>
                        </select>
                    </div>
                    <div class="form-group-tab">
                        <label>Duración (Horas Totales):</label>
                        <input type="number" name="duracion" class="form-control" value="60">
                    </div>
                    <div class="form-group-tab">
                        <label>Familia Profesional:</label>
                        <select name="familia_profesional" class="form-control">
                            <option value=""></option>
                            <option value="Actividades Físicas y Deportivas">Actividades Físicas y Deportivas</option>
                            <option value="Actividades y Competencias Transversales">Actividades y Competencias Transversales</option>
                            <option value="Administración y Gestión">Administración y Gestión</option>
                            <option value="Agraria">Agraria</option>
                            <option value="Artes Gráficas">Artes Gráficas</option>
                            <option value="Artes y Artesanías">Artes y Artesanías</option>
                            <option value="Comercio y Marketing">Comercio y Marketing</option>
                            <option value="Edificación y Obra Civil">Edificación y Obra Civil</option>
                            <option value="Electricidad y Electrónica">Electricidad y Electrónica</option>
                            <option value="Energía y Agua">Energía y Agua</option>
                            <option value="Fabricación Mecánica">Fabricación Mecánica</option>
                            <option value="Hostelería y Turismo">Hostelería y Turismo</option>
                            <option value="Imagen Personal">Imagen Personal</option>
                            <option value="Imagen y Sonido">Imagen y Sonido</option>
                            <option value="Industrias Alimentarias">Industrias Alimentarias</option>
                            <option value="Industrias Extractivas">Industrias Extractivas</option>
                            <option value="Informática y Comunicaciones">Informática y Comunicaciones</option>
                            <option value="Instalación y Mantenimiento">Instalación y Mantenimiento</option>
                            <option value="Inteligencia Artificial y Data">Inteligencia Artificial y Data</option>
                            <option value="Madera, Mueble y Corcho">Madera, Mueble y Corcho</option>
                            <option value="Marítimo-Pesquera">Marítimo-Pesquera</option>
                            <option value="Química">Química</option>
                            <option value="Sanidad">Sanidad</option>
                            <option value="Seguridad y Medio Ambiente">Seguridad y Medio Ambiente</option>
                            <option value="Servicios Socioculturales y a la Comunidad">Servicios Socioculturales y a la Comunidad</option>
                            <option value="Textil, Confección y Piel">Textil, Confección y Piel</option>
                            <option value="Transporte y Mantenimiento de Vehículos">Transporte y Mantenimiento de Vehículos</option>
                            <option value="Vidrio y Cerámica">Vidrio y Cerámica</option>
                            <option value="Transversal">Transversal</option>
                        </select>
                    </div>
                </div>

                <div class="moodle-sync-box-tab">
                    <input type="checkbox" name="crear_moodle" id="crear_moodle_tab" checked style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <label for="crear_moodle_tab" style="font-weight: 700; color: var(--primary-color); cursor: pointer; display: block; font-size: 0.8rem; margin-bottom: 0;">Aprovisionar automáticamente en el Aula Virtual</label>
                        <span style="font-size: 0.7rem; color: var(--text-muted);">Se creará un curso en Moodle y se vinculará automáticamente.</span>
                    </div>
                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 2.5rem; border-radius: 8px; cursor: pointer;">Crear Acción Formativa</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?= $back_url ?>" class="btn btn-glass" style="border: 1px solid var(--border-color); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; display: inline-block;">« Volver</a>
        </div>

    </main>
</div>

</body>
</html>
