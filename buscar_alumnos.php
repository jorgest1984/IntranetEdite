<?php
// buscar_alumnos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COMERCIAL, ROLE_COORD])) {
    header("Location: home.php");
    exit();
}

$active_tab = 'search';
$error = '';
$success = '';

$is_subvencionada = (isset($_GET['context']) && $_GET['context'] === 'subvencionada');
$is_comercial = (isset($_GET['context']) && $_GET['context'] === 'comercial');

if ($is_subvencionada) {
    $page_title_prefix = 'FORMACIÓN SUBVENCIONADA';
    $back_url = 'formacion_subvencionada.php';
} elseif ($is_comercial) {
    $page_title_prefix = 'ALUMNOS';
    $back_url = 'comerciales_trabajadores.php';
} else {
    $page_title_prefix = 'ALUMNOS';
    $back_url = 'formacion_bonificada.php';
}

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
        /* ===== BUSCAR ALUMNOS PREMIUM STYLES ===== */
        .main-content { padding: 2rem; }

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
            padding: 1rem;
            text-align: center;
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

        .section-header-custom {
            grid-column: span 12;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            border-left: 4px solid var(--primary-color);
            padding-left: 10px;
            letter-spacing: 0.5px;
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
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-header-premium h2 {
            margin: 0;
            font-size: 0.85rem;
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
            padding: 0.75rem 1rem;
            text-align: left;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-premium th svg {
            width: 12px;
            height: 12px;
            vertical-align: middle;
            margin-right: 4px;
            fill: currentColor;
        }

        .table-premium td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .table-premium tr:last-child td {
            border-bottom: none;
        }

        .table-premium tr:hover td {
            background-color: rgba(0, 108, 228, 0.015);
        }

        @media print {
            .sidebar, .main-content > div:first-child, .search-card-premium, .results-header-premium form { display: none !important; }
            .results-section-premium { border: none; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">

            <!-- Barra de acciones superior -->
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem; padding: 2rem 2rem 0;">
                <?php if (has_permission([ROLE_ADMIN, ROLE_TUTOR])): ?>
                    <a href="alumnos.php" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                        Gestión de Alumnos
                    </a>
                <?php endif; ?>
            </div>
            
            <div style="padding: 0 2rem 2rem;">
                <!-- PANEL DE BÚSQUEDA -->
                <div class="search-card-premium">
                    <div class="card-header-premium">
                        <h2><?= $page_title_prefix ?> - Filtros de Búsqueda</h2>
                    </div>
                    <form method="GET" style="margin:0;">
                        <?php if ($is_subvencionada): ?>
                            <input type="hidden" name="context" value="subvencionada">
                        <?php elseif ($is_comercial): ?>
                            <input type="hidden" name="context" value="comercial">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <!-- IDENTIFICACIÓN Y GRUPO -->
                            <div class="section-header-custom" style="margin-top: 0;">Identificación y Grupo</div>
                            
                            <div class="form-group-custom span-2">
                                <label>Cod. Alumno:</label>
                                <input type="text" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-3">
                                <label>Nombre:</label>
                                <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-4">
                                <label>Apellidos:</label>
                                <input type="text" name="apellidos" value="<?= htmlspecialchars($_GET['apellidos'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-3">
                                <label>NIF:</label>
                                <input type="text" name="dni" value="<?= htmlspecialchars($_GET['dni'] ?? '') ?>">
                            </div>

                            <!-- CONTACTO E INFORMACIÓN -->
                            <div class="section-header-custom">Contacto e Información</div>
                            
                            <div class="form-group-custom span-3">
                                <label>Tlfno/móvil:</label>
                                <input type="text" name="telefono" value="<?= htmlspecialchars($_GET['telefono'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-4">
                                <label>E-mail:</label>
                                <input type="text" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-3">
                                <label>Provincia:</label>
                                <select name="provincia">
                                    <option value="">--- Todas ---</option>
                                    <?php foreach($provincias as $prov): ?>
                                        <option value="<?= mb_strtoupper($prov, 'UTF-8') ?>" <?= ($_GET['provincia'] ?? '') == mb_strtoupper($prov, 'UTF-8') ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prov) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-custom span-2">
                                <label>Cod. Grupo:</label>
                                <input type="text" name="cod_grupo" value="<?= htmlspecialchars($_GET['cod_grupo'] ?? '') ?>">
                            </div>

                            <!-- FILTROS Y ASIGNACIONES -->
                            <div class="section-header-custom">Filtros y Asignaciones</div>

                            <div class="form-group-custom span-3">
                                <label>Estado:</label>
                                <select name="estado">
                                    <option value="">--- Todos ---</option>
                                    <?php foreach($estados as $est): ?>
                                        <option value="<?= $est ?>" <?= ($_GET['estado'] ?? '') == $est ? 'selected' : '' ?>><?= $est ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-custom span-4">
                                <label>Comercial:</label>
                                <select name="comercial_id">
                                    <option value="">--- Todos ---</option>
                                    <?php foreach($comerciales as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($_GET['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-custom span-5">
                                <label>Empresa:</label>
                                <input type="text" name="empresa" value="<?= htmlspecialchars($_GET['empresa'] ?? '') ?>" list="centros_list">
                                <datalist id="centros_list">
                                    <?php foreach($centros_db as $cent): ?><option value="<?= htmlspecialchars($cent) ?>"><?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="form-group-custom span-4">
                                <label>Colectivo:</label>
                                <select name="colectivo">
                                    <option value="">--- Todos ---</option>
                                    <?php foreach($colectivos as $col): ?>
                                        <option value="<?= $col ?>" <?= ($_GET['colectivo'] ?? '') == $col ? 'selected' : '' ?>><?= $col ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-custom span-2">
                                <label>Convocatoria:</label>
                                <select name="convocatoria_id">
                                    <option value="Todas">Todas</option>
                                    <?php foreach($convocatorias as $conv): ?>
                                        <option value="<?= $conv['id'] ?>" <?= ($_GET['convocatoria_id'] ?? '') == $conv['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($conv['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-custom span-6">
                                <label>Plan:</label>
                                <select name="plan_id">
                                    <option value="">------------ Todos los planes ------------</option>
                                    <?php foreach($planes as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= ($_GET['plan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Checkboxes -->
                            <div class="form-group-custom span-12" style="flex-direction: row; gap: 20px; align-items: center; margin-top: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-color); font-weight: 600; font-size: 0.8rem; text-transform: none; letter-spacing: normal;">
                                    <input type="checkbox" name="incluir_email_personal" <?= isset($_GET['incluir_email_personal']) ? 'checked' : '' ?> style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary-color);">
                                    Incluir contactos
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-color); font-weight: 600; font-size: 0.8rem; text-transform: none; letter-spacing: normal;">
                                    <input type="checkbox" name="solo_bajas" <?= isset($_GET['solo_bajas']) ? 'checked' : '' ?> style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary-color);">
                                    Solo bajas
                                </label>
                            </div>
                        </div>

                        <!-- ACCIONES DE BÚSQUEDA -->
                        <div style="padding: 1.5rem 2rem; background: rgba(0, 108, 228, 0.03); border-top: 1px solid var(--border-color); display: flex; justify-content: center; gap: 1rem; align-items: center;">
                            <button type="submit" class="btn btn-primary">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align: middle;"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                Buscar
                            </button>
                            <button type="button" class="btn btn-glass" onclick="window.print()" style="border: 1px solid var(--border-color);">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align: middle;"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                                Imprimir
                            </button>
                        </div>
                    </form>
                </div>

                <!-- RESULTADOS -->
                <div class="results-section-premium">
                    <div class="results-header-premium">
                        <h2>Resultado de la Búsqueda</h2>
                        <label style="font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; color: var(--text-color); cursor: pointer; font-weight: 600; margin: 0;">
                            <input type="checkbox" name="multiple_sort" style="width: 15px; height: 15px; cursor: pointer; accent-color: var(--primary-color);"> Ordenar Múltiple
                        </label>
                    </div>
                    
                    <div style="padding: 1.5rem; overflow-x: auto;">
                        <table class="table-premium">
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
                                        <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted); font-weight: 500;">
                                            Utilice los filtros para realizar una búsqueda.
                                        </td>
                                    </tr>
                                <?php elseif (empty($alumnos)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 2.5rem; color: #b91c1c; font-weight: 700;">
                                            ⚠️ No se encontraron alumnos con los criterios seleccionados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alumnos as $al): ?>
                                        <tr>
                                            <td style="font-weight: 700;">
                                                <a href="ficha_alumno.php?id=<?= $al['id'] ?>" style="color: var(--primary-color); text-decoration: none;">
                                                    <?= htmlspecialchars($al['nombre'] ?? '') ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars(($al['primer_apellido'] ?? '') . ' ' . ($al['segundo_apellido'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars($al['dni'] ?? '') ?></td>
                                            <td style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);"><?= htmlspecialchars($al['provincia'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($al['centro_trabajo'] ?: '—') ?></td>
                                            <td>
                                                <?php if (!empty($al['email'])): ?>
                                                    <a href="mailto:<?= $al['email'] ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                                                        <?= htmlspecialchars($al['email']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <a href="ficha_alumno.php?id=<?= $al['id'] ?>" class="btn btn-glass" style="padding: 4px 10px; font-size: 0.72rem; border: 1px solid var(--border-color); display: inline-flex; align-items: center; gap: 4px; font-weight: 700; text-decoration: none;">
                                                    Ver Ficha
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 1rem; margin-bottom: 2rem;">
                    <a href="<?= $back_url ?>" class="btn btn-glass" style="border: 1px solid var(--border-color); text-decoration: none; display: inline-block;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Volver al Panel Principal
                    </a>
                </div>
            </div>

        </main>
    </div>
</body>
</html>
