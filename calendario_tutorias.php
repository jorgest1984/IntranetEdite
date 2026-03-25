<?php
// calendario_tutorias.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_FORMADOR, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

// Variables iniciales
$error = '';
$tutores = [];

try {
    // Obtener Tutores (Rol 'Formador' o 'Tutor')
    $stmtTutores = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%') AND u.activo = 1 ORDER BY u.nombre ASC");
    $tutores = $stmtTutores->fetchAll();
} catch (Exception $e) {}

$current_page = 'calendario_tutorias.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Tutorías - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }

        .page-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-gray);
            padding-bottom: 10px;
        }

        .section-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .section-header {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-gray);
            font-size: 1.1rem;
            font-weight: 800;
            color: #1e293b;
        }

        .section-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: inline-block;
            font-weight: 700;
            color: var(--label-blue);
            font-size: 0.9rem;
            margin-right: 10px;
        }
        
        .form-text-muted {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 5px;
            display: block;
        }

        .form-control {
            font-size: 0.9rem;
            padding: 4px 8px;
            border: 1px solid var(--border-gray);
            border-radius: 3px;
            background: #fff;
            min-width: 200px;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover { background: #1d4ed8; }

        /* Results Table */
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .table-custom th {
            border-bottom: 2px solid var(--border-gray);
            padding: 10px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 700;
        }

        .table-custom td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px;
        }

        .table-custom tr.total-row {
            background: #f8fafc;
            font-weight: 700;
        }

        /* Leyenda */
        .leyenda-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .leyenda-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #334155;
            font-weight: 500;
        }

        .color-box {
            width: 24px;
            height: 16px;
            margin-right: 12px;
            border-radius: 2px;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .color-fin-semana { background: #991b1b; }
        .color-festivo { background: #ef4444; }
        .color-tutorias { background: #8b5cf6; }

    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <h1 class="page-title">CALENDARIO DE OCUPACIÓN DE TUTORES</h1>

            <div class="section-card">
                <div class="section-header">
                    Tutor/a - Filtro
                </div>
                <div class="section-body">
                    <form method="GET">
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                            <label style="width: 50px;">Tutor/a:</label>
                            <select name="tutor" class="form-control" style="width: 300px;">
                                <option value="">--- Seleccione tutor/a ---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                            <label>Mostrar calendario desde:</label>
                            <input type="date" name="fecha_desde" class="form-control" style="width: auto;">
                            <label style="margin-left: 10px;">hasta:</label>
                            <input type="date" name="fecha_hasta" class="form-control" style="width: auto;">
                        </div>
                        
                        <span class="form-text-muted">Si no se seleccionan fechas se mostrará el calendario de los próximos 2 meses.</span>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn-primary">Enviar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    Resumen por grupo
                </div>
                <div class="section-body" style="padding: 0;">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Sesiones</th>
                                <th>Horas en el periodo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Aquí se cargarán las filas iterando resultados -->
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 2rem; color: #64748b; font-style: italic;">
                                    Ningún tutor seleccionado o sin datos.
                                </td>
                            </tr>
                            <tr class="total-row">
                                <td>Total</td>
                                <td></td>
                                <td>0 h</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card" style="max-width: 400px;">
                <div class="section-header">
                    Leyenda
                </div>
                <div class="section-body">
                    <ul class="leyenda-list">
                        <li class="leyenda-item">
                            <div class="color-box color-fin-semana"></div>
                            Fin de semana
                        </li>
                        <li class="leyenda-item">
                            <div class="color-box color-festivo"></div>
                            Festivo
                        </li>
                        <li class="leyenda-item">
                            <div class="color-box color-tutorias"></div>
                            Horario de tutoría asignado a un grupo
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Aquí iría el Calendario interactivo (Grid) que se renderiza dinámicamente -->

        </main>
    </div>
</body>
</html>
