<?php
// alumnos.php - Versión Premium Unificada con Sincronización Moodle
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$moodle = new MoodleAPI($pdo);
$error = '';
$success = '';

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    try {
        // 1. Recoger datos locales
        $data = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'primer_apellido' => trim($_POST['primer_apellido'] ?? ''),
            'segundo_apellido' => trim($_POST['segundo_apellido'] ?? ''),
            'dni' => trim($_POST['dni'] ?? ''),
            'comercial_id' => !empty($_POST['comercial_id']) ? $_POST['comercial_id'] : null,
            'bloqueado' => isset($_POST['bloqueado']) ? 1 : 0,
            'restringido' => isset($_POST['restringido']) ? 1 : 0,
            'baja' => isset($_POST['baja']) ? 1 : 0,
            'alias' => trim($_POST['alias'] ?? ''),
            'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
            'seguridad_social' => trim($_POST['seguridad_social'] ?? ''),
            'profesion' => trim($_POST['profesion'] ?? ''),
            'sexo' => $_POST['sexo'] ?? 'Hombre',
            'estudios' => $_POST['estudios'] ?? '',
            'tipo_via' => $_POST['tipo_via'] ?? '',
            'nombre_via' => trim($_POST['nombre_via'] ?? ''),
            'tipo_num' => $_POST['tipo_num'] ?? '',
            'num_domicilio' => trim($_POST['num_domicilio'] ?? ''),
            'calificador' => $_POST['calificador'] ?? '',
            'bloque' => trim($_POST['bloque'] ?? ''),
            'portal' => trim($_POST['portal'] ?? ''),
            'escalera' => trim($_POST['escalera'] ?? ''),
            'planta' => trim($_POST['planta'] ?? ''),
            'puerta' => trim($_POST['puerta'] ?? ''),
            'complemento' => trim($_POST['complemento'] ?? ''),
            'domicilio' => trim($_POST['domicilio_full'] ?? ''),
            'cp' => trim($_POST['cp'] ?? ''),
            'localidad' => trim($_POST['localidad'] ?? ''),
            'provincia' => $_POST['provincia'] ?? '',
            'telefono' => trim($_POST['telefono'] ?? ''),
            'telefono_empresa' => trim($_POST['telefono_empresa'] ?? ''), // Usado para móvil en el form
            'mananas_desde' => trim($_POST['mananas_desde'] ?? ''),
            'mananas_hasta' => trim($_POST['mananas_hasta'] ?? ''),
            'tardes_desde' => trim($_POST['tardes_desde'] ?? ''),
            'tardes_hasta' => trim($_POST['tardes_hasta'] ?? ''),
            'solo_los' => trim($_POST['solo_los'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'email_2' => trim($_POST['email_2'] ?? ''),
            'ultima_empresa_id' => !empty($_POST['ultima_empresa_id']) ? $_POST['ultima_empresa_id'] : null,
            'centro_trabajo' => trim($_POST['centro_trabajo'] ?? ''),
            'enviar_emails' => isset($_POST['enviar_emails']) ? 1 : 0,
            'plat_usuario' => trim($_POST['plat_usuario'] ?? ''),
            'plat_clave' => trim($_POST['plat_clave'] ?? ''),
            'id_plat_2015' => trim($_POST['id_plat_2015'] ?? ''),
            'id_plat_2016' => trim($_POST['id_plat_2016'] ?? ''),
            'pref_presencial' => trim($_POST['pref_presencial'] ?? ''),
            'modulacion' => trim($_POST['modulacion'] ?? ''),
            'horarios' => trim($_POST['horarios'] ?? ''),
            'observaciones' => trim($_POST['observaciones'] ?? ''),
            'entrega_atencion' => trim($_POST['entrega_atencion'] ?? ''),
            'entrega_domicilio' => trim($_POST['entrega_domicilio'] ?? ''),
            'entrega_cp' => trim($_POST['entrega_cp'] ?? ''),
            'entrega_localidad' => trim($_POST['entrega_localidad'] ?? ''),
            'entrega_provincia' => $_POST['entrega_provincia'] ?? '',
            'creado_en' => date('Y-m-d H:i:s')
        ];

        // Validaciones básicas locales
        if (empty($data['nombre']) || empty($data['primer_apellido']) || empty($data['dni']) || empty($data['email'])) {
            throw new Exception("Nombre, Primer Apellido, NIF y Email son obligatorios.");
        }

        // 1.5 Comprobar duplicados en local antes de seguir
        $stmtCheckDni = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ?");
        $stmtCheckDni->execute([$data['dni']]);
        if ($stmtCheckDni->rowCount() > 0) {
            throw new Exception("Ya existe un alumno con el DNI '" . htmlspecialchars($data['dni']) . "' en el sistema.");
        }

        $stmtCheckEmail = $pdo->prepare("SELECT id FROM alumnos WHERE email = ?");
        $stmtCheckEmail->execute([$data['email']]);
        if ($stmtCheckEmail->rowCount() > 0) {
            throw new Exception("Ya existe un alumno con el Email '" . htmlspecialchars($data['email']) . "' en el sistema.");
        }

        // 2. Sincronización con Moodle (Opcional, no debe bloquear el registro local)
        $moodleUserId = null;
        if ($moodle->isConfigured()) {
            try {
                // Generar credenciales moodle si no vienen dadas
                $username = !empty($data['plat_usuario']) ? $data['plat_usuario'] : strtolower(explode('@', $data['email'])[0]) . '_' . substr($data['dni'], -3);
                $password = !empty($data['plat_clave']) ? $data['plat_clave'] : 'ef_' . strtoupper(substr($data['dni'], -4)) . '!' . rand(10,99);
                
                // Buscar si existe
                $moodleSearch = $moodle->getUsersByField('email', [$data['email']]);
                if (!empty($moodleSearch) && !empty($moodleSearch['users'])) {
                    $moodleUserId = $moodleSearch['users'][0]['id'];
                } else {
                    // Crear en Moodle
                    $moodleCreate = $moodle->createUser(
                        $username, 
                        $password, 
                        $data['nombre'], 
                        $data['primer_apellido'] . ' ' . $data['segundo_apellido'], 
                        $data['email']
                    );
                    if (isset($moodleCreate[0]['id'])) {
                        $moodleUserId = $moodleCreate[0]['id'];
                        // Actualizamos las claves en el array data para que se guarden localmente
                        if (empty($data['plat_usuario'])) $data['plat_usuario'] = $username;
                        if (empty($data['plat_clave'])) $data['plat_clave'] = $password;
                    }
                }
            } catch (Exception $mEx) {
                // No lanzamos excepcion para no bloquear, pero guardamos el error para avisar
                $moodleError = "Ocurrió un aviso en Moodle: " . $mEx->getMessage();
            }
        }
        
        // Añadir moodle_id al registro
        $data['moodle_user_id'] = $moodleUserId;

        // 3. Insertar en DB Local
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO alumnos ($columns) VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $nuevoId = $pdo->lastInsertId();

        audit_log($pdo, 'ALUMNO_CREADO', 'alumnos', $nuevoId, null, ['dni' => $data['dni'], 'moodle_id' => $moodleUserId]);
        
        // Redirigir a la ficha
        $mErrorParam = isset($moodleError) ? "&m_error=" . urlencode($moodleError) : "";
        header("Location: ficha_alumno.php?id=$nuevoId&success=1" . $mErrorParam);
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Control de pestaña activa
$active_tab = 'listado';
if ($_SERVER['REQUEST_METHOD'] == 'POST' || (isset($_GET['tab']) && $_GET['tab'] == 'nuevo')) {
    $active_tab = 'nuevo';
}

// Obtener estadísticas globales de alumnos
$totalAlumnos = 0;
$totalMoodle = 0;
try {
    $totalAlumnos = $pdo->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
    $totalMoodle = $pdo->query("SELECT COUNT(*) FROM alumnos WHERE moodle_user_id IS NOT NULL")->fetchColumn();
} catch (Exception $e) {
    // Silencioso
}

// Búsqueda y listado de alumnos
$search = trim($_GET['search'] ?? '');
$alumnosList = [];
try {
    $centro_filter = get_user_centro_filter('g.sede_id');
    
    // Si hay filtro de centro, necesitamos JOIN con matriculas y grupos
    $join_clause = "";
    $distinct = "";
    if ($centro_filter !== "1=1") {
        $join_clause = " JOIN matriculas m ON alumnos.id = m.alumno_id JOIN grupos g ON m.grupo_id = g.id ";
        $distinct = "DISTINCT";
    }

    if ($search !== '') {
        $stmtList = $pdo->prepare("SELECT $distinct alumnos.* FROM alumnos $join_clause WHERE (alumnos.nombre LIKE :search OR alumnos.primer_apellido LIKE :search OR alumnos.segundo_apellido LIKE :search OR alumnos.dni LIKE :search OR alumnos.email LIKE :search) AND $centro_filter ORDER BY alumnos.creado_en DESC LIMIT 100");
        $stmtList->execute(['search' => "%$search%"]);
    } else {
        $stmtList = $pdo->query("SELECT $distinct alumnos.* FROM alumnos $join_clause WHERE $centro_filter ORDER BY alumnos.creado_en DESC LIMIT 100");
    }
    $alumnosList = $stmtList->fetchAll();
} catch (Exception $e) {
    $error = "Error al cargar el listado de alumnos: " . $e->getMessage();
}

// Datos para Selects
$provincias = ["Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"];
$comerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll();
$empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumnos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-gray: #f1f5f9;
        }
        body { background-color: var(--bg-gray); font-size: 0.85rem; }
        .ficha-container { background: #fff; border: 1px solid var(--border-gray); padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .ficha-title { color: var(--title-red); font-weight: 800; text-align: center; text-transform: uppercase; border-bottom: 2px solid var(--border-gray); padding-bottom: 10px; margin-bottom: 20px; font-size: 1rem; }
        
        .form-section { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .form-section:last-child { border-bottom: none; }
        
        .field-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 10px; align-items: center; }
        .field-group { display: flex; align-items: center; gap: 5px; }
        .field-group label { font-weight: 700; color: var(--label-blue); white-space: nowrap; font-size: 0.75rem; }
        .field-group input, .field-group select, .field-group textarea { font-size: 0.8rem; padding: 3px 6px; border: 1px solid var(--border-gray); border-radius: 2px; }
        
        .label-red { color: var(--title-red) !important; font-weight: 800 !important; }
        .checkbox-group { display: flex; align-items: center; gap: 4px; font-weight: 700; color: var(--title-red); font-size: 0.75rem; }

        input[type="text"]:focus, select:focus { outline: none; border-color: var(--label-blue); box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1); }
        
        .section-header { font-weight: 800; color: var(--label-blue); text-transform: uppercase; margin-bottom: 12px; font-size: 0.7rem; border-left: 3px solid var(--label-blue); padding-left: 8px; }
        
        .btn-submit { background: #f8fafc; border: 1px solid var(--border-gray); padding: 6px 20px; font-weight: 700; cursor: pointer; border-radius: 3px; font-size: 0.8rem; }
        .btn-submit:hover { background: #e2e8f0; }

        /* Helpers Ancho */
        .w-60 { width: 60px; } .w-100 { width: 100px; } .w-150 { width: 150px; } .w-200 { width: 200px; } .w-250 { width: 250px; } .w-full { flex: 1; }
        
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Pestañas */
        .tabs-header {
            display: flex;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
            margin-bottom: 0;
        }
        .tab-btn {
            padding: 1rem 1.5rem;
            border: none;
            background: none;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            border-right: 1px solid var(--border-color);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
        }
        .tab-btn:hover {
            background: #f1f5f9;
        }
        .tab-btn.active {
            background: white;
            color: var(--primary-color);
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
        }
        .tab-panel {
            background: white;
            padding: 2rem;
            border-radius: 0 0 12px 12px;
            border: 1px solid var(--border-color);
            border-top: none;
            min-height: 400px;
        }

        /* Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon.primary {
            background: #eff6ff;
            color: var(--primary-color);
        }
        .stat-icon.success {
            background: #d1fae5;
            color: #059669;
        }
        .stat-info {
            flex: 1;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
            color: var(--text-color);
        }
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Buscador Rápido */
        .search-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            max-width: 400px;
            width: 100%;
        }
        .search-input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.75rem !important;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.15);
        }
        .search-icon {
            position: absolute;
            left: 0.875rem;
            width: 1.2rem;
            height: 1.2rem;
            color: var(--text-muted);
            pointer-events: none;
        }
        .search-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .search-btn:hover {
            background: var(--primary-hover);
        }

        /* Tabla de Alumnos */
        .table-responsive {
            overflow-x: auto;
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            position: relative;
        }
        .table-custom {
            width: 100%;
            min-width: 1100px;
            border-collapse: separate; /* Required for sticky border styling */
            border-spacing: 0;
            font-size: 0.85rem;
            text-align: left;
        }
        .table-custom th {
            background: #f8fafc;
            padding: 1rem;
            color: var(--text-color);
            font-weight: 700;
            border-bottom: 1px solid var(--border-color);
        }
        .table-custom td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
            vertical-align: middle;
            background-color: #ffffff;
        }
        .table-custom tr:last-child td {
            border-bottom: none;
        }
        /* Sticky first column (Nombre Completo) */
        .table-custom th:first-child,
        .table-custom td:first-child {
            position: sticky;
            left: 0;
            z-index: 10;
            box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.1);
        }
        .table-custom th:first-child {
            background-color: #f8fafc;
            z-index: 11;
        }
        .table-custom td:first-child {
            background-color: #ffffff;
        }
        .table-custom tr:hover td {
            background-color: #f8fafc;
        }
        
        /* Badges de Sincronización */
        .badge-sync {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-sync.active {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-sync.inactive {
            background: #f1f5f9;
            color: #475569;
        }
        
        /* Acciones */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            background: #eff6ff;
            color: var(--primary-color);
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-action:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Tabla responsive con scroll inferior nativo */
        .table-responsive {
            overflow-x: auto;
            overflow-y: visible;
            border-radius: 12px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) #e2e8f0;
        }
        .table-responsive::-webkit-scrollbar { height: 10px; }
        .table-responsive::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 5px; }
        .table-responsive::-webkit-scrollbar-thumb { background: var(--primary-color); border-radius: 5px; }

        /* Barra de scroll fija superior (inyectada por JS) */
        #fixedScrollBar {
            display: none;          /* oculta por defecto */
            position: fixed;
            z-index: 1000;
            overflow-x: auto;
            overflow-y: hidden;
            background: #fff;
            border-bottom: 2px solid #e2e8f0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.10);
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) #e2e8f0;
        }
        #fixedScrollBar::-webkit-scrollbar { height: 10px; }
        #fixedScrollBar::-webkit-scrollbar-track { background: #e2e8f0; }
        #fixedScrollBar::-webkit-scrollbar-thumb { background: var(--primary-color); border-radius: 5px; }
        #fixedScrollBar.visible { display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <?php if ($active_tab == 'listado'): ?>
                        <h1>Gestión de Alumnos</h1>
                        <p>Listado completo y matriculaciones en la intranet</p>
                    <?php else: ?>
                        <h1>Sincronización Moodle</h1>
                        <p>Alta de alumno con provisionamiento automático</p>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>Alumno eliminado correctamente y enviado a la Papelera.</span>
                </div>
            <?php endif; ?>

            <!-- Cabecera de Pestañas -->
            <nav class="tabs-header">
                <button class="tab-btn <?= $active_tab == 'listado' ? 'active' : '' ?>" onclick="location.href='?tab=listado'">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align: middle; margin-right: 6px; fill: currentColor;"><path d="M4 14h6v-6H4v6zm0 7h6v-6H4v6zm7 0h6v-6h-6v6zm0-14v6h6V7h-6z"/></svg>
                    Listado de Alumnos
                </button>
                <button class="tab-btn <?= $active_tab == 'nuevo' ? 'active' : '' ?>" onclick="location.href='?tab=nuevo'">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align: middle; margin-right: 6px; fill: currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Registrar Nuevo Alumno
                </button>
            </nav>

            <?php if ($active_tab == 'listado'): ?>
                <div class="tab-panel">
                    <!-- Tarjetas de Estadísticas -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon primary">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?= number_format($totalAlumnos) ?></div>
                                <div class="stat-label">Total Alumnos Registrados</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon success">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?= number_format($totalMoodle) ?></div>
                                <div class="stat-label">Sincronizados con Moodle</div>
                            </div>
                        </div>
                    </div>

                    <!-- Buscador Rápido -->
                    <form method="GET" class="search-container">
                        <input type="hidden" name="tab" value="listado">
                        <div class="search-box">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" name="search" class="search-input" placeholder="Buscar por Nombre, DNI, Email..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="search-btn">Buscar</button>
                            <?php if ($search !== ''): ?>
                                <a href="?tab=listado" class="btn-action" style="padding: 0.6rem 1.2rem; background: #f1f5f9; color: #475569; border-color: #cbd5e1; display: inline-flex; align-items: center; justify-content: center; height: auto;">Limpiar</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Tabla de Alumnos -->
                    <div class="table-scroll-wrapper">
                        <div class="table-responsive" id="tableResponsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>NIF / NIE</th>
                                    <th>Email Principal</th>
                                    <th>Teléfono</th>
                                    <th>Moodle Sync</th>
                                    <th>Fecha Alta</th>
                                    <th style="text-align: center; width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alumnosList)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                            No se encontraron alumnos registrados en el sistema.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alumnosList as $alumno): 
                                        $fullName = trim($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']);
                                        $phone = $alumno['telefono'] ?: ($alumno['telefono_empresa'] ?: '---');
                                        $dateAlta = $alumno['creado_en'] ? date('d/m/Y H:i', strtotime($alumno['creado_en'])) : '---';
                                        $isSynced = !empty($alumno['moodle_user_id']);
                                    ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--text-color);">
                                                <a href="ficha_alumno.php?id=<?= $alumno['id'] ?>" style="color: var(--primary-color); text-decoration: none;">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </a>
                                            </td>
                                            <td style="font-family: monospace; font-size: 0.9rem;"><?= htmlspecialchars($alumno['dni']) ?></td>
                                            <td><?= htmlspecialchars($alumno['email']) ?></td>
                                            <td><?= htmlspecialchars($phone) ?></td>
                                            <td>
                                                <?php if ($isSynced): ?>
                                                    <span class="badge-sync active" title="ID de Moodle: <?= $alumno['moodle_user_id'] ?>">
                                                        <span style="width: 6px; height: 6px; background: #10b981; border-radius: 50%;"></span>
                                                        Sincronizado
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-sync inactive">
                                                        <span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span>
                                                        Local
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($dateAlta) ?></td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                                    <a href="ficha_alumno.php?id=<?= $alumno['id'] ?>" class="btn-action" title="Ver ficha del alumno">
                                                        <svg viewBox="0 0 24 24" width="14" height="14" style="fill: currentColor; vertical-align: middle; margin-right: 2px;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                                        Ficha
                                                    </a>
                                                    <form method="POST" action="ficha_alumno.php?id=<?= $alumno['id'] ?>" style="display: inline; margin: 0;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar permanentemente a este alumno? Se archivará en la Papelera con todos sus documentos e inscripciones asociadas.');">
                                                        <input type="hidden" name="action" value="delete_alumno">
                                                        <button type="submit" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; border-radius: 6px; padding: 6px; cursor: pointer; display: inline-flex; align-items: center; transition: all 0.2s;" onmouseover="this.style.background='#fca5a5'" onmouseout="this.style.background='#fee2e2'" title="Eliminar Alumno">
                                                            <svg style="width: 14px; height: 14px; fill: currentColor;" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div><!-- /#tableResponsive -->
                    </div><!-- /.table-scroll-wrapper -->
                </div>
            <?php else: ?>
                <div class="tab-panel">
                    <form method="POST" class="ficha-container" style="border: none; padding: 0; box-shadow: none; margin: 0;">
                        <input type="hidden" name="action" value="create">
                        <div class="ficha-title">Ficha de Inscripción / Alta de Alumno</div>

                <!-- SECCIÓN 1: DATOS PERSONALES -->
                <div class="form-section">
                    <div class="section-header">Datos Personales y de Control</div>
                    <div class="field-row">
                        <div class="field-group">
                            <label class="label-red">NOMBRE *</label>
                            <input type="text" name="nombre" class="w-150" required>
                        </div>
                        <div class="field-group">
                            <label class="label-red">1º APELLIDO *</label>
                            <input type="text" name="primer_apellido" class="w-150" required>
                        </div>
                        <div class="field-group">
                            <label>2º APELLIDO</label>
                            <input type="text" name="segundo_apellido" class="w-150">
                        </div>
                        <div class="field-group">
                            <label class="label-red">NIF/NIE *</label>
                            <input type="text" name="dni" class="w-100" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label>COMERCIAL</label>
                            <select name="comercial_id" class="w-150">
                                <option value="">---</option>
                                <?php foreach ($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nombre'] . ' ' . $c['apellidos'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" name="bloqueado" id="bloqueado">
                            <label for="bloqueado">BLOQUEADO</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="restringido" id="restringido">
                            <label for="restringido">RESTRINGIDO</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="baja" id="baja">
                            <label for="baja">BAJA</label>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label>ALIAS</label>
                            <input type="text" name="alias" class="w-150">
                        </div>
                        <div class="field-group">
                            <label>F. NACIMIENTO</label>
                            <input type="date" name="fecha_nacimiento" style="padding: 1px 6px;">
                        </div>
                        <div class="field-group">
                            <label>Nº S. SOCIAL</label>
                            <input type="text" name="seguridad_social" class="w-100">
                        </div>
                        <div class="field-group">
                            <label>PROFESIÓN</label>
                            <input type="text" name="profesion" class="w-150">
                        </div>
                        <div class="field-group">
                            <label>SEXO</label>
                            <select name="sexo">
                                <option>Hombre</option>
                                <option>Mujer</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>ESTUDIOS</label>
                            <select name="estudios">
                                <option value="">---</option>
                                <option>Sin estudios</option>
                                <option>Primaria</option>
                                <option>ESO/EGB</option>
                                <option>Bachillerato</option>
                                <option>FP Grado Medio</option>
                                <option>FP Grado Superior</option>
                                <option>Universidad</option>
                                <option>Carnet Profesional</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: DIRECCIÓN -->
                <div class="form-section">
                    <div class="section-header">Domicilio y Contacto</div>
                    <div class="field-row">
                        <div class="field-group">
                            <label>TIPO VÍA</label>
                            <select name="tipo_via" class="w-100">
                                <option>Calle</option>
                                <option>Avenida</option>
                                <option>Plaza</option>
                                <option>Carretera</option>
                                <option>Paseo</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>NOMBRE VÍA</label>
                            <input type="text" name="nombre_via" class="w-250">
                        </div>
                        <div class="field-group">
                            <label>TIPO Nº</label>
                            <select name="tipo_num">
                                <option>Número</option>
                                <option>Kilómetro</option>
                                <option>Sin Número</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Nº</label>
                            <input type="text" name="num_domicilio" class="w-60">
                        </div>
                        <div class="field-group">
                            <label>CALIFICADOR</label>
                            <select name="calificador">
                                <option value=""></option>
                                <option>Bis</option>
                                <option>Duplicado</option>
                                <option>Moderno</option>
                            </select>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group"><label>BLOQUE</label><input type="text" name="bloque" class="w-60"></div>
                        <div class="field-group"><label>PORTAL</label><input type="text" name="portal" class="w-60"></div>
                        <div class="field-group"><label>ESCALERA</label><input type="text" name="escalera" class="w-60"></div>
                        <div class="field-group"><label>PLANTA</label><input type="text" name="planta" class="w-60"></div>
                        <div class="field-group"><label>PUERTA</label><input type="text" name="puerta" class="w-60"></div>
                        <div class="field-group"><label>COMPLEMENTO</label><input type="text" name="complemento" class="w-100"></div>
                    </div>
                    
                    <!-- Campo oculto para domicilio_full si es necesario -->
                    <input type="hidden" name="domicilio_full" id="domicilio_full">

                    <div class="field-row" style="margin-top: 10px;">
                        <div class="field-group"><label>CP</label><input type="text" name="cp" class="w-60"></div>
                        <div class="field-group"><label>LOCALIDAD</label><input type="text" name="localidad" class="w-150"></div>
                        <div class="field-group">
                            <label>PROVINCIA</label>
                            <select name="provincia" class="w-150">
                                <option value="">---</option>
                                <?php foreach ($provincias as $p): ?>
                                    <option value="<?= $p ?>"><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field-row" style="margin-top: 15px;">
                        <div class="field-group">
                            <label>TELÉFONO</label>
                            <input type="text" name="telefono" class="w-100">
                        </div>
                        <div class="field-group">
                            <label>MÓVIL / EMPRESA</label>
                            <input type="text" name="telefono_empresa" class="w-100">
                        </div>
                        <div class="field-group">
                            <label class="label-red">EMAIL PRINCIPAL *</label>
                            <input type="email" name="email" class="w-200" required>
                        </div>
                        <div class="field-group">
                            <label>EMAIL SECUNDARIO</label>
                            <input type="email" name="email_2" class="w-200">
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: PLATAFORMA MOODLE -->
                <div class="form-section" style="background: #fdf2f2;">
                    <div class="section-header">Configuración Moodle (Provisionamiento)</div>
                    <div class="field-row">
                        <div class="field-group">
                            <label>USUARIO PLAT.</label>
                            <input type="text" name="plat_usuario" class="w-150" placeholder="Auto si vacío">
                        </div>
                        <div class="field-group">
                            <label>CLAVE PLAT.</label>
                            <input type="text" name="plat_clave" class="w-150" placeholder="Auto si vacío">
                        </div>
                        <div class="checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" name="enviar_emails" id="enviar_emails" checked>
                            <label for="enviar_emails">NOTIFICAR POR EMAIL</label>
                        </div>
                    </div>
                    <p style="font-size: 0.7rem; color: #666; margin-top: 5px;">* Si no indicas usuario/clave, se generarán según el DNI y se sincronizarán con Moodle automáticamente.</p>
                </div>

                <!-- SECCIÓN 4: INFORMACIÓN LABORAL Y OTROS -->
                <div class="form-section">
                    <div class="section-header">Información Adicional</div>
                    <div class="field-row">
                         <div class="field-group">
                            <label>ÚLTIMA EMPRESA</label>
                            <select name="ultima_empresa_id" class="w-200">
                                <option value="">---</option>
                                <?php foreach ($empresas as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= $e['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>CENTRO TRABAJO</label>
                            <input type="text" name="centro_trabajo" class="w-200">
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label>OBSERVACIONES</label>
                            <textarea name="observaciones" rows="3" style="width: 500px; border: 1px solid var(--border-gray); border-radius: 2px;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- BOTÓN SUBMIT -->
                <div style="text-align: right; padding: 20px 0;">
                    <button type="submit" class="btn-submit" style="background: var(--title-red); color: white; border: none; padding: 10px 40px;">
                        REGISTRAR ALUMNO Y SINCRONIZAR MOODLE
                    </button>
                    <div style="margin-top: 10px; font-size: 0.7rem; color: #666;">
                        Se creará el registro en la base de datos local y se enviará la petición a la API de Moodle.
                    </div>
                </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Script simple para concatenar domicilio si se desea (sólo si el formulario de registro está presente)
        const regForm = document.querySelector('form[method="POST"]');
        if (regForm) {
            regForm.addEventListener('submit', function() {
                const via = document.querySelector('[name="tipo_via"]').value;
                const nombre = document.querySelector('[name="nombre_via"]').value;
                const num = document.querySelector('[name="num_domicilio"]').value;
                document.getElementById('domicilio_full').value = via + ' ' + nombre + ', ' + num;
            });
        }

        // Sincronizar scroll superior FIJO con el scroll real de la tabla
        (function() {
            const tableResp = document.getElementById('tableResponsive');
            if (!tableResp) return;

            // Crear la barra fija de scroll en el body
            const fixedBar = document.createElement('div');
            fixedBar.id = 'fixedScrollBar';
            const fixedInner = document.createElement('div');
            fixedInner.style.height = '1px';
            fixedBar.appendChild(fixedInner);
            document.body.appendChild(fixedBar);

            // Actualizar posición y tamaño de la barra fija según el contenedor real
            function updateBarGeometry() {
                const rect = tableResp.getBoundingClientRect();
                fixedBar.style.left   = rect.left + 'px';
                fixedBar.style.width  = rect.width + 'px';
                fixedBar.style.top    = '0px';
                // El ancho del contenido desplazable
                const tableEl = tableResp.querySelector('table');
                fixedInner.style.width = (tableEl ? tableEl.scrollWidth : tableResp.scrollWidth) + 'px';
            }

            // Mostrar/ocultar la barra fija según si la tabla está por debajo del viewport
            function onScroll() {
                const rect = tableResp.getBoundingClientRect();
                // Mostrar cuando el borde superior de la tabla sale por arriba del viewport
                // y el borde inferior todavía es visible (tabla en pantalla)
                const tableVisible = rect.bottom > 40;
                const topAboveViewport = rect.top < 0;
                if (topAboveViewport && tableVisible) {
                    updateBarGeometry();
                    fixedBar.classList.add('visible');
                } else {
                    fixedBar.classList.remove('visible');
                }
            }

            // Sincronizar scroll: barra fija -> tabla
            let lockTable = false, lockFixed = false;
            fixedBar.addEventListener('scroll', function() {
                if (lockTable) return;
                lockFixed = true;
                tableResp.scrollLeft = fixedBar.scrollLeft;
                requestAnimationFrame(() => { lockFixed = false; });
            });
            // Sincronizar scroll: tabla -> barra fija
            tableResp.addEventListener('scroll', function() {
                if (lockFixed) return;
                lockTable = true;
                fixedBar.scrollLeft = tableResp.scrollLeft;
                requestAnimationFrame(() => { lockTable = false; });
            });

            updateBarGeometry();
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', function() {
                updateBarGeometry();
                onScroll();
            });
        })();
    </script>
</body>
</html>
