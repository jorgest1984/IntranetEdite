<?php
// tutorias.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$active_view = $_GET['view'] ?? 'dashboard';
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

    // Lógica de búsqueda para Llamadas de Seguimiento
    $llamadas = [];
    if ($active_view === 'llamadas' && isset($_GET['fecha_desde'])) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($_GET['fecha_desde'])) { $where[] = "fecha >= ?"; $params[] = $_GET['fecha_desde']; }
        if (!empty($_GET['fecha_hasta'])) { $where[] = "fecha <= ?"; $params[] = $_GET['fecha_hasta']; }
        if (!empty($_GET['nombre'])) { $where[] = "a.nombre LIKE ?"; $params[] = "%".$_GET['nombre']."%"; }
        if (!empty($_GET['apellidos'])) { $where[] = "a.primer_apellido LIKE ?"; $params[] = "%".$_GET['apellidos']."%"; }
        if (!empty($_GET['empresa'])) { $where[] = "(ts.empresa LIKE ? OR e.nombre LIKE ?)"; $params[] = "%".$_GET['empresa']."%"; $params[] = "%".$_GET['empresa']."%"; }
        if (!empty($_GET['usuario_id'])) { $where[] = "ts.usuario_id = ?"; $params[] = $_GET['usuario_id']; }
        if (!empty($_GET['motivo'])) { $where[] = "ts.motivo = ?"; $params[] = $_GET['motivo']; }
        if (!empty($_GET['forma'])) { $where[] = "ts.forma = ?"; $params[] = $_GET['forma']; }

        $sql = "SELECT ts.*, a.nombre as alumno_nombre, a.primer_apellido as alumno_apellido, e.nombre as empresa_nombre, c.nombre as curso_nombre
                FROM tutorias_seguimiento ts
                LEFT JOIN alumnos a ON ts.alumno_id = a.id
                LEFT JOIN empresas e ON ts.empresa_id = e.id
                LEFT JOIN cursos c ON ts.curso_id = c.id
                WHERE " . implode(" AND ", $where) . " ORDER BY ts.fecha DESC, ts.hora DESC LIMIT 100";
        $stmtS = $pdo->prepare($sql);
        $stmtS->execute($params);
        $llamadas = $stmtS->fetchAll();
    }

} catch (Exception $e) {}

