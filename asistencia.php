<?php
// asistencia.php
session_start();
require_once 'includes/auth.php';

// Formadores pueden entrar para pasar lista
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

$convocatoria_id = isset($_GET['convocatoria_id']) ? intval($_GET['convocatoria_id']) : 0;
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Procesar Guardado de Asistencia Masiva
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_asistencia') {
    $convocatoriaId = intval($_POST['convocatoria_id']);
    $fecha = $_POST['fecha'];
    $asistencias = $_POST['estado'] ?? [];
    $horas = $_POST['horas'] ?? [];
    $obs = $_POST['obs'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        $stmtInsert = $pdo->prepare("INSERT INTO asistencia (convocatoria_id, alumno_id, fecha, estado, horas, observaciones) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE estado=VALUES(estado), horas=VALUES(horas), observaciones=VALUES(observaciones)");
        
        $count = 0;
        foreach ($asistencias as $alumnoId => $estado) {
            $h = intval($horas[$alumnoId] ?? 0);
            $o = trim($obs[$alumnoId] ?? '');
            
            $stmtInsert->execute([$convocatoriaId, $alumnoId, $fecha, $estado, $h, $o]);
            $count++;
        }
        
        audit_log($pdo, 'ASISTENCIA_GUARDADA', 'asistencia', $convocatoriaId, null, ['fecha' => $fecha, 'total_registros' => $count]);
        $pdo->commit();
        
        $success = "Lista de asistencia guardada correctamente para la fecha " . date('d/m/Y', strtotime($fecha)) . " ($count registros).";
        
        // Mantener la selección
        $convocatoria_id = $convocatoriaId;
        $fecha_filtro = $fecha;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al guardar la asistencia: " . $e->getMessage();
    }
}

// Cargar convocatorias activas para el selector
$stmtConvs = $pdo->query("SELECT id, codigo_expediente, nombre FROM convocatorias WHERE estado IN ('Aprobada', 'En Ejecución') ORDER BY codigo_expediente DESC");
$convocatoriasActivas = $stmtConvs->fetchAll();

