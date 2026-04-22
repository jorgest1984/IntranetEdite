<?php
// buscar_alumnos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$active_tab = 'search';
$error = '';
$success = '';

$is_subvencionada = (isset($_GET['context']) && $_GET['context'] === 'subvencionada');
$page_title_prefix = $is_subvencionada ? 'FORMACIÓN SUBVENCIONADA' : 'ALUMNOS';
$back_url = $is_subvencionada ? 'formacion_subvencionada.php' : 'formacion_bonificada.php';

// Listas para dropdowns
$provincias = [
    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
];

$estados = [
    "Abandono", "Admitido", "Baja", "Baja por colocación", "Empleado en curso", "Espera", "Finalizado", "Finalizado sobrante", "Inscrito", "Pendiente docu", "Pendiente estado", "Pendiente otro curso", "Pendiente validacion", "Preinscrito", "Reserva"
];

$colectivos = [
    "Cuidadores no profesionales de las personas en situación de dependencia", "Empleado hogar", "ERE (Art. 51 y 52 del Estatuto de los Trabajadores)", "ERTE (Art. 47 del Estatuto de los Trabajadores)", "Fijos discontinuos en periodo de no ocupación", "Mutualistas de Colegios Profesionales no incluidos como autónomos", "Persona actualmente desempleada que anteriormente ha estado en situación de ERTE.", "Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE.", "Régimen especial agrario por cuenta ajena", "Régimen especial agrario por cuenta propia", "Régimen especial autónomos", "Régimen general", "Regulación de empleo en periodos de no ocupación", "Trabajador con contrato a tiempo parcial", "Trabajador con contrato temporal", "Trabajadores a tiempo parcial de carácter indefinido con trabajos discontinuos en sus periodos de no ocupación", "Trabajadores con convenio especial con la Seguridad Social", "Trabajadores con relaciones laborales de carácter especial que se recogen en el art.2 del Estatuto de los Trabajadores", "Trabajadores incluidos en el Régimen especial del mar", "Trabajadores no ocupados inscritos como demandantes de empleo en los servicios públicos de empleo", "administración pública"
];

// LÓGICA DE BÚSQUEDA
$alumnos = [];
$searchPerformed = false;

