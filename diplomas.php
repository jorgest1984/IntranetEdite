<?php
// diplomas.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$accion_id = intval($_GET['accion_id'] ?? 0);
$grupo_id = intval($_GET['grupo_id'] ?? 0);

if (!$accion_id || !$grupo_id) {
    die("Faltan parámetros requeridos.");
}

// Obtener datos del grupo y acción
$stmt = $pdo->prepare("SELECT g.numero_grupo, af.titulo FROM grupos g JOIN acciones_formativas af ON g.accion_id = af.id WHERE g.id = ? AND af.id = ?");
$stmt->execute([$grupo_id, $accion_id]);
$grupo_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo_info) {
    die("Grupo o acción formativa no encontrados.");
}

// Obtener alumnos del grupo con sus notas
$stmtAlumnos = $pdo->prepare("SELECT m.alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, 
                                     m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade
                              FROM matriculas m
                              JOIN alumnos a ON m.alumno_id = a.id
                              WHERE m.grupo_id = ?
                              ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre");
$stmtAlumnos->execute([$grupo_id]);
$alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diplomas y Certificados - <?= htmlspecialchars($grupo_info['titulo']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 1.8rem; color: var(--text-color); }
        .page-subtitle { font-size: 1rem; color: var(--text-muted); margin-top: 5px; }
        .table-responsive { overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background: #f8fafc; font-weight: 600; color: #475569; }
        .btn-action { padding: 8px 12px; border-radius: 6px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; font-size: 0.9rem; }
        .btn-diploma { background: #0ea5e9; color: white; }
        .btn-diploma:hover { background: #0284c7; }
        .btn-diploma:disabled { background: #cbd5e1; cursor: not-allowed; }
        .btn-certificado { background: #10b981; color: white; }
        .btn-certificado:hover { background: #059669; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Diplomas y Certificados</h1>
                <div class="page-subtitle"><?= htmlspecialchars($grupo_info['titulo']) ?> (Grupo <?= htmlspecialchars($grupo_info['numero_grupo']) ?>)</div>
            </div>
            <a href="documentacion.php" class="btn" style="border: 1px solid #cbd5e1;">Volver a Documentación</a>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Nombre y Apellidos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alumnos as $alumno): 
                        $grades = [];
                        if ($alumno['moodle_e1_grade'] !== null) $grades[] = (float)$alumno['moodle_e1_grade'];
                        if ($alumno['moodle_e2_grade'] !== null) $grades[] = (float)$alumno['moodle_e2_grade'];
                        if ($alumno['moodle_e3_grade'] !== null) $grades[] = (float)$alumno['moodle_e3_grade'];
                        
                        $apto = false;
                        if (count($grades) > 0) {
                            $media = array_sum($grades) / count($grades);
                            if ($media >= 5) {
                                $apto = true;
                            }
                        } else {
                            // Si no hay notas registradas, asumimos apto como en las actas (o podríamos dejarlo manual)
                            $apto = true; 
                        }
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($alumno['dni']) ?></td>
                            <td><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?></td>
                            <td>
                                <?php if ($apto): ?>
                                    <span style="color: #059669; font-weight: 600; padding: 4px 8px; background: #d1fae5; border-radius: 12px; font-size: 0.85rem;">APTO</span>
                                <?php else: ?>
                                    <span style="color: #dc2626; font-weight: 600; padding: 4px 8px; background: #fee2e2; border-radius: 12px; font-size: 0.85rem;">NO APTO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <?php if ($apto): ?>
                                        <a href="pdf_diploma.php?alumno_id=<?= $alumno['alumno_id'] ?>&grupo_id=<?= $grupo_id ?>&accion_id=<?= $accion_id ?>&tipo=diploma" target="_blank" class="btn-action btn-diploma">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15l-3-3m0 0l3-3m-3 3h8M2 12a10 10 0 1 1 20 0 10 10 0 0 1-20 0z"/></svg>
                                            Generar Diploma
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-action btn-diploma" disabled title="Solo para alumnos APTOS">
                                            Generar Diploma
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="pdf_diploma.php?alumno_id=<?= $alumno['alumno_id'] ?>&grupo_id=<?= $grupo_id ?>&accion_id=<?= $accion_id ?>&tipo=certificado" target="_blank" class="btn-action btn-certificado">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        Generar Certificado
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($alumnos)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #64748b;">No hay alumnos matriculados en este grupo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
