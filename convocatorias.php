<?php
// convocatorias.php - v2.1 (Corrección Matriculas)
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create') {
        $codigo = trim($_POST['codigo_expediente'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = $_POST['tipo'] ?? '';
        $organismo = trim($_POST['organismo'] ?? '');
        $presupuesto = empty($_POST['presupuesto']) ? 0 : floatval($_POST['presupuesto']);
        
        $abreviatura = trim($_POST['abreviatura'] ?? '');
        $anio = trim($_POST['anio'] ?? '');
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $ambito = trim($_POST['ambito'] ?? '');
        $solicitante = trim($_POST['solicitante'] ?? '');
        
        $url = trim($_POST['url'] ?? '');
        $url_aula_virtual = trim($_POST['url_aula_virtual'] ?? '');
        $activa = isset($_POST['activa']) ? 1 : 0;
        $descripcion = trim($_POST['descripcion'] ?? '');
        $requisitos = trim($_POST['requisitos'] ?? '');
        
        if (empty($codigo) || empty($nombre)) {
            $error = "El código y el nombre son obligatorios.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO convocatorias (codigo_expediente, nombre, tipo, organismo, presupuesto, estado, abreviatura, anio, fecha_inicio_prevista, fecha_fin_prevista, ambito, solicitante, url, url_aula_virtual, activa, descripcion, requisitos) VALUES (?, ?, ?, ?, ?, 'Borrador', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$codigo, $nombre, $tipo, $organismo, $presupuesto, $abreviatura, $anio, $fecha_inicio, $fecha_fin, $ambito, $solicitante, $url, $url_aula_virtual, $activa, $descripcion, $requisitos]);
                $success = "Convocatoria creada correctamente.";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'upload_zip') {
        $conv_id = (int)($_POST['convocatoria_zip_id'] ?? 0);
        if ($conv_id > 0 && isset($_FILES['archivo_zip'])) {
            $file = $_FILES['archivo_zip'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === 'zip') {
                    $upload_dir = 'uploads/evaluaciones_zip/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $target_path = $upload_dir . $conv_id . '.zip';
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $success = "El archivo ZIP de evaluaciones se ha subido correctamente.";
                        audit_log($pdo, 'SUBIDA_ZIP_EVAL', 'convocatorias', $conv_id, null, ['archivo' => basename($file['name'])]);
                    } else {
                        $error = "Error al guardar el archivo ZIP en el servidor.";
                    }
                } else {
                    $error = "El archivo debe ser de tipo .ZIP";
                }
            } else {
                $error = "Error en la subida del archivo: Código " . $file['error'];
            }
        }
    }
}

// Listar convocatorias con estadísticas
$search = $_GET['search'] ?? '';
$tipoFilter = $_GET['tipo'] ?? '';

$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM grupos g WHERE g.accion_id IN (SELECT id FROM acciones_formativas WHERE plan_id IN (SELECT id FROM planes WHERE convocatoria_id = c.id))) as total_grupos,
        (SELECT COUNT(*) FROM matriculas WHERE grupo_id IN (SELECT id FROM grupos WHERE accion_id IN (SELECT id FROM acciones_formativas WHERE plan_id IN (SELECT id FROM planes WHERE convocatoria_id = c.id)))) as total_alumnos
        FROM convocatorias c WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR c.codigo_expediente LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($tipoFilter) {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipoFilter;
}

$sql .= " ORDER BY c.creado_en DESC";
$convocatorias = $pdo->prepare($sql);
$convocatorias->execute($params);
$list = $convocatorias->fetchAll();

