<?php
// datos_certificacion_grupo.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/config.php';

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// 1. Obtener datos del grupo
$stmt = $pdo->prepare("SELECT g.*, af.num_accion, c.nombre_largo as curso_titulo
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       JOIN cursos c ON af.curso_id = c.id
                       WHERE g.id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch();
if (!$grupo) { die("Grupo no encontrado."); }

// 2. Obtener alumnos del grupo con todos los campos necesarios
$stmtAl = $pdo->prepare("
    SELECT m.id as matricula_id,
           m.estado as matricula_estado,
           m.certificables,
           a.id as alumno_id,
           CONCAT(a.primer_apellido, ' ', COALESCE(a.segundo_apellido,''), ' ', a.nombre) as alumno_nombre_completo,
           a.nombre, a.primer_apellido, a.segundo_apellido,
           a.dni,
           a.fecha_nacimiento,
           a.sexo,
           a.colectivo,
           a.desempleado_larga_duracion,
           a.contrato,
           a.provincia,
           e.nombre as empresa_nombre
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
    WHERE m.grupo_id = ?
    ORDER BY a.primer_apellido ASC, a.nombre ASC
");
$stmtAl->execute([$grupo_id]);
$alumnos = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

// Helper: calcular edad
function calc_edad($fecha_nac) {
    if (!$fecha_nac || $fecha_nac === '0000-00-00') return '—';
    $dt = new DateTime($fecha_nac);
    $now = new DateTime();
    return $dt->diff($now)->y;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos de Certificación - Grupo <?= htmlspecialchars($grupo['numero_grupo']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .cert-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .cert-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #dc2626;
            margin: 0 0 0.3rem 0;
            letter-spacing: 0.5px;
        }
        .cert-header .info-line {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin: 0;
        }
        .cert-header .info-line strong {
            color: var(--text-color);
        }

        .cert-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .cert-table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        .cert-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 1100px;
        }

        .cert-table th {
            background: rgba(0, 108, 228, 0.08);
            border-bottom: 2px solid rgba(0,108,228,0.2);
            padding: 10px 10px;
            text-align: left;
            font-weight: 700;
            color: var(--primary-color);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .cert-table td {
            padding: 10px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
            vertical-align: middle;
        }

        .cert-table tr:last-child td {
            border-bottom: none;
        }

        .cert-table tr:hover td {
            background-color: rgba(0, 108, 228, 0.02);
        }

        .alumno-name {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.82rem;
            line-height: 1.3;
        }

        .badge-sm {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 50px;
            font-weight: 700;
            display: inline-block;
            text-align: center;
            text-transform: uppercase;
        }

        .badge-admitido { background: rgba(16,185,129,0.1); color: #10b981; }
        .badge-baja     { background: rgba(239,68,68,0.1);  color: #ef4444; }
        .badge-pendiente{ background: rgba(245,158,11,0.1); color: #f59e0b; }
        .badge-si       { background: rgba(16,185,129,0.1); color: #10b981; }
        .badge-no       { background: rgba(239,68,68,0.1);  color: #ef4444; }
        .badge-default  { background: rgba(100,116,139,0.1);color: #64748b; }

        .block-all-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .btn-outline-sm {
            font-size: 0.8rem;
            padding: 0.45rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid rgba(0,108,228,0.3);
            background: rgba(0,108,228,0.05);
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-outline-sm:hover {
            background: rgba(0,108,228,0.1);
            transform: translateY(-1px);
        }

        .btn-back {
            background: rgba(0, 108, 228, 0.08);
            color: var(--primary-color);
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 108, 228, 0.15);
            font-size: 0.85rem;
        }
        .btn-back:hover {
            background: rgba(0, 108, 228, 0.12);
            transform: translateY(-2px);
        }

        .edit-select {
            background: transparent;
            border: 1px solid rgba(0,108,228,0.2);
            border-radius: 5px;
            padding: 3px 6px;
            font-size: 0.78rem;
            color: var(--text-color);
            cursor: pointer;
        }
        .edit-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/fp_sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto; padding: 2rem;">
            <!-- Breadcrumbs -->
            <div style="margin-bottom: 1.5rem; background: var(--glass-bg); padding: 0.75rem 1.5rem; border-radius: 10px; border: 1px solid var(--glass-border); font-size: 0.85rem; display: flex; gap: 8px; align-items: center;">
                <a href="home.php" style="color: var(--primary-color); text-decoration: none;">Inicio</a>
                <span style="color: var(--text-muted);">/</span>
                <a href="grupos.php" style="color: var(--primary-color); text-decoration: none;">Grupos</a>
                <span style="color: var(--text-muted);">/</span>
                <a href="ficha_grupo_edicion.php?id=<?= $grupo_id ?>" style="color: var(--primary-color); text-decoration: none;">Grupo</a>
                <span style="color: var(--text-muted);">/</span>
                <span style="color: var(--text-color); font-weight: 600;">Datos de Certificación</span>
            </div>

            <!-- Cabecera -->
            <div class="cert-header">
                <h2>Comprobación Requisitos Certificación por Grupo</h2>
                <p class="info-line">
                    Expediente: <strong><?= htmlspecialchars($grupo['num_accion']) ?></strong>, Acción: <strong><?= htmlspecialchars($grupo['numero_grupo']) ?></strong>, ADGD29 - <?= htmlspecialchars($grupo['curso_titulo']) ?>
                </p>
                <p class="info-line">
                    <?= !empty($grupo['fecha_inicio']) ? date('d/m/Y', strtotime($grupo['fecha_inicio'])) : '—' ?>
                    al
                    <?= !empty($grupo['fecha_fin']) ? date('d/m/Y', strtotime($grupo['fecha_fin'])) : '—' ?>,
                    Horario: <?= htmlspecialchars((!empty($grupo['horario_desde']) && !empty($grupo['horario_hasta'])) ? $grupo['horario_desde'].' a '.$grupo['horario_hasta'] : ($grupo['horario_info'] ?: '—')) ?>
                </p>
                <p class="info-line">Modalidad: <strong><?= htmlspecialchars($grupo['modalidad'] ?: '—') ?></strong></p>
            </div>

            <!-- Actions bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <a href="ficha_grupo_edicion.php?id=<?= $grupo_id ?>" class="btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Volver al Grupo
                </a>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                        Bloquear todos:
                        <input type="checkbox" id="blockAllCheck" style="cursor: pointer; width: 16px; height: 16px;">
                    </label>
                </div>
            </div>

            <!-- Tabla -->
            <div class="cert-card">
                <div class="cert-table-wrapper">
                    <table class="cert-table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>NIF</th>
                                <th>Empresa</th>
                                <th style="text-align:center;">&lt;10</th>
                                <th style="text-align:center;">Edad</th>
                                <th style="text-align:center;">Sexo</th>
                                <th>Estudios</th>
                                <th style="text-align:center;">Discap</th>
                                <th>Tipo Contrato</th>
                                <th style="text-align:center;">DSPLD</th>
                                <th style="text-align:center;">Colectivo</th>
                                <th>Provincia</th>
                                <th style="text-align:center;">Estado</th>
                                <th style="text-align:center;">Colectivo Prioritario</th>
                                <th style="text-align:center;">Certifica</th>
                                <th style="text-align:center;">Bloqueado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alumnos)): ?>
                                <tr>
                                    <td colspan="16" style="text-align: center; padding: 2rem; color: var(--text-muted); font-style: italic;">No hay alumnos matriculados en este grupo.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alumnos as $alumno): ?>
                                    <?php
                                    $apellidos = trim(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''));
                                    $nombre_comp = mb_strtoupper($apellidos . "\n" . $alumno['nombre']);
                                    $edad = calc_edad($alumno['fecha_nacimiento']);
                                    $sexo = mb_strtoupper(substr($alumno['sexo'] ?? '—', 0, 1));
                                    $colectivo = mb_strtoupper($alumno['colectivo'] ?? '—');
                                    $estado = $alumno['matricula_estado'] ?? '—';
                                    $certifica = $alumno['certificables'] ?? 'NO';
                                    $contrato = $alumno['contrato'] ?? '—';
                                    $dspld = ($alumno['desempleado_larga_duracion'] === 'SI' || $alumno['desempleado_larga_duracion'] === '1') ? 'SI' : 'NO';

                                    // Badge class estado
                                    $estado_lower = mb_strtolower($estado);
                                    if (in_array($estado_lower, ['admitido', 'inscrito', 'activo'])) {
                                        $estado_class = 'badge-admitido';
                                    } elseif ($estado_lower === 'baja') {
                                        $estado_class = 'badge-baja';
                                    } else {
                                        $estado_class = 'badge-pendiente';
                                    }
                                    $cert_class = $certifica === 'SI' ? 'badge-si' : 'badge-no';
                                    ?>
                                    <tr data-matricula-id="<?= $alumno['matricula_id'] ?>">
                                        <td>
                                            <div class="alumno-name">
                                                <?= htmlspecialchars(mb_strtoupper($apellidos)) ?><br>
                                                <span style="font-weight: 400; text-transform: none; color: var(--text-muted);"><?= htmlspecialchars($alumno['nombre']) ?></span>
                                            </div>
                                        </td>
                                        <td style="font-family: monospace; font-size: 0.8rem;"><?= htmlspecialchars($alumno['dni'] ?? '—') ?></td>
                                        <td style="max-width: 160px; word-break: break-word; font-size: 0.78rem;"><?= htmlspecialchars($alumno['empresa_nombre'] ?? '—') ?></td>
                                        <td style="text-align:center;">—</td>
                                        <td style="text-align:center; font-weight: 600;"><?= $edad ?></td>
                                        <td style="text-align:center; font-weight: 700;"><?= $sexo ?></td>
                                        <td>—</td>
                                        <td style="text-align:center;">—</td>
                                        <td style="font-size: 0.78rem; max-width: 130px;"><?= htmlspecialchars($contrato) ?></td>
                                        <td style="text-align:center;">
                                            <span class="badge-sm <?= $dspld === 'SI' ? 'badge-si' : 'badge-no' ?>"><?= $dspld ?></span>
                                        </td>
                                        <td style="text-align:center; font-weight: 600; font-size: 0.78rem;"><?= htmlspecialchars($colectivo) ?></td>
                                        <td style="font-size: 0.8rem;"><?= htmlspecialchars(mb_strtoupper($alumno['provincia'] ?? '—')) ?></td>
                                        <td style="text-align:center;">
                                            <span class="badge-sm <?= $estado_class ?>"><?= htmlspecialchars(mb_strtoupper($estado)) ?></span>
                                        </td>
                                        <td style="text-align:center; font-weight: 600;">SI</td>
                                        <td style="text-align:center;">
                                            <span class="badge-sm <?= $cert_class ?>"><?= $certifica ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" class="block-check" data-id="<?= $alumno['matricula_id'] ?>" title="Bloquear alumno" style="cursor: pointer; width: 16px; height: 16px;">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // "Bloquear todos" checkbox behavior
        document.getElementById('blockAllCheck').addEventListener('change', function() {
            const checks = document.querySelectorAll('.block-check');
            checks.forEach(c => c.checked = this.checked);
        });

        // Sync "bloquear todos" state when individual checkboxes change
        document.querySelectorAll('.block-check').forEach(cb => {
            cb.addEventListener('change', function() {
                const all = document.querySelectorAll('.block-check');
                const allChecked = [...all].every(c => c.checked);
                document.getElementById('blockAllCheck').checked = allChecked;
            });
        });
    </script>
</body>
</html>
