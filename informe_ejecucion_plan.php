<?php
// informe_ejecucion_plan.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'informe_ejecucion_plan.php';

// Cargar convocatorias y planes reales
$convocatorias = $pdo->query("SELECT id, nombre, codigo_expediente, abreviatura FROM convocatorias ORDER BY creado_en DESC")->fetchAll();
$planes = $pdo->query("SELECT id, nombre, codigo, convocatoria_id FROM planes ORDER BY nombre ASC")->fetchAll();

$plan_id = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
$convocatoria_id = isset($_GET['convocatoria']) ? (int)$_GET['convocatoria'] : 0;

$selected_plan = null;
$selected_convocatoria = null;

if ($plan_id > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.nombre as convocatoria_nombre, c.abreviatura as convocatoria_abreviatura, c.codigo_expediente 
                           FROM planes p 
                           JOIN convocatorias c ON p.convocatoria_id = c.id 
                           WHERE p.id = ?");
    $stmt->execute([$plan_id]);
    $selected_plan = $stmt->fetch();
    if ($selected_plan) {
        $convocatoria_id = $selected_plan['convocatoria_id'];
    }
}

if ($convocatoria_id > 0 && !$selected_plan) {
    $stmt = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
    $stmt->execute([$convocatoria_id]);
    $selected_convocatoria = $stmt->fetch();
}

