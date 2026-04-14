<?php
// buscar_empresas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = 'search';
$error = '';
$success = '';

$is_subvencionada = (isset($_GET['context']) && $_GET['context'] === 'subvencionada');
$page_title_prefix = $is_subvencionada ? 'FORMACIÓN SUBVENCIONADA' : 'EMPRESAS';
$back_url = $is_subvencionada ? 'formacion_subvencionada.php' : 'formacion_bonificada.php';

// Listas para dropdowns
$provincias = [
    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
];

$comerciales = [];
$sectores = [];

// LÓGICA DE BÚSQUEDA
$empresas = [];
$searchPerformed = false;

try {
    $sql = "SELECT e.* FROM empresas e WHERE 1=1";
    $params = [];

    // Filtros del Formulario
    if (!empty($_GET['nombre'])) {
        $sql .= " AND e.nombre LIKE ?";
        $params[] = "%" . $_GET['nombre'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['cif'])) {
        $sql .= " AND e.cif LIKE ?";
        $params[] = "%" . $_GET['cif'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['telefono'])) {
        $sql .= " AND (e.telefono LIKE ? OR e.contacto_telefono LIKE ?)";
        $params[] = "%" . $_GET['telefono'] . "%";
        $params[] = "%" . $_GET['telefono'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['email'])) {
        $sql .= " AND e.email LIKE ?";
        $params[] = "%" . $_GET['email'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['localidad'])) {
        $sql .= " AND e.localidad LIKE ?";
        $params[] = "%" . $_GET['localidad'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['cp'])) {
        $sql .= " AND e.cp LIKE ?";
        $params[] = "%" . $_GET['cp'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['actividad'])) {
        $sql .= " AND e.actividad LIKE ?";
        $params[] = "%" . $_GET['actividad'] . "%";
        $searchPerformed = true;
    }
    
    // Selects SI/NO (TINYINT 1/0)
    if (!empty($_GET['con_cursos']) && $_GET['con_cursos'] !== '---') {
        // Lógica para 'Empresas que hayan hecho cursos' - Requiere join con matriculas o similar
        // Por ahora lo dejamos como placeholder o filtro simple si existe la columna
        $searchPerformed = true;
    }
    if (!empty($_GET['adhesion']) && $_GET['adhesion'] !== '---') {
        $sql .= " AND e.es_adhesion = ?";
        $params[] = ($_GET['adhesion'] == 'SI' ? 1 : 0);
        $searchPerformed = true;
    }
    if (!empty($_GET['gestora']) && $_GET['gestora'] !== '---') {
        $sql .= " AND e.es_gestora = ?";
        $params[] = ($_GET['gestora'] == 'SI' ? 1 : 0);
        $searchPerformed = true;
    }
    if (!empty($_GET['mercadolid']) && $_GET['mercadolid'] !== '---') {
        $sql .= " AND e.es_mercadolid = ?";
        $params[] = ($_GET['mercadolid'] == 'SI' ? 1 : 0);
        $searchPerformed = true;
    }

    if (!empty($_GET['comercial_id'])) {
        $sql .= " AND e.comercial_id = ?";
        $params[] = $_GET['comercial_id'];
        $searchPerformed = true;
    }
    if (!empty($_GET['provincia'])) {
        $sql .= " AND e.provincia = ?";
        $params[] = $_GET['provincia'];
        $searchPerformed = true;
    }
    if (!empty($_GET['sector'])) {
        $sql .= " AND e.sector = ?";
        $params[] = $_GET['sector'];
        $searchPerformed = true;
    }

    if ($searchPerformed) {
        $sql .= " ORDER BY e.nombre ASC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $empresas = $stmt->fetchAll();
    } else {
        // Carga inicial (opcional, por defecto vacía según imagen de alumnos)
    }

} catch (Exception $e) {
    $error = "Error en la búsqueda: " . $e->getMessage();
}

