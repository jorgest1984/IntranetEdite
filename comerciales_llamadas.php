<?php
// comerciales_llamadas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

$is_comercial_only = has_permission([ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL]) && !has_permission([ROLE_ADMIN, ROLE_COORD]);

// Eliminar llamada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_llamada') {
    try {
        $llamada_id = $_POST['llamada_id'];
        // Verificar si la llamada le pertenece al comercial si no es admin
        $check_sql = "SELECT usuario_id FROM tutorias_seguimiento WHERE id = ?";
        $stmt_check = $pdo->prepare($check_sql);
        $stmt_check->execute([$llamada_id]);
        $owner_id = $stmt_check->fetchColumn();

        if ($owner_id && ($owner_id == $_SESSION['user_id'] || !$is_comercial_only)) {
            $stmt = $pdo->prepare("DELETE FROM tutorias_seguimiento WHERE id = ?");
            $stmt->execute([$llamada_id]);
            $success = "Llamada eliminada correctamente.";
        } else {
            $error = "No tienes permiso para borrar esta llamada.";
        }
    } catch (Exception $e) {
        $error = "Error al eliminar la llamada: " . $e->getMessage();
    }
}

// Listas para dropdowns
$comerciales = [];
$destinatarios = [];
$resultados = ["Interesado", "No interesa", "Volver a llamar", "Cita concertada", "No responde", "Equivocado"];
$enviada_info_options = ["SI", "NO"];

// Cargar comerciales
try {
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales->fetchAll();
    
    // Cargar algunos destinatarios (empresas) para el dropdown
    $stmtDest = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 100");
    $destinatarios = $stmtDest->fetchAll();
} catch (Exception $e) {}

