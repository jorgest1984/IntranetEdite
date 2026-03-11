<?php
// informes.php
session_start();
require_once 'includes/auth.php';

// Formadores no tienen acceso a esta parte por norma ISO 27001 (datos globales)
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$convocatoria_id = isset($_GET['convocatoria_id']) ? intval($_GET['convocatoria_id']) : 0;

// Cargar convocatorias para el selector
$stmtConvs = $pdo->query("SELECT id, codigo_expediente, nombre, tipo FROM convocatorias ORDER BY creado_en DESC");
$convocatorias = $stmtConvs->fetchAll();

$reportData = [];
$convocatoriaInfo = null;

if ($convocatoria_id) {
    // 1. Info básica de la convocatoria
    $stmtConv = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
    $stmtConv->execute([$convocatoria_id]);
    $convocatoriaInfo = $stmtConv->fetch();
    
    // 2. Info estadística de alumnos (faltas vs asistencia)
    // Usamos GROUP BY con IFs para contar faltas vs asistentes
    $queryReport = "
        SELECT 
            m.alumno_id, 
            a.nombre, 
            a.primer_apellido,
            a.segundo_apellido,
            a.dni,
            m.estado as estado_matricula,
            COUNT(asist.id) as total_partes,
            SUM(IF(asist.estado = 'Presente' OR asist.estado = 'Retraso', 1, 0)) as dias_asistidos,
            SUM(IF(asist.estado = 'Falta' OR asist.estado = 'Falta Justificada', 1, 0)) as dias_ausentes,
            SUM(asist.horas) as horas_totales_registradas
        FROM matriculas m
        INNER JOIN alumnos a ON m.alumno_id = a.id
        LEFT JOIN asistencia asist ON m.alumno_id = asist.alumno_id AND asist.convocatoria_id = m.convocatoria_id
        WHERE m.convocatoria_id = ?
        GROUP BY m.alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, m.estado
        ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre
    ";
    
    $stmtRep = $pdo->prepare($queryReport);
    $stmtRep->execute([$convocatoria_id]);
    $reportData = $stmtRep->fetchAll();
    
    audit_log($pdo, 'GENERACION_INFORME', 'convocatorias', $convocatoria_id, null, ['tipo' => 'Estadistica Global Asistencia']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informes y Reportes - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .filter-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .form-group { margin-bottom: 0; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .form-input { padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background-color: white; min-width: 350px;}
        
        .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .export-btn { background-color: #f3f4f6; color: #374151; border: 1px solid var(--border-color); padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .export-btn:hover { background-color: #e5e7eb; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; background: var(--card-bg); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { font-weight: 600; color: var(--text-color); background-color: #f8fafc; }
        .data-table tr:hover td { background-color: #fef2f2; }
        
        @media print {
            .sidebar, .page-header, .filter-card, .export-btn { display: none !important; }
            .app-container { display: block; padding: 0; }
            .main-content { margin: 0; padding: 0; }
            .data-table { border: 1px solid #000; }
            .data-table th, .data-table td { border-bottom: 1px solid #000; }
            body { background: white; }
        }
        
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-neutral { background: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Informes de Rendimiento</h1>
                <p>Generación de actas para justificación (SEPE/FUNDAE)</p>
            </div>
        </header>

        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Generar informe del Expediente:</label>
                    <select name="convocatoria_id" class="form-input" required onchange="this.form.submit()">
                        <option value="">-- Selecciona una Convocatoria --</option>
                        <?php foreach ($convocatorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?> (<?= $c['tipo'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Extraer Datos</button>
            </form>
        </div>

        <?php if ($convocatoria_id && $convocatoriaInfo): ?>
            
            <div class="report-header">
                <div>
                    <h2 style="margin:0 0 0.5rem 0;">Informe Resumen de Asistencia</h2>
                    <p style="margin:0; color:var(--text-muted); font-size:0.9rem;">
                        <strong>Expediente:</strong> <?= htmlspecialchars($convocatoriaInfo['codigo_expediente']) ?> | 
                        <strong>Tipo:</strong> <?= htmlspecialchars($convocatoriaInfo['tipo']) ?> | 
                        <strong>Generado:</strong> <?= date('d/m/Y H:i') ?>
                    </p>
                </div>
                <div>
                    <button class="export-btn" onclick="window.print()">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                        Imprimir / Guardar PDF
                    </button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>DNI/NIE</th>
                            <th>Estado Actual</th>
                            <th>Partes Totales</th>
                            <th>Días Asistidos</th>
                            <th>Faltas</th>
                            <th>Tot. Horas</th>
                            <th>% Asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="8" style="text-align:center;">No hay alumnos matriculados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): 
                                $totalDias = intval($row['total_partes']);
                                $faltas = intval($row['dias_ausentes']);
                                $asistencias = intval($row['dias_asistidos']);
                                $porcentaje = $totalDias > 0 ? round(($asistencias / $totalDias) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['primer_apellido'] . ' ' . $row['segundo_apellido']) ?>, <?= htmlspecialchars($row['nombre']) ?></td>
                                <td><?= htmlspecialchars($row['dni']) ?></td>
                                <td>
                                    <?php if($row['estado_matricula'] == 'Baja'): ?>
                                        <span class="badge badge-danger">Baja</span>
                                    <?php elseif($row['estado_matricula'] == 'Activo'): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral"><?= htmlspecialchars($row['estado_matricula']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><?= $totalDias ?></td>
                                <td style="text-align:center; color:#059669; font-weight:600;"><?= $asistencias ?></td>
                                <td style="text-align:center; color:#dc2626; font-weight:600;"><?= $faltas ?></td>
                                <td style="text-align:center; font-weight: bold;"><?= intval($row['horas_totales_registradas']) ?>h</td>
                                <td style="text-align:right;">
                                    <?php if($porcentaje < 75 && $totalDias > 0): ?>
                                        <span style="color:#dc2626; font-weight:bold;"><?= $porcentaje ?>% ⚠️</span>
                                    <?php else: ?>
                                        <span><?= $porcentaje ?>%</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 1rem;">
                * <strong>Nota:</strong> Para FUNDAE/SEPE el porcentaje mínimo de asistencia para tener derecho a diploma suele ser del 75%. Los alumnos por debajo de este umbral están marcados con ⚠️. Esta información está auditada y es inmutable.
            </p>

        <?php elseif(empty($convocatoria_id)): ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-muted); border: 1px dashed var(--border-color); border-radius: 12px;">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor" style="opacity: 0.3; margin-bottom: 1rem;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                <p>Selecciona una convocatoria para generar su informe de resultados.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>