try {
    $sql = "SELECT DISTINCT a.* FROM alumnos a 
            LEFT JOIN matriculas m ON a.id = m.alumno_id
            WHERE 1=1";
    $params = [];

    // Filtros del Formulario
    if (!empty($_GET['id'])) {
        $sql .= " AND a.id = ?";
        $params[] = $_GET['id'];
        $searchPerformed = true;
    }
    if (!empty($_GET['nombre'])) {
        $sql .= " AND a.nombre LIKE ?";
        $params[] = "%" . $_GET['nombre'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['apellidos'])) {
        $sql .= " AND (a.primer_apellido LIKE ? OR a.segundo_apellido LIKE ?)";
        $params[] = "%" . $_GET['apellidos'] . "%";
        $params[] = "%" . $_GET['apellidos'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['cod_grupo'])) {
        $sql .= " AND a.cod_grupo LIKE ?";
        $params[] = "%" . $_GET['cod_grupo'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['estado'])) {
        $sql .= " AND a.estado = ?";
        $params[] = $_GET['estado'];
        $searchPerformed = true;
    }
    if (!empty($_GET['comercial_id'])) {
        $sql .= " AND a.comercial_id = ?";
        $params[] = $_GET['comercial_id'];
        $searchPerformed = true;
    }
    if (!empty($_GET['empresa'])) {
        $sql .= " AND a.centro_trabajo LIKE ?";
        $params[] = "%" . $_GET['empresa'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['colectivo'])) {
        $sql .= " AND a.colectivo = ?";
        $params[] = $_GET['colectivo'];
        $searchPerformed = true;
    }
    if (!empty($_GET['dni'])) {
        $sql .= " AND a.dni LIKE ?";
        $params[] = "%" . $_GET['dni'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['telefono'])) {
        $sql .= " AND (a.telefono LIKE ? OR a.telefono_empresa LIKE ?)";
        $params[] = "%" . $_GET['telefono'] . "%";
        $params[] = "%" . $_GET['telefono'] . "%";
        $searchPerformed = true;
    }
    if (!empty($_GET['provincia'])) {
        $sql .= " AND a.provincia = ?";
        $params[] = $_GET['provincia'];
        $searchPerformed = true;
    }
    if (!empty($_GET['email'])) {
        $sql .= " AND (a.email LIKE ? OR a.email_personal LIKE ?)";
        $params[] = "%" . $_GET['email'] . "%";
        $params[] = "%" . $_GET['email'] . "%";
        $searchPerformed = true;
    }
    
    // Filtros de Convocatoria y Plan (a través de matrículas)
    if (!empty($_GET['convocatoria_id']) && $_GET['convocatoria_id'] !== 'Todas') {
        $sql .= " AND m.convocatoria_id = ?";
        $params[] = $_GET['convocatoria_id'];
        $searchPerformed = true;
    }
    if (!empty($_GET['plan_id']) && $_GET['plan_id'] !== '') {
        // Asumiendo que matrículas tiene plan_id o se llega a través de convocatoria
        // Por consistencia con la UI actual de buscar_alumnos.php
        $searchPerformed = true;
    }

    // Checkboxes especiales
    if (!empty($_GET['incluir_email_personal'])) {
        $sql .= " AND a.email_personal IS NOT NULL AND a.email_personal <> ''";
        $searchPerformed = true;
    }
    if (!empty($_GET['solo_bajas'])) {
        $sql .= " AND a.baja = 1";
        $searchPerformed = true;
    }

    if ($searchPerformed) {
        $sql .= " ORDER BY a.primer_apellido ASC, a.nombre ASC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alumnos = $stmt->fetchAll();
    }

} catch (Exception $e) {
    $error = "Error en la búsqueda: " . $e->getMessage();
}

