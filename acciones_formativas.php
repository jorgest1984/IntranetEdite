<?php
// acciones_formativas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    die("No tiene permisos suficientes para acceder a Acciones Formativas.");
}

$is_subvencionada = (isset($_GET['context']) && $_GET['context'] === 'subvencionada');
$page_title_prefix = $is_subvencionada ? 'FORMACIÓN SUBVENCIONADA' : 'ACCIONES FORMATIVAS';
$back_url = $is_subvencionada ? 'formacion_subvencionada.php' : 'formacion_bonificada.php';

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
    <meta charset="UTF-8">
    <title><?= $is_subvencionada ? 'Formación Subvencionada' : 'Acciones Formativas' ?> - Campos de Búsqueda</title>
    <style>
        :root {
            --primary-blue: #1a237e;
            --header-red: #b22222;
            --border-gray: #ccc;
            --bg-light: #f9f9f9;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-header {
            color: var(--header-red);
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid var(--border-gray);
            padding-bottom: 5px;
        }
        .search-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .search-grid td {
            padding: 8px 10px;
            border: 1px solid #efefef;
            vertical-align: middle;
        }
        .label {
            color: var(--primary-blue);
            font-weight: bold;
            font-size: 13px;
            text-align: right;
            white-space: nowrap;
            width: 120px;
        }
        .input-field {
            width: 100%;
            padding: 4px;
            border: 1px solid #aaa;
            border-radius: 2px;
            font-size: 13px;
            box-sizing: border-box;
        }
        .button-bar {
            text-align: center;
            padding: 15px;
            gap: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .btn-classic {
            background: #eee;
            border: 1px solid #999;
            padding: 5px 15px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            color: #000;
        }
        .btn-classic:hover {
            background: #ddd;
        }
        .pdf-icon {
            width: 16px;
            height: 16px;
        }
        .results-section {
            margin-top: 30px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 10px;
        }
        .results-table th {
            background: #eee;
            color: var(--primary-blue);
            border: 1px solid var(--border-gray);
            padding: 5px;
            text-align: center;
            white-space: nowrap;
        }
        .results-table td {
            border: 1px solid var(--border-gray);
            padding: 5px;
            text-align: left;
        }
        .results-table tr:hover {
            background-color: #f5f5f5;
        }
        .sort-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            vertical-align: middle;
        }
        .volver-container {
            text-align: center;
            margin-top: 20px;
        }
        .check-group {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--primary-blue);
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="section-header"><?= $page_title_prefix ?> - CAMPOS DE BÚSQUEDA</div>
    
    <form method="GET">
        <table class="search-grid">
            <!-- Row 1 -->
            <tr>
                <td class="label">Nombre:</td>
                <td colspan="5">
                    <input type="text" name="nombre" class="input-field" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
                </td>
            </tr>
            <!-- Row 2 -->
            <tr>
                <td class="label">Convocatoria:</td>
                <td>
                    <select name="convocatoria_id" class="input-field">
                        <option value="">Todas</option>
                        <?php foreach ($convocatorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($_GET['convocatoria_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="label">Plan:</td>
                <td colspan="3">
                    <select name="plan_id" class="input-field">
                        <option value="">Todos los planes</option>
                        <?php foreach ($planes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (($_GET['plan_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <!-- Row 3 -->
            <tr>
                <td class="label">Solicitante:</td>
                <td>
                    <select name="solicitante" class="input-field">
                        <option value=""></option>
                        <?php foreach ($solicitantes as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= (($_GET['solicitante'] ?? '') == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="label">Sector:</td>
                <td colspan="3">
                    <select name="sector" class="input-field">
                        <option value=""></option>
                        <?php foreach ($sectores as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= (($_GET['sector'] ?? '') == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <!-- Row 4 -->
            <tr>
                <td class="label">Proveedor:</td>
                <td>
                    <select name="proveedor" class="input-field">
                        <option value=""></option>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= (($_GET['proveedor'] ?? '') == $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="label">Catálogo:</td>
                <td colspan="3">
                    <select name="catalogo" class="input-field">
                        <option value=""></option>
                        <?php foreach ($catalogos as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= (($_GET['catalogo'] ?? '') == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <!-- Row 5 -->
            <tr>
                <td class="label">Consultora:</td>
                <td>
                    <select name="consultora" class="input-field">
                        <option value=""></option>
                        <?php foreach ($consultoras as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= (($_GET['consultora'] ?? '') == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="label">Num. acción:</td>
                <td>
                    <input type="text" name="id_accion" class="input-field" value="<?= htmlspecialchars($_GET['id_accion'] ?? '') ?>">
                </td>
                <td class="label">Prioridad:</td>
                <td>
                    <select name="prioridad" class="input-field">
                        <option value=""></option>
                        <?php foreach ($prioridades as $p): ?>
                            <option value="<?= $p ?>" <?= (($_GET['prioridad'] ?? '') == $p) ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">Modalidad:</td>
                <td>
                    <select name="modalidad" class="input-field">
                        <option value=""></option>
                        <?php foreach ($modalidades as $m): ?>
                            <option value="<?= $m ?>" <?= (($_GET['modalidad'] ?? '') == $m) ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="label">Reserva:</td>
                <td colspan="3">
                    <select name="reserva" class="input-field">
                        <option value=""></option>
                    </select>
                </td>
            </tr>
        </table>

        <div class="button-bar">
            <button type="submit" class="btn-classic">Buscar</button>
            <button type="button" class="btn-classic">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Imprimir Contenidos
            </button>
            <button type="button" class="btn-classic">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Contenidos resumidos
            </button>
            <button type="button" class="btn-classic">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Imprimir
            </button>
        </div>
    </form>

    <div class="results-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
            <div class="check-group">
                Ordenar múltiple <input type="checkbox" name="ord_multiple">
            </div>
            <div style="color: var(--header-red); font-weight: bold; font-size: 14px; text-transform: uppercase;">
                RESULTADO DE LA BÚSQUEDA
            </div>
            <div style="width: 150px;"></div>
        </div>

        <table class="results-table">
            <thead>
                <tr>
                    <th><button class="sort-btn">⬇️</button> Nº Acc</th>
                    <th><button class="sort-btn">⬇️</button> Título</th>
                    <th><button class="sort-btn">⬇️</button> Abrev.</th>
                    <th><button class="sort-btn">⬇️</button> Modalidad</th>
                    <th><button class="sort-btn">⬇️</button> Duración</th>
                    <th><button class="sort-btn">⬇️</button> Plan</th>
                    <th><button class="sort-btn">⬇️</button> Partic.</th>
                    <th><button class="sort-btn">⬇️</button> Mostrar</th>
                    <th><button class="sort-btn">⬇️</button> Estado</th>
                    <th><button class="sort-btn">⬇️</button> Tutor1</th>
                    <th><button class="sort-btn">⬇️</button> Tutor2</th>
                    <th><button class="sort-btn">⬇️</button> Win</th>
                    <th><button class="sort-btn">⬇️</button> Mac</th>
                    <th><button class="sort-btn">⬇️</button> Proveedor</th>
                    <th><button class="sort-btn">⬇️</button> Precio venta</th>
                    <th><button class="sort-btn">⬇️</button> Último inicio</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($searched && count($results) > 0): ?>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td align="center"><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['titulo']) ?></td>
                            <td><?= htmlspecialchars($row['abreviatura'] ?? '') ?></td>
                            <td align="center"><?= htmlspecialchars($row['modalidad'] ?? '') ?></td>
                            <td align="center"><?= $row['duracion'] ?></td>
                            <td><?= htmlspecialchars($row['nombre_plan'] ?? '') ?></td>
                            <td align="center"><?= $row['participantes'] ?></td>
                            <td align="center"><?= $row['mostrar_web'] ? 'Sí' : 'No' ?></td>
                            <td align="center"><?= htmlspecialchars($row['estado']) ?></td>
                            <td><?= $row['tutor1_id'] ?></td>
                            <td><?= $row['tutor2_id'] ?></td>
                            <td align="center"><?= $row['win'] ? 'X' : '' ?></td>
                            <td align="center"><?= $row['mac'] ? 'X' : '' ?></td>
                            <td><?= htmlspecialchars($row['proveedor'] ?? '') ?></td>
                            <td align="right"><?= number_format($row['precio_venta'], 2) ?> €</td>
                            <td align="center"><?= $row['ultimo_inicio'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif ($searched): ?>
                    <tr>
                        <td colspan="16" align="center" style="padding: 20px; color: #666;">No se encontraron acciones formativas que coincidan con los criterios.</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="16" align="center" style="padding: 20px; color: #666;">Realice una búsqueda para ver los resultados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="volver-container">
        <a href="<?= $back_url ?>" class="btn-classic" style="text-decoration: none; display: inline-block;">Volver</a>
    </div>
</div>

</body>
</html>
