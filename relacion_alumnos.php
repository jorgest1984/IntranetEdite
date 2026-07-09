<?php
// relacion_alumnos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COORD])) {
    header("Location: home.php");
    exit();
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);

if (!$grupo_id) {
    die("ID de grupo no proporcionado.");
}

// Auto-saneamiento de la base de datos (por si faltan columnas en la tabla matriculas)
try {
    $stmtCols = $pdo->query("DESCRIBE matriculas");
    $existing_cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('facturables', $existing_cols)) {
        $pdo->exec("ALTER TABLE matriculas ADD COLUMN facturables VARCHAR(10) DEFAULT 'NO'");
    }
    if (!in_array('certificables', $existing_cols)) {
        $pdo->exec("ALTER TABLE matriculas ADD COLUMN certificables VARCHAR(10) DEFAULT 'NO'");
    }
    if (!in_array('diploma_entregado', $existing_cols)) {
        $pdo->exec("ALTER TABLE matriculas ADD COLUMN diploma_entregado TINYINT(1) DEFAULT 0");
    }
    if (!in_array('fecha_comunicacion', $existing_cols)) {
        $pdo->exec("ALTER TABLE matriculas ADD COLUMN fecha_comunicacion DATE DEFAULT NULL");
    }
} catch (Exception $e) {
    // Silencioso
}

$success_msg = '';
$error_msg = '';

// Procesar Guardar Cambios (Facturables)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_facturables'])) {
    try {
        $facturables_post = $_POST['facturable'] ?? []; // Array of matricula_id => '1'
        
        // Primero obtenemos todas las matrículas asociadas a este grupo
        $stmtAllM = $pdo->prepare("SELECT id FROM matriculas WHERE grupo_id = ?");
        $stmtAllM->execute([$grupo_id]);
        $all_m_ids = $stmtAllM->fetchAll(PDO::FETCH_COLUMN);

        $stmtUpdate = $pdo->prepare("UPDATE matriculas SET facturables = ? WHERE id = ?");
        
        $pdo->beginTransaction();
        foreach ($all_m_ids as $mid) {
            $is_facturable = isset($facturables_post[$mid]) ? 'SI' : 'NO';
            $stmtUpdate->execute([$is_facturable, $mid]);
        }
        $pdo->commit();
        $success_msg = "Cambios guardados correctamente.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error al guardar cambios: " . $e->getMessage();
    }
}

// Procesar Baja/Eliminación de matrícula si se solicita
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['matricula_id'])) {
    $matricula_id = (int)$_GET['matricula_id'];
    try {
        $stmtDel = $pdo->prepare("DELETE FROM matriculas WHERE id = ? AND grupo_id = ?");
        $stmtDel->execute([$matricula_id, $grupo_id]);
        $success_msg = "Matrícula eliminada correctamente.";
    } catch (Exception $e) {
        $error_msg = "Error al eliminar matrícula: " . $e->getMessage();
    }
}

