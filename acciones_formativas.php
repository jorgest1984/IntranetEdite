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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $searched = true;
    $params = [];
    $sql = "SELECT af.*, c.nombre_largo as titulo, p.nombre as nombre_plan 
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
    <title><?= $is_subvencionada ? 'Formación Subvencionada' : 'Acciones Formativas' ?> - Campos de Búsqueda</title>
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

        .td-center { text-align: center; }
        .td-right { text-align: right; }
    </style>
</head>
<body>

<div class="app-container" style="display: flex; min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto;">
        
        <div class="search-card">
            <div class="card-header-custom">
                <h2><?= $page_title_prefix ?> - CAMPOS DE BÚSQUEDA</h2>
            </div>
            
            <form class="search-form" method="GET">
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

        <div class="results-section">
            <div class="results-header">
                <div class="check-group">
                    <input type="checkbox" name="ord_multiple"> Ordenar múltiple
                </div>
                <h2>RESULTADO DE LA BÚSQUEDA</h2>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Nº Acc</th>
                            <th style="text-align: left;"><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Título</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Abrev.</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Modalidad</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Duración</th>
                            <th style="text-align: left;"><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Plan</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Partic.</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Mostrar</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Estado</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Tutor1</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Tutor2</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Win</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Mac</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Proveedor</th>
                            <th style="text-align: right;"><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Precio venta</th>
                            <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Último inicio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($searched && count($results) > 0): ?>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td class="td-center"><?= $row['id'] ?></td>
                                    <td><a href="#" style="color: var(--label-blue); font-weight: 700; text-decoration: none;"><?= htmlspecialchars($row['titulo'] ?? '') ?></a></td>
                                    <td><?= htmlspecialchars($row['abreviatura'] ?? '') ?></td>
                                    <td class="td-center"><?= htmlspecialchars($row['modalidad'] ?? '') ?></td>
                                    <td class="td-center"><?= $row['duracion'] ?></td>
                                    <td><?= htmlspecialchars($row['nombre_plan'] ?? '') ?></td>
                                    <td class="td-center"><?= $row['participantes'] ?></td>
                                    <td class="td-center"><?= $row['mostrar_web'] ? 'Sí' : 'No' ?></td>
                                    <td class="td-center"><?= htmlspecialchars($row['estado'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['tutor1_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['tutor2_id'] ?? '') ?></td>
                                    <td class="td-center"><?= $row['win'] ? 'X' : '' ?></td>
                                    <td class="td-center"><?= $row['mac'] ? 'X' : '' ?></td>
                                    <td><?= htmlspecialchars($row['proveedor'] ?? '') ?></td>
                                    <td class="td-right"><?= number_format($row['precio_venta'], 2) ?> €</td>
                                    <td class="td-center"><?= htmlspecialchars($row['ultimo_inicio'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif ($searched): ?>
                            <tr>
                                <td colspan="16" class="td-center" style="padding: 2rem; color: var(--title-red); font-weight: 600;">
                                    No se encontraron acciones formativas que coincidan con los criterios seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="16" class="td-center" style="padding: 2rem; color: #64748b;">
                                    Utilice los filtros superiores para realizar una búsqueda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="<?= $back_url ?>" class="btn-volver" style="display: inline-block; text-decoration: none;">Volver</a>
        </div>

    </main>
</div>

</body>
</html>