// LÓGICA DE BÚSQUEDA REAL
$llamadas = [];
$searchPerformed = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['buscar'])) {
    $searchPerformed = true;
    try {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($_GET['fecha_desde'])) {
            $where[] = "ts.fecha >= ?";
            $params[] = $_GET['fecha_desde'];
        }
        if (!empty($_GET['fecha_hasta'])) {
            $where[] = "ts.fecha <= ?";
            $params[] = $_GET['fecha_hasta'];
        }
        if ($is_comercial_only) {
            $where[] = "ts.usuario_id = ?";
            $params[] = $_SESSION['user_id'];
        } elseif (!empty($_GET['comercial_id'])) {
            $where[] = "ts.usuario_id = ?";
            $params[] = $_GET['comercial_id'];
        }
        if (!empty($_GET['destinatario_id'])) {
            $where[] = "ts.empresa_id = ?";
            $params[] = $_GET['destinatario_id'];
        }
        if (!empty($_GET['resultado'])) {
            $where[] = "ts.resultado = ?";
            $params[] = $_GET['resultado'];
        }

        $sql = "SELECT ts.*, a.nombre as alumno_nombre, a.primer_apellido as alumno_apellido, e.nombre as empresa_nombre, c.nombre as curso_nombre, u.nombre as comercial_nombre, u.apellidos as comercial_apellidos,
                       (SELECT conv.nombre FROM matriculas m JOIN convocatorias conv ON m.convocatoria_id = conv.id WHERE m.alumno_id = a.id ORDER BY m.id DESC LIMIT 1) as convocatoria_nombre
                FROM tutorias_seguimiento ts
                LEFT JOIN alumnos a ON ts.alumno_id = a.id
                LEFT JOIN empresas e ON ts.empresa_id = e.id
                LEFT JOIN cursos c ON ts.curso_id = c.id
                LEFT JOIN usuarios u ON ts.usuario_id = u.id
                WHERE " . implode(" AND ", $where) . " 
                ORDER BY ts.fecha DESC, ts.hora DESC LIMIT 200";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $llamadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error al realizar la búsqueda: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Llamadas - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #000080; /* Azul oscuro similar a la imagen */
            --border-gray: #cbd5e1;
            --bg-header: #e2e8f0;
            --row-pink: #fee2e2;
            --row-beige: #fef3c7;
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
            justify-content: center;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--label-blue);
            white-space: nowrap;
        }

        .form-control {
            font-size: 0.85rem;
            padding: 4px 8px;
            border: 1px solid var(--border-gray);
            border-radius: 3px;
            background: #fff;
            height: 32px;
            box-sizing: border-box;
        }

        select.form-control { min-width: 150px; }

        .btn-actions-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-buscar {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #000080;
            color: #fff;
            border: none;
            padding: 8px 24px;
            font-size: 0.82rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            border-radius: 5px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-buscar:hover { background: #00007a; }
        .btn-buscar:active { transform: scale(0.98); }
        
        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            color: #475569;
            border: 1.5px solid #cbd5e1;
            padding: 7px 18px;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            border-radius: 5px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.1s;
        }

        .btn-print:hover { 
            background: #f8fafc; 
            border-color: #94a3b8;
            color: #1e293b;
        }
        .btn-print:active { transform: scale(0.98); }

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
            position: relative;
        }

        .results-header .check-group {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--label-blue);
            font-weight: 700;
        }

        .results-header h2 {
            margin: 0;
            font-size: 0.75rem;
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
            min-width: 1200px;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-custom th {
            background: var(--bg-header);
            border: 1px solid var(--border-gray);
            padding: 8px 10px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 800;
            font-size: 0.8rem;
        }

        .table-custom td {
            border: 1px solid var(--border-gray);
            padding: 6px 10px;
            white-space: normal;
            vertical-align: middle;
            color: var(--label-blue);
            font-weight: 500;
        }

        .table-custom tr.row-odd { background: var(--row-pink); }
        .table-custom tr.row-even { background: var(--row-beige); }

        .cell-bold-blue {
            font-weight: 700 !important;
        }

        .action-icons {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
            align-items: center;
        }

        .icon-edit { color: #555; cursor: pointer; }
        .icon-delete { color: #dc2626; cursor: pointer; font-weight: bold; }

        .btn-volver {
            padding: 6px 20px;
            font-size: 0.85rem;
            cursor: pointer;
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
            border-radius: 3px;
            text-decoration: none;
            display: inline-block;
            color: #475569;
            font-weight: 500;
        }
        
        .btn-volver:hover { background: #e2e8f0; }

        .date-input-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .date-input-container span {
            background: #e2e8f0;
            border: 1px solid var(--border-gray);
            padding: 0 8px;
            height: 32px;
            display: flex;
            align-items: center;
            border-radius: 3px;
            font-size: 0.8rem;
            color: #475569;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6); /* Slate 900 with transparency */
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-container {
            background: #fff;
            width: 500px;
            max-width: 90%;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.active .modal-container {
            transform: scale(1);
        }

        .modal-header-custom {
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-custom h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e293b;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .modal-close-btn:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .modal-body-custom {
            padding: 20px;
        }

        .search-wrapper {
            position: relative;
            margin-bottom: 15px;
        }

        .search-wrapper svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input-modal {
            width: 100%;
            padding: 8px 12px 8px 36px;
            font-size: 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input-modal:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }

        .results-container {
            max-height: 260px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            display: none;
        }

        .results-container.active {
            display: block;
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .result-item:hover {
            background-color: #f8fafc;
        }

        .result-student-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .result-student-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.85rem;
        }

        .result-student-dni {
            font-size: 0.75rem;
            color: #64748b;
        }

        .result-action-label {
            font-size: 0.75rem;
            color: #0ea5e9;
            font-weight: 600;
        }

        .no-results-msg {
            padding: 16px;
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
        }

        .searching-spinner {
            padding: 16px;
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        /* Print styling */
        @media print {
            .sidebar, 
            .btn-volver, 
            .search-card, 
            .results-header, 
            .action-icons, 
            td:last-child, 
            th:last-child,
            .btn-primary-custom,
            .modal-overlay,
            header,
            footer {
                display: none !important;
            }

            body, .app-container, .main-content {
                background: #fff !important;
                color: #000 !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                box-shadow: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            .results-section {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .table-custom {
                width: 100% !important;
                min-width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px !important;
                font-size: 0.7rem !important;
            }

            .table-custom th,
            .table-custom td {
                border: 1px solid #cbd5e1 !important;
                padding: 6px 8px !important;
                color: #000 !important;
                background: transparent !important;
            }

            .table-custom th {
                background-color: #f1f5f9 !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-only-header {
                display: block !important;
                margin-top: 10px !important;
                margin-bottom: 25px !important;
            }
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <!-- Cabecera Exclusiva para Impresión -->
            <div class="print-only-header" style="display: none;">
                <h1 style="text-align: center; color: #b91c1c; font-size: 1.5rem; margin-bottom: 5px; font-weight: 800; font-family: 'Inter', sans-serif;">GRUPO EFP - GESTIÓN COMERCIAL</h1>
                <h2 style="text-align: center; color: #334155; font-size: 1.1rem; margin-bottom: 20px; font-weight: 700; font-family: 'Inter', sans-serif;">Informe de Registro de Llamadas</h2>
                <?php if (!empty($_GET['fecha_desde']) || !empty($_GET['fecha_hasta'])): ?>
                    <p style="text-align: center; font-size: 0.85rem; color: #475569; margin-bottom: 20px; font-family: 'Inter', sans-serif;">
                        <strong>Período:</strong> 
                        <?= !empty($_GET['fecha_desde']) ? date('d/m/Y', strtotime($_GET['fecha_desde'])) : 'Inicio' ?> 
                        hasta 
                        <?= !empty($_GET['fecha_hasta']) ? date('d/m/Y', strtotime($_GET['fecha_hasta'])) : 'Fin' ?>
                    </p>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <a href="comerciales.php" class="btn-volver" style="margin: 0;">← Volver a Gestión Comercial</a>
                <button type="button" class="btn-primary-custom" onclick="openNewCallModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #0ea5e9; color: #fff; border: none; border-radius: 4px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: background-color 0.2s; font-family: inherit;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Registrar Nueva Llamada
                </button>
            </div>

            <div class="search-card">
                <div class="card-header-custom">
                    <h2>LLAMADAS REALIZADAS - CAMPOS DE BÚSQUEDA</h2>
                </div>
                <form class="search-form" method="GET">
                    <input type="hidden" name="buscar" value="1">
                    
                    <!-- Fila 1: Fechas -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Desde:</label>
                            <div class="date-input-container">
                                <input type="date" name="fecha_desde" value="<?= htmlspecialchars($_GET['fecha_desde'] ?? '') ?>" class="form-control">
                                <span>»</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Hasta:</label>
                            <div class="date-input-container">
                                <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($_GET['fecha_hasta'] ?? '') ?>" class="form-control">
                                <span>»</span>
                            </div>
                        </div>
                    </div>

                    <!-- Fila 2: Filtros -->
                    <div class="search-row">
                        <div class="form-group" <?= $is_comercial_only ? 'style="display:none;"' : '' ?>>
                            <label>Comercial:</label>
                            <select name="comercial_id" class="form-control" style="width: 250px;">
                                <option value="">--- Seleccionar ---</option>
                                <?php foreach($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($_GET['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Destinatario:</label>
                            <select name="destinatario_id" class="form-control" style="width: 200px;">
                                <option value="">--- Todos ---</option>
                                <?php foreach($destinatarios as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= ($_GET['destinatario_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Enviada info:</label>
                            <select name="enviada_info" class="form-control" style="width: 80px;">
                                <option value="">---</option>
                                <?php foreach($enviada_info_options as $opt): ?>
                                    <option value="<?= $opt ?>" <?= ($_GET['enviada_info'] ?? '') == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Resultado:</label>
                            <select name="resultado" class="form-control" style="width: 180px;">
                                <option value="">--- Todos ---</option>
                                <?php foreach($resultados as $res): ?>
                                    <option value="<?= $res ?>" <?= ($_GET['resultado'] ?? '') == $res ? 'selected' : '' ?>><?= $res ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 18px; display: flex; justify-content: center;">
                        <div class="btn-actions-row">
                            <button type="submit" class="btn-buscar">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                Buscar
                            </button>
                            <button type="button" class="btn-print" onclick="window.print()">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" style="flex-shrink: 0;"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                                Imprimir
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS -->
            <div class="results-section">
                <div class="results-header">
                    <div class="check-group">
                        <input type="checkbox" name="multiple_sort"> Ordenar múltiple
                    </div>
                    <h2>RESULTADO DE LA BÚSQUEDA</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Empresa/Contacto</th>
                                <th style="width: 8%;">Fecha</th>
                                <th style="width: 8%;">Hora</th>
                                <th style="width: 10%;">Asunto</th>
                                <th>Notas</th>
                                <th style="width: 12%;">Comercial</th>
                                <th style="width: 8%;">Enviada info</th>
                                <th style="width: 8%;">Fecha envio info</th>
                                <th style="width: 10%;">Resultado</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$searchPerformed): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 3rem; color: #64748b; font-size: 0.9rem; background: #fff !important;">
                                        Utilice los filtros superiores para consultar el registro de llamadas.
                                    </td>
                                </tr>
                            <?php elseif (empty($llamadas)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 3rem; color: var(--title-red); font-weight: 600; background: #fff !important;">
                                        No se encontraron registros de llamadas con los criterios seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($llamadas as $index => $ll): 
                                    $display_name = $ll['empresa'] ?: ($ll['alumno_nombre'] ? trim($ll['alumno_nombre'] . ' ' . $ll['alumno_apellido']) : ($ll['empresa_nombre'] ?: 'DESEMPLEADO'));
                                    
                                    $conv_nombre = strtolower($ll['convocatoria_nombre'] ?? '');
                                    $row_style = '';
                                    if (strpos($conv_nombre, 'estatal') !== false) {
                                        $row_style = 'background-color: #dbeafe;'; // blue
                                    } elseif (strpos($conv_nombre, 'madrid') !== false || strpos($conv_nombre, 'ocupados') !== false) {
                                        $row_style = 'background-color: #fce7f3;'; // pinkish
                                    } elseif (strpos($conv_nombre, '2016') !== false) {
                                        $row_style = 'background-color: #f3e8ff;'; // purple
                                    } elseif (strpos($conv_nombre, '2018') !== false) {
                                        $row_style = 'background-color: #e0e7ff;'; // light indigo
                                    } else {
                                        $row_style = ($index % 2 == 0) ? 'background-color: var(--row-pink);' : 'background-color: var(--row-beige);';
                                    }
                                ?>
                                    <tr style="<?= $row_style ?>">
                                        <td class="cell-bold-blue"><?= htmlspecialchars($display_name) ?>
                                            <?php if (!empty($ll['convocatoria_nombre'])): ?>
                                                <div style="font-size: 0.65rem; color: #64748b; font-weight: normal; margin-top: 2px;">
                                                    <?= htmlspecialchars($ll['convocatoria_nombre']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $ll['fecha'] ? date('d/m/Y', strtotime($ll['fecha'])) : '' ?></td>
                                        <td class="cell-bold-blue"><?= htmlspecialchars($ll['hora']) ?></td>
                                        <td class="cell-bold-blue"><?= htmlspecialchars($ll['asunto']) ?></td>
                                        <td class="cell-bold-blue"><?= htmlspecialchars($ll['notas']) ?></td>
                                        <td class="cell-bold-blue"><?= htmlspecialchars(trim(($ll['comercial_nombre'] ?? '') . ' ' . ($ll['comercial_apellidos'] ?? ''))) ?: 'Sistema' ?></td>
                                        <td style="text-align: center;"><?= htmlspecialchars($ll['enviada_info'] ?? '') ?></td>
                                        <td><?= (!empty($ll['fecha_envio'])) ? date('d/m/Y', strtotime($ll['fecha_envio'])) : '' ?></td>
                                        <td><?= htmlspecialchars($ll['resultado'] ?? '') ?></td>
                                        <td>
                                            <div class="action-icons">
                                                <a href="ficha_llamada.php?call_id=<?= $ll['id'] ?>" class="icon-edit" title="Editar">
                                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 000-1.41l-2.34-2.34a.996.996 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                                </a>
                                                <?php if (!$is_comercial_only || $ll['usuario_id'] == $_SESSION['user_id']): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar esta llamada?');">
                                                    <input type="hidden" name="action" value="delete_llamada">
                                                    <input type="hidden" name="llamada_id" value="<?= $ll['id'] ?>">
                                                    <button type="submit" class="icon-delete" title="Eliminar" style="background:none; border:none; padding:0; font:inherit; cursor:pointer;">✕</button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="comerciales.php" class="btn-volver">Volver</a>
            </div>

        </main>
    </div>

    <!-- MODAL REGISTRAR NUEVA LLAMADA -->
    <div id="newCallModal" class="modal-overlay" onclick="handleOutsideClick(event)">
        <div class="modal-container">
            <div class="modal-header-custom">
                <h3>Registrar Nueva Llamada</h3>
                <button type="button" class="modal-close-btn" onclick="closeNewCallModal()">&times;</button>
            </div>
            <div class="modal-body-custom">
                <p style="margin: 0 0 15px 0; font-size: 0.8rem; color: #64748b; font-weight: 500;">
                    Busca al alumno por su nombre, apellidos o DNI para iniciar el registro de la llamada.
                </p>
                <div class="search-wrapper">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="studentSearchInput" class="search-input-modal" placeholder="Ej: Juan Pérez o 12345678Z" autocomplete="off" oninput="debounceSearch()">
                </div>
                <div id="searchResults" class="results-container">
                    <!-- Dynamic items will be added here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let searchTimeout = null;

        function openNewCallModal() {
            const modal = document.getElementById('newCallModal');
            modal.classList.add('active');
            const input = document.getElementById('studentSearchInput');
            input.value = '';
            input.focus();
            document.getElementById('searchResults').classList.remove('active');
            document.getElementById('searchResults').innerHTML = '';
        }

        function closeNewCallModal() {
            const modal = document.getElementById('newCallModal');
            modal.classList.remove('active');
        }

        function handleOutsideClick(event) {
            if (event.target === event.currentTarget) {
                closeNewCallModal();
            }
        }

        function debounceSearch() {
            clearTimeout(searchTimeout);
            const query = document.getElementById('studentSearchInput').value.trim();
            const resultsDiv = document.getElementById('searchResults');

            if (query.length < 2) {
                resultsDiv.classList.remove('active');
                resultsDiv.innerHTML = '';
                return;
            }

            resultsDiv.classList.add('active');
            resultsDiv.innerHTML = `
                <div class="searching-spinner">
                    <svg class="animate-spin" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="10"></circle></svg>
                    Buscando alumno...
                </div>
            `;

            // Style standard spinner animation if not present
            if (!document.getElementById('spinner-style')) {
                const style = document.createElement('style');
                style.id = 'spinner-style';
                style.innerHTML = `
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }

            searchTimeout = setTimeout(() => {
                fetch(`api_buscar_alumnos.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            resultsDiv.innerHTML = '<div class="no-results-msg">No se encontraron alumnos que coincidan.</div>';
                        } else {
                            let html = '';
                            data.forEach(student => {
                                const fullName = `${student.nombre} ${student.primer_apellido}`;
                                html += `
                                    <a href="ficha_llamada.php?alumno_id=${student.id}" class="result-item">
                                        <div class="result-student-info">
                                            <span class="result-student-name">${escapeHTML(fullName)}</span>
                                            <span class="result-student-dni">DNI/NIE: ${escapeHTML(student.dni || 'No indicado')}</span>
                                        </div>
                                        <span class="result-action-label">Seleccionar &raquo;</span>
                                    </a>
                                `;
                            });
                            resultsDiv.innerHTML = html;
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching students:', err);
                        resultsDiv.innerHTML = '<div class="no-results-msg" style="color: #dc2626;">Error al buscar alumnos.</div>';
                    });
            }, 300);
        }

        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g, 
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }
    </script>
</body>
</html>