// Totales para KPIs
$total_presupuesto = array_sum(array_column($list, 'presupuesto'));
$total_alumnos = array_sum(array_column($list, 'total_alumnos'));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convocatorias - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .conv-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .conv-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-top: 4px solid #1e3a8a;
        }

        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .conv-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border-collapse: collapse;
        }

        .conv-table th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-size: 0.7rem;
            color: #1e40af;
            text-transform: uppercase;
            border-bottom: 2px solid #f1f5f9;
        }

        .conv-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-borrador { background: #f1f5f9; color: #64748b; }
        .status-activa { background: #dcfce7; color: #166534; }
        .status-finalizada { background: #fee2e2; color: #991b1b; }

        .btn-new-conv {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .form-new-card {
            background: #fff;
            border: 2px solid #e2e8f0;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: none;
        }

        .rte-mock { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .rte-toolbar { background: #f8fafc; padding: 0.5rem; border-bottom: 1px solid #e2e8f0; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .toolbar-btn { padding: 0.25rem 0.5rem; border: 1px solid transparent; background: none; cursor:pointer; color: #475569; border-radius: 4px; }
        .toolbar-btn:hover { background: #e2e8f0; }
        .rte-textarea { width: 100%; min-height: 150px; padding: 1rem; border: none; font-family: inherit; resize: vertical; display: block; font-size: 0.95rem; }
        
        /* Responsive Media Queries */
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column !important;
            }
            .main-content {
                padding: 15px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            .page-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 15px !important;
            }
            .page-header div[style*="display: flex"] {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
            }
            .btn-new-conv {
                width: 100% !important;
                justify-content: center !important;
            }
            .conv-kpi-grid {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            #formNueva form {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            #formNueva form > div {
                grid-column: span 1 !important;
            }
            .filter-bar {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 15px !important;
                padding: 15px !important;
            }
            .filter-bar div {
                width: 100% !important;
            }
            .filter-bar button {
                width: 100% !important;
            }
            
            /* Responsive Table (Cards transformation) */
            .conv-table, .conv-table thead, .conv-table tbody, .conv-table th, .conv-table td, .conv-table tr {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .conv-table thead {
                display: none !important;
            }
            .conv-table tr {
                margin-bottom: 20px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 12px !important;
                background: white !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;
                padding: 12px !important;
            }
            .conv-table td {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                border-bottom: 1px solid #f1f5f9 !important;
                padding: 12px 5px !important;
                text-align: right !important;
            }
            .conv-table td:last-child {
                border-bottom: none !important;
            }
            .conv-table td::before {
                content: attr(data-label) !important;
                font-weight: 700 !important;
                color: #1e40af !important;
                font-size: 0.75rem !important;
                text-transform: uppercase !important;
                text-align: left !important;
                margin-right: 15px !important;
            }
            .conv-table td div {
                text-align: right !important;
            }
            .conv-table td div[style*="justify-content: center"] {
                justify-content: flex-end !important;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <a href="home.php" title="Volver a Inicio" style="display: flex; align-items: center; justify-content: center; background: white; padding: 8px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    <img src="img/logo_efp.png" alt="EFP Home" style="height: 40px; width: auto;">
                </a>
                <div class="page-title">
                    <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: #1e3a8a;">Gestión de Convocatorias</h1>
                    <p style="margin: 0; color: #64748b; font-weight: 500;">Control administrativo de subvenciones y planes formativos</p>
                </div>
            </div>
            <button class="btn-new-conv" onclick="toggleForm()" style="background: #1e3a8a; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.2s;">+ Nueva Convocatoria</button>
        </header>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; border: 1px solid #a7f3d0; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; border: 1px solid #fca5a5; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="conv-kpi-grid">
            <div class="conv-card">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Convocatorias</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #1e3a8a;"><?= count($list) ?></div>
            </div>
            <div class="conv-card" style="border-top-color: #10b981;">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Presupuesto Gestionado</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #10b981;"><?= number_format($total_presupuesto, 2) ?> €</div>
            </div>
            <div class="conv-card" style="border-top-color: #f59e0b;">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Alumnos Totales</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;"><?= number_format($total_alumnos) ?></div>
            </div>
        </div>

        <div id="formNueva" class="form-new-card">
            <h3 style="margin-top: 0; color: #1e3a8a; font-weight: 800; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 20px;">Registrar Nueva Convocatoria</h3>
            <form action="" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <input type="hidden" name="action" value="create">
                
                <!-- Convocatoria -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Convocatoria *</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Nombre descriptivo de la convocatoria">
                    <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">Nombre de la convocatoria</span>
                </div>

                <!-- Abreviatura -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Abreviatura</label>
                    <input type="text" name="abreviatura" class="form-control" placeholder="E16, TIC18">
                </div>

                <!-- Año de convocatoria -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Año de convocatoria</label>
                    <input type="text" name="anio" class="form-control" placeholder="2018">
                </div>

                <!-- Fecha de inicio -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Fecha de inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control">
                </div>

                <!-- Fecha de finalización -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Fecha de finalización</label>
                    <input type="date" name="fecha_fin" class="form-control">
                </div>

                <!-- Ámbito -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Ámbito</label>
                    <input type="text" name="ambito" class="form-control" placeholder="Ámbito geográfico (Ej: Estatal)">
                </div>

                <!-- Solicitante -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Solicitante</label>
                    <input type="text" name="solicitante" class="form-control" placeholder="Entidad solicitante">
                </div>

                <!-- URL -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">URL</label>
                    <input type="text" name="url" class="form-control" placeholder="/formacion/bonificada">
                    <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic; display: block; margin-top: 4px;">URL de la convocatoria en la web. Puede ser una ruta relativa.</span>
                </div>

                <!-- URL del Aula Virtual -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">URL del Aula Virtual</label>
                    <input type="text" name="url_aula_virtual" class="form-control" placeholder="https://...">
                    <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic; display: block; margin-top: 4px;">URL oficial, la que se comunica a los organismos públicos.</span>
                </div>

                <!-- Activa -->
                <div class="form-group" style="grid-column: span 2; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="activa" id="activa" checked style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="activa" style="font-size: 0.85rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; cursor: pointer; margin-bottom: 0;">Activa</label>
                </div>

                <!-- Descripción -->
                <div class="form-group" style="grid-column: span 2; display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Descripción</label>
                    <div class="rte-mock">
                        <div class="rte-toolbar">
                            <button type="button" class="toolbar-btn"><b>B</b></button>
                            <button type="button" class="toolbar-btn"><i>I</i></button>
                            <button type="button" class="toolbar-btn"><u>U</u></button>
                            <span style="border-right:1px solid #ccc; margin:0 5px;"></span>
                            <button type="button" class="toolbar-btn">🔗</button>
                            <button type="button" class="toolbar-btn">🖼️</button>
                        </div>
                        <textarea name="descripcion" class="rte-textarea" placeholder="Escribe aquí la descripción..."></textarea>
                    </div>
                </div>

                <!-- Requisitos de participación -->
                <div class="form-group" style="grid-column: span 2; display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Requisitos de participación</label>
                    <div class="rte-mock">
                        <div class="rte-toolbar">
                            <button type="button" class="toolbar-btn"><b>B</b></button>
                            <button type="button" class="toolbar-btn"><i>I</i></button>
                            <button type="button" class="toolbar-btn"><u>U</u></button>
                        </div>
                        <textarea name="requisitos" class="rte-textarea" placeholder="Escribe aquí los requisitos de participación..."></textarea>
                    </div>
                </div>

                <!-- Código Expediente (Admin) -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Código Expediente *</label>
                    <input type="text" name="codigo_expediente" class="form-control" required placeholder="Ej: F240001 o E16">
                </div>

                <!-- Presupuesto (Admin) -->
                <div class="form-group">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Presupuesto</label>
                    <input type="number" name="presupuesto" step="0.01" class="form-control" placeholder="0.00">
                </div>

                <!-- Tipo de convocatoria (Admin) -->
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Tipo de Convocatoria</label>
                    <select name="tipo" class="form-control">
                        <option value="SEPE_DESEMPLEADOS">SEPE - Desempleados</option>
                        <option value="FUNDAE_OCUPADOS">FUNDAE - Ocupados</option>
                        <option value="PRIVADA">Privada</option>
                    </select>
                </div>

                <div style="grid-column: span 2; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="toggleForm()" style="margin-right: 10px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Registro</button>
                </div>
            </form>
        </div>

        <form class="filter-bar" method="GET" style="display: flex; gap: 15px; align-items: stretch;">
            <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Buscar por nombre o expediente..." style="width: 100%;">
                <select name="tipo" class="form-control" style="width: 100%;">
                    <option value="">Todos los tipos</option>
                    <option value="SEPE_DESEMPLEADOS" <?= $tipoFilter=='SEPE_DESEMPLEADOS'?'selected':'' ?>>SEPE</option>
                    <option value="FUNDAE_OCUPADOS" <?= $tipoFilter=='FUNDAE_OCUPADOS'?'selected':'' ?>>FUNDAE</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="height: auto;">Filtrar</button>
        </form>

        <table class="conv-table">
            <thead>
                <tr>
                    <th>Expediente</th>
                    <th>Convocatoria</th>
                    <th>Tipo / Organismo</th>
                    <th style="text-align: center;">Grupos</th>
                    <th style="text-align: center;">Alumnos</th>
                    <th style="text-align: center;">Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $c): ?>
                <tr>
                    <td data-label="Expediente" style="font-family: monospace; font-weight: 700; color: #64748b;"><?= htmlspecialchars($c['codigo_expediente']) ?></td>
                    <td data-label="Convocatoria">
                        <div style="font-weight: 700; color: #1e3a8a;"><?= htmlspecialchars($c['nombre']) ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">Creada el <?= date('d/m/Y', strtotime($c['creado_en'])) ?></div>
                    </td>
                    <td data-label="Tipo / Organismo">
                        <div style="font-weight: 600; font-size: 0.8rem;"><?= str_replace('_', ' ', $c['tipo']) ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;"><?= htmlspecialchars($c['organismo'] ?? '---') ?></div>
                    </td>
                    <td data-label="Grupos" style="text-align: center; font-weight: 700;"><?= $c['total_grupos'] ?></td>
                    <td data-label="Alumnos" style="text-align: center; font-weight: 700; color: #10b981;"><?= $c['total_alumnos'] ?></td>
                    <td data-label="Estado" style="text-align: center;">
                        <span class="badge-status status-<?= strtolower($c['estado']) ?>"><?= $c['estado'] ?></span>
                    </td>
                    <td data-label="Acciones" style="text-align: center;">
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <?php
                            $uploadedZipPath = 'uploads/evaluaciones_zip/' . $c['id'] . '.zip';
                            $hasUploadedZip = file_exists($uploadedZipPath);
                            ?>
                            <a href="descargar_actas_convocatoria.php?id=<?= $c['id'] ?>" 
                               class="btn-icon" 
                               title="<?= $hasUploadedZip ? 'Descargar Actas Subidas (ZIP)' : 'Descargar Actas Generadas (ZIP)' ?>" 
                               style="color: <?= $hasUploadedZip ? '#16a34a' : '#8b5cf6' ?>;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            </a>
                            <a href="#" 
                               onclick="abrirModalZip(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>')" 
                               class="btn-icon" 
                               title="Subir Actas Evaluación (ZIP)" 
                               style="color: #ea580c;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            </a>
                            <a href="editar_convocatoria.php?id=<?= $c['id'] ?>" class="btn-icon" title="Editar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                            <a href="planes.php?convocatoria_id=<?= $c['id'] ?>" class="btn-icon" title="Ver Planes" style="color: #3b82f6;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></a>
                            <a href="planes.php?convocatoria_id=<?= $c['id'] ?>&new=1" class="btn-icon" title="Añadir Plan" style="color: #10b981;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></a>
                            <?php if (has_permission([ROLE_ADMIN])): ?>
                                 <a href="borrar_convocatoria.php?id=<?= $c['id'] ?>" 
                                    class="btn-icon" 
                                    title="Eliminar Convocatoria" 
                                    style="color: #ef4444;"
                                    onclick="return confirm('¿Seguro que desea enviar esta convocatoria a la papelera? Se archivarán todos sus planes asociados.');">
                                     <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                 </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<!-- Modal Subir ZIP de Evaluaciones -->
<div id="modalSubirZip" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 12px; padding: 25px; width: calc(100% - 30px); max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #1e3a8a; font-weight: 800; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.5px;">Subir Evaluaciones (ZIP)</h3>
            <button onclick="cerrarModalZip()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 18px;">
            <input type="hidden" name="action" value="upload_zip">
            <input type="hidden" name="convocatoria_zip_id" id="convocatoria_zip_id" value="">
            
            <div style="font-size: 0.85rem; color: #475569; background: #f8fafc; padding: 10px 15px; border-radius: 6px; border-left: 4px solid #ea580c;">
                Convocatoria: <strong id="modal_convocatoria_nombre" style="color: #1e3a8a;"></strong>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label style="font-size: 0.75rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase;">Seleccionar Archivo .ZIP</label>
                <input type="file" name="archivo_zip" accept=".zip" required style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; font-size: 0.85rem; width: 100%; box-sizing: border-box;">
                <span style="font-size: 0.7rem; color: #64748b; font-style: italic;">Por favor, suba el archivo consolidado en formato comprimido (.zip).</span>
            </div>

            <div style="text-align: right; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalZip()" style="padding: 8px 16px; font-weight: 700; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background: #ea580c; border-color: #d97706; padding: 8px 20px; font-weight: 700; border-radius: 6px; cursor: pointer; color: white;">Subir Actas</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleForm() {
    const f = document.getElementById('formNueva');
    f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';
}

function abrirModalZip(id, nombre) {
    document.getElementById('convocatoria_zip_id').value = id;
    document.getElementById('modal_convocatoria_nombre').innerText = nombre;
    document.getElementById('modalSubirZip').style.display = 'flex';
}

function cerrarModalZip() {
    document.getElementById('modalSubirZip').style.display = 'none';
}

// Cerrar modal al hacer clic fuera del recuadro
window.onclick = function(event) {
    const modal = document.getElementById('modalSubirZip');
    if (event.target === modal) {
        cerrarModalZip();
    }
}
</script>

</body>
</html>
