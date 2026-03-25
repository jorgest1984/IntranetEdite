<?php
// informe_cambios_estado.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'informe_cambios_estado.php';

// Lista de estados según la captura enviada
$estados = [
    'Abandono', 'Admitido', 'Baja', 'Baja por colocación', 'Empleado en curso', 
    'Espera', 'Finalizado', 'Finalizado sobrante', 'Inscrito', 'Pendiente docu', 
    'Pendiente estado', 'Pendiente otro curso', 'Pendiente validacion', 
    'Preinscrito', 'Reserva'
];

$resultados_simulados = false;
if (isset($_GET['estado']) || isset($_GET['desde'])) {
    $resultados_simulados = true;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Cambios de Estado - <?= APP_NAME ?></title>
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
        .main-content { padding: 2rem; }

        .breadcrumb {
            background-color: #f8fafc;
            padding: 12px 20px;
            border-radius: 4px;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 1px solid #e2e8f0;
        }
        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .page-header-title {
            margin-bottom: 2rem;
        }
        .page-header-title h1 {
            font-size: 1.8rem;
            color: #334155;
            font-weight: 700;
            margin: 0;
        }
        .page-header-title h2 {
            font-size: 1.4rem;
            color: #475569;
            font-weight: 600;
            margin: 5px 0 0 0;
        }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .search-form {
            padding: 2rem;
        }

        .search-row-columns {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .form-group-col {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group-col label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.9rem;
        }

        .form-control {
            font-size: 0.9rem;
            padding: 8px 12px;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            background: #fff;
        }
        
        input[type="date"].form-control {
            min-width: 160px;
        }
        select.form-control {
            min-width: 300px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .btn-buscar {
            background: #2563eb;
            color: white;
            border: 1px solid #1d4ed8;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-buscar:hover { background: #1d4ed8; }

        .btn-limpiar {
            background: #ef4444;
            color: white;
            border: 1px solid #dc2626;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-limpiar:hover { background: #dc2626; }

        /* Contenedor de tabla */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 1.5rem;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .table-custom th {
            padding: 12px 15px;
            text-align: left;
            color: #fff;
            font-weight: 700;
            background: #333; /* Estilo oscuro según captura */
            border-bottom: 1px solid #000;
        }

        .table-custom td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 15px;
            color: #334155;
            vertical-align: middle;
        }

        .empty-results {
            text-align: center;
            padding: 3rem;
            color: #64748b;
            font-style: italic;
        }

        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 1.5rem;
        }
        .page-link {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #475569;
            text-decoration: none;
            font-size: 0.85rem;
            border-radius: 4px;
        }
        .page-link:hover {
            background: #f1f5f9;
        }

    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="breadcrumb">
                <a href="dashboard.php">Inicio</a> / 
                <a href="formacion_profesional.php">Formación</a> / 
                <a href="informes.php">Informes</a> / 
                Cambios de estado de las inscripciones
            </div>

            <div class="page-header-title">
                <h1>Formación</h1>
                <h2>Informe de cambios de estado de las inscripciones</h2>
            </div>

            <div class="search-card">
                <form class="search-form" method="GET">
                    
                    <div class="search-row-columns">
                        <div class="form-group-col">
                            <label>Desde</label>
                            <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group-col">
                            <label>hasta</label>
                            <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>">
                        </div>

                        <div class="form-group-col">
                            <label>&nbsp;</label>
                            <select name="estado" class="form-control">
                                <option value="">- Selecciona estado -</option>
                                <?php foreach($estados as $e): ?>
                                    <option value="<?= $e ?>" <?= (isset($_GET['estado']) && $_GET['estado'] == $e) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-buscar">Enviar</button>
                        <a href="informe_cambios_estado.php" class="btn-limpiar">Eliminar filtros</a>
                    </div>

                </form>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Grupo</th>
                            <th>Curso</th>
                            <th>Alumno/a</th>
                            <th>Usuario</th>
                            <th>Fecha y hora del cambio</th>
                            <th>Estado actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($resultados_simulados): ?>
                            <tr>
                                <td colspan="7" class="empty-results">Cargando resultados...</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-results">No se ha encontrado ningún registro.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <a href="#" class="page-link">Primero</a>
                <a href="#" class="page-link">Anterior</a>
                <a href="#" class="page-link">Siguiente</a>
                <a href="#" class="page-link">Último</a>
            </div>

        </main>
    </div>
</body>
</html>
