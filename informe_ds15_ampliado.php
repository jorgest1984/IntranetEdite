<?php
// informe_ds15_ampliado.php - Versión Completa Premium v2
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'informe_ds15_ampliado.php';

// --- CARGA DE DATOS PARA FILTROS ---
$provincias = [
    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
];

try {
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    
    // Comerciales
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales ? $stmtComerciales->fetchAll(PDO::FETCH_ASSOC) : [];

    // Tutores
    $stmtTutores = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%') AND u.activo = 1 ORDER BY u.nombre ASC");
    $tutores = $stmtTutores ? $stmtTutores->fetchAll(PDO::FETCH_ASSOC) : [];

    // Centros
    $stmtCentros = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    $centros_db = $stmtCentros ? $stmtCentros->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $convocatorias = $planes = $comerciales = $tutores = $centros_db = [];
}

// --- LÓGICA DE BÚSQUEDA ---
$resultados = [];
$buscando = false;
$where = ["1=1"];
$params = [];

if ($_SERVER['REQUEST_METHOD'] == 'GET' && !empty($_GET)) {
    if (isset($_GET['buscar'])) {
        $buscando = true;
        
        // Filtros aplicables (Simplificados para esta versión de reporte)
        if (!empty($_GET['convocatoria'])) { $where[] = "m.convocatoria_id = ?"; $params[] = $_GET['convocatoria']; }
        if (!empty($_GET['plan'])) { $where[] = "p.id = ?"; $params[] = $_GET['plan']; }
        if (!empty($_GET['curso'])) { $where[] = "c.nombre LIKE ?"; $params[] = "%" . $_GET['curso'] . "%"; }
        if (!empty($_GET['cod_grupo'])) { $where[] = "m.id LIKE ?"; $params[] = "%" . $_GET['cod_grupo'] . "%"; }
        if (!empty($_GET['estado'])) { $where[] = "m.estado = ?"; $params[] = $_GET['estado']; }
        if (!empty($_GET['provincia'])) { $where[] = "a.provincia LIKE ?"; $params[] = "%" . $_GET['provincia'] . "%"; }
        if (!empty($_GET['sexo'])) { $where[] = "a.sexo = ?"; $params[] = $_GET['sexo']; }

        $sql = "SELECT m.*, a.nombre as alumno_nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.provincia, a.sexo, a.fecha_nacimiento, a.seguridad_social,
                    c.nombre as convocatoria_nombre, p.nombre as plan_nombre, e.nombre as empresa_nombre, e.cif as empresa_cif, e.sector as empresa_sector,
                    COALESCE(p.expediente, c.codigo_expediente) as exp_report,
                    TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, COALESCE(m.fecha_matricula, CURDATE())) as edad
                FROM matriculas m
                INNER JOIN alumnos a ON m.alumno_id = a.id
                LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
                LEFT JOIN planes p ON c.id = p.convocatoria_id
                LEFT JOIN empresas e ON a.id = e.id
                WHERE " . implode(" AND ", $where) . "
                LIMIT 300";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Error en la búsqueda: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe DS15 Ampliado - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
            --header-dark: #333;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 1.5rem; }

        /* Estilo del Buscador Grid */
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
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }
        .form-group { display: flex; align-items: center; gap: 5px; }
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
        input[type="text"].form-control, input[type="date"].form-control { height: 22px; }

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

        /* Resultados */
        .report-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 1.5rem 0 1rem;
            color: #1e293b;
        }

        .table-responsive-fp {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border-gray);
            background: white;
            border-radius: 4px;
        }
        .table-custom {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
        }
        .table-custom th {
            background: var(--header-dark);
            color: white;
            padding: 8px 10px;
            text-align: left;
            text-transform: uppercase;
            font-weight: 700;
            border-right: 1px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }
        .table-custom td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            white-space: nowrap;
        }
        .total-row { background: #f8fafc; font-weight: 800; border-bottom: 2px solid #333; }

        /* Botones de Exportación */
        .export-actions { display: flex; gap: 10px; margin: 15px 0; }
        .btn-export {
            background: #2563eb;
            color: white;
            padding: 6px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-export:hover { background: #1d4ed8; }

        .legend-box { margin-top: 20px; font-size: 0.75rem; color: #64748b; }
        .footnote { vertical-align: super; font-size: 0.6rem; font-weight: 700; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            
            <div class="search-card">
                <div class="card-header-custom">
                    <h2>LISTADO DE DS15 AMPLIADO DEL PLAN - CAMPOS DE BÚSQUEDA</h2>
                </div>
                <form class="search-form" method="GET">
                    <input type="hidden" name="buscar" value="1">
                    
                    <!-- Fila 1 -->
                    <div class="search-row">
                        <div class="form-group"><label>Curso:</label><input type="text" name="curso" class="form-control" style="width: 150px;"></div>
                        <div class="form-group"><label>Código grupo:</label><input type="text" name="cod_grupo" class="form-control" style="width: 100px;"></div>
                        <div class="form-group"><label>Mostrar s/grupo</label><input type="checkbox" name="sin_grupo"></div>
                        <div class="form-group">
                            <label>Comercial:</label>
                            <select name="comercial" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach($comerciales as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tutor:</label>
                            <select name="tutor" class="form-control" style="width: 250px;">
                                <option value="">---</option>
                                <?php foreach($tutores as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'].' '.$t['apellidos']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group"><label>Fecha insc. desde:</label><input type="date" name="fecha_desde" class="form-control"></div>
                        <div class="form-group"><label>hasta:</label><input type="date" name="fecha_hasta" class="form-control"></div>
                        <div class="form-group">
                            <label>Provincia:</label>
                            <input type="text" name="provincia" class="form-control" list="provincias_list" style="width: 150px;">
                            <datalist id="provincias_list"><?php foreach($provincias as $p): ?><option value="<?= mb_strtoupper($p, 'UTF-8') ?>"><?php endforeach; ?></datalist>
                        </div>
                        <div class="form-group">
                            <label>Motivo No-Admisión:</label>
                            <select name="motivo_rechazo" class="form-control" style="width: 150px;">
                                <option value="">---</option>
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
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <div class="form-group"><label>Solicitante:</label><select name="solicitante" class="form-control" style="width: 280px;"><option value="">---</option><option value="COMFIA">COMFIA</option><option value="UGT DE CATALUNYA">UGT DE CATALUNYA</option><option value="UGT Madrid">UGT Madrid</option><option value="FETCM-UGT">FETCM-UGT</option><option value="FETE-UGT">FETE-UGT</option><option value="FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA">FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA</option><option value="MARS">MARS</option><option value="FITAG">FITAG</option><option value="Comunidad de Madrid">Comunidad de Madrid</option><option value="FAECTA">FAECTA</option><option value="UGT Andalucía">UGT Andalucía</option><option value="FTFE">FTFE</option><option value="Criteria">Criteria</option><option value="FSP-UGT Palencia">FSP-UGT Palencia</option><option value="JUNTA DE CASTILLA Y LEON">JUNTA DE CASTILLA Y LEON</option><option value="JUNTA DE ANDALUCIA">JUNTA DE ANDALUCIA</option><option value="CRUZ ROJA ESPAÑOLA">CRUZ ROJA ESPAÑOLA</option><option value="MARSDIGITAL S.L.">MARSDIGITAL S.L.</option><option value="FUNDACIÓN PIQUER">FUNDACIÓN PIQUER</option><option value="Fed. Estatal de servicios - UGT">Fed. Estatal de servicios - UGT</option></select></div>
                        <div class="form-group"><label>Sexo:</label><select name="sexo" class="form-control"><option value="">---</option><option value="M">Hombre</option><option value="F">Mujer</option></select></div>
                        <div class="form-group"><label>Colectivo:</label><select name="colectivo" class="form-control" style="width: 380px;"><option value="">---</option><option value="Cuidadores no profesionales">Cuidadores no profesionales</option><option value="Empleado hogar">Empleado hogar</option><option value="ERE">ERE</option><option value="ERTE">ERTE</option><option value="Fijos discontinuos">Fijos discontinuos</option><option value="Autónomos">Autónomos</option><option value="Régimen general">Régimen general</option></select></div>
                        <div class="form-group"><label>No válido:</label><select name="no_valido" class="form-control"><option value="N">NO</option><option value="S">SÍ</option></select></div>
                        <div class="form-group"><label>Mayor 45:</label><select name="mayor_45" class="form-control"><option value="">---</option><option value="S">SÍ</option><option value="N">NO</option></select></div>
                    </div>

                    <!-- Fila 4 -->
                    <div class="search-row">
                        <div class="form-group" style="margin-left: 360px;"><label>Discapacitado:</label><select name="discapacitado" class="form-control"><option value="">---</option><option value="S">SÍ</option><option value="N">NO</option></select></div>
                    </div>

                    <!-- Fila 5 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Grupo cotización:</label>
                            <select name="grupo_cotizacion" class="form-control" style="width: 320px;">
                                <option value="">---</option>
                                <option value="1">1.- Ingenieros y licenciados</option>
                                <option value="2">2.- Ingenieros técnicos, peritos y Aytes. titulados</option>
                                <option value="3">3.- Jefes Advos. y de taller</option>
                                <option value="4">4.- Ayudantes no titulados</option>
                                <option value="5">5.- Oficiales administrativos</option>
                                <option value="6">6.- Subalternos</option>
                                <option value="7">7.- Auxiliares administrativos</option>
                                <option value="8">8.- Oficiales de primera y segunda</option>
                                <option value="9">9.- Oficiales de tercera y especialistas</option>
                                <option value="10">10.- Peones</option>
                                <option value="11">11.- Trabajadores menores de 18 años</option>
                                <option value="12">Trabajadores mayores de 18 años no cualif.</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Centro impartición:</label>
                            <input type="text" name="centro" class="form-control" list="centros_list" style="width: 500px;">
                            <datalist id="centros_list"><?php foreach($centros_db as $c): ?><option value="<?= htmlspecialchars($c['nombre']) ?>"><?php endforeach; ?></datalist>
                        </div>
                    </div>

                    <!-- Fila 6 -->
                    <div class="search-row">
                        <div class="form-group"><label>Convocatoria:</label><select name="convocatoria" class="form-control" style="width: 80px;"><option value="">Todas</option><?php foreach($convocatorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Plan:</label><select name="plan" class="form-control" style="width: 450px;"><option value="">------------ Todos los planes ------------</option><?php foreach($planes as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado" class="form-control" style="width: 150px;">
                                <option value="">---</option>
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
                        <div class="form-group">
                            <label>Modalidad:</label>
                            <select name="modalidad" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <option value="Teleformación">Teleformación</option>
                                <option value="Distancia">Distancia</option>
                                <option value="Mixta">Mixta</option>
                                <option value="Presencial">Presencial</option>
                                <option value="Semipresencial">Semipresencial</option>
                                <option value="Excepto presencial">Excepto presencial</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 7 -->
                    <div class="search-row">
                        <div class="form-group"><label>Acción:</label><input type="text" name="accion" class="form-control" style="width: 50px;"></div>
                        <div class="form-group"><label>Grupo:</label><input type="text" name="grupo" class="form-control" style="width: 50px;"></div>
                        <div class="form-group"><label>Prioridad:</label><select name="prioridad" class="form-control"><option value="">---</option><?php for($i=1;$i<=7;$i++):?><option value="<?=$i?>"><?=$i?></option><?php endfor;?></select></div>
                        <div class="form-group"><label>Inscripciones:</label><select name="inscr_tipo" class="form-control" style="width: 100px;"><option value="">---</option><option value="Web">Web</option><option value="Manual">Manual</option></select></div>
                        <div class="form-group"><label>Cursos nuestros:</label><select name="nuestros" class="form-control"><option value="">Todos</option><option value="S">Sí</option><option value="N">No</option></select></div>
                        <div class="form-group"><label>Entregado mat:</label><select name="entregado" class="form-control"><option value="">Todos</option><option value="S">Sí</option><option value="N">No</option></select></div>
                        <div class="form-group"><label>Captado:</label><select name="captado" class="form-control"><option value="">Todos</option><option value="IDFO">IDFO</option><option value="UGT">UGT</option></select></div>
                        <div class="form-group"><label>CERTIFICABLES:</label><select name="certificables" class="form-control"><option value="">Todos</option><option value="S">Sí</option><option value="N">No</option></select></div>
                    </div>

                    <!-- Fila 8 -->
                    <div class="search-row">
                        <div class="form-group"><label>Inicio desde:</label><input type="text" class="form-control" style="width:80px;"><label>hasta:</label><input type="text" class="form-control" style="width:80px;"></div>
                        <div class="form-group" style="margin-left: 15px;"><label>Mitad desde:</label><input type="text" class="form-control" style="width:80px;"><label>hasta:</label><input type="text" class="form-control" style="width:80px;"></div>
                        <div class="form-group" style="margin-left: 15px;"><label>Fin desde:</label><input type="text" class="form-control" style="width:80px;"><label>hasta:</label><input type="text" class="form-control" style="width:80px;"></div>
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn-buscar">Buscar</button>
                    </div>
                </form>
            </div>

            <?php if ($buscando): ?>
            <div class="report-section">
                <h1 class="report-title">- Plan</h1>

                <div class="export-actions">
                    <a href="#" class="btn-export">Exportar a Excel</a>
                    <a href="#" class="btn-export">Exportar a CSV</a>
                    <a href="#" class="btn-export">Exportar a JSON</a>
                </div>

                <div class="table-responsive-fp">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Expediente</th>
                                <th>nA</th>
                                <th>nG</th>
                                <th>Curso</th>
                                <th>Alumno/a</th>
                                <th>NIF</th>
                                <th>NSS</th>
                                <th>Provincia</th>
                                <th>Fecha nac.</th>
                                <th>Edad<span class="footnote">1</span></th>
                                <th>Sexo</th>
                                <th>Col.</th>
                                <th>DSP LD</th>
                                <th>Contrato</th>
                                <th>Grupo cotiz.</th>
                                <th>Estudios</th>
                                <th>Dis.</th>
                                <th>Empresa</th>
                                <th>CIF</th>
                                <th>Sector</th>
                                <th>PYME</th>
                                <th>Estado</th>
                                <th>Certifica</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="total-row">
                                <td>TOTAL</td>
                                <td></td>
                                <td></td>
                                <td colspan="21"><?= count($resultados) ?> resultados encontrados</td>
                            </tr>
                            <?php foreach ($resultados as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['exp_report']) ?></td>
                                <td>1</td><td>1</td>
                                <td><?= htmlspecialchars($row['convocatoria_nombre'] ?? 'N/A') ?></td>
                                <td><strong><?= htmlspecialchars($row['primer_apellido'].' '.$row['segundo_apellido'].', '.$row['alumno_nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($row['dni']) ?></td>
                                <td><?= htmlspecialchars($row['seguridad_social'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['provincia'] ?? '-') ?></td>
                                <td><?= ($row['fecha_nacimiento'] && $row['fecha_nacimiento'] != '0000-00-00') ? date('d/m/Y', strtotime($row['fecha_nacimiento'])) : '-' ?></td>
                                <td><?= $row['edad'] ?? '-' ?></td>
                                <td><?= htmlspecialchars($row['sexo'] ?? '-') ?></td>
                                <td>RG</td><td></td><td>Indef.</td><td>7</td><td>G. Medio</td><td>No</td>
                                <td><?= htmlspecialchars($row['empresa_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['empresa_cif'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['empresa_sector'] ?? '-') ?></td>
                                <td>Sí</td>
                                <td><span style="background:#e2e8f0; padding:2px 5px; border-radius:3px;"><?= htmlspecialchars($row['estado']) ?></span></td>
                                <td>SÍ</td>
                                <td><a href="ficha_alumno.php?id=<?= $row['alumno_id'] ?>" style="color:#2563eb; font-weight:600;">Ver Ficha</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="legend-box">
                    <p><span class="footnote">1</span> Edad en la fecha de inicio del curso.</p>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>