// Cargar listas dinámicas
try {
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales->fetchAll();
    
    $stmtSectores = $pdo->query("SELECT DISTINCT sector FROM empresas WHERE sector IS NOT NULL AND sector != '' ORDER BY sector ASC");
    $sectores = $stmtSectores->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$current_page = 'buscar_empresas.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_subvencionada ? 'Formación Subvencionada' : 'Buscar Empresa' ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-blue: #006ce4;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
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
            color: var(--title-blue);
            text-transform: uppercase;
        }

        .search-form { padding: 1rem; }

        .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 8px;
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
            padding: 2px 5px;
            border: 1px solid var(--border-gray);
            border-radius: 2px;
            background: #fff;
        }

        select.form-control { height: 24px; padding: 0 5px; }
        input[type="text"].form-control { height: 22px; }

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
            padding: 2px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Results Table */
        .results-section {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            overflow: hidden;
        }

        .results-header {
            padding: 0.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border-gray);
        }

        .results-header h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--title-blue);
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
            min-width: 1100px;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-custom th {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            padding: 6px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 700;
        }

        .table-custom th svg {
            width: 10px;
            height: 10px;
            vertical-align: middle;
            margin-right: 4px;
        }

        .table-custom td {
            border: 1px solid #f1f5f9;
            padding: 6px;
            white-space: nowrap;
        }

        .table-custom tr:nth-child(even) { background: #f8fafc; }
        .table-custom tr:hover { background: #f1f5f9; }

        .btn-volver {
            margin-top: 10px;
            padding: 4px 15px;
            font-size: 0.75rem;
            cursor: pointer;
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
        }
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
                    
                    <!-- Fila 1 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Razón social:</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control" style="width: 280px;">
                        </div>
                        <div class="form-group">
                            <label>CIF:</label>
                            <input type="text" name="cif" value="<?= htmlspecialchars($_GET['cif'] ?? '') ?>" class="form-control" style="width: 120px;">
                        </div>
                        <div class="form-group">
                            <label>Teléfono:</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($_GET['telefono'] ?? '') ?>" class="form-control" style="width: 130px;">
                        </div>
                        <div class="form-group">
                            <label>email:</label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="text" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" class="form-control" style="width: 130px; padding-right: 25px;">
                                <svg viewBox="0 0 24 24" width="14" style="position: absolute; right: 5px; color: #fbbf24;"><circle cx="12" cy="12" r="10" fill="currentColor"/><circle cx="12" cy="12" r="3" fill="white"/></svg>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Localidad:</label>
                            <input type="text" name="localidad" value="<?= htmlspecialchars($_GET['localidad'] ?? '') ?>" class="form-control" style="width: 120px;">
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>C.P.:</label>
                            <input type="text" name="cp" value="<?= htmlspecialchars($_GET['cp'] ?? '') ?>" class="form-control" style="width: 90px;">
                        </div>
                        <div class="form-group">
                            <label>Empresas que hayan hecho cursos:</label>
                            <select name="con_cursos" class="form-control">
                                <option value="---">---</option>
                                <option value="SI">SI</option>
                                <option value="NO">NO</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Actividad:</label>
                            <input type="text" name="actividad" value="<?= htmlspecialchars($_GET['actividad'] ?? '') ?>" class="form-control" style="width: 150px;">
                        </div>
                        <div class="form-group">
                            <label>Empresas con adhesión:</label>
                            <select name="adhesion" class="form-control">
                                <option value="---">---</option>
                                <option value="SI" <?= ($_GET['adhesion'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                                <option value="NO" <?= ($_GET['adhesion'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Empresas gestoras:</label>
                            <select name="gestora" class="form-control">
                                <option value="---">---</option>
                                <option value="SI" <?= ($_GET['gestora'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                                <option value="NO" <?= ($_GET['gestora'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Empresas de Mercadolid:</label>
                            <select name="mercadolid" class="form-control">
                                <option value="---">---</option>
                                <option value="SI" <?= ($_GET['mercadolid'] ?? '') == 'SI' ? 'selected' : '' ?>>SI</option>
                                <option value="NO" <?= ($_GET['mercadolid'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Comercial:</label>
                            <select name="comercial_id" class="form-control" style="width: 250px;">
                                <option value="">---</option>
                                <?php foreach($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($_GET['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Provincia:</label>
                            <select name="provincia" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <?php foreach($provincias as $prov): ?>
                                    <option value="<?= mb_strtoupper($prov, 'UTF-8') ?>" <?= ($_GET['provincia'] ?? '') == mb_strtoupper($prov, 'UTF-8') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sector:</label>
                            <select name="sector" class="form-control" style="width: 450px;">
                                <option value="">---</option>
                                <?php foreach($sectores as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= ($_GET['sector'] ?? '') == $s ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn-buscar">Buscar</button>
                        <button type="button" class="btn-print" onclick="window.print()">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg" width="16" alt="PDF">
                            Imprimir
                        </button>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS -->
            <div class="results-section">
                <div class="results-header">
                    <div style="font-size: 0.65rem; display: flex; align-items: center; gap: 5px; margin-bottom: 5px; color: var(--label-blue);">
                        <input type="checkbox" name="multiple_sort"> Ordenar múltiple
                    </div>
                    <h2>RESULTADO DE LA BÚSQUEDA</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Razón social</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Localidad</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>CP</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Provincia</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Teléfono</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Email</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>RLT</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Adhesión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$searchPerformed): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 2rem; color: #64748b;">
                                        Utilice los filtros para realizar una búsqueda.
                                    </td>
                                </tr>
                            <?php elseif (empty($empresas)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 2rem; color: #b91c1c; font-weight: 600;">
                                        No se encontraron empresas con los criterios seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($empresas as $emp): ?>
                                    <tr>
                                        <td>
                                            <a href="ficha_empresa.php?id=<?= $emp['id'] ?>" style="color: var(--label-blue); text-decoration: none; font-weight: 700;">
                                                <?= htmlspecialchars($emp['nombre']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($emp['localidad'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($emp['cp'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($emp['provincia'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($emp['telefono'] ?? '') ?></td>
                                        <td>
                                            <?php if (!empty($emp['email'])): ?>
                                                <a href="mailto:<?= $emp['email'] ?>" style="color: var(--label-blue); text-decoration: none;">
                                                    <?= htmlspecialchars($emp['email']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($emp['rlt'] ?? '---') ?></td>
                                        <td style="text-align: center;">
                                            <?= $emp['es_adhesion'] ? 'SÍ' : 'NO' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="<?= $back_url ?>" class="btn-volver" style="text-decoration: none; display: inline-block;">Volver</a>
            </div>

        </main>
    </div>
</body>
</html>
