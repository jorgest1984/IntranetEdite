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
    // Tutors (Docentes) - Fetching from usuarios with Tutor/Formador roles
    $stmtTutores = $pdo->query("SELECT u.id, CONCAT(u.nombre, ' ', u.apellidos) as nombre 
                                FROM usuarios u 
                                JOIN roles r ON u.rol_id = r.id 
                                WHERE (r.nombre LIKE '%Tutor%' OR r.nombre LIKE '%Formador%') 
                                AND u.activo = 1
                                ORDER BY u.nombre ASC");
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
    <title>Ficha de Grupo | Intranet Edite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-content { padding: 1.5rem 2rem; max-width: 1400px; box-sizing: border-box; }
        
        /* Top crimson banner matching screenshot */
        .top-banner {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 6px;
            box-shadow: 0 4px 10px rgba(185, 28, 28, 0.15);
        }
        
        .banner-btn {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.68rem;
            font-weight: 700;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
        }
        
        .banner-btn:hover {
            background: #ffffff;
            color: #b91c1c;
            border-color: #ffffff;
            transform: translateY(-1px);
        }

        .page-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
        }

        .page-header-premium h1 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1e3a8a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Navigation Tabs */
        .tabs-container {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 1px;
        }

        .tab-item {
            padding: 8px 16px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            background: #f1f5f9;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .tab-item:hover {
            background: #e2e8f0;
            color: #1e3a8a;
        }

        .tab-item.active {
            background: #ffffff;
            color: #1e3a8a;
            border-color: #cbd5e1 #cbd5e1 #ffffff #cbd5e1;
            position: relative;
            bottom: -2px;
            z-index: 2;
        }

        .action-info-box {
            background: #fff;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .action-info-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 4px;
        }

        .action-info-content {
            font-size: 1rem;
            font-weight: 700;
            color: #1e3a8a;
        }

        .form-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            padding: 24px;
        }

        .form-section-title {
            color: #1e3a8a;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 18px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.col-span-2 { grid-column: span 2; }
        .form-group.col-span-4 { grid-column: span 4; }

        .form-group label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-save {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 10px rgba(30, 58, 138, 0.2);
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(30, 58, 138, 0.3);
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        .footer-actions {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/fp_sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            <!-- Top Actions Crimson Bar -->
            <div class="top-banner">
                <a href="#" class="banner-btn">Generar PDF evaluaciones grupo</a>
                <a href="#" class="banner-btn">Informe de conexión</a>
                <a href="calendario.php" class="banner-btn">Ver calendario</a>
                <a href="#" class="banner-btn">S20</a>
                <a href="#" class="banner-btn">Registro de diplomas</a>
                <a href="asistencia.php?grupo_id=<?= $id ?>" class="banner-btn">Asistencia presenciales</a>
                <a href="subir_documento.php?grupo_id=<?= $id ?>" class="banner-btn">Subir documento</a>
                <a href="documentacion.php?grupo_id=<?= $id ?>" class="banner-btn">Documentos</a>
                <a href="#" class="banner-btn">Ficha grupo</a>
                <a href="home.php" class="banner-btn">Página Inicio</a>
                <a href="logout.php" class="banner-btn" style="background: rgba(0,0,0,0.2);">Desconectar</a>
            </div>

            <div class="page-header-premium">
                <h1>Ficha de Grupo</h1>
            </div>

            <!-- Tabs Navigation -->
            <?php if ($id): ?>
                <div class="tabs-container">
                    <a href="#" class="tab-item">DS-15 Acción</a>
                    <a href="#" class="tab-item">Recalcular alumnos tutor</a>
                    <a href="gestion_matriculas.php?af_id=<?= $accion_id ?>" class="tab-item">Listado de alumnos</a>
                    <a href="relacion_alumnos.php?grupo_id=<?= $id ?>" class="tab-item active">Listado de alumnos nuevo</a>
                </div>
            <?php endif; ?>

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
                        <label>ID Plataforma (Moodle):</label>
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
    </div>
</body>
</html>