// Cargar listas dinámicas para filtros
try {
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC LIMIT 50")->fetchAll();
    $planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC LIMIT 50")->fetchAll();
    $comerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll();
    $centros_db = $pdo->query("SELECT DISTINCT centro_trabajo FROM alumnos WHERE centro_trabajo IS NOT NULL AND centro_trabajo != '' ORDER BY centro_trabajo ASC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_subvencionada ? 'Formación Subvencionada' : 'Buscador de Alumnos' ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
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
        }

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
            min-width: 1100px;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-custom th {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            padding: 8px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 700;
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
                            <label>Cod. Alumno:</label>
                            <input type="text" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>" class="form-control" style="width: 80px;">
                        </div>
                        <div class="form-group">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control" style="width: 200px;">
                        </div>
                        <div class="form-group">
                            <label>Apellidos:</label>
                            <input type="text" name="apellidos" value="<?= htmlspecialchars($_GET['apellidos'] ?? '') ?>" class="form-control" style="width: 300px;">
                        </div>
                        <div class="form-group">
                            <label>Cod. Grupo:</label>
                            <input type="text" name="cod_grupo" value="<?= htmlspecialchars($_GET['cod_grupo'] ?? '') ?>" class="form-control" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado" class="form-control" style="width: 160px;">
                                <option value="">---</option>
                                <?php foreach($estados as $est): ?>
                                    <option value="<?= $est ?>" <?= ($_GET['estado'] ?? '') == $est ? 'selected' : '' ?>><?= $est ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Comercial:</label>
                            <select name="comercial_id" class="form-control" style="width: 280px;">
                                <option value="">---</option>
                                <?php foreach($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($_GET['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Empresa:</label>
                            <input type="text" name="empresa" value="<?= htmlspecialchars($_GET['empresa'] ?? '') ?>" class="form-control" list="centros_list" style="width: 250px;">
                            <datalist id="centros_list">
                                <?php foreach($centros_db as $cent): ?><option value="<?= htmlspecialchars($cent) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Colectivo:</label>
                            <select name="colectivo" class="form-control" style="width: 220px;">
                                <option value="">---</option>
                                <?php foreach($colectivos as $col): ?>
                                    <option value="<?= $col ?>" <?= ($_GET['colectivo'] ?? '') == $col ? 'selected' : '' ?>><?= $col ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>NIF:</label>
                            <input type="text" name="dni" value="<?= htmlspecialchars($_GET['dni'] ?? '') ?>" class="form-control" style="width: 120px;">
                        </div>
                        <div class="form-group">
                            <label>Tlfno/móvil:</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($_GET['telefono'] ?? '') ?>" class="form-control" style="width: 120px;">
                        </div>
                        <div class="form-group">
                            <label>Provincia:</label>
                            <select name="provincia" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach($provincias as $prov): ?>
                                    <option value="<?= mb_strtoupper($prov, 'UTF-8') ?>" <?= ($_GET['provincia'] ?? '') == mb_strtoupper($prov, 'UTF-8') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>E-mail:</label>
                            <input type="text" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" class="form-control" style="width: 200px;">
                        </div>
                    </div>

                    <!-- Fila 4 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Convocatoria:</label>
                            <select name="convocatoria_id" class="form-control" style="width: 100px;">
                                <option value="Todas">Todas</option>
                                <?php foreach($convocatorias as $conv): ?>
                                    <option value="<?= $conv['id'] ?>" <?= ($_GET['convocatoria_id'] ?? '') == $conv['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($conv['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plan:</label>
                            <select name="plan_id" class="form-control" style="width: 500px;">
                                <option value="">------------ Todos los planes ------------</option>
                                <?php foreach($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($_GET['plan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="gap: 15px; margin-left: 10px;">
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: var(--label-blue); font-weight: 700; font-size: 0.75rem;">
                                <input type="checkbox" name="incluir_email_personal" <?= isset($_GET['incluir_email_personal']) ? 'checked' : '' ?>>
                                Incluir contactos
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: var(--label-blue); font-weight: 700; font-size: 0.75rem;">
                                <input type="checkbox" name="solo_bajas" <?= isset($_GET['solo_bajas']) ? 'checked' : '' ?>>
                                Solo bajas
                            </label>
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
                    <div style="font-size: 0.65rem; display: flex; align-items: center; gap: 5px; margin-bottom: 5px; color: var(--label-blue); font-weight: 700;">
                        <input type="checkbox" name="multiple_sort"> Ordenar múltiple
                    </div>
                    <h2>RESULTADO DE LA BÚSQUEDA</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Nombre</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Apellidos</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>NIF</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Provincia</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Empresa</th>
                                <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>E-mail</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$searchPerformed): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                        Utilice los filtros para realizar una búsqueda.
                                    </td>
                                </tr>
                            <?php elseif (empty($alumnos)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem; color: #b91c1c; font-weight: 600;">
                                        No se encontraron alumnos con los criterios seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alumnos as $al): ?>
                                    <tr>
                                        <td>
                                            <a href="ficha_alumno.php?id=<?= $al['id'] ?>" style="color: var(--label-blue); text-decoration: none; font-weight: 700;">
                                                <?= htmlspecialchars($al['nombre'] ?? '') ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars(($al['primer_apellido'] ?? '') . ' ' . ($al['segundo_apellido'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($al['dni'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($al['provincia'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($al['centro_trabajo'] ?: '---') ?></td>
                                        <td><?= htmlspecialchars($al['email'] ?? '') ?></td>
                                        <td style="text-align: center;">
                                            <a href="ficha_alumno.php?id=<?= $al['id'] ?>" class="btn-print" style="text-decoration: none; padding: 2px 8px; font-weight: 700;">
                                                VER FICHA
                                            </a>
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
