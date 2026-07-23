<?php
// buscar_empresas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL, ROLE_LECTURA])) {
    header("Location: dashboard.php");
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
    $page_title_prefix = 'EMPRESAS';
    $back_url = 'comerciales_empresas.php';
} else {
    $page_title_prefix = 'EMPRESAS';
    $back_url = 'formacion_bonificada.php';
}

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
    }
    if (!empty($_GET['cif'])) {
        $sql .= " AND e.cif LIKE ?";
        $params[] = "%" . $_GET['cif'] . "%";
    }
    if (!empty($_GET['telefono'])) {
        $sql .= " AND (e.telefono LIKE ? OR e.contacto_telefono LIKE ?)";
        $params[] = "%" . $_GET['telefono'] . "%";
        $params[] = "%" . $_GET['telefono'] . "%";
    }
    if (!empty($_GET['email'])) {
        $sql .= " AND e.email LIKE ?";
        $params[] = "%" . $_GET['email'] . "%";
    }
    if (!empty($_GET['localidad'])) {
        $sql .= " AND e.localidad LIKE ?";
        $params[] = "%" . $_GET['localidad'] . "%";
    }
    if (!empty($_GET['cp'])) {
        $sql .= " AND e.cp LIKE ?";
        $params[] = "%" . $_GET['cp'] . "%";
    }
    if (!empty($_GET['actividad'])) {
        $sql .= " AND e.actividad LIKE ?";
        $params[] = "%" . $_GET['actividad'] . "%";
    }
    
    // Selects SI/NO (TINYINT 1/0)
    if (!empty($_GET['adhesion']) && $_GET['adhesion'] !== '---') {
        $sql .= " AND e.es_adhesion = ?";
        $params[] = ($_GET['adhesion'] == 'SI' ? 1 : 0);
    }
    if (!empty($_GET['gestora']) && $_GET['gestora'] !== '---') {
        $sql .= " AND e.es_gestora = ?";
        $params[] = ($_GET['gestora'] == 'SI' ? 1 : 0);
    }
    if (!empty($_GET['mercadolid']) && $_GET['mercadolid'] !== '---') {
        $sql .= " AND e.es_mercadolid = ?";
        $params[] = ($_GET['mercadolid'] == 'SI' ? 1 : 0);
    }

    if (!empty($_GET['comercial_id'])) {
        $sql .= " AND e.comercial_id = ?";
        $params[] = $_GET['comercial_id'];
    }
    if (!empty($_GET['provincia'])) {
        $sql .= " AND e.provincia = ?";
        $params[] = $_GET['provincia'];
    }
    if (!empty($_GET['sector'])) {
        $sql .= " AND e.sector = ?";
        $params[] = $_GET['sector'];
    }

    // Ejecutar sólo si se pulsó el botón Buscar
    if (isset($_GET['buscar'])) {
        $searchPerformed = true;
        $sql .= " ORDER BY e.nombre ASC LIMIT 300";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $empresas = $stmt->fetchAll();
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
        /* ===== BUSCAR EMPRESAS PREMIUM STYLES ===== */
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
                <a href="ficha_empresa.php?new=1" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    Nueva Empresa
                </a>
            </div>
            
            <div style="padding: 0 2rem 2rem;">
                <!-- PANEL DE BÚSQUEDA -->
                <div class="search-card-premium">
                    <div class="card-header-premium">
                        <h2><?= $page_title_prefix ?> - Filtros de Búsqueda</h2>
                    </div>
                    <form method="GET" style="margin:0;">
                        <input type="hidden" name="buscar" value="1">
                        <?php if ($is_subvencionada): ?><input type="hidden" name="context" value="subvencionada"><?php endif; ?>
                        
                        <div class="form-grid">
                            
                            <!-- IDENTIFICACIÓN Y CONTACTO -->
                            <div class="section-header-custom" style="margin-top: 0;">Identificación y Contacto</div>
                            
                            <div class="form-group-custom span-4">
                                <label>Razón Social / Nombre:</label>
                                <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-2">
                                <label>CIF:</label>
                                <input type="text" name="cif" value="<?= htmlspecialchars($_GET['cif'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-3">
                                <label>Teléfono:</label>
                                <input type="text" name="telefono" value="<?= htmlspecialchars($_GET['telefono'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-3">
                                <label>E-mail:</label>
                                <input type="text" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
                            </div>

                            <!-- UBICACIÓN Y ACTIVIDAD -->
                            <div class="section-header-custom">Ubicación y Actividad</div>
                            
                            <div class="form-group-custom span-4">
                                <label>Localidad:</label>
                                <input type="text" name="localidad" value="<?= htmlspecialchars($_GET['localidad'] ?? '') ?>">
                            </div>
                            <div class="form-group-custom span-2">
                                <label>Código Postal (C.P.):</label>
                                <input type="text" name="cp" value="<?= htmlspecialchars($_GET['cp'] ?? '') ?>">
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
                            <div class="form-group-custom span-3">
                                <label>Actividad:</label>
                                <input type="text" name="actividad" value="<?= htmlspecialchars($_GET['actividad'] ?? '') ?>">
                            </div>

                            <!-- FILTROS Y ASIGNACIONES -->
                            <div class="section-header-custom">Filtros y Asignaciones</div>

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
                            <div class="form-group-custom span-8">
                                <label>Sector:</label>
                                <select name="sector">
                                    <option value="">--- Todos ---</option>
                                    <?php foreach($sectores as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= ($_GET['sector'] ?? '') == $s ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group-custom span-3">
                                <label>Con Cursos:</label>
                                <select name="con_cursos">
                                    <option value="---">--- Todos ---</option>
                                    <option value="SI" <?= ($_GET['con_cursos'] ?? '') == 'SI' ? 'selected' : '' ?>>SÍ</option>
                                    <option value="NO" <?= ($_GET['con_cursos'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                                </select>
                            </div>
                            <div class="form-group-custom span-3">
                                <label>Es Adhesión:</label>
                                <select name="adhesion">
                                    <option value="---">--- Todos ---</option>
                                    <option value="SI" <?= ($_GET['adhesion'] ?? '') == 'SI' ? 'selected' : '' ?>>SÍ</option>
                                    <option value="NO" <?= ($_GET['adhesion'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                                </select>
                            </div>
                            <div class="form-group-custom span-3">
                                <label>Es Gestora:</label>
                                <select name="gestora">
                                    <option value="---">--- Todos ---</option>
                                    <option value="SI" <?= ($_GET['gestora'] ?? '') == 'SI' ? 'selected' : '' ?>>SÍ</option>
                                    <option value="NO" <?= ($_GET['gestora'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                                </select>
                            </div>
                            <div class="form-group-custom span-3">
                                <label>De Mercadolid:</label>
                                <select name="mercadolid">
                                    <option value="---">--- Todos ---</option>
                                    <option value="SI" <?= ($_GET['mercadolid'] ?? '') == 'SI' ? 'selected' : '' ?>>SÍ</option>
                                    <option value="NO" <?= ($_GET['mercadolid'] ?? '') == 'NO' ? 'selected' : '' ?>>NO</option>
                                </select>
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
                                    <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Razón social</th>
                                    <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Localidad</th>
                                    <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>CP</th>
                                    <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Provincia</th>
                                    <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Teléfono</th>
                                    <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Email</th>
                                    <th><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>RLT</th>
                                    <th style="text-align: center;"><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>Adhesión</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$searchPerformed): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted); font-weight: 500;">
                                            Utilice los filtros para realizar una búsqueda.
                                        </td>
                                    </tr>
                                <?php elseif (empty($empresas)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 2.5rem; color: #b91c1c; font-weight: 700;">
                                            ⚠️ No se encontraron empresas con los criterios seleccionados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($empresas as $emp): ?>
                                        <tr>
                                            <td style="font-weight: 700;">
                                                <a href="ficha_empresa.php?id=<?= $emp['id'] ?>" style="color: var(--primary-color); text-decoration: none;">
                                                    <?= htmlspecialchars($emp['nombre']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($emp['localidad'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($emp['cp'] ?? '—') ?></td>
                                            <td style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);"><?= htmlspecialchars($emp['provincia'] ?? '—') ?></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($emp['telefono'] ?? '—') ?></td>
                                            <td>
                                                <?php if (!empty($emp['email'])): ?>
                                                    <a href="mailto:<?= $emp['email'] ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                                                        <?= htmlspecialchars($emp['email']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($emp['rlt'] ?? '—') ?></td>
                                            <td style="text-align: center;">
                                                <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 700; 
                                                    <?= $emp['es_adhesion'] 
                                                        ? 'background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;' 
                                                        : 'background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569;' 
                                                    ?>">
                                                    <?= $emp['es_adhesion'] ? 'SÍ' : 'NO' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 1rem; margin-bottom: 2rem;">
                    <a href="<?= $back_url ?>" class="btn btn-glass" style="border: 1px solid var(--border-color);">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Volver al Panel Principal
                    </a>
                </div>
            </div>

        </main>
    </div>
</body>
</html>
