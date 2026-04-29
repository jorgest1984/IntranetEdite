<?php
// comerciales_llamadas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Listas para dropdowns
$comerciales = [];
$destinatarios = [];
$resultados = ["Interesado", "No interesa", "Volver a llamar", "Cita concertada", "No responde", "Equivocado"];
$enviada_info_options = ["SI", "NO"];

// Cargar comerciales
try {
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales->fetchAll();
    
    // Cargar algunos destinatarios (empresas) para el dropdown
    $stmtDest = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 100");
    $destinatarios = $stmtDest->fetchAll();
} catch (Exception $e) {}

// LÓGICA DE BÚSQUEDA (Placeholder para implementación real)
$llamadas = [];
$searchPerformed = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!empty($_GET['fecha_desde']) || !empty($_GET['comercial_id']))) {
    $searchPerformed = true;
    // Aquí iría la consulta real a la tabla de llamadas
    // Por ahora mockeamos un resultado para demostración si se pulsa buscar
    $llamadas = [
        [
            'empresa' => 'Empresa de Prueba SL',
            'contacto' => 'Juan Pérez',
            'fecha' => date('Y-m-d'),
            'hora' => '10:30',
            'asunto' => 'Presentación servicios',
            'notas' => 'Interesado en formación bonificada',
            'enviada_info' => 'SI',
            'fecha_envio' => date('Y-m-d'),
            'resultado' => 'Cita concertada'
        ],
        [
            'empresa' => 'Talleres Mecánicos S.A.',
            'contacto' => 'María García',
            'fecha' => date('Y-m-d', strtotime('-1 day')),
            'hora' => '11:45',
            'asunto' => 'Seguimiento presupuesto',
            'notas' => 'Pendiente de aprobación por gerencia',
            'enviada_info' => 'SI',
            'fecha_envio' => date('Y-m-d', strtotime('-1 day')),
            'resultado' => 'Volver a llamar'
        ]
    ];
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
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
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

        .btn-buscar {
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
            padding: 6px 25px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 3px;
            transition: all 0.2s;
        }

        .btn-buscar:hover { background: #e2e8f0; }
        
        .btn-print {
            background: #fff;
            border: 1px solid var(--border-gray);
            padding: 5px 15px;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 3px;
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
            min-width: 1200px;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .table-custom th {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            padding: 10px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 700;
            text-transform: uppercase;
        }

        .table-custom td {
            border: 1px solid #f1f5f9;
            padding: 10px;
            white-space: normal;
        }

        .table-custom tr:nth-child(even) { background: #f8fafc; }
        .table-custom tr:hover { background: #fef2f2; }

        .btn-volver {
            margin-top: 15px;
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
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="search-card">
                <div class="card-header-custom">
                    <h2>LLAMADAS REALIZADAS - CAMPOS DE BÚSQUEDA</h2>
                </div>
                <form class="search-form" method="GET">
                    
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
                        <div class="form-group">
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
                    <div class="check-group">
                        <input type="checkbox" name="multiple_sort"> Ordenar múltiple
                    </div>
                    <h2>RESULTADO DE LA BÚSQUEDA</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Empresa/Contacto</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Asunto</th>
                                <th style="width: 30%;">Notas</th>
                                <th>Enviada info</th>
                                <th>Fecha envio info</th>
                                <th>Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$searchPerformed): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem; color: #64748b; font-size: 0.9rem;">
                                        Utilice los filtros superiores para consultar el registro de llamadas.
                                    </td>
                                </tr>
                            <?php elseif (empty($llamadas)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--title-red); font-weight: 600;">
                                        No se encontraron registros de llamadas con los criterios seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($llamadas as $ll): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: var(--label-blue);"><?= htmlspecialchars($ll['empresa']) ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($ll['contacto']) ?></div>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($ll['fecha'])) ?></td>
                                        <td><?= htmlspecialchars($ll['hora']) ?></td>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($ll['asunto']) ?></td>
                                        <td style="font-size: 0.75rem; color: #334155;"><?= htmlspecialchars($ll['notas']) ?></td>
                                        <td style="text-align: center; font-weight: 700; color: <?= $ll['enviada_info'] == 'SI' ? '#059669' : '#dc2626' ?>;">
                                            <?= $ll['enviada_info'] ?>
                                        </td>
                                        <td><?= $ll['fecha_envio'] ? date('d/m/Y', strtotime($ll['fecha_envio'])) : '---' ?></td>
                                        <td>
                                            <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: #f1f5f9; font-weight: 600; font-size: 0.7rem; color: #475569; border: 1px solid var(--border-gray);">
                                                <?= htmlspecialchars($ll['resultado']) ?>
                                            </span>
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
</body>
</html>
