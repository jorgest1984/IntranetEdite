<?php
// informe_grupos.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'informe_grupos.php';

// Dummy data
$convocatorias = [
    ['id' => 1, 'nombre' => 'Convocatoria Estatal 2024'],
    ['id' => 2, 'nombre' => 'Convocatoria Autonómica Madrid']
];
$planes = [
    ['id' => 1, 'nombre' => 'Plan de Transformación Digital'],
    ['id' => 2, 'nombre' => 'Plan Transversal de Idiomas']
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Grupos - <?= APP_NAME ?></title>
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

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header-custom {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-gray);
        }

        .search-form {
            padding: 1.5rem;
        }

        .search-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group label {
            font-weight: 700;
            color: var(--label-blue);
            font-size: 0.9rem;
            min-width: 140px;
            text-align: right;
        }

        .form-control {
            font-size: 0.9rem;
            padding: 6px 10px;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            background: #fff;
            min-width: 300px;
        }
        
        input[type="date"].form-control {
            min-width: 150px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            padding-left: 150px; /* Aligned with inputs */
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
            background: #fff;
            color: #ef4444;
            border: 1px solid #ef4444;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-limpiar:hover { background: #fef2f2; }

        /* Contenedor de tabla con scroll horizontal */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 1.5rem;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
        }

        .table-custom {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .table-custom th {
            border-bottom: 2px solid var(--border-gray);
            padding: 10px 8px;
            text-align: left;
            color: #fff;
            font-weight: 700;
            background: #333; /* Cabecera oscura según captura */
            white-space: nowrap;
        }

        .table-custom th .sort-icon {
            display: inline-block;
            margin-left: 5px;
            opacity: 0.5;
        }

        .table-custom td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
            white-space: nowrap;
            color: #334155;
        }

        .export-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .btn-export {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #fff;
            border: none;
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-export.excel { background: #0078d4; } /* Blue excel variant for this page */
        .btn-export.excel:hover { background: #005a9e; }
        
        .btn-export.csv { background: #0078d4; }
        .btn-export.csv:hover { background: #005a9e; }
        
        .btn-export.json { background: #0078d4; }
        .btn-export.json:hover { background: #005a9e; }

    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="search-card">
                <form class="search-form" method="GET">
                    <div class="search-row">
                        <div class="form-group">
                            <label>Convocatoria</label>
                            <select name="convocatoria" class="form-control">
                                <option value="">-</option>
                                <?php foreach($convocatorias as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="search-row">
                        <div class="form-group">
                            <label>Plan</label>
                            <select name="plan" class="form-control">
                                <option value="">-</option>
                                <?php foreach($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="search-row">
                        <div class="form-group">
                            <label>Fecha de inicio desde</label>
                            <input type="date" name="inicio_desde" class="form-control">
                        </div>
                        <div class="form-group">
                            <label style="min-width:auto; margin: 0 10px;">hasta</label>
                            <input type="date" name="inicio_hasta" class="form-control">
                        </div>
                    </div>

                    <div class="search-row">
                        <div class="form-group">
                            <label>Fecha de fin desde</label>
                            <input type="date" name="fin_desde" class="form-control">
                        </div>
                        <div class="form-group">
                            <label style="min-width:auto; margin: 0 10px;">hasta</label>
                            <input type="date" name="fin_hasta" class="form-control">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-buscar">Enviar</button>
                        <a href="informe_grupos.php" class="btn-limpiar" style="text-decoration: none; display: inline-flex; align-items: center;">Eliminar filtros</a>
                    </div>
                </form>
            </div>

            <?php if (isset($_GET['convocatoria']) || isset($_GET['plan']) || isset($_GET['inicio_desde'])): ?>
            
            <div style="background: #333; color: white; padding: 15px 20px; font-size: 1.5rem; font-weight: 600; border-radius: 4px 4px 0 0;">
                Informe de grupos
            </div>
            <div class="table-responsive" style="margin-bottom: 10px; border-radius: 0 0 4px 4px; border-top: none;">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Plan <span class="sort-icon">↕</span></th>
                            <th>MODALIDAD <span class="sort-icon">↕</span></th>
                            <th>nA <span class="sort-icon">↕</span></th>
                            <th>nG <span class="sort-icon">↕</span></th>
                            <th>ACCIÓN FORMATIVA <span class="sort-icon">↕</span></th>
                            <th>PROVINCIA <span class="sort-icon">↕</span></th>
                            <th>TUTOR <span class="sort-icon">↕</span></th>
                            <th>INICIO <span class="sort-icon">↕</span></th>
                            <th>FIN <span class="sort-icon">↕</span></th>
                            <th>HORAS <span class="sort-icon">↕</span></th>
                            <th>PART <span class="sort-icon">↕</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 2rem; color: #64748b;">
                                Sin resultados para los filtros seleccionados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="export-actions">
                <button class="btn-export excel">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg> 
                    Exportar a Excel
                </button>
                <button class="btn-export csv">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg> 
                    Exportar a CSV
                </button>
                <button class="btn-export json">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg> 
                    Exportar a JSON
                </button>
            </div>

            <?php endif; ?>

        </main>
    </div>
</body>
</html>