$acciones = [];
if ($plan_id > 0) {
    $stmt = $pdo->prepare("SELECT af.*, c.nombre_largo as curso_titulo 
                           FROM acciones_formativas af 
                           LEFT JOIN cursos c ON af.curso_id = c.id 
                           WHERE af.plan_id = ? 
                           ORDER BY af.id ASC");
    $stmt->execute([$plan_id]);
    $acciones = $stmt->fetchAll();
} elseif ($convocatoria_id > 0) {
    $stmt = $pdo->prepare("SELECT af.*, c.nombre_largo as curso_titulo, p.nombre as plan_nombre 
                           FROM acciones_formativas af 
                           LEFT JOIN cursos c ON af.curso_id = c.id 
                           JOIN planes p ON af.plan_id = p.id 
                           WHERE p.convocatoria_id = ? 
                           ORDER BY p.id ASC, af.id ASC");
    $stmt->execute([$convocatoria_id]);
    $acciones = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Ejecución de Plan - <?= APP_NAME ?></title>
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
        .main-content { padding: 2rem; }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .card-header-custom {
            background: #f8fafc;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-gray);
            border-radius: 8px 8px 0 0;
        }

        .search-form {
            padding: 1.5rem;
        }

        .search-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 1.25rem;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .form-group label {
            font-weight: 700;
            color: var(--label-blue);
            font-size: 0.85rem;
            min-width: 110px;
            text-align: right;
            text-transform: uppercase;
        }

        .form-control {
            font-size: 0.9rem;
            padding: 8px 12px;
            border: 1px solid var(--border-gray);
            border-radius: 6px;
            background: #fff;
            flex-grow: 1;
            max-width: 450px;
            color: #1e293b;
            font-weight: 500;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            padding-left: 122px;
            margin-top: 1.5rem;
        }

        .btn-buscar {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 10px 30px;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.15);
            transition: all 0.2s;
        }
        .btn-buscar:hover { background: #1e40af; transform: translateY(-1px); }

        .btn-limpiar {
            background: #fff;
            color: #ef4444;
            border: 1px solid #ef4444;
            padding: 10px 30px;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-limpiar:hover { background: #fef2f2; }

        /* Cabecera del informe */
        .plan-header-card {
            background: #2b2b2b;
            color: white;
            padding: 25px 30px;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-top: 2rem;
        }
        .plan-header-card h2 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
        }
        .plan-header-card p {
            margin: 8px 0 0 0;
            font-size: 1.15rem;
            font-weight: 500;
            color: #e2e8f0;
        }

        /* Contenedor de tabla con scroll horizontal */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 1.5rem;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-top: none;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .table-custom th {
            background: #333333;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            padding: 12px 10px;
            border: 1px solid #444444;
            text-align: center;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table-custom td {
            border: 1px solid #cbd5e1;
            padding: 10px;
            color: #1e293b;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .table-custom tr:nth-child(even) td:not(.td-orange) {
            background-color: #f8fafc;
        }

        .table-custom tr:hover td:not(.td-orange) {
            background-color: #f1f5f9;
        }

        .table-custom td.text-left {
            text-align: left;
            white-space: normal;
        }

        .table-custom tr.total-row td {
            background: #f1f5f9;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #94a3b8;
        }

        .td-orange {
            background-color: #f59e0b !important;
            color: #ffffff !important;
            font-weight: 800;
            text-align: center;
            font-size: 0.85rem;
        }

        .table-custom tr.total-row td.td-orange {
            background-color: #d97706 !important;
        }

        .export-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 2rem;
            margin-top: 1.5rem;
        }

        .btn-export {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .btn-export.excel { background: #16a34a; }
        .btn-export.excel:hover { background: #15803d; transform: translateY(-1px); }
        
        .btn-export.csv { background: #0284c7; }
        .btn-export.csv:hover { background: #0369a1; transform: translateY(-1px); }
        
        .btn-export.json { background: #4f46e5; }
        .btn-export.json:hover { background: #4338ca; transform: translateY(-1px); }

        .legend-section {
            background: #fff;
            border-left: 4px solid var(--label-blue);
            padding: 1.25rem 1.5rem;
            margin-bottom: 2.5rem;
            border-radius: 0 8px 8px 0;
            font-size: 0.85rem;
            color: #475569;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
        }
        .legend-section h3 {
            margin: 0 0 8px 0;
            font-size: 1rem;
            color: var(--label-blue);
            font-weight: 800;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="search-card">
                <div class="card-header-custom">
                    <h2 style="color:var(--title-red); margin:0; font-size:1.1rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">INFORME DE EJECUCIÓN DE PLAN</h2>
                </div>
                <form class="search-form" method="GET">
                    
                    <div class="search-row">
                        <div class="form-group">
                            <label>Convocatoria</label>
                            <select name="convocatoria" class="form-control" onchange="this.form.submit()">
                                <option value="">- Seleccionar convocatoria -</option>
                                <?php foreach($convocatorias as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre']) ?><?= !empty($c['codigo_expediente']) ? ' (' . htmlspecialchars($c['codigo_expediente']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="search-row">
                        <div class="form-group">
                            <label>Plan</label>
                            <select name="plan" class="form-control" onchange="this.form.submit()">
                                <option value="">- Seleccionar plan estratégico -</option>
                                <?php foreach($planes as $p): ?>
                                    <?php if ($convocatoria_id == 0 || $p['convocatoria_id'] == $convocatoria_id): ?>
                                        <option value="<?= $p['id'] ?>" <?= $plan_id == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nombre']) ?><?= !empty($p['codigo']) ? ' (' . htmlspecialchars($p['codigo']) . ')' : '' ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-buscar">Generar Informe</button>
                        <a href="informe_ejecucion_plan.php" class="btn-limpiar" style="text-decoration: none; display: inline-flex; align-items: center;">Limpiar filtros</a>
                    </div>
                </form>
            </div>

            <?php if ($plan_id > 0 || $convocatoria_id > 0): ?>
            
            <?php 
                $title_convocatoria = '';
                $title_plan = '';
                if ($selected_plan) {
                    $title_convocatoria = $selected_plan['convocatoria_abreviatura'] ?: $selected_plan['convocatoria_nombre'];
                    $title_plan = $selected_plan['nombre'];
                } elseif ($selected_convocatoria) {
                    $title_convocatoria = $selected_convocatoria['abreviatura'] ?: $selected_convocatoria['nombre'];
                    $title_plan = 'Todos los planes';
                }
            ?>

            <div class="plan-header-card">
                <h2><?= htmlspecialchars($title_convocatoria) ?> - <?= htmlspecialchars($title_plan) ?></h2>
                <p>Estado de ejecución</p>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th width="50">Nº AC</th>
                            <th width="80">COD</th>
                            <th>ACCIÓN FORMATIVA</th>
                            <th width="120">MODALIDAD</th>
                            <th width="60">HORAS</th>
                            <th width="50">PART</th>
                            <th width="50">GR</th>
                            <th width="50">GR<sub>EJ</sub></th>
                            <th width="40">AD</th>
                            <th width="40">F</th>
                            <th width="40">AB</th>
                            <th width="60" style="background:#2b2b2b;">TOTAL</th>
                            <th width="60" style="background:#2b2b2b;">PART<sub>P</sub></th>
                            <th width="40">BJ</th>
                            <th width="40">DSP</th>
                            <th width="40">AU</th>
                            <th width="40">TR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($acciones)): ?>
                            <tr>
                                <td colspan="17" style="text-align: center; padding: 3rem; color: #64748b; font-weight: 600;">
                                    No se encontraron acciones formativas para el plan seleccionado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                                $tot_horas = 0;
                                $tot_part = 0;
                                $tot_gr = 0;
                                $tot_grej = 0;
                                $tot_ad = 0;
                                $tot_f = 0;
                                $tot_ab = 0;
                                $tot_total = 0;
                                $tot_partp = 0;
                                $tot_bj = 0;
                                $tot_dsp = 0;
                                $tot_au = 0;
                                $tot_tr = 0;
                            ?>
                            <?php foreach ($acciones as $row): ?>
                                <?php
                                    // 1. Obtener grupos previstos (totales) de la base de datos
                                    $stmtGr = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE accion_id = ?");
                                    $stmtGr->execute([$row['id']]);
                                    $cnt_gr = (int)$stmtGr->fetchColumn();

                                    // 2. Obtener grupos ejecutados (válidos / activos)
                                    $stmtGrej = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE accion_id = ? AND situacion IN ('Válido', 'En curso', 'Finalizado')");
                                    $stmtGrej->execute([$row['id']]);
                                    $cnt_grej = (int)$stmtGrej->fetchColumn();

                                    // 3. Inicializar contadores de alumnos de esta acción
                                    $cnt_ad = 0;
                                    $cnt_f = 0;
                                    $cnt_ab = 0;
                                    $cnt_total = 0;
                                    $cnt_bj = 0;
                                    $cnt_dsp = 0;
                                    $cnt_au = 0;
                                    $cnt_tr = 0;

                                    // 4. Obtener las matrículas de la acción formativa actual
                                    $stmtM = $pdo->prepare("
                                        SELECT m.estado as matricula_estado, a.colectivo, a.sexo
                                        FROM matriculas m
                                        JOIN grupos g ON m.grupo_id = g.id
                                        JOIN alumnos a ON m.alumno_id = a.id
                                        WHERE g.accion_id = ?
                                    ");
                                    $stmtM->execute([$row['id']]);
                                    $mats = $stmtM->fetchAll();

                                    foreach ($mats as $m) {
                                        if ($m['matricula_estado'] === 'Baja') {
                                            $cnt_bj++;
                                        } else {
                                            $cnt_total++;
                                            
                                            $col = $m['colectivo'] ?? '';
                                            if (stripos($col, 'autónomo') !== false) {
                                                $cnt_au++;
                                            } elseif (stripos($col, 'desempleado') !== false || stripos($col, 'no ocupado') !== false) {
                                                $cnt_dsp++;
                                            } elseif (stripos($col, 'Régimen general') !== false || stripos($col, 'Trabajador') !== false) {
                                                $cnt_tr++;
                                            } elseif (stripos($col, 'administración') !== false) {
                                                $cnt_ad++;
                                            }
                                            
                                            if ($m['sexo'] === 'Mujer' || $m['sexo'] === 'Femenino') {
                                                $cnt_f++;
                                            }
                                            
                                            if (stripos($col, 'ERTE') !== false || stripos($col, 'ERE') !== false) {
                                                $cnt_ab++;
                                            }
                                        }
                                    }

                                    // Sumatorios para la fila final de Totales
                                    $tot_horas += (int)$row['duracion'];
                                    $tot_part += (int)($row['p'] ?? 0);
                                    $tot_gr += $cnt_gr;
                                    $tot_grej += $cnt_grej;
                                    $tot_ad += $cnt_ad;
                                    $tot_f += $cnt_f;
                                    $tot_ab += $cnt_ab;
                                    $tot_total += $cnt_total;
                                    $tot_partp += (int)($row['p'] ?? 0);
                                    $tot_bj += $cnt_bj;
                                    $tot_dsp += $cnt_dsp;
                                    $tot_au += $cnt_au;
                                    $tot_tr += $cnt_tr;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['num_accion'] ?? '0') ?></td>
                                    <td><?= htmlspecialchars($row['abreviatura'] ?? '') ?></td>
                                    <td class="text-left">
                                        <div style="font-weight: 700; color: #1e3a8a;"><?= htmlspecialchars($row['abreviatura'] ?? '') ?> - <?= htmlspecialchars($row['curso_titulo'] ?? $row['titulo']) ?></div>
                                    </td>
                                    <td class="text-left" style="font-weight: 600; color: #64748b;"><?= htmlspecialchars(ucfirst(strtolower($row['modalidad'] ?? ''))) ?></td>
                                    <td style="color: #b91c1c; font-weight: 700;"><?= htmlspecialchars($row['duracion'] ?? '0') ?></td>
                                    <td style="color: #334155; font-weight: 700;"><?= ($row['p'] ?? 0) ?: '' ?></td>
                                    <td><?= $cnt_gr ?: '' ?></td>
                                    <td><?= $cnt_grej ?: '' ?></td>
                                    <td><?= $cnt_ad ?: '' ?></td>
                                    <td><?= $cnt_f ?: '' ?></td>
                                    <td><?= $cnt_ab ?: '' ?></td>
                                    <td class="td-orange"><?= $cnt_total ?: '' ?></td>
                                    <td class="td-orange"><?= ($row['p'] ?? 0) ?: '' ?></td>
                                    <td><?= $cnt_bj ?: '' ?></td>
                                    <td><?= $cnt_dsp ?: '' ?></td>
                                    <td><?= $cnt_au ?: '' ?></td>
                                    <td><?= $cnt_tr ?: '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2">TOTAL</td>
                                <td colspan="2"></td>
                                <td style="color: #b91c1c; font-weight: 800;"><?= $tot_horas ?></td>
                                <td style="font-weight: 800;"><?= $tot_part ?: '' ?></td>
                                <td><?= $tot_gr ?: '' ?></td>
                                <td><?= $tot_grej ?: '' ?></td>
                                <td><?= $tot_ad ?: '' ?></td>
                                <td><?= $tot_f ?: '' ?></td>
                                <td><?= $tot_ab ?: '' ?></td>
                                <td class="td-orange"><?= $tot_total ?: '' ?></td>
                                <td class="td-orange"><?= $tot_partp ?: '' ?></td>
                                <td><?= $tot_bj ?: '' ?></td>
                                <td><?= $tot_dsp ?: '' ?></td>
                                <td><?= $tot_au ?: '' ?></td>
                                <td><?= $tot_tr ?: '' ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="export-actions">
                <button class="btn-export excel" onclick="exportarInforme('excel')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg> 
                    Exportar a Excel
                </button>
                <button class="btn-export csv" onclick="exportarInforme('csv')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg> 
                    Exportar a CSV
                </button>
                <button class="btn-export json" onclick="exportarInforme('json')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg> 
                    Exportar a JSON
                </button>
            </div>

            <div class="legend-section">
                <h3>Leyenda de Abreviaturas</h3>
                <p style="margin: 0 0 10px 0; font-weight: 500;">Esta es la guía de equivalencias de las columnas del informe:</p>
                <ul style="margin: 0; padding-left: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">
                    <li><strong>Nº AC</strong>: Número de Acción Formativa.</li>
                    <li><strong>COD</strong>: Código abreviado de la acción.</li>
                    <li><strong>PART</strong>: Participantes planificados inicialmente.</li>
                    <li><strong>GR</strong>: Número total de grupos previstos.</li>
                    <li><strong>GR<sub>EJ</sub></strong>: Número de grupos ejecutados / en curso.</li>
                    <li><strong>AD</strong>: Colectivo de Administración Pública.</li>
                    <li><strong>F</strong>: Participantes del género femenino (Mujeres).</li>
                    <li><strong>AB</strong>: Afectados por regulación de empleo (ERE / ERTE).</li>
                    <li><strong>TOTAL</strong>: Total de participantes activos actuales.</li>
                    <li><strong>PART<sub>P</sub></strong>: Participantes planificados con derecho a financiación.</li>
                    <li><strong>BJ</strong>: Bajas registradas (alumnos retirados).</li>
                    <li><strong>DSP</strong>: Desempleados y demandantes de empleo.</li>
                    <li><strong>AU</strong>: Trabajadores Autónomos por cuenta propia.</li>
                    <li><strong>TR</strong>: Trabajadores en Régimen General por cuenta ajena.</li>
                </ul>
            </div>
            <?php endif; ?>

        </main>
    </div>

<script>
function exportarInforme(formato) {
    const table = document.querySelector('.table-custom');
    if (!table) {
        alert('No hay datos para exportar.');
        return;
    }

    const headers = [
        "Nº AC", "COD", "ACCIÓN FORMATIVA", "MODALIDAD", "HORAS", 
        "PART", "GR", "GR_EJ", "AD", "F", "AB", 
        "TOTAL", "PART_P", "BJ", "DSP", "AU", "TR"
    ];

    const keys = [
        "n_ac", "cod", "accion_formativa", "modalidad", "horas",
        "part", "gr", "gr_ej", "ad", "f", "ab",
        "total", "part_p", "bj", "dsp", "au", "tr"
    ];

    const rows = [];
    const tbodyRows = table.querySelectorAll('tbody tr');
    
    let planTitle = "Informe_Ejecucion";
    const headerTitleEl = document.querySelector('.plan-header-card h2');
    if (headerTitleEl) {
        planTitle = headerTitleEl.innerText.trim();
    }
    // Clean filename for safety
    const safeTitle = planTitle.replace(/[^a-zA-Z0-9_\-]/g, "_");

    tbodyRows.forEach(tr => {
        // Skip empty state row
        if (tr.cells.length === 1 && tr.cells[0].colSpan > 1) {
            return;
        }
        
        const isTotal = tr.classList.contains('total-row');
        const rowData = [];
        for (let i = 0; i < tr.cells.length; i++) {
            rowData.push(tr.cells[i].innerText.trim());
        }
        
        if (isTotal) {
            const normalizedTotal = [
                "TOTAL", "", "", "", 
                rowData[2] || "0", // HORAS
                rowData[3] || "0", // PART
                rowData[4] || "0", // GR
                rowData[5] || "0", // GR_EJ
                rowData[6] || "0", // AD
                rowData[7] || "0", // F
                rowData[8] || "0", // AB
                rowData[9] || "0", // TOTAL
                rowData[10] || "0", // PART_P
                rowData[11] || "0", // BJ
                rowData[12] || "0", // DSP
                rowData[13] || "0", // AU
                rowData[14] || "0"  // TR
            ];
            rows.push({ data: normalizedTotal, isTotal: true });
        } else {
            rows.push({ data: rowData, isTotal: false });
        }
    });

    if (rows.length === 0) {
        alert('No hay filas de datos para exportar.');
        return;
    }

    if (formato === 'json') {
        const jsonArray = rows.map(row => {
            const obj = {};
            keys.forEach((key, idx) => {
                let val = row.data[idx];
                if (["n_ac", "horas", "part", "gr", "gr_ej", "ad", "f", "ab", "total", "part_p", "bj", "dsp", "au", "tr"].includes(key)) {
                    const num = parseFloat(val);
                    obj[key] = isNaN(num) ? (val === "" ? null : val) : num;
                } else {
                    obj[key] = val;
                }
            });
            if (row.isTotal) {
                obj["es_total"] = true;
            }
            return obj;
        });

        const jsonString = JSON.stringify(jsonArray, null, 2);
        downloadFile(jsonString, safeTitle + ".json", "application/json;charset=utf-8;");

    } else if (formato === 'csv') {
        let csvContent = "\xEF\xBB\xBF"; // UTF-8 BOM
        csvContent += headers.map(h => `"${h.replace(/"/g, '""')}"`).join(";") + "\r\n";

        rows.forEach(row => {
            csvContent += row.data.map(val => `"${val.replace(/"/g, '""')}"`).join(";") + "\r\n";
        });

        downloadFile(csvContent, safeTitle + ".csv", "text/csv;charset=utf-8;");

    } else if (formato === 'excel') {
        const excelXML = generateExcelXML(headers, rows, "Estado Ejecución", planTitle);
        downloadFile(excelXML, safeTitle + ".xls", "application/vnd.ms-excel;charset=utf-8;");
    }
}

function generateExcelXML(headers, rows, sheetName, planTitle) {
    const escapedPlanTitle = planTitle
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");

    let xml = `<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Intranet EFP</Author>
  <Created>${new Date().toISOString()}</Created>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="Title">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="14" ss:Color="#1E3A8A" ss:Bold="1"/>
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="Header">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="10" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#444444"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#444444"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#444444"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#444444"/>
   </Borders>
   <Interior ss:Color="#333333" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Cell">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="CellLeft">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="CellOrange">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="10" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
   <Interior ss:Color="#F59E0B" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Total">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="10" ss:Color="#0F172A" ss:Bold="1"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#94A3B8"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#94A3B8"/>
   </Borders>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TotalOrange">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="10" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#94A3B8"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#94A3B8"/>
   </Borders>
   <Interior ss:Color="#D97706" ss:Pattern="Solid"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="${sheetName.substring(0, 31)}">
  <Table>
   <Column ss:Width="50"/> 
   <Column ss:Width="60"/> 
   <Column ss:Width="250"/> 
   <Column ss:Width="90"/> 
   <Column ss:Width="50"/> 
   <Column ss:Width="50"/> 
   <Column ss:Width="40"/> 
   <Column ss:Width="45"/> 
   <Column ss:Width="40"/> 
   <Column ss:Width="40"/> 
   <Column ss:Width="40"/> 
   <Column ss:Width="60"/> 
   <Column ss:Width="60"/> 
   <Column ss:Width="40"/> 
   <Column ss:Width="40"/> 
   <Column ss:Width="40"/> 
   <Column ss:Width="40"/> 
   
   <Row ss:Height="30">
    <Cell ss:MergeAcross="16" ss:StyleID="Title"><Data ss:Type="String">${escapedPlanTitle} - Estado de ejecución</Data></Cell>
   </Row>
   <Row ss:Height="10"></Row>
   
   <Row ss:Height="25">`;
    headers.forEach(h => {
        xml += `  <Cell ss:StyleID="Header"><Data ss:Type="String">${h}</Data></Cell>\n`;
    });
    xml += ` </Row>\n`;

    rows.forEach(row => {
        xml += ` <Row ss:Height="${row.isTotal ? '24' : '20'}">\n`;
        row.data.forEach((val, idx) => {
            let style = "Cell";
            if (row.isTotal) {
                style = (idx === 11 || idx === 12) ? "TotalOrange" : "Total";
            } else {
                if (idx === 2 || idx === 3) {
                    style = "CellLeft";
                } else if (idx === 11 || idx === 12) {
                    style = "CellOrange";
                }
            }

            const isNumCol = [0, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16].includes(idx);
            let type = "String";
            let formattedVal = val;
            
            if (isNumCol && val !== "") {
                const parsed = parseFloat(val);
                if (!isNaN(parsed)) {
                    type = "Number";
                    formattedVal = parsed;
                }
            }

            // XML special character escaping
            if (type === "String" && typeof formattedVal === "string") {
                formattedVal = formattedVal
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&apos;");
            }

            xml += `  <Cell ss:StyleID="${style}"><Data ss:Type="${type}">${formattedVal}</Data></Cell>\n`;
        });
        xml += ` </Row>\n`;
    });

    xml += `  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <Selected/>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>`;
    return xml;
}

function downloadFile(content, fileName, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

</body>
</html>