// Si hay convocatoria seleccionada, cargar los alumnos activos y la asistencia previa para esa fecha
$alumnos = [];
if ($convocatoria_id) {
    // Buscar alumnos activos en esa convocatoria, cruzando con la tabla de asistencia para la fecha dada (LEFT JOIN)
    $stmtAlumnos = $pdo->prepare("
        SELECT 
            m.alumno_id, 
            a.nombre, 
            a.primer_apellido,
            a.segundo_apellido, 
            a.dni,
            asist.estado as asistencia_estado,
            asist.horas as asistencia_horas,
            asist.observaciones as asistencia_obs
        FROM matriculas m
        INNER JOIN alumnos a ON m.alumno_id = a.id
        LEFT JOIN asistencia asist ON m.alumno_id = asist.alumno_id AND asist.convocatoria_id = m.convocatoria_id AND asist.fecha = ?
        WHERE m.convocatoria_id = ? AND m.estado = 'Activo'
        ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre
    ");
    $stmtAlumnos->execute([$fecha_filtro, $convocatoria_id]);
    $alumnos = $stmtAlumnos->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencia - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .filter-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .form-group { margin-bottom: 0; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .form-input { padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background-color: white; min-width: 250px;}
        .form-input:focus { border-color: var(--primary-color); outline: none; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; background: var(--card-bg); border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { font-weight: 600; color: var(--text-muted); background-color: #f8fafc; }
        .data-table tr:hover td { background-color: #fef2f2; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
        
        .estado-select { padding: 0.4rem; border-radius: 4px; border: 1px solid var(--border-color); font-weight: 600; }
        .estado-presente { background-color: #d1fae5; color: #059669; }
        .estado-falta { background-color: #fee2e2; color: #dc2626; }
        .estado-retraso { background-color: #fef3c7; color: #d97706; }
        
        .horas-input { width: 60px; padding: 0.4rem; border: 1px solid var(--border-color); border-radius: 4px; text-align: center; }
        .obs-input { width: 100%; padding: 0.4rem; border: 1px solid var(--border-color); border-radius: 4px; }
        
        .actions-bar { margin-top: 1.5rem; display: flex; justify-content: flex-end; }
        
        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); background: var(--card-bg); border-radius: 12px; border: 1px dashed var(--border-color); }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Control de Asistencia</h1>
                <p>Módulo de registro diario para convocatorias (obligatorio justificación)</p>
            </div>
        </header>

        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Seleccionar Expediente Activo *</label>
                    <select name="convocatoria_id" class="form-input" required onchange="this.form.submit()">
                        <option value="">-- Elige una convocatoria --</option>
                        <?php foreach ($convocatoriasActivas as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Fecha del Parte *</label>
                    <input type="date" name="fecha" class="form-input" value="<?= htmlspecialchars($fecha_filtro) ?>" required onchange="this.form.submit()">
                </div>
                
                <button type="submit" class="btn btn-primary">Cargar Lista</button>
            </form>
        </div>

        <?php if ($convocatoria_id): ?>
            <?php if (empty($alumnos)): ?>
                <div class="empty-state">
                    <h3>No hay alumnos activos para pasar lista</h3>
                    <p>Asegúrate de haber matriculado alumnos en este expediente y que su estado sea "Activo".</p>
                    <a href="matriculas.php?convocatoria_id=<?= $convocatoria_id ?>" class="btn btn-primary" style="margin-top: 1rem;">Ir a Matrículas</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_asistencia">
                    <input type="hidden" name="convocatoria_id" value="<?= $convocatoria_id ?>">
                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>">
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>DNI</th>
                                    <th style="width: 150px;">Estado Asistencia</th>
                                    <th style="width: 100px;">Horas Lectivas</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumnos as $alumno): 
                                    $estadoActual = $alumno['asistencia_estado'] ?? 'Presente';
                                    $horasActuales = $alumno['asistencia_horas'] !== null ? $alumno['asistencia_horas'] : 5; // Por defecto 5 horas
                                    $obsActual = $alumno['asistencia_obs'] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?>,</div>
                                        <div><?= htmlspecialchars($alumno['nombre']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($alumno['dni']) ?></td>
                                    <td>
                                        <select name="estado[<?= $alumno['alumno_id'] ?>]" class="estado-select <?= 'estado-'.strtolower(str_replace('Falta Justificada','falta', $estadoActual)) ?>" onchange="updateColors(this)">
                                            <option value="Presente" <?= $estadoActual=='Presente'?'selected':'' ?>>Presente</option>
                                            <option value="Falta" <?= $estadoActual=='Falta'?'selected':'' ?>>Falta Libre</option>
                                            <option value="Falta Justificada" <?= $estadoActual=='Falta Justificada'?'selected':'' ?>>Falta Justificada</option>
                                            <option value="Retraso" <?= $estadoActual=='Retraso'?'selected':'' ?>>Retraso</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="horas[<?= $alumno['alumno_id'] ?>]" class="horas-input" value="<?= $horasActuales ?>" min="0" max="10">
                                    </td>
                                    <td>
                                        <input type="text" name="obs[<?= $alumno['alumno_id'] ?>]" class="obs-input" placeholder="Justificante, nota..." value="<?= htmlspecialchars($obsActual) ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="actions-bar">
                        <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            Guardar Parte de Asistencia
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="var(--border-color)"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 9h-2V7h-2v5H6v2h2v5h2v-5h2v-2z"/></svg>
                <h3>Selecciona un Expediente arriba</h3>
                <p>Debes elegir una convocatoria y una fecha para cargar el listado de alumnos y pasar lista.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
function updateColors(select) {
    select.className = 'estado-select';
    if (select.value === 'Presente') select.classList.add('estado-presente');
    else if (select.value === 'Falta' || select.value === 'Falta Justificada') select.classList.add('estado-falta');
    else if (select.value === 'Retraso') select.classList.add('estado-retraso');
}
</script>
</body>
</html>
