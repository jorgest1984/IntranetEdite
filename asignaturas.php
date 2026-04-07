<?php
// asignaturas.php
require_once 'includes/auth.php'; // Verifica login y permisos

// Fetch from database
try {
    $stmt = $pdo->query("SELECT id, titulo, num_accion, abreviatura, id_plataforma, modalidad, nivel, duracion FROM acciones_formativas ORDER BY id DESC");
    $asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $asignaturas = [];
    $error = "Error al cargar datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignaturas (Abuela) - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .search-container-fp {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .search-header-fp {
            background: var(--primary-color); /* Rojo corporativo Edite */
            color: white;
            padding: 10px 20px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .search-form-fp {
            padding: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            justify-content: center;
        }

        .form-group-fp {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-label-fp {
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e293b;
            white-space: nowrap;
        }

        .form-select-fp {
            padding: 8px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #f8fafc;
            min-width: 250px;
            outline: none;
        }

        .btn-search-fp {
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            padding: 8px 30px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-search-fp:hover {
            background: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Tabla de Resultados */
        .results-container-fp {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .results-header-fp {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-title-fp {
            color: var(--primary-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .table-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #0369a1;
        }

        .table-fp {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .table-fp th {
            background: #f1f5f9;
            color: #0369a1;
            padding: 12px 10px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            border-bottom: 2px solid #cbd5e1;
            font-weight: 700;
            white-space: nowrap;
        }

        .table-fp td {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            color: #334155;
        }

        .header-with-icon {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .sort-icon {
            opacity: 0.5;
            width: 14px;
            height: 14px;
        }

        .table-fp tr:hover td {
            background: #f0f9ff;
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            color: #64748b;
            text-decoration: none;
        }
        .btn-action:hover {
            background: #f8fafc;
            color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn-action.delete:hover {
            color: #ef4444;
            border-color: #ef4444;
        }

    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="fp-layout">
            <?php include 'includes/fp_sidebar.php'; ?>
            <section class="fp-content-main">
        <header class="page-header">
            <div class="page-title" style="display: flex; align-items: center; gap: 20px; justify-content: space-between; width: 100%;">
                <div>
                    <h1>Asignaturas / Acciones "Abuela"</h1>
                    <p>Gestión de acciones formativas de primer nivel vinculadas a títulos</p>
                </div>
                <a href="formacion_profesional.php" class="btn-fp" style="display: flex; align-items: center; gap: 8px; text-decoration: none; background: var(--primary-color); color: white; border-radius: 0; padding: 6px 12px; font-weight: 700; font-size: 0.75rem; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2); transition: all 0.2s; border: 1px solid #b91c1c;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    VOLVER
                </a>
            </div>
        </header>

        <!-- Buscador -->
        <section class="search-container-fp">
            <div class="search-header-fp">Acciones/Asignaturas de Títulos Formativos - Campos de Búsqueda</div>
            <form class="search-form-fp">
                <div class="form-group-fp">
                    <label class="form-label-fp">Curso:</label>
                    <select class="form-select-fp">
                        <option value="">Seleccione el curso...</option>
                        <option>Técnico en Emergencias Sanitarias</option>
                        <option>Técnico Superior en Educación Infantil</option>
                    </select>
                </div>
                <div class="form-group-fp">
                    <label class="form-label-fp">Modalidad:</label>
                    <select class="form-select-fp" style="min-width: 150px;">
                        <option value="">Cualquiera</option>
                        <option>Presencial</option>
                        <option>Distancia</option>
                        <option>Teleformación</option>
                    </select>
                </div>
                <button type="button" class="btn-search-fp">Buscar</button>
            </form>
        </section>

        <!-- Resultados -->
        <section class="results-container-fp">
            <div class="results-header-fp">
                <div class="table-controls">
                    Ordenar múltiple <input type="checkbox">
                </div>
                <div class="results-title-fp">Resultado de la Búsqueda</div>
                <div style="width: 100px;"></div> <!-- Spacer -->
            </div>

            <div style="overflow-x: auto;">
                <table class="table-fp">
                    <thead>
                        <tr>
                            <th>
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Título formativo
                                </div>
                            </th>
                            <th>
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Curso
                                </div>
                            </th>
                            <th>
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Acción
                                </div>
                            </th>
                            <th>
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Abreviatura
                                </div>
                            </th>
                            <th>
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Código externo
                                </div>
                            </th>
                            <th>
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Modalidad
                                </div>
                            </th>
                            <th>
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Nivel
                                </div>
                            </th>
                            <th style="border-right: none;">
                                <div class="header-with-icon">
                                    <svg class="sort-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="8 12 12 16 16 12"></polyline><line x1="12" y1="8" x2="12" y2="16"></line></svg>
                                    Horas
                                </div>
                            </th>
                            <th style="border-right: none; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asignaturas as $a): ?>
                        <tr>
                            <td style="font-weight: 500;"><?= htmlspecialchars($a['titulo'] ?? '-') ?></td>
                            <td style="color: #0369a1; font-weight: 600;"><?= htmlspecialchars($a['titulo'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['num_accion'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['abreviatura'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['id_plataforma'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['modalidad'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['nivel'] ?? '-') ?></td>
                            <td style="font-weight: 700;"><?= htmlspecialchars($a['duracion'] ?? '0') ?>h</td>
                            <td style="border-right: none;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="ficha_accion_formativa.php?id=<?= $a['id'] ?>" class="btn-action" title="Editar">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <button onclick="confirmDelete(<?= $a['id'] ?>)" class="btn-action delete" title="Borrar">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
            </section>
        </div>
    </main>
</div>

<script>
function confirmDelete(id) {
    if (confirm('¿Estás seguro de que deseas eliminar esta acción formativa? Esta acción no se puede deshacer.')) {
        window.location.href = 'borrar_asignatura.php?id=' + id;
    }
}
</script>
</body>
</html>
