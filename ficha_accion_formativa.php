<?php
// ficha_accion_formativa.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    die("No tiene permisos suficientes. Su rol actual es: " . ($_SESSION['rol_nombre'] ?? 'Desconocido'));
}

// Moodle tracking variables
$alumnos_seguimiento = [];
$moodle_connected = false;
$moodle_error = '';

if (!function_exists('format_connected_time')) {
    function format_connected_time($seconds) {
        if (empty($seconds) || $seconds <= 0) return '---';
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "{$hours}h {$minutes}m";
    }
}

// Fetch plans for the dropdown
$planes = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, codigo FROM planes ORDER BY nombre ASC");
    if ($stmt) { $planes = $stmt->fetchAll(); }
} catch (Throwable $e) { }

$modalidades = ['Teleformacion', 'Presencial', 'Mixta', 'Aula Virtual'];
$niveles = ['Básico', 'Medio', 'Medio-superior', 'Superior'];
$prioridades = ['Alta', 'Media', 'Baja'];
$estados = ['No programable', 'Programable', 'En curso', 'Finalizado'];

$familias = [
    'Actividades Físicas y Deportivas',
    'Actividades y Competencias Transversales',
    'Administración y Gestión',
    'Agraria',
    'Artes Gráficas',
    'Artes y Artesanías',
    'Comercio y Marketing',
    'Edificación y Obra Civil',
    'Electricidad y Electrónica',
    'Energía y Agua',
    'Fabricación Mecánica',
    'Hostelería y Turismo',
    'Imagen Personal',
    'Imagen y Sonido',
    'Industrias Alimentarias',
    'Industrias Extractivas',
    'Informática y Comunicaciones',
    'Instalación y Mantenimiento',
    'Inteligencia Artificial y Data',
    'Madera, Mueble y Corcho',
    'Marítimo-Pesquera',
    'Química',
    'Sanidad',
    'Seguridad y Medio Ambiente',
    'Servicios Socioculturales y a la Comunidad',
    'Textil, Confección y Piel',
    'Transporte y Mantenimiento de Vehículos',
    'Vidrio y Cerámica',
    'Transversal',
    'Certificado de Profesionalidad',
    'Prevención de Riesgos Laborales',
    'SAP',
    'Seguridad Privada'
];

// Fetch existing action if ID is provided
$accion = [];
$grupos = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
        $stmt->execute([$id]);
        $accion = $stmt->fetch();

        // Fetch groups
        $stmtGrupos = $pdo->prepare("SELECT g.*, e.nombre as centro_nombre, CONCAT(a.nombre, ' ', a.primer_apellido, ' ', COALESCE(a.segundo_apellido, '')) as tutor_nombre 
                                    FROM grupos g 
                                    LEFT JOIN empresas e ON g.centro_id = e.id 
                                    LEFT JOIN alumnos a ON g.tutor_id = a.id 
                                    WHERE g.accion_id = ? 
                                    ORDER BY g.creado_en DESC");
        $stmtGrupos->execute([$id]);
        $grupos = $stmtGrupos->fetchAll();

        // Fetch Moodle Tracking Stats
        $stmtSeguimiento = $pdo->prepare("SELECT a.id as alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.moodle_user_id, 
                                            m.moodle_first_access, m.moodle_last_access, m.moodle_connected_time, m.moodle_progress, m.moodle_last_sync,
                                            m.moodle_m1_completed, m.moodle_m2_completed, m.moodle_m3_completed,
                                            m.moodle_e1_completed, m.moodle_e2_completed, m.moodle_e3_completed,
                                            m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade,
                                            m.moodle_final_grade, m.moodle_aptitud,
                                            g.numero_grupo, m.estado as matricula_estado
                                          FROM matriculas m
                                          JOIN alumnos a ON m.alumno_id = a.id
                                          JOIN grupos g ON m.grupo_id = g.id
                                          WHERE g.accion_id = ? AND m.estado != 'Baja'
                                          ORDER BY a.nombre ASC, a.primer_apellido ASC");
        $stmtSeguimiento->execute([$id]);
        $alumnos_seguimiento = $stmtSeguimiento->fetchAll();

        // Moodle DB Connection Status check
        require_once 'includes/moodle_db.php';
        $moodleDb = new MoodleDB();
        $moodle_connected = $moodleDb->isConnected();
        $moodle_error = $moodleDb->getError();

    } catch (Throwable $e) { }
}

// Fetch users for Responsables and Tutores
$usuarios = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, apellidos FROM usuarios ORDER BY nombre ASC");
    if ($stmt) { $usuarios = $stmt->fetchAll(); }
} catch (Throwable $e) { }