$current_page = 'tutorias.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutorías - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --primary-color: #006ce4;
            --primary-hover: #0056b3;
            --accent-blue: #0ea5e9;
            --text-color: #0f172a;
            --text-muted: #64748b;
            --border-color: rgba(0, 108, 228, 0.08);
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            --title-red: #b91c1c;
        }

        .main-content {
            padding: 2rem !important;
            max-width: 1600px;
        }

        /* Premium Card / Panel */
        .card-premium {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .card-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .card-header-premium h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form elements matching premium spec */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.25rem;
            margin-bottom: 1rem;
        }

        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        /* Grid Column Spans */
        .span-1 { grid-column: span 1; }
        .span-2 { grid-column: span 2; }
        .span-3 { grid-column: span 3; }
        .span-4 { grid-column: span 4; }
        .span-5 { grid-column: span 5; }
        .span-6 { grid-column: span 6; }
        .span-7 { grid-column: span 7; }
        .span-8 { grid-column: span 8; }
        .span-9 { grid-column: span 9; }
        .span-10 { grid-column: span 10; }
        .span-11 { grid-column: span 11; }
        .span-12 { grid-column: span 12; }

        .form-group-custom label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .form-group-custom label.label-red {
            color: #ef4444 !important;
        }

        .form-control-edit {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            background: rgba(248, 250, 252, 0.8);
            color: var(--text-color);
            outline: none;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
            height: 38px;
        }

        .form-control-edit:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.12);
            background: #fff;
        }

        select.form-control-edit {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg fill='%23475569' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/><path d='M0 0h24v24H0z' fill='none'/></svg>") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 1.25rem !important;
            padding-right: 2.5rem !important;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            height: 38px;
            box-sizing: border-box;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.15);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 108, 228, 0.25);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--text-color);
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        /* Action Bar for Tutorias */
        .action-bar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            padding: 1.25rem;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            margin-bottom: 2.5rem;
            box-shadow: var(--glass-shadow);
        }

        .action-bar .btn-action {
            padding: 0.7rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--sidebar-text);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            height: 42px;
            box-sizing: border-box;
        }

        .action-bar .btn-action i {
            font-size: 0.95rem;
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }

        .action-bar .btn-action:hover {
            color: var(--primary-color);
            border-color: var(--primary-hover);
            background: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 108, 228, 0.1);
        }

        .action-bar .btn-action:hover i {
            transform: scale(1.15) rotate(5deg);
        }

        .action-bar .btn-action.active-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: #ffffff !important;
            border-color: transparent;
            box-shadow: 0 8px 20px rgba(0, 108, 228, 0.25);
        }

        .action-bar .btn-action.active-btn i {
            color: #ffffff !important;
        }

        /* Premium Table dense */
        .table-premium {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.78rem;
            text-align: left;
        }

        .table-premium th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            color: #334155;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
            border-top: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .table-premium th .sort-icon {
            display: inline-flex;
            align-items: center;
            margin-right: 4px;
            vertical-align: middle;
            color: var(--primary-color);
        }

        .table-premium td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-color);
            vertical-align: middle;
            background-color: #ffffff;
            white-space: nowrap;
        }

        .table-premium tr:hover td {
            background-color: #f8fafc;
        }

        .table-premium tr:last-child td {
            border-bottom: none;
        }

        /* Custom Scrollbar for Results Table */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 9999px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        
        .dark-theme .table-responsive::-webkit-scrollbar-thumb {
            background: #475569;
        }
        
        .dark-theme .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Status colors & Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .status-badge.autonomo { background: #ffedd5; color: #b45309; border: 1px solid #fed7aa; }
        .status-badge.desempleado { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-badge.suspendido { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .status-badge.restringido { background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; }

        .legend-container {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 1.25rem;
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        /* Status Header layout */
        .status-header {
            display: flex;
            gap: 10px;
            padding: 1rem;
            flex-wrap: wrap;
            background: rgba(248, 250, 252, 0.5);
            border-bottom: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
        }

        .status-box {
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.04);
        }

        .status-box.bg-orange { background: #ffedd5; color: #ea580c; border-color: #fed7aa; }
        .status-box.bg-cyan { background: #ecfeff; color: #0891b2; border-color: #cffafe; }
        .status-box.bg-pink { background: #fdf2f8; color: #db2777; border-color: #fbcfe8; }
        .status-box.bg-green { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <!-- ACTION BAR -->
            <div class="action-bar">
                <a href="tutorias.php?view=calcular" class="btn-action <?= $active_view == 'calcular' ? 'active-btn' : '' ?>">
                    <i class="fas fa-calculator"></i> Calcular llamadas
                </a>
                <a href="email_masivo.php" class="btn-action">
                    <i class="fas fa-envelope"></i> E-mails masivos
                </a>
                <button type="button" class="btn-action">
                    <i class="fas fa-play"></i> Inicio curso ()
                </button>
                <button type="button" class="btn-action">
                    <i class="fas fa-adjust"></i> Mitad de curso ()
                </button>
                <button type="button" class="btn-action">
                    <i class="fas fa-calendar-check"></i> 7 días fin ()
                </button>
                <button type="button" class="btn-action">
                    <i class="fas fa-file-alt"></i> Documentación ()
                </button>
                <button type="button" class="btn-action">
                    <i class="fas fa-upload"></i> Subir evals
                </button>
                <button type="button" class="btn-action">
                    <i class="fas fa-print"></i> Imprimir evals
                </button>
                <a href="tutorias.php?view=llamadas" class="btn-action <?= $active_view == 'llamadas' ? 'active-btn' : '' ?>">
                    <i class="fas fa-phone-alt"></i> Llamadas seguimiento
                </a>
                <a href="calendario_tutorias.php" class="btn-action">
                    <i class="fas fa-calendar-alt"></i> Calendario de tutorias
                </a>
            </div>

            <?php if ($active_view === 'calcular'): ?>
            <!-- VISTA: CALCULAR LLAMADAS (BUSCADOR             <div class="card-premium">
                <div class="card-header-premium">
                    <h2><i class="fas fa-calculator"></i> Calcular llamadas (Buscador Avanzado v1.5)</h2>
                    <button type="button" class="btn btn-secondary" style="height: 32px; padding: 0 12px; font-size: 0.78rem;">Quitar autofiltro</button>
                </div>
                
                <form class="search-form" method="GET" style="padding: 0;">
                    <input type="hidden" name="view" value="calcular">
                    
                    <div class="form-grid">
                        <!-- Autofiltros superiores -->
                        <div class="form-group-custom span-4">
                            <label>Tutor</label>
                            <select name="tutor" class="form-control-edit">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'].' '.$t['apellidos']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-4">
                            <label>Solicitante</label>
                            <select name="solicitante" class="form-control-edit">
                                <option value="">---</option>
                                <option value="COMFIA">COMFIA</option>
                                <option value="FED. COM. Y TTE. CCOO MADRID">FED. COM. Y TTE. CCOO MADRID</option>
                                <option value="UGT DE CATALUNYA">UGT DE CATALUNYA</option>
                                <option value="UGT Madrid">UGT Madrid</option>
                                <option value="FETCM-UGT">FETCM-UGT</option>
                                <option value="FETE-UGT">FETE-UGT</option>
                                <option value="FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA">FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA</option>
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
                        <div class="form-group-custom span-4">
                            <label>Comercial</label>
                            <select name="comercial" class="form-control-edit">
                                <option value="">---</option>
                                <?php foreach ($comerciales as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Datos Personales -->
                        <div class="form-group-custom span-3">
                            <label>Nombre</label>
                            <input type="text" name="nombre" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Apellidos</label>
                            <input type="text" name="apellidos" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>NIF</label>
                            <input type="text" name="nif" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>E-mail</label>
                            <input type="text" name="email" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" class="form-control-edit">
                        </div>

                        <!-- Ubicación y Empresa -->
                        <div class="form-group-custom span-4">
                            <label>Provincia</label>
                            <select name="provincia" class="form-control-edit">
                                <option value="">---</option>
                                <?php foreach($provincias as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-4">
                            <label>Empresa</label>
                            <input type="text" name="empresa" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-4">
                            <label>Colectivo</label>
                            <select name="colectivo" class="form-control-edit">
                                <option value="">---</option>
                                <option value="Régimen general">Régimen general</option>
                                <option value="Autónomo">Autónomo</option>
                                <option value="Desempleado">Desempleado</option>
                            </select>
                        </div>

                        <!-- Académico -->
                        <div class="form-group-custom span-2">
                            <label>Acción</label>
                            <input type="text" name="accion" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Grupo</label>
                            <input type="text" name="grupo" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-4">
                            <label>Curso</label>
                            <input type="text" name="curso" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Grupos en curso</label>
                            <select name="grupos_en_curso" class="form-control-edit"><option value=""></option></select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Cod grupo</label>
                            <input type="text" name="cod_grupo" class="form-control-edit">
                        </div>

                        <!-- Plan y Estado -->
                        <div class="form-group-custom span-4">
                            <label>Convocatoria</label>
                            <select name="convocatoria" class="form-control-edit"><option value="Todas">Todas</option></select>
                        </div>
                        <div class="form-group-custom span-4">
                            <label>Plan</label>
                            <select name="plan" class="form-control-edit"><option value="">Todos los planes</option></select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Estado</label>
                            <select name="estado" class="form-control-edit"><option value=""></option></select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Modalidad</label>
                            <select name="modalidad" class="form-control-edit"><option value=""></option></select>
                        </div>

                        <!-- Estados Documentales -->
                        <div class="form-group-custom span-2">
                            <label>Entregado mat</label>
                            <select name="entregado" class="form-control-edit"><option value=""></option></select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Horario</label>
                            <input type="text" name="horario" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Conectados</label>
                            <select name="conectados" class="form-control-edit"><option value=""></option></select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Realizó encuesta</label>
                            <select name="encuesta" class="form-control-edit"><option value=""></option></select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Docu alumno pte</label>
                            <select name="docu_alumno_pte" class="form-control-edit"><option value=""></option></select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Docu curso pte</label>
                            <select name="docu_curso_pte" class="form-control-edit"><option value=""></option></select>
                        </div>

                        <!-- Fechas v2 -->
                        <div class="form-group-custom span-3">
                            <label>Inicio desde / hasta</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="inicio_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="inicio_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>25% desde / hasta</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="pct25_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="pct25_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Mitad desde / hasta</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="mitad_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="mitad_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Fin desde</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="fin_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="fin_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>

                        <!-- Checkboxes / Toggles -->
                        <div class="form-group-custom span-4" style="flex-direction: row; align-items: center; gap: 8px; padding-top: 15px;">
                            <input type="checkbox" name="eval_hechas_sin_subir" id="eval_no_sub" style="width: 17px; height: 17px;">
                            <label for="eval_no_sub" style="cursor: pointer; margin: 0; color: #ef4444; font-weight: bold;">Evals hechas sin subir</label>
                        </div>
                        <div class="form-group-custom span-4" style="flex-direction: row; align-items: center; gap: 8px; padding-top: 15px;">
                            <input type="checkbox" name="no_conec_15" id="no_conec_15" style="width: 17px; height: 17px;">
                            <label for="no_conec_15" style="cursor: pointer; margin: 0; color: #ef4444; font-weight: bold;">No conectados antes del 15%</label>
                        </div>
                        <div class="form-group-custom span-4" style="flex-direction: row; align-items: center; gap: 8px; padding-top: 15px;">
                            <input type="checkbox" name="no_conec_25" id="no_conec_25" style="width: 17px; height: 17px;">
                            <label for="no_conec_25" style="cursor: pointer; margin: 0; color: #ef4444; font-weight: bold;">No conectados antes del 25%</label>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 2rem; display: flex; justify-content: center; gap: 0.75rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0 2.5rem; height: 42px;"><i class="fas fa-search"></i> Buscar</button>
                        <button type="reset" class="btn btn-secondary" style="padding: 0 1.5rem; height: 42px;"><i class="fas fa-undo"></i> Limpiar filtros</button>
                    </div>
                </form>
            </div>
            
            <!-- RESULTADOS: RELACION DE CURSOS (27 COLUMNAS) -->
            <div class="card-premium" style="margin-top: 20px;">
                <div class="card-header-premium">
                    <h2><i class="fas fa-list-alt"></i> Relación de Cursos</h2>
                    <div style="font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="ord_mult_1" style="width: 16px; height: 16px;">
                        <label for="ord_mult_1" style="color: var(--text-muted); cursor: pointer;">Ordenar múltiple</label>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-premium" style="min-width: 2500px;">
                        <thead>
                            <tr style="background: #eff6ff;">
                                <th><span class="sort-icon">⬇</span>Plan</th>
                                <th><span class="sort-icon">⬇</span>Ac/Gr</th>
                                <th><span class="sort-icon">⬇</span>Curso</th>
                                <th><span class="sort-icon">⬇</span>Alumno</th>
                                <th><span class="sort-icon">⬇</span>NIF</th>
                                <th><span class="sort-icon">⬇</span>Empresa</th>
                                <th><span class="sort-icon">⬇</span>Fecha ins</th>
                                <th><span class="sort-icon">⬇</span>Inicio</th>
                                <th><span class="sort-icon">⬇</span>25%</th>
                                <th><span class="sort-icon">⬇</span>Mitad</th>
                                <th><span class="sort-icon">⬇</span>Fin</th>
                                <th><span class="sort-icon">⬇</span>Estado</th>
                                <th><span class="sort-icon">⬇</span>Ult Fecha Mat.</th>
                                <th><span class="sort-icon">⬇</span>Ultima conex</th>
                                <th style="color: var(--title-red); background: #fee2e2;"><span class="sort-icon">⬇</span>Fecha ult. llam.</th>
                                <th><span class="sort-icon">⬇</span>Eval N</th>
                                <th><span class="sort-icon">⬇</span>Eval I</th>
                                <th><span class="sort-icon">⬇</span>Eval F</th>
                                <th><span class="sort-icon">⬇</span>Fecha final. alumno</th>
                                <th><span class="sort-icon">⬇</span>Doc conexión</th>
                                <th><span class="sort-icon">⬇</span>Encu</th>
                                <th><span class="sort-icon">⬇</span>Dipl</th>
                                <th><span class="sort-icon">⬇</span>Fecha Dipl.</th>
                                <th><span class="sort-icon">⬇</span>Doc inic. pte</th>
                                <th><span class="sort-icon">⬇</span>Datos ptes</th>
                                <th><span class="sort-icon">⬇</span>Docu alumno pte</th>
                                <th><span class="sort-icon">⬇</span>Docu curso pte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="27" style="text-align: center; padding: 3rem; color: #94a3b8; font-style: italic;">
                                    Realice una búsqueda para generar resultados.
                                </td>
                            </tr>
                         <!-- LEYENDAS Y EXPORTACIÓN -->
            <div class="legend-container">
                <div style="display: flex; align-items: center; gap: 6px;"><span class="status-badge autonomo">Autónom@</span></div>
                <div style="display: flex; align-items: center; gap: 6px;"><span class="status-badge desempleado">Desemplead@</span></div>
                <div style="display: flex; align-items: center; gap: 6px;"><span class="status-badge suspendido">Suspendid@</span></div>
                <div style="display: flex; align-items: center; gap: 6px;"><span class="status-badge restringido">Restringid@</span></div>
                <div style="display: flex; align-items: center; gap: 6px; color: var(--text-muted);"><i class="fas fa-cog" style="color: #64748b;"></i> Incid. pendientes &lt; 4 días</div>
                <div style="display: flex; align-items: center; gap: 6px; color: #ef4444;"><i class="fas fa-bomb" style="color: #ef4444;"></i> Incid. pendientes más de 4 días</div>
            </div>

            <div style="text-align: center; margin-top: 20px; display: flex; justify-content: center; gap: 10px; margin-bottom: 2rem;">
                <button type="button" class="btn btn-primary"><i class="fas fa-file-excel"></i> Exportar a Excel</button>
                <a href="tutorias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>

            <?php elseif ($active_view === 'llamadas'): ?>
            <!-- VISTA: LLAMADAS DE SEGUIMIENTO -->
            <div class="card-premium">
                <div class="card-header-premium">
                    <h2><i class="fas fa-phone-alt"></i> Llamadas Realizadas - Campos de Búsqueda</h2>
                </div>
                <form class="search-form" method="GET" style="padding: 0;">
                    <input type="hidden" name="view" value="llamadas">
                    
                    <div class="form-grid">
                        <div class="form-group-custom span-3">
                            <label>Desde</label>
                            <input type="date" name="fecha_desde" class="form-control-edit" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control-edit" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Nombre</label>
                            <input type="text" name="nombre" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Apellidos</label>
                            <input type="text" name="apellidos" class="form-control-edit">
                        </div>

                        <div class="form-group-custom span-4">
                            <label>Empresa</label>
                            <input type="text" name="empresa" class="form-control-edit" list="centros_list">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Usuario</label>
                            <select name="usuario_id" class="form-control-edit">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Motivo</label>
                            <select name="motivo" class="form-control-edit">
                                <option value="">---</option>
                                <option value="Seguimiento">Seguimiento</option>
                                <option value="Bienvenida">Bienvenida</option>
                                <option value="Incidencia">Incidencia</option>
                                <option value="Encuesta">Encuesta</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Forma</label>
                            <select name="forma" class="form-control-edit">
                                <option value="">---</option>
                                <option value="Teléfono">Teléfono</option>
                                <option value="Email">Email</option>
                                <option value="WhatsApp">WhatsApp</option>
                            </select>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0 2.5rem; height: 42px;"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                </form>
            </div>
            
            <!-- RESULTADOS LLAMADAS -->
            <div class="card-premium" style="margin-top: 20px;">
                <div class="card-header-premium">
                    <h2><i class="fas fa-history"></i> Historial de Llamadas Seguimiento</h2>
                    <div style="font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="ord_mult_2" style="width: 16px; height: 16px;">
                        <label for="ord_mult_2" style="color: var(--text-muted); cursor: pointer;">Ordenar múltiple</label>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th><span class="sort-icon">⬇</span>Empresa</th>
                                <th><span class="sort-icon">⬇</span>Alumno</th>
                                <th><span class="sort-icon">⬇</span>Curso</th>
                                <th><span class="sort-icon">⬇</span>Motivo</th>
                                <th><span class="sort-icon">⬇</span>Forma</th>
                                <th><span class="sort-icon">⬇</span>Fecha</th>
                                <th><span class="sort-icon">⬇</span>Hora</th>
                                <th><span class="sort-icon">⬇</span>Asunto</th>
                                <th><span class="sort-icon">⬇</span>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($llamadas)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 1rem; color: var(--label-blue); font-weight: bold;">
                                        0 registros.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($llamadas as $ll): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ll['empresa_nombre'] ?: $ll['empresa']) ?></td>
                                        <td><?= htmlspecialchars($ll['alumno_nombre'] . ' ' . $ll['alumno_apellido']) ?></td>
                                        <td><?= htmlspecialchars($ll['curso_nombre']) ?></td>
                                        <td><?= htmlspecialchars($ll['motivo']) ?></td>
                                        <td><?= htmlspecialchars($ll['forma']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($ll['fecha'])) ?></td>
                                        <td><?= date('H:i', strtotime($ll['hora'])) ?></td>
                                        <td><?= htmlspecialchars($ll['asunto']) ?></td>
                                        <td>
                                            <span title="<?= htmlspecialchars($ll['notas']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($ll['notas'], 0, 30, "...")) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <a href=            <?php else: ?>
            <!-- BUSCADOR GENERAL -->
            <div class="card-premium">
                <div class="card-header-premium">
                    <h2><i class="fas fa-search"></i> Tutorías - Campos de Búsqueda</h2>
                </div>
                <form class="search-form" method="GET" style="padding: 0;">
                    <div class="form-grid">
                        <div class="form-group-custom span-4">
                            <label>Curso</label>
                            <input type="text" name="curso" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Código grupo</label>
                            <input type="text" name="cod_grupo" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Comercial</label>
                            <select name="comercial" class="form-control-edit">
                                <option value="">---</option>
                                <?php foreach ($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Tutor</label>
                            <select name="tutor" class="form-control-edit">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fechas Inscripción -->
                        <div class="form-group-custom span-3">
                            <label>Inscripción desde</label>
                            <input type="date" name="fecha_desde" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Inscripción hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Provincia</label>
                            <input type="text" name="provincia" class="form-control-edit" list="provincias_list" placeholder="Escriba provincia...">
                            <datalist id="provincias_list">
                                <?php foreach($provincias as $prov): ?><option value="<?= mb_strtoupper($prov, 'UTF-8') ?>"><?php endforeach; ?></option>
                            </datalist>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Motivo No-Admisión</label>
                            <select name="motivo_rechazo" class="form-control-edit">
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

                        <!-- Solicitante, Sexo, Colectivo -->
                        <div class="form-group-custom span-4">
                            <label>Solicitante</label>
                            <select name="solicitante" class="form-control-edit">
                                <option value="">---</option>
                                <option value="COMFIA">COMFIA</option>
                                <option value="FED. COM. Y TTE. CCOO MADRID">FED. COM. Y TTE. CCOO MADRID</option>
                                <option value="UGT DE CATALUNYA">UGT DE CATALUNYA</option>
                                <option value="UGT Madrid">UGT Madrid</option>
                                <option value="FETCM-UGT">FETCM-UGT</option>
                                <option value="FETE-UGT">FETE-UGT</option>
                                <option value="FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA">FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA</option>
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
                                <option value="">---</option>
                                <option value="M">Hombre</option>
                                <option value="F">Mujer</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-6">
                            <label>Colectivo</label>
                            <select name="colectivo" class="form-control-edit">
                                <option value="">---</option>
                                <?php // Agregamos opciones principales para agilizar ?>
                                <option value="Régimen general">Régimen general</option>
                                <option value="Autónomo">Autónomo / Régimen especial autónomos</option>
                                <option value="Desempleado">Desempleado</option>
                                <option value="Empleado hogar">Empleado hogar</option>
                            </select>
                        </div>

                        <!-- Filtros Adicionales -->
                        <div class="form-group-custom span-3">
                            <label>No válido</label>
                            <select name="no_valido" class="form-control-edit">
                                <option value="">---</option>
                                <option value="S">SÍ</option>
                                <option value="N" selected>NO</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Mayor de 45</label>
                            <select name="mayor_45" class="form-control-edit">
                                <option value="">---</option>
                                <option value="S">SÍ</option>
                                <option value="N">NO</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Discapacitado</label>
                            <select name="discapacitado" class="form-control-edit">
                                <option value="">---</option>
                                <option value="S">SÍ</option>
                                <option value="N">NO</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Grupo cotización</label>
                            <select name="grupo_cotizacion" class="form-control-edit">
                                <option value="">---</option>
                                <option value="1.- Ingenieros y licenciados">1.- Ingenieros y licenciados</option>
                                <option value="2.- Ingenieros técnicos, peritos y Aytes. titulados">2.- Ingenieros técnicos, peritos y Aytes. titulados</option>
                                <option value="3.- Jefes Advos. y de taller">3.- Jefes Advos. y de taller</option>
                                <option value="4.- Ayudantes no titulados">4.- Ayudantes no titulados</option>
                                <option value="5.- Oficiales administrativos">5.- Oficiales administrativos</option>
                                <option value="6.- Subalternos">6.- Subalternos</option>
                                <option value="7.- Auxiliares administrativos">7.- Auxiliares administrativos</option>
                                <option value="8.- Oficiales de primera y segunda">8.- Oficiales de primera y segunda</option>
                                <option value="9.- Oficiales de tercera y especialistas">9.- Oficiales de tercera y especialistas</option>
                                <option value="10.- Peones">10.- Peones</option>
                                <option value="11.- Trabajadores menores de 18 años">11.- Trabajadores menores de 18 años</option>
                                <option value="Trabajadores mayores de 18 años no cualif.">Trabajadores mayores de 18 años no cualif.</option>
                            </select>
                        </div>

                        <!-- Centro, Convocatoria, Plan, Estado, Modalidad -->
                        <div class="form-group-custom span-4">
                            <label>Centro impartición</label>
                            <input type="text" name="centro" class="form-control-edit" list="centros_list" placeholder="Escriba el centro...">
                            <datalist id="centros_list">
                                <?php foreach($centros_db as $c): ?><option value="<?= htmlspecialchars($c['nombre']) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Convocatoria</label>
                            <select name="convocatoria" class="form-control-edit">
                                <option value="Todas">Todas</option>
                                <?php foreach($convocatorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Plan</label>
                            <select name="plan" class="form-control-edit"><option value="">Todos los planes</option></select>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Estado</label>
                            <select name="estado" class="form-control-edit">
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

                        <!-- Acción, Grupo, Prioridad, Inscripciones, Cursos, Entregado, Captado, Certificables -->
                        <div class="form-group-custom span-1">
                            <label>Acción</label>
                            <input type="text" name="accion" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-1">
                            <label>Grupo</label>
                            <input type="text" name="grupo" class="form-control-edit">
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Prioridad</label>
                            <select name="prioridad" class="form-control-edit">
                                <option value="">---</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Inscripción</label>
                            <select name="filtro_inscripciones" class="form-control-edit">
                                <option value="">---</option>
                                <option value="Web">Web</option>
                                <option value="Manual">Manual</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Nuestros</label>
                            <select name="nuestros" class="form-control-edit">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Entregado mat</label>
                            <select name="entregado" class="form-control-edit">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Captado</label>
                            <select name="captado" class="form-control-edit">
                                <option value="">Todos</option>
                                <option value="IDFO">IDFO</option>
                                <option value="UGT">UGT</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Certificables</label>
                            <select name="certificables" class="form-control-edit">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-custom span-2">
                            <label>Modalidad</label>
                            <select name="modalidad" class="form-control-edit">
                                <option value="">---</option>
                                <option value="Teleformación">Teleformación</option>
                                <option value="Distancia">Distancia</option>
                                <option value="Mixta">Mixta</option>
                                <option value="Presencial">Presencial</option>
                                <option value="Semipresencial">Semipresencial</option>
                                <option value="Excepto presencial">Excepto presencial</option>
                            </select>
                        </div>

                        <!-- Fechas v2 -->
                        <div class="form-group-custom span-3">
                            <label>Inicio desde / hasta</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="inicio_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="inicio_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>25% desde / hasta</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="pct25_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="pct25_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Mitad desde / hasta</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="mitad_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="mitad_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Fin desde</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" name="fin_desde" class="form-control-edit" placeholder="Desde" style="flex: 1;">
                                <input type="text" name="fin_hasta" class="form-control-edit" placeholder="Hasta" style="flex: 1;">
                            </div>
                        </div>

                        <!-- Checkboxes / Toggles -->
                        <div class="form-group-custom span-4" style="flex-direction: row; align-items: center; gap: 8px; padding-top: 15px;">
                            <input type="checkbox" name="sin_grupo" id="sin_grupo" style="width: 17px; height: 17px;">
                            <label for="sin_grupo" style="cursor: pointer; margin: 0; color: var(--text-muted); font-weight: bold;">Mostrar alumnos sin grupo</label>
                        </div>
                        <div class="form-group-custom span-4" style="flex-direction: row; align-items: center; gap: 8px; padding-top: 15px;">
                            <input type="checkbox" name="no_conec_15" id="no_conec_15_def" style="width: 17px; height: 17px;">
                            <label for="no_conec_15_def" style="cursor: pointer; margin: 0; color: #ef4444; font-weight: bold;">No conectados antes del 15%</label>
                        </div>
                        <div class="form-group-custom span-4" style="flex-direction: row; align-items: center; gap: 8px; padding-top: 15px;">
                            <input type="checkbox" name="no_conec_25" id="no_conec_25_def" style="width: 17px; height: 17px;">
                            <label for="no_conec_25_def" style="cursor: pointer; margin: 0; color: #ef4444; font-weight: bold;">No conectados antes del 25%</label>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0 2.5rem; height: 42px;"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS -->
            <div class="card-premium" style="margin-top: 20px;">
                <div class="card-header-premium">
                    <h2><i class="fas fa-list-alt"></i> Resultado de la Búsqueda</h2>
                    <div style="font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="ord_mult_3" style="width: 16px; height: 16px;">
                        <label for="ord_mult_3" style="color: var(--text-muted); cursor: pointer;">Ordenar múltiple</label>
                    </div>
                </div>

                <div class="status-header">
                    <div class="status-box bg-orange">Curso suspendido</div>
                    <div class="status-box bg-cyan">Curso regalo</div>
                    <div class="status-box bg-pink">Grupo 1</div>
                    <div class="status-box bg-pink">Grupo 2</div>
                    <div class="status-box bg-orange" style="color:#000;">Colec. prio.</div>
                    <div class="status-box bg-cyan" style="color:#000;">Bonificado</div>
                    <div class="status-box bg-green">No valido</div>
                </div>

                <div class="table-responsive">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Plan</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Ac/Gr</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Curso</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Alumno</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>NIF</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Empresa</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha ins</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Inicio</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>25%</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Mitad</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fin</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Estado</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Ult Fecha Mat.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Ultima conex</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha ult. llam.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>N.Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>I.Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>F.Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha final. alumno</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Doc conexión</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Encu</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Dipl</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha Dipl.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Doc. inic. pte</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Datos ptes</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Docu alumno pte</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Docu curso pte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="28" style="text-align: center; padding: 3rem; color: #94a3b8; font-style: italic;">
                                    Utilice los filtros para realizar una búsqueda.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; // End view check ?>

        </main>
    </div>
</body>
</html>
