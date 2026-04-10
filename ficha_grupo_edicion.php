<?php
// ficha_grupo_edicion.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    die("No tiene permisos suficientes.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$accion_id = isset($_GET['accion_id']) ? (int)$_GET['accion_id'] : null;

$grupo = [];
$accion = [];
$tutores = [];
$centros = [];

try {
    // Tutors
    $stmtTutores = $pdo->query("SELECT a.id, CONCAT(a.nombre, ' ', a.primer_apellido) as nombre 
                                FROM alumnos a 
                                JOIN profesorado_detalles p ON a.id = p.alumno_id 
                                ORDER BY a.nombre ASC");
    if ($stmtTutores) $tutores = $stmtTutores->fetchAll(PDO::FETCH_ASSOC);

    // Centers
    $stmtCentros = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    if ($stmtCentros) $centros = $stmtCentros->fetchAll(PDO::FETCH_ASSOC);

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
        $stmt->execute([$id]);
        $grupo = $stmt->fetch();
        if ($grupo) {
            $accion_id = $grupo['accion_id'];
        }
    }

    if ($accion_id) {
        $stmt = $pdo->prepare("SELECT id, titulo, num_accion, plan_id FROM acciones_formativas WHERE id = ?");
        $stmt->execute([$accion_id]);
        $accion = $stmt->fetch();
    }

} catch (Throwable $e) {
    // Silently fail or log
}

$modalidades = ['Teleformación', 'Presencial', 'Mixta', 'Aula Virtual'];
$situaciones = ['Válido', 'Suspendido', 'Finalizado', 'Lista espera', 'Inactivo'];
$asignaciones = ['I', 'E', 'M'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición de Grupo | Intranet Edite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 1rem 2rem;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .header-title h1 {
            color: #b91c1c;
            font-size: 1.25rem;
            margin: 0;
            font-weight: 800;
            text-transform: uppercase;
        }

        .action-info-box {
            background: #f1f5f9;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        .action-info-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 5px;
        }

        .action-info-content {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e3a8a;
        }

        .form-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 2rem;
        }

        .form-section-title {
            color: #1e40af;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group.col-span-2 { grid-column: span 2; }
        .form-group.col-span-4 { grid-column: span 4; }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-save {
            background: #b91c1c;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-save:hover { background: #991b1b; }

        .btn-back {
            background: #fff;
            color: #64748b;
            border: 1px solid #cbd5e1;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-back:hover { background: #f8fafc; }

        .footer-actions {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="page-header">
        <div class="header-title">
            <h1><?= $id ? 'Editar Grupo' : 'Nuevo Grupo' ?></h1>
        </div>
        <div class="btn-group-header">
            <a href="ficha_accion_formativa.php?id=<?= $accion_id ?>&tab=grupos" class="btn-back">Volver a la ficha</a>
        </div>
    </div>

    <main class="main-content">
        <div class="action-info-box">
            <div class="action-info-title">Acción Formativa vinculada</div>
            <div class="action-info-content">
                <?= htmlspecialchars($accion['titulo'] ?? '---') ?> 
                <span style="color:#64748b; font-weight:400; font-size:0.9rem;">
                    (Acción Nº <?= htmlspecialchars($accion['num_accion'] ?? '0') ?>)
                </span>
            </div>
        </div>

        <form action="guardar_grupo.php" method="POST" class="form-card">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>
            <input type="hidden" name="accion_id" value="<?= $accion_id ?>">

            <div class="form-section-title">Datos del Grupo</div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Nº Grupo:</label>
                    <input type="text" name="numero_grupo" class="form-control" value="<?= htmlspecialchars($grupo['numero_grupo'] ?? '') ?>" placeholder="Ej: G1">
                </div>
                <div class="form-group col-span-2">
                    <label>Código Plataforma:</label>
                    <input type="text" name="codigo_plataforma" class="form-control" value="<?= htmlspecialchars($grupo['codigo_plataforma'] ?? '') ?>" placeholder="Ej: ADT-2024-01">
                </div>
                <div class="form-group">
                    <label>ID Plataforma (Moodle/Otro):</label>
                    <input type="text" name="id_plataforma" class="form-control" value="<?= htmlspecialchars($grupo['id_plataforma'] ?? '') ?>">
                </div>

                <div class="form-group col-span-2">
                    <label>Centro de Impartición:</label>
                    <select name="centro_id" class="form-control">
                        <option value="">Seleccione centro...</option>
                        <?php foreach ($centros as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($grupo['centro_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-span-2">
                    <label>Tutor / Docente:</label>
                    <select name="tutor_id" class="form-control">
                        <option value="">Seleccione tutor...</option>
                        <?php foreach ($tutores as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($grupo['tutor_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fecha Inicio:</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= $grupo['fecha_inicio'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Fecha Mitad:</label>
                    <input type="date" name="fecha_mitad" class="form-control" value="<?= $grupo['fecha_mitad'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Fecha 7 Días:</label>
                    <input type="date" name="fecha_7_dias" class="form-control" value="<?= $grupo['fecha_7_dias'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Fecha Fin:</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= $grupo['fecha_fin'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label>Modalidad:</label>
                    <select name="modalidad" class="form-control">
                        <?php foreach ($modalidades as $m): ?>
                            <option value="<?= $m ?>" <?= ($grupo['modalidad'] ?? '') == $m ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Asignación:</label>
                    <select name="asignacion" class="form-control">
                        <option value=""></option>
                        <?php foreach ($asignaciones as $a): ?>
                            <option value="<?= $a ?>" <?= ($grupo['asignacion'] ?? '') == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Situación:</label>
                    <select name="situacion" class="form-control">
                        <?php foreach ($situaciones as $s): ?>
                            <option value="<?= $s ?>" <?= ($grupo['situacion'] ?? '') == $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Horas:</label>
                    <input type="number" name="horas" class="form-control" value="<?= $grupo['horas'] ?? '' ?>">
                </div>
            </div>

            <div class="footer-actions">
                <button type="submit" class="btn-save">Guardar Cambios</button>
            </div>
        </form>
    </main>
</body>
</html>