try {
    // Obtener datos del grupo
    $stmtGrupo = $pdo->prepare("
        SELECT g.*, af.id as accion_id, af.abreviatura as af_abreviatura, 
               c.nombre_largo as curso_titulo, c.nombre_corto as curso_codigo
        FROM grupos g
        INNER JOIN acciones_formativas af ON g.accion_id = af.id
        INNER JOIN cursos c ON af.curso_id = c.id
        WHERE g.id = ?
    ");
    $stmtGrupo->execute([$grupo_id]);
    $group = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        die("Grupo no encontrado.");
    }

    // Obtener alumnos matriculados
    $stmtAlumnos = $pdo->prepare("
        SELECT m.id as matricula_id, m.estado as matricula_estado, m.fecha_matricula, m.facturables, m.certificables, m.diploma_entregado, m.fecha_comunicacion,
               a.id as alumno_id, CONCAT(a.nombre, ' ', a.primer_apellido, ' ', COALESCE(a.segundo_apellido, '')) as alumno_nombre,
               a.dni, a.fecha_nacimiento, a.localidad, a.provincia,
               e.nombre as empresa_nombre, e.cif as empresa_cif
        FROM matriculas m
        INNER JOIN alumnos a ON m.alumno_id = a.id
        LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
        WHERE m.grupo_id = ?
        ORDER BY a.nombre ASC, a.primer_apellido ASC
    ");
    $stmtAlumnos->execute([$grupo_id]);
    $alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error de base de datos: " . $e->getMessage());
}

$current_page = 'grupos.php'; // Para marcar activo en la sidebar
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relación de Alumnos | Intranet Edite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-content { padding: 2rem; max-width: 100%; box-sizing: border-box; }
        
        .relacion-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .relacion-header h1 {
            color: #b91c1c;
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .relacion-header .sub-info {
            color: #b91c1c;
            font-weight: 700;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .table-responsive {
            overflow-x: auto;
            max-height: calc(100vh - 260px);
            overflow-y: auto;
            width: 100%;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #cbd5e1;
            margin-bottom: 20px;
        }

        .table-responsive::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 6px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 6px;
            border: 3px solid #f1f5f9;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .table-relacion {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            text-align: left;
        }

        .table-relacion th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 0.78rem;
            white-space: nowrap;
        }

        .table-relacion td {
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
            color: #334155;
            white-space: nowrap;
        }

        .table-relacion tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .table-relacion tr:hover td {
            background-color: #f1f5f9;
        }

        .table-relacion td.alumno-highlight,
        .table-relacion td:first-child {
            white-space: normal;
        }

        .alumno-highlight {
            font-weight: 700;
            color: #d97706; /* Orange color */
            text-transform: uppercase;
        }

        .badge-status {
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-finalizado { background-color: rgba(22, 163, 74, 0.1); color: #16a34a; }
        .badge-abandono { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .badge-otros { background-color: rgba(100, 116, 139, 0.1); color: #64748b; }

        .btn-action-relacion {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-action-relacion:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn-edit { color: #d97706; border-color: rgba(217, 119, 6, 0.3); }
        .btn-edit:hover { background-color: rgba(217, 119, 6, 0.05); }

        .btn-user { color: #2563eb; border-color: rgba(37, 99, 237, 0.3); }
        .btn-user:hover { background-color: rgba(37, 99, 237, 0.05); }

        .btn-doc { color: #475569; border-color: rgba(71, 85, 105, 0.3); }
        .btn-doc:hover { background-color: rgba(71, 85, 105, 0.05); }

        .btn-delete { color: #dc2626; border-color: rgba(220, 38, 38, 0.3); }
        .btn-delete:hover { background-color: rgba(220, 38, 38, 0.05); }

        .footer-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 15px;
            gap: 10px;
        }

        .registros-count {
            font-weight: 700;
            color: #1e3a8a;
            font-size: 0.9rem;
        }

        .btn-volver {
            background-color: #cbd5e1;
            color: #334155;
            padding: 6px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid #b8c2cc;
            transition: all 0.2s;
        }

        .btn-volver:hover {
            background-color: #b8c2cc;
        }

        .btn-guardar {
            background-color: #f1f5f9;
            color: #334155;
            padding: 8px 24px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .btn-guardar:hover {
            background-color: #e2e8f0;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto;">
        <div class="relacion-header">
            <h1>RELACIÓN DE ALUMNOS</h1>
            <div class="sub-info">
                Grupo: <?= htmlspecialchars($group['numero_grupo'] ?? '') ?> <?= htmlspecialchars($group['curso_codigo'] ?? '') ?> - <?= htmlspecialchars($group['curso_titulo'] ?? '') ?><br>
                <?= htmlspecialchars($group['fecha_inicio'] ? date('d/m/Y', strtotime($group['fecha_inicio'])) : '—') ?> al <?= htmlspecialchars($group['fecha_fin'] ? date('d/m/Y', strtotime($group['fecha_fin'])) : '—') ?><?= !empty($group['horas']) ? ', de 09:00 a 10:00 h' : '' ?><br>
                Modalidad: <?= htmlspecialchars($group['modalidad'] ?? '—') ?>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="guardar_facturables" value="1">
            <div class="table-responsive">
                <table class="table-relacion">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Alumno</th>
                            <th>Empresa</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>NIF</th>
                            <th>Fecha nac.</th>
                            <th>Estado</th>
                            <th>Certifica</th>
                            <th>Diploma</th>
                            <th>Fecha Diploma</th>
                            <th style="text-align: center;">Facturable</th>
                            <th style="width: 30px; border-left: none;"></th>
                            <th style="width: 30px;"></th>
                            <th style="width: 30px;"></th>
                            <th style="width: 30px; border-right: none;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alumnos)): ?>
                            <tr>
                                <td colspan="16" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay alumnos matriculados en este grupo.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alumnos as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($group['curso_codigo'] ?? '') ?> - <?= htmlspecialchars($group['curso_titulo'] ?? '') ?></td>
                                    <td class="alumno-highlight"><?= htmlspecialchars($row['alumno_nombre'] ?? '') ?></td>
                                    <td>
                                        <?php if (!empty($row['empresa_nombre'])): ?>
                                            <?= htmlspecialchars($row['empresa_nombre']) ?> [<?= htmlspecialchars($row['empresa_cif']) ?>]
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['localidad'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($row['provincia'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($row['dni'] ?? '—') ?></td>
                                    <td><?= $row['fecha_nacimiento'] ? date('d/m/Y', strtotime($row['fecha_nacimiento'])) : '—' ?></td>
                                    <td>
                                        <?php 
                                        $est = mb_strtolower($row['matricula_estado'] ?? '');
                                        if (strpos($est, 'fin') !== false) {
                                            echo '<span class="badge-status badge-finalizado">Finalizado</span>';
                                        } elseif (strpos($est, 'aban') !== false) {
                                            echo '<span class="badge-status badge-abandono">Abandono</span>';
                                        } else {
                                            echo '<span class="badge-status badge-otros">' . htmlspecialchars($row['matricula_estado'] ?? '—') . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['certificables'] ?? '—') ?></td>
                                    <td><?= !empty($row['diploma_entregado']) ? 'SI' : 'NO' ?></td>
                                    <td><?= $row['fecha_comunicacion'] ? date('d/m/Y', strtotime($row['fecha_comunicacion'])) : '—' ?></td>
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="facturable[<?= $row['matricula_id'] ?>]" value="1" <?= ($row['facturables'] ?? '') === 'SI' ? 'checked' : '' ?> style="width: 16px; height: 16px; cursor: pointer;">
                                    </td>
                                    <!-- Editar Matrícula -->
                                    <td style="border-left: none; text-align: center;">
                                        <a href="ficha_matricula.php?id=<?= $row['matricula_id'] ?>" class="btn-action-relacion btn-edit" title="Editar Matrícula">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                    </td>
                                    <!-- Ver Ficha Alumno -->
                                    <td style="text-align: center;">
                                        <a href="ficha_alumno.php?id=<?= $row['alumno_id'] ?>" class="btn-action-relacion btn-user" title="Ficha del Alumno">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        </a>
                                    </td>
                                    <!-- Documentos -->
                                    <td style="text-align: center;">
                                        <a href="ficha_matricula.php?id=<?= $row['matricula_id'] ?>&active_tab=tab-documentacion" class="btn-action-relacion btn-doc" title="Documentación">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        </a>
                                    </td>
                                    <!-- Dar de baja matrícula -->
                                    <td style="border-right: none; text-align: center;">
                                        <a href="relacion_alumnos.php?grupo_id=<?= $grupo_id ?>&action=delete&matricula_id=<?= $row['matricula_id'] ?>" class="btn-action-relacion btn-delete" title="Eliminar Matrícula" onclick="return confirm('¿Seguro que deseas dar de baja este alumno de este grupo?');">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($alumnos)): ?>
                <div style="display: flex; justify-content: flex-end; margin-right: 5px;">
                    <button type="submit" class="btn-guardar">Guardar cambios</button>
                </div>
            <?php endif; ?>
        </form>

        <div class="footer-info">
            <div class="registros-count"><?= count($alumnos) ?> registros.</div>
            <a href="grupos.php" class="btn-volver">Volver</a>
        </div>
    </main>
</div>
</body>
</html>
