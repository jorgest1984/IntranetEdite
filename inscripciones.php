<?php
// inscripciones.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

// Cargar listas para filtros (placeholder por ahora)
$planes = [];
$convocatorias = [];

try {
    $planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

$current_page = 'inscripciones.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripciones - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-content { padding: 2rem; }
        
        .search-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .search-card-header {
            background: #f8fafc;
            padding: 0.75rem 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            text-align: center;
        }
        .search-card-header h2 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .search-form { padding: 1.5rem; }
        
        .form-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px 10px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1e3a8a;
            white-space: nowrap;
        }
        .form-control {
            padding: 0.4rem 0.6rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #f8fafc;
            width: 100%;
            box-sizing: border-box;
        }

        .w-25 { width: calc(25% - 10px); }
        .w-33 { width: calc(33.33% - 10px); }
        .w-50 { width: calc(50% - 10px); }
        .w-100 { width: 100%; }

        .btn-search {
            padding: 0.5rem 2rem;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        .btn-search:hover { background: #e2e8f0; }

        .results-section {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .results-header {
            background: #fff;
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .results-header h2 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: #b91c1c;
            text-transform: uppercase;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        .table-custom th {
            background: #1e293b;
            color: #fff;
            padding: 0.75rem 0.5rem;
            text-align: left;
        }
        .table-custom td {
            padding: 0.6rem 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="search-card">
            <div class="search-card-header">
                <h2>INSCRIPCIONES - FILTROS</h2>
            </div>
            <form class="search-form" method="GET">
                <div class="form-grid">
                    <div class="form-group w-50">
                        <label>Alumno:</label>
                        <input type="text" name="alumno" class="form-control" placeholder="Nombre o DNI...">
                    </div>
                    <div class="form-group w-50">
                        <label>Curso:</label>
                        <input type="text" name="curso" class="form-control" placeholder="Nombre del curso...">
                    </div>
                    <div class="form-group w-33">
                        <label>Estado:</label>
                        <select name="estado" class="form-control">
                            <option value="">Todos</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Admitido">Admitido</option>
                            <option value="Rechazado">Rechazado</option>
                        </select>
                    </div>
                    <div class="form-group w-33">
                        <label>Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach ($convocatorias as $conv): ?>
                                <option value="<?= $conv['id'] ?>"><?= htmlspecialchars($conv['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group w-33">
                        <label>Plan:</label>
                        <select name="plan_id" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="text-align: center;">
                    <button type="submit" class="btn-search">Buscar Inscripciones</button>
                </div>
            </form>
        </div>

        <div class="results-section">
            <div class="results-header">
                <h2>LISTADO DE INSCRIPCIONES</h2>
            </div>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th>Convocatoria</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                            Utilice los filtros para realizar una búsqueda.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
