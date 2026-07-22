<?php
// inscripciones.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COMERCIAL])) {
    header("Location: home.php");
    exit();
}

$active_tab = 'search';
$error = '';
$success = '';

// Listas para dropdowns (placeholder para la integración futura con DB)
$provincias = [
    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
];
$comerciales = [];
$tutores = [];
$convocatorias = [];
$planes = [];

try {
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC LIMIT 50")->fetchAll();
    
    // Obtener Comerciales (Rol 'Comercial' o buscando por nombre de rol similar)
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales->fetchAll();

    // Obtener Tutores (Rol 'Formador' o 'Tutor')
    $stmtTutores = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%') AND u.activo = 1 ORDER BY u.nombre ASC");
    $tutores = $stmtTutores->fetchAll();

    // Obtener Centros (Tabla empresas)
    $stmtEmpresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    $centros_db = $stmtEmpresas->fetchAll();

} catch (Exception $e) {}

$current_page = 'inscripciones.php';

// --- LOGICA DE BÚSQUEDA ---
$resultados = [];
$buscando = false;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && (isset($_GET['curso']) || isset($_GET['convocatoria']) || isset($_GET['dni']))) {
    $buscando = true;
    $params = [];
    $where = ["1=1"];

    if (!empty($_GET['curso'])) {
        $where[] = "p.nombre LIKE ?";
        $params[] = "%" . $_GET['curso'] . "%";
    }
    if (!empty($_GET['cod_grupo'])) {
        $where[] = "m.id LIKE ?"; // Asumiendo que el id de matricula o algun campo cod_grupo existe
        $params[] = "%" . $_GET['cod_grupo'] . "%";
    }
    if (!empty($_GET['convocatoria']) && $_GET['convocatoria'] !== 'Todas') {
        $where[] = "m.convocatoria_id = ?";
        $params[] = $_GET['convocatoria'];
    }
    if (!empty($_GET['estado'])) {
        $where[] = "m.estado = ?";
        $params[] = $_GET['estado'];
    }
    if (!empty($_GET['provincia'])) {
        $where[] = "a.provincia LIKE ?";
        $params[] = "%" . $_GET['provincia'] . "%";
    }

    $sql = "SELECT m.*, a.nombre as alumno_nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.provincia, a.pref_presencial,
                   c.nombre as convocatoria_nombre,
                   (SELECT pl.nombre FROM planes pl WHERE pl.convocatoria_id = c.id ORDER BY pl.id ASC LIMIT 1) as plan_nombre,
                   e.nombre as empresa_nombre, e.sector as empresa_sector,
                   g.numero_grupo, g.codigo_plataforma as grupo_cod, g.fecha_inicio as grupo_inicio, g.fecha_mitad as grupo_mitad, g.fecha_fin as grupo_fin,
                   af.abreviatura as af_abreviatura, af.prioridad as af_prioridad, cu.nombre_corto as curso_nombre,
                   u_com.nombre as comercial_nombre, u_com.apellidos as comercial_apellidos,
                   COALESCE(af.modalidad, g.modalidad) as modalidad_real
            FROM matriculas m
            INNER JOIN alumnos a ON m.alumno_id = a.id
            LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
            LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
            LEFT JOIN grupos g ON m.grupo_id = g.id
            LEFT JOIN acciones_formativas af ON g.accion_id = af.id
            LEFT JOIN cursos cu ON af.curso_id = cu.id
            LEFT JOIN usuarios u_com ON m.comercial_id = u_com.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY m.id DESC
            LIMIT 500";


    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "Error en la búsqueda: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripciones - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ===== INSCRIPCIONES PREMIUM STYLES ===== */

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

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 0.8rem 1rem;
            padding: 1.5rem 2rem;
        }

        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group-custom.span-12 { grid-column: span 12; }
        .form-group-custom.span-10 { grid-column: span 10; }
        .form-group-custom.span-8  { grid-column: span 8; }
        .form-group-custom.span-6  { grid-column: span 6; }
        .form-group-custom.span-5  { grid-column: span 5; }
        .form-group-custom.span-4  { grid-column: span 4; }
        .form-group-custom.span-3  { grid-column: span 3; }
        .form-group-custom.span-2  { grid-column: span 2; }
        .form-group-custom.span-1  { grid-column: span 1; }

        .form-group-custom label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control-edit {
            padding: 0.45rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 7px;
            font-size: 0.8rem;
            background: var(--input-bg);
            color: var(--text-color);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
            box-sizing: border-box;
            height: 36px;
        }

        .form-control-edit:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.1);
        }

        /* Results */
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

        .results-count {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 2000px;
        }

        .table-premium th {
            background: rgba(0, 108, 228, 0.04);
            border-bottom: 2px solid var(--border-color);
            padding: 0.85rem 0.75rem;
            text-align: left;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table-premium td {
            padding: 0.75rem 0.75rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
            white-space: nowrap;
        }

        .table-premium tr:last-child td { border-bottom: none; }
        .table-premium tr:hover td { background-color: rgba(0, 108, 228, 0.015); }

        /* Status badges */
        .badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: inline-flex;
            align-items: center;
        }
        .badge-admitido  { background: rgba(22, 163, 74, 0.1);  color: #16a34a; }
        .badge-inscrito  { background: rgba(37, 99, 235, 0.1);  color: #2563eb; }
        .badge-espera    { background: rgba(202, 138, 4, 0.1);  color: #ca8a04; }
        .badge-baja      { background: rgba(239, 68, 68, 0.1);  color: #ef4444; }
        .badge-finalizado{ background: rgba(100, 116, 139, 0.1);color: #64748b; }
        .badge-default   { background: rgba(100, 116, 139, 0.08);color: var(--text-muted); }

        /* Action button */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 8px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            font-size: 0.72rem;
            font-weight: 700;
            transition: all 0.2s;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.15);
            border-color: var(--primary-color);
        }

        /* Blue + Red nav buttons */
        .btn-blue {
            background-color: rgba(0, 108, 228, 0.08) !important;
            color: var(--primary-color) !important;
            border: 1px solid rgba(0, 108, 228, 0.15) !important;
            box-shadow: 0 4px 12px 0 rgba(0, 108, 228, 0.05);
        }
        .btn-blue:hover {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px 0 rgba(0, 108, 228, 0.3);
        }

        .btn-red {
            background-color: rgba(239, 68, 68, 0.08) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.15) !important;
            box-shadow: 0 4px 12px 0 rgba(239, 68, 68, 0.05);
        }
        .btn-red:hover {
            background-color: #ef4444 !important;
            color: white !important;
            border-color: #ef4444 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px 0 rgba(239, 68, 68, 0.3);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid { padding: 1.5rem !important; gap: 1rem !important; }
            .form-group-custom { grid-column: span 6 !important; }
            .form-group-custom.span-12 { grid-column: span 12 !important; }
        }
        @media (max-width: 768px) {
            .form-group-custom { grid-column: span 12 !important; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">

            <!-- PAGE HEADER -->
            <header class="page-header">
                <div class="page-title">
                    <h1>Inscripciones</h1>
                    <p>Búsqueda y gestión de inscripciones de alumnos</p>
                </div>
                <div class="page-actions" style="display: flex; gap: 12px;">
                    <a href="home.php" class="btn btn-blue" style="font-weight: 700; text-decoration: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        Inicio
                    </a>
                </div>
            </header>

            <!-- BUSCADOR -->
            <div class="search-card-premium">
                <div class="card-header-premium">
                    <h2>Inscripciones &mdash; Filtros de Búsqueda</h2>
                </div>
                <form method="GET">
                    
                    <!-- Fila 1: Curso, Código grupo, Comercial, Tutor, Sin grupo -->
                    <div class="form-grid">
                        <div class="form-group-custom span-3">
                            <label>Curso</label>
                            <input type="text" name="curso" class="form-control-edit" placeholder="Nombre del curso...">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Código Grupo</label>
                            <input type="text" name="cod_grupo" class="form-control-edit" placeholder="Ej: G1">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Comercial</label>
                            <select name="comercial" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <?php foreach ($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Tutor</label>
                            <select name="tutor" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-1" style="justify-content: flex-end; padding-top: 1.6rem;">
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.72rem; white-space:nowrap;">
                                <input type="checkbox" name="sin_grupo"> Sin grupo
                            </label>
                        </div>

                        <!-- Fila 2: Fechas inscripción, Provincia, Motivo No-Admisión -->
                        <div class="form-group-custom span-3">
                            <label>Fecha Insc. Desde</label>
                            <input type="date" name="fecha_desde" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Fecha Insc. Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Provincia</label>
                            <input type="text" name="provincia" class="form-control-edit" list="provincias_list" placeholder="Escriba provincia...">
                            <datalist id="provincias_list">
                                <?php foreach($provincias as $prov): ?><option value="<?= mb_strtoupper($prov, 'UTF-8') ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Motivo No-Admisión</label>
                            <select name="motivo_rechazo" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <option value="Acción agotada">Acción agotada</option>
                                <option value="Acción no programable">Acción no programable</option>
                                <option value="Falta doc">Falta doc</option>
                                <option value="Falta grupo">Falta grupo</option>
                                <option value="Falta tutor">Falta tutor</option>
                                <option value="Otro curso">Otro curso</option>
                                <option value="Plan agotado">Plan agotado</option>
                                <option value="Sector NO Correcto">Sector NO Correcto</option>
                                <option value="Tutor completo">Tutor completo</option>
                            </select>
                        </div>

                        <!-- Fila 3: Solicitante, Sexo, Colectivo, No válido, Mayor 45 -->
                        <div class="form-group-custom span-3">
                            <label>Solicitante</label>
                            <select name="solicitante" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <option value="COMFIA">COMFIA</option>
                                <option value="FED. COM. Y TTE. CCOO MADRID">FED. COM. Y TTE. CCOO MADRID</option>
                                <option value="UGT DE CATALUNYA">UGT DE CATALUNYA</option>
                                <option value="UGT Madrid">UGT Madrid</option>
                                <option value="FETCM-UGT">FETCM-UGT</option>
                                <option value="FETE-UGT">FETE-UGT</option>
                                <option value="FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA">FED. NACIONAL DE DETALLISTAS...</option>
                                <option value="MARS">MARS</option>
                                <option value="FITAG">FITAG</option>
                                <option value="Comunidad de Madrid">Comunidad de Madrid</option>
                                <option value="FAECTA">FAECTA</option>
                                <option value="UGT Andalucía">UGT Andalucía</option>
                                <option value="FTFE">FTFE</option>
                                <option value="Criteria">Criteria</option>
                                <option value="FSP-UGT Palencia">FSP-UGT Palencia</option>
                                <option value="JUNTA DE CASTILLA Y LEON">JUNTA DE CASTILLA Y LEON</option>
                                <option value="JUNTA DE ANDALUCIA">JUNTA DE ANDALUCIA</option>
                                <option value="CRUZ ROJA ESPAÑOLA">CRUZ ROJA ESPAÑOLA</option>
                                <option value="MARSDIGITAL S.L.">MARSDIGITAL S.L.</option>
                                <option value="FUNDACIÓN PIQUER">FUNDACIÓN PIQUER</option>
                                <option value="Fed. Estatal de servicios - UGT">Fed. Estatal de servicios - UGT</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Sexo</label>
                            <select name="sexo" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <option value="M">Hombre</option>
                                <option value="F">Mujer</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Colectivo</label>
                            <select name="colectivo" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <option value="Cuidadores no profesionales de las personas en situación de dependencia">Cuidadores no profesionales</option>
                                <option value="Empleado hogar">Empleado hogar</option>
                                <option value="ERE (Art. 51 y 52 del Estatuto de los Trabajadores)">ERE</option>
                                <option value="ERTE (Art. 47 del Estatuto de los Trabajadores)">ERTE</option>
                                <option value="Fijos discontinuos en periodo de no ocupación">Fijos discontinuos</option>
                                <option value="Mutualistas de Colegios Profesionales no incluidos como autónomos">Mutualistas</option>
                                <option value="Persona actualmente desempleada que anteriormente ha estado en situación de ERTE.">Desempleado ex-ERTE</option>
                                <option value="Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE.">Trabajando ex-ERTE</option>
                                <option value="Régimen especial agrario por cuenta ajena">Agrario cuenta ajena</option>
                                <option value="Régimen especial agrario por cuenta propia">Agrario cuenta propia</option>
                                <option value="Régimen especial autónomos">Autónomos</option>
                                <option value="Régimen general">Régimen general</option>
                                <option value="Regulación de empleo en periodos de no ocupación">Regulación empleo</option>
                                <option value="Trabajador con contrato a tiempo parcial">Tiempo parcial</option>
                                <option value="Trabajador con contrato temporal">Contrato temporal</option>
                                <option value="administración pública">Administración pública</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>No válido</label>
                            <select name="no_valido" class="form-control-edit">
                                <option value="">---</option>
                                <option value="S">SÍ</option>
                                <option value="N" selected>NO</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Mayor 45</label>
                            <select name="mayor_45" class="form-control-edit">
                                <option value="">---</option>
                                <option value="S">SÍ</option>
                                <option value="N">NO</option>
                            </select>
                        </div>

                        <!-- Fila 4: Discapacitado, Grupo cotización, Centro impartición -->
                        <div class="form-group-custom span-2">
                            <label>Discapacitado</label>
                            <select name="discapacitado" class="form-control-edit">
                                <option value="">---</option>
                                <option value="S">SÍ</option>
                                <option value="N">NO</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-4">
                            <label>Grupo de cotización</label>
                            <select name="grupo_cotizacion" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <option value="1.- Ingenieros y licenciados">1. Ingenieros y licenciados</option>
                                <option value="2.- Ingenieros técnicos, peritos y Aytes. titulados">2. Ingenieros técnicos</option>
                                <option value="3.- Jefes Advos. y de taller">3. Jefes Advos.</option>
                                <option value="4.- Ayudantes no titulados">4. Ayudantes no titulados</option>
                                <option value="5.- Oficiales administrativos">5. Oficiales administrativos</option>
                                <option value="6.- Subalternos">6. Subalternos</option>
                                <option value="7.- Auxiliares administrativos">7. Auxiliares</option>
                                <option value="8.- Oficiales de primera y segunda">8. Of. 1ª y 2ª</option>
                                <option value="9.- Oficiales de tercera y especialistas">9. Of. 3ª y especialistas</option>
                                <option value="10.- Peones">10. Peones</option>
                                <option value="11.- Trabajadores menores de 18 años">11. Menores 18 años</option>
                                <option value="Trabajadores mayores de 18 años no cualif.">Mayores 18 no cualificados</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-6">
                            <label>Centro de impartición</label>
                            <input type="text" name="centro" class="form-control-edit" list="centros_list" placeholder="Escriba el centro...">
                            <datalist id="centros_list">
                                <?php foreach($centros_db as $c): ?><option value="<?= htmlspecialchars($c['nombre']) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>

                        <!-- Fila 5: Convocatoria, Plan, Estado, Modalidad -->
                        <div class="form-group-custom span-2">
                            <label>Convocatoria</label>
                            <select name="convocatoria" class="form-control-edit">
                                <option value="Todas">Todas</option>
                                <?php foreach($convocatorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-4">
                            <label>Plan</label>
                            <select name="plan" class="form-control-edit"><option value="">— Todos los planes —</option></select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Estado</label>
                            <select name="estado" class="form-control-edit">
                                <option value="">— Todos —</option>
                                <option value="Abandono">Abandono</option>
                                <option value="Admitido">Admitido</option>
                                <option value="Baja">Baja</option>
                                <option value="Baja por colocación">Baja por colocación</option>
                                <option value="Empleado en curso">Empleado en curso</option>
                                <option value="Espera">Espera</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="Finalizado sobrante">Finalizado sobrante</option>
                                <option value="Inscrito">Inscrito</option>
                                <option value="Pendiente docu">Pendiente docu</option>
                                <option value="Pendiente estado">Pendiente estado</option>
                                <option value="Pendiente otro curso">Pendiente otro curso</option>
                                <option value="Pendiente validacion">Pendiente validacion</option>
                                <option value="Preinscrito">Preinscrito</option>
                                <option value="Reserva">Reserva</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Modalidad</label>
                            <select name="modalidad" class="form-control-edit">
                                <option value="">— Todas —</option>
                                <option value="Teleformación">Teleformación</option>
                                <option value="Distancia">Distancia</option>
                                <option value="Mixta">Mixta</option>
                                <option value="Presencial">Presencial</option>
                                <option value="Semipresencial">Semipresencial</option>
                                <option value="Excepto presencial">Excepto presencial</option>
                            </select>
                        </div>

                        <!-- Fila 6: Acción, Grupo, Prioridad, Tipo Insc., Nuestros, Entregado, Captado, Certificables -->
                        <div class="form-group-custom span-1">
                            <label>Acción</label>
                            <input type="text" name="accion" class="form-control-edit" placeholder="Nº">
                        </div>
                        <div class="form-group-custom span-1">
                            <label>Grupo</label>
                            <input type="text" name="grupo" class="form-control-edit" placeholder="Nº">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Prioridad</label>
                            <select name="prioridad" class="form-control-edit">
                                <option value="">---</option>
                                <option value="1">1</option><option value="2">2</option><option value="3">3</option>
                                <option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Tipo Insc.</label>
                            <select name="filtro_inscripciones" class="form-control-edit">
                                <option value="">— Todas —</option>
                                <option value="Web">Web</option>
                                <option value="Manual">Manual</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Cursos nuestros</label>
                            <select name="nuestros" class="form-control-edit">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Entregado mat.</label>
                            <select name="entregado" class="form-control-edit">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-1">
                            <label>Captado</label>
                            <select name="captado" class="form-control-edit">
                                <option value="">---</option>
                                <option value="IDFO">IDFO</option>
                                <option value="UGT">UGT</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-1">
                            <label>Certif.</label>
                            <select name="certificables" class="form-control-edit">
                                <option value="">---</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>

                        <!-- Fila 7: Fechas de grupo -->
                        <div class="form-group-custom span-2">
                            <label>Inicio desde</label>
                            <input type="date" name="inicio_desde" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Inicio hasta</label>
                            <input type="date" name="inicio_hasta" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Mitad desde</label>
                            <input type="date" name="mitad_desde" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Mitad hasta</label>
                            <input type="date" name="mitad_hasta" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Fin desde</label>
                            <input type="date" name="fin_desde" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Fin hasta</label>
                            <input type="date" name="fin_hasta" class="form-control-edit">
                        </div>

                        <!-- Botón Buscar -->
                        <div class="form-group-custom span-12" style="display:flex; justify-content:center; padding-top:0.5rem;">
                            <button type="submit" class="btn btn-primary" style="padding:0.6rem 3rem; min-width:160px; width:auto;">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                Buscar
                            </button>
                        </div>
                    </div><!-- /form-grid -->
                </form>
            </div>

            <!-- RESULTADOS -->
            <div class="results-section-premium">
                <div class="results-header-premium">
                    <h2>Resultado de la Búsqueda</h2>
                    <span class="results-count"><?= count($resultados) ?> inscripciones encontradas</span>
                </div>

                <div class="table-responsive">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Modal.</th>
                                <th>Nº Acc.</th>
                                <th>Nº Gr.</th>
                                <th>Cód Grupo</th>
                                <th>Curso</th>
                                <th>Alumno</th>
                                <th>Empresa</th>
                                <th>Sector empresa</th>
                                <th>Provincia</th>
                                <th>Comercial</th>
                                <th>Inicio</th>
                                <th>Mitad</th>
                                <th>Fin</th>
                                <th>Estado</th>
                                <th>No Admisión</th>
                                <th>Fecha Ins.</th>
                                <th>Cambio estado</th>
                                <th>Doc. Pte.</th>
                                <th>Prior.</th>
                                <th>Prefiere</th>
                                <th>Núm.</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resultados)): ?>
                                <tr>
                                    <td colspan="23" style="text-align: center; padding: 3rem; color: #64748b; font-style: italic;">
                                        <?= $buscando ? 'No se han encontrado resultados para los criterios seleccionados.' : 'Utilice los filtros para realizar una búsqueda.' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($resultados as $res): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($res['plan_nombre'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($res['modalidad_real'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($res['af_abreviatura'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($res['numero_grupo'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($res['grupo_cod'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($res['curso_nombre'] ?? $res['convocatoria_nombre'] ?? '') ?></td>
                                        <td>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($res['primer_apellido'] . ' ' . $res['segundo_apellido'] . ', ' . $res['alumno_nombre']) ?></div>
                                            <div style="font-size: 0.65rem; color: #64748b;"><?= htmlspecialchars($res['dni']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($res['empresa_nombre'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($res['empresa_sector'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($res['provincia'] ?? '') ?></td>
                                        <td><?= htmlspecialchars(trim(($res['comercial_nombre'] ?? '') . ' ' . ($res['comercial_apellidos'] ?? ''))) ?></td>
                                        <td><?= !empty($res['grupo_inicio']) && $res['grupo_inicio'] != '0000-00-00' ? date('d/m/Y', strtotime($res['grupo_inicio'])) : '' ?></td>
                                        <td><?= !empty($res['grupo_mitad']) && $res['grupo_mitad'] != '0000-00-00' ? date('d/m/Y', strtotime($res['grupo_mitad'])) : '' ?></td>
                                        <td><?= !empty($res['grupo_fin']) && $res['grupo_fin'] != '0000-00-00' ? date('d/m/Y', strtotime($res['grupo_fin'])) : '' ?></td>
                                        <td>
                                            <?php
                                            $estado_val = strtolower($res['estado'] ?? '');
                                            $badge_class = 'badge-default';
                                            if (stripos($estado_val, 'admitido') !== false) $badge_class = 'badge-admitido';
                                            elseif (stripos($estado_val, 'inscrito') !== false || stripos($estado_val, 'preinscrito') !== false) $badge_class = 'badge-inscrito';
                                            elseif (stripos($estado_val, 'espera') !== false || stripos($estado_val, 'pendiente') !== false) $badge_class = 'badge-espera';
                                            elseif (stripos($estado_val, 'baja') !== false || stripos($estado_val, 'abandono') !== false) $badge_class = 'badge-baja';
                                            elseif (stripos($estado_val, 'finalizado') !== false) $badge_class = 'badge-finalizado';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($res['estado'] ?? '') ?></span>
                                        </td>
                                        <td></td> <!-- No admisión -->
                                        <td><?= !empty($res['fecha_matricula']) && $res['fecha_matricula'] != '0000-00-00' ? date('d/m/Y', strtotime($res['fecha_matricula'])) : '' ?></td>
                                        <td></td> <!-- Cambio estado -->
                                        <td style="color: #ef4444; font-size: 0.72rem;">
                                            <?php
                                            $doc_pte = [];
                                            if (empty($res['dni_entregado'])) $doc_pte[] = 'DNI';
                                            if (empty($res['nomina_entregada'])) $doc_pte[] = 'Nómina';
                                            if (empty($res['anexo1_entregado'])) $doc_pte[] = 'Anexo';
                                            echo empty($doc_pte) ? '' : 'Falta: ' . implode(', ', $doc_pte);
                                            ?>
                                        </td>
                                        <td style="text-align: center; font-weight: 700;"><?= htmlspecialchars($res['af_prioridad'] ?? '') ?></td>
                                        <td style="text-align: center;"><?= htmlspecialchars($res['pref_presencial'] ?? '') ?></td>
                                        <td style="text-align: center;">1</td>
                                        <td style="text-align: center;">
                                            <a href="ficha_alumno.php?id=<?= $res['alumno_id'] ?>" class="btn-action" title="Ver ficha del alumno">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                Ficha
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</body>
</html>