// Fetch formadores for the Teleformador dropdown (using usuarios with role Formador/Tutor)
$formadores = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.nombre, u.apellidos as primer_apellido FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%') AND u.activo = 1 ORDER BY u.nombre ASC");
    if ($stmt) { $formadores = $stmt->fetchAll(); }
} catch (Throwable $e) { }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Acción Formativa | Intranet Edite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        /* Premium Page Header (Glassmorphic) */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 1.25rem 2rem;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
        }

        .header-title h1 {
            color: #b91c1c; /* Rojo corporativo */
            font-size: 1.2rem;
            margin: 0;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn-group-header {
            display: flex;
            gap: 10px;
        }

        .btn-header {
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            height: 38px;
            box-sizing: border-box;
            cursor: pointer;
        }

        .btn-header:hover {
            background: #fef2f2;
            border-color: rgba(185, 28, 28, 0.3);
            color: #b91c1c;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.08);
        }

        .btn-header[form="main-form"] {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.2);
        }

        .btn-header[form="main-form"]:hover {
            background: linear-gradient(135deg, #a11717, #7f1616);
            color: #ffffff;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 16px rgba(185, 28, 28, 0.3);
        }

        .course-title-display {
            text-align: center;
            font-weight: 800;
            font-size: 1.15rem;
            margin: 24px 0;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Glassmorphic Tabs Container */
        .tabs-container {
            border: 1px solid var(--border-color);
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .tabs-header {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            padding: 0 1rem;
            gap: 5px;
        }

        .tab-btn {
            padding: 14px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.2s ease;
            position: relative;
        }

        .tab-btn:hover {
            color: #b91c1c;
            background: rgba(185, 28, 28, 0.02);
        }

        .tab-btn.active {
            color: #b91c1c;
            font-weight: 700;
            background: #ffffff;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #b91c1c;
            border-radius: 9999px 9999px 0 0;
        }

        .tab-content {
            padding: 2.25rem 2rem;
        }

        /* Subtabs inside Contenidos */
        .subtabs-header {
            display: flex;
            gap: 10px;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .subtab-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .subtab-btn:hover {
            color: #b91c1c;
            border-color: rgba(185, 28, 28, 0.2);
            background: #fef2f2;
        }

        .subtab-btn.active {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 4px 10px rgba(185, 28, 28, 0.15);
        }

        .subtab-btn i {
            font-size: 0.85rem;
        }

        .subtab-content {
            animation: fadeInSub 0.3s ease;
        }

        @keyframes fadeInSub {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section-title {
            text-align: center;
            color: #b91c1c;
            font-weight: 800;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -12px 1.5rem -12px;
        }

        .form-col {
            padding: 0 12px;
            box-sizing: border-box;
        }

        .form-group label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 0.45rem;
            color: #475569; /* Gris pizarra consistente */
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            background: rgba(248, 250, 252, 0.8);
            color: var(--text-color);
            transition: all 0.2s ease;
            box-sizing: border-box;
            height: 38px;
            outline: none;
        }

        .form-group select {
            width: 100%;
            padding: 0 2.5rem 0 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            background: rgba(248, 250, 252, 0.8);
            color: var(--text-color);
            transition: all 0.2s ease;
            box-sizing: border-box;
            height: 38px;
            outline: none;
            line-height: 36px;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.12);
            background: #ffffff;
        }

        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg fill='%23475569' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/><path d='M0 0h24v24H0z' fill='none'/></svg>") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 1.25rem !important;
            padding-right: 2.5rem !important;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 100%;
            padding-top: 1.7rem;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .checkbox-group input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: #b91c1c;
        }

        .btn-footer-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 2rem;
            padding: 1.75rem;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            border-radius: 0 0 16px 16px;
        }

        .btn-save {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            border: none;
            padding: 0.65rem 2.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            color: #fff;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.2);
            height: 40px;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #a11717, #7f1616);
            transform: translateY(-1.5px);
            box-shadow: 0 6px 16px rgba(185, 28, 28, 0.3);
        }

        .btn-back {
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 0.65rem 2.5rem;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-color);
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            box-sizing: border-box;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .btn-back:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        /* Custom Scrollbars */
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

        /* Responsive Styles */
        @media (max-width: 768px) {
            .form-col { width: 100% !important; }
            .tabs-header { flex-wrap: wrap; }
        }

        /* Tablas y Badges */
        .table-grupos-ficha {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.8rem;
        }
        .table-grupos-ficha th {
            background: #f8fafc;
            color: #475569;
            padding: 12px 16px;
            text-align: left;
            text-transform: uppercase;
            font-size: 0.72rem;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
            border-top: 1px solid var(--border-color);
            letter-spacing: 0.05em;
        }
        .table-grupos-ficha td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-color);
            vertical-align: middle;
            background-color: #ffffff;
        }
        .table-grupos-ficha tr:hover td {
            background: #f8fafc;
        }
        .table-grupos-ficha tr:last-child td {
            border-bottom: none;
        }

        .badge-ficha {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-valido { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-progra { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

        .btn-add-sector {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%) !important;
            color: white !important;
            border: none;
            padding: 10px 24px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.15);
            transition: all 0.3s ease;
        }

        .btn-add-sector:hover {
            transform: translateY(-1.5px);
            box-shadow: 0 6px 16px rgba(0, 108, 228, 0.25);
        }

        /* Sectores Table */
        .sectores-table-container {
            margin-top: 2rem;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: var(--card-shadow);
        }

        .sectores-table-header {
            background: #f8fafc;
            padding: 14px;
            text-align: center;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border-color);
        }

        .sectores-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .sectores-table th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 16px;
            font-size: 0.72rem;
            color: #475569;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sectores-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 12px 16px;
            text-align: center;
            color: var(--text-color);
            background: #ffffff;
        }

        .sectores-table tr:hover td {
            background: #f8fafc;
        }

        .sectores-table tr:last-child td {
            border-bottom: none;
        }

        /* Moodle Banners (Glassmorphic) */
        .moodle-status-banner {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 1.2rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            line-height: 1.5;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.01);
        }
        .moodle-status-banner.success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .moodle-status-banner.warning {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .moodle-status-banner .status-icon {
            font-size: 1.5rem;
        }
        .moodle-status-banner .status-info {
            display: flex;
            flex-direction: column;
        }
        .moodle-status-banner .status-info strong {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        /* Stats Seguimiento Moodle */
        .stats-seguimiento {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card-seguimiento {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        .stat-card-seguimiento:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .stat-card-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .bg-red-corp { background-color: #b91c1c; }
        .bg-blue-corp { background-color: #1e3a8a; }
        .bg-green-corp { background-color: #10b981; }
        .bg-amber-corp { background-color: #f59e0b; }
        
        .stat-card-info {
            display: flex;
            flex-direction: column;
        }
        .stat-card-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-color);
        }
        .stat-card-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 0.25rem;
            letter-spacing: 0.5px;
        }

        /* Moodle Seguimiento Table */
        .table-seguimiento-moodle {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.82rem;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .table-seguimiento-moodle th {
            background: #f8fafc;
            color: #475569;
            padding: 12px 16px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.72rem;
            border-bottom: 2px solid #e2e8f0;
            letter-spacing: 0.05em;
        }
        .table-seguimiento-moodle td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-color);
            vertical-align: middle;
            background: #ffffff;
        }
        .table-seguimiento-moodle tr:hover td {
            background-color: #f8fafc;
        }
        .table-seguimiento-moodle tr:last-child td {
            border-bottom: none;
        }
        
        .alumno-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alumno-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: #fff;
            text-shadow: 0 1px 1px rgba(0,0,0,0.1);
        }
        .avatar-a { background: linear-gradient(135deg, #ec4899, #f43f5e); }
        .avatar-b { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .avatar-c { background: linear-gradient(135deg, #10b981, #047857); }
        .avatar-d { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .avatar-e { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

        .alumno-info {
            display: flex;
            flex-direction: column;
        }
        .alumno-nombre {
            font-weight: 600;
            color: var(--text-color);
        }
        .alumno-email {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.1rem;
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 140px;
        }
        .progress-bar-wrapper {
            flex-grow: 1;
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease-in-out;
        }
        .progress-text {
            font-weight: 600;
            font-size: 0.8rem;
            min-width: 32px;
            text-align: right;
            color: var(--text-color);
        }
        
        .badge-sync {
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-sync.success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-sync.pending {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        
        .seguimiento-actions {
            margin-top: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
            background: #f8fafc;
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
        }
        .btn-sync-moodle {
            padding: 0.65rem 1.5rem;
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(185, 28, 28, 0.2);
            height: 38px;
            box-sizing: border-box;
        }
        .btn-sync-moodle:hover:not(:disabled) {
            background: linear-gradient(135deg, #a11717, #7f1616);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(185, 28, 28, 0.3);
        }
        .btn-sync-moodle:disabled {
            background: #cbd5e1;
            color: #64748b;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .sync-status-text {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .spinning {
            animation: spin 1s linear infinite;
        }

        .badge-content {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 22px;
            text-align: center;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            border: 1px solid #cbd5e1;
        }
        .badge-content.completed {
            background-color: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }
        .badge-content.pending {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }
    </style>
</head>
<body class="bg-light">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-title">
                <h1>FICHA DE ACCIÓN FORMATIVA - CONTRATOS PROGRAMA</h1>
            </div>
            <div class="btn-group-header">
                <a href="#" class="btn-header">Duplicar Acción Formativa</a>
                <a href="#" class="btn-header">Duplicar en Bonificados</a>
                <a href="#" class="btn-header">Peticiones</a>
                <button type="submit" form="main-form" class="btn-header">Guardar registro</button>
            </div>
        </header>

        <div class="course-title-display">
            <?= !empty($accion['titulo']) ? htmlspecialchars($accion['titulo']) : 'NUEVA ACCIÓN FORMATIVA' ?>
            <?= !empty($accion['abreviatura']) ? '('.htmlspecialchars($accion['abreviatura']).')' : '' ?>
        </div>
        <form id="main-form" method="POST" action="guardar_accion.php">
        <?php if (isset($_GET['success'])): ?>
            <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; text-align: center; border: 1px solid #166534;">
                ¡Acción formativa guardada con éxito!
            </div>
        <?php endif; ?>
        <?php if ($id): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <div class="tabs-container">
            <div class="tabs-header">
                <button type="button" class="tab-btn active" onclick="switchTab(event, 'datos-generales')">Datos Generales</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'grupos')">Grupos</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'contenidos')">Contenidos</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'material')">Material</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'gestion')">Gestión</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'ejecucion')">Ejecución</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'instalacion')">Instalación</button>
                <?php if ($id): ?>
                    <button type="button" class="tab-btn" onclick="switchTab(event, 'seguimiento-moodle')">Seguimiento Moodle</button>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="datos-generales">
                <div class="form-section-title">Datos Generales</div>
                
                
                    <div class="form-row">
                        <div class="form-group form-col" style="width: 60%;">
                            <label>Plan:</label>
                            <select name="plan_id">
                                <option value="">Seleccione un plan...</option>
                                <?php foreach ($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($accion['plan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['codigo']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-col" style="width: 25%;">
                            <label>Nivel:</label>
                            <select name="nivel">
                                <?php foreach ($niveles as $n): ?>
                                    <option value="<?= $n ?>" <?= ($accion['nivel'] ?? '') == $n ? 'selected' : '' ?>><?= $n ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>Prioridad:</label>
                            <select name="prioridad">
                                <option value=""></option>
                                <?php foreach ($prioridades as $pr): ?>
                                    <option value="<?= $pr ?>" <?= ($accion['prioridad'] ?? '') == $pr ? 'selected' : '' ?>><?= $pr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 40%;">
                            <label>Estado de la acción:</label>
                            <select name="estado">
                                <?php foreach ($estados as $e): ?>
                                    <option value="<?= $e ?>" <?= ($accion['estado'] ?? '') == $e ? 'selected' : '' ?>><?= $e ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <div class="checkbox-group">
                                <label>Últimas plazas</label>
                                <input type="checkbox" name="ultimas_plazas" <?= !empty($accion['ultimas_plazas']) ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>id plataforma:</label>
                            <input type="text" name="id_plataforma" value="<?= htmlspecialchars($accion['id_plataforma'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 50%;">
                            <label>Título:</label>
                            <input type="text" name="titulo" value="<?= htmlspecialchars($accion['titulo'] ?? 'ACREDITACIÓN DOCENTE PARA TELEFORMACIÓN') ?>">
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>Abreviatura:</label>
                            <input type="text" name="abreviatura" value="<?= htmlspecialchars($accion['abreviatura'] ?? 'ADT') ?>">
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>Nº Acción:</label>
                            <input type="number" name="num_accion" value="<?= htmlspecialchars($accion['num_accion'] ?? '0') ?>">
                        </div>
                        <div class="form-group form-col" style="width: 20%;">
                            <label>Grupos anteriores:</label>
                            <span style="font-size: 0.9rem; color: #00008b;">0</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 10%;">
                            <label>Duración:</label>
                            <input type="number" name="duracion" value="<?= htmlspecialchars($accion['duracion'] ?? '60') ?>">
                        </div>
                        <div class="form-group form-col" style="width: 10%;">
                            <label>P.:</label>
                            <input type="number" name="p" value="<?= htmlspecialchars($accion['p'] ?? '0') ?>">
                        </div>
                        <div class="form-group form-col" style="width: 10%;">
                            <label>D.:</label>
                            <input type="number" name="d" value="<?= htmlspecialchars($accion['d'] ?? '0') ?>">
                        </div>
                        <div class="form-group form-col" style="width: 10%;">
                            <label>T.:</label>
                            <input type="number" name="t" value="<?= htmlspecialchars($accion['t'] ?? '60') ?>">
                        </div>
                        <div class="form-group form-col" style="width: 30%;">
                            <label>Modalidad:</label>
                            <select name="modalidad">
                                <?php foreach ($modalidades as $m): ?>
                                    <option value="<?= $m ?>" <?= ($accion['modalidad'] ?? 'Teleformacion') == $m ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-group form-col" style="width: 100%;">
                            <label>Familia profesional:</label>
                            <select name="familia_profesional">
                                <option value=""></option>
                                <?php foreach ($familias as $fam): ?>
                                    <option value="<?= htmlspecialchars($fam) ?>" <?= ($accion['familia_profesional'] ?? '') == $fam ? 'selected' : '' ?>><?= htmlspecialchars($fam) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 50%;">
                            <label>Para presenciales:</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 0.85rem;">Horas teóricas:</span>
                                <input type="number" name="horas_teoricas" value="<?= htmlspecialchars($accion['horas_teoricas'] ?? '0') ?>" style="width: 80px;">
                                <span style="font-size: 0.85rem;">Horas prácticas:</span>
                                <input type="number" name="horas_practicas" value="<?= htmlspecialchars($accion['horas_practicas'] ?? '0') ?>" style="width: 80px;">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 50%;">
                            <label>Para cursos cortos:</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 0.85rem;">Días extra sin tutorización:</span>
                                <input type="number" name="dias_extra" value="<?= htmlspecialchars($accion['dias_extra'] ?? '0') ?>" style="width: 80px;">
                                <span style="font-size: 0.85rem;">Asignación:</span>
                                <select name="asignacion" style="width: 150px;">
                                    <option value="<?= htmlspecialchars($accion['asignacion'] ?? '') ?>"><?= htmlspecialchars($accion['asignacion'] ?? '') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
            </div>

            <div class="tab-content" id="grupos" style="display: none;">
                <div class="form-section-title">Grupos vinculados a esta acción</div>
                <div class="table-responsive">

                    <table class="table-grupos-ficha">
                        <thead>
                            <tr>
                                <th>Nº Grupo</th>
                                <th>Código Plataforma</th>
                                <th>Centro Impartición</th>
                                <th>F. Inicio</th>
                                <th>F. Fin</th>
                                <th>Alumnos (I/A/F)</th>
                                <th>Tutor / Docente</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($grupos)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding: 20px; color: #64748b;">No hay grupos vinculados a esta acción.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($grupos as $g): ?>
                                    <tr>
                                        <td style="font-weight:700;"><?= htmlspecialchars($g['numero_grupo']) ?></td>
                                        <td style="font-family:monospace;"><?= htmlspecialchars($g['codigo_plataforma']) ?></td>
                                        <td><?= htmlspecialchars($g['centro_nombre'] ?? '---') ?></td>
                                        <td><?= $g['fecha_inicio'] ? date('d/m/Y', strtotime($g['fecha_inicio'])) : '---' ?></td>
                                        <td><?= $g['fecha_fin'] ? date('d/m/Y', strtotime($g['fecha_fin'])) : '---' ?></td>
                                        <td style="text-align:center;">
                                            <span style="color:#1e40af;">0</span> / <span style="color:#166534;">0</span> / <span style="color:#64748b;">0</span>
                                        </td>
                                        <td style="font-weight:600;"><?= htmlspecialchars($g['tutor_nombre'] ?? '---') ?></td>
                                        <td><span class="badge-ficha badge-valido"><?= htmlspecialchars($g['situacion']) ?></span></td>
                                        <td>
                                            <div style="display:flex; gap:5px;">
                                                <a href="ficha_grupo_edicion.php?id=<?= $g['id'] ?>" style="color:#64748b;" title="Ver/Editar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                                <button type="button" onclick="deleteGrupo(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['numero_grupo'])) ?>')" style="background:none; border:none; padding:0; cursor:pointer; color:#ef4444;" title="Eliminar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:20px; text-align:center;">
                        <a href="ficha_grupo_edicion.php?accion_id=<?= $id ?>" class="btn-add-sector" style="background:var(--primary-color); color:white; border:none; padding:10px 20px; text-decoration:none; display:inline-block;">+ Crear Nuevo Grupo para esta Acción</a>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="contenidos" style="display: none;">
                <style>
                    .contenidos-wrapper {
                        max-width: 1000px;
                        margin: 0 auto;
                    }
                    .contenidos-header-box { 
                        border: 1px solid var(--border-color); 
                        padding: 20px; 
                        margin-bottom: 30px; 
                        background: #ffffff;
                        border-radius: 12px;
                        box-shadow: var(--card-shadow);
                    }
                    .contenidos-title-red { 
                        text-align: center; 
                        color: #b91c1c; 
                        font-weight: 800; 
                        font-size: 0.95rem; 
                        text-transform: uppercase; 
                        margin-bottom: 20px; 
                        letter-spacing: 1px;
                    }
                    .contenidos-row-blue { 
                        display: flex; 
                        justify-content: space-around;
                        padding: 12px 0;
                        border-top: 1px solid var(--border-color);
                    }
                    .label-blue-bold { 
                        color: var(--text-color); 
                        font-weight: 700; 
                        font-size: 0.85rem; 
                        display: flex; 
                        align-items: center; 
                        gap: 10px; 
                    }
                    .label-blue-bold input[type="checkbox"] {
                        width: 16px;
                        height: 16px;
                        accent-color: #b91c1c;
                        cursor: pointer;
                    }
                    .input-underline { 
                        border: none; 
                        border-bottom: 2px solid #b91c1c !important; 
                        background: transparent; 
                        width: 45px; 
                        text-align: center; 
                        font-weight: 700; 
                        color: #b91c1c; 
                        outline: none; 
                        padding: 2px;
                    }
                    
                    .section-title-blue { 
                        color: #1f2937; 
                        font-size: 1.1rem; 
                        font-weight: 700; 
                        margin-bottom: 12px; 
                        margin-top: 35px; 
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }
                    
                    .editor-container {
                        margin-bottom: 30px;
                    }

                    /* Toolbar Styles */
                    .editor-toolbar {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 4px;
                        background: #f8fafc;
                        border: 1px solid var(--border-color);
                        padding: 6px;
                        border-bottom: none;
                        align-items: center;
                        border-radius: 8px 8px 0 0;
                    }
                    .toolbar-btn {
                        padding: 6px 10px;
                        border: 1px solid transparent;
                        background: none;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: #475569;
                        border-radius: 6px;
                        transition: all 0.2s;
                        font-size: 0.85rem;
                    }
                    .toolbar-btn:hover { background: #e2e8f0; border-color: #cbd5e1; color: var(--primary-color); }
                    .toolbar-sep { width: 1px; height: 20px; background: #cbd5e1; margin: 0 6px; }
                    .toolbar-select { font-size: 0.8rem; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: white; outline: none; }
                    
                    .editor-textarea {
                        width: 100%;
                        height: 250px;
                        border: 1px solid var(--border-color);
                        border-radius: 0 0 8px 8px;
                        padding: 15px;
                        font-family: 'Inter', sans-serif;
                        font-size: 0.9rem;
                        color: var(--text-color);
                        resize: vertical;
                        outline: none;
                        box-sizing: border-box;
                        background: #ffffff;
                        transition: all 0.2s ease;
                    }
                    .editor-textarea:focus {
                        border-color: #b91c1c;
                        box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.08);
                    }
                    .textarea-grey { background: #f8fafc; border-radius: 8px !important; }

                    .hint-text {
                        font-size: 0.8rem; 
                        color: var(--text-muted); 
                        margin-bottom: 12px; 
                        font-weight: 500;
                        font-style: italic;
                    }
                    .link-units {
                        font-size: 0.85rem; 
                        color: var(--primary-color); 
                        font-weight: 700; 
                        text-decoration: underline; 
                        display: inline-block; 
                        margin-top: 12px;
                        transition: all 0.2s;
                    }
                    .link-units:hover { color: var(--primary-hover); }
                </style>

                <div class="contenidos-wrapper">
                    <!-- Subtabs Header -->
                    <div class="subtabs-header">
                        <button type="button" class="subtab-btn active" onclick="switchSubTab(event, 'sub-modulos')"><i class="fas fa-cubes"></i> Módulos y Horas</button>
                        <button type="button" class="subtab-btn" onclick="switchSubTab(event, 'sub-objetivos')"><i class="fas fa-bullseye"></i> Objetivos</button>
                        <button type="button" class="subtab-btn" onclick="switchSubTab(event, 'sub-contenidos-text')"><i class="fas fa-book-open"></i> Contenidos</button>
                        <button type="button" class="subtab-btn" onclick="switchSubTab(event, 'sub-otros')"><i class="fas fa-cogs"></i> Otros Datos</button>
                    </div>

                    <!-- Sub-tab 1: Módulos -->
                    <div id="sub-modulos" class="subtab-content">
                        <div class="contenidos-header-box">
                            <div class="contenidos-title-red">CONTENIDOS, OBJETIVOS...</div>
                            <div class="contenidos-row-blue">
                                <label class="label-blue-bold">Módulo Sensib. : <input type="checkbox" name="modulo_sensib" <?= !empty($accion['modulo_sensib']) ? 'checked' : '' ?>></label>
                                <label class="label-blue-bold">Módulo Alfab. : <input type="checkbox" name="modulo_alfab" <?= !empty($accion['modulo_alfab']) ? 'checked' : '' ?>></label>
                                <label class="label-blue-bold">Encuesta post . : <input type="checkbox" name="encuesta_post" <?= !empty($accion['encuesta_post']) ? 'checked' : '' ?>></label>
                            </div>
                            <div class="contenidos-row-blue" style="justify-content: flex-start; gap: 40px; padding-left: 10px;">
                                <label class="label-blue-bold">
                                    Duración del Módulo Int. Empresas: <input type="text" class="input-underline" name="dur_int_empresas" value="<?= htmlspecialchars($accion['dur_int_empresas'] ?? '___') ?>"> h.
                                </label>
                                <label class="label-blue-bold">
                                    Duración del Módulo emprendimiento: <input type="text" class="input-underline" name="dur_emprendimiento" value="<?= htmlspecialchars($accion['dur_emprendimiento'] ?? '___') ?>"> h.
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-tab 2: Objetivos -->
                    <div id="sub-objetivos" class="subtab-content" style="display:none;">
                        <div class="editor-container">
                            <h2 class="section-title-blue">Objetivos:</h2>
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" title="Deshacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg></button>
                                <button type="button" class="toolbar-btn" title="Rehacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Cut -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg></button>
                                <!-- Copy -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                <!-- Paste -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg></button>
                                <!-- Paste as text -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <select class="toolbar-select"><option>Párrafo</option></select>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-weight:900;">B</button>
                                <button type="button" class="toolbar-btn" style="font-style:italic; font-family:serif; font-size: 1.2rem;">I</button>
                                <button type="button" class="toolbar-btn">x²</button>
                                <button type="button" class="toolbar-btn">x₂</button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-size: 1.1rem; line-height: 1;">&rdquo;</button>
                                <!-- Unordered list -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></button>
                                <!-- Ordered list -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Align left -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg></button>
                                <!-- Align center -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg></button>
                                <!-- Align right -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg></button>
                                <!-- Justify -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg></button>
                                <!-- Language -->
                                <button type="button" class="toolbar-btn" title="Idioma"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Link -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></button>
                                <!-- Image -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></button>
                                <!-- Table -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Source code -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></button>
                            </div>
                            <textarea class="editor-textarea" name="objetivos"><?= htmlspecialchars($accion['objetivos'] ?? '') ?></textarea>
                        </div>

                        <div class="editor-container">
                            <h2 class="section-title-blue">Objetivos específicos:</h2>
                            <textarea class="editor-textarea textarea-grey" name="objetivos_especificos" style="height: 120px;"><?= htmlspecialchars($accion['objetivos_especificos'] ?? "Saber hacer X\nConocer Y\n...") ?></textarea>
                            <a href="editar_unidades.php" class="link-units">Ver / Editar Unidades</a>
                        </div>
                    </div>

                    <!-- Sub-tab 3: Contenidos -->
                    <div id="sub-contenidos-text" class="subtab-content" style="display:none;">
                        <div class="editor-container">
                            <h2 class="section-title-blue">Contenidos:</h2>
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" title="Deshacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg></button>
                                <button type="button" class="toolbar-btn" title="Rehacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Cut -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg></button>
                                <!-- Copy -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                <!-- Paste -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg></button>
                                <!-- Paste as text -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <select class="toolbar-select"><option>Párrafo</option></select>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-weight:900;">B</button>
                                <button type="button" class="toolbar-btn" style="font-style:italic; font-family:serif; font-size: 1.2rem;">I</button>
                                <button type="button" class="toolbar-btn">x²</button>
                                <button type="button" class="toolbar-btn">x₂</button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-size: 1.1rem; line-height: 1;">&rdquo;</button>
                                <!-- Unordered list -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></button>
                                <!-- Ordered list -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Align left -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg></button>
                                <!-- Align center -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg></button>
                                <!-- Align right -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg></button>
                                <!-- Justify -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg></button>
                                <!-- Language -->
                                <button type="button" class="toolbar-btn" title="Idioma"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Link -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></button>
                                <!-- Image -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></button>
                                <!-- Table -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <!-- Source code -->
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></button>
                            </div>
                            <textarea class="editor-textarea" name="contenidos" style="height: 350px;"><?= htmlspecialchars($accion['contenidos'] ?? '') ?></textarea>
                        </div>

                        <div class="editor-container">
                            <h2 class="section-title-blue">Contenidos breves:</h2>
                            <p class="hint-text">(Se mostrará en el apartado &ldquo;Contenidos&rdquo; en la ficha)</p>
                            <textarea class="editor-textarea textarea-grey" name="contenidos_breves" style="height: 120px;"><?= htmlspecialchars($accion['contenidos_breves'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Sub-tab 4: Otros Datos -->
                    <div id="sub-otros" class="subtab-content" style="display:none;">
                        <div class="editor-container">
                            <h2 class="section-title-blue">Qué aprenden los alumnos:</h2>
                            <p class="hint-text">(Se mostrará en el apartado &ldquo;Descripción del curso&rdquo; en la ficha)</p>
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" title="Deshacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg></button>
                                <button type="button" class="toolbar-btn" title="Rehacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <select class="toolbar-select"><option>Párrafo</option></select>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-weight:900;">B</button>
                                <button type="button" class="toolbar-btn" style="font-style:italic; font-family:serif; font-size: 1.2rem;">I</button>
                                <button type="button" class="toolbar-btn">x²</button>
                                <button type="button" class="toolbar-btn">x₂</button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-size: 1.1rem; line-height: 1;">&rdquo;</button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></path><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn" title="Idioma"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></button>
                            </div>
                            <textarea class="editor-textarea" name="que_aprenden"><?= htmlspecialchars($accion['que_aprenden'] ?? '') ?></textarea>
                        </div>

                        <div class="editor-container">
                            <h2 class="section-title-blue">Contenidos diploma FeS:</h2>
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" title="Deshacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg></button>
                                <button type="button" class="toolbar-btn" title="Rehacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <select class="toolbar-select"><option>Párrafo</option></select>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-weight:900;">B</button>
                                <button type="button" class="toolbar-btn" style="font-style:italic; font-family:serif; font-size: 1.2rem;">I</button>
                                <button type="button" class="toolbar-btn">x²</button>
                                <button type="button" class="toolbar-btn">x₂</button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-size: 1.1rem; line-height: 1;">&rdquo;</button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></path><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn" title="Idioma"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></button>
                            </div>
                            <textarea class="editor-textarea" name="contenidos_fes"><?= htmlspecialchars($accion['contenidos_fes'] ?? '') ?></textarea>
                        </div>

                        <div class="editor-container">
                            <h2 class="section-title-blue">Recursos de la acción:</h2>
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" title="Deshacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg></button>
                                <button type="button" class="toolbar-btn" title="Rehacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <select class="toolbar-select"><option>Párrafo</option></select>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-weight:900;">B</button>
                                <button type="button" class="toolbar-btn" style="font-style:italic; font-family:serif; font-size: 1.2rem;">I</button>
                                <button type="button" class="toolbar-btn">x²</button>
                                <button type="button" class="toolbar-btn">x₂</button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-size: 1.1rem; line-height: 1;">&rdquo;</button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></path><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn" title="Idioma"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></button>
                            </div>
                            <textarea class="editor-textarea" name="recursos_accion"><?= htmlspecialchars($accion['recursos_accion'] ?? '') ?></textarea>
                        </div>

                        <div class="editor-container">
                            <h2 class="section-title-blue">Demanda Mercado:</h2>
                            <div class="editor-toolbar">
                                <button type="button" class="toolbar-btn" title="Deshacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg></button>
                                <button type="button" class="toolbar-btn" title="Rehacer"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <select class="toolbar-select"><option>Párrafo</option></select>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-weight:900;">B</button>
                                <button type="button" class="toolbar-btn" style="font-style:italic; font-family:serif; font-size: 1.2rem;">I</button>
                                <button type="button" class="toolbar-btn">x²</button>
                                <button type="button" class="toolbar-btn">x₂</button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn" style="font-size: 1.1rem; line-height: 1;">&rdquo;</button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></path><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg></button>
                                <button type="button" class="toolbar-btn" title="Idioma"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></button>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></button>
                                <div class="toolbar-sep"></div>
                                <button type="button" class="toolbar-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg></button>
                            </div>
                            <textarea class="editor-textarea" name="demanda_mercado"><?= htmlspecialchars($accion['demanda_mercado'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="material" style="display: none;">
                <div class="form-section-title" style="color: #d32f2f; text-align: center; font-weight: bold; margin-bottom: 25px;">
                    DATOS DE MATERIAL, ENVÍOS...
                </div>
                
                <div class="info-grid" style="grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px;">
                    <div class="info-box">
                        <label class="info-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="hay_material" value="1" <?= ($accion['hay_material'] ?? 0) ? 'checked' : '' ?>>
                            HAY MATERIAL
                        </label>
                    </div>
                    <div class="info-box">
                        <label class="info-label">Nº ENTREGAS</label>
                        <input type="number" class="info-value" name="num_entregas" value="<?= htmlspecialchars($accion['num_entregas'] ?? '0') ?>">
                    </div>
                    <div class="info-box">
                        <label class="info-label">CÓDIGO ENTREGAS</label>
                        <select class="info-value" name="codigo_entregas">
                            <option value="">Seleccione...</option>
                            <option value="ESTANDAR" <?= ($accion['codigo_entregas'] ?? '') == 'ESTANDAR' ? 'selected' : '' ?>>ESTÁNDAR</option>
                            <option value="ESPECIAL" <?= ($accion['codigo_entregas'] ?? '') == 'ESPECIAL' ? 'selected' : '' ?>>ESPECIAL</option>
                        </select>
                    </div>
                    <div class="info-box">
                        <label class="info-label">Nº MÓDULOS</label>
                        <input type="number" class="info-value" name="num_modulos" value="<?= htmlspecialchars($accion['num_modulos'] ?? '0') ?>">
                    </div>
                </div>

                <div class="editor-container" style="margin-bottom: 25px;">
                    <h2 class="section-title-blue" style="font-size: 0.9rem; margin-bottom: 5px;">Detalle entregas:</h2>
                    <textarea class="editor-textarea textarea-grey" name="detalle_entregas" style="height: 100px;"><?= htmlspecialchars($accion['detalle_entregas'] ?? '') ?></textarea>
                </div>

                <div class="info-box" style="margin-bottom: 25px;">
                    <label class="section-title-blue" style="font-size: 0.9rem; margin-bottom: 15px; display: block;">Material:</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 0.85rem;">
                            <input type="checkbox" name="manual_curso" value="1" <?= ($accion['manual_curso'] ?? 0) ? 'checked' : '' ?>> MANUAL CURSO
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 0.85rem;">
                            <input type="checkbox" name="manual_sensibilizacion" value="1" <?= ($accion['manual_sensibilizacion'] ?? 0) ? 'checked' : '' ?>> MANUAL SENSIBILIZACIÓN
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 0.85rem;">
                            <input type="checkbox" name="carpeta_clasificadora" value="1" <?= ($accion['carpeta_clasificadora'] ?? 0) ? 'checked' : '' ?>> CARPETA CLASIFICADORA
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 0.85rem;">
                            <input type="checkbox" name="cuaderno_a4" value="1" <?= ($accion['cuaderno_a4'] ?? 0) ? 'checked' : '' ?>> CUADERNO A4
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 0.85rem;">
                            <input type="checkbox" name="boligrafo" value="1" <?= ($accion['boligrafo'] ?? 0) ? 'checked' : '' ?>> BOLÍGRAFO
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 0.85rem;">
                            <input type="checkbox" name="maletin" value="1" <?= ($accion['maletin'] ?? 0) ? 'checked' : '' ?>> MALETÍN
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 0.85rem;">
                            <input type="checkbox" name="otros_materiales" value="1" <?= ($accion['otros_materiales'] ?? 0) ? 'checked' : '' ?>> OTROS
                        </label>
                    </div>
                </div>

                <div class="editor-container">
                    <textarea class="editor-textarea textarea-grey" name="otros_materiales_txt" style="height: 100px;"><?= htmlspecialchars($accion['otros_materiales_txt'] ?? '') ?></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <input type="text" class="info-value" name="material_extra_info" style="width: 100%;" placeholder="...">
                </div>
            </div>
            
            <div class="tab-content" id="gestion" style="display: none;">
                <div class="form-section-title" style="color: #d32f2f;">DATOS PARA GESTIÓN, SEGUIMIENTO, ETC.</div>
                
                <!-- Responsables -->
                <div class="form-row">
                    <div class="form-group form-col" style="width: 33%;">
                        <label>Resp documentacion :</label>
                        <select name="resp_documentacion_id">
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($accion['resp_documentacion_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-col" style="width: 33%;">
                        <label>Resp seguimiento :</label>
                        <select name="resp_seguimiento_id">
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($accion['resp_seguimiento_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-col" style="width: 34%;">
                        <label>Resp dudas :</label>
                        <select name="resp_dudas_id">
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($accion['resp_dudas_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Tutores -->
                <div class="form-row">
                    <div class="form-group form-col" style="width: 40%; display: flex; align-items: flex-end; gap: 10px;">
                        <div style="flex-grow: 1;">
                            <label>Tutor 1 :</label>
                            <select name="tutor1_id">
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($accion['tutor1_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 5px;">
                            <label style="margin: 0; font-size: 0.75rem;">Activo:</label>
                            <input type="checkbox" name="tutor1_activo" value="1" <?= ($accion['tutor1_activo'] ?? 0) ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="form-group form-col" style="width: 40%; display: flex; align-items: flex-end; gap: 10px;">
                        <div style="flex-grow: 1;">
                            <label>Tutor 2 :</label>
                            <select name="tutor2_id">
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($accion['tutor2_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 5px;">
                            <label style="margin: 0; font-size: 0.75rem;">Activo:</label>
                            <input type="checkbox" name="tutor2_activo" value="1" <?= ($accion['tutor2_activo'] ?? 0) ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <!-- Otras consultoras -->
                <div class="form-row">
                    <div class="form-group form-col" style="width: 15%; display: flex; align-items: center; gap: 10px; padding-top: 15px;">
                        <label style="margin: 0;">Mostrar:</label>
                        <input type="checkbox" name="mostrar_otras_consultoras" value="1" <?= ($accion['mostrar_otras_consultoras'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                    <div class="form-group form-col" style="width: 35%;">
                        <label>Alumnos otras consultoras:</label>
                        <input type="text" name="alumnos_otras_consultoras" value="<?= htmlspecialchars($accion['alumnos_otras_consultoras'] ?? '') ?>">
                    </div>
                    <div class="form-group form-col" style="width: 50%;">
                        <label>Teleformador:</label>
                        <select name="teleformador_id">
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($formadores as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= ($accion['teleformador_id'] ?? '') == $f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['nombre'] . ' ' . $f['primer_apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Grupo, Email y Checks -->
                <div class="form-row">
                    <div class="form-group form-col" style="width: 20%;">
                        <label>Id grupo:</label>
                        <input type="text" name="id_grupo_gestion" value="<?= htmlspecialchars($accion['id_grupo_gestion'] ?? '') ?>">
                    </div>
                    <div class="form-group form-col" style="width: 40%;">
                        <label>e-mail tutor:</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="email" name="email_tutor_gestion" value="<?= htmlspecialchars($accion['email_tutor_gestion'] ?? '') ?>" style="flex-grow: 1;">
                            <button type="button" class="toolbar-btn" style="padding: 0 5px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"></path><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33 1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82 1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></button>
                        </div>
                    </div>
                    <div class="form-group form-col" style="width: 20%; display: flex; align-items: center; gap: 10px; padding-top: 15px;">
                        <label style="margin: 0;">Nuestra:</label>
                        <input type="checkbox" name="nuestra_check" value="1" <?= ($accion['nuestra_check'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                    <div class="form-group form-col" style="width: 20%; display: flex; align-items: center; gap: 10px; padding-top: 15px;">
                        <label style="margin: 0;">Prioritaria:</label>
                        <input type="checkbox" name="prioritaria_check" value="1" <?= ($accion['prioritaria_check'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                </div>

                <!-- Evaluaciones Base -->
                <div class="form-row" style="align-items: flex-end; gap: 20px; margin-bottom: 25px;">
                    <div class="form-group" style="width: 180px;">
                        <label style="color: #1e3a8a; font-weight: 700;">Nº EVALUACIONES:</label>
                        <input type="number" name="num_evaluaciones" value="<?= htmlspecialchars($accion['num_evaluaciones'] ?? '0') ?>" style="padding: 8px;">
                    </div>
                    <div style="display: flex; gap: 30px; padding-bottom: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e3a8a; font-weight: 700; font-size: 0.85rem;">
                             Recibí material 1: <input type="checkbox" name="recibi_material1" value="1" <?= ($accion['recibi_material1'] ?? 0) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e3a8a; font-weight: 700; font-size: 0.85rem;">
                             Recibí material 2: <input type="checkbox" name="recibi_material2" value="1" <?= ($accion['recibi_material2'] ?? 0) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                        </label>
                    </div>
                </div>

                <!-- Evaluaciones Detalle -->
                <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px;">
                    <?php for($i=1; $i<=4; $i++): ?>
                    <div style="display: grid; grid-template-columns: 160px 1fr; gap: 15px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 10px; color: #1e3a8a; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; cursor: pointer;">
                            <input type="checkbox" name="eval<?= $i ?>_check" value="1" <?= ($accion['eval' . $i . '_check'] ?? 0) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                            <?= $i ?>ª Evaluación:
                        </label>
                        <div class="form-group" style="margin: 0;">
                            <input type="text" name="eval<?= $i ?>_titulo" value="<?= htmlspecialchars($accion['eval' . $i . '_titulo'] ?? '') ?>" 
                                   placeholder="TÍTULO DE LA <?= $i ?>ª EVALUACIÓN" 
                                   style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; background: #f8fafc;">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Supuesto Práctico -->
                <div class="form-row">
                    <div class="form-group form-col" style="width: 100%;">
                        <label style="color: #1e3a8a; font-weight: 700; text-transform: uppercase;">Supuesto práctico:</label>
                        <input type="text" name="supuesto_practico" value="<?= htmlspecialchars($accion['supuesto_practico'] ?? '') ?>" 
                               placeholder="Título del supuesto práctico"
                               style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 4px; background: #f8fafc;">
                    </div>
                </div>

                <!-- Sistemas y Nivel -->
                <div class="form-row" style="gap: 20px;">
                    <div style="display: flex; align-items: center; gap: 30px; flex-grow: 1; padding-top: 15px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="margin:0; white-space: nowrap; font-weight: 600; color: #1e40af;">CONEXIA:</label>
                            <input type="checkbox" name="conexia_check" value="1" <?= ($accion['conexia_check'] ?? 0) ? 'checked' : '' ?> style="width: auto; margin: 0;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="margin:0; white-space: nowrap; font-weight: 600; color: #1e40af;">CAE:</label>
                            <input type="checkbox" name="cae_check" value="1" <?= ($accion['cae_check'] ?? 0) ? 'checked' : '' ?> style="width: auto; margin: 0;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label style="margin:0; white-space: nowrap; font-weight: 600; color: #1e40af;">EDITE:</label>
                            <input type="checkbox" name="edite_gestion_check" value="1" <?= ($accion['edite_gestion_check'] ?? 0) ? 'checked' : '' ?> style="width: auto; margin: 0;">
                        </div>
                    </div>
                    <div class="form-group" style="width: 15%;">
                        <label>Nivel:</label>
                        <input type="number" name="nivel_gestion" value="<?= htmlspecialchars($accion['nivel_gestion'] ?? '1') ?>">
                    </div>
                    <div class="form-group" style="width: 30%;">
                        <label>Paquete:</label>
                        <select name="paquete_gestion">
                            <option value="">-- Seleccione --</option>
                            <option value="Paquete 1" <?= ($accion['paquete_gestion'] ?? '') == 'Paquete 1' ? 'selected' : '' ?>>Paquete 1</option>
                        </select>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="form-row">
                    <div class="form-group form-col" style="width: 100%;">
                        <label style="font-weight: 800; text-decoration: underline;">Observaciones:</label>
                        <textarea name="observaciones_gestion" style="width:100%; height: 100px; border: 1px solid #cbd5e1; background: #f1f5f9; border-radius: 4px; padding: 10px;"
                        ><?= htmlspecialchars($accion['observaciones_gestion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="ejecucion" style="display: none;">
                <div class="form-section-title">Ejecución y Seguimiento</div>
                <div class="form-row">
                    <div class="form-group form-col" style="width: 100%;">
                        <label>Notas de Ejecución:</label>
                        <textarea class="editor-textarea textarea-grey" name="notas_ejecucion" style="height: 150px;"><?= htmlspecialchars($accion['notas_ejecucion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="instalacion" style="display: none;">
                <div class="form-section-title">Instalación y Logística</div>
                <div class="form-row">
                    <div class="form-group form-col" style="width: 100%;">
                        <label>Notas de Instalación:</label>
                        <textarea class="editor-textarea textarea-grey" name="notas_instalacion" style="height: 150px;"><?= htmlspecialchars($accion['notas_instalacion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <?php if ($id): ?>
            <div class="tab-content" id="seguimiento-moodle" style="display: none;">
                <?php if (empty($accion['id_plataforma'])): ?>
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); max-width: 600px; margin: 2rem auto;">
                        <div style="width: 64px; height: 64px; background: #fee2e2; color: #b91c1c; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                <path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5"></path>
                            </svg>
                        </div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 0.75rem 0;">Acción Formativa no creada en Moodle</h3>
                        <p style="color: #64748b; font-size: 0.9rem; line-height: 1.5; margin: 0 0 2rem 0;">
                            Esta acción formativa aún no está vinculada a ningún curso de Moodle. Puedes crear automáticamente el curso en la plataforma de Moodle utilizando el título <strong>(<?= htmlspecialchars($accion['titulo'] ?? '') ?>)</strong> y la abreviatura <strong>(<?= htmlspecialchars($accion['abreviatura'] ?? '') ?>)</strong> definidos en esta ficha.
                        </p>
                        
                        <div id="moodle-create-status-msg" style="margin-bottom: 1.5rem; font-size: 0.85rem; font-weight: 600;"></div>
                        
                        <button type="button" class="btn-sync-moodle" onclick="createMoodleCourse(<?= $id ?>)" style="background-color: #1e3a8a; border-color: #172554;">
                            <svg class="create-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="transition: transform 0.2s; margin-right: 8px;">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Crear Curso en Moodle
                        </button>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div class="form-section-title" style="margin: 0; padding: 0; border: none;">Seguimiento de Alumnos en Moodle</div>
                        <?php
                        $last_sync_time = '---';
                        foreach ($alumnos_seguimiento as $al) {
                            if ($al['moodle_last_sync']) {
                                if ($last_sync_time == '---' || strtotime($al['moodle_last_sync']) > strtotime($last_sync_time)) {
                                    $last_sync_time = date('d/m/Y H:i:s', strtotime($al['moodle_last_sync']));
                                }
                            }
                        }
                        ?>
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600; background: #e2e8f0; padding: 4px 12px; border-radius: 4px;">
                            Última Sincronización: <strong><?= $last_sync_time ?></strong>
                        </span>
                    </div>

                    <?php if ($moodle_connected): ?>
                        <div class="moodle-status-banner success">
                            <div class="status-icon">✓</div>
                            <div class="status-info">
                                <strong>Conexión Moodle Activa</strong>
                                <span>El sistema está conectado a la base de datos de Moodle. Los tiempos y calificaciones se calculan a partir de la plataforma oficial.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="moodle-status-banner warning">
                            <div class="status-icon">⚠️</div>
                            <div class="status-info">
                                <strong>Modo Simulación Activo</strong>
                                <span>No se pudo conectar a la base de datos de Moodle (<?= htmlspecialchars($moodle_error) ?>). Mostrando datos de seguimiento simulados para demostración.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tarjetas de Estadísticas -->
                    <?php
                    $total_alumnos = count($alumnos_seguimiento);
                    $suma_progreso = 0;
                    $suma_tiempo = 0;
                    $aptos_count = 0;

                    foreach ($alumnos_seguimiento as $al) {
                        $suma_progreso += (int)$al['moodle_progress'];
                        $suma_tiempo += (int)$al['moodle_connected_time'];
                        if ($al['moodle_aptitud'] === 'APTO') {
                            $aptos_count++;
                        }
                    }

                    $avg_progreso = $total_alumnos > 0 ? round($suma_progreso / $total_alumnos) : 0;
                    $avg_tiempo_seg = $total_alumnos > 0 ? round($suma_tiempo / $total_alumnos) : 0;
                    $avg_tiempo_formatted = format_connected_time($avg_tiempo_seg);
                    ?>

                    <div class="stats-seguimiento">
                        <div class="stat-card-seguimiento">
                            <div class="stat-card-icon-wrapper bg-blue-corp">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                            <div class="stat-card-info">
                                <span class="stat-card-value"><?= $total_alumnos ?></span>
                                <span class="stat-card-label">Alumnos Matriculados</span>
                            </div>
                        </div>
                        
                        <div class="stat-card-seguimiento">
                            <div class="stat-card-icon-wrapper bg-green-corp">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            </div>
                            <div class="stat-card-info">
                                <span class="stat-card-value"><?= $avg_progreso ?>%</span>
                                <span class="stat-card-label">Progreso Promedio (% Curso)</span>
                            </div>
                        </div>

                        <div class="stat-card-seguimiento">
                            <div class="stat-card-icon-wrapper bg-amber-corp">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                            </div>
                            <div class="stat-card-info">
                                <span class="stat-card-value"><?= $avg_tiempo_formatted ?></span>
                                <span class="stat-card-label">Tiempo Conexión Promedio</span>
                            </div>
                        </div>

                        <div class="stat-card-seguimiento">
                            <div class="stat-card-icon-wrapper bg-red-corp">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                            </div>
                            <div class="stat-card-info">
                                <span class="stat-card-value"><?= $aptos_count ?> / <?= $total_alumnos ?></span>
                                <span class="stat-card-label">Alumnos Aptos</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Alumnos -->
                    <div style="overflow-x: auto; margin-top: 1.5rem; border-radius: 8px;">
                        <table class="table-seguimiento-moodle">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>DNI</th>
                                    <th>Grupo</th>
                                    <th style="text-align: center;">Moodle ID</th>
                                    <th>Accesos</th>
                                    <th style="text-align: center;">Horas Moodle</th>
                                    <th>% Curso</th>
                                    <th style="text-align: center;">Visualización</th>
                                    <th>Evaluaciones</th>
                                    <th>Controles %</th>
                                    <th style="text-align: center;">Nota Media</th>
                                    <th style="text-align: center;">Aptitud</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alumnos_seguimiento)): ?>
                                    <tr>
                                        <td colspan="12" style="text-align:center; padding: 40px; color: #64748b;">
                                            <p style="margin-bottom: 15px; font-weight: 500;">No hay alumnos matriculados en los grupos vinculados a esta acción formativa.</p>
                                            <button type="button" class="btn-sync-moodle" id="btn-empty-import-moodle" onclick="importMoodleStudents(<?= $id ?>)" style="background-color: #1e3a8a; border-color: #172554;">
                                                <svg class="import-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="transition: transform 0.2s; margin-right: 8px; display: inline-block; vertical-align: middle;">
                                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                                </svg>
                                                Importar Alumnos desde Moodle
                                            </button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $avatars = ['avatar-a', 'avatar-b', 'avatar-c', 'avatar-d', 'avatar-e'];
                                    foreach ($alumnos_seguimiento as $idx => $al): 
                                        $iniciales = strtoupper(substr($al['nombre'], 0, 1) . substr($al['primer_apellido'], 0, 1));
                                        $avatar_class = $avatars[$idx % count($avatars)];
                                        
                                        // % Curso color
                                        $p_color = '#ef4444'; // Red
                                        if ($al['moodle_progress'] >= 80) $p_color = '#10b981'; // Green
                                        elseif ($al['moodle_progress'] >= 40) $p_color = '#f59e0b'; // Amber

                                        // Controles %
                                        $evals_done = ($al['moodle_e1_completed'] ? 1 : 0) + ($al['moodle_e2_completed'] ? 1 : 0) + ($al['moodle_e3_completed'] ? 1 : 0);
                                        $controles_pct = round(($evals_done / 3) * 100);
                                        $c_color = '#ef4444';
                                        if ($controles_pct >= 100) $c_color = '#10b981';
                                        elseif ($controles_pct >= 33) $c_color = '#f59e0b';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="alumno-cell">
                                                    <div class="alumno-avatar <?= $avatar_class ?>"><?= $iniciales ?></div>
                                                    <div class="alumno-info">
                                                        <span class="alumno-nombre" style="white-space: nowrap;"><?= htmlspecialchars($al['nombre'] . ' ' . $al['primer_apellido'] . ($al['segundo_apellido'] ? ' ' . $al['segundo_apellido'] : '')) ?></span>
                                                        <span class="alumno-email"><?= htmlspecialchars($al['email']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-family: monospace; font-weight: 600; white-space: nowrap;"><?= htmlspecialchars($al['dni']) ?></td>
                                            <td style="font-weight: 700; color: #1e3a8a;">G<?= htmlspecialchars($al['numero_grupo']) ?></td>
                                            <td style="text-align: center; font-family: monospace; font-weight: 600;"><?= $al['moodle_user_id'] ?: '---' ?></td>
                                            <td style="font-size: 0.75rem; white-space: nowrap; line-height: 1.4;">
                                                Prim: <?= $al['moodle_first_access'] ? date('d/m H:i', strtotime($al['moodle_first_access'])) : '---' ?><br>
                                                Últ: <?= $al['moodle_last_access'] ? date('d/m H:i', strtotime($al['moodle_last_access'])) : '---' ?>
                                            </td>
                                            <td style="text-align: center; font-weight: 700; color: #1e293b; white-space: nowrap;"><?= format_connected_time($al['moodle_connected_time']) ?></td>
                                            <td>
                                                <div class="progress-container">
                                                    <div class="progress-bar-wrapper">
                                                        <div class="progress-bar-fill" style="width: <?= (int)$al['moodle_progress'] ?>%; background-color: <?= $p_color ?>;"></div>
                                                    </div>
                                                    <span class="progress-text"><?= (int)$al['moodle_progress'] ?>%</span>
                                                </div>
                                            </td>
                                            <td style="text-align: center; vertical-align: middle;">
                                                <div style="display: flex; gap: 4px; justify-content: center; align-items: center; min-height: 24px;">
                                                    <span class="badge-content <?= $al['moodle_m1_completed'] ? 'completed' : 'pending' ?>" title="Visualización M1 (Módulo 1 / Tema 1)">M1</span>
                                                    <span class="badge-content <?= $al['moodle_m2_completed'] ? 'completed' : 'pending' ?>" title="Visualización M2 (Módulo 2 / Tema 2)">M2</span>
                                                    <span class="badge-content <?= $al['moodle_m3_completed'] ? 'completed' : 'pending' ?>" title="Visualización M3 (Módulo 3 / Tema 3)">M3</span>
                                                </div>
                                            </td>
                                            <td style="font-size: 0.75rem; white-space: nowrap; line-height: 1.4; color: #475569;">
                                                E1: <strong><?= $al['moodle_e1_completed'] && $al['moodle_e1_grade'] !== null ? number_format($al['moodle_e1_grade'], 1) : '---' ?></strong><br>
                                                E2: <strong><?= $al['moodle_e2_completed'] && $al['moodle_e2_grade'] !== null ? number_format($al['moodle_e2_grade'], 1) : '---' ?></strong><br>
                                                E3: <strong><?= $al['moodle_e3_completed'] && $al['moodle_e3_grade'] !== null ? number_format($al['moodle_e3_grade'], 1) : '---' ?></strong>
                                            </td>
                                            <td>
                                                <div class="progress-container">
                                                    <div class="progress-bar-wrapper">
                                                        <div class="progress-bar-fill" style="width: <?= $controles_pct ?>%; background-color: <?= $c_color ?>;"></div>
                                                    </div>
                                                    <span class="progress-text"><?= $controles_pct ?>%</span>
                                                </div>
                                            </td>
                                            <td style="text-align: center; font-size: 0.95rem; font-weight: 800; color: #1e293b;">
                                                <?= ($al['moodle_final_grade'] !== null) ? number_format($al['moodle_final_grade'], 2) : '---' ?>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <?php 
                                                $apt = $al['moodle_aptitud'] ?: 'PENDIENTE';
                                                $apt_style = 'background-color: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db;'; // PENDIENTE default
                                                if ($apt === 'APTO') {
                                                    $apt_style = 'background-color: #dcfce7; color: #15803d; border: 1px solid #86efac;';
                                                } elseif ($apt === 'NO APTO') {
                                                    $apt_style = 'background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;';
                                                }
                                                ?>
                                                <span class="badge-sync" style="<?= $apt_style ?> font-weight: 700; text-transform: uppercase;">
                                                    <?= $apt ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botón de Sincronización -->
                    <div class="seguimiento-actions" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                        <button type="button" class="btn-sync-moodle" id="btn-sync-moodle-times" onclick="syncMoodleTimes(<?= $id ?>)" <?= empty($alumnos_seguimiento) ? 'disabled' : '' ?> title="<?= empty($alumnos_seguimiento) ? 'Primero debes importar alumnos' : 'Sincronizar tiempos' ?>">
                            <svg class="sync-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="transition: transform 0.2s;">
                                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                            </svg>
                            Sincronizar Tiempos Moodle
                        </button>
                        
                        <button type="button" class="btn-sync-moodle" id="btn-import-moodle-students" onclick="importMoodleStudents(<?= $id ?>)" style="background-color: #1e3a8a; border-color: #172554;">
                            <svg class="import-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="transition: transform 0.2s; margin-right: 8px;">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Importar Alumnos de Moodle
                        </button>
                        
                        <a href="pdf_informe_seguimiento.php?id=<?= $id ?>" target="_blank" class="btn-sync-moodle" style="background-color: #0f172a; border-color: #020617; text-decoration: none; color: white; display: inline-flex; align-items: center;" <?= empty($alumnos_seguimiento) ? 'onclick="event.preventDefault();"' : '' ?>>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right: 8px;">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Descargar Informe PDF
                        </a>
                        
                        <div id="sync-status-msg" class="sync-status-text">
                            <?php if (isset($_GET['sync_success'])): ?>
                                <span style="color:#166534;">✓ ¡Sincronización de tiempos y calificaciones finalizada correctamente!</span>
                            <?php elseif (isset($_GET['import_success'])): ?>
                                <span style="color:#166534;">✓ ¡Alumnos importados y sincronizados correctamente! (<?= (int)$_GET['imported_count'] ?> alumnos)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        </form>

        <div class="sectores-table-container">
            <div class="sectores-table-header">SECTORES</div>
            <table class="sectores-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">SECTOR</th>
                        <th style="width: 35%;">SOLICITANTE</th>
                        <th style="width: 35%;">CONVOCATORIA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="3">
                            <button class="btn-add-sector">Añadir nuevo sector</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function switchTab(event, tabId) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show the selected tab content
            const targetContent = document.getElementById(tabId);
            if (targetContent) {
                targetContent.style.display = 'block';
            }
            
            // Activate the clicked button
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
        }

        function switchSubTab(event, subTabId) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Hide all sub-tab contents
            document.querySelectorAll('.subtab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Deactivate all sub-tab buttons
            document.querySelectorAll('.subtab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show the selected sub-tab content
            const targetContent = document.getElementById(subTabId);
            if (targetContent) {
                targetContent.style.display = 'block';
            }
            
            // Activate the clicked button
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
        }

        // Moodle synchronization AJAX function
        function syncMoodleTimes(actionId) {
            const btn = document.getElementById('btn-sync-moodle-times') || document.querySelector('.btn-sync-moodle');
            const icon = btn.querySelector('.sync-icon');
            const msgDiv = document.getElementById('sync-status-msg');
            
            btn.disabled = true;
            icon.classList.add('spinning');
            msgDiv.innerHTML = '<span style="color:#475569;">Conectando con Moodle y calculando tiempos...</span>';
            
            const csrf = '<?= $_SESSION['csrf_token'] ?? '' ?>';
            
            fetch(`api_sync_moodle_times.php?id=${actionId}&csrf_token=${csrf}`)
                .then(response => response.json())
                .then(data => {
                    icon.classList.remove('spinning');
                    btn.disabled = false;
                    
                    if (data.success) {
                        msgDiv.innerHTML = `<span style="color:#166534;">✓ ${data.message}</span>`;
                        // Reload and redirect back to this tab
                        setTimeout(() => {
                            window.location.href = `ficha_accion_formativa.php?id=${actionId}&tab=seguimiento-moodle&sync_success=1`;
                        }, 1200);
                    } else {
                        msgDiv.innerHTML = `<span style="color:#991b1b;">❌ Error: ${data.error}</span>`;
                    }
                })
                .catch(error => {
                    icon.classList.remove('spinning');
                    btn.disabled = false;
                    msgDiv.innerHTML = '<span style="color:#991b1b;">❌ Error de conexión de red.</span>';
                    console.error('Error:', error);
                });
        }

        // Moodle student import AJAX function
        function importMoodleStudents(actionId) {
            const syncBtn = document.getElementById('btn-sync-moodle-times');
            const importBtn = document.getElementById('btn-import-moodle-students');
            const emptyBtn = document.getElementById('btn-empty-import-moodle');
            const msgDiv = document.getElementById('sync-status-msg');
            
            if (syncBtn) syncBtn.disabled = true;
            if (importBtn) importBtn.disabled = true;
            if (emptyBtn) emptyBtn.disabled = true;
            
            const activeIcon = importBtn ? importBtn.querySelector('.import-icon') : (emptyBtn ? emptyBtn.querySelector('.import-icon') : null);
            if (activeIcon) activeIcon.classList.add('spinning');
            
            msgDiv.innerHTML = '<span style="color:#475569;">Conectando con Moodle e importando alumnos...</span>';
            
            const csrf = '<?= $_SESSION['csrf_token'] ?? '' ?>';
            
            fetch(`api_import_moodle_students.php?id=${actionId}&csrf_token=${csrf}`)
                .then(response => response.json())
                .then(data => {
                    if (activeIcon) activeIcon.classList.remove('spinning');
                    if (syncBtn && document.querySelectorAll('.table-seguimiento-moodle tbody tr').length > 1) syncBtn.disabled = false;
                    if (importBtn) importBtn.disabled = false;
                    if (emptyBtn) emptyBtn.disabled = false;
                    
                    if (data.success) {
                        msgDiv.innerHTML = `<span style="color:#166534;">✓ ${data.message}</span>`;
                        // Reload and redirect back to this tab
                        setTimeout(() => {
                            window.location.href = `ficha_accion_formativa.php?id=${actionId}&tab=seguimiento-moodle&import_success=1&imported_count=${data.imported_count}`;
                        }, 1200);
                    } else {
                        msgDiv.innerHTML = `<span style="color:#991b1b;">❌ Error: ${data.error}</span>`;
                    }
                })
                .catch(error => {
                    if (activeIcon) activeIcon.classList.remove('spinning');
                    if (syncBtn) syncBtn.disabled = false;
                    if (importBtn) importBtn.disabled = false;
                    if (emptyBtn) emptyBtn.disabled = false;
                    msgDiv.innerHTML = '<span style="color:#991b1b;">❌ Error de conexión de red.</span>';
                    console.error('Error:', error);
                });
        }

        // Moodle course creation AJAX function
        function createMoodleCourse(actionId) {
            const btn = document.querySelector('#seguimiento-moodle button');
            const icon = btn.querySelector('.create-icon');
            const msgDiv = document.getElementById('moodle-create-status-msg');
            
            btn.disabled = true;
            if (icon) icon.classList.add('spinning');
            msgDiv.innerHTML = '<span style="color:#475569;">Creando curso en Moodle, por favor espera...</span>';
            
            const csrf = '<?= $_SESSION['csrf_token'] ?? '' ?>';
            
            fetch(`api_create_moodle_course.php?id=${actionId}&csrf_token=${csrf}`)
                .then(response => response.json())
                .then(data => {
                    if (icon) icon.classList.remove('spinning');
                    
                    if (data.success) {
                        msgDiv.innerHTML = `<span style="color:#166534;">✓ ${data.message}</span>`;
                        // Reload and redirect back to this tab
                        setTimeout(() => {
                            window.location.href = `ficha_accion_formativa.php?id=${actionId}&tab=seguimiento-moodle`;
                        }, 1500);
                    } else {
                        btn.disabled = false;
                        msgDiv.innerHTML = `<span style="color:#991b1b;">❌ Error: ${data.error}</span>`;
                    }
                })
                .catch(error => {
                    if (icon) icon.classList.remove('spinning');
                    btn.disabled = false;
                    msgDiv.innerHTML = '<span style="color:#991b1b;">❌ Error de conexión de red.</span>';
                    console.error('Error:', error);
                });
        }

        // Borrar grupo AJAX
        function deleteGrupo(grupoId, numeroGrupo) {
            if (!confirm(`¿Estás seguro de que deseas eliminar el Grupo ${numeroGrupo}? Esta acción no se puede deshacer.`)) {
                return;
            }
            
            const csrf = '<?= $_SESSION['csrf_token'] ?? '' ?>';
            const formData = new FormData();
            formData.append('id', grupoId);
            formData.append('csrf_token', csrf);
            
            fetch('api_delete_grupo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Grupo borrado correctamente.');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error de red al intentar borrar el grupo.');
                console.error('Error:', error);
            });
        }

        // Initialize active tab from query param
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                // Find tab button that corresponds to this tabId
                const tabBtn = document.querySelector(`.tab-btn[onclick*="${tabParam}"]`);
                if (tabBtn) {
                    tabBtn.click();
                }
            }
        });
    </script>
</body>
</html>
